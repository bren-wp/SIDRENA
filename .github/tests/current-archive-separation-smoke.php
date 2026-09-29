<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

function sidrena_current_archive_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$root       = dirname( __DIR__, 2 );
$pricelist  = file_get_contents( $root . '/includes/class-sidrena-pricelist.php' );
$utils      = file_get_contents( $root . '/includes/class-sidrena-utils.php' );
$admin      = file_get_contents( $root . '/includes/class-sidrena-admin.php' );
$bulk       = file_get_contents( $root . '/includes/class-sidrena-bulk.php' );
$products   = file_get_contents( $root . '/includes/class-sidrena-products.php' );

sidrena_current_archive_assert(
	false !== strpos( $pricelist, "add_action( 'sidrena_daily_generation', array( \$this, 'publish_daily_archive' ) );" )
	&& false !== strpos( $pricelist, "add_action( 'sidrena_queued_generation', array( \$this, 'refresh_current' ) );" ),
	'Daily archive and current refresh hooks must remain separate.'
);

$refresh_start = strpos( $pricelist, 'public function refresh_current()' );
$refresh_end   = false !== $refresh_start ? strpos( $pricelist, 'private function publication_alert_recipient()', $refresh_start ) : false;
sidrena_current_archive_assert( false !== $refresh_start && false !== $refresh_end, 'Current refresh method is missing.' );
$refresh = substr( $pricelist, $refresh_start, $refresh_end - $refresh_start );
sidrena_current_archive_assert( false === strpos( $refresh, 'merge_archive_index' ), 'Current refresh must never write the immutable archive index.' );
sidrena_current_archive_assert( false !== strpos( $refresh, "'sidrena_last_current_refresh'" ), 'Current refresh status must be stored separately from the daily archive status.' );
sidrena_current_archive_assert( false !== strpos( $refresh, "build_current_filename" ), 'Current refresh must publish stable current filenames.' );

sidrena_current_archive_assert(
	false !== strpos( $utils, "'current_dir'" )
	&& false !== strpos( $utils, "'current_url'" ),
	'Dedicated current price-list paths are missing.'
);
sidrena_current_archive_assert( false !== strpos( $pricelist, "return sprintf( 'aktualni-%s-%s.%s'" ), 'Stable current filename convention is missing.' );
sidrena_current_archive_assert( false !== strpos( $pricelist, "wp_date( 'Y-m-d', \$last_ts ) === wp_date( 'Y-m-d' )" ), 'Daily archive duplicate guard is missing.' );

sidrena_current_archive_assert(
	false !== strpos( $admin, 'Osvježi aktualni cjenik' )
	&& false !== strpos( $admin, 'Objavi današnji arhivski cjenik' ),
	'Admin must expose current refresh and daily archive publication as separate actions.'
);

sidrena_current_archive_assert(
	false === strpos( $bulk, '][date]"' )
	&& false === strpos( $products, "'_sidrena_anchor_date'       => 'string'" ),
	'Anchor dates must not be editable through bulk forms or the standard REST meta surface.'
);

fwrite( STDOUT, "SIDRENA current/archive separation smoke test passed.\n" );
