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
	public static function decimal( $value ) {
		return '' === $value ? '' : (float) $value;
	}
}

class Mock_Sidrena_Location_Order_Product {
	private $children;
	private $id;
	private $type;
	private $parent_id;

	public function __construct( $children, $id = 0, $type = 'variable', $parent_id = 0 ) {
		$this->children  = $children;
		$this->id        = $id;
		$this->type      = $type;
		$this->parent_id = $parent_id;
	}

	public function get_children() {
		return $this->children;
	}
	public function get_id() {
		return $this->id;
	}
	public function is_type( $type ) {
		return $this->type === $type;
	}
	public function get_parent_id() {
		return $this->parent_id;
	}
}

$GLOBALS['sidrena_location_order_products'] = array(
	2  => new Mock_Sidrena_Location_Order_Product( array( 22, 20, 21 ), 2 ),
	22 => new Mock_Sidrena_Location_Order_Product( array(), 22, 'variation', 2 ),
);

function wc_get_product( $id ) {
	return $GLOBALS['sidrena_location_order_products'][ (int) $id ] ?? false;
}

class Mock_Sidrena_WPDB {
	public $prefix = 'wp_';
	public $last_prepare_args = array();
	public $get_results_calls = 0;
	public $get_row_calls = 0;

	public function prepare( $query, ...$args ) {
		$this->last_prepare_args = $args;
		return $query;
	}

	public function get_results( $query, $format ) {
		unset( $query, $format );
		++$this->get_results_calls;
		return array(
			array( 'product_id' => 1, 'variation_id' => 0 ),
			array( 'product_id' => 2, 'variation_id' => 20 ),
			array( 'product_id' => 2, 'variation_id' => 21 ),
			array( 'product_id' => 2, 'variation_id' => 22 ),
		);
	}

	public function get_row( $query, $format ) {
		unset( $query, $format );
		++$this->get_row_calls;
		return array(
			'price'        => '12.340000',
			'anchor_price' => '14.000000',
			'availability' => 'dostupno',
			'updated_at'   => '2026-09-30 03:00:00',
		);
	}
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
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

$results_before = $GLOBALS['wpdb']->get_results_calls;
$variation_data = Sidrena_Location_Data::get_for_product( 'loc-1', $GLOBALS['sidrena_location_order_products'][22] );
sidrena_location_order_assert(
	12.34 === $variation_data['price'] && 'dostupno' === $variation_data['availability'],
	'Per-product location lookup must return the indexed location override.'
);
sidrena_location_order_assert(
	$results_before === $GLOBALS['wpdb']->get_results_calls && 1 === $GLOBALS['wpdb']->get_row_calls,
	'Per-product location lookup must use one targeted indexed row query instead of loading the complete location dataset.'
);
$variation_cached = Sidrena_Location_Data::get_for_product( 'loc-1', $GLOBALS['sidrena_location_order_products'][22] );
sidrena_location_order_assert(
	$variation_data === $variation_cached && 1 === $GLOBALS['wpdb']->get_row_calls,
	'Per-product location override must be request-cached after the targeted lookup.'
);

fwrite( STDOUT, "Sidrena location variation-order smoke test passed.\n" );
