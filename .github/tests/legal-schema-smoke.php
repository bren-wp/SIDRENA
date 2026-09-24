<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo LTD Developer
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

fwrite( STDOUT, "Sidrena NN 101/2026 schema smoke test passed.\n" );
