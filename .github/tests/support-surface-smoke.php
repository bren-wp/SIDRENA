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
define( 'SIDRENA_URL', 'https://example.test/wp-content/plugins/sidrena/' );

function apply_filters( $hook, $value ) {
	unset( $hook );
	return $value;
}
function esc_url_raw( $url ) {
	return (string) $url;
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php';

function sidrena_support_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

sidrena_support_assert( 'sidrena@brendigo.com' === Sidrena_Utils::support_email(), 'Support email mismatch.' );
sidrena_support_assert( false !== strpos( Sidrena_Utils::support_email_url(), 'mailto:sidrena@brendigo.com' ), 'Support mailto URL missing.' );
sidrena_support_assert( '+385 91 901 0092' === Sidrena_Utils::whatsapp_number(), 'WhatsApp number mismatch.' );
sidrena_support_assert( false !== strpos( Sidrena_Utils::whatsapp_url(), 'wa.me/385919010092' ), 'WhatsApp URL mismatch.' );
sidrena_support_assert( '80 EUR' === Sidrena_Utils::installation_price(), 'Installation price mismatch.' );
sidrena_support_assert( false !== strpos( rawurldecode( Sidrena_Utils::installation_service_url() ), '80 EUR' ), 'Installation service URL must mention 80 EUR.' );
sidrena_support_assert( false !== strpos( Sidrena_Utils::donation_url(), 'revolut.me/catanyus' ), 'Direct Revolut donation URL missing.' );
sidrena_support_assert( false !== strpos( Sidrena_Utils::support_pdf_url(), 'docs/SIDRENA-PODRSKA.pdf' ), 'Support PDF URL mismatch.' );
sidrena_support_assert( 'Brendigo' === Sidrena_Utils::developer_label(), 'Author label mismatch.' );

sidrena_support_assert(
	false !== strpos( $builder, '"wordpress" "$WP_STAGE/docs/UPUTE.md"' )
	&& false !== strpos( $builder, '"woocommerce" "$WOO_STAGE/docs/UPUTE.md"' ),
	'Each edition must build its own PDF manual from its prepared guide.'
);
foreach ( array( 'Detaljne upute za krajnjeg korisnika', '80 EUR jednokratno', 'Revolut donacija', 'guide_path', 'edition_label' ) as $needle ) {
	sidrena_support_assert( false !== strpos( $pdf_tool, $needle ), 'Detailed PDF manual generator missing: ' . $needle );
}
sidrena_support_assert(
	false !== strpos( $wp_guide, 'Korak-po-korak za korisnika koji prvi put koristi Sidrenu' )
	&& false !== strpos( $woo_guide, 'Korak-po-korak za korisnika koji prvi put koristi Sidrena WooCommerce' ),
	'Both edition guides must contain layperson step-by-step instructions.'
);
sidrena_support_assert(
	false !== strpos( $wp_guide, '80 EUR' )
	&& false !== strpos( $woo_guide, '80 EUR' )
	&& false !== strpos( $wp_guide, 'Donacija' )
	&& false !== strpos( $woo_guide, 'Donacija' ),
	'Donation and optional paid setup must remain documented in both editions.'
);

$admin    = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-admin.php' );
$public   = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-public.php' );
$services = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-services.php' );
$frontend = file_get_contents( dirname( __DIR__, 2 ) . '/public/css/frontend.css' );
$builder  = file_get_contents( dirname( __DIR__, 2 ) . '/tools/build-editions.sh' );
$pdf_tool = file_get_contents( dirname( __DIR__, 2 ) . '/tools/build-support-pdf.py' );
$wp_guide = file_get_contents( dirname( __DIR__, 2 ) . '/docs/UPUTE-WORDPRESS.md' );
$woo_guide = file_get_contents( dirname( __DIR__, 2 ) . '/docs/UPUTE-WOOCOMMERCE.md' );

foreach ( array( 'sidrena-support', 'support_tab', 'about_tab', 'help_tab', 'dashicons-pdf', 'Zatraži postavljanje - %s', 'Jednokratno početno postavljanje', 'Dobrovoljna donacija za razvoj', 'logo-horizontal-light.svg', 'sidrena-brandbar__edition', 'admin/css/brand.css' ) as $needle ) {
	sidrena_support_assert( false !== strpos( $admin, $needle ), 'Admin support surface missing: ' . $needle );
}
foreach ( array( 'sidrena_objava_cjenika', 'Objava cjenika', '$group_index', '1 === $group_index' ) as $needle ) {
	sidrena_support_assert( false !== strpos( $public, $needle ), 'Public publication surface missing: ' . $needle );
}

sidrena_support_assert(
	false !== strpos( $public, '<caption class="sidrena-visually-hidden">' )
	&& 8 <= substr_count( $public, 'scope="col"' )
	&& false !== strpos( $public, '<th scope="row" data-label="' ),
	'Public price-list table must retain an independent caption and scoped column headers.'
);
sidrena_support_assert(
	false !== strpos( $services, '<caption class="sidrena-visually-hidden">' )
	&& false !== strpos( $services, '<th scope="row">' ),
	'Public services table must retain caption and row/column header semantics.'
);
sidrena_support_assert(
	false !== strpos( $frontend, '.sidrena-visually-hidden' )
	&& false !== strpos( $frontend, 'clip:rect(0,0,0,0)!important;' ),
	'Frontend accessibility utility must remain local and theme-independent.'
);

sidrena_support_assert( false === strpos( $admin, '20 EUR' ), 'Unrequested recurring maintenance offer leaked into admin.' );
sidrena_support_assert(
	false === strpos( $admin, 'Podaci obrta / tvrtke' )
	&& false === strpos( $public, 'sidrena-business-card' ),
	'Unrelated business-identity settings/public card must stay outside Sidrena price-list scope.'
);
fwrite( STDOUT, "Sidrena support/public surface smoke test passed.\n" );

