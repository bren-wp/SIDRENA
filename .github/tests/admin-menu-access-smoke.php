<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
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
$GLOBALS['submenu']           = array();

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
function wp_unslash( $value ) {
	return $value;
}
function __( $text, $domain = null ) {
	unset( $domain );
	return $text;
}
function get_post_type( $post_id ) {
	return 123 === (int) $post_id ? 'sidrena_service' : 'post';
}
function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback, $icon_url = '', $position = null ) {
	unset( $page_title, $menu_title, $callback, $icon_url, $position );
	$GLOBALS['sidrena_test_menu']['top'] = array(
		'capability' => $capability,
		'slug'       => $menu_slug,
	);
}
function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {
	unset( $callback );
	$GLOBALS['sidrena_test_menu']['sub'][] = array(
		'parent'     => $parent_slug,
		'capability' => $capability,
		'slug'       => $menu_slug,
	);
	$GLOBALS['submenu'][ $parent_slug ][] = array( $menu_title, $capability, $menu_slug, $page_title );
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php';
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-admin.php';
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-admin-ux.php';

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
$GLOBALS['submenu']['sidrena'][] = array( 'Usluge', 'manage_options', 'edit.php?post_type=sidrena_service', 'Usluge' );
$GLOBALS['submenu']['sidrena'][] = array( 'Nova usluga', 'manage_options', 'post-new.php?post_type=sidrena_service', 'Nova usluga' );

sidrena_menu_assert( isset( $GLOBALS['sidrena_test_menu']['top'] ), 'Sidrena top-level menu was not registered.' );
sidrena_menu_assert( 'sidrena' === $GLOBALS['sidrena_test_menu']['top']['slug'], 'Sidrena top-level menu slug is incorrect.' );
sidrena_menu_assert( 'manage_options' === $GLOBALS['sidrena_test_menu']['top']['capability'], 'Sidrena menu must use administrator fallback capability when needed.' );
sidrena_menu_assert( ! empty( $GLOBALS['sidrena_test_menu']['sub'] ), 'Sidrena submenus were not registered.' );
sidrena_menu_assert( count( $GLOBALS['submenu']['sidrena'] ) >= 12, 'Baseline Sidrena submenu should expose the full internal page set before UX simplification.' );

Sidrena_Admin_UX::instance()->simplify_menu();
$visible_slugs = array_map(
	static function ( $item ) {
		return $item[2];
	},
	$GLOBALS['submenu']['sidrena']
);
$visible_labels = array_map(
	static function ( $item ) {
		return $item[0];
	},
	$GLOBALS['submenu']['sidrena']
);
$expected_slugs  = array( 'sidrena', 'sidrena-catalog', 'edit.php?post_type=sidrena_service', 'sidrena-files', 'sidrena-locations', 'sidrena-settings', 'sidrena-support' );
$expected_labels = array( 'Početak', 'Proizvodi', 'Usluge', 'Objava cjenika', 'Lokacije / webshop', 'Zakonske postavke', 'Pomoć' );
sidrena_menu_assert( $expected_slugs === $visible_slugs, 'Simplified Sidrena submenu must keep only the legal task-based pages in order.' );
sidrena_menu_assert( $expected_labels === $visible_labels, 'Simplified Sidrena submenu labels must be clear and legal-workflow focused.' );

foreach ( Sidrena_Admin_UX::hidden_menu_slugs() as $hidden_slug ) {
	sidrena_menu_assert( ! in_array( $hidden_slug, $visible_slugs, true ), 'Hidden technical/support page leaked into simplified menu: ' . $hidden_slug );
}

$_GET['post_type'] = 'sidrena_service';
sidrena_menu_assert( 'sidrena' === Sidrena_Admin_UX::instance()->parent_file( 'edit.php' ), 'Service screens must stay visually grouped under Sidrena.' );
sidrena_menu_assert( 'edit.php?post_type=sidrena_service' === Sidrena_Admin_UX::instance()->submenu_file( 'edit.php?post_type=sidrena_service' ), 'Service screens must highlight Usluge instead of a hidden technical item.' );

$_GET = array( 'post' => 123 );
sidrena_menu_assert( 'sidrena' === Sidrena_Admin_UX::instance()->parent_file( 'edit.php' ), 'Editing a service must keep the Sidrena menu parent active.' );

fwrite( STDOUT, "Sidrena administrator menu access and strict legal sidebar smoke test passed.\n" );
