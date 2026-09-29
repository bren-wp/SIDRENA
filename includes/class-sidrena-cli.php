<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional WP-CLI support for production cron and diagnostics.
 */
final class Sidrena_CLI {
	public static function register() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		$instance = new self();
		WP_CLI::add_command( 'sidrena generate', array( $instance, 'generate' ) );
		WP_CLI::add_command( 'sidrena publish', array( $instance, 'publish' ) );
		WP_CLI::add_command( 'sidrena status', array( $instance, 'status' ) );
		WP_CLI::add_command( 'sidrena audit', array( $instance, 'audit' ) );

		if ( Sidrena_Utils::is_woocommerce_edition() ) {
			WP_CLI::add_command( 'sidrena fill', array( $instance, 'fill' ) );
		}
	}

	/**
	 * Generate all configured CSV/XML pricelists immediately.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sidrena generate
	 */
	public function generate() {
		$ok   = Sidrena_Pricelist::instance()->generate_all();
		$last = get_option( 'sidrena_last_run', array() );
		if ( $ok ) {
			WP_CLI::success( sprintf( 'Generiranje dovršeno. Datoteka: %d', isset( $last['files'] ) ? absint( $last['files'] ) : 0 ) );
			return;
		}
		$errors = isset( $last['errors'] ) && is_array( $last['errors'] ) ? implode( '; ', $last['errors'] ) : 'Nepoznata pogreška.';
		WP_CLI::error( $errors );
	}


	/**
	 * Publish today's archive and refresh the stable current price list.
	 *
	 * Intended for a real server cron when automation_mode=external.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sidrena publish
	 */
	public function publish() {
		$ok   = Sidrena_Pricelist::instance()->publish_daily_archive();
		$last = get_option( 'sidrena_last_run', array() );
		if ( $ok ) {
			WP_CLI::success( sprintf( 'Dnevna SIDRENA objava dovršena. Datoteka: %d', isset( $last['files'] ) ? absint( $last['files'] ) : 0 ) );
			return;
		}
		$errors = isset( $last['errors'] ) && is_array( $last['errors'] ) ? implode( '; ', $last['errors'] ) : 'Nepoznata pogreška.';
		WP_CLI::error( $errors );
	}

	/**
	 * Legacy compatibility command.
	 *
	 * Automatic backfilling from the current WooCommerce price is intentionally
	 * disabled. A present-day catalog price is not evidence of the statutory
	 * Sidrena price on 10.09.2026. / 02.05.2025. and must never manufacture a
	 * compliance value or a custom legal date.
	 */
	public function fill( $args = array(), $assoc_args = array() ) {
		unset( $args, $assoc_args );
		WP_CLI::error( 'Automatsko popunjavanje sidrene cijene iz trenutačne WooCommerce cijene onemogućeno je radi compliance sigurnosti. Koristite provjerenu povijesnu evidenciju/CSV ili automatski snapshot stvarno novouvedene stavke pri prvom objavljivanju.' );
	}

	/**
	 * Show operational status.
	 */
	public function status() {
		$settings = Sidrena_Utils::settings();
		$last     = get_option( 'sidrena_last_run', array() );
		$next     = wp_next_scheduled( 'sidrena_daily_generation' );
		$rows     = array(
			array(
				'key'   => 'version',
				'value' => SIDRENA_VERSION,
			),
			array(
				'key'   => 'generation_time',
				'value' => $settings['generation_time'],
			),
			array(
				'key'   => 'automation_mode',
				'value' => $settings['automation_mode'] ?? 'wp_cron',
			),
			array(
				'key'   => 'retention_days',
				'value' => max( 30, absint( $settings['retention_days'] ) ),
			),
			array(
				'key'   => 'next_run',
				'value' => $next ? wp_date( DATE_ATOM, $next ) : 'not-scheduled',
			),
			array(
				'key'   => 'last_run',
				'value' => ! empty( $last['generated_at'] ) ? $last['generated_at'] : 'never',
			),
			array(
				'key'   => 'edition',
				'value' => Sidrena_Utils::edition(),
			),
			array(
				'key'   => 'audit_rows',
				'value' => Sidrena_Audit::count_rows(),
			),
			array(
				'key'   => 'history_rows',
				'value' => ( class_exists( 'Sidrena_History' ) ? Sidrena_History::count_rows() : 0 )
					+ Sidrena_Service_History::count_rows()
					+ ( class_exists( 'Sidrena_Location_History' ) ? Sidrena_Location_History::count_rows() : 0 ),
			),
			array(
				'key'   => 'public_files',
				'value' => count( Sidrena_Utils::public_index() ),
			),
			array(
				'key'   => 'archive_files',
				'value' => count( Sidrena_Utils::archive_index() ),
			),
			array(
				'key'   => 'strict_publication',
				'value' => $settings['strict_publication'],
			),
		);
		WP_CLI\Utils\format_items( 'table', $rows, array( 'key', 'value' ) );
	}

	/**
	 * Print recent local audit events.
	 *
	 * [--limit=<number>]
	 * : Maximum number of rows. Default 30.
	 */
	public function audit( $args, $assoc_args ) {
		$limit = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 30;
		$rows  = Sidrena_Audit::recent( $limit );
		WP_CLI\Utils\format_items( 'table', $rows, array( 'created_at', 'event_type', 'status', 'message' ) );
	}
}
