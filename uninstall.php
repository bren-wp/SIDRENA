<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://sidrene-cijene.com.hr/
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

$active_plugins = (array) get_option( 'active_plugins', array() );
foreach ( $active_plugins as $active_plugin ) {
	$active_plugin = (string) $active_plugin;
	if ( $active_plugin === (string) WP_UNINSTALL_PLUGIN ) {
		continue;
	}
	if ( preg_match( '#(^|/)(sidrena-wordpress|sidrena-woocommerce)\.php$#', $active_plugin ) ) {
		return;
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
delete_option( 'sidrena_history_seeded_at' );
delete_option( 'sidrena_public_page_id' );

wp_clear_scheduled_hook( 'sidrena_daily_generation' );
wp_clear_scheduled_hook( 'sidrena_queued_generation' );
wp_clear_scheduled_hook( 'sidrena_history_seed' );

$roles = function_exists( 'wp_roles' ) ? wp_roles() : null;
if ( $roles && ! empty( $roles->role_objects ) && is_array( $roles->role_objects ) ) {
	foreach ( $roles->role_objects as $role ) {
		if ( is_object( $role ) && is_callable( array( $role, 'remove_cap' ) ) ) {
			$role->remove_cap( 'manage_sidrena' );
		}
	}
}

global $wpdb;
$tables = array(
	$wpdb->prefix . 'sidrena_price_history',
	$wpdb->prefix . 'sidrena_service_price_history',
	$wpdb->prefix . 'sidrena_location_products',
	$wpdb->prefix . 'sidrena_location_price_history',
	$wpdb->prefix . 'sidrena_audit_log',
);
foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed plugin-owned table names.
}

// Public CSV/XML archives, Sidrena service posts and product/service reference
// metadata are intentionally preserved even during explicit database cleanup.
// They can be business records and should not disappear merely because code is
// uninstalled.
