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
 * This class deliberately does not calculate or expose the statutory
 * "lowest price in the previous 30 days". SIDRENA price history is an audit
 * trail only and is independent from the immutable anchor-price ruleset.
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
		} else {
			if ( ! Sidrena_Utils::is_woocommerce_active() || ! in_array( $meta_key, array( '_regular_price', '_sale_price', '_price' ), true ) || ! in_array( get_post_type( $object_id ), array( 'product', 'product_variation' ), true ) ) {
				return;
			}
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
			$this->insert_if_changed( $item_id, 0, $price, $price, null, $source );
			return;
		}

		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			return;
		}
		$product = wc_get_product( $item_id );
		if ( ! $product ) {
			return;
		}
		$parent_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$variation_id = $product->is_type( 'variation' ) ? $product->get_id() : 0;
		$this->insert_if_changed(
			$parent_id,
			$variation_id,
			$product->get_price( 'edit' ),
			$product->get_regular_price( 'edit' ),
			$product->get_sale_price( 'edit' ),
			$source
		);
	}

	private function insert_if_changed( $product_id, $variation_id, $price, $regular_price, $sale_price, $source ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sidrena_price_history';
		$key   = $variation_id ? 'variation_id' : 'product_id';
		$id    = $variation_id ? $variation_id : $product_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SIDRENA-owned audit table requires bounded direct CRUD.
		$last = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT price, regular_price, sale_price FROM %i WHERE %i = %d ORDER BY id DESC LIMIT 1',
				$table,
				$key,
				$id
			),
			ARRAY_A
		);

		$price         = '' === $price ? null : (float) $price;
		$regular_price = '' === $regular_price ? null : (float) $regular_price;
		$sale_price    = '' === $sale_price ? null : (float) $sale_price;
		if ( $last
			&& $this->same_numeric_value( $last['price'], $price )
			&& $this->same_numeric_value( $last['regular_price'], $regular_price )
			&& $this->same_numeric_value( $last['sale_price'], $sale_price )
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
				'sale_price'    => $sale_price,
				'recorded_at'   => current_time( 'mysql' ),
				'source'        => sanitize_key( $source ),
			),
			array( '%d', '%d', '%f', '%f', '%f', '%s', '%s' )
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
			$query = new WC_Product_Query(
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
