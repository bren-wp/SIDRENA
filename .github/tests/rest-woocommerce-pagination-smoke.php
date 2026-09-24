<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://sidrene-cijene.com.hr/
 * @see https://brendigo.com/
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_REST_Request {}
class Sidrena_Location_Data {
	public static function get_for_product( $location_id, $product ) {
		unset( $location_id, $product );
		return array();
	}
}
class Sidrena_Utils {
	public static function is_wordpress_edition() { return false; }
	public static function is_woocommerce_active() { return true; }
	public static function is_public_wc_product( $product ) { return $product instanceof Mock_Sidrena_Product && $product->is_public; }
	public static function sanitize_location_id( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) ); }
	public static function decimal( $value ) { return '' === $value ? '' : (float) $value; }
	public static function product_anchor_price( $id ) { return 100 + (int) $id; }
	public static function product_meta_with_parent( $product, $key, $default = '' ) {
		unset( $product );
		return '_sidrena_unit_price_status' === $key ? 'review' : $default;
	}
	public static function get_product_code( $product ) { return 'SKU' . $product->get_id(); }
	public static function get_brand( $product ) { unset( $product ); return 'Brend'; }
	public static function current_reference_date( $id ) { unset( $id ); return '2026-09-10'; }
	public static function get_barcode( $product ) { return 'BAR' . $product->get_id(); }
}
class Mock_Sidrena_Product {
	public $is_public = true;
	private $id;
	private $type;
	private $children;
	private $parent_id;
	private $visibility;
	public function __construct( $id, $type = 'simple', $children = array(), $parent_id = 0, $visibility = 'visible' ) {
		$this->id = $id;
		$this->type = $type;
		$this->children = $children;
		$this->parent_id = $parent_id;
		$this->visibility = $visibility;
	}
	public function get_id() { return $this->id; }
	public function is_type( $type ) { return $this->type === $type; }
	public function get_children() { return $this->children; }
	public function get_parent_id() { return $this->parent_id; }
	public function get_catalog_visibility() { return $this->visibility; }
	public function exists() { return true; }
	public function get_price( $context = '' ) { unset( $context ); return (string) $this->id; }
	public function is_in_stock() { return true; }
	public function get_name() { return 'Proizvod ' . $this->id; }
	public function is_on_sale( $context = '' ) { unset( $context ); return false; }
}

$GLOBALS['sidrena_wc_products'] = array(
	1  => new Mock_Sidrena_Product( 1 ),
	2  => new Mock_Sidrena_Product( 2, 'variable', array( 20, 21, 22 ) ),
	3  => new Mock_Sidrena_Product( 3, 'simple', array(), 0, 'hidden' ),
	4  => new Mock_Sidrena_Product( 4 ),
	5  => new Mock_Sidrena_Product( 5 ),
	20 => new Mock_Sidrena_Product( 20, 'variation', array(), 2 ),
	21 => new Mock_Sidrena_Product( 21, 'variation', array(), 2 ),
	22 => new Mock_Sidrena_Product( 22, 'variation', array(), 2 ),
);
$GLOBALS['sidrena_wc_queries'] = array();

function wc_get_products( $args ) {
	$GLOBALS['sidrena_wc_queries'][] = $args;
	return 1 === (int) ( $args['page'] ?? 1 )
		? array(
			$GLOBALS['sidrena_wc_products'][1],
			$GLOBALS['sidrena_wc_products'][2],
			$GLOBALS['sidrena_wc_products'][3],
			$GLOBALS['sidrena_wc_products'][4],
			$GLOBALS['sidrena_wc_products'][5],
		)
		: array();
}
function wc_get_product( $id ) { return $GLOBALS['sidrena_wc_products'][ (int) $id ] ?? false; }
function get_post_meta( $id, $key, $single = true ) {
	unset( $single );
	if ( '_sidrena_cjenik_visibility' === $key && 4 === (int) $id ) {
		return 'exclude';
	}
	return '';
}
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function get_post_modified_time( $format, $gmt, $id ) { unset( $format, $gmt ); return '2026-09-24T10:00:00+00:00'; }

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-rest.php';

function sidrena_rest_page_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$method = new ReflectionMethod( 'Sidrena_REST', 'realtime_products' );
$method->setAccessible( true );
$rest = Sidrena_REST::instance();

$page1 = $method->invoke( $rest, array( 'id' => 'loc-1', 'code' => 'L1' ), 1, 2 );
sidrena_rest_page_assert( 5 === $page1['total'], 'Woo REST total must count flattened public items, including variations and excluding hidden/excluded parents.' );
sidrena_rest_page_assert( 3 === $page1['total_pages'], 'Woo REST total_pages must use flattened item count.' );
sidrena_rest_page_assert( 2 === count( $page1['items'] ), 'Woo REST page must never exceed per_page.' );
sidrena_rest_page_assert( 1 === $page1['items'][0]['id'], 'Woo REST first item should be the simple product.' );
sidrena_rest_page_assert( 20 === $page1['items'][1]['id'], 'Woo REST variable parent must expand to its first variation.' );

$page2 = $method->invoke( $rest, array( 'id' => 'loc-1', 'code' => 'L1' ), 2, 2 );
sidrena_rest_page_assert( array( 21, 22 ) === array_column( $page2['items'], 'id' ), 'Woo REST second page must continue through flattened variations.' );
sidrena_rest_page_assert( 2 === count( $page2['items'] ), 'Woo REST second page must respect per_page.' );

$page3 = $method->invoke( $rest, array( 'id' => 'loc-1', 'code' => 'L1' ), 3, 2 );
sidrena_rest_page_assert( array( 5 ) === array_column( $page3['items'], 'id' ), 'Woo REST final page must contain the remaining public item.' );

sidrena_rest_page_assert( ! isset( $GLOBALS['sidrena_wc_queries'][0]['paginate'] ), 'Woo REST flattened iterator must not use parent-product paginate totals.' );
sidrena_rest_page_assert( 100 === $GLOBALS['sidrena_wc_queries'][0]['limit'], 'Woo REST flattened iterator must fetch bounded catalog batches.' );

fwrite( STDOUT, "Sidrena Woo REST pagination smoke test passed.\n" );
