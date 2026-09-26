<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'SIDRENA_VERSION', '1.0.9' );
define( 'SIDRENA_DIR', dirname( __DIR__, 2 ) . '/' );
define( 'SIDRENA_FILE', SIDRENA_DIR . 'sidrena-wordpress.php' );

$GLOBALS['sidrena_styles']  = array();
$GLOBALS['sidrena_scripts'] = array();
$GLOBALS['sidrena_screen']  = null;

final class Sidrena_Utils {
	public static function is_woocommerce_edition() { return false; }
	public static function current_user_can_manage() { return true; }
	public static function is_woocommerce_active() { return false; }
}
function get_current_screen() { return $GLOBALS['sidrena_screen']; }
function plugins_url( $path, $file ) { unset( $file ); return 'https://example.test/wp-content/plugins/sidrena-wordpress/' . ltrim( $path, '/' ); }
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {
	$GLOBALS['sidrena_styles'][ $handle ] = array( $src, $deps, $ver );
}
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $footer = false ) {
	$GLOBALS['sidrena_scripts'][ $handle ] = array( $src, $deps, $ver, $footer );
}
function wp_localize_script() { return true; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function wp_unslash( $value ) { return $value; }
function __( $value ) { return $value; }

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-admin.php';

function sidrena_asset_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$admin_factory = new ReflectionClass( 'Sidrena_Admin' );
$admin         = $admin_factory->newInstanceWithoutConstructor();

// The real regression: even when another plugin/router changes the hook suffix,
// the Sidrena page query must still load the complete UI assets.
$_GET['page'] = 'sidrena-locations';
$admin->assets( 'unexpected_hook_suffix' );
sidrena_asset_assert( isset( $GLOBALS['sidrena_styles']['sidrena-brand'] ), 'Brand CSS was not enqueued from the Sidrena page query fallback.' );
sidrena_asset_assert( isset( $GLOBALS['sidrena_scripts']['sidrena-admin'] ), 'Admin JS was not enqueued from the Sidrena page query fallback.' );
sidrena_asset_assert( false !== strpos( $GLOBALS['sidrena_styles']['sidrena-brand'][0], '/admin/css/brand.css' ), 'Brand CSS URL is incorrect.' );
sidrena_asset_assert( false !== strpos( $GLOBALS['sidrena_scripts']['sidrena-admin'][0], '/admin/js/admin.js' ), 'Admin JS URL is incorrect.' );
sidrena_asset_assert( 'dashicons' === array_key_first( $GLOBALS['sidrena_styles'] ), 'Dashicons must be explicitly available before Sidrena brand CSS.' );

// Screen-id fallback must work even without ?page.
$_GET = array();
$GLOBALS['sidrena_styles'] = array();
$GLOBALS['sidrena_scripts'] = array();
$GLOBALS['sidrena_screen'] = (object) array( 'id' => 'sidrena_page_sidrena-files', 'post_type' => '' );
$admin = $admin_factory->newInstanceWithoutConstructor();
$admin->ensure_assets();
sidrena_asset_assert( isset( $GLOBALS['sidrena_styles']['sidrena-brand'] ), 'Brand CSS was not enqueued from the screen-id fallback.' );

// Service editor keeps the smaller editor-only stylesheet.
$GLOBALS['sidrena_styles'] = array();
$GLOBALS['sidrena_scripts'] = array();
$GLOBALS['sidrena_screen'] = (object) array( 'id' => 'sidrena_service', 'post_type' => 'sidrena_service' );
$admin = $admin_factory->newInstanceWithoutConstructor();
$admin->assets( 'post.php' );
sidrena_asset_assert( isset( $GLOBALS['sidrena_styles']['sidrena-admin-editor'] ), 'Service editor stylesheet was not enqueued.' );
sidrena_asset_assert( ! isset( $GLOBALS['sidrena_styles']['sidrena-brand'] ), 'Full Sidrena brand UI must not leak into the service editor.' );

fwrite( STDOUT, "Sidrena admin asset routing smoke test passed.\n" );
