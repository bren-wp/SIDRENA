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
	 * Fill empty Sidrena anchor prices from the current WooCommerce regular price.
	 *
	 * Existing Sidrena values are never overwritten.
	 *
	 * ## OPTIONS
	 *
	 * [--today]
	 * : Also stores today's date as the custom reference date when the date is empty.
	 *
	 * [--dry-run]
	 * : Show how many rows would be changed without saving anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sidrena fill --dry-run
	 *     wp sidrena fill
	 *     wp sidrena fill --today
	 */
	public function fill( $args, $assoc_args ) {
		unset( $args );
		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			WP_CLI::error( 'WooCommerce nije aktivan.' );
		}

		$today   = ! empty( $assoc_args['today'] );
		$dry_run = ! empty( $assoc_args['dry-run'] );
		$page    = 1;
		$seen    = 0;
		$filled  = 0;
		$skipped = 0;
		$date    = wp_date( 'Y-m-d' );

		do {
			$query = new WC_Product_Query(
				array(
					'limit'   => 100,
					'page'    => $page,
					'status'  => array( 'publish', 'private', 'draft', 'pending' ),
					'return'  => 'objects',
					'orderby' => 'ID',
					'order'   => 'ASC',
				)
			);
			$products = $query->get_products();

			foreach ( $products as $product ) {
				$items = $product->is_type( 'variable' )
					? array_filter( array_map( 'wc_get_product', $product->get_children() ) )
					: array( $product );

				foreach ( $items as $item ) {
					if ( ! $item instanceof WC_Product || $item->is_type( 'variable' ) || $item->is_type( 'grouped' ) ) {
						continue;
					}
					++$seen;

					if ( '' !== get_post_meta( $item->get_id(), '_sidrena_anchor_price', true ) ) {
						++$skipped;
						continue;
					}

					$regular = $item->get_regular_price( 'edit' );
					if ( '' === $regular ) {
						++$skipped;
						continue;
					}

					++$filled;
					if ( $dry_run ) {
						continue;
					}

					update_post_meta( $item->get_id(), '_sidrena_anchor_price', wc_format_decimal( $regular ) );
					if ( $today && '' === get_post_meta( $item->get_id(), '_sidrena_anchor_date', true ) ) {
						update_post_meta( $item->get_id(), '_sidrena_anchor_date', $date );
						update_post_meta( $item->get_id(), '_sidrena_reference_group', 'custom' );
					}
				}
			}
			++$page;
		} while ( 100 === count( $products ) );

		if ( ! $dry_run && $filled ) {
			Sidrena_Pricelist::queue_regeneration();
			Sidrena_Audit::log(
				'cli_fill',
				'success',
				sprintf( 'WP-CLI popunio je %d praznih Sidrena cijena.', $filled ),
				array( 'today' => $today ? 'yes' : 'no', 'seen' => $seen, 'skipped' => $skipped )
			);
		}

		$message = sprintf(
			'%s Pregledano: %d, za popuniti/popunjeno: %d, preskočeno: %d.',
			$dry_run ? 'Probni pregled dovršen.' : 'Popunjavanje dovršeno.',
			$seen,
			$filled,
			$skipped
		);
		WP_CLI::success( $message );
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
			array( 'key' => 'edition', 'value' => Sidrena_Utils::edition() ),
			array( 'key' => 'audit_rows', 'value' => Sidrena_Audit::count_rows() ),
			array(
				'key'   => 'history_rows',
				'value' => ( class_exists( 'Sidrena_History' ) ? Sidrena_History::count_rows() : 0 )
					+ Sidrena_Service_History::count_rows()
					+ ( class_exists( 'Sidrena_Location_History' ) ? Sidrena_Location_History::count_rows() : 0 ),
			),
			array( 'key' => 'public_files', 'value' => count( Sidrena_Utils::public_index() ) ),
			array( 'key' => 'archive_files', 'value' => count( Sidrena_Utils::archive_index() ) ),
			array( 'key' => 'strict_publication', 'value' => $settings['strict_publication'] ),
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
