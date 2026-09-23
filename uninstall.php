<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
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

foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
	$role = get_role( $role_name );
	if ( $role ) {
		$role->remove_cap( 'manage_sidrena' );
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
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed plugin-owned table name.
}

// Public CSV/XML archives are intentionally preserved in uploads/sidrena/arhiva.
// They may be part of a merchant's required publication history. Sidrena service
// posts and product/service reference metadata are also preserved to avoid
// destructive loss of business records when the plugin is temporarily removed.
