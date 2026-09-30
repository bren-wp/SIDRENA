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

function absint( $value ) {
	return abs( (int) $value );
}
function trailingslashit( $value ) {
	return rtrim( (string) $value, "/\\" ) . '/';
}
function wp_parse_url( $url ) {
	return parse_url( $url );
}
function wp_http_validate_url( $url ) {
	$parts = parse_url( (string) $url );
	return is_array( $parts )
		&& in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true )
		&& ! empty( $parts['host'] );
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-admin.php';

function sidrena_http_policy_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

$admin  = Sidrena_Admin::instance();
$method = new ReflectionMethod( 'Sidrena_Admin', 'is_allowed_public_check_url' );
$method->setAccessible( true );
$base = 'https://shop.example.test/wp-content/uploads/sidrena/';

$allowed = array(
	'https://shop.example.test/wp-content/uploads/sidrena/current/cjenik.csv',
	'https://SHOP.EXAMPLE.TEST/wp-content/uploads/sidrena/archive/cjenik.xml',
	'https://shop.example.test:443/wp-content/uploads/sidrena/current/cjenik.csv',
);
foreach ( $allowed as $url ) {
	sidrena_http_policy_assert( true === $method->invoke( $admin, $url, $base ), 'Expected public self-check URL to be allowed: ' . $url );
}

$blocked = array(
	'http://shop.example.test/wp-content/uploads/sidrena/current/cjenik.csv',
	'https://shop.example.test.evil.invalid/wp-content/uploads/sidrena/current/cjenik.csv',
	'https://shop.example.test:8443/wp-content/uploads/sidrena/current/cjenik.csv',
	'https://user:pass@shop.example.test/wp-content/uploads/sidrena/current/cjenik.csv',
	'https://shop.example.test/wp-content/uploads/sidrena-evil/cjenik.csv',
	'https://shop.example.test/wp-content/uploads/sidrena/../private/secret.csv',
	'https://shop.example.test/wp-content/uploads/sidrena/%2e%2e/private/secret.csv',
	'https://shop.example.test/wp-content/uploads/sidrena/',
);
foreach ( $blocked as $url ) {
	sidrena_http_policy_assert( false === $method->invoke( $admin, $url, $base ), 'Expected public self-check URL to be blocked: ' . $url );
}

fwrite( STDOUT, "SIDRENA public HTTP URL policy smoke test passed.\n" );
