<?php
define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['sidrena_called'] = array();
$GLOBALS['sidrena_actions'] = array();
$GLOBALS['sidrena_scheduled'] = array(
	'sidrena_history_seed' => 12345,
);

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $callback, $priority, $accepted_args );
	$GLOBALS['sidrena_actions'][] = $hook;
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

abstract class Sidrena_No_Woo_Module {
	public static function instance() {
		return new static();
	}
	public function hooks() {
		$GLOBALS['sidrena_called'][ static::class ] = true;
	}
}
class Sidrena_Audit extends Sidrena_No_Woo_Module {}
class Sidrena_Service_History extends Sidrena_No_Woo_Module {}
class Sidrena_Location_History extends Sidrena_No_Woo_Module {}
class Sidrena_Standalone extends Sidrena_No_Woo_Module {}
class Sidrena_Services extends Sidrena_No_Woo_Module {}
class Sidrena_Pricelist extends Sidrena_No_Woo_Module {}
class Sidrena_REST extends Sidrena_No_Woo_Module {}
class Sidrena_Public extends Sidrena_No_Woo_Module {}
class Sidrena_History extends Sidrena_No_Woo_Module {}
class Sidrena_Products extends Sidrena_No_Woo_Module {}
class Sidrena_Woo_Import_Export extends Sidrena_No_Woo_Module {}
class Sidrena_Compatibility extends Sidrena_No_Woo_Module {}
class Sidrena_Bulk extends Sidrena_No_Woo_Module {}
class Sidrena_Site_Health extends Sidrena_No_Woo_Module {}
class Sidrena_Admin extends Sidrena_No_Woo_Module {}
class Sidrena_CLI {
	public static function register() {
		$GLOBALS['sidrena_called'][ __CLASS__ ] = true;
	}
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php';
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-plugin.php';
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-activator.php';

function sidrena_no_woo_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

sidrena_no_woo_assert( 'standalone' === Sidrena_Utils::runtime_mode(), 'Runtime mode must be standalone without WooCommerce.' );

Sidrena_Plugin::instance()->run();

$core = array(
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
	'Sidrena_CLI',
);
foreach ( $core as $class ) {
	sidrena_no_woo_assert( ! empty( $GLOBALS['sidrena_called'][ $class ] ), $class . ' must initialize without WooCommerce.' );
}

$woo_only = array(
	'Sidrena_History',
	'Sidrena_Products',
	'Sidrena_Woo_Import_Export',
	'Sidrena_Compatibility',
);
foreach ( $woo_only as $class ) {
	sidrena_no_woo_assert( empty( $GLOBALS['sidrena_called'][ $class ] ), $class . ' must not register Woo-only hooks without WooCommerce.' );
}

$method = new ReflectionMethod( 'Sidrena_Activator', 'ensure_schedules' );
$method->setAccessible( true );
$method->invoke( null );
sidrena_no_woo_assert( isset( $GLOBALS['sidrena_scheduled']['sidrena_daily_generation'] ), 'Daily Sidrena generation must remain scheduled without WooCommerce.' );
sidrena_no_woo_assert( ! isset( $GLOBALS['sidrena_scheduled']['sidrena_history_seed'] ), 'WooCommerce history seed must be cleared in standalone mode.' );

$main = file_get_contents( dirname( __DIR__, 2 ) . '/sidrena.php' );
sidrena_no_woo_assert( 0 === preg_match( '/^ \* Requires Plugins:.*woocommerce/im', $main ), 'Sidrena must not declare WooCommerce as a hard dependency.' );

fwrite( STDOUT, "Sidrena no-WooCommerce runtime smoke test passed.\n" );
