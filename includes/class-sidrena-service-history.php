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
 * Unlimited audit history of actual service price changes.
 *
 * The history is not a 30-day sale-price calculator and does not participate
 * in the immutable SIDRENA anchor-price ruleset.
 */
final class Sidrena_Service_History {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'save_post_sidrena_service', array( $this, 'capture_service' ), 30, 2 );
		add_action( 'sidrena_daily_generation', array( $this, 'daily_snapshot' ), 6 );
	}

	public function capture_service( $service_id, $post = null, $source = 'save' ) {
		unset( $post );
		$service_id = absint( $service_id );
		if ( ! $service_id || 'publish' !== get_post_status( $service_id ) ) {
			return;
		}
		$price = get_post_meta( $service_id, '_sidrena_service_current_price', true );
		$this->insert_if_changed( $service_id, $price, $source );
	}

	private function insert_if_changed( $service_id, $price, $source ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sidrena_service_price_history';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SIDRENA-owned audit table requires bounded direct CRUD.
		$last = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT price FROM %i WHERE service_id = %d ORDER BY id DESC LIMIT 1',
				$table,
				$service_id
			)
		);
		$price = '' === $price ? null : (float) $price;
		if ( $this->same_numeric_value( $last, $price ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SIDRENA-owned audit table requires bounded direct CRUD.
		$wpdb->insert(
			$table,
			array(
				'service_id'  => $service_id,
				'price'       => $price,
				'recorded_at' => current_time( 'mysql' ),
				'source'      => sanitize_key( $source ),
			),
			array( '%d', '%f', '%s', '%s' )
		);
	}

	private function same_numeric_value( $stored, $current ) {
		if ( null === $current ) {
			return null === $stored || '' === $stored;
		}
		if ( null === $stored || '' === $stored ) {
			return false;
		}
		return abs( (float) $stored - (float) $current ) < 0.000001;
	}

	public function daily_snapshot() {
		$page = 1;
		do {
			$query = new WP_Query(
				array(
					'post_type'      => 'sidrena_service',
					'post_status'    => 'publish',
					'posts_per_page' => 250, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Bounded audit-history batch.
					'paged'          => $page,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);
			foreach ( $query->posts as $service_id ) {
				$this->capture_service( absint( $service_id ), null, 'daily' );
			}
			$count = count( $query->posts );
			++$page;
		} while ( 250 === $count );
	}
}
