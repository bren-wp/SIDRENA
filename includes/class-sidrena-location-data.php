<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-location stock/current-price/anchor-price overrides. WooCommerce Core stores one stock/price
 * state by default, while the public cjenik requires availability per location.
 */
final class Sidrena_Location_Data {
	private static $cache = array();

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'sidrena_location_products';
	}

	public static function get_for_location( $location_id ) {
		$location_id = Sidrena_Utils::sanitize_location_id( $location_id );
		if ( isset( self::$cache[ $location_id ] ) ) {
			return self::$cache[ $location_id ];
		}

		global $wpdb;
		$table = self::table_name();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, variation_id, price, anchor_price, availability, updated_at FROM {$table} WHERE location_id = %s",
				$location_id
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.

		$data = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$item_id = ! empty( $row['variation_id'] ) ? absint( $row['variation_id'] ) : absint( $row['product_id'] );
			if ( ! $item_id ) {
				continue;
			}
			$data[ $item_id ] = array(
				'price'        => null === $row['price'] ? '' : Sidrena_Utils::decimal( $row['price'] ),
				'anchor_price' => null === $row['anchor_price'] ? '' : Sidrena_Utils::decimal( $row['anchor_price'] ),
				'availability' => in_array( $row['availability'], array( 'dostupno', 'nedostupno' ), true ) ? $row['availability'] : '',
				'updated_at'   => sanitize_text_field( $row['updated_at'] ),
			);
		}

		self::$cache[ $location_id ] = $data;
		return $data;
	}

	public static function get_for_product( $location_id, $product ) {
		if ( ! $product || ! is_callable( array( $product, 'get_id' ) ) ) {
			return array();
		}
		$all = self::get_for_location( $location_id );
		$id  = absint( $product->get_id() );
		return isset( $all[ $id ] ) ? $all[ $id ] : array();
	}

	public static function upsert( $location_id, $product_id, $variation_id, $price, $availability, $anchor_price = '' ) {
		global $wpdb;
		$table        = self::table_name();
		$location_id  = Sidrena_Utils::sanitize_location_id( $location_id );
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );
		$price        = Sidrena_Utils::decimal( $price );
		$anchor_price = Sidrena_Utils::decimal( $anchor_price );
		$availability = in_array( $availability, array( 'dostupno', 'nedostupno' ), true ) ? $availability : '';

		if ( ! $location_id || ! $product_id ) {
			return false;
		}

		// NULLIF preserves an intentionally blank per-location price as SQL NULL
		// instead of converting it to 0.00 through a %f placeholder.
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (location_id, product_id, variation_id, price, anchor_price, availability, updated_at)
				 VALUES (%s, %d, %d, NULLIF(%s, ''), NULLIF(%s, ''), %s, %s)
				 ON DUPLICATE KEY UPDATE price = VALUES(price), anchor_price = VALUES(anchor_price), availability = VALUES(availability), updated_at = VALUES(updated_at)",
				$location_id,
				$product_id,
				$variation_id,
				$price,
				$anchor_price,
				$availability,
				current_time( 'mysql' )
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.

		unset( self::$cache[ $location_id ] );
		if ( false !== $result && class_exists( 'Sidrena_Location_History' ) ) {
			Sidrena_Location_History::capture( $location_id, $product_id, $variation_id, $price, $anchor_price, $availability, 'import' );
		}
		return false !== $result;
	}

	public static function delete_location( $location_id ) {
		global $wpdb;
		$location_id = Sidrena_Utils::sanitize_location_id( $location_id );
		$table       = self::table_name();
		$wpdb->delete( $table, array( 'location_id' => $location_id ), array( '%s' ) );
		unset( self::$cache[ $location_id ] );
	}

	public static function coverage( $location_id ) {
		global $wpdb;
		$table       = self::table_name();
		$location_id = Sidrena_Utils::sanitize_location_id( $location_id );
		return absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE location_id = %s AND availability IN ('dostupno','nedostupno')",
					$location_id
				)
			) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
		);
	}
}
