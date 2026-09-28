<?php
/**
 * SIDRENA edition conflict regression test.
 *
 * @package Sidrena
 * @author brendigo
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
$GLOBALS['sidrena_can_activate_plugins'] = true;

function register_activation_hook( $file, $callback ) {
	unset( $file );
	$GLOBALS['sidrena_activation_callback'] = $callback;
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $priority, $accepted_args );
	$GLOBALS['sidrena_actions'][ $hook ][] = $callback;
}
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function current_user_can( $capability ) { return 'activate_plugins' !== $capability || ! empty( $GLOBALS['sidrena_can_activate_plugins'] ); }
function esc_html__( $text, $domain = null ) { unset( $domain ); return $text; }
function wp_die( $message, $title = '', $args = array() ) { unset( $title, $args ); throw new RuntimeException( (string) $message ); }

$sidrena_entry_file        = '/tmp/' . $target . '.php';
$sidrena_requested_edition = $target;
$conflict = require dirname( __DIR__, 2 ) . '/includes/sidrena-edition-guard.php';

function sidrena_conflict_assert( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); }
}

sidrena_conflict_assert( true === $conflict, 'Conflict guard did not detect an already loaded SIDRENA edition/runtime.' );
sidrena_conflict_assert( is_callable( $GLOBALS['sidrena_activation_callback'] ), 'Conflicting edition did not register an activation blocker.' );
sidrena_conflict_assert( empty( $GLOBALS['sidrena_actions']['admin_init'] ), 'Conflict guard must not register self-deactivation or activation-state mutations.' );
sidrena_conflict_assert( ! empty( $GLOBALS['sidrena_actions']['admin_notices'] ), 'Conflicting edition did not register a scoped admin notice.' );

$source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/sidrena-edition-guard.php' );
sidrena_conflict_assert( false === strpos( $source, 'deactivate_plugins' ), 'Conflict guard must never call deactivate_plugins().' );
sidrena_conflict_assert( false === strpos( $source, "wp-admin/includes/plugin.php" ), 'Conflict guard must not load plugin.php merely to change another plugin state.' );

$blocked = false;
try {
	call_user_func( $GLOBALS['sidrena_activation_callback'] );
} catch ( RuntimeException $e ) {
	$blocked = false !== strpos( $e->getMessage(), 'SIDRENA izdanje' ) && false !== strpos( $e->getMessage(), 'aktivno' );
}
sidrena_conflict_assert( $blocked, 'Activation blocker did not stop the conflicting edition.' );

fwrite( STDOUT, "SIDRENA {$target} {$mode} conflict guard smoke test passed.\n" );
