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
 * Per-location stock/current-price/anchor-price overrides. WooCommerce Core stores one stock/price
 * state by default, while the public cjenik requires availability per location.
 */
final class Sidrena_Location_Data {
	private static $cache               = array();
	private static $available_ids_cache = array();

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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned per-location price/availability table requires direct bounded CRUD.
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, variation_id, price, anchor_price, availability, updated_at FROM %i WHERE location_id = %s",
				$table,
				$location_id
			),
			ARRAY_A
		);

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

	/**
	 * Returns only product/variation IDs that have an explicit public availability
	 * state for a physical location. The order mirrors WooCommerce parent/variation
	 * catalog ordering without loading the whole WooCommerce catalog first.
	 */
	public static function available_item_ids_for_location( $location_id ) {
		$location_id = Sidrena_Utils::sanitize_location_id( $location_id );
		if ( isset( self::$available_ids_cache[ $location_id ] ) ) {
			return self::$available_ids_cache[ $location_id ];
		}

		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned indexed location table is the bounded source for explicit availability candidates.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, variation_id FROM %i WHERE location_id = %s AND availability IN ('dostupno','nedostupno') ORDER BY product_id ASC, variation_id ASC",
				$table,
				$location_id
			),
			ARRAY_A
		);

		$ids = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$id = ! empty( $row['variation_id'] ) ? absint( $row['variation_id'] ) : absint( $row['product_id'] );
			if ( $id ) {
				$ids[ $id ] = $id;
			}
		}

		self::$available_ids_cache[ $location_id ] = array_values( $ids );
		return self::$available_ids_cache[ $location_id ];
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned per-location price/availability table requires direct bounded CRUD.
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i (location_id, product_id, variation_id, price, anchor_price, availability, updated_at)
				 VALUES (%s, %d, %d, NULLIF(%s, ''), NULLIF(%s, ''), %s, %s)
				 ON DUPLICATE KEY UPDATE price = VALUES(price), anchor_price = VALUES(anchor_price), availability = VALUES(availability), updated_at = VALUES(updated_at)",
				$table,
				$location_id,
				$product_id,
				$variation_id,
				$price,
				$anchor_price,
				$availability,
				current_time( 'mysql' )
			)
		);

		unset( self::$cache[ $location_id ], self::$available_ids_cache[ $location_id ] );
		if ( false !== $result && class_exists( 'Sidrena_Location_History' ) ) {
			Sidrena_Location_History::capture( $location_id, $product_id, $variation_id, $price, $anchor_price, $availability, 'import' );
		}
		return false !== $result;
	}

	public static function delete_location( $location_id ) {
		global $wpdb;
		$location_id = Sidrena_Utils::sanitize_location_id( $location_id );
		$table       = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit deletion from the plugin-owned per-location table.
		$wpdb->delete( $table, array( 'location_id' => $location_id ), array( '%s' ) );
		unset( self::$cache[ $location_id ], self::$available_ids_cache[ $location_id ] );
	}

	public static function coverage( $location_id ) {
		global $wpdb;
		$table       = self::table_name();
		$location_id = Sidrena_Utils::sanitize_location_id( $location_id );
		return absint(
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned per-location price/availability table requires direct bounded CRUD.
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE location_id = %s AND availability IN ('dostupno','nedostupno')",
					$table,
					$location_id
				)
			)
		);
	}
}
