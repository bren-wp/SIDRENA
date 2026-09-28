<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Both Sidrena editions intentionally share settings, services, generated
 * archives and (when applicable) WooCommerce history tables. Removing one
 * package must therefore be non-destructive by default.
 *
 * To permanently remove Sidrena data after BOTH editions have been removed,
 * add this to wp-config.php before clicking Delete:
 *
 * define( 'SIDRENA_DELETE_DATA_ON_UNINSTALL', true );
 */

$sidrena_active_plugins = (array) get_option( 'active_plugins', array() );
if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	$sidrena_network_active = (array) get_site_option( 'active_sitewide_plugins', array() );
	$sidrena_active_plugins = array_merge( $sidrena_active_plugins, array_keys( $sidrena_network_active ) );
}
foreach ( array_unique( $sidrena_active_plugins ) as $sidrena_active_plugin ) {
	$sidrena_active_plugin = (string) $sidrena_active_plugin;
	if ( (string) WP_UNINSTALL_PLUGIN === $sidrena_active_plugin ) {
		continue;
	}
	if ( preg_match( '#(^|/)(sidrena-wordpress|sidrena-woocommerce)\.php$#', $sidrena_active_plugin ) ) {
		return;
	}
}

// Runtime hooks and capabilities must not outlive the final installed edition.
// Business records remain preserved unless explicit destructive cleanup is enabled.
wp_clear_scheduled_hook( 'sidrena_daily_generation' );
wp_clear_scheduled_hook( 'sidrena_queued_generation' );
wp_clear_scheduled_hook( 'sidrena_publication_watch' );
wp_clear_scheduled_hook( 'sidrena_history_seed' );
wp_clear_scheduled_hook( 'sidrena_standalone_sync_batch' );

$sidrena_roles = function_exists( 'wp_roles' ) ? wp_roles() : null;
if ( $sidrena_roles && ! empty( $sidrena_roles->role_objects ) && is_array( $sidrena_roles->role_objects ) ) {
	foreach ( $sidrena_roles->role_objects as $sidrena_role ) {
		if ( is_object( $sidrena_role ) && is_callable( array( $sidrena_role, 'remove_cap' ) ) ) {
			$sidrena_role->remove_cap( 'manage_sidrena' );
		}
	}
}

if ( ! defined( 'SIDRENA_DELETE_DATA_ON_UNINSTALL' ) || true !== SIDRENA_DELETE_DATA_ON_UNINSTALL ) {
	return;
}

delete_option( 'sidrena_settings' );
delete_option( 'sidrena_locations' );
delete_option( 'sidrena_public_index' );
delete_option( 'sidrena_archive_index' );
delete_option( 'sidrena_last_run' );
delete_option( 'sidrena_db_version' );
delete_option( 'sidrena_plugin_version' );
delete_option( 'sidrena_history_seeded_at' );
delete_option( 'sidrena_public_page_id' );

global $wpdb;
$sidrena_tables = array(
	$wpdb->prefix . 'sidrena_price_history',
	$wpdb->prefix . 'sidrena_service_price_history',
	$wpdb->prefix . 'sidrena_location_products',
	$wpdb->prefix . 'sidrena_location_price_history',
	$wpdb->prefix . 'sidrena_audit_log',
);
foreach ( $sidrena_tables as $sidrena_table ) {
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $sidrena_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Explicit destructive uninstall of fixed plugin-owned tables.
}

// Public CSV/XML archives, Sidrena service posts and product/service reference
// metadata are intentionally preserved even during explicit database cleanup.
// They can be business records and should not disappear merely because code is
// uninstalled.
