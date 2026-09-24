<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo LTD Developer
 * @link https://sidrene-cijene.com.hr/
 * @see https://brendigo.com/
 */

$target = isset( $argv[1] ) ? (string) $argv[1] : '';
$mode   = isset( $argv[2] ) ? (string) $argv[2] : 'edition';
if ( ! in_array( $target, array( 'wordpress', 'woocommerce' ), true ) || ! in_array( $mode, array( 'edition', 'legacy' ), true ) ) {
	fwrite( STDERR, "Usage: php edition-conflict-smoke.php wordpress|woocommerce [edition|legacy]\n" );
	exit( 2 );
}

define( 'ABSPATH', __DIR__ . '/' );
if ( 'legacy' === $mode ) {
	class Sidrena_Plugin {}
} else {
	define( 'SIDRENA_EDITION', 'wordpress' === $target ? 'woocommerce' : 'wordpress' );
}

$GLOBALS['sidrena_activation_callback'] = null;
$GLOBALS['sidrena_actions'] = array();
$GLOBALS['sidrena_deactivated'] = array();

function register_activation_hook( $file, $callback ) {
	unset( $file );
	$GLOBALS['sidrena_activation_callback'] = $callback;
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $priority, $accepted_args );
	$GLOBALS['sidrena_actions'][ $hook ][] = $callback;
}
function current_user_can( $capability ) {
	unset( $capability );
	return true;
}
function esc_html__( $text, $domain = null ) {
	unset( $domain );
	return $text;
}
function plugin_basename( $file ) {
	return basename( $file );
}
function deactivate_plugins( $plugins, $silent = false ) {
	unset( $silent );
	$GLOBALS['sidrena_deactivated'][] = $plugins;
}
function wp_die( $message, $title = '', $args = array() ) {
	unset( $title, $args );
	throw new RuntimeException( (string) $message );
}

$file = dirname( __DIR__, 2 ) . '/editions/' . $target . '/sidrena-' . $target . '.php';
require $file;

function sidrena_conflict_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

sidrena_conflict_assert( is_callable( $GLOBALS['sidrena_activation_callback'] ), 'Conflicting edition did not register an activation blocker.' );
sidrena_conflict_assert( ! empty( $GLOBALS['sidrena_actions']['admin_init'] ), 'Conflicting edition did not schedule self-deactivation.' );
sidrena_conflict_assert( ! empty( $GLOBALS['sidrena_actions']['admin_notices'] ), 'Conflicting edition did not register an admin notice.' );

foreach ( $GLOBALS['sidrena_actions']['admin_init'] as $callback ) {
	call_user_func( $callback );
}
$expected = 'sidrena-' . $target . '.php';
sidrena_conflict_assert( in_array( $expected, $GLOBALS['sidrena_deactivated'], true ), 'Conflicting edition did not deactivate itself.' );

$blocked = false;
try {
	call_user_func( $GLOBALS['sidrena_activation_callback'] );
} catch ( RuntimeException $e ) {
	$blocked = false !== strpos( $e->getMessage(), 'Sidrena izdanje' ) && false !== strpos( $e->getMessage(), 'aktivno' );
}
sidrena_conflict_assert( $blocked, 'Activation blocker did not stop the conflicting edition.' );

fwrite( STDOUT, "Sidrena {$target} {$mode} conflict guard smoke test passed.\n" );
