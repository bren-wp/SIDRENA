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

class WP_REST_Request {
	private $params;
	public function __construct( $params = array() ) { $this->params = $params; }
	public function get_param( $key ) { return $this->params[ $key ] ?? null; }
}
class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $code, $message, $data = array() ) {
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}
}
class WP_REST_Server {
	const READABLE = 'GET';
}
$GLOBALS['sidrena_rest_enabled']       = 'yes';
$GLOBALS['sidrena_woocommerce_active'] = false;
$GLOBALS['sidrena_woo_catalog_calls']  = 0;
$GLOBALS['sidrena_service_queries']    = 0;
$GLOBALS['sidrena_locations']          = array(
	array( 'id' => 'rijeka-centar', 'code' => 'RI-C', 'kind' => 'objekt', 'address' => 'Korzo 1', 'enabled' => 'yes', 'sequence' => 7 ),
	array( 'id' => 'rijeka-zapad', 'code' => 'RI-Z', 'kind' => 'objekt', 'address' => 'Zapad 2', 'enabled' => 'no', 'sequence' => 8 ),
);

class Sidrena_Utils {
	public static function settings() {
		return array(
			'enable_rest_index' => $GLOBALS['sidrena_rest_enabled'],
			'retention_days' => 30,
			'publish_manifest' => 'yes',
		);
	}
	public static function locations() {
		return $GLOBALS['sidrena_locations'];
	}
	public static function sanitize_location_id( $value ) {
		$value = strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) );
		return $value ? $value : 'lokacija';
	}
	public static function runtime_mode() { return 'woocommerce'; }
	public static function is_woocommerce_active() { return (bool) $GLOBALS['sidrena_woocommerce_active']; }
	public static function is_wordpress_edition() { return false; }
	public static function public_index() { return array(); }
	public static function archive_index() { return array(); }
	public static function public_file_index( $entries ) { return is_array( $entries ) ? $entries : array(); }
	public static function upload_paths() { return array( 'manifest_url' => '' ); }
}
function __( $text, $domain = null ) { unset( $domain ); return $text; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function current_time( $format ) { unset( $format ); return '2026-09-27T00:00:00+02:00'; }
function get_option( $key, $default = false ) { unset( $key ); return $default; }
function rest_ensure_response( $data ) {
	return new class( $data ) {
		public $data;
		public $headers = array();
		public function __construct( $data ) { $this->data = $data; }
		public function header( $name, $value ) { $this->headers[ $name ] = $value; }
	};
}
function rest_url( $path ) { return 'https://example.test/wp-json/' . ltrim( $path, '/' ); }
function esc_url_raw( $url ) { return (string) $url; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wc_get_products( $args = array() ) {
	unset( $args );
	++$GLOBALS['sidrena_woo_catalog_calls'];
	return array();
}
class WP_Query {
	public $posts = array();
	public $found_posts = 0;
	public $max_num_pages = 0;
	public function __construct( $args = array() ) {
		unset( $args );
		++$GLOBALS['sidrena_service_queries'];
	}
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-rest.php';

function sidrena_rest_location_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

$rest = Sidrena_REST::instance();
$method = new ReflectionMethod( 'Sidrena_REST', 'resolve_location' );
$method->setAccessible( true );

$default = $method->invoke( $rest, '' );
sidrena_rest_location_assert( 'rijeka-centar' === ( $default['id'] ?? '' ), 'Empty location must resolve to the first enabled location even when the shared location sanitizer has a non-empty fallback.' );

$default_request = new WP_REST_Request(
	array(
		'type' => 'products',
		'page' => 1,
		'per_page' => 20,
	)
);
$default_response = $rest->prices( $default_request );
sidrena_rest_location_assert( ! ( $default_response instanceof WP_Error ), 'Omitted location must not become a synthetic location_not_found error.' );
sidrena_rest_location_assert( 'rijeka-centar' === ( $default_response->data['location']['id'] ?? '' ), 'Omitted location must expose the first enabled public location.' );
sidrena_rest_location_assert( ! isset( $default_response->data['location']['enabled'] ), 'Public REST location payload must not expose internal enabled state.' );
sidrena_rest_location_assert( ! isset( $default_response->data['location']['sequence'] ), 'Public REST location payload must not expose internal ordering metadata.' );
sidrena_rest_location_assert( array( 'id', 'code', 'kind', 'address' ) === array_keys( $default_response->data['location'] ), 'Public REST location payload must use the canonical public field set.' );

$index_response = $rest->index();
foreach ( array( 'generator', 'ruleset', 'rules_effective', 'catalog_mode', 'woocommerce_active', 'product_count', 'retention_days' ) as $internal_key ) {
	sidrena_rest_location_assert( ! array_key_exists( $internal_key, $index_response->data ), 'Public index must not expose internal runtime metadata: ' . $internal_key );
}
sidrena_rest_location_assert( 'https://brendigo.com/sidrene-cijene/' === ( $index_response->data['plugin_url'] ?? '' ), 'Public index must retain the canonical plugin URL.' );

$known = $method->invoke( $rest, 'RIJEKA-CENTAR' );
sidrena_rest_location_assert( 'rijeka-centar' === ( $known['id'] ?? '' ), 'Known explicit location must resolve after sanitization.' );

$disabled = $method->invoke( $rest, 'rijeka-zapad' );
sidrena_rest_location_assert( null === $disabled, 'Disabled explicit location must not silently fall back to another location.' );

$unknown = $method->invoke( $rest, 'ne-postoji' );
sidrena_rest_location_assert( null === $unknown, 'Unknown explicit location must not silently fall back to another location.' );

$request = new WP_REST_Request(
	array(
		'type' => 'products',
		'page' => 1,
		'per_page' => 20,
		'location' => 'ne-postoji',
	)
);
$response = $rest->prices( $request );
sidrena_rest_location_assert( $response instanceof WP_Error, 'Unknown explicit location must return WP_Error.' );
sidrena_rest_location_assert( 'location_not_found' === $response->code, 'Unknown explicit location must return location_not_found.' );
sidrena_rest_location_assert( 404 === ( $response->data['status'] ?? 0 ), 'Unknown explicit location must return HTTP 404.' );

$GLOBALS['sidrena_locations'] = array();
$GLOBALS['sidrena_woocommerce_active'] = true;
$no_location_response = $rest->prices(
	new WP_REST_Request(
		array(
			'type' => 'all',
			'page' => 1,
			'per_page' => 20,
		)
	)
);
sidrena_rest_location_assert( ! ( $no_location_response instanceof WP_Error ), 'Omitted location with no enabled locations must return a safe empty response.' );
sidrena_rest_location_assert( array() === ( $no_location_response->data['location'] ?? null ), 'No enabled location must keep the public location payload empty.' );
sidrena_rest_location_assert( array( 'items' => array(), 'total' => 0, 'total_pages' => 0 ) === ( $no_location_response->data['products'] ?? null ), 'No enabled location must expose an empty product collection.' );
sidrena_rest_location_assert( array( 'items' => array(), 'total' => 0, 'total_pages' => 0 ) === ( $no_location_response->data['services'] ?? null ), 'No enabled location must expose an empty service collection.' );
sidrena_rest_location_assert( 0 === $GLOBALS['sidrena_woo_catalog_calls'], 'No enabled location must not fall back to scanning the global WooCommerce catalog.' );
sidrena_rest_location_assert( 0 === $GLOBALS['sidrena_service_queries'], 'No enabled location must not query public services with a synthetic location.' );

$GLOBALS['sidrena_rest_enabled'] = 'no';
$display_response = $rest->display( new WP_REST_Request( array( 'id' => '1' ) ) );
sidrena_rest_location_assert( $display_response instanceof WP_Error, 'Display endpoint must not remain public when REST API is disabled.' );
sidrena_rest_location_assert( 'disabled' === $display_response->code, 'Disabled display endpoint must return the shared disabled error code.' );
sidrena_rest_location_assert( 404 === ( $display_response->data['status'] ?? 0 ), 'Disabled display endpoint must return HTTP 404.' );

fwrite( STDOUT, "Sidrena REST location resolution smoke test passed.\n" );
