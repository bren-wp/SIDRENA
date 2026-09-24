<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo LTD Developer
 * @link https://sidrene-cijene.com.hr/
 * @see https://brendigo.com/
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'SIDRENA_EDITION', 'wordpress' );
define( 'SIDRENA_URL', 'https://example.test/wp-content/plugins/sidrena/' );

$GLOBALS['sidrena_test_caps'] = array(
	'manage_options'     => true,
	'manage_woocommerce' => false,
	'manage_sidrena'     => false,
);
$GLOBALS['sidrena_test_menu'] = array();

function apply_filters( $hook, $value ) {
	unset( $hook );
	return $value;
}
function sanitize_key( $key ) {
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}
function current_user_can( $capability ) {
	return ! empty( $GLOBALS['sidrena_test_caps'][ $capability ] );
}
function get_userdata( $user_id ) {
	unset( $user_id );
	$user = new stdClass();
	$user->allcaps = $GLOBALS['sidrena_test_caps'];
	return $user;
}
function absint( $value ) {
	return abs( (int) $value );
}
function __( $text, $domain = null ) {
	unset( $domain );
	return $text;
}
function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback, $icon_url = '', $position = null ) {
	unset( $page_title, $menu_title, $callback, $icon_url, $position );
	$GLOBALS['sidrena_test_menu']['top'] = array(
		'capability' => $capability,
		'slug'       => $menu_slug,
	);
}
function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {
	unset( $page_title, $menu_title, $callback );
	$GLOBALS['sidrena_test_menu']['sub'][] = array(
		'parent'     => $parent_slug,
		'capability' => $capability,
		'slug'       => $menu_slug,
	);
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php';
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-admin.php';

function sidrena_menu_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

sidrena_menu_assert( 'manage_sidrena' === Sidrena_Utils::admin_capability(), 'Default Sidrena capability changed unexpectedly.' );
sidrena_menu_assert( 'manage_options' === Sidrena_Utils::admin_menu_capability(), 'Administrator must get manage_options as menu fallback.' );
sidrena_menu_assert( Sidrena_Utils::current_user_can_manage(), 'Administrator with manage_options must be allowed to manage Sidrena.' );

$mapped = Sidrena_Utils::map_admin_capability( array( 'manage_sidrena' ), 'manage_sidrena', 1, array() );
sidrena_menu_assert( array( 'exist' ) === $mapped, 'manage_sidrena must map for an administrator even when the custom capability is missing.' );

Sidrena_Admin::instance()->menu();
sidrena_menu_assert( isset( $GLOBALS['sidrena_test_menu']['top'] ), 'Sidrena top-level menu was not registered.' );
sidrena_menu_assert( 'sidrena' === $GLOBALS['sidrena_test_menu']['top']['slug'], 'Sidrena top-level menu slug is incorrect.' );
sidrena_menu_assert( 'manage_options' === $GLOBALS['sidrena_test_menu']['top']['capability'], 'Sidrena menu must use administrator fallback capability when needed.' );
sidrena_menu_assert( ! empty( $GLOBALS['sidrena_test_menu']['sub'] ), 'Sidrena submenus were not registered.' );

fwrite( STDOUT, "Sidrena administrator menu access smoke test passed.\n" );
