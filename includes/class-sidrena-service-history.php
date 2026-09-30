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
		$last  = $wpdb->get_var(
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

	public static function recent_changes( $limit = 5 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sidrena_service_price_history';
		$limit = min( 20, max( 1, absint( $limit ) ) );
		$scan  = max( 120, $limit * 30 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned service-history table requires bounded direct reads.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, service_id, price, recorded_at
				FROM %i
				WHERE price IS NOT NULL
				ORDER BY id DESC
				LIMIT %d',
				$table,
				$scan
			),
			ARRAY_A
		);

		$newest  = array();
		$changes = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$service_id = absint( $row['service_id'] );
			if ( ! $service_id ) {
				continue;
			}

			if ( ! isset( $newest[ $service_id ] ) ) {
				$newest[ $service_id ] = $row;
				continue;
			}

			$new = $newest[ $service_id ];
			if ( abs( (float) $new['price'] - (float) $row['price'] ) < 0.000001 ) {
				$newest[ $service_id ] = $row;
				continue;
			}

			$old       = (float) $row['price'];
			$now       = (float) $new['price'];
			$changes[] = array(
				'item_id'     => $service_id,
				'name'        => wp_strip_all_tags( get_the_title( $service_id ) ),
				'old_price'   => $old,
				'new_price'   => $now,
				'recorded_at' => sanitize_text_field( $new['recorded_at'] ),
				'change_pct'  => 0.0 !== $old ? ( ( $now - $old ) / $old ) * 100 : 0,
				'kind'        => 'service',
			);
			unset( $newest[ $service_id ] );

			if ( count( $changes ) >= $limit ) {
				break;
			}
		}

		return $changes;
	}

	public static function count_rows() {
		global $wpdb;
		$table = $wpdb->prefix . 'sidrena_service_price_history';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Count from plugin-owned service-history table.
		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ) );
	}

	public function daily_snapshot() {
		foreach ( Sidrena_Utils::iterate_published_post_ids( 'sidrena_service', 250 ) as $service_id ) {
			$this->capture_service( $service_id, null, 'daily' );
		}
	}


}
