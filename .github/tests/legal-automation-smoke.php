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
$services_source   = file_get_contents( $root . '/includes/class-sidrena-services.php' );
$history_source    = file_get_contents( $root . '/includes/class-sidrena-history.php' );
$service_history_source = file_get_contents( $root . '/includes/class-sidrena-service-history.php' );
$utils_source      = file_get_contents( $root . '/includes/class-sidrena-utils.php' );
$changelog_source  = file_get_contents( $root . '/changelog.txt' );

sidrena_legal_assert( false !== strpos( $bootstrap_source, "includes/class-sidrena-compliance.php" ), 'Compliance class is not loaded by bootstrap.' );
sidrena_legal_assert( false !== strpos( $plugin_source, 'Sidrena_Compliance::instance()->hooks();' ), 'Compliance hooks are not registered.' );
sidrena_legal_assert( false !== strpos( $compliance_source, "add_action( 'sidrena_publication_watch'" ), 'Publication watchdog integration is missing.' );
sidrena_legal_assert( false !== strpos( $compliance_source, "add_action( 'sidrena_daily_generation'" ), 'Daily generation watchdog integration is missing.' );
sidrena_legal_assert( false !== strpos( $compliance_source, 'Sidrena_Public::ensure_public_page();' ), 'Automatic public page repair is missing.' );
sidrena_legal_assert( false !== strpos( $compliance_source, "array( 'archive_dir', 'current_dir', 'snapshot_dir' )" ), 'Archive/snapshot directory self-heal list is missing.' );
sidrena_legal_assert( false !== strpos( $compliance_source, 'wp_mkdir_p( $paths[ $path_key ] );' ), 'Looped directory self-heal is missing.' );
sidrena_legal_assert( false !== strpos( $compliance_source, 'legal_automation_watchdog' ), 'Audit log event for legal automation watchdog is missing.' );
sidrena_legal_assert( false !== strpos( $compliance_source, '$written = file_put_contents( $index' ), 'Compliance directory protection must capture the index write result.' );
sidrena_legal_assert( false !== strpos( $compliance_source, 'return false !== $written;' ), 'Compliance directory protection must not report success after a failed index write.' );
sidrena_legal_assert( false !== strpos( $compliance_source, 'Sidrena_Legal_Automation::normalize_settings' ), 'Compliance repair must reuse centralized legal setting normalization.' );
sidrena_legal_assert( false !== strpos( $services_source, 'Sidrena_Pricelist::queue_regeneration();' ), 'Service price changes must queue cjenik regeneration.' );

foreach ( array( 'nn_101_2026_anchor_price', 'nn_101_2026_public_pricelist', 'nn_105_2026_retail_unit_price', 'mingo_2026_09_22_clarifications' ) as $source_key ) {
	sidrena_legal_assert( false !== strpos( $compliance_source, $source_key ), 'Legal source marker missing: ' . $source_key );
}
foreach ( array( '2026-09-10', '2025-05-02', 'generate_csv', 'generate_xml', 'enable_public_html', 'enable_rest_index', 'publish_manifest', 'strict_publication', 'publication_watch', 'daily_generation', 'Sidrena automatizacija treba održavati i CSV i XML izlaz' ) as $profile_key ) {
	sidrena_legal_assert( false !== strpos( $compliance_source, $profile_key ), 'Automation profile marker missing: ' . $profile_key );
}

foreach ( array( 'repair_settings', 'repair_schedules', 'repair_public_surface', 'log_watchdog_result', 'LAST_STATUS_OPTION' ) as $marker ) {
	sidrena_legal_assert( false !== strpos( $compliance_source, $marker ), 'Production compliance hardening marker missing: ' . $marker );
}
foreach ( array( 'MAX_MESSAGE_BYTES', 'MAX_CONTEXT_BYTES', 'MAX_ROWS', 'table_exists', 'trim_bytes', 'sidrena_publication_watch' ) as $marker ) {
	sidrena_legal_assert( false !== strpos( $audit_source, $marker ), 'Audit hardening marker missing: ' . $marker );
}

sidrena_legal_assert( false !== strpos( $utils_source, "const STANDARD_REFERENCE_DATE = '2026-09-10';" ), 'Standard reference date is not locked in the legal ruleset.' );
sidrena_legal_assert( false !== strpos( $utils_source, "const FMCG_REFERENCE_DATE     = '2025-05-02';" ), 'FMCG reference date is not locked in the legal ruleset.' );
sidrena_legal_assert(
	false !== strpos( $utils_source, "unset( \$settings['default_ref_date'], \$settings['fmcg_ref_date'], \$settings['fmsid_ref_date'], \$settings['display_lowest_30'], \$settings['track_price_history'] );" ),
	'Legacy administrator date and retired 30-day sale-reference overrides are not stripped from settings.'
);
sidrena_legal_assert( 1 === preg_match( "/'generation_time'\\s*=>\\s*'06:30'/", $utils_source ), 'Default generation time is not automated early enough.' );
sidrena_legal_assert( false !== strpos( $utils_source, "'automation_mode'       => 'wp_cron'" ), 'Internal WP-Cron must remain the safe default automation mode.' );
sidrena_legal_assert( false !== strpos( $compliance_source, "'external' === ( \$settings['automation_mode'] ?? 'wp_cron' )" ), 'Compliance repair must respect external server cron/WP-CLI mode instead of recreating the internal daily cron.' );
sidrena_legal_assert( false !== strpos( $compliance_source, "wp_clear_scheduled_hook( 'sidrena_daily_generation' )" ), 'External automation mode must remove the internal daily generation hook.' );
sidrena_legal_assert( 1 === preg_match( "/'strict_publication'\\s*=>\\s*'yes'/", $utils_source ), 'Strict publication is not enabled by default.' );
sidrena_legal_assert( false !== strpos( $utils_source, "'failure_notifications' => 'yes'" ), 'Failure notifications are not enabled by default.' );
sidrena_legal_assert( false === strpos( $utils_source, "'business_name'        =>" ) && false === strpos( $utils_source, "'show_business_identity' =>" ), 'Unrelated business identity settings must not return to Sidrena defaults.' );
sidrena_legal_assert( false === strpos( $utils_source, "'label_custom'         =>" ) && false === strpos( $utils_source, "'anchor_tooltip_text'    =>" ), 'User-customizable legal labels/tooltips must not return to Sidrena defaults.' );
sidrena_legal_assert( false !== strpos( $utils_source, "Sidrena cijena na %s" ), 'Sidrena reference label must stay fixed and date-based.' );
sidrena_legal_assert( false !== strpos( $utils_source, "'retention_days'        => 30" ), 'Public price-list archive must default to the 30-day minimum.' );
sidrena_legal_assert( false !== strpos( $plugin_source, 'Sidrena_History::instance()->hooks();' ) && false !== strpos( $plugin_source, 'Sidrena_Service_History::instance()->hooks();' ), 'Unlimited audit price-history hooks must remain active.' );
sidrena_legal_assert( false === strpos( $history_source, 'sale_reference' ) && false === strpos( $history_source, 'calculate_lowest_before' ) && false === strpos( $history_source, 'prune_history' ), 'Product history must not restore the retired sale-reference workflow or finite-history pruning.' );
sidrena_legal_assert( false !== strpos( $history_source, 'lowest_30_day_reference' ) && false !== strpos( $history_source, 'never changes or supplies the immutable SIDRENA' ), 'Woo 30-day minimum must remain an explicit consumer-price rule separate from the immutable anchor ruleset.' );
sidrena_legal_assert( false === strpos( $service_history_source, 'sale_reference' ) && false === strpos( $service_history_source, 'lowest_30' ) && false === strpos( $service_history_source, 'calculate_lowest_before' ) && false === strpos( $service_history_source, 'prune_history' ), 'Service audit history must not contain the retired 30-day sale-reference workflow.' );
sidrena_legal_assert( false !== strpos( $changelog_source, '= 1.0.0 =' ) && false !== strpos( $changelog_source, 'compliance/automation watchdog' ), '1.0.0 consolidated changelog does not mention the compliance/automation watchdog.' );
sidrena_legal_assert( false !== stripos( $changelog_source, 'production hardening' ), '1.0.0 consolidated changelog does not mention production hardening.' );

fwrite( STDOUT, "Sidrena legal automation smoke test passed.\n" );
