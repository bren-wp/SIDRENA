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
sidrena_schema_assert( false !== strpos( $pricelist_source, 'nedostaje vrsta usluge' ), 'Strict service preflight must require service type.' );
sidrena_schema_assert( false !== strpos( $pricelist_source, 'nedostaje opseg usluge' ), 'Strict service preflight must require service scope.' );

fwrite( STDOUT, "Sidrena NN 101/2026 + NN 105/2026 schema smoke test passed.\n" );
