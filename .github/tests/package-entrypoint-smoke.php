<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author brendigo
 * @link https://brendigo.com/sidrene-cijene/
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

function sidrena_entry_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$expected_domains = array(
	'wordpress'   => 'brendigo-sidrene-cijene-digitalni-cjenici',
	'woocommerce' => 'brendigo-sidrena-cijena',
);

$legacy_entrypoints = array(
	'wordpress'   => array( 'sidrena-wordpress.php', 'sidrena-woocommerce.php', 'brendigo-sidrena-cijena.php' ),
	'woocommerce' => array( 'sidrena-wordpress.php', 'sidrena-woocommerce.php', 'brendigo-sidrene-cijene-digitalni-cjenici.php' ),
);

$expected_domain     = $expected_domains[ $edition ];
$expected_entrypoint = $expected_domain . '.php';
$main                = $root . '/' . $expected_entrypoint;

sidrena_entry_assert( is_file( $main ), "Package slug entrypoint missing: {$main}" );

foreach ( $legacy_entrypoints[ $edition ] as $legacy_entrypoint ) {
	sidrena_entry_assert(
		! is_file( $root . '/' . $legacy_entrypoint ),
		"Unexpected non-slug package entrypoint found: {$legacy_entrypoint}"
	);
}

$header = file_get_contents( $main );
preg_match( '/^ \\* Plugin Name: ([^\\r\\n]+)/m', (string) $header, $name_match );
$actual_name = isset( $name_match[1] ) ? trim( $name_match[1] ) : '';

preg_match( '/^ \\* Version: ([^\\r\\n]+)/m', (string) $header, $version_match );
$expected_version = isset( $version_match[1] ) ? trim( $version_match[1] ) : '';

preg_match( '/^ \\* Text Domain: ([^\\r\\n]+)/m', (string) $header, $domain_match );
$actual_domain = isset( $domain_match[1] ) ? trim( $domain_match[1] ) : '';

sidrena_entry_assert( 'SIDRENA' === $actual_name, 'Runtime plugin brand must remain SIDRENA.' );
sidrena_entry_assert( $expected_domain === $actual_domain, 'Entrypoint text domain does not match the public plugin slug.' );

if ( 'woocommerce' === $edition ) {
	preg_match( '/^ \\* Requires Plugins: ([^\\r\\n]+)/m', (string) $header, $requires_match );
	$actual_requires = isset( $requires_match[1] ) ? trim( $requires_match[1] ) : '';
	sidrena_entry_assert( 'woocommerce' === $actual_requires, 'WooCommerce package must declare the WooCommerce dependency header.' );
}

require $main;

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
	sidrena_entry_assert( ! class_exists( 'Sidrena_Products' ), 'WooCommerce product runtime must remain deferred until the dependency-safe plugins_loaded bootstrap.' );
	sidrena_entry_assert( ! class_exists( 'Sidrena_Bulk' ), 'WooCommerce bulk runtime must remain deferred until the dependency-safe plugins_loaded bootstrap.' );
	sidrena_entry_assert( is_file( $root . '/includes/class-sidrena-products.php' ), 'WooCommerce package is missing product integration source.' );
	sidrena_entry_assert( is_file( $root . '/includes/class-sidrena-bulk.php' ), 'WooCommerce package is missing bulk integration source.' );
	sidrena_entry_assert( ! class_exists( 'Sidrena_Standalone' ), 'Standalone catalog leaked into WooCommerce package.' );
}

fwrite( STDOUT, "Sidrena {$edition} package entrypoint smoke test passed.\n" );
