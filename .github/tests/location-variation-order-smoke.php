<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );

function absint( $value ) {
	return abs( (int) $value );
}

class Sidrena_Utils {
	public static function sanitize_location_id( $value ) {
		$value = strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) );
		return $value ? $value : 'lokacija';
	}
}

class Mock_Sidrena_Location_Order_Product {
	private $children;

	public function __construct( $children ) {
		$this->children = $children;
	}

	public function get_children() {
		return $this->children;
	}
}

$GLOBALS['sidrena_location_order_products'] = array(
	2 => new Mock_Sidrena_Location_Order_Product( array( 22, 20, 21 ) ),
);

function wc_get_product( $id ) {
	return $GLOBALS['sidrena_location_order_products'][ (int) $id ] ?? false;
}

class Mock_Sidrena_WPDB {
	public $prefix = 'wp_';

	public function prepare( $query, ...$args ) {
		unset( $args );
		return $query;
	}

	public function get_results( $query, $format ) {
		unset( $query, $format );
		return array(
			array( 'product_id' => 1, 'variation_id' => 0 ),
			array( 'product_id' => 2, 'variation_id' => 20 ),
			array( 'product_id' => 2, 'variation_id' => 21 ),
			array( 'product_id' => 2, 'variation_id' => 22 ),
		);
	}
}

$GLOBALS['wpdb'] = new Mock_Sidrena_WPDB();

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-location-data.php';

function sidrena_location_order_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

$ids = Sidrena_Location_Data::available_item_ids_for_location( 'loc-1' );
sidrena_location_order_assert(
	array( 1, 22, 20, 21 ) === $ids,
	'Physical-location candidates must preserve WooCommerce configured variation order.'
);

$cached = Sidrena_Location_Data::available_item_ids_for_location( 'loc-1' );
sidrena_location_order_assert(
	$ids === $cached,
	'Physical-location candidate ordering must remain stable when served from the request cache.'
);

fwrite( STDOUT, "Sidrena location variation-order smoke test passed.\n" );
