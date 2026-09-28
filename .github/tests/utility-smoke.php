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
define( 'SIDRENA_EDITION', 'woocommerce' );

function get_option( $key, $default = false ) {
	$values = array(
		'woocommerce_store_address' => 'Korzo 1',
		'woocommerce_store_address_2' => '2. kat',
		'woocommerce_store_postcode' => '51000',
		'woocommerce_store_city' => 'Rijeka',
	);
	return array_key_exists( $key, $values ) ? $values[ $key ] : $default;
}

$GLOBALS['sidrena_test_filters'] = array();
function apply_filters( $tag, $value ) {
	if ( isset( $GLOBALS['sidrena_test_filters'][ $tag ] ) && is_callable( $GLOBALS['sidrena_test_filters'][ $tag ] ) ) {
		return call_user_func( $GLOBALS['sidrena_test_filters'][ $tag ], $value );
	}
	return $value;
}

function wp_strip_all_tags( $value ) {
	return strip_tags( (string) $value );
}

function wp_check_invalid_utf8( $value, $strip = false ) {
	unset( $strip );
	return (string) $value;
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_json_encode( $value, $flags = 0 ) {
	return json_encode( $value, $flags );
}

function sanitize_key( $value ) {
	$value = strtolower( (string) $value );
	return preg_replace( '/[^a-z0-9_\-]/', '', $value );
}

function sanitize_textarea_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function wp_timezone() { return new DateTimeZone( 'Europe/Zagreb' ); }

function esc_url_raw( $value ) {
	return filter_var( (string) $value, FILTER_SANITIZE_URL );
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php';
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-pricelist.php';
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-audit.php';

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

sidrena_assert_same(
	'Korzo 1, 2. kat, 51000 Rijeka',
	Sidrena_Utils::woocommerce_store_address(),
	'WooCommerce store address suggestion must combine only trusted configured address fields.'
);

$default_locations = Sidrena_Utils::locations();
sidrena_assert_same( 'Korzo 1, 2. kat, 51000 Rijeka', $default_locations[0]['address'] ?? '', 'Fresh WooCommerce location fallback must use the configured store address suggestion.' );

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

$GLOBALS['sidrena_test_filters']['sidrena_unit_definitions'] = static function ( $units ) {
	$units['oz'] = array( 'base' => 'kg', 'multiplier' => 0.028349523125 );
	$units['bad-negative'] = array( 'base' => 'kg', 'multiplier' => -1 );
	$units['bad-empty-base'] = array( 'base' => '', 'multiplier' => 1 );
	return $units;
};
$GLOBALS['sidrena_test_filters']['sidrena_unit_aliases'] = static function ( $aliases ) {
	$aliases['unca'] = 'oz';
	$aliases['bad alias!'] = '../broken';
	return $aliases;
};

$custom_unit_price = Sidrena_Utils::calculate_unit_price( '10', '2', 'unca' );
sidrena_assert_same( 'kg', $custom_unit_price['unit'] ?? '', 'Custom unit alias must resolve to the filtered unit definition.' );
sidrena_assert_same( '176.3698', $custom_unit_price['unit_price'] ?? '', 'Custom unit conversion produced an unexpected price.' );
$custom_units = Sidrena_Utils::unit_definitions();
sidrena_assert_same( true, isset( $custom_units['oz'] ), 'Validated custom unit definition must be retained.' );
sidrena_assert_same( false, isset( $custom_units['bad-negative'] ), 'Negative unit multipliers must be rejected.' );
sidrena_assert_same( false, isset( $custom_units['bad-empty-base'] ), 'Unit definitions without a base unit must be rejected.' );
$custom_aliases = Sidrena_Utils::unit_aliases();
sidrena_assert_same( 'oz', $custom_aliases['unca'] ?? '', 'Validated custom unit alias must be retained.' );
sidrena_assert_same( false, isset( $custom_aliases['bad alias!'] ), 'Unsafe unit alias keys must be rejected.' );

$GLOBALS['sidrena_test_filters'] = array();

sidrena_assert_same( '1234.56', Sidrena_Utils::decimal( '1.234,56' ), 'Croatian thousands/decimal parsing failed.' );
sidrena_assert_same( '1234.56', Sidrena_Utils::decimal( '1,234.56' ), 'International thousands/decimal parsing failed.' );
sidrena_assert_same( '', Sidrena_Utils::decimal( '1e9999' ), 'Non-finite numeric values must be rejected.' );


$decimal_cases = array(
	'-100' => null,
	'-1' => null,
	'-0.01' => null,
	'0' => '0',
	'0.00' => '0',
	'1' => '1',
	'1.25' => '1.25',
	'1,25' => '1.25',
	'abc' => null,
	'' => '',
	'999999999999999999999999999999' => null,
	'1e3' => null,
	'1E3' => null,
);
foreach ( $decimal_cases as $input => $expected ) {
	sidrena_assert_same( $expected, Sidrena_Utils::validated_nonnegative_decimal( $input ), 'Nonnegative decimal validation failed for: ' . var_export( $input, true ) );
}

sidrena_assert_same( '2026-09-28', Sidrena_Utils::sanitize_date( '2026-09-28' ), 'Valid ISO date was rejected.' );
sidrena_assert_same( '', Sidrena_Utils::sanitize_date( '2026-99-99' ), 'Invalid ISO date must be rejected.' );
sidrena_assert_same( '06:30', Sidrena_Utils::sanitize_time( '06:30' ), 'Valid HH:MM time was rejected.' );
sidrena_assert_same( '', Sidrena_Utils::sanitize_time( '99:99' ), 'Invalid time must be rejected.' );
sidrena_assert_same( '', Sidrena_Utils::sanitize_time( '25:70' ), 'Out-of-range time must be rejected.' );
sidrena_assert_same( '', Sidrena_Utils::sanitize_time( '6:30' ), 'Time must use strict HH:MM format.' );

$public_files = Sidrena_Utils::public_file_index(
	array(
		array(
			'location_id' => 'RIJEKA-CENTAR',
			'location_code' => '<b>RI-C</b>',
			'kind' => 'OBJEKT',
			'catalog' => 'PRODUCTS',
			'format' => 'CSV',
			'url' => 'https://example.test/uploads/sidrena/arhiva/cjenik.csv',
			'filename' => '../cjenik.csv',
			'generated_at' => '2026-09-28T01:00:00+02:00',
			'generated_ts' => 1790550000,
			'retain_until' => '2026-11-12T01:00:00+01:00',
			'retain_until_ts' => 1794438000,
			'sequence' => 44,
			'rows' => '25',
			'bytes' => '2048',
			'sha256' => str_repeat( 'A', 64 ),
		),
	)
);
sidrena_assert_same( 1, count( $public_files ), 'Public file projection must retain valid public entries.' );
sidrena_assert_same( 'cjenik.csv', $public_files[0]['filename'] ?? '', 'Public file projection must strip path components from filenames.' );
sidrena_assert_same( 'RI-C', $public_files[0]['location_code'] ?? '', 'Public file projection must sanitize display text.' );
sidrena_assert_same( str_repeat( 'a', 64 ), $public_files[0]['sha256'] ?? '', 'Public file projection must normalize SHA-256 digests.' );
sidrena_assert_same( false, array_key_exists( 'sequence', $public_files[0] ), 'Internal sequence metadata must not be exposed publicly.' );
sidrena_assert_same( false, array_key_exists( 'generated_ts', $public_files[0] ), 'Internal generation timestamps must not be exposed publicly.' );
sidrena_assert_same( false, array_key_exists( 'retain_until_ts', $public_files[0] ), 'Internal retention timestamps must not be exposed publicly.' );

$trim_method = new ReflectionMethod( 'Sidrena_Audit', 'trim_bytes' );
$trim_method->setAccessible( true );
$trimmed = $trim_method->invoke( null, str_repeat( 'Ž', 30 ), 25 );
sidrena_assert_same( true, strlen( $trimmed ) <= 25, 'Audit byte trimming exceeded the configured byte budget.' );
sidrena_assert_same( 1, preg_match( '//u', $trimmed ), 'Audit byte trimming split a UTF-8 character.' );

$context_method = new ReflectionMethod( 'Sidrena_Audit', 'encode_context' );
$context_method->setAccessible( true );
$encoded_context = $context_method->invoke( null, array( 'payload' => str_repeat( 'čćžšđ', 5000 ) ) );
$decoded_context = json_decode( $encoded_context, true );
sidrena_assert_same( true, is_array( $decoded_context ), 'Truncated audit context must remain valid JSON.' );
sidrena_assert_same( true, true === ( $decoded_context['truncated'] ?? false ), 'Oversized audit context must expose truncation metadata.' );
sidrena_assert_same( true, isset( $decoded_context['original_bytes'] ) && $decoded_context['original_bytes'] > strlen( $encoded_context ), 'Audit truncation metadata must retain the original byte size.' );
sidrena_assert_same( 1, preg_match( '//u', (string) ( $decoded_context['preview'] ?? '' ) ), 'Audit JSON preview must remain valid UTF-8.' );
sidrena_assert_same( true, strlen( $encoded_context ) <= Sidrena_Audit::MAX_CONTEXT_BYTES, 'Truncated audit context exceeded the configured byte budget.' );


$csv_upload = tempnam( sys_get_temp_dir(), 'sidrena-csv-' );
file_put_contents( $csv_upload, "sku;price\nTEST-1;9,99\n" );
sidrena_assert_same(
	true,
	Sidrena_Utils::uploaded_text_type_allowed( $csv_upload, 'catalog.csv', array( 'csv' ) ),
	'Valid text CSV upload must pass MIME/type validation.'
);
sidrena_assert_same(
	false,
	Sidrena_Utils::uploaded_text_type_allowed( $csv_upload, 'catalog.exe', array( 'csv' ) ),
	'Disallowed upload extension must be rejected.'
);
@unlink( $csv_upload );

$binary_upload = tempnam( sys_get_temp_dir(), 'sidrena-bin-' );
file_put_contents( $binary_upload, "\x89PNG\r\n\x1a\n" . str_repeat( "\0", 128 ) );
sidrena_assert_same(
	false,
	Sidrena_Utils::uploaded_text_type_allowed( $binary_upload, 'fake.csv', array( 'csv' ) ),
	'Binary content disguised with a CSV extension must be rejected.'
);
@unlink( $binary_upload );

fwrite( STDOUT, "Sidrena utility smoke tests passed.\n" );
