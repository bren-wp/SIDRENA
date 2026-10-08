<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

declare( strict_types=1 );

$root       = dirname( __DIR__, 2 );
$admin      = file_get_contents( $root . '/includes/class-sidrena-admin.php' );
$standalone = file_get_contents( $root . '/includes/class-sidrena-standalone.php' );
$script     = file_get_contents( $root . '/admin/js/admin.js' );
$style      = file_get_contents( $root . '/admin/css/brand.css' );
$editor     = file_get_contents( $root . '/admin/css/admin.css' );
$services   = file_get_contents( $root . '/includes/class-sidrena-services.php' );

function sidrena_form_ux_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

foreach ( array( $admin, $standalone, $script, $style, $editor, $services ) as $contents ) {
	sidrena_form_ux_assert( false !== $contents, 'Unable to read an admin UX source file.' );
}

sidrena_form_ux_assert(
	2 === substr_count( $admin, 'class="sid-card sid-tool-card sid-form"' ),
	'Both WooCommerce CSV import forms must use the managed Sidrena form lifecycle.'
);
sidrena_form_ux_assert(
	false !== strpos( $standalone, 'class="sid-form sid-inline-form"' ),
	'Standalone source sync must use the managed Sidrena form lifecycle.'
);
sidrena_form_ux_assert(
	false !== strpos( $standalone, 'aria-describedby="sid-standalone-file-help"' ),
	'Standalone import file input must expose its help text to assistive technology.'
);
sidrena_form_ux_assert(
	false !== strpos( $admin, 'aria-describedby="sid-anchor-csv-help"' )
	&& false !== strpos( $admin, 'aria-describedby="sid-location-csv-help"' ),
	'WooCommerce import file inputs must expose their help text to assistive technology.'
);

foreach (
	array(
		"document.addEventListener('invalid'",
		"window.addEventListener('pageshow'",
		"setFormStatus(form, message('savingForm'",
		"form.setAttribute('aria-busy', 'true')",
		"button.removeAttribute('aria-disabled')",
	) as $needle
) {
	sidrena_form_ux_assert( false !== strpos( $script, $needle ), 'Admin form lifecycle regression: ' . $needle );
}

foreach (
	array(
		'.sid-toggle-card input:focus-visible+.sid-toggle-ui',
		'.sid-form-status',
		'input[aria-invalid="true"]',
		'.sid-table-wrap:focus-within',
		'.sid-file-control',
		'.sid-file-input { display:block;width:100%;min-width:0; }',
		'.sid-inline-form .sid-form-status',
	) as $needle
) {
	sidrena_form_ux_assert( false !== strpos( $style, $needle ), 'Admin form style regression: ' . $needle );
}

sidrena_form_ux_assert(
	false !== strpos( $admin, "'savingForm'" ) && false !== strpos( $admin, "'invalidField'" ),
	'Localized form feedback strings must remain available to admin.js.'
);
sidrena_form_ux_assert(
	false !== strpos( $services, 'for="sidrena_service_location_price_' )
	&& false !== strpos( $services, 'for="sidrena_service_location_anchor_price_' )
	&& false !== strpos( $services, 'id="sidrena_service_location_price_' )
	&& false !== strpos( $services, 'id="sidrena_service_location_anchor_price_' ),
	'Service per-location price controls must retain explicit labels.'
);
sidrena_form_ux_assert(
	false !== strpos( $editor, '.sid-service-location-fields' ),
	'Service per-location form layout must remain responsive.'
);

sidrena_form_ux_assert(
	false === strpos( $admin, 'data-sidrena-oib' )
	&& false === strpos( $admin, 'business_name' )
	&& false === strpos( $admin, 'show_business_identity' )
	&& false === strpos( $script, 'function isValidOib(value)' ),
	'General business-identity fields must stay outside the focused Sidrena settings workflow.'
);
sidrena_form_ux_assert(
	false !== strpos( $admin, 'is_email( $failure_email )' )
	&& false !== strpos( $admin, "settings_invalid_email" ),
	'Publication alert email must retain server-side validation.'
);
sidrena_form_ux_assert(
	false !== strpos( $admin, 'name="automation_mode"' )
	&& false !== strpos( $admin, 'Vanjski server cron / WP-CLI' )
	&& false !== strpos( $admin, 'wp sidrena publish' )
	&& false !== strpos( $admin, "wp_clear_scheduled_hook( 'sidrena_daily_generation' )" ),
	'Publication settings must expose a validated external scheduler mode that explicitly removes the internal daily cron.'
);
sidrena_form_ux_assert(
	false !== strpos( $admin, "CSV + XML uključeni" )
	&& false !== strpos( $admin, "Javni i strojni pristup" )
	&& false === strpos( $admin, 'name="generate_csv"' )
	&& false === strpos( $admin, 'name="enable_rest_index"' ),
	'Legal publication switches must be automatic rather than user-disableable settings.'
);

sidrena_form_ux_assert(
	false !== strpos( $script, 'function validateFileField(field)' )
	&& false !== strpos( $script, "message('fileTooLarge'" )
	&& false !== strpos( $script, "message('invalidFileType'" ),
	'Client-side upload size/type validation must remain available.'
);
sidrena_form_ux_assert(
	false !== strpos( $admin, "'fileTooLarge'" )
	&& false !== strpos( $admin, "'invalidFileType'" ),
	'Localized upload validation feedback must remain available.'
);

sidrena_form_ux_assert(
	false !== strpos( $admin, "'locationAdded'" )
	&& false !== strpos( $admin, "'locationRemoved'" )
	&& false !== strpos( $admin, "'removeLocationLabel'" )
	&& false !== strpos( $admin, 'class="sid-location-title"' )
	&& false !== strpos( $admin, 'class="sid-location-address"' ),
	'Dynamic location cards must retain contextual labels and localized feedback.'
);
sidrena_form_ux_assert(
	false !== strpos( $script, 'function updateLocationSummary(row)' )
	&& false !== strpos( $script, "remove.setAttribute('aria-label'" )
	&& false !== strpos( $script, 'var nextRow = row.nextElementSibling' )
	&& false !== strpos( $script, 'focusFirstField(nextRow || previousRow)' ),
	'Dynamic location summaries, removal labels and focus recovery must remain synchronized.'
);

$admin_table_count   = substr_count( $admin, '<table' );
$admin_caption_count = substr_count( $admin, '<caption class="screen-reader-text">' );
sidrena_form_ux_assert(
	0 < $admin_table_count
	&& $admin_table_count === $admin_caption_count
	&& 0 < substr_count( $admin, 'scope="col"' )
	&& false !== strpos( $admin, "esc_html_e( 'Radnje', 'sidrena' )" )
	&& false !== strpos( $admin, 'aria-label="<?php echo esc_attr( $open_label ); ?>"' ),
	'Every admin data table must retain a screen-reader caption, scoped headers and contextual action labels.'
);


sidrena_form_ux_assert(
	false !== strpos( $admin, 'class="sidrena-quicknav"' )
	&& false !== strpos( $admin, 'aria-current="page"' )
	&& false !== strpos( $admin, 'Sidrena_Admin_Menu::items() as $nav_item' )
	&& false !== strpos( $admin, 'Sidrena_Admin_UX::SERVICE_MENU_SLUG === $nav_slug' ),
	'Primary Sidrena nav must reuse the registered menu, preserve services route and expose the active page.'
);
sidrena_form_ux_assert(
	false !== strpos( $admin, "'logo-wordpress-light.svg'" )
	&& false !== strpos( $admin, "'logo-woocommerce-light.svg'" )
	&& false !== strpos( $admin, 'width="560" height="112"' ),
	'Both editions must display the matching, dimensionally accurate brand logo.'
);
sidrena_form_ux_assert(
	false !== strpos( $style, '.sidrena-admin-screen .sidrena-quicknav__scroll' )
	&& false !== strpos( $style, 'overflow-x:auto;' )
	&& false !== strpos( $style, '.sidrena-admin-screen .sidrena-quicknav__item:focus-visible' )
	&& false !== strpos( $style, '.sidrena-admin-screen .sidrena-quicknav__item[aria-current="page"]' ),
	'Quick navigation must be horizontally scrollable and keyboard accessible on mobile.'
);
sidrena_form_ux_assert(
	false !== strpos( $style, '.sidrena-admin-screen .sidrena-app .sid-toggle-card:has(input:checked)' )
	&& false !== strpos( $style, '.sidrena-admin-screen .sid-switch input:focus-visible+span::before' )
	&& false !== strpos( $editor, '.sid-service-location-fields input:focus-visible' ),
	'Edition toggle controls and Woo/service editor fields must retain visible interactive states.'
);
sidrena_form_ux_assert(
	false !== strpos( $script, 'function syncFieldValidity(field)' )
	&& false !== strpos( $script, 'if (!syncFieldValidity(target))' )
	&& false !== strpos( $script, 'if (!syncFieldValidity(event.target))' )
	&& false !== strpos( $script, "status.setAttribute('role', isError ? 'alert' : 'status')" )
	&& false !== strpos( $script, "message('unsavedChanges'" )
	&& false !== strpos( $admin, "'unsavedChanges'" ),
	'Client-side validation must preserve errors after file changes, announce invalid fields and signal unsaved edits.'
);
sidrena_form_ux_assert(
	false !== strpos( $style, '.sidrena-admin-screen .sidrena-app .sid-form-status.is-error:not(:empty)' )
	&& false !== strpos( $style, '.sidrena-admin-screen .sidrena-app input[aria-invalid="true"]' ),
	'Validation messages and invalid fields must keep their distinct accessible visual states.'
);

fwrite( STDOUT, "Sidrena admin form UX smoke test passed.\n" );
