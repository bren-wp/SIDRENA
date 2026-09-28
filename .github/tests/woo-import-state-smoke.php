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

function wp_strip_all_tags( $value ) {
	return strip_tags( (string) $value );
}

class WC_Product {
	private $meta = array();

	public function __construct( $meta = array() ) {
		$this->meta = is_array( $meta ) ? $meta : array();
	}

	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value;
	}

	public function delete_meta_data( $key ) {
		unset( $this->meta[ $key ] );
	}

	public function get_meta( $key, $single = true ) {
		unset( $single );
		return array_key_exists( $key, $this->meta ) ? $this->meta[ $key ] : '';
	}
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php';
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-woo-import-export.php';

function sidrena_woo_import_state_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

$method = new ReflectionMethod( 'Sidrena_Woo_Import_Export', 'set_quantity' );
$method->setAccessible( true );
$importer = Sidrena_Woo_Import_Export::instance();

$product = new WC_Product(
	array(
		'_sidrena_quantity'      => '750',
		'_sidrena_quantity_unit' => 'g',
	)
);
$method->invoke(
	$importer,
	$product,
	array(
		'sidrena_kolicina_pakiranja' => '',
		'sidrena_jedinica_pakiranja' => '',
	)
);
sidrena_woo_import_state_assert( '' === $product->get_meta( '_sidrena_quantity' ), 'Blank imported package quantity must clear stale quantity metadata.' );
sidrena_woo_import_state_assert( '' === $product->get_meta( '_sidrena_quantity_unit' ), 'Blank imported package unit must clear stale unit metadata.' );

$product = new WC_Product();
$method->invoke(
	$importer,
	$product,
	array(
		'sidrena_kolicina_pakiranja' => '750 g',
	)
);
sidrena_woo_import_state_assert( '750' === $product->get_meta( '_sidrena_quantity' ), 'Quantity-with-unit import must retain parsed quantity.' );
sidrena_woo_import_state_assert( 'g' === $product->get_meta( '_sidrena_quantity_unit' ), 'Quantity-with-unit import must retain parsed unit.' );

$product = new WC_Product( array( '_sidrena_quantity_unit' => 'g' ) );
$method->invoke(
	$importer,
	$product,
	array(
		'sidrena_jedinica_pakiranja' => 'ML',
	)
);
sidrena_woo_import_state_assert( 'ml' === $product->get_meta( '_sidrena_quantity_unit' ), 'Explicit package unit import must normalize and replace stale unit metadata.' );

$decimal_method = new ReflectionMethod( 'Sidrena_Woo_Import_Export', 'set_decimal' );
$decimal_method->setAccessible( true );

$product = new WC_Product( array( '_sidrena_anchor_price' => '19.99' ) );
$decimal_method->invoke( $importer, $product, '_sidrena_anchor_price', array( 'sidrena_cijena' => 'abc' ), 'sidrena_cijena' );
sidrena_woo_import_state_assert( '19.99' === $product->get_meta( '_sidrena_anchor_price' ), 'Malformed Woo CSV price must preserve existing metadata.' );
$decimal_method->invoke( $importer, $product, '_sidrena_anchor_price', array( 'sidrena_cijena' => '-1' ), 'sidrena_cijena' );
sidrena_woo_import_state_assert( '19.99' === $product->get_meta( '_sidrena_anchor_price' ), 'Negative Woo CSV price must preserve existing metadata.' );
$decimal_method->invoke( $importer, $product, '_sidrena_anchor_price', array( 'sidrena_cijena' => '' ), 'sidrena_cijena' );
sidrena_woo_import_state_assert( '' === $product->get_meta( '_sidrena_anchor_price' ), 'Explicitly blank Woo CSV price must still clear stale metadata.' );

$product = new WC_Product( array( '_sidrena_quantity' => '500' ) );
$method->invoke( $importer, $product, array( 'sidrena_kolicina_pakiranja' => 'abc' ) );
sidrena_woo_import_state_assert( '500' === $product->get_meta( '_sidrena_quantity' ), 'Malformed Woo CSV package quantity must preserve existing metadata.' );
$method->invoke( $importer, $product, array( 'sidrena_kolicina_pakiranja' => '-2' ) );
sidrena_woo_import_state_assert( '500' === $product->get_meta( '_sidrena_quantity' ), 'Negative Woo CSV package quantity must preserve existing metadata.' );
$method->invoke( $importer, $product, array( 'sidrena_kolicina_pakiranja' => '0' ) );
sidrena_woo_import_state_assert( '500' === $product->get_meta( '_sidrena_quantity' ), 'Zero Woo CSV package quantity must preserve existing metadata.' );

fwrite( STDOUT, "Sidrena Woo import state smoke test passed.\n" );
