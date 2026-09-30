<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */


$root = isset( $argv[1] ) && is_dir( $argv[1] ) ? rtrim( (string) $argv[1], '/\\' ) : dirname( __DIR__, 2 );
$main = is_file( $root . '/brendigo-sidrena-cijena.php' ) ? $root . '/brendigo-sidrena-cijena.php' : $root . '/editions/woocommerce/brendigo-sidrena-cijena.php';

define( 'ABSPATH', __DIR__ . '/' );
define( 'WP_CLI', false );
define( 'SIDRENA_VERSION', '0.4.0' );
define( 'SIDRENA_EDITION', 'woocommerce' );
define( 'SIDRENA_FILE', $main );
define( 'SIDRENA_DIR', $root . '/' );
define( 'SIDRENA_URL', 'https://example.test/wp-content/plugins/brendigo-sidrena-cijena/' );

class WooCommerce {}
class WC_Product {}
function wc_get_product( $id = 0 ) { unset( $id ); return false; }

$GLOBALS['sidrena_actions'] = array();
$GLOBALS['sidrena_filters'] = array();
$GLOBALS['sidrena_shortcodes'] = array();
$GLOBALS['sidrena_scheduled'] = array();

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $priority, $accepted_args );
	$GLOBALS['sidrena_actions'][ $hook ][] = $callback;
}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $priority, $accepted_args );
	$GLOBALS['sidrena_filters'][ $hook ][] = $callback;
}
function add_shortcode( $tag, $callback ) { $GLOBALS['sidrena_shortcodes'][ $tag ] = $callback; }
function register_activation_hook( $file, $callback ) { unset( $file, $callback ); }
function register_deactivation_hook( $file, $callback ) { unset( $file, $callback ); }
function plugin_basename( $file ) { return basename( $file ); }
function load_plugin_textdomain( $domain, $deprecated = false, $path = '' ) { unset( $domain, $deprecated, $path ); return true; }
function is_admin() { return true; }
function __( $text, $domain = null ) { unset( $domain ); return $text; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_timezone() { return new DateTimeZone( 'Europe/Zagreb' ); }
function get_option( $key, $default = false ) {
	if ( 'sidrena_history_seeded_at' === $key ) return false;
	if ( 'sidrena_settings' === $key ) return array();
	return $default;
}
function wp_next_scheduled( $hook ) { return isset( $GLOBALS['sidrena_scheduled'][ $hook ] ) ? $GLOBALS['sidrena_scheduled'][ $hook ] : false; }
function wp_schedule_event( $timestamp, $recurrence, $hook ) { unset( $recurrence ); $GLOBALS['sidrena_scheduled'][ $hook ] = $timestamp; return true; }
function wp_schedule_single_event( $timestamp, $hook ) { $GLOBALS['sidrena_scheduled'][ $hook ] = $timestamp; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['sidrena_scheduled'][ $hook ] ); return 1; }

require $root . '/includes/sidrena-bootstrap.php';

function sidrena_woo_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

sidrena_woo_assert( 'woocommerce' === Sidrena_Utils::edition(), 'WooCommerce edition marker is incorrect.' );
sidrena_woo_assert( Sidrena_Utils::is_woocommerce_active(), 'WooCommerce edition must detect Woo runtime.' );
sidrena_woo_assert( ! class_exists( 'Sidrena_Standalone' ), 'Standalone catalog must not load in WooCommerce edition.' );

foreach ( array( 'Sidrena_Products', 'Sidrena_History', 'Sidrena_Woo_Import_Export', 'Sidrena_Compatibility', 'Sidrena_Bulk', 'Sidrena_Location_Data', 'Sidrena_Location_History' ) as $class ) {
	sidrena_woo_assert( ! class_exists( $class ), $class . ' loaded before the WooCommerce dependency check completed.' );
}

sidrena_woo_assert( ! empty( $GLOBALS['sidrena_actions']['plugins_loaded'] ), 'WooCommerce dependency-safe bootstrap hook must register.' );
foreach ( $GLOBALS['sidrena_actions']['plugins_loaded'] as $callback ) {
	call_user_func( $callback );
}

foreach ( array( 'Sidrena_Products', 'Sidrena_History', 'Sidrena_Woo_Import_Export', 'Sidrena_Compatibility', 'Sidrena_Bulk', 'Sidrena_Location_Data', 'Sidrena_Location_History' ) as $class ) {
	sidrena_woo_assert( class_exists( $class ), $class . ' must load after the WooCommerce dependency check succeeds.' );
}

$downloads_shortcode = $GLOBALS['sidrena_shortcodes']['sidrena_cjenici'] ?? null;
sidrena_woo_assert(
	is_array( $downloads_shortcode )
	&& isset( $downloads_shortcode[0], $downloads_shortcode[1] )
	&& $downloads_shortcode[0] instanceof Sidrena_Public
	&& 'downloads_shortcode' === $downloads_shortcode[1],
	'SIDRENA public download shortcode must have exactly one canonical owner.'
);

sidrena_woo_assert( ! empty( $GLOBALS['sidrena_actions']['woocommerce_update_product'] ), 'WooCommerce edition must retain unlimited product price-history capture; that audit history remains separate from the immutable Sidrena-price ruleset.' );
sidrena_woo_assert( ! empty( $GLOBALS['sidrena_actions']['woocommerce_product_options_pricing'] ), 'Woo product fields must register.' );
sidrena_woo_assert( ! empty( $GLOBALS['sidrena_actions']['admin_post_sidrena_bulk_save'] ), 'Woo bulk save must register.' );
sidrena_woo_assert( ! empty( $GLOBALS['sidrena_actions']['sidrena_publication_watch'] ), 'Publication watchdog must register.' );
sidrena_woo_assert( empty( $GLOBALS['sidrena_actions']['admin_post_sidrena_standalone_import'] ), 'Standalone import must not register in Woo edition.' );
sidrena_woo_assert( ! empty( $GLOBALS['sidrena_filters']['woocommerce_product_export_column_names'] ), 'Woo CSV export filters must register.' );

$sanitize = new ReflectionMethod( 'Sidrena_Products', 'sanitize_by_type' );
$sanitize->setAccessible( true );
$products = Sidrena_Products::instance();
sidrena_woo_assert( '12.5' === $sanitize->invoke( $products, '12,50', 'decimal' ), 'Woo decimal metadata sanitizer must preserve valid nonnegative values.' );
sidrena_woo_assert( null === $sanitize->invoke( $products, '-1', 'decimal' ), 'Woo decimal metadata sanitizer must reject negative values without converting them to an empty value.' );
sidrena_woo_assert( null === $sanitize->invoke( $products, 'abc', 'decimal' ), 'Woo decimal metadata sanitizer must reject malformed values without converting them to an empty value.' );

$method = new ReflectionMethod( 'Sidrena_Activator', 'ensure_schedules' );
$method->setAccessible( true );
$method->invoke( null );
sidrena_woo_assert( isset( $GLOBALS['sidrena_scheduled']['sidrena_daily_generation'] ), 'Daily generation must be scheduled.' );
sidrena_woo_assert( isset( $GLOBALS['sidrena_scheduled']['sidrena_publication_watch'] ), 'Publication watchdog must be scheduled.' );
sidrena_woo_assert( ! isset( $GLOBALS['sidrena_scheduled']['sidrena_history_seed'] ), 'Retired 30-day Woo history seed must not be scheduled.' );

fwrite( STDOUT, "Sidrena WooCommerce edition smoke test passed.\n" );
