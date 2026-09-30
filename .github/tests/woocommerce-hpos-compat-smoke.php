<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

$root = dirname( __DIR__, 2 );
$woo  = file_get_contents( $root . '/editions/woocommerce/sidrena-woocommerce.php' );
$wp   = file_get_contents( $root . '/editions/wordpress/sidrena-wordpress.php' );

function sidrena_hpos_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

sidrena_hpos_assert( false !== $woo, 'Unable to read WooCommerce entrypoint.' );
sidrena_hpos_assert( false !== $wp, 'Unable to read WordPress entrypoint.' );
sidrena_hpos_assert( false !== strpos( $woo, "'before_woocommerce_init'" ), 'WooCommerce edition must declare feature compatibility before WooCommerce initialization.' );
sidrena_hpos_assert( false !== strpos( $woo, "FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true )" ), 'WooCommerce edition must declare verified HPOS compatibility.' );
sidrena_hpos_assert( false === strpos( $wp, 'FeaturesUtil::declare_compatibility' ), 'Standalone WordPress edition must not declare WooCommerce feature compatibility.' );
sidrena_hpos_assert( false === strpos( $woo, "declare_compatibility( 'product_block_editor'" ), 'Product block editor compatibility must not be declared until separately tested.' );
sidrena_hpos_assert( false === strpos( $woo, "declare_compatibility( 'cart_checkout_blocks'" ), 'Cart/Checkout Blocks compatibility must not be over-declared when SIDRENA does not need that feature declaration.' );

fwrite( STDOUT, "SIDRENA WooCommerce HPOS compatibility declaration smoke test passed.\n" );
