<?php
/**
 * Uninstall Sidrena cijena.
 *
 * Product meta is kept on purpose to avoid destructive loss of historical
 * reference-price data. Administrators can use the in-plugin purge action
 * before uninstall when they want those fields removed as well.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'sidrena_cijena_settings' );
delete_option( 'sidrena_cijena_snapshot_pending' );
delete_option( 'sidrena_cijena_snapshot_page' );
delete_option( 'sidrena_cijena_snapshot_offset' );
delete_option( 'sidrena_cijena_snapshot_child' );
delete_option( 'sidrena_cijena_fill_prompt' );
delete_option( 'sidrena_cijena_public_xml' );
delete_option( 'sidrena_cijena_publication_history' );
delete_option( 'brendigo_sidrena_publication_alert_state' );
delete_option( 'brendigo_sidrena_history_seed_page' );
delete_option( 'brendigo_sidrena_history_manual_seed_offset' );
wp_clear_scheduled_hook( 'brendigo_sidrena_history_seed_batch' );
wp_clear_scheduled_hook( 'brendigo_sidrena_manual_history_seed_batch' );
// Price history, administrator-only audit table and manual next-ID counter remain available after uninstall to avoid destroying historical evidence.
delete_option( 'sidrena_cijena_rewrite_ver' );
delete_option( 'sidrena_cijena_poslovnice' );
delete_option( 'sidrena_cijena_catalog' );
delete_option( 'sidrena_cijena_upit' );
delete_option( 'brendigo_sidrena_cijena_migration_version' );
delete_option( 'sidrena_cijena_automation_state' );
delete_option( 'sidrena_cijena_runtime_version' );
delete_option( 'sidrena_cijena_pending_publication' );
delete_option( 'sidrena_cijena_rebuild_lock' );
delete_transient( 'sidrena_cijena_rebuild_cooldown' );
delete_transient( 'sidrena_cijena_rebuild_retry_count' );
delete_transient( 'sidrena_cijena_automation_health_throttle' );
delete_transient( 'sidrena_cijena_rebuild_reasons' );
delete_transient( 'brendigo_sidrena_overview_metrics' );

$sidrena_cijena_uploads = wp_upload_dir( null, false );
if ( empty( $sidrena_cijena_uploads['error'] ) && ! empty( $sidrena_cijena_uploads['basedir'] ) ) {
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	global $wp_filesystem;
	if ( WP_Filesystem() && is_object( $wp_filesystem ) ) {
		$sidrena_cijena_base = trailingslashit( $sidrena_cijena_uploads['basedir'] ) . 'sidrena-cijena';
		if ( $wp_filesystem->is_dir( $sidrena_cijena_base ) ) {
			$wp_filesystem->delete( $sidrena_cijena_base, true );
		}
	}
}

wp_clear_scheduled_hook( 'sidrena_cijena_snapshot_batch' );
wp_clear_scheduled_hook( 'sidrena_cijena_daily_xml' );
wp_clear_scheduled_hook( 'sidrena_cijena_generate_xml' );
wp_clear_scheduled_hook( 'sidrena_cijena_automation_health' );
