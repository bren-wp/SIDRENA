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
$variation_data = Sidrena_Products::instance()->variation_reference_payload( array( 'variation_id' => 12 ), null, $product );
$frontend_css = file_get_contents( dirname( __DIR__, 2 ) . '/public/css/frontend.css' );

$compat_php = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-compatibility.php' );
$compat_js  = file_get_contents( dirname( __DIR__, 2 ) . '/public/js/compat.js' );

sidrena_woo_output_assert( false !== strpos( $html, 'sidrena-reference-prices' ), 'Automatic Woo output must append Sidrena reference wrapper.' );
sidrena_woo_output_assert( false !== strpos( $html, '29,99 €' ), 'Automatic Woo output must include the entered Sidrena price.' );
sidrena_woo_output_assert( false !== strpos( $html, 'Cijena na 10.09.2026.' ), 'Default storefront label must identify the Sidrena price and reference date.' );
sidrena_woo_output_assert( false !== strpos( $html, 'sidrena-anchor__info' ), 'Automatic Woo output must include the accessible info indicator.' );
sidrena_woo_output_assert( false !== strpos( $html, 'tabindex="0"' ), 'Sidrena tooltip trigger must be keyboard focusable.' );
sidrena_woo_output_assert( false !== strpos( $html, 'aria-describedby=' ), 'Sidrena tooltip trigger must reference its tooltip with aria-describedby.' );
sidrena_woo_output_assert( false !== strpos( $frontend_css, '.sidrena-anchor--has-tooltip:focus-visible' ), 'Sidrena tooltip needs a visible keyboard focus state.' );
sidrena_woo_output_assert( false !== strpos( $frontend_css, 'position: fixed;' ) && false !== strpos( $frontend_css, 'max-width: calc(100vw - 36px);' ), 'Mobile Sidrena tooltip viewport clamp is missing.' );

sidrena_woo_output_assert( isset( $variation_data['sidrena_reference_html'] ), 'Woo variation payload must expose Sidrena reference markup.' );
sidrena_woo_output_assert( false !== strpos( $variation_data['sidrena_reference_html'], '29,99 €' ), 'Woo variation payload must contain the variation Sidrena price.' );
sidrena_woo_output_assert( false !== strpos( $compat_php, "'endpoint'  => 'yes' === ( \$settings['enable_rest_index'] ?? 'yes' )" ), 'Woo compatibility script must keep REST as an optional fallback endpoint.' );
sidrena_woo_output_assert( false === strpos( $compat_php, "if ( 'yes' !== ( \$settings['enable_rest_index'] ?? 'yes' ) )" ), 'Woo variation synchronization must not be disabled when public REST is disabled.' );
sidrena_woo_output_assert( false !== strpos( $compat_js, 'variation.sidrena_reference_html' ), 'Woo compatibility JavaScript must prefer embedded variation reference markup.' );
sidrena_woo_output_assert( false !== strpos( $compat_js, 'applyVariationPayload' ), 'Woo compatibility JavaScript must apply embedded variation payloads without REST.' );
sidrena_woo_output_assert( false !== strpos( $compat_js, 'parentMarkup = readMarkup(document);' ), 'Woo variation reset must retain server-rendered parent markup locally.' );
sidrena_woo_output_assert( false !== strpos( $compat_js, 'typeof html !== "string"' ), 'Woo compatibility hydration must distinguish transient fallback failures from known empty markup.' );
sidrena_woo_output_assert( false !== strpos( $compat_js, 'removeMarkup(existing)' ), 'Known empty variation markup must remove stale parent reference output.' );

$again = Sidrena_Products::instance()->append_reference_prices( $html, $product );
sidrena_woo_output_assert( $again === $html, 'Repeated Woo price filtering must not duplicate Sidrena markup.' );
sidrena_woo_output_assert( 1 === substr_count( $again, 'sidrena-reference-prices' ), 'Sidrena wrapper must appear exactly once.' );

$product = null;
sidrena_woo_output_assert( '' === Sidrena_Products::instance()->shortcode( array( 'id' => 's123' ) ), 'Woo shortcode must reject standalone IDs without loading a missing class.' );

fwrite( STDOUT, "Sidrena Woo automatic price output smoke test passed.\n" );
