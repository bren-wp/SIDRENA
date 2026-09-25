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
	unset( $page_title, $menu_title, $callback, $position );
	$GLOBALS['sidrena_test_menu']['top'] = array(
		'capability' => $capability,
		'slug'       => $menu_slug,
		'icon'       => $icon_url,
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
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-admin-menu.php';

function sidrena_menu_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

function sidrena_visible_submenu_slugs() {
	return array_map(
		static function ( $item ) {
			return $item[2];
		},
		$GLOBALS['submenu']['sidrena']
	);
}

function sidrena_visible_submenu_labels() {
	return array_map(
		static function ( $item ) {
			return $item[0];
		},
		$GLOBALS['submenu']['sidrena']
	);
}

sidrena_menu_assert( 'manage_sidrena' === Sidrena_Utils::admin_capability(), 'Default Sidrena capability changed unexpectedly.' );
sidrena_menu_assert( 'manage_options' === Sidrena_Utils::admin_menu_capability(), 'Administrator must get manage_options as menu fallback.' );
sidrena_menu_assert( Sidrena_Utils::current_user_can_manage(), 'Administrator with manage_options must be allowed to manage Sidrena.' );

$mapped = Sidrena_Utils::map_admin_capability( array( 'manage_sidrena' ), 'manage_sidrena', 1, array() );
sidrena_menu_assert( array( 'exist' ) === $mapped, 'manage_sidrena must map for an administrator even when the custom capability is missing.' );

Sidrena_Admin_Menu::instance()->register_menu();

sidrena_menu_assert( isset( $GLOBALS['sidrena_test_menu']['top'] ), 'Sidrena top-level menu was not registered.' );
sidrena_menu_assert( 'sidrena' === $GLOBALS['sidrena_test_menu']['top']['slug'], 'Sidrena top-level menu slug is incorrect.' );
sidrena_menu_assert( 'manage_options' === $GLOBALS['sidrena_test_menu']['top']['capability'], 'Sidrena menu must use administrator fallback capability when needed.' );
sidrena_menu_assert( SIDRENA_URL . 'assets/images/menu-anchor.svg' === $GLOBALS['sidrena_test_menu']['top']['icon'], 'Sidrena top-level menu must use the local anchor icon.' );
sidrena_menu_assert( ! empty( $GLOBALS['sidrena_test_menu']['sub'] ), 'Sidrena submenus were not registered.' );

$expected_slugs  = array( 'sidrena', 'sidrena-catalog', Sidrena_Admin_UX::SERVICE_MENU_SLUG, 'sidrena-files', 'sidrena-locations', 'sidrena-settings', 'sidrena-support' );
$expected_labels = array( 'Početak', 'Proizvodi', 'Usluge', 'Objava cjenika', 'Lokacije / webshop', 'Zakonske postavke', 'Pomoć' );

sidrena_menu_assert( $expected_slugs === sidrena_visible_submenu_slugs(), 'Clean Sidrena submenu must register only the legal task-based pages in order.' );
sidrena_menu_assert( $expected_labels === sidrena_visible_submenu_labels(), 'Clean Sidrena submenu labels must be clear and legal-workflow focused.' );

$retired_slugs = array(
	'sidrena-compliance',
	'sidrena-archive',
	'sidrena-tools',
	'sidrena-log',
	'sidrena-rules',
	'sidrena-about',
	'sidrena-help',
	'post-new.php?post_type=sidrena_service',
);
foreach ( $retired_slugs as $retired_slug ) {
	sidrena_menu_assert( ! in_array( $retired_slug, sidrena_visible_submenu_slugs(), true ), 'Retired technical/support page was registered in clean menu: ' . $retired_slug );
}

$GLOBALS['submenu']['sidrena'][] = array( 'Usluge', 'manage_options', Sidrena_Admin_UX::SERVICE_MENU_SLUG, 'Usluge' );
$GLOBALS['submenu']['sidrena'][] = array( 'Objava cjenika', 'manage_options', 'sidrena-files', 'Objava cjenika' );
Sidrena_Admin_UX::instance()->simplify_menu();

$visible_slugs = sidrena_visible_submenu_slugs();
sidrena_menu_assert( $expected_slugs === $visible_slugs, 'Sidrena UX normalization must remove duplicate submenu slugs without hiding registered pages.' );
sidrena_menu_assert( count( $visible_slugs ) === count( array_unique( $visible_slugs ) ), 'Simplified Sidrena submenu must not contain duplicate slugs.' );

$_GET['post_type'] = 'sidrena_service';
sidrena_menu_assert( 'sidrena' === Sidrena_Admin_UX::instance()->parent_file( 'edit.php' ), 'Service screens must stay visually grouped under Sidrena.' );
sidrena_menu_assert( Sidrena_Admin_UX::SERVICE_MENU_SLUG === Sidrena_Admin_UX::instance()->submenu_file( Sidrena_Admin_UX::SERVICE_MENU_SLUG ), 'Service screens must highlight Usluge.' );

$_GET = array( 'post' => 123 );
sidrena_menu_assert( 'sidrena' === Sidrena_Admin_UX::instance()->parent_file( 'edit.php' ), 'Editing a service must keep the Sidrena menu parent active.' );

fwrite( STDOUT, "Sidrena clean admin menu registration and sidebar normalization smoke test passed.\n" );
