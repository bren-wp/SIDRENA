<?php
/**
 * WP-CLI: fill empty sidrena cijena from regular price.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * `wp sidrena-cijena` commands.
 */
class Brendigo_Sidrena_Cijena_CLI {

	/**
	 * Fill empty sidrena cijena fields from the current regular price.
	 *
	 * Existing sidrena values are never overwritten. Variable/grouped parents
	 * are skipped; their variations are filled.
	 *
	 * Default path is a SQL bulk copy (seconds, not hours). The admin button
	 * walks the catalog in short AJAX steps and resumes if a request is killed.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Count rows that would be filled, without writing.
	 *
	 * [--today]
	 * : Also write today's date as the per-product reference date when that field is empty.
	 *   Use for products first put on sale after 10.09.2026.
	 *
	 * [--safe]
	 * : Use WooCommerce product objects instead of SQL (same as the admin button, slower).
	 *
	 * [--batch-size=<n>]
	 * : Products per page for --safe. Default 250.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sidrena-cijena status
	 *     wp sidrena-cijena fill --dry-run
	 *     wp sidrena-cijena fill
	 *     wp sidrena-cijena fill --today
	 *
	 * @when after_wp_load
	 * @param array<int,string>    $args       Unused.
	 * @param array<string,string> $assoc_args Flags.
	 */
	public function fill( $args, $assoc_args ) {
		unset( $args );

		if ( ! class_exists( 'WooCommerce' ) ) {
			WP_CLI::error( 'WooCommerce is not active.' );
		}

		$dry_run    = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$safe       = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'safe', false );
		$today      = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'today', false );
		$batch_size = absint( WP_CLI\Utils\get_flag_value( $assoc_args, 'batch-size', 250 ) );
		if ( $batch_size < 1 ) {
			$batch_size = 250;
		}

		$stamp_date = $today ? Brendigo_Sidrena_Cijena_Product_Meta::today_date() : '';
		if ( $today ) {
			WP_CLI::log( sprintf( 'Reference date for newly filled rows: %s', $stamp_date ) );
		}

		$stats = Brendigo_Sidrena_Cijena_Activator::catalog_stats();
		WP_CLI::log(
			sprintf(
				'Catalog: eligible=%d filled=%d missing=%d',
				$stats['eligible'],
				$stats['filled'],
				$stats['missing']
			)
		);

		if ( 0 === (int) $stats['missing'] ) {
			WP_CLI::success( 'Nothing to fill. Every eligible product already has a sidrena cijena.' );
			return;
		}

		$automation = Brendigo_Sidrena_Cijena_Automation::instance();
		$previous   = $automation->suspend_rebuilds( true );
		try {
			$result = Brendigo_Sidrena_Cijena_Activator::fill_catalog(
				array(
					'dry_run'    => $dry_run,
					'fast'       => ! $safe,
					'batch_size' => $batch_size,
					'stamp_date' => $stamp_date,
					'on_page'    => static function ( $batch ) {
						WP_CLI::log(
							sprintf(
								'page %d processed=%d written=%d',
								(int) $batch['page'],
								(int) $batch['processed'],
								(int) $batch['written']
							)
						);
					},
				)
			);
		} finally {
			$automation->suspend_rebuilds( $previous );
		}

		if ( $dry_run ) {
			$would = $safe ? (int) $result['written'] : (int) $stats['missing'];
			WP_CLI::success(
				sprintf(
					'Dry-run: would fill %d empty sidrena field(s). Re-run without --dry-run to write.',
					$would
				)
			);
			return;
		}

		Brendigo_Sidrena_Cijena_Automation::instance()->queue_rebuild( 'cli-fill' );

		$after = Brendigo_Sidrena_Cijena_Activator::catalog_stats();
		WP_CLI::success(
			sprintf(
				'Filled %d sidrena field(s) (inserted=%d updated=%d). Remaining empty: %d.',
				(int) $result['written'],
				(int) $result['inserted'],
				(int) $result['updated'],
				(int) $after['missing']
			)
		);
	}

	/**
	 * Show how many products/variations already have a sidrena cijena.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sidrena-cijena status
	 *
	 * @when after_wp_load
	 * @param array<int,string>    $args       Unused.
	 * @param array<string,string> $assoc_args Unused.
	 */
	public function status( $args, $assoc_args ) {
		unset( $args, $assoc_args );

		if ( ! class_exists( 'WooCommerce' ) ) {
			WP_CLI::error( 'WooCommerce is not active.' );
		}

		$stats = Brendigo_Sidrena_Cijena_Activator::catalog_stats();
		WP_CLI::log(
			sprintf(
				'eligible=%d (products/variations with a regular price)',
				$stats['eligible']
			)
		);
		WP_CLI::log( sprintf( 'filled=%d', $stats['filled'] ) );
		WP_CLI::log( sprintf( 'missing=%d', $stats['missing'] ) );
		WP_CLI::success(
			sprintf(
				'%d of %d eligible items have a sidrena cijena.',
				$stats['filled'],
				$stats['eligible']
			)
		);
	}
	/**
	 * Regenerate the public XML/CSV immediately and archive the result.
	 * Useful from a real system cron when WP-Cron is disabled.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sidrena-cijena regenerate
	 *
	 * @when after_wp_load
	 * @param array<int,string>    $args       Unused.
	 * @param array<string,string> $assoc_args Unused.
	 */
	public function regenerate( $args, $assoc_args ) {
		unset( $args, $assoc_args );

		$export = Brendigo_Sidrena_Cijena_Export::instance();
		$result = $export->publish_public_catalog( 'cli' );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		$count = isset( $result['count'] ) ? (int) $result['count'] : 0;
		WP_CLI::success( sprintf( 'Public feed regenerated (%d row(s)).', $count ) );
	}

	/**
	 * Run automatic maintenance now and print the resulting health state.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sidrena-cijena health
	 *
	 * @when after_wp_load
	 * @param array<int,string>    $args       Unused.
	 * @param array<string,string> $assoc_args Unused.
	 */
	public function health( $args, $assoc_args ) {
		unset( $args, $assoc_args );

		$automation = Brendigo_Sidrena_Cijena_Automation::instance();
		if ( ! $automation->maintenance_enabled() ) {
			WP_CLI::warning( 'Automatic maintenance is disabled in plugin settings.' );
		}

		$state = $automation->run_health_check();
		if ( empty( $state ) ) {
			WP_CLI::success( 'Health check skipped because automatic maintenance is disabled.' );
			return;
		}

		WP_CLI::log( 'queued_rebuild=' . ( isset( $state['queued_rebuild'] ) ? $state['queued_rebuild'] : 'no' ) );
		WP_CLI::log( 'reason=' . ( isset( $state['last_reason'] ) && '' !== $state['last_reason'] ? $state['last_reason'] : 'none' ) );
		WP_CLI::log( 'daily_time=' . Brendigo_Sidrena_Cijena_Settings::instance()->daily_time() );
		WP_CLI::log( 'timezone=' . wp_timezone_string() );
		WP_CLI::log( 'next_daily=' . ( ! empty( $state['next_daily'] ) ? wp_date( 'c', (int) $state['next_daily'] ) : 'none' ) );
		$pending = $automation->get_pending_state();
		WP_CLI::log( 'pending_since=' . ( ! empty( $pending['since'] ) ? wp_date( 'c', (int) $pending['since'] ) : 'none' ) );
		WP_CLI::log( 'pending_reason_groups=' . ( isset( $pending['reasons'] ) && is_array( $pending['reasons'] ) ? count( $pending['reasons'] ) : 0 ) );
		$history = Brendigo_Sidrena_Cijena_Export::instance()->get_publication_history();
		if ( ! empty( $history[0] ) && is_array( $history[0] ) ) {
			$latest = $history[0];
			WP_CLI::log( 'last_publication_status=' . ( isset( $latest['status'] ) ? $latest['status'] : 'unknown' ) );
			WP_CLI::log( 'last_publication_source=' . ( isset( $latest['source'] ) ? $latest['source'] : 'unknown' ) );
		}
		WP_CLI::success( 'Automatic maintenance check completed.' );
	}

}
