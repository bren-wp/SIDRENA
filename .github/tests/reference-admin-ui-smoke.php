<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 */

declare( strict_types=1 );

$root       = dirname( __DIR__, 2 );
$admin      = file_get_contents( $root . '/includes/class-sidrena-admin.php' );
$bulk       = file_get_contents( $root . '/includes/class-sidrena-bulk.php' );
$standalone = file_get_contents( $root . '/includes/class-sidrena-standalone.php' );
$script     = file_get_contents( $root . '/admin/js/admin.js' );
$style      = file_get_contents( $root . '/admin/css/brand.css' );
$capture    = file_get_contents( $root . '/tools/capture-wporg-assets.mjs' );

function sidrena_reference_ui_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

foreach ( array( $admin, $bulk, $standalone, $script, $style, $capture ) as $contents ) {
	sidrena_reference_ui_assert( false !== $contents, 'Unable to read a reference UI source file.' );
}

foreach (
	array(
		'private function wordpress_dashboard( $data )',
		'private function woocommerce_dashboard( $data )',
		'sid-reference-dashboard--wordpress',
		'sid-reference-dashboard--woocommerce',
		'sid-reference-files-grid',
		'sid-reference-public-preview',
	) as $needle
) {
	sidrena_reference_ui_assert( false !== strpos( $admin, $needle ), 'Reference admin renderer regression: ' . $needle );
}

foreach (
	array(
		'sid-woo-compact-table',
		'sid-row-details',
		'Napredna SIDRENA polja',
		'Sidrena_History::sale_reference( $product )',
	) as $needle
) {
	sidrena_reference_ui_assert( false !== strpos( $bulk, $needle ), 'Woo compact catalog regression: ' . $needle );
}

sidrena_reference_ui_assert(
	false === strpos( $bulk, "<th scope=\"col\"><?php esc_html_e( 'Pakiranje', 'sidrena' ); ?></th><th scope=\"col\"><?php esc_html_e( 'Jedinica', 'sidrena' ); ?></th>" ),
	'The legacy 13-column Woo editor must not return as the main table.'
);

foreach (
	array(
		'function validateFileField(field)',
		"message('fileTooLarge'",
		"message('invalidFileType'",
		"document.querySelectorAll('.sid-file-input').forEach(validateFileField)",
	) as $needle
) {
	sidrena_reference_ui_assert( false !== strpos( $script, $needle ), 'Upload validation regression: ' . $needle );
}

foreach (
	array(
		'.sid-reference-grid--wp-main',
		'.sid-reference-grid--woo-main',
		'.sid-reference-files-grid',
		'.sid-woo-compact-table',
		'.sid-row-details__grid',
		'.sid-fields-location',
		'@media (max-width:782px)',
		'@media (max-width:560px)',
	) as $needle
) {
	sidrena_reference_ui_assert( false !== strpos( $style, $needle ), 'Reference CSS regression: ' . $needle );
}

sidrena_reference_ui_assert(
	false !== strpos( $capture, 'assertNoClientErrors' )
	&& false !== strpos( $capture, 'assertNoKeyOverlaps' )
	&& false !== strpos( $capture, 'assertFormRuntime' ),
	'Real wp-admin capture must keep JavaScript, overlap and form viewport assertions.'
);

sidrena_reference_ui_assert(
	false !== strpos( $standalone, 'sid-standalone-table' ),
	'WordPress catalog table must remain part of the reference UI surface.'
);

fwrite( STDOUT, "Sidrena reference admin UI smoke test passed.\n" );
