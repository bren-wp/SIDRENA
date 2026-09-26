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
 * Tracks per-location product price, anchor price and availability changes.
 *
 * This history is deliberately separate from the public CSV/XML archive. The
 * archive preserves published price-list files, while this table provides an
 * internal audit trail for location-specific values imported into Sidrena.
 */
final class Sidrena_Location_History {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'sidrena_daily_generation', array( $this, 'daily_snapshot' ), 7 );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'sidrena_location_price_history';
	}

	public static function capture( $location_id, $product_id, $variation_id, $price, $anchor_price, $availability, $source = 'import' ) {
		if ( 'yes' !== Sidrena_Utils::settings()['track_price_history'] ) {
			return;
		}

		global $wpdb;
		$table        = self::table_name();
		$location_id  = Sidrena_Utils::sanitize_location_id( $location_id );
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );
		$price        = Sidrena_Utils::decimal( $price );
		$anchor_price = Sidrena_Utils::decimal( $anchor_price );
		$availability = in_array( $availability, array( 'dostupno', 'nedostupno' ), true ) ? $availability : '';

		if ( ! $location_id || ! $product_id ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned per-location history table requires direct bounded CRUD.
		$last = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT price, anchor_price, availability FROM %i WHERE location_id = %s AND product_id = %d AND variation_id = %d ORDER BY recorded_at DESC, id DESC LIMIT 1",
				$table,
				$location_id,
				$product_id,
				$variation_id
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.

		if (
			$last
			&& self::same_numeric_value( $last['price'], '' === $price ? null : (float) $price )
			&& self::same_numeric_value( $last['anchor_price'], '' === $anchor_price ? null : (float) $anchor_price )
			&& (string) $last['availability'] === $availability
		) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned per-location history table requires direct bounded CRUD.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i (location_id, product_id, variation_id, price, anchor_price, availability, recorded_at, source)
				 VALUES (%s, %d, %d, NULLIF(%s, ''), NULLIF(%s, ''), %s, %s, %s)",
				$table,
				$location_id,
				$product_id,
				$variation_id,
				$price,
				$anchor_price,
				$availability,
				current_time( 'mysql' ),
				sanitize_key( $source )
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
	}

	public function daily_snapshot() {
		if ( 'yes' !== Sidrena_Utils::settings()['track_price_history'] ) {
			return;
		}

		global $wpdb;
		$current_table = Sidrena_Location_Data::table_name();
		$batch_size    = min( 1000, max( 50, absint( apply_filters( 'sidrena_location_history_batch_size', 250 ) ) ) );
		$last_id       = 0;

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned per-location history table requires direct bounded CRUD.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, location_id, product_id, variation_id, price, anchor_price, availability
					FROM %i
					WHERE id > %d
					ORDER BY id ASC
					LIMIT %d",
					$current_table,
					$last_id,
					$batch_size
				),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.

			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$row_id = absint( $row['id'] );
				if ( $row_id <= $last_id ) {
					continue;
				}

				self::capture(
					$row['location_id'],
					$row['product_id'],
					$row['variation_id'],
					null === $row['price'] ? '' : $row['price'],
					null === $row['anchor_price'] ? '' : $row['anchor_price'],
					$row['availability'],
					'daily'
				);
				$last_id = $row_id;
			}
		} while ( count( $rows ) === $batch_size );

		$this->prune_history();
	}

	public static function count_rows() {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Count from plugin-owned per-location history table.
		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ) );
	}

	private static function same_numeric_value( $stored, $current ) {
		if ( null === $current ) {
			return null === $stored || '' === $stored;
		}
		if ( null === $stored || '' === $stored ) {
			return false;
		}
		return abs( (float) $stored - (float) $current ) < 0.000001;
	}

	private function prune_history() {
		global $wpdb;
		$table  = self::table_name();
		$cutoff = wp_date( 'Y-m-d H:i:s', time() - ( 400 * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned per-location history table requires direct bounded CRUD.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE recorded_at < %s",
				$table,
				$cutoff
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
	}
}
