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
			add_action( 'woocommerce_update_product', array( $this, 'capture_item' ), 20 );
			add_action( 'woocommerce_update_product_variation', array( $this, 'capture_item' ), 20 );
		}
		add_action( 'added_post_meta', array( $this, 'capture_price_meta_change' ), 20, 4 );
		add_action( 'updated_post_meta', array( $this, 'capture_price_meta_change' ), 20, 4 );
		add_action( 'deleted_post_meta', array( $this, 'capture_price_meta_change' ), 20, 4 );
		add_action( 'shutdown', array( $this, 'flush_price_meta_changes' ), 5 );
		add_action( 'sidrena_daily_generation', array( $this, 'daily_snapshot' ), 5 );
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
		$key   = $variation_id ? 'variation_id' : 'product_id';
		$id    = $variation_id ? $variation_id : $product_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SIDRENA-owned audit table requires bounded direct CRUD.
		$last = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT price, regular_price FROM %i WHERE %i = %d ORDER BY id DESC LIMIT 1',
				$table,
				$key,
				$id
			),
			ARRAY_A
		);

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
		$key          = $variation_id ? 'variation_id' : 'product_id';
		$id           = $variation_id ? $variation_id : $product_id;
		$window_mysql = wp_date( 'Y-m-d H:i:s', $window_from );
		$start_mysql  = wp_date( 'Y-m-d H:i:s', $start );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded read from SIDRENA-owned audit table.
		$baseline = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT price, recorded_at FROM %i WHERE %i = %d AND recorded_at <= %s ORDER BY id DESC LIMIT 1',
				$table,
				$key,
				$id,
				$window_mysql
			),
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded 30-day read from SIDRENA-owned audit table.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT price, recorded_at FROM %i WHERE %i = %d AND recorded_at > %s AND recorded_at < %s ORDER BY id ASC LIMIT 2000',
				$table,
				$key,
				$id,
				$window_mysql,
				$start_mysql
			),
			ARRAY_A
		);

		$values = array();
		if ( is_array( $baseline ) && null !== $baseline['price'] && '' !== $baseline['price'] ) {
			$values[] = (float) $baseline['price'];
		}
		foreach ( (array) $rows as $row ) {
			if ( null !== $row['price'] && '' !== $row['price'] ) {
				$values[] = (float) $row['price'];
			}
		}

		$auto_ready = ! empty( $baseline ) && ! empty( $values );
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
		$key          = $variation_id ? 'variation_id' : 'product_id';
		$id           = $variation_id ? $variation_id : absint( $product->get_id() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded inference from SIDRENA-owned audit history.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT price, recorded_at FROM %i WHERE %i = %d ORDER BY id DESC LIMIT 500',
				$table,
				$key,
				$id
			),
			ARRAY_A
		);

		$start          = 0;
		$matched        = false;
		$previous_found = false;
		foreach ( (array) $rows as $row ) {
			if ( null === $row['price'] || '' === $row['price'] ) {
				continue;
			}
			if ( abs( (float) $row['price'] - (float) $sale_price ) < 0.000001 ) {
				$matched = true;
				$parsed  = strtotime( (string) $row['recorded_at'] );
				if ( $parsed ) {
					$start = $parsed;
				}
				continue;
			}
			if ( $matched ) {
				$previous_found = true;
				break;
			}
		}

		return $matched && $previous_found ? $start : 0;
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
			$page = 1;
			do {
				$query = new WP_Query(
					array(
						'post_type'      => Sidrena_Standalone::POST_TYPE,
						'post_status'    => 'publish',
						'posts_per_page' => 250, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Bounded audit-history batch.
						'paged'          => $page,
						'fields'         => 'ids',
						'orderby'        => 'ID',
						'order'          => 'ASC',
						'no_found_rows'  => true,
					)
				);
				foreach ( $query->posts as $item_id ) {
					yield absint( $item_id );
				}
				$count = count( $query->posts );
				++$page;
			} while ( 250 === $count );
			return;
		}

		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			return;
		}
		$page = 1;
		do {
			$query    = new WC_Product_Query(
				array(
					'limit'   => 100,
					'page'    => $page,
					'status'  => array( 'publish' ),
					'return'  => 'objects',
					'orderby' => 'ID',
					'order'   => 'ASC',
				)
			);
			$products = $query->get_products();
			foreach ( $products as $product ) {
				if ( $product->is_type( 'variable' ) ) {
					foreach ( $product->get_children() as $variation_id ) {
						yield absint( $variation_id );
					}
				} else {
					yield absint( $product->get_id() );
				}
			}
			$count = count( $products );
			++$page;
		} while ( 100 === $count );
	}
}
