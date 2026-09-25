<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://sidrene-cijene.com.hr/
 * @see https://brendigo.com/
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	public $code;
	public function __construct( $code = '' ) { $this->code = $code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $text, $domain = null ) { unset( $domain ); return $text; }
function absint( $value ) { return abs( (int) $value ); }
function remove_accents( $value ) {
	return strtr( (string) $value, array( 'Č'=>'C','Ć'=>'C','Š'=>'S','Ž'=>'Z','Đ'=>'D','č'=>'c','ć'=>'c','š'=>'s','ž'=>'z','đ'=>'d' ) );
}
function wp_check_invalid_utf8( $value, $strip = false ) { unset( $strip ); return (string) $value; }

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php';
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-standalone.php';
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-admin.php';

function sidrena_import_stream_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$standalone = Sidrena_Standalone::instance();

$prepare_csv = new ReflectionMethod( 'Sidrena_Standalone', 'prepare_csv_import_stream' );
$prepare_csv->setAccessible( true );
$iterate_csv = new ReflectionMethod( 'Sidrena_Standalone', 'iterate_csv_import_rows' );
$iterate_csv->setAccessible( true );

$prepared = $prepare_csv->invoke( $standalone, "šifra;cijena\nA1;10,50\nA2;11,50\n", 10 );
sidrena_import_stream_assert( is_array( $prepared ) && 2 === $prepared[3], 'Standalone CSV preflight must count rows before import.' );
$csv_rows = iterator_to_array( $iterate_csv->invoke( $standalone, $prepared[0], $prepared[1], $prepared[2] ), false );
sidrena_import_stream_assert( 2 === count( $csv_rows ), 'Standalone CSV iterator must stream data rows.' );
sidrena_import_stream_assert( 'A1' === $csv_rows[0]['sifra'], 'Standalone CSV header normalization failed.' );
fclose( $prepared[0] );

$too_many = $prepare_csv->invoke( $standalone, "sku;price\nA;1\nB;2\nC;3\n", 2 );
sidrena_import_stream_assert( is_wp_error( $too_many ) && 'csv_row_limit' === $too_many->code, 'Standalone CSV must reject an over-limit file before import.' );

$validate_xml = new ReflectionMethod( 'Sidrena_Standalone', 'validate_xml_import' );
$validate_xml->setAccessible( true );
$iterate_xml = new ReflectionMethod( 'Sidrena_Standalone', 'iterate_xml_import_rows' );
$iterate_xml->setAccessible( true );

if ( function_exists( 'simplexml_load_string' ) ) {
	$xml = '<proizvodi><proizvod><sifra>A1</sifra><cijena>10,50</cijena></proizvod><proizvod><sifra>A2</sifra><cijena>11,50</cijena></proizvod></proizvodi>';
	$xml_count = $validate_xml->invoke( $standalone, $xml, 10 );
	sidrena_import_stream_assert( 2 === $xml_count, 'Standalone XML preflight must count records.' );
	$xml_rows = iterator_to_array( $iterate_xml->invoke( $standalone, $xml ), false );
	sidrena_import_stream_assert( 2 === count( $xml_rows ) && 'A2' === $xml_rows[1]['sifra'], 'Standalone XML iterator must stream normalized rows.' );
	$unsafe = $validate_xml->invoke( $standalone, '<!DOCTYPE x [<!ENTITY e "x">]><proizvodi/>', 10 );
	sidrena_import_stream_assert( is_wp_error( $unsafe ) && 'xml_unsafe' === $unsafe->code, 'Standalone XML must reject DOCTYPE/ENTITY input.' );
}

$admin = Sidrena_Admin::instance();
$limit = new ReflectionMethod( 'Sidrena_Admin', 'enforce_csv_row_limit' );
$limit->setAccessible( true );

$resource = fopen( 'php://temp', 'w+b' );
fwrite( $resource, "sku;price\nA;1\nB;2\nC;3\n" );
rewind( $resource );
fgetcsv( $resource, 0, ';' );
$limit_result = $limit->invoke( $admin, $resource, ';', 2 );
sidrena_import_stream_assert( is_wp_error( $limit_result ) && 'upload_row_limit' === $limit_result->code, 'Woo admin CSV must reject over-limit input before processing.' );
fclose( $resource );

$resource = fopen( 'php://temp', 'w+b' );
fwrite( $resource, "sku;price\nA;1\nB;2\n" );
rewind( $resource );
fgetcsv( $resource, 0, ';' );
$limit_result = $limit->invoke( $admin, $resource, ';', 10 );
sidrena_import_stream_assert( 2 === $limit_result, 'Woo admin CSV preflight row count is incorrect.' );
$first_data = fgetcsv( $resource, 0, ';' );
sidrena_import_stream_assert( 'A' === $first_data[0], 'Woo admin CSV preflight must rewind to the first data row.' );
fclose( $resource );

$standalone_source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-standalone.php' );
$admin_source      = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-admin.php' );
sidrena_import_stream_assert( false === strpos( $standalone_source, 'private function parse_csv_rows(' ), 'Legacy array-accumulating CSV parser must be removed.' );
sidrena_import_stream_assert( false === strpos( $standalone_source, 'private function parse_xml_rows(' ), 'Legacy array-accumulating XML parser must be removed.' );
sidrena_import_stream_assert( false === strpos( $admin_source, 'if ( $processed > 50000 )' ), 'Woo imports must not partially import then silently stop at the row limit.' );

fwrite( STDOUT, "Sidrena streaming import smoke test passed.\n" );
