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
	false !== strpos( $standalone, 'sid-standalone-table' )
	&& false !== strpos( $standalone, 'sid-standalone-details-row' )
	&& false !== strpos( $standalone, 'sid-row-details__grid--wordpress' ),
	'WordPress catalog must retain compact expandable product rows.'
);
sidrena_reference_ui_assert(
	false !== strpos( $script, "closest(removeStandalone, '.sid-standalone-details-row')" )
	&& false !== strpos( $script, 'detailsRow.remove()' ),
	'Removing an unsaved WordPress catalog row must also remove its detail row.'
);

sidrena_reference_ui_assert(
	false === strpos( $admin, 'style="--sid-score:' )
	&& false !== strpos( $admin, 'sid-reference-ring__value' ),
	'Compliance progress must not reintroduce inline CSS.'
);

sidrena_reference_ui_assert(
	false !== strpos( $admin, '$last_success' )
	&& false !== strpos( $admin, "'files'] ?? 0" )
	&& false !== strpos( $admin, 'sidrena_check_public_access' ),
	'Publication UI must distinguish successful runs and retain the public-access action.'
);
sidrena_reference_ui_assert(
	false !== strpos( $admin, "'missing_current_total'" )
	&& false !== strpos( $admin, '$current_price_ok' )
	&& false !== strpos( $admin, '$lowest_price_ok' )
	&& false !== strpos( $admin, '$dated_price_ok' ),
	'Price-label status rows must derive from actual audit data.'
);
sidrena_reference_ui_assert(
	false !== strpos( $bulk, '$catalog_visibility' )
	&& false !== strpos( $bulk, '$public_included' )
	&& false !== strpos( $bulk, "'hidden' !==" ),
	'Woo public-catalog status must honor native catalog visibility.'
);

$wordpress_dashboard_start = strpos( $admin, 'private function wordpress_dashboard( $data )' );
$woocommerce_dashboard_start = strpos( $admin, 'private function woocommerce_dashboard( $data )' );
sidrena_reference_ui_assert(
	false !== $wordpress_dashboard_start
	&& false !== $woocommerce_dashboard_start
	&& $woocommerce_dashboard_start > $wordpress_dashboard_start,
	'Unable to isolate the WordPress dashboard renderer.'
);
$wordpress_dashboard_source = substr( $admin, $wordpress_dashboard_start, $woocommerce_dashboard_start - $wordpress_dashboard_start );
$first_php_close = strpos( $wordpress_dashboard_source, '?>' );
$anchor_assignment = strpos( $wordpress_dashboard_source, '$anchor_caption = sprintf(' );
$published_assignment = strpos( $wordpress_dashboard_source, '$published_caption = sprintf(' );
sidrena_reference_ui_assert(
	false !== $first_php_close
	&& false !== $anchor_assignment
	&& false !== $published_assignment
	&& $anchor_assignment < $first_php_close
	&& $published_assignment < $first_php_close,
	'WordPress dashboard captions must be computed inside PHP and must never leak as visible source text.'
);

sidrena_reference_ui_assert(
	false === strpos( $style, 'grid-template-columns:minmax(360px,520px) minmax(280px,1fr) auto' )
	&& false === strpos( $style, ".sidrena-admin-screen .sidrena-brandbar__logo {\n\twidth:min(430px,100%);" )
	&& false === strpos( $style, 'SIDRENA 1.0.14 production polish' )
	&& false !== strpos( $style, ".sidrena-brandbar {\n\tposition:relative;\n\tdisplay:grid;\n\tgrid-template-columns:minmax(430px,470px) minmax(360px,1fr) max-content;" ),
	'Hero sizing must remain consolidated in the canonical 1.0.16 component layer.'
);

sidrena_reference_ui_assert(
	false !== strpos( $capture, 'visibleSourceLeak' )
	&& false !== strpos( $capture, "['.sidrena-brandbar__edition', '.sidrena-brandbar__copy']" ),
	'Real browser capture must detect visible PHP fragments and edition-badge overlap.'
);

fwrite( STDOUT, "Sidrena reference admin UI smoke test passed.\n" );
