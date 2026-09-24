<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://sidrene-cijene.com.hr/
 * @see https://brendigo.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sidrena_Activator {
	const DB_VERSION = '0.1.0';

	public static function activate() {
		self::install_schema();
		self::migrate_options();
		self::ensure_capabilities();

		if ( false === get_option( 'sidrena_settings', false ) ) {
			add_option( 'sidrena_settings', Sidrena_Utils::defaults(), '', false );
		}
		if ( false === get_option( 'sidrena_locations', false ) ) {
			add_option( 'sidrena_locations', Sidrena_Utils::locations(), '', false );
		}

		$paths = Sidrena_Utils::upload_paths();
		wp_mkdir_p( $paths['archive_dir'] );
		wp_mkdir_p( $paths['snapshot_dir'] );
		self::protect_upload_directory( $paths['base_dir'] );
		self::protect_upload_directory( $paths['archive_dir'] );
		self::protect_upload_directory( $paths['snapshot_dir'] );

		self::ensure_schedules();
		if ( class_exists( 'Sidrena_Public' ) ) {
			Sidrena_Public::instance()->register_rewrites();
			Sidrena_Public::ensure_public_page();
			flush_rewrite_rules( false );
		}

		update_option( 'sidrena_db_version', self::DB_VERSION, false );
	}

	public static function maybe_upgrade() {
		$current = (string) get_option( 'sidrena_db_version', '' );
		self::ensure_capabilities();
		if ( self::DB_VERSION === $current ) {
			self::migrate_options();
			self::ensure_schedules();
			return;
		}
		self::install_schema();
		self::migrate_options();
		self::ensure_schedules();
		update_option( 'sidrena_db_version', self::DB_VERSION, false );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'sidrena_daily_generation' );
		wp_clear_scheduled_hook( 'sidrena_queued_generation' );
		wp_clear_scheduled_hook( 'sidrena_publication_watch' );
		wp_clear_scheduled_hook( 'sidrena_history_seed' );
		flush_rewrite_rules( false );
	}

	private static function ensure_capabilities() {
		$roles = function_exists( 'wp_roles' ) ? wp_roles() : null;
		if ( $roles && ! empty( $roles->role_objects ) && is_array( $roles->role_objects ) ) {
			foreach ( $roles->role_objects as $role ) {
				if ( ! is_object( $role ) || ! is_callable( array( $role, 'has_cap' ) ) || ! is_callable( array( $role, 'add_cap' ) ) ) {
					continue;
				}
				$elevated = $role->has_cap( 'manage_options' ) || $role->has_cap( 'manage_sidrena' );
				if ( Sidrena_Utils::is_woocommerce_edition() && $role->has_cap( 'manage_woocommerce' ) ) {
					$elevated = true;
				}
				if ( $elevated ) {
					if ( ! $role->has_cap( 'manage_sidrena' ) ) {
						$role->add_cap( 'manage_sidrena' );
					}
				}
			}
			return;
		}

		// Compatibility fallback for unusual WordPress bootstraps.
		$fallback_roles = Sidrena_Utils::is_woocommerce_edition() ? array( 'administrator', 'shop_manager' ) : array( 'administrator' );
		foreach ( $fallback_roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && ! $role->has_cap( 'manage_sidrena' ) ) {
				$role->add_cap( 'manage_sidrena' );
			}
		}
	}

	private static function ensure_schedules() {
		if ( ! wp_next_scheduled( 'sidrena_daily_generation' ) ) {
			wp_schedule_event( Sidrena_Utils::schedule_timestamp(), 'daily', 'sidrena_daily_generation' );
		}
		if ( ! wp_next_scheduled( 'sidrena_publication_watch' ) ) {
			wp_schedule_event( self::publication_watch_timestamp(), 'hourly', 'sidrena_publication_watch' );
		}

		if ( Sidrena_Utils::is_woocommerce_active() ) {
			if ( ! get_option( 'sidrena_history_seeded_at' ) && ! wp_next_scheduled( 'sidrena_history_seed' ) ) {
				wp_schedule_single_event( time() + 30, 'sidrena_history_seed' );
			}
		} elseif ( wp_next_scheduled( 'sidrena_history_seed' ) ) {
			wp_clear_scheduled_hook( 'sidrena_history_seed' );
		}
	}


	private static function publication_watch_timestamp() {
		$timezone = wp_timezone();
		$now      = new DateTimeImmutable( 'now', $timezone );
		$next     = $now->setTime( 5, 15, 0 );
		if ( $next <= $now ) {
			$settings = Sidrena_Utils::settings();
			$target   = isset( $settings['generation_time'] ) ? (string) $settings['generation_time'] : '06:30';
			if ( $now->format( 'H:i' ) >= $target && $now->format( 'H:i' ) < '08:00' ) {
				return time() + 120;
			}
			$next = $next->modify( '+1 day' );
		}
		return $next->getTimestamp();
	}


	private static function install_schema() {
		self::create_service_history_table();
		self::create_audit_table();

		if ( Sidrena_Utils::is_woocommerce_edition() ) {
			self::create_history_table();
			self::create_location_table();
			self::create_location_history_table();
		}
	}

	private static function create_history_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $wpdb->prefix . 'sidrena_price_history';
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			price decimal(20,6) NULL,
			regular_price decimal(20,6) NULL,
			sale_price decimal(20,6) NULL,
			recorded_at datetime NOT NULL,
			source varchar(32) NOT NULL DEFAULT 'save',
			PRIMARY KEY (id),
			KEY product_date (product_id, recorded_at),
			KEY variation_date (variation_id, recorded_at)
		) {$charset};";
		dbDelta( $sql );
	}


	private static function create_service_history_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $wpdb->prefix . 'sidrena_service_price_history';
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			service_id bigint(20) unsigned NOT NULL,
			price decimal(20,6) NULL,
			recorded_at datetime NOT NULL,
			source varchar(32) NOT NULL DEFAULT 'save',
			PRIMARY KEY (id),
			KEY service_date (service_id, recorded_at)
		) {$charset};";
		dbDelta( $sql );
	}

	private static function create_location_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $wpdb->prefix . 'sidrena_location_products';
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			location_id varchar(191) NOT NULL,
			product_id bigint(20) unsigned NOT NULL,
			variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			price decimal(20,6) NULL,
			anchor_price decimal(20,6) NULL,
			availability varchar(16) NOT NULL DEFAULT '',
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY location_item (location_id, product_id, variation_id),
			KEY product_lookup (product_id, variation_id)
		) {$charset};";
		dbDelta( $sql );
	}


	private static function create_location_history_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $wpdb->prefix . 'sidrena_location_price_history';
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			location_id varchar(191) NOT NULL,
			product_id bigint(20) unsigned NOT NULL,
			variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			price decimal(20,6) NULL,
			anchor_price decimal(20,6) NULL,
			availability varchar(16) NOT NULL DEFAULT '',
			recorded_at datetime NOT NULL,
			source varchar(32) NOT NULL DEFAULT 'import',
			PRIMARY KEY (id),
			KEY location_item_date (location_id, product_id, variation_id, recorded_at),
			KEY item_date (product_id, variation_id, recorded_at)
		) {$charset};";
		dbDelta( $sql );
	}


	private static function create_audit_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $wpdb->prefix . 'sidrena_audit_log';
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(64) NOT NULL,
			status varchar(16) NOT NULL DEFAULT 'info',
			message text NOT NULL,
			context longtext NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY event_date (event_type, created_at),
			KEY status_date (status, created_at)
		) {$charset};";
		dbDelta( $sql );
	}

	private static function migrate_options() {
		$settings = get_option( 'sidrena_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( empty( $settings['fmcg_ref_date'] ) && ! empty( $settings['fmsid_ref_date'] ) ) {
			$settings['fmcg_ref_date'] = Sidrena_Utils::sanitize_date( $settings['fmsid_ref_date'], '2025-05-02' );
		}
		unset( $settings['fmsid_ref_date'] );

		$settings = wp_parse_args( $settings, Sidrena_Utils::defaults() );
		$settings['retention_days'] = max( 30, absint( $settings['retention_days'] ) );
		update_option( 'sidrena_settings', $settings, false );
	}

	private static function protect_upload_directory( $base ) {
		if ( ! is_dir( $base ) ) {
			return;
		}
		$index = trailingslashit( $base ) . 'index.html';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '<!doctype html><meta charset="utf-8"><title>Sidrena</title>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}
}
