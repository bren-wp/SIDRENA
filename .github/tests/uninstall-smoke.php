<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo LTD Developer
 * @link https://sidrene-cijene.com.hr/
 * @see https://brendigo.com/
 */

$scenario = isset( $argv[1] ) ? (string) $argv[1] : 'preserve';
if ( ! in_array( $scenario, array( 'preserve', 'other-active', 'destroy' ), true ) ) {
	fwrite( STDERR, "Unknown scenario.\n" );
	exit( 2 );
}

define( 'WP_UNINSTALL_PLUGIN', 'sidrena-wordpress/sidrena-wordpress.php' );
if ( in_array( $scenario, array( 'other-active', 'destroy' ), true ) ) {
	define( 'SIDRENA_DELETE_DATA_ON_UNINSTALL', true );
}

$GLOBALS['sidrena_deleted_options'] = array();
$GLOBALS['sidrena_cleared_hooks'] = array();
$GLOBALS['sidrena_queries'] = array();

function get_option( $key, $default = false ) {
	global $scenario;
	if ( 'active_plugins' === $key ) {
		return 'other-active' === $scenario ? array( 'sidrena-woocommerce/sidrena-woocommerce.php' ) : array();
	}
	return $default;
}
function delete_option( $key ) {
	$GLOBALS['sidrena_deleted_options'][] = $key;
	return true;
}
function wp_clear_scheduled_hook( $hook ) {
	$GLOBALS['sidrena_cleared_hooks'][] = $hook;
	return 1;
}
function wp_roles() {
	$role = new class {
		public function remove_cap( $cap ) {
			unset( $cap );
		}
	};
	$roles = new stdClass();
	$roles->role_objects = array( 'administrator' => $role );
	return $roles;
}

$wpdb = new class {
	public $prefix = 'wp_';
	public function query( $sql ) {
		$GLOBALS['sidrena_queries'][] = $sql;
		return 1;
	}
};

require dirname( __DIR__, 2 ) . '/uninstall.php';

function sidrena_uninstall_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

if ( 'destroy' === $scenario ) {
	sidrena_uninstall_assert( ! empty( $GLOBALS['sidrena_deleted_options'] ), 'Explicit destructive uninstall did not remove options.' );
	sidrena_uninstall_assert( ! empty( $GLOBALS['sidrena_cleared_hooks'] ), 'Explicit destructive uninstall did not clear schedules.' );
	sidrena_uninstall_assert( 5 === count( $GLOBALS['sidrena_queries'] ), 'Explicit destructive uninstall did not drop all plugin tables.' );
} else {
	sidrena_uninstall_assert( empty( $GLOBALS['sidrena_deleted_options'] ), 'Safe uninstall unexpectedly removed options.' );
	sidrena_uninstall_assert( empty( $GLOBALS['sidrena_cleared_hooks'] ), 'Safe uninstall unexpectedly cleared shared schedules.' );
	sidrena_uninstall_assert( empty( $GLOBALS['sidrena_queries'] ), 'Safe uninstall unexpectedly dropped shared tables.' );
}

fwrite( STDOUT, "Sidrena uninstall {$scenario} smoke test passed.\n" );
