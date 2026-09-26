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
class Sidrena_Utils {
	public static function settings() {
		return array( 'enable_rest_index' => 'yes', 'retention_days' => 30 );
	}
	public static function locations() {
		return array(
			array( 'id' => 'rijeka-centar', 'code' => 'RI-C', 'enabled' => 'yes' ),
			array( 'id' => 'rijeka-zapad', 'code' => 'RI-Z', 'enabled' => 'no' ),
		);
	}
	public static function sanitize_location_id( $value ) {
		return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) );
	}
	public static function runtime_mode() { return 'woocommerce'; }
	public static function is_woocommerce_active() { return true; }
	public static function is_wordpress_edition() { return false; }
	public static function public_index() { return array(); }
	public static function archive_index() { return array(); }
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
sidrena_rest_location_assert( 'rijeka-centar' === ( $default['id'] ?? '' ), 'Empty location must resolve to the first enabled location.' );

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

fwrite( STDOUT, "Sidrena REST location resolution smoke test passed.\n" );
