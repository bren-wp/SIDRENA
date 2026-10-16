<?php
/**
 * WooCommerce catalog bulk editor for sidrena data.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads WooCommerce products directly and prepares a compact bulk-edit view.
 */
final class Brendigo_Sidrena_Cijena_Woo_Bulk_Editor {

	const PAGE_SIZE = 25;

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Read the requested catalog page.
	 *
	 * @return int
	 */
	public function requested_page() {
		// This is navigation state only; no data is changed by this GET parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination state.
		$page = isset( $_GET['sidrena_wc_page'] ) ? absint( wp_unslash( $_GET['sidrena_wc_page'] ) ) : 1;
		return max( 1, $page );
	}

	/**
	 * WooCommerce parent-product page with variations expanded below each parent.
	 *
	 * @param int $page Page number.
	 * @return array{rows:array<int,array<string,mixed>>,page:int,pages:int,total:int}
	 */
	public function page( $page = 1 ) {
		$page = max( 1, absint( $page ) );
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array( 'rows' => array(), 'page' => 1, 'pages' => 1, 'total' => 0 );
		}

		$query = wc_get_products(
			array(
				'status'   => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'limit'    => self::PAGE_SIZE,
				'page'     => $page,
				'paginate' => true,
				'return'   => 'objects',
				'orderby'  => 'ID',
				'order'    => 'DESC',
			)
		);

		$products = is_object( $query ) && isset( $query->products ) && is_array( $query->products ) ? $query->products : array();
		$total    = is_object( $query ) && isset( $query->total ) ? absint( $query->total ) : count( $products );
		$pages    = is_object( $query ) && isset( $query->max_num_pages ) ? max( 1, absint( $query->max_num_pages ) ) : 1;
		$rows     = array();

		$variation_ids = array();
		foreach ( $products as $product ) {
			if ( $product instanceof WC_Product && $product->is_type( array( 'variable', 'variable-subscription' ) ) ) {
				$variation_ids = array_merge( $variation_ids, array_map( 'absint', $product->get_children() ) );
			}
		}
		$variation_map = array();
		if ( $variation_ids ) {
			foreach ( wc_get_products( array( 'include' => array_values( array_unique( $variation_ids ) ), 'limit' => -1, 'return' => 'objects' ) ) as $variation ) {
				if ( $variation instanceof WC_Product_Variation ) {
					$variation_map[ $variation->get_id() ] = $variation;
				}
			}
		}

		foreach ( $products as $product ) {
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			if ( $product->is_type( array( 'variable', 'variable-subscription' ) ) ) {
				$rows[] = $this->group_row( $product );
				foreach ( $product->get_children() as $variation_id ) {
					$variation_id = absint( $variation_id );
					if ( isset( $variation_map[ $variation_id ] ) ) {
						$rows[] = $this->editable_row( $variation_map[ $variation_id ], $product );
					}
				}
				continue;
			}

			$rows[] = $this->editable_row( $product );
		}

		return array(
			'rows'  => $rows,
			'page'  => min( $page, $pages ),
			'pages' => $pages,
			'total' => $total,
		);
	}

	/**
	 * Non-editable variable parent heading.
	 *
	 * @param WC_Product $product Parent product.
	 * @return array<string,mixed>
	 */
	private function group_row( $product ) {
		return array(
			'id'       => $product->get_id(),
			'group'    => true,
			'name'     => $product->get_name(),
			'sku'      => $product->get_sku(),
			'edit_url' => get_edit_post_link( $product->get_id(), '' ),
		);
	}

	/**
	 * One editable simple/external/subscription product or variation.
	 *
	 * @param WC_Product      $product Product.
	 * @param WC_Product|null $parent  Optional variation parent.
	 * @return array<string,mixed>
	 */
	private function editable_row( $product, $parent = null ) {
		$id   = $product->get_id();
		$meta = Brendigo_Sidrena_Cijena_Product_Meta::instance();
		$name = $product->get_name();
		if ( $product instanceof WC_Product_Variation && $parent instanceof WC_Product ) {
			$attributes = wc_get_formatted_variation( $product, true, false, false );
			if ( '' !== trim( wp_strip_all_tags( $attributes ) ) ) {
				$name = wp_strip_all_tags( $attributes );
			} else {
				/* translators: %d: variation product ID. */
				$name = sprintf( __( 'Varijacija #%d', 'brendigo-sidrena-cijena' ), $id );
			}
		}

		$regular = (string) $product->get_regular_price();
		$current = (string) $product->get_price();
		$display = '';
		if ( '' !== $current && function_exists( 'wc_price' ) ) {
			$display_amount = function_exists( 'wc_get_price_to_display' ) ? wc_get_price_to_display( $product, array( 'price' => (float) $current ) ) : (float) $current;
			$display        = wc_price( $display_amount );
		}

		return array(
			'id'         => $id,
			'group'      => false,
			'variation'  => $product instanceof WC_Product_Variation,
			'name'       => $name,
			'parent'     => $parent instanceof WC_Product ? $parent->get_name() : '',
			'sku'        => $product->get_sku(),
			'regular'    => $regular,
			'current'    => $current,
			'display'    => $display,
			'sidrena'    => $meta->get_price( $id ),
			'date'       => $meta->get_date( $id ),
			'barcode'    => $meta->get_barcode( $id ),
			'visibility' => $meta->get_cjenik_visibility( $id ),
			'edit_url'   => get_edit_post_link( $id, '' ),
		);
	}
}
