<?php
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
		WP_CLI::add_command( 'sidrena', __CLASS__ );
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
	 * Show operational status.
	 */
	public function status() {
		$settings = Sidrena_Utils::settings();
		$last     = get_option( 'sidrena_last_run', array() );
		$next     = wp_next_scheduled( 'sidrena_daily_generation' );
		$rows = array(
			array( 'key' => 'version', 'value' => SIDRENA_VERSION ),
			array( 'key' => 'generation_time', 'value' => $settings['generation_time'] ),
			array( 'key' => 'retention_days', 'value' => max( 30, absint( $settings['retention_days'] ) ) ),
			array( 'key' => 'next_run', 'value' => $next ? wp_date( DATE_ATOM, $next ) : 'not-scheduled' ),
			array( 'key' => 'last_run', 'value' => ! empty( $last['generated_at'] ) ? $last['generated_at'] : 'never' ),
			array( 'key' => 'audit_rows', 'value' => Sidrena_Audit::count_rows() ),
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
