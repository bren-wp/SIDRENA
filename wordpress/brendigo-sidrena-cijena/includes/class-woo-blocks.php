<?php
/** Sidrena price visibility in WooCommerce Cart / Checkout blocks. */
defined( 'ABSPATH' ) || exit;

final class Brendigo_Sidrena_Cijena_Woo_Blocks {
	private static $instance;
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	private function __construct() {
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_store_api' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 30 );
		// Late fallback for installations registering WC Blocks scripts after wp_enqueue_scripts.
		add_action( 'woocommerce_blocks_enqueue_cart_block_scripts_after', array( $this, 'enqueue' ) );
		add_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_after', array( $this, 'enqueue' ) );
	}
	public function register_store_api() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) || ! class_exists( '\\Automattic\\WooCommerce\\StoreApi\\Schemas\\V1\\CartItemSchema' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data( array(
			'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
			'namespace'       => 'brendigo_sidrena',
			'data_callback'   => array( $this, 'item_data' ),
			'schema_callback' => array( $this, 'item_schema' ),
			'schema_type'     => ARRAY_A,
		) );
	}
	public function item_schema() {
		return array( 'properties' => array( 'text' => array( 'type' => 'string', 'readonly' => true ) ) );
	}
	public function item_data( $cart_item ) {
		if ( ! Brendigo_Sidrena_Cijena_Settings::instance()->is_auto_display_enabled() ) {
			return array( 'text' => '' );
		}
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		if ( ! $product instanceof WC_Product ) {
			return array( 'text' => '' );
		}
		$meta = Brendigo_Sidrena_Cijena_Product_Meta::instance();
		$id = $meta->display_id( $product->get_id() );
		$price = $meta->get_price( $id );
		if ( '' === $price ) {
			return array( 'text' => '' );
		}
		$amount = $meta->get_price_to_display( $product, $price );
		if ( null === $amount ) {
			return array( 'text' => '' );
		}
		$label = wp_strip_all_tags( $meta->get_display_prefix( $id ) );
		return array( 'text' => $label . ': ' . wp_strip_all_tags( wc_price( $amount ) ) );
	}
	public function enqueue() {
		if ( is_admin() || ! Brendigo_Sidrena_Cijena_Settings::instance()->is_auto_display_enabled() || ! ( function_exists( 'is_cart' ) && is_cart() || function_exists( 'is_checkout' ) && is_checkout() ) ) {
			return;
		}
		if ( ! wp_script_is( 'wc-blocks-checkout', 'registered' ) ) {
			return;
		}
		wp_enqueue_script( 'brendigo-sidrena-blocks', BRENDIGO_SIDRENA_CIJENA_URL . 'assets/js/woo-blocks.js', array( 'wc-blocks-checkout' ), BRENDIGO_SIDRENA_CIJENA_VERSION, true );
		wp_enqueue_style( 'brendigo-sidrena-blocks', BRENDIGO_SIDRENA_CIJENA_URL . 'assets/css/woo-blocks.css', array(), BRENDIGO_SIDRENA_CIJENA_VERSION );
	}
}
