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

$admin  = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-admin.php' );
$public = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-public.php' );

foreach ( array( 'sidrena-support', 'support_tab', 'about_tab', 'help_tab', 'dashicons-pdf', 'Zatraži postavljanje - %s', 'Jednokratna instalacija i početno postavljanje', 'Dobrovoljna donacija za razvoj', 'logo-wordpress-light.svg', 'logo-woocommerce-light.svg', 'admin/css/brand.css' ) as $needle ) {
	sidrena_support_assert( false !== strpos( $admin, $needle ), 'Admin support surface missing: ' . $needle );
}
foreach ( array( 'sidrena_objava_cjenika', 'Objava cjenika', '$group_index', '1 === $group_index' ) as $needle ) {
	sidrena_support_assert( false !== strpos( $public, $needle ), 'Public publication surface missing: ' . $needle );
}

sidrena_support_assert( false === strpos( $admin, '20 EUR' ), 'Unrequested recurring maintenance offer leaked into admin.' );
fwrite( STDOUT, "Sidrena support/public surface smoke test passed.\n" );

