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
define( 'SIDRENA_EDITION', 'woocommerce' );

class WC_Product {
	private $id;
	public function __construct( $id ) { $this->id = $id; }
	public function get_id() { return $this->id; }
	public function is_type( $type ) { return false; }
	public function is_on_sale() { return false; }
}

$GLOBALS['sidrena_meta'] = array(
	12 => array(
		'_sidrena_anchor_price' => '29.99',
		'_sidrena_anchor_date' => '2026-09-10',
	),
);

function get_option( $key, $default = false ) {
	if ( 'sidrena_settings' === $key ) {
		return array(
			'display_anchor' => 'yes',
			'display_lowest_30' => 'no',
			'anchor_tooltip_enabled' => 'yes',
			'anchor_tooltip_text' => 'Sidrena cijena je referentna redovna cijena na prikazani datum.',
		);
	}
	return $default;
}
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function get_post_meta( $id, $key, $single = true ) { unset( $single ); return $GLOBALS['sidrena_meta'][ (int) $id ][ $key ] ?? ''; }
function wp_get_post_parent_id( $id ) { unset( $id ); return 0; }
function wp_timezone() { return new DateTimeZone( 'Europe/Zagreb' ); }
function __( $text, $domain = null ) { unset( $domain ); return $text; }
function apply_filters( $tag, $value ) { unset( $tag ); return $value; }
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function wc_price( $price ) { return number_format( (float) $price, 2, ',', '' ) . ' €'; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function wp_kses_post( $html ) { return $html; }

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php';
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-products.php';

function sidrena_woo_output_assert( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); }
}

$product = new WC_Product( 12 );
$html = Sidrena_Products::instance()->append_reference_prices( '<span class="price">19,99 €</span>', $product );

sidrena_woo_output_assert( false !== strpos( $html, 'sidrena-reference-prices' ), 'Automatic Woo output must append Sidrena reference wrapper.' );
sidrena_woo_output_assert( false !== strpos( $html, '29,99 €' ), 'Automatic Woo output must include the entered Sidrena price.' );
sidrena_woo_output_assert( false !== strpos( $html, 'Sidrena cijena (10.09.2026.)' ), 'Default storefront label must identify the Sidrena price and reference date.' );
sidrena_woo_output_assert( false !== strpos( $html, 'sidrena-anchor__info' ), 'Automatic Woo output must include the accessible info indicator.' );

$again = Sidrena_Products::instance()->append_reference_prices( $html, $product );
sidrena_woo_output_assert( $again === $html, 'Repeated Woo price filtering must not duplicate Sidrena markup.' );
sidrena_woo_output_assert( 1 === substr_count( $again, 'sidrena-reference-prices' ), 'Sidrena wrapper must appear exactly once.' );

$product = null;
sidrena_woo_output_assert( '' === Sidrena_Products::instance()->shortcode( array( 'id' => 's123' ) ), 'Woo shortcode must reject standalone IDs without loading a missing class.' );

fwrite( STDOUT, "Sidrena Woo automatic price output smoke test passed.\n" );
