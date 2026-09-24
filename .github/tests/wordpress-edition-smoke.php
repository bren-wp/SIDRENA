<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://sidrene-cijene.com.hr/
 * @see https://brendigo.com/
 */

$root = isset( $argv[1] ) && is_dir( $argv[1] ) ? rtrim( (string) $argv[1], '/\\' ) : dirname( __DIR__, 2 );
$main = is_file( $root . '/sidrena-wordpress.php' ) ? $root . '/sidrena-wordpress.php' : $root . '/editions/wordpress/sidrena-wordpress.php';

define( 'ABSPATH', __DIR__ . '/' );
define( 'WP_CLI', false );
define( 'SIDRENA_VERSION', '0.2.0' );
define( 'SIDRENA_EDITION', 'wordpress' );
define( 'SIDRENA_FILE', $main );
define( 'SIDRENA_DIR', $root . '/' );
define( 'SIDRENA_URL', 'https://example.test/wp-content/plugins/sidrena-wordpress/' );

class WooCommerce {}
function wc_get_product( $id = 0 ) { unset( $id ); return false; }

$GLOBALS['sidrena_actions'] = array();
$GLOBALS['sidrena_filters'] = array();
$GLOBALS['sidrena_shortcodes'] = array();
$GLOBALS['sidrena_scheduled'] = array( 'sidrena_history_seed' => 12345 );

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

function sidrena_wp_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

sidrena_wp_assert( 'wordpress' === Sidrena_Utils::edition(), 'WordPress edition marker is incorrect.' );
sidrena_wp_assert( ! Sidrena_Utils::is_woocommerce_active(), 'WordPress edition must ignore WooCommerce even when Woo is present.' );
sidrena_wp_assert( class_exists( 'Sidrena_Standalone' ), 'WordPress catalog class must load.' );
sidrena_wp_assert( ! class_exists( 'Sidrena_Products' ), 'Woo product class must not load in WordPress edition.' );
sidrena_wp_assert( ! class_exists( 'Sidrena_History' ), 'Woo history class must not load in WordPress edition.' );
sidrena_wp_assert( ! class_exists( 'Sidrena_Woo_Import_Export' ), 'Woo CSV class must not load in WordPress edition.' );
sidrena_wp_assert( ! class_exists( 'Sidrena_Compatibility' ), 'Woo compatibility class must not load in WordPress edition.' );
sidrena_wp_assert( ! class_exists( 'Sidrena_Bulk' ), 'Woo bulk class must not load in WordPress edition.' );
sidrena_wp_assert( ! class_exists( 'Sidrena_Location_Data' ), 'Woo location data class must not load in WordPress edition.' );
sidrena_wp_assert( ! class_exists( 'Sidrena_Location_History' ), 'Woo location history class must not load in WordPress edition.' );
sidrena_wp_assert( function_exists( 'sidrena_cijena' ), 'Template helper must exist.' );

Sidrena_Plugin::instance()->run();

sidrena_wp_assert( ! empty( $GLOBALS['sidrena_actions']['admin_menu'] ), 'WordPress edition admin menu must register.' );
sidrena_wp_assert( ! empty( $GLOBALS['sidrena_actions']['admin_post_sidrena_standalone_import'] ), 'WordPress catalog import must register.' );
sidrena_wp_assert( ! empty( $GLOBALS['sidrena_actions']['admin_post_sidrena_standalone_save'] ), 'WordPress catalog save must register.' );
sidrena_wp_assert( ! empty( $GLOBALS['sidrena_actions']['sidrena_standalone_sync_batch'] ), 'WordPress source sync worker must register.' );
sidrena_wp_assert( ! empty( $GLOBALS['sidrena_filters']['the_content'] ), 'WordPress linked-content auto display filter must register.' );
sidrena_wp_assert( ! empty( $GLOBALS['sidrena_actions']['sidrena_publication_watch'] ), 'Publication watchdog must register.' );

foreach ( array_keys( $GLOBALS['sidrena_actions'] ) as $hook ) {
	sidrena_wp_assert( 0 !== strpos( $hook, 'woocommerce_' ) && 0 !== strpos( $hook, 'wc_product_' ), 'Woo action leaked into WordPress edition: ' . $hook );
}
foreach ( array_keys( $GLOBALS['sidrena_filters'] ) as $hook ) {
	sidrena_wp_assert( 0 !== strpos( $hook, 'woocommerce_' ), 'Woo filter leaked into WordPress edition: ' . $hook );
}

$method = new ReflectionMethod( 'Sidrena_Activator', 'ensure_schedules' );
$method->setAccessible( true );
$method->invoke( null );
sidrena_wp_assert( isset( $GLOBALS['sidrena_scheduled']['sidrena_daily_generation'] ), 'Daily generation must remain scheduled.' );
sidrena_wp_assert( isset( $GLOBALS['sidrena_scheduled']['sidrena_publication_watch'] ), 'Publication watchdog must be scheduled.' );
sidrena_wp_assert( ! isset( $GLOBALS['sidrena_scheduled']['sidrena_history_seed'] ), 'Woo history seed must not exist in WordPress edition.' );

fwrite( STDOUT, "Sidrena WordPress edition smoke test passed.\n" );
