<?php
/**
 * Minimal compatibility bridge for updates from the WordPress.org 1.0.x line.
 *
 * The current plugin keeps its own data model. Only the small set of values
 * needed to preserve the visible product pricing workflow is copied. Legacy
 * history, audit, REST indexes, services, locations and custom tables are
 * intentionally not imported or deleted.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Minimal, non-destructive update bridge.
 */
final class Brendigo_Sidrena_Cijena_Migration {

	const OPTION_VERSION = 'brendigo_sidrena_cijena_migration_version';
	const VERSION        = '1.2.0-wporg-bridge-1';

	/**
	 * Run once when an older Brendigo Sidrena Cijena installation is detected.
	 */
	public static function maybe_migrate() {
		if ( self::VERSION === get_option( self::OPTION_VERSION, '' ) ) {
			return;
		}

		self::run();
	}

	/**
	 * Copy only the essential 1.0.x values used by the new plugin.
	 */
	public static function run() {
		if ( ! self::has_wporg_1x_data() ) {
			update_option( self::OPTION_VERSION, self::VERSION, false );
			return;
		}

		self::migrate_product_meta();
		self::migrate_basic_settings();
		self::clear_legacy_cron();

		// Keep every legacy source option/table untouched as a non-destructive fallback.
		update_option( self::OPTION_VERSION, self::VERSION, false );
	}

	/**
	 * Detect the former WordPress.org implementation without scanning all data.
	 *
	 * @return bool
	 */
	private static function has_wporg_1x_data() {
		if ( false !== get_option( 'sidrena_plugin_version', false ) ) {
			return true;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration uses prepared bulk SQL to avoid loading entire legacy datasets into PHP.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1",
				'_sidrena_anchor_price'
			)
		);

		return ! empty( $found );
	}

	/**
	 * Preserve the core product fields customers already entered in 1.0.x.
	 */
	private static function migrate_product_meta() {
		$map = array(
			'_sidrena_anchor_price'      => BRENDIGO_SIDRENA_CIJENA_META_PRICE,
			'_sidrena_anchor_date'       => BRENDIGO_SIDRENA_CIJENA_META_DATE,
			'_sidrena_unit'              => BRENDIGO_SIDRENA_CIJENA_META_JEDINICA,
			'_sidrena_unit_price'        => BRENDIGO_SIDRENA_CIJENA_META_JEDINICA_CIJENA,
			'_sidrena_sale_name'         => BRENDIGO_SIDRENA_CIJENA_META_OBLIK,
			'_sidrena_barcode'           => BRENDIGO_SIDRENA_CIJENA_META_BARCODE,
			'_sidrena_cjenik_visibility' => BRENDIGO_SIDRENA_CIJENA_META_VISIBILITY,
		);

		foreach ( $map as $source => $target ) {
			self::copy_meta_if_empty( $source, $target );
		}

		self::migrate_unit_price_flag();
	}

	/**
	 * A 1.0.x product explicitly marked as requiring unit pricing keeps that flag.
	 */
	private static function migrate_unit_price_flag() {
		global $wpdb;

		$target = BRENDIGO_SIDRENA_CIJENA_META_CJM;
		$source = '_sidrena_unit_price_status';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration uses prepared bulk SQL to avoid loading entire legacy datasets into PHP.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} target
				 INNER JOIN {$wpdb->postmeta} source ON source.post_id = target.post_id AND source.meta_key = %s
				 SET target.meta_value = 'yes'
				 WHERE target.meta_key = %s AND target.meta_value = '' AND source.meta_value = 'required'",
				$source,
				$target
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration uses prepared bulk SQL to avoid loading entire legacy datasets into PHP.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->postmeta} ( post_id, meta_key, meta_value )
				 SELECT source.post_id, %s, 'yes'
				 FROM {$wpdb->postmeta} source
				 LEFT JOIN {$wpdb->postmeta} target ON target.post_id = source.post_id AND target.meta_key = %s
				 WHERE source.meta_key = %s AND source.meta_value = 'required' AND target.meta_id IS NULL",
				$target,
				$target,
				$source
			)
		);
	}

	/**
	 * Copy one post-meta key only where the new key is missing or empty.
	 *
	 * @param string $source Legacy key.
	 * @param string $target Current key.
	 */
	private static function copy_meta_if_empty( $source, $target ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration uses prepared bulk SQL to avoid loading entire legacy datasets into PHP.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} target
				 INNER JOIN {$wpdb->postmeta} source ON source.post_id = target.post_id AND source.meta_key = %s
				 SET target.meta_value = source.meta_value
				 WHERE target.meta_key = %s AND target.meta_value = '' AND source.meta_value <> ''",
				$source,
				$target
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration uses prepared bulk SQL to avoid loading entire legacy datasets into PHP.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->postmeta} ( post_id, meta_key, meta_value )
				 SELECT source.post_id, %s, source.meta_value
				 FROM {$wpdb->postmeta} source
				 LEFT JOIN {$wpdb->postmeta} target ON target.post_id = source.post_id AND target.meta_key = %s
				 WHERE source.meta_key = %s AND source.meta_value <> '' AND target.meta_id IS NULL",
				$target,
				$target,
				$source
			)
		);
	}

	/**
	 * Import only basic presentation/publication choices from 1.0.x.
	 */
	private static function migrate_basic_settings() {
		$legacy = get_option( 'sidrena_settings', array() );
		if ( ! is_array( $legacy ) || empty( $legacy ) ) {
			return;
		}

		$current = get_option( Brendigo_Sidrena_Cijena_Settings::OPTION_KEY, array() );
		$current = is_array( $current ) ? $current : array();
		$changed = false;

		if ( ! isset( $current['auto_display'] ) && isset( $legacy['display_anchor'] ) ) {
			$current['auto_display'] = 'yes' === $legacy['display_anchor'] ? 'yes' : 'no';
			$changed = true;
		}

		if ( ! isset( $current['public_cjenik'] ) && isset( $legacy['enable_public_html'] ) ) {
			$current['public_cjenik'] = 'yes' === $legacy['enable_public_html'] ? 'yes' : 'no';
			$changed = true;
		}

		if ( ! isset( $current['export_format'] ) ) {
			if ( ! empty( $legacy['generate_xml'] ) && 'yes' === $legacy['generate_xml'] ) {
				$current['export_format'] = 'xml';
				$changed = true;
			} elseif ( ! empty( $legacy['generate_csv'] ) && 'yes' === $legacy['generate_csv'] ) {
				$current['export_format'] = 'csv';
				$changed = true;
			}
		}

		if ( self::legacy_requires_unit_pricing() && ! isset( $current['enable_cjm'] ) ) {
			$current['enable_cjm'] = 'yes';
			$changed = true;
		}

		if ( $changed ) {
			update_option( Brendigo_Sidrena_Cijena_Settings::OPTION_KEY, $current, false );
		}
	}

	/**
	 * Whether 1.0.x contains at least one product explicitly requiring unit pricing.
	 *
	 * @return bool
	 */
	private static function legacy_requires_unit_pricing() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration uses prepared bulk SQL to avoid loading entire legacy datasets into PHP.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				'_sidrena_unit_price_status',
				'required'
			)
		);
		return ! empty( $found );
	}

	/**
	 * Old scheduled hooks would otherwise remain in wp-cron after a file update.
	 */
	private static function clear_legacy_cron() {
		foreach (
			array(
				'sidrena_daily_generation',
				'sidrena_queued_generation',
				'sidrena_publication_watch',
				'sidrena_history_seed',
				'sidrena_standalone_sync_batch',
			)
			as $hook
		) {
			wp_clear_scheduled_hook( $hook );
		}
	}
}
