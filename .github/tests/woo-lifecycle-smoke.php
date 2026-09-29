<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['sidrena_actions'] = array();
$GLOBALS['sidrena_regeneration_queues'] = 0;

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['sidrena_actions'][ $hook ][] = array( $callback, $priority, $accepted_args );
}
function absint( $value ) {
	return abs( (int) $value );
}
function get_post_type( $id ) {
	unset( $id );
	return 'product';
}

final class Sidrena_Utils {
	public static function is_woocommerce_active() {
		return true;
	}
	public static function is_wordpress_edition() {
		return false;
	}
}

final class Sidrena_Pricelist {
	public static function queue_regeneration() {
		++$GLOBALS['sidrena_regeneration_queues'];
		return true;
	}
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-history.php';

function sidrena_woo_lifecycle_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

$history = Sidrena_History::instance();
$history->hooks();

foreach ( array( 'woocommerce_update_product', 'woocommerce_update_product_variation' ) as $hook ) {
	$callbacks = $GLOBALS['sidrena_actions'][ $hook ] ?? array();
	$found     = false;
	foreach ( $callbacks as $entry ) {
		if ( is_array( $entry[0] ) && 'capture_woocommerce_update' === $entry[0][1] ) {
			$found = true;
			break;
		}
	}
	sidrena_woo_lifecycle_assert( $found, 'Woo lifecycle update hook must capture history and refresh the current pricelist: ' . $hook );
}

$history->capture_price_meta_change( 1, 123, '_price', '9.99' );
sidrena_woo_lifecycle_assert( 1 === $GLOBALS['sidrena_regeneration_queues'], 'Direct Woo _price changes must queue the current pricelist refresh.' );

$history->capture_price_meta_change( 2, 123, '_sale_price', '8.99' );
sidrena_woo_lifecycle_assert( 2 === $GLOBALS['sidrena_regeneration_queues'], 'Woo sale-price changes, including scheduled sale transitions, must queue the current pricelist refresh.' );

$history->capture_price_meta_change( 3, 123, '_unrelated_meta', 'x' );
sidrena_woo_lifecycle_assert( 2 === $GLOBALS['sidrena_regeneration_queues'], 'Unrelated product metadata must not queue a price-change refresh through the history listener.' );

$product_source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-products.php' );
sidrena_woo_lifecycle_assert(
	false !== strpos( $product_source, "add_action( 'woocommerce_new_product_variation', array( \$this, 'snapshot_new_variation' ), 20, 1 );" )
	&& false !== strpos( $product_source, "public function snapshot_new_variation( \$variation_id )" )
	&& false !== strpos( $product_source, 'Sidrena_Pricelist::queue_regeneration();' ),
	'New Woo variations must remain connected to SIDRENA anchor snapshot and current-pricelist refresh behavior.'
);

fwrite( STDOUT, "Sidrena Woo lifecycle refresh smoke test passed.\n" );
