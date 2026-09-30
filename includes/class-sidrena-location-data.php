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
	private static $item_cache          = array();
	private static $available_ids_cache = array();

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'sidrena_location_products';
	}

	public static function get_for_product( $location_id, $product ) {
		if ( ! $product || ! is_callable( array( $product, 'get_id' ) ) ) {
			return array();
		}

		$location_id = Sidrena_Utils::sanitize_location_id( $location_id );
		$item_id     = absint( $product->get_id() );
		if ( ! $location_id || ! $item_id ) {
			return array();
		}
		if ( isset( self::$item_cache[ $location_id ] ) && array_key_exists( $item_id, self::$item_cache[ $location_id ] ) ) {
			return self::$item_cache[ $location_id ][ $item_id ];
		}

		$is_variation = is_callable( array( $product, 'is_type' ) ) && $product->is_type( 'variation' );
		$product_id   = $is_variation && is_callable( array( $product, 'get_parent_id' ) ) ? absint( $product->get_parent_id() ) : $item_id;
		$variation_id = $is_variation ? $item_id : 0;
		if ( ! $product_id ) {
			return array();
		}

		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Unique indexed lookup for one location/product row; request-local cache prevents repeated reads.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT price, anchor_price, availability, updated_at FROM %i WHERE location_id = %s AND product_id = %d AND variation_id = %d LIMIT 1',
				$table,
				$location_id,
				$product_id,
				$variation_id
			),
			ARRAY_A
		);

		$data = is_array( $row )
			? array(
				'price'        => null === $row['price'] ? '' : Sidrena_Utils::decimal( $row['price'] ),
				'anchor_price' => null === $row['anchor_price'] ? '' : Sidrena_Utils::decimal( $row['anchor_price'] ),
				'availability' => in_array( $row['availability'], array( 'dostupno', 'nedostupno' ), true ) ? $row['availability'] : '',
				'updated_at'   => sanitize_text_field( $row['updated_at'] ),
			)
			: array();

		if ( ! isset( self::$item_cache[ $location_id ] ) ) {
			self::$item_cache[ $location_id ] = array();
		}
		self::$item_cache[ $location_id ][ $item_id ] = $data;
		return $data;
	}

	/**
	 * Returns only product/variation IDs that have an explicit public availability
	 * state for a physical location. Variable products preserve WooCommerce's
	 * configured child order without loading the whole WooCommerce catalog first.
	 */
	public static function available_item_ids_for_location( $location_id ) {
		$location_id = Sidrena_Utils::sanitize_location_id( $location_id );
		if ( isset( self::$available_ids_cache[ $location_id ] ) ) {
			return self::$available_ids_cache[ $location_id ];
		}

		$ids = array();
		foreach ( self::iterate_available_item_ids_for_location( $location_id ) as $item_id ) {
			$ids[] = $item_id;
		}

		self::$available_ids_cache[ $location_id ] = $ids;
		return $ids;
	}

	public static function iterate_available_item_ids_for_location( $location_id, $batch_size = 250 ) {
		$location_id = Sidrena_Utils::sanitize_location_id( $location_id );
		$batch_size  = min( 500, max( 25, absint( $batch_size ) ) );
		if ( ! $location_id ) {
			return;
		}

		global $wpdb;
		$table          = self::table_name();
		$last_product   = 0;
		$last_variation = -1;
		$current_id     = 0;
		$current_simple = false;
		$current_vars   = array();

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Keyset-paginated read from the plugin-owned indexed location table keeps memory bounded.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT product_id, variation_id FROM %i WHERE location_id = %s AND product_id > 0 AND availability IN ('dostupno','nedostupno') AND (product_id > %d OR (product_id = %d AND variation_id > %d)) ORDER BY product_id ASC, variation_id ASC LIMIT %d",
					$table,
					$location_id,
					$last_product,
					$last_product,
					$last_variation,
					$batch_size
				),
				ARRAY_A
			);
			$rows      = is_array( $rows ) ? $rows : array();
			$row_count = count( $rows );

			foreach ( $rows as $row ) {
				$product_id   = absint( $row['product_id'] ?? 0 );
				$variation_id = absint( $row['variation_id'] ?? 0 );
				if ( ! $product_id ) {
					continue;
				}

				if ( $current_id && $product_id !== $current_id ) {
					foreach ( self::ordered_location_group_ids( $current_id, $current_simple, $current_vars ) as $item_id ) {
						yield $item_id;
					}
					$current_simple = false;
					$current_vars   = array();
				}
				$current_id = $product_id;
				if ( $variation_id ) {
					$current_vars[ $variation_id ] = $variation_id;
				} else {
					$current_simple = true;
				}
				$last_product   = $product_id;
				$last_variation = $variation_id;
			}
		} while ( $row_count === $batch_size );

		if ( $current_id ) {
			foreach ( self::ordered_location_group_ids( $current_id, $current_simple, $current_vars ) as $item_id ) {
				yield $item_id;
			}
		}
	}

	private static function ordered_location_group_ids( $product_id, $has_simple, $variation_ids ) {
		$ids = array();
		if ( $has_simple ) {
			$ids[] = absint( $product_id );
		}

		$variation_ids = is_array( $variation_ids ) ? $variation_ids : array();
		if ( empty( $variation_ids ) ) {
			return $ids;
		}

		$parent   = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
		$children = $parent && is_callable( array( $parent, 'get_children' ) ) ? $parent->get_children() : array();
		foreach ( is_array( $children ) ? $children : array() as $child_id ) {
			$child_id = absint( $child_id );
			if ( isset( $variation_ids[ $child_id ] ) ) {
				$ids[] = $child_id;
				unset( $variation_ids[ $child_id ] );
			}
		}

		if ( $variation_ids ) {
			ksort( $variation_ids, SORT_NUMERIC );
			foreach ( $variation_ids as $variation_id ) {
				$ids[] = $variation_id;
			}
		}

		return $ids;
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

		$item_id = $variation_id ? $variation_id : $product_id;
		unset( self::$available_ids_cache[ $location_id ], self::$item_cache[ $location_id ][ $item_id ] );
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
		unset( self::$item_cache[ $location_id ], self::$available_ids_cache[ $location_id ] );
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
