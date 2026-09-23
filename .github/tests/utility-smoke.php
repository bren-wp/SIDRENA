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

fwrite( STDOUT, "Sidrena utility smoke tests passed.\n" );
