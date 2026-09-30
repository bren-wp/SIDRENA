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
 * Unlimited audit history of actual product price changes.
 *
 * The 30-day minimum for an active special sale is derived here as a separate
 * consumer-price rule. It never changes or supplies the immutable SIDRENA
 * anchor-price ruleset.
 */
final class Sidrena_History {
	private static $instance;
	private static $pending_items = array();

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		if ( Sidrena_Utils::is_woocommerce_active() ) {
			add_action( 'woocommerce_update_product', array( $this, 'capture_woocommerce_update' ), 20 );
			add_action( 'woocommerce_update_product_variation', array( $this, 'capture_woocommerce_update' ), 20 );
		}
		add_action( 'added_post_meta', array( $this, 'capture_price_meta_change' ), 20, 4 );
		add_action( 'updated_post_meta', array( $this, 'capture_price_meta_change' ), 20, 4 );
		add_action( 'deleted_post_meta', array( $this, 'capture_price_meta_change' ), 20, 4 );
		add_action( 'shutdown', array( $this, 'flush_price_meta_changes' ), 5 );
		add_action( 'sidrena_daily_generation', array( $this, 'daily_snapshot' ), 5 );
	}

	public function capture_woocommerce_update( $item_id ) {
		$this->capture_item( $item_id, 'woocommerce-update' );
		Sidrena_Pricelist::queue_regeneration();
	}

	public function capture_price_meta_change( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );
		$object_id = absint( $object_id );
		if ( ! $object_id ) {
			return;
		}

		if ( Sidrena_Utils::is_wordpress_edition() ) {
			if ( '_sidrena_standalone_current_price' !== $meta_key || ! class_exists( 'Sidrena_Standalone' ) || Sidrena_Standalone::POST_TYPE !== get_post_type( $object_id ) ) {
				return;
			}
		} elseif ( ! Sidrena_Utils::is_woocommerce_active() || ! in_array( $meta_key, array( '_regular_price', '_sale_price', '_price' ), true ) || ! in_array( get_post_type( $object_id ), array( 'product', 'product_variation' ), true ) ) {
			return;
		}

		self::$pending_items[ $object_id ] = true;
		Sidrena_Pricelist::queue_regeneration();
	}

	public function flush_price_meta_changes() {
		if ( empty( self::$pending_items ) ) {
			return;
		}
		$ids = array_keys( self::$pending_items );

		self::$pending_items = array();
		foreach ( $ids as $item_id ) {
			$this->capture_item( absint( $item_id ), 'price-change' );
		}
	}

	public function capture_item( $item_id, $source = 'save' ) {
		$item_id = absint( $item_id );
		if ( ! $item_id ) {
			return;
		}

		if ( Sidrena_Utils::is_wordpress_edition() ) {
			if ( ! class_exists( 'Sidrena_Standalone' ) || Sidrena_Standalone::POST_TYPE !== get_post_type( $item_id ) || 'publish' !== get_post_status( $item_id ) ) {
				return;
			}
			$price = get_post_meta( $item_id, '_sidrena_standalone_current_price', true );
			$this->insert_if_changed( $item_id, 0, $price, null, $source );
			return;
		}

		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			return;
		}
		$product = wc_get_product( $item_id );
		if ( ! $product ) {
			return;
		}
		$parent_id    = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$variation_id = $product->is_type( 'variation' ) ? $product->get_id() : 0;
		$this->insert_if_changed(
			$parent_id,
			$variation_id,
			$product->get_price( 'edit' ),
			$product->get_regular_price( 'edit' ),
			$source
		);
	}

	private function insert_if_changed( $product_id, $variation_id, $price, $regular_price, $source ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sidrena_price_history';
		// Product rows and variation rows share product_id, so parent/simple lookups
		// must explicitly exclude variation history.
		if ( $variation_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SIDRENA-owned audit table requires a bounded lookup for one variation.
			$last = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT price, regular_price FROM %i WHERE variation_id = %d ORDER BY id DESC LIMIT 1',
					$table,
					$variation_id
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SIDRENA-owned audit table requires a bounded lookup for one parent/simple product.
			$last = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT price, regular_price FROM %i WHERE product_id = %d AND variation_id = 0 ORDER BY id DESC LIMIT 1',
					$table,
					$product_id
				),
				ARRAY_A
			);
		}

		$price         = '' === $price ? null : (float) $price;
		$regular_price = '' === $regular_price ? null : (float) $regular_price;
		if ( $last
			&& $this->same_numeric_value( $last['price'], $price )
			&& $this->same_numeric_value( $last['regular_price'], $regular_price )
		) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SIDRENA-owned audit table requires bounded direct CRUD.
		$wpdb->insert(
			$table,
			array(
				'product_id'    => absint( $product_id ),
				'variation_id'  => absint( $variation_id ),
				'price'         => $price,
				'regular_price' => $regular_price,
				'recorded_at'   => current_time( 'mysql' ),
				'source'        => sanitize_key( $source ),
			),
			array( '%d', '%d', '%f', '%f', '%s', '%s' )
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


	public function lowest_30_day_reference( $product ) {
		$result = array(
			'status'      => 'inactive',
			'price'       => '',
			'source'      => '',
			'sale_start'  => 0,
			'window_from' => 0,
		);

		if ( ! Sidrena_Utils::is_woocommerce_active() || ! $product instanceof WC_Product || ! $product->is_on_sale( 'edit' ) ) {
			return $result;
		}

		$sale_price    = Sidrena_Utils::decimal( $product->get_sale_price( 'edit' ) );
		$regular_price = Sidrena_Utils::decimal( $product->get_regular_price( 'edit' ) );
		if ( '' === $sale_price || '' === $regular_price || (float) $sale_price >= (float) $regular_price ) {
			return $result;
		}

		$manual = Sidrena_Utils::decimal( $product->get_meta( '_sidrena_lowest_30_verified', true ) );
		$start  = $this->sale_start_timestamp( $product, (float) $sale_price );
		if ( ! $start ) {
			if ( '' !== $manual ) {
				$result['status'] = 'ready';
				$result['price']  = $manual;
				$result['source'] = 'manual';
			} else {
				$result['status'] = 'incomplete';
			}
			return $result;
		}

		$window_from           = $start - ( 30 * DAY_IN_SECONDS );
		$result['sale_start']  = $start;
		$result['window_from'] = $window_from;

		global $wpdb;
		$table        = $wpdb->prefix . 'sidrena_price_history';
		$variation_id = $product->is_type( 'variation' ) ? absint( $product->get_id() ) : 0;
		$product_id   = $variation_id ? absint( $product->get_parent_id() ) : absint( $product->get_id() );
		$window_mysql = wp_date( 'Y-m-d H:i:s', $window_from );
		$start_mysql  = wp_date( 'Y-m-d H:i:s', $start );

		if ( $variation_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Indexed baseline lookup for one variation.
			$baseline = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT price, recorded_at FROM %i WHERE variation_id = %d AND recorded_at <= %s ORDER BY recorded_at DESC, id DESC LIMIT 1',
					$table,
					$variation_id,
					$window_mysql
				),
				ARRAY_A
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact indexed aggregate for one variation avoids truncating high-frequency history.
			$window_min = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT MIN(price) FROM %i WHERE variation_id = %d AND recorded_at > %s AND recorded_at < %s AND price IS NOT NULL',
					$table,
					$variation_id,
					$window_mysql,
					$start_mysql
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Parent/simple scope excludes child variation rows.
			$baseline = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT price, recorded_at FROM %i WHERE product_id = %d AND variation_id = 0 AND recorded_at <= %s ORDER BY recorded_at DESC, id DESC LIMIT 1',
					$table,
					$product_id,
					$window_mysql
				),
				ARRAY_A
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact parent/simple aggregate excludes child variation rows.
			$window_min = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT MIN(price) FROM %i WHERE product_id = %d AND variation_id = 0 AND recorded_at > %s AND recorded_at < %s AND price IS NOT NULL',
					$table,
					$product_id,
					$window_mysql,
					$start_mysql
				)
			);
		}

		$baseline_ready = is_array( $baseline ) && array_key_exists( 'price', $baseline ) && null !== $baseline['price'] && '' !== $baseline['price'];
		$values         = array();
		if ( $baseline_ready ) {
			$values[] = (float) $baseline['price'];
		}
		if ( null !== $window_min && '' !== $window_min ) {
			$values[] = (float) $window_min;
		}

		$auto_ready = $baseline_ready && ! empty( $values );
		$auto_price = $values ? min( $values ) : '';

		if ( '' !== $manual ) {
			$result['status'] = 'ready';
			$result['price']  = $manual;
			$result['source'] = 'manual';
			if ( $auto_ready ) {
				$result['calculated_price'] = $auto_price;
			}
			return $result;
		}

		if ( ! $auto_ready ) {
			$result['status'] = 'incomplete';
			$result['price']  = $auto_price;
			$result['source'] = 'history';
			return $result;
		}

		$result['status'] = 'ready';
		$result['price']  = $auto_price;
		$result['source'] = 'history';
		return $result;
	}

	private function sale_start_timestamp( $product, $sale_price ) {
		$date = $product->get_date_on_sale_from( 'edit' );
		if ( $date && is_callable( array( $date, 'getTimestamp' ) ) ) {
			return absint( $date->getTimestamp() );
		}

		global $wpdb;
		$table        = $wpdb->prefix . 'sidrena_price_history';
		$variation_id = $product->is_type( 'variation' ) ? absint( $product->get_id() ) : 0;
		$product_id   = $variation_id ? absint( $product->get_parent_id() ) : absint( $product->get_id() );
		$sale_price   = (float) $sale_price;

		if ( $variation_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Three single-row indexed lookups infer the current contiguous sale-price run without an arbitrary history cutoff.
			$latest = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT id, price, recorded_at FROM %i WHERE variation_id = %d AND price IS NOT NULL ORDER BY id DESC LIMIT 1',
					$table,
					$variation_id
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Parent/simple scope explicitly excludes child variations.
			$latest = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT id, price, recorded_at FROM %i WHERE product_id = %d AND variation_id = 0 AND price IS NOT NULL ORDER BY id DESC LIMIT 1',
					$table,
					$product_id
				),
				ARRAY_A
			);
		}

		if ( ! is_array( $latest ) || empty( $latest['id'] ) || null === $latest['price'] || abs( (float) $latest['price'] - $sale_price ) >= 0.000001 ) {
			return 0;
		}
		$latest_id = absint( $latest['id'] );

		if ( $variation_id ) {
			$previous_id = absint(
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Single-row lookup for the immediately preceding different variation price.
				$wpdb->get_var(
					$wpdb->prepare(
						'SELECT id FROM %i WHERE variation_id = %d AND id < %d AND price IS NOT NULL AND ABS(price - %f) >= 0.000001 ORDER BY id DESC LIMIT 1',
						$table,
						$variation_id,
						$latest_id,
						$sale_price
					)
				)
			);
		} else {
			$previous_id = absint(
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Parent/simple lookup excludes all variation history.
				$wpdb->get_var(
					$wpdb->prepare(
						'SELECT id FROM %i WHERE product_id = %d AND variation_id = 0 AND id < %d AND price IS NOT NULL AND ABS(price - %f) >= 0.000001 ORDER BY id DESC LIMIT 1',
						$table,
						$product_id,
						$latest_id,
						$sale_price
					)
				)
			);
		}

		// Without a preceding different price there is not enough evidence to infer
		// when the reduction began; keep the result incomplete rather than guessing.
		if ( ! $previous_id ) {
			return 0;
		}

		if ( $variation_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Single-row lookup for the first current sale-price record after the preceding different value.
			$start_row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT recorded_at FROM %i WHERE variation_id = %d AND id > %d AND id <= %d AND ABS(price - %f) < 0.000001 ORDER BY id ASC LIMIT 1',
					$table,
					$variation_id,
					$previous_id,
					$latest_id,
					$sale_price
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Parent/simple lookup excludes all variation history.
			$start_row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT recorded_at FROM %i WHERE product_id = %d AND variation_id = 0 AND id > %d AND id <= %d AND ABS(price - %f) < 0.000001 ORDER BY id ASC LIMIT 1',
					$table,
					$product_id,
					$previous_id,
					$latest_id,
					$sale_price
				),
				ARRAY_A
			);
		}

		$parsed = is_array( $start_row ) && ! empty( $start_row['recorded_at'] ) ? strtotime( (string) $start_row['recorded_at'] ) : false;
		return $parsed ? absint( $parsed ) : 0;
	}

	public static function recent_changes( $limit = 5 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sidrena_price_history';
		$limit = min( 20, max( 1, absint( $limit ) ) );
		$scan  = max( 120, $limit * 30 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned price-history table requires bounded direct reads.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, product_id, variation_id, price, recorded_at
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
			$item_id = absint( $row['variation_id'] ) ? absint( $row['variation_id'] ) : absint( $row['product_id'] );
			if ( ! $item_id ) {
				continue;
			}
			$key = ( absint( $row['variation_id'] ) ? 'v:' : 'p:' ) . $item_id;

			if ( ! isset( $newest[ $key ] ) ) {
				$newest[ $key ] = $row;
				continue;
			}

			$new = $newest[ $key ];
			if ( abs( (float) $new['price'] - (float) $row['price'] ) < 0.000001 ) {
				$newest[ $key ] = $row;
				continue;
			}

			$product   = function_exists( 'wc_get_product' ) ? wc_get_product( $item_id ) : null;
			$name      = $product ? $product->get_name() : get_the_title( $item_id );
			$old       = (float) $row['price'];
			$now       = (float) $new['price'];
			$changes[] = array(
				'item_id'     => $item_id,
				/* translators: %d: product or variation ID. */
				'name'        => $name ? wp_strip_all_tags( $name ) : sprintf( __( 'Stavka #%d', 'sidrena' ), $item_id ),
				'old_price'   => $old,
				'new_price'   => $now,
				'recorded_at' => sanitize_text_field( $new['recorded_at'] ),
				'change_pct'  => 0.0 !== $old ? ( ( $now - $old ) / $old ) * 100 : 0,
				'kind'        => 'product',
			);
			unset( $newest[ $key ] );

			if ( count( $changes ) >= $limit ) {
				break;
			}
		}

		return $changes;
	}

	public static function count_rows() {
		global $wpdb;
		$table = $wpdb->prefix . 'sidrena_price_history';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Count from plugin-owned price-history table.
		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ) );
	}

	public function daily_snapshot() {
		foreach ( $this->catalog_item_ids() as $item_id ) {
			$this->capture_item( $item_id, 'daily' );
		}
	}

	private function catalog_item_ids() {
		if ( Sidrena_Utils::is_wordpress_edition() ) {
			foreach ( $this->catalog_parent_ids_keyset( Sidrena_Standalone::POST_TYPE, 250 ) as $item_id ) {
				yield $item_id;
			}
			return;
		}

		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			return;
		}

		foreach ( $this->catalog_parent_ids_keyset( 'product', 100 ) as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}
			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $variation_id ) {
					yield absint( $variation_id );
				}
			} else {
				yield absint( $product->get_id() );
			}
		}
	}

	private function catalog_parent_ids_keyset( $post_type, $batch_size ) {
		global $wpdb;

		$post_type  = sanitize_key( (string) $post_type );
		$batch_size = min( 500, max( 25, absint( $batch_size ) ) );
		if ( '' === $post_type ) {
			return;
		}

		$last_id = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Keyset-paginated bounded read avoids progressively expensive OFFSET scans during daily history snapshots.
			$ids   = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT ID FROM %i WHERE post_type = %s AND post_status = %s AND ID > %d ORDER BY ID ASC LIMIT %d',
					$wpdb->posts,
					$post_type,
					'publish',
					$last_id,
					$batch_size
				)
			);
			$ids   = is_array( $ids ) ? $ids : array();
			$count = count( $ids );

			foreach ( $ids as $item_id ) {
				$item_id = absint( $item_id );
				if ( ! $item_id ) {
					continue;
				}
				$last_id = $item_id;
				yield $item_id;
			}
		} while ( $count === $batch_size );
	}
}
