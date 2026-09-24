<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://sidrene-cijene.com.hr/
 * @see https://brendigo.com/
 */

$root    = isset( $argv[1] ) ? rtrim( (string) $argv[1], '/\\' ) : '';
$edition = isset( $argv[2] ) ? (string) $argv[2] : '';

if ( ! is_dir( $root ) || ! in_array( $edition, array( 'wordpress', 'woocommerce' ), true ) ) {
	fwrite( STDERR, "Usage: php package-entrypoint-smoke.php <package-root> wordpress|woocommerce\n" );
	exit( 2 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['sidrena_entry_actions'] = array();

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}
function plugin_dir_url( $file ) {
	unset( $file );
	return 'https://example.test/wp-content/plugins/sidrena-test/';
}
function plugin_basename( $file ) {
	return basename( $file );
}
function sanitize_key( $value ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) );
}
function register_activation_hook( $file, $callback ) {
	unset( $file, $callback );
}
function register_deactivation_hook( $file, $callback ) {
	unset( $file, $callback );
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $priority, $accepted_args );
	$GLOBALS['sidrena_entry_actions'][ $hook ][] = $callback;
}

$main = $root . '/sidrena-' . $edition . '.php';
if ( ! is_file( $main ) ) {
	fwrite( STDERR, "Package entrypoint missing: {$main}\n" );
	exit( 1 );
}

$header = file_get_contents( $main );
preg_match( '/^ \\* Version: ([^\\r\\n]+)/m', (string) $header, $version_match );
$expected_version = isset( $version_match[1] ) ? trim( $version_match[1] ) : '';

require $main;

function sidrena_entry_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

sidrena_entry_assert( defined( 'SIDRENA_EDITION' ) && SIDRENA_EDITION === $edition, 'Entrypoint defined the wrong edition.' );
sidrena_entry_assert( '' !== $expected_version && defined( 'SIDRENA_VERSION' ) && $expected_version === SIDRENA_VERSION, 'Entrypoint version mismatch.' );
sidrena_entry_assert( defined( 'SIDRENA_DIR' ) && realpath( SIDRENA_DIR ) === realpath( $root ), 'SIDRENA_DIR does not point to the package root.' );
sidrena_entry_assert( function_exists( 'sidrena_cijena' ), 'Template helper was not loaded through the package entrypoint.' );
sidrena_entry_assert( class_exists( 'Sidrena_Plugin' ), 'Common plugin bootstrap did not load.' );
sidrena_entry_assert( ! empty( $GLOBALS['sidrena_entry_actions']['plugins_loaded'] ), 'plugins_loaded bootstrap hook was not registered.' );

if ( 'wordpress' === $edition ) {
	sidrena_entry_assert( class_exists( 'Sidrena_Standalone' ), 'WordPress package did not load its catalog class.' );
	sidrena_entry_assert( ! class_exists( 'Sidrena_Products' ), 'Woo product class leaked into WordPress package.' );
	sidrena_entry_assert( ! class_exists( 'Sidrena_Bulk' ), 'Woo bulk class leaked into WordPress package.' );
} else {
	sidrena_entry_assert( class_exists( 'Sidrena_Products' ), 'WooCommerce package did not load product integration.' );
	sidrena_entry_assert( class_exists( 'Sidrena_Bulk' ), 'WooCommerce package did not load bulk integration.' );
	sidrena_entry_assert( ! class_exists( 'Sidrena_Standalone' ), 'Standalone catalog leaked into WooCommerce package.' );
}

fwrite( STDOUT, "Sidrena {$edition} package entrypoint smoke test passed.\n" );
