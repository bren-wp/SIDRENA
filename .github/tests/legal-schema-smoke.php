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

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( preg_replace( '/[\x00-\x1F\x7F]+/', '', (string) $value ) );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-legal-automation.php';
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-pricelist.php';

function sidrena_schema_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$instance = Sidrena_Pricelist::instance();

$product_method = new ReflectionMethod( 'Sidrena_Pricelist', 'product_headers' );
$product_method->setAccessible( true );
$product_headers = $product_method->invoke( $instance );

$service_method = new ReflectionMethod( 'Sidrena_Pricelist', 'service_headers' );
$service_method->setAccessible( true );
$service_headers = $service_method->invoke( $instance );

$required_products = array(
	'naziv',
	'sifra',
	'marka',
	'jedinica_mjere',
	'cijena_za_jedinicu_mjere',
	'maloprodajna_cijena',
	'posebni_oblik_prodaje',
	'naziv_posebnog_oblika_prodaje',
	'sidrena_cijena',
	'barkod',
	'dostupnost',
);

$required_services = array(
	'naziv_usluge',
	'maloprodajna_cijena',
	'posebni_oblik_prodaje',
	'naziv_posebnog_oblika_prodaje',
	'sidrena_cijena',
);

foreach ( $required_products as $header ) {
	sidrena_schema_assert( in_array( $header, $product_headers, true ), 'Required NN 101/2026 product field missing: ' . $header );
}
foreach ( $required_services as $header ) {
	sidrena_schema_assert( in_array( $header, $service_headers, true ), 'Required NN 101/2026 service field missing: ' . $header );
}

sidrena_schema_assert( count( $product_headers ) === count( array_unique( $product_headers ) ), 'Duplicate product headers detected.' );
sidrena_schema_assert( count( $service_headers ) === count( array_unique( $service_headers ) ), 'Duplicate service headers detected.' );
sidrena_schema_assert( in_array( 'datum_sidrene_cijene', $product_headers, true ), 'Reference date traceability missing from product output.' );
sidrena_schema_assert( in_array( 'datum_sidrene_cijene', $service_headers, true ), 'Reference date traceability missing from service output.' );
foreach ( array( 'vrsta_usluge', 'opseg_usluge', 'pripadajuci_troskovi' ) as $service_detail ) {
	sidrena_schema_assert( in_array( $service_detail, $service_headers, true ), 'NN 105/2026 service detail missing: ' . $service_detail );
}

$utils_source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php' );
sidrena_schema_assert( false !== strpos( $utils_source, "'default_ref_date'     => '2026-09-10'" ), 'Default reference date must remain 10.09.2026.' );
sidrena_schema_assert( false !== strpos( $utils_source, "'fmcg_ref_date'        => '2025-05-02'" ), 'Existing FMCG reference date must remain 02.05.2025.' );
sidrena_schema_assert( false !== strpos( $utils_source, "'retention_days'       => 45" ), 'Default archive retention should preserve an operational margin above 30 days.' );
sidrena_schema_assert( false !== strpos( $utils_source, "max( 30, absint( \$settings['retention_days'] ) )" ), 'Archive retention must never fall below 30 days.' );

$pricelist_source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-pricelist.php' );
sidrena_schema_assert( false === strpos( $pricelist_source, 'nedostaje vrsta usluge' ), 'Service type must remain optional in strict NN 101/2026 publication preflight.' );
sidrena_schema_assert( false === strpos( $pricelist_source, 'nedostaje opseg usluge' ), 'Service scope must remain optional in strict NN 101/2026 publication preflight.' );
sidrena_schema_assert( false === strpos( $pricelist_source, "'barkod'              => __( 'barkod'" ), 'Barcode must not block publication when it is not applicable.' );

sidrena_schema_assert( '06:30' === Sidrena_Legal_Automation::normalize_generation_time( '08:00' ), 'Generation at 08:00 or later must be clamped before the publication deadline.' );
sidrena_schema_assert( '06:30' === Sidrena_Legal_Automation::normalize_generation_time( '09:15' ), 'Generation after the publication deadline must be clamped.' );
sidrena_schema_assert( '07:59' === Sidrena_Legal_Automation::normalize_generation_time( '7:59' ), 'Generation before 08:00 must be accepted and normalized.' );
sidrena_schema_assert( '06:30' === Sidrena_Legal_Automation::normalize_generation_time( 'not-a-time' ), 'Invalid generation time must fall back to the safe default.' );
sidrena_schema_assert( '08:00' === Sidrena_Legal_Automation::publication_deadline(), 'Publication deadline marker must remain 08:00.' );

$hardened = Sidrena_Legal_Automation::normalize_settings(
	array(
		'generation_time'       => '12:15',
		'retention_days'        => 7,
		'generate_csv'          => 'no',
		'generate_xml'          => 'no',
		'enable_public_html'    => 'no',
		'publish_manifest'      => 'no',
		'strict_publication'    => 'no',
		'failure_notifications' => 'no',
	)
);
sidrena_schema_assert( '06:30' === $hardened['generation_time'], 'Unsafe generation time was not automatically hardened.' );
sidrena_schema_assert( 30 === $hardened['retention_days'], 'Archive retention must be hardened to at least 30 days.' );
foreach ( Sidrena_Legal_Automation::required_publication_flags() as $required_flag ) {
	sidrena_schema_assert( 'yes' === $hardened[ $required_flag ], 'Required publication safety flag not hardened: ' . $required_flag );
}
sidrena_schema_assert( 'yes' === $hardened['generate_csv'], 'At least one machine-readable format must be enabled when both CSV and XML are disabled.' );
sidrena_schema_assert( 'no' === $hardened['generate_xml'], 'XML must remain a user choice when CSV already satisfies the machine-readable publication requirement.' );
sidrena_schema_assert( 'no' === $hardened['enable_public_html'], 'Public HTML is optional and must not be forced by legal automation.' );
sidrena_schema_assert( 'no' === $hardened['publish_manifest'], 'Manifest publication is optional and must not be forced by legal automation.' );

fwrite( STDOUT, "Sidrena NN 101/2026 + NN 105/2026 schema and automation smoke test passed.\n" );
