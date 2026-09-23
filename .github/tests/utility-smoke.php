<?php
define( 'ABSPATH', __DIR__ . '/' );

function wp_strip_all_tags( $value ) {
	return strip_tags( (string) $value );
}

function wp_check_invalid_utf8( $value, $strip = false ) {
	unset( $strip );
	return (string) $value;
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php';
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-pricelist.php';

function sidrena_assert_same( $expected, $actual, $label ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, $label . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

sidrena_assert_same(
	'Ulica Ivana Meštrovića 12, Čakovec',
	Sidrena_Utils::filename_part( 'Ulica Ivana Meštrovića 12, Čakovec' ),
	'Croatian filename characters must be preserved.'
);

sidrena_assert_same( "'=HYPERLINK(\"https://example.test\")", Sidrena_Utils::csv_safe_cell( '=HYPERLINK("https://example.test")' ), 'Formula prefix must be neutralized.' );
sidrena_assert_same( "' \t=1+1", Sidrena_Utils::csv_safe_cell( " \t=1+1" ), 'Whitespace-prefixed formula must be neutralized.' );
sidrena_assert_same( '-12,50', Sidrena_Utils::csv_safe_cell( '-12,50' ), 'Negative numeric values must stay numeric.' );

$croatian = 'Željko Šarić, Čakovec';
$cp1250   = "\x8Eeljko \x8Aari\xE6, \xC8akovec";
$iso88592 = "\xAEeljko \xA9ari\xE6, \xC8akovec";
sidrena_assert_same( $croatian, Sidrena_Utils::normalize_text_encoding( $cp1250 ), 'Windows-1250 conversion failed.' );
sidrena_assert_same( $croatian, Sidrena_Utils::normalize_text_encoding( $iso88592 ), 'ISO-8859-2 conversion failed.' );

$method = new ReflectionMethod( 'Sidrena_Pricelist', 'build_filename' );
$method->setAccessible( true );
$filename = $method->invoke(
	Sidrena_Pricelist::instance(),
	array(
		'kind'    => 'prodavaonica',
		'address' => 'Ulica Ivana Meštrovića 12, Čakovec',
		'code'    => 'P-01',
	),
	104,
	'01.10.2026_07-45',
	'csv'
);
sidrena_assert_same(
	'prodavaonica_Ulica Ivana Meštrovića 12, Čakovec_P-01_000104_01.10.2026_07-45.csv',
	$filename,
	'Pricelist filename format changed unexpectedly.'
);




sidrena_assert_same( 'naziv_proizvoda', Sidrena_Utils::import_header_key( 'NAZIV PROIZVODA' ), 'Croatian CSV header normalization failed.' );
sidrena_assert_same( 'sidrena_cijena_na_10_09_2026', Sidrena_Utils::import_header_key( 'SIDRENA CIJENA NA 10.09.2026.' ), 'Dated anchor header normalization failed.' );

$quantity = Sidrena_Utils::parse_quantity_with_unit( '750 g' );
sidrena_assert_same( '750', $quantity['quantity'] ?? '', 'Package quantity parsing failed.' );
sidrena_assert_same( 'g', $quantity['unit'] ?? '', 'Package unit parsing failed.' );

$unit_price = Sidrena_Utils::calculate_unit_price( '3,75', '750', 'g' );
sidrena_assert_same( 'kg', $unit_price['unit'] ?? '', 'Base unit calculation failed.' );
sidrena_assert_same( '5', $unit_price['unit_price'] ?? '', 'Unit price calculation failed.' );

sidrena_assert_same( '1234.56', Sidrena_Utils::decimal( '1.234,56' ), 'Croatian thousands/decimal parsing failed.' );
sidrena_assert_same( '1234.56', Sidrena_Utils::decimal( '1,234.56' ), 'International thousands/decimal parsing failed.' );
sidrena_assert_same( '', Sidrena_Utils::decimal( '1e9999' ), 'Non-finite numeric values must be rejected.' );

fwrite( STDOUT, "Sidrena utility smoke tests passed.\n" );
