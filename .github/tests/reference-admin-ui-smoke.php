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
$products   = file_get_contents( $root . '/includes/class-sidrena-products.php' );
$services   = file_get_contents( $root . '/includes/class-sidrena-services.php' );
$standalone = file_get_contents( $root . '/includes/class-sidrena-standalone.php' );
$script     = file_get_contents( $root . '/admin/js/admin.js' );
$style      = file_get_contents( $root . '/admin/css/brand.css' );
$capture    = file_get_contents( $root . '/tools/capture-wporg-assets.mjs' );
$compat     = file_get_contents( $root . '/public/js/compat.js' );

function sidrena_reference_ui_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

foreach ( array( $admin, $bulk, $products, $services, $standalone, $script, $style, $capture, $compat ) as $contents ) {
	sidrena_reference_ui_assert( false !== $contents, 'Unable to read a reference UI source file.' );
}

foreach (
	array(
		'sidrena-brandbar',
		'sidrena-contextbar',
		'sid-dashboard-metrics--archive',
		'private function archive_timeline( $archive )',
		'Arhivirane objave',
		'Uredi postavke arhive',
	) as $needle
) {
	sidrena_reference_ui_assert( false !== strpos( $admin, $needle ), 'Reference admin renderer regression: ' . $needle );
}

foreach (
	array(
		'sid-woo-compact-table',
		'sid-row-details',
		'Napredna SIDRENA polja',
		'private function safe_suggestions( $product )',
		"apply_filters( 'sidrena_safe_field_suggestions'",
		'data-sidrena-safe-fill="code"',
		'data-sidrena-safe-fill="brand"',
		'data-sidrena-safe-fill="barcode"',
		'id="sid-safe-fill-page"',
		'sid-safe-fill-row',
	) as $needle
) {
	sidrena_reference_ui_assert( false !== strpos( $bulk, $needle ), 'Woo compact catalog regression: ' . $needle );
}

sidrena_reference_ui_assert(
	false === strpos( $products, "<option value=\"custom\"" )
	&& false === strpos( $services, "<option value=\"custom\"" )
	&& false === strpos( $standalone, "<option value=\"custom\"" ),
	'Normal product, service and standalone forms must not offer a manual custom reference-date choice.'
);

sidrena_reference_ui_assert(
	false === strpos( $products, 'name="_sidrena_anchor_date"' )
	&& false === strpos( $products, 'name="_sidrena_anchor_date[' )
	&& false === strpos( $services, 'name="sidrena_service_anchor_date"' )
	&& false === strpos( $standalone, '][anchor_date]"' )
	&& false === strpos( $bulk, '][date]"' )
	&& false === strpos( $products, "'_sidrena_anchor_date'       => 'string'" ),
	'Normal SIDRENA admin screens must not expose an arbitrary editable custom reference date.'
);

sidrena_reference_ui_assert(
	false === strpos( $bulk, "Najniža 30 dana" )
	&& false === strpos( $bulk, 'sale_reference' )
	&& false === strpos( $bulk, "<th scope=\"col\"><?php esc_html_e( 'Pakiranje', 'sidrena' ); ?></th><th scope=\"col\"><?php esc_html_e( 'Jedinica', 'sidrena' ); ?></th>" ),
	'The legacy 13-column Woo editor must not return as the main table.'
);

foreach (
	array(
		'function validateFileField(field)',
		"message('fileTooLarge'",
		"message('invalidFileType'",
		"document.querySelectorAll('.sid-file-input').forEach(validateFileField)",
		'function safeFillScope(scope)',
		"closest(target, '.sid-safe-fill-row')",
		"closest(target, '#sid-safe-fill-page')",
		"message('safeFillChanged'",
		"message('safeFillEmpty'",
		"closest(target, '#sid-fill-woo-location')",
		"data-sidrena-woo-address",
		"message('wooLocationFilled'",
		"message('wooLocationNoTarget'",
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
		'.sid-safe-fill',
		'.sid-bulk-actions__primary',
		'input.is-suggested',
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
	false === strpos( $admin, 'style="' )
	&& false !== strpos( $admin, 'sid-dashboard-metrics--archive' )
	&& false !== strpos( $admin, 'sidrena-contextbar' ),
	'Current SIDRENA dashboard must remain free of inline CSS.'
);

sidrena_reference_ui_assert(
	false !== strpos( $admin, "Jednostavno postavljanje" )
	&& false !== strpos( $admin, "Zakonska pravila" )
	&& false !== strpos( $admin, "Zaštita objave" )
	&& false === strpos( $admin, "Podaci obrta / tvrtke" )
	&& false === strpos( $admin, 'name="display_anchor"' ),
	'Settings UI must remain focused on layperson-safe automatic legal publication.'
);

sidrena_reference_ui_assert(
	false !== strpos( $admin, 'id="sid-fill-woo-location"' )
	&& false !== strpos( $admin, 'Sidrena_Utils::woocommerce_store_address()' )
	&& false !== strpos( $admin, 'sid-location-address-input' ),
	'Woo location onboarding must remain an explicit empty-field suggestion and never an automatic overwrite.'
);

sidrena_reference_ui_assert(
	false !== strpos( $admin, 'Sidrena_Utils::public_index()' )
	&& false !== strpos( $admin, 'sidrena_check_public_access' )
	&& false !== strpos( $admin, "'public_access_ok'" ),
	'Publication UI must distinguish successful runs and retain the public-access action.'
);
sidrena_reference_ui_assert(
	false !== strpos( $admin, "'missing_current_total'" )
	&& false !== strpos( $admin, '$current_price_ok' )
	&& false !== strpos( $admin, '$dated_price_ok' )
	&& false === strpos( $admin, '$lowest_price_ok' ),
	'Price-label status rows must derive from current-price and Sidrena-price audit data only.'
);
sidrena_reference_ui_assert(
	false !== strpos( $bulk, '$catalog_visibility' )
	&& false !== strpos( $bulk, '$public_included' )
	&& false !== strpos( $bulk, "'hidden' !==" ),
	'Woo public-catalog status must honor native catalog visibility.'
);

sidrena_reference_ui_assert(
	false !== strpos( $admin, 'private function archive_timeline( $archive )' )
	&& false !== strpos( $admin, 'sid-dashboard-metrics--archive' )
	&& false !== strpos( $admin, 'Arhivirane objave' ),
	'Current SIDRENA archive dashboard renderer is incomplete.'
);

sidrena_reference_ui_assert(
	false === strpos( $style, 'grid-template-columns:minmax(360px,520px) minmax(280px,1fr) auto' )
	&& false === strpos( $style, ".sidrena-admin-screen .sidrena-brandbar__logo {\n\twidth:min(430px,100%);" )
	&& false === strpos( $style, 'SIDRENA 1.0.14 production polish' )
	&& false !== strpos( $style, ".sidrena-brandbar {\n\tposition:relative;\n\tdisplay:grid;\n\tgrid-template-columns:minmax(430px,470px) minmax(360px,1fr) max-content;" ),
	'Hero sizing must remain consolidated in the canonical 1.0.16 component layer.'
);

sidrena_reference_ui_assert(
	false === strpos( $style, 'Sidrena 1.0.10 brand UI' ),
	'Production admin CSS must not retain obsolete release-specific branding comments.'
);

sidrena_reference_ui_assert(
	false !== strpos( $style, '.sidrena-admin-screen .sidrena-brandbar__edition .dashicons,' ),
	'Edition badge icon sizing must remain in the canonical production icon group.'
);

sidrena_reference_ui_assert(
	false !== strpos( $compat, 'id = parseInt(id || 0, 10);' )
	&& false !== strpos( $compat, 'if (id !== activeId) {' )
	&& false !== strpos( $compat, 'applyMarkup(html, root, variationMode);' ),
	'Woo compatibility hydration must discard stale asynchronous variation responses.'
);

sidrena_reference_ui_assert(
	false !== strpos( $capture, 'visibleSourceLeak' )
	&& false !== strpos( $capture, "['.sidrena-brandbar__edition', '.sidrena-brandbar__copy']" ),
	'Real browser capture must detect visible PHP fragments and edition-badge overlap.'
);

fwrite( STDOUT, "Sidrena reference admin UI smoke test passed.\n" );
