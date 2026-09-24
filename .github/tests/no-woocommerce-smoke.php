<?php
define( 'ABSPATH', __DIR__ . '/' );
define( 'WP_CLI', false );

$GLOBALS['sidrena_actions']    = array();
$GLOBALS['sidrena_filters']    = array();
$GLOBALS['sidrena_shortcodes'] = array();
$GLOBALS['sidrena_scheduled']  = array(
	'sidrena_history_seed' => 12345,
);

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $priority, $accepted_args );
	$GLOBALS['sidrena_actions'][ $hook ][] = $callback;
}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $priority, $accepted_args );
	$GLOBALS['sidrena_filters'][ $hook ][] = $callback;
}
function add_shortcode( $tag, $callback ) {
	$GLOBALS['sidrena_shortcodes'][ $tag ] = $callback;
}
function register_activation_hook( $file, $callback ) {
	unset( $file, $callback );
}
function register_deactivation_hook( $file, $callback ) {
	unset( $file, $callback );
}
function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}
function plugin_dir_url( $file ) {
	unset( $file );
	return 'https://example.test/wp-content/plugins/sidrena/';
}
function plugin_basename( $file ) {
	return basename( $file );
}
function load_plugin_textdomain( $domain, $deprecated = false, $path = '' ) {
	unset( $domain, $deprecated, $path );
	return true;
}
function is_admin() {
	return true;
}
function __( $text, $domain = null ) {
	unset( $domain );
	return $text;
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, is_array( $args ) ? $args : array() );
}
function absint( $value ) {
	return abs( (int) $value );
}
function wp_timezone() {
	return new DateTimeZone( 'Europe/Zagreb' );
}
function get_option( $key, $default = false ) {
	if ( 'sidrena_history_seeded_at' === $key ) {
		return false;
	}
	if ( 'sidrena_settings' === $key ) {
		return array();
	}
	return $default;
}
function wp_next_scheduled( $hook ) {
	return isset( $GLOBALS['sidrena_scheduled'][ $hook ] ) ? $GLOBALS['sidrena_scheduled'][ $hook ] : false;
}
function wp_schedule_event( $timestamp, $recurrence, $hook ) {
	unset( $recurrence );
	$GLOBALS['sidrena_scheduled'][ $hook ] = $timestamp;
	return true;
}
function wp_schedule_single_event( $timestamp, $hook ) {
	$GLOBALS['sidrena_scheduled'][ $hook ] = $timestamp;
	return true;
}
function wp_clear_scheduled_hook( $hook ) {
	unset( $GLOBALS['sidrena_scheduled'][ $hook ] );
	return 1;
}

require dirname( __DIR__, 2 ) . '/sidrena.php';

function sidrena_no_woo_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

sidrena_no_woo_assert( ! class_exists( 'WooCommerce' ), 'Test environment must not load WooCommerce.' );
sidrena_no_woo_assert( ! function_exists( 'wc_get_product' ), 'Test environment must not expose WooCommerce product functions.' );
sidrena_no_woo_assert( 'standalone' === Sidrena_Utils::runtime_mode(), 'Runtime mode must be standalone without WooCommerce.' );
sidrena_no_woo_assert( function_exists( 'sidrena_cijena' ), 'Universal Sidrena template helper must exist without WooCommerce.' );

Sidrena_Plugin::instance()->run();

foreach ( array(
	'Sidrena_Audit',
	'Sidrena_Service_History',
	'Sidrena_Location_History',
	'Sidrena_Standalone',
	'Sidrena_Services',
	'Sidrena_Pricelist',
	'Sidrena_REST',
	'Sidrena_Public',
	'Sidrena_Bulk',
	'Sidrena_Site_Health',
	'Sidrena_Admin',
) as $class ) {
	sidrena_no_woo_assert( class_exists( $class ), $class . ' must load without WooCommerce.' );
}

sidrena_no_woo_assert( isset( $GLOBALS['sidrena_shortcodes']['sidrena_cijena'] ), 'Standalone sidrena_cijena shortcode must register without WooCommerce.' );
sidrena_no_woo_assert( isset( $GLOBALS['sidrena_shortcodes']['sidrena-cijena'] ), 'Standalone sidrena-cijena shortcode must register without WooCommerce.' );
sidrena_no_woo_assert( ! empty( $GLOBALS['sidrena_actions']['admin_menu'] ), 'Sidrena admin menu must register without WooCommerce.' );
sidrena_no_woo_assert( ! empty( $GLOBALS['sidrena_actions']['admin_post_sidrena_standalone_import'] ), 'Standalone import must register without WooCommerce.' );
sidrena_no_woo_assert( ! empty( $GLOBALS['sidrena_actions']['admin_post_sidrena_standalone_save'] ), 'Standalone save must register without WooCommerce.' );

foreach ( array(
	'admin_post_sidrena_import_anchor',
	'admin_post_sidrena_import_location_data',
	'admin_post_sidrena_export_missing',
	'admin_post_sidrena_export_location_template',
	'admin_post_sidrena_bulk_save',
	'woocommerce_update_product',
	'woocommerce_update_product_variation',
) as $hook ) {
	sidrena_no_woo_assert( empty( $GLOBALS['sidrena_actions'][ $hook ] ), $hook . ' must not register in standalone mode.' );
}

foreach ( array_keys( $GLOBALS['sidrena_filters'] ) as $hook ) {
	sidrena_no_woo_assert( 0 !== strpos( $hook, 'woocommerce_' ), 'WooCommerce filter registered in standalone mode: ' . $hook );
}

$method = new ReflectionMethod( 'Sidrena_Activator', 'ensure_schedules' );
$method->setAccessible( true );
$method->invoke( null );
sidrena_no_woo_assert( isset( $GLOBALS['sidrena_scheduled']['sidrena_daily_generation'] ), 'Daily Sidrena generation must remain scheduled without WooCommerce.' );
sidrena_no_woo_assert( ! isset( $GLOBALS['sidrena_scheduled']['sidrena_history_seed'] ), 'WooCommerce history seed must be cleared in standalone mode.' );

$main = file_get_contents( dirname( __DIR__, 2 ) . '/sidrena.php' );
sidrena_no_woo_assert( 0 === preg_match( '/^ \* Requires Plugins:.*woocommerce/im', $main ), 'Sidrena must not declare WooCommerce as a hard dependency.' );

fwrite( STDOUT, "Sidrena real no-WooCommerce runtime smoke test passed.\n" );
