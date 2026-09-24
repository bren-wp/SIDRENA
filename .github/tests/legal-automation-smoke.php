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

function sidrena_legal_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$root              = dirname( __DIR__, 2 );
$compliance_source = file_get_contents( $root . '/includes/class-sidrena-compliance.php' );
$audit_source      = file_get_contents( $root . '/includes/class-sidrena-audit.php' );
$bootstrap_source  = file_get_contents( $root . '/includes/sidrena-bootstrap.php' );
$plugin_source     = file_get_contents( $root . '/includes/class-sidrena-plugin.php' );
$utils_source      = file_get_contents( $root . '/includes/class-sidrena-utils.php' );
$changelog_source  = file_get_contents( $root . '/changelog.txt' );

sidrena_legal_assert( false !== strpos( $bootstrap_source, "includes/class-sidrena-compliance.php" ), 'Compliance class is not loaded by bootstrap.' );
sidrena_legal_assert( false !== strpos( $plugin_source, 'Sidrena_Compliance::instance()->hooks();' ), 'Compliance hooks are not registered.' );
sidrena_legal_assert( false !== strpos( $compliance_source, "add_action( 'sidrena_publication_watch'" ), 'Publication watchdog integration is missing.' );
sidrena_legal_assert( false !== strpos( $compliance_source, "add_action( 'sidrena_daily_generation'" ), 'Daily generation watchdog integration is missing.' );
sidrena_legal_assert( false !== strpos( $compliance_source, 'Sidrena_Public::ensure_public_page();' ), 'Automatic public page repair is missing.' );
sidrena_legal_assert( false !== strpos( $compliance_source, "array( 'archive_dir', 'snapshot_dir' )" ), 'Archive/snapshot directory self-heal list is missing.' );
sidrena_legal_assert( false !== strpos( $compliance_source, 'wp_mkdir_p( $paths[ $path_key ] );' ), 'Looped directory self-heal is missing.' );
sidrena_legal_assert( false !== strpos( $compliance_source, 'legal_automation_watchdog' ), 'Audit log event for legal automation watchdog is missing.' );

foreach ( array( 'nn_101_2026_anchor_price', 'nn_101_2026_public_pricelist', 'nn_105_2026_retail_unit_price', 'mingo_2026_09_22_clarifications' ) as $source_key ) {
	sidrena_legal_assert( false !== strpos( $compliance_source, $source_key ), 'Legal source marker missing: ' . $source_key );
}
foreach ( array( '2026-09-10', '2025-05-02', 'generate_csv', 'generate_xml', 'enable_public_html', 'strict_publication', 'publication_watch', 'daily_generation' ) as $profile_key ) {
	sidrena_legal_assert( false !== strpos( $compliance_source, $profile_key ), 'Automation profile marker missing: ' . $profile_key );
}

foreach ( array( 'repair_settings', 'repair_schedules', 'repair_public_surface', 'log_watchdog_result', 'LAST_STATUS_OPTION' ) as $marker ) {
	sidrena_legal_assert( false !== strpos( $compliance_source, $marker ), 'Production compliance hardening marker missing: ' . $marker );
}
foreach ( array( 'MAX_MESSAGE_BYTES', 'MAX_CONTEXT_BYTES', 'MAX_ROWS', 'table_exists', 'trim_bytes', 'sidrena_publication_watch' ) as $marker ) {
	sidrena_legal_assert( false !== strpos( $audit_source, $marker ), 'Audit hardening marker missing: ' . $marker );
}

sidrena_legal_assert( false !== strpos( $utils_source, "'default_ref_date'     => '2026-09-10'" ), 'Default reference date is not aligned with NN 101/2026.' );
sidrena_legal_assert( false !== strpos( $utils_source, "'fmcg_ref_date'        => '2025-05-02'" ), 'FMCG reference date is not preserved.' );
sidrena_legal_assert( false !== strpos( $utils_source, "'generation_time'      => '06:30'" ), 'Default generation time is not automated early enough.' );
sidrena_legal_assert( false !== strpos( $utils_source, "'strict_publication'   => 'yes'" ), 'Strict publication is not enabled by default.' );
sidrena_legal_assert( false !== strpos( $utils_source, "'failure_notifications' => 'yes'" ), 'Failure notifications are not enabled by default.' );
sidrena_legal_assert( false !== strpos( $changelog_source, '0.5.0' ) && false !== strpos( $changelog_source, 'compliance/automation watchdog' ), '0.5.0 changelog does not mention legal automation watchdog.' );
sidrena_legal_assert( false !== strpos( $changelog_source, 'production hardening' ), '0.5.0 changelog does not mention production hardening.' );

fwrite( STDOUT, "Sidrena legal automation smoke test passed.\n" );
