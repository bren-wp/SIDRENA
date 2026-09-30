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
define( 'MB_IN_BYTES', 1048576 );

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
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_filesize( $path ) { return filesize( $path ); }

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

$standalone_upload_size = new ReflectionMethod( 'Sidrena_Standalone', 'validate_uploaded_catalog_size' );
$standalone_upload_size->setAccessible( true );
$standalone_fixture = tempnam( sys_get_temp_dir(), 'sidrena-standalone-upload-' );
file_put_contents( $standalone_fixture, "sku;price\nA;1\n" );
$standalone_actual = $standalone_upload_size->invoke( $standalone, $standalone_fixture, 1 );
sidrena_import_stream_assert( is_int( $standalone_actual ) && $standalone_actual === filesize( $standalone_fixture ), 'Standalone upload validation must use the server-side temp file size.' );
file_put_contents( $standalone_fixture, str_repeat( 'x', ( 5 * MB_IN_BYTES ) + 1 ) );
$standalone_oversize = $standalone_upload_size->invoke( $standalone, $standalone_fixture, 1 );
sidrena_import_stream_assert( is_wp_error( $standalone_oversize ) && 'upload_too_large' === $standalone_oversize->code, 'Standalone import must reject an oversized server-side temp file even when reported metadata is smaller.' );
file_put_contents( $standalone_fixture, '' );
$standalone_empty = $standalone_upload_size->invoke( $standalone, $standalone_fixture, 0 );
sidrena_import_stream_assert( is_wp_error( $standalone_empty ) && 'upload_empty' === $standalone_empty->code, 'Standalone import must reject an empty server-side temp file before loading it into memory.' );
unlink( $standalone_fixture );

$prepare_csv = new ReflectionMethod( 'Sidrena_Standalone', 'prepare_csv_import_file_stream' );
$prepare_csv->setAccessible( true );
$iterate_csv = new ReflectionMethod( 'Sidrena_Standalone', 'iterate_csv_import_rows' );
$iterate_csv->setAccessible( true );

$csv_fixture = tempnam( sys_get_temp_dir(), 'sidrena-standalone-csv-' );
file_put_contents( $csv_fixture, "šifra;cijena\nA1;10,50\nA2;11,50\n" );
$prepared = $prepare_csv->invoke( $standalone, $csv_fixture, 10 );
sidrena_import_stream_assert( is_array( $prepared ) && 2 === $prepared[3], 'Standalone CSV preflight must count rows directly from the uploaded file stream.' );
$csv_rows = iterator_to_array( $iterate_csv->invoke( $standalone, $prepared[0], $prepared[1], $prepared[2] ), false );
sidrena_import_stream_assert( 2 === count( $csv_rows ), 'Standalone CSV iterator must stream data rows.' );
sidrena_import_stream_assert( 'A1' === $csv_rows[0]['sifra'], 'Standalone CSV header normalization failed.' );
fclose( $prepared[0] );

file_put_contents( $csv_fixture, "sku;price\nA;1\nB;2\nC;3\n" );
$too_many = $prepare_csv->invoke( $standalone, $csv_fixture, 2 );
sidrena_import_stream_assert( is_wp_error( $too_many ) && 'csv_row_limit' === $too_many->code, 'Standalone CSV must reject an over-limit file before import.' );

file_put_contents( $csv_fixture, "sku;price\nA;1\0bad\n" );
$binary_csv = $prepare_csv->invoke( $standalone, $csv_fixture, 10 );
sidrena_import_stream_assert( is_wp_error( $binary_csv ) && 'csv_binary' === $binary_csv->code, 'Standalone CSV preflight must reject NUL/binary content while streaming.' );
unlink( $csv_fixture );

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

$validate_upload_size = new ReflectionMethod( 'Sidrena_Admin', 'validate_uploaded_csv_size' );
$validate_upload_size->setAccessible( true );
$upload_fixture = tempnam( sys_get_temp_dir(), 'sidrena-upload-' );
file_put_contents( $upload_fixture, "sku;price\nA;1\n" );
$upload_size = $validate_upload_size->invoke( $admin, $upload_fixture, 1 );
sidrena_import_stream_assert( is_int( $upload_size ) && $upload_size === filesize( $upload_fixture ), 'Woo CSV upload size validation must use the server-side temp file size.' );
$reported_too_large = $validate_upload_size->invoke( $admin, $upload_fixture, ( 5 * MB_IN_BYTES ) + 1 );
sidrena_import_stream_assert( is_wp_error( $reported_too_large ) && 'upload_too_large' === $reported_too_large->code, 'Woo CSV upload must reject oversized reported upload metadata.' );
file_put_contents( $upload_fixture, str_repeat( 'x', ( 5 * MB_IN_BYTES ) + 1 ) );
$actual_too_large = $validate_upload_size->invoke( $admin, $upload_fixture, 1 );
sidrena_import_stream_assert( is_wp_error( $actual_too_large ) && 'upload_too_large' === $actual_too_large->code, 'Woo CSV upload must reject an oversized server-side temp file even when reported metadata is smaller.' );
file_put_contents( $upload_fixture, '' );
$empty_upload = $validate_upload_size->invoke( $admin, $upload_fixture, 0 );
sidrena_import_stream_assert( is_wp_error( $empty_upload ) && 'upload_empty' === $empty_upload->code, 'Woo CSV upload must reject an empty server-side temp file before reading it into memory.' );
unlink( $upload_fixture );

$prepare_admin_csv = new ReflectionMethod( 'Sidrena_Admin', 'prepare_uploaded_csv_stream' );
$prepare_admin_csv->setAccessible( true );
$admin_csv_fixture = tempnam( sys_get_temp_dir(), 'sidrena-admin-csv-' );
file_put_contents( $admin_csv_fixture, "sku;price\nA;1\nB;2\n" );
$admin_prepared = $prepare_admin_csv->invoke( $admin, $admin_csv_fixture, 10 );
sidrena_import_stream_assert( is_array( $admin_prepared ) && 'sku' === array_key_first( $admin_prepared[2] ), 'Woo admin CSV import must prepare the validated upload file directly.' );
$admin_first_row = Sidrena_Utils::csv_read_row( $admin_prepared[0], $admin_prepared[1] );
sidrena_import_stream_assert( is_array( $admin_first_row ) && 'A' === $admin_first_row[0], 'Woo admin CSV direct stream must remain positioned at the first data row.' );
fclose( $admin_prepared[0] );

file_put_contents( $admin_csv_fixture, "sku;price\nA;1\nB;2\nC;3\n" );
$admin_too_many = $prepare_admin_csv->invoke( $admin, $admin_csv_fixture, 2 );
sidrena_import_stream_assert( is_wp_error( $admin_too_many ) && 'upload_row_limit' === $admin_too_many->code, 'Woo admin direct CSV stream must enforce the pre-import row bound.' );

file_put_contents( $admin_csv_fixture, "sku;price\nA;1\0bad\n" );
$admin_binary = $prepare_admin_csv->invoke( $admin, $admin_csv_fixture, 10 );
sidrena_import_stream_assert( is_wp_error( $admin_binary ) && 'upload_binary' === $admin_binary->code, 'Woo admin direct CSV stream must reject NUL/binary content.' );
unlink( $admin_csv_fixture );

$roundtrip = fopen( 'php://temp', 'w+b' );
sidrena_import_stream_assert( is_resource( $roundtrip ), 'CSV dialect roundtrip stream must be available.' );
$roundtrip_fields = array( 'sku', 'A;1', 'Naziv "test"' );
sidrena_import_stream_assert( false !== Sidrena_Utils::csv_write_row( $roundtrip, $roundtrip_fields, ';' ), 'Central CSV writer must write the explicit SIDRENA dialect.' );
rewind( $roundtrip );
sidrena_import_stream_assert( $roundtrip_fields === Sidrena_Utils::csv_read_row( $roundtrip, ';' ), 'Central CSV reader/writer dialect must roundtrip quoted fields.' );
fclose( $roundtrip );

$validate_price = new ReflectionMethod( 'Sidrena_Admin', 'validated_import_price' );
$validate_price->setAccessible( true );
sidrena_import_stream_assert( '1234.56' === $validate_price->invoke( $admin, '1.234,56' ), 'Woo financial CSV validation must preserve a valid Croatian decimal.' );
sidrena_import_stream_assert( '' === $validate_price->invoke( $admin, '' ), 'Woo financial CSV validation must preserve an intentionally blank value.' );
sidrena_import_stream_assert( null === $validate_price->invoke( $admin, 'abc' ), 'Woo financial CSV validation must reject malformed numeric text instead of treating it as an empty value.' );
sidrena_import_stream_assert( null === $validate_price->invoke( $admin, '-1,00' ), 'Woo financial CSV validation must reject negative retail/reference prices.' );

$limit = new ReflectionMethod( 'Sidrena_Admin', 'enforce_csv_row_limit' );
$limit->setAccessible( true );

$resource = fopen( 'php://temp', 'w+b' );
fwrite( $resource, "sku;price\nA;1\nB;2\nC;3\n" );
rewind( $resource );
Sidrena_Utils::csv_read_row( $resource, ';' );
$limit_result = $limit->invoke( $admin, $resource, ';', 2 );
sidrena_import_stream_assert( is_wp_error( $limit_result ) && 'upload_row_limit' === $limit_result->code, 'Woo admin CSV must reject over-limit input before processing.' );
fclose( $resource );

$resource = fopen( 'php://temp', 'w+b' );
fwrite( $resource, "sku;price\nA;1\nB;2\n" );
rewind( $resource );
Sidrena_Utils::csv_read_row( $resource, ';' );
$limit_result = $limit->invoke( $admin, $resource, ';', 10 );
sidrena_import_stream_assert( 2 === $limit_result, 'Woo admin CSV preflight row count is incorrect.' );
$first_data = Sidrena_Utils::csv_read_row( $resource, ';' );
sidrena_import_stream_assert( 'A' === $first_data[0], 'Woo admin CSV preflight must rewind to the first data row.' );
fclose( $resource );

$standalone_source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-standalone.php' );
$admin_source      = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-admin.php' );
$utils_source      = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php' );
$pricelist_source  = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-pricelist.php' );
sidrena_import_stream_assert( false === strpos( $standalone_source, 'private function parse_csv_rows(' ), 'Legacy array-accumulating CSV parser must be removed.' );
sidrena_import_stream_assert( false === strpos( $standalone_source, 'private function parse_xml_rows(' ), 'Legacy array-accumulating XML parser must be removed.' );
sidrena_import_stream_assert( false === strpos( $standalone_source, 'private function code_index()' ), 'Standalone imports must not rebuild an unbounded whole-catalog code index in memory.' );
sidrena_import_stream_assert( false !== strpos( $standalone_source, 'private function code_index_for_codes( $codes )' ), 'Standalone imports must use bounded code lookups.' );
sidrena_import_stream_assert( false !== strpos( $standalone_source, 'if ( count( $chunk ) >= 250 )' ), 'Standalone imports must process rows in bounded chunks.' );
sidrena_import_stream_assert( false !== strpos( $standalone_source, 'if ( count( $keys ) >= 500 )' ), 'Standalone code lookup must retain a defensive lookup-key bound.' );
sidrena_import_stream_assert( false !== strpos( $standalone_source, 'private function prepare_csv_import_file_stream( $tmp_name, $row_limit = 50000 )' ), 'Standalone CSV import must prepare a direct uploaded-file stream.' );
sidrena_import_stream_assert( false !== strpos( $standalone_source, "fopen( \$tmp_name, 'rb' )" ), 'Standalone CSV import must open the validated upload temp file directly.' );
sidrena_import_stream_assert( false === strpos( $standalone_source, 'private function prepare_csv_import_stream( $contents' ), 'Standalone CSV import must not restore the full-buffer php://temp copy helper.' );
sidrena_import_stream_assert( false !== strpos( $standalone_source, "Sidrena_Utils::normalize_text_encoding( \$value )" ), 'Standalone streamed CSV cells must retain legacy text-encoding normalization.' );
sidrena_import_stream_assert( false !== strpos( $standalone_source, 'FROM %i p' ), 'Standalone code index must prepare the posts table identifier.' );
sidrena_import_stream_assert( false !== strpos( $standalone_source, 'INNER JOIN %i pm' ), 'Standalone code index must prepare the postmeta table identifier.' );
sidrena_import_stream_assert( false === strpos( $standalone_source, 'FROM {$wpdb->posts} p' ), 'Standalone code index must not interpolate the posts table identifier.' );
sidrena_import_stream_assert( false === strpos( $admin_source, 'if ( $processed > 50000 )' ), 'Woo imports must not partially import then silently stop at the row limit.' );
sidrena_import_stream_assert( false !== strpos( $admin_source, 'private function prepare_uploaded_csv_stream( $tmp_name, $row_limit = 50000 )' ), 'Woo admin CSV import must prepare the validated upload file as a direct stream.' );
sidrena_import_stream_assert( false !== strpos( $admin_source, "fopen( \$tmp_name, 'rb' )" ), 'Woo admin CSV import must open the validated upload temp file directly.' );
sidrena_import_stream_assert( false === strpos( $admin_source, "fopen( 'php://temp/maxmemory:1048576', 'w+b' )" ), 'Woo admin CSV import must not copy the complete upload into a second temp stream.' );
sidrena_import_stream_assert( false === strpos( $admin_source, "file_get_contents( \$file['tmp_name'] )" ), 'Woo admin CSV import must not read the complete upload into memory.' );
sidrena_import_stream_assert( false === strpos( $admin_source, 'private function write_stream_all( $stream, $contents )' ), 'Retired Woo full-buffer stream-copy helper must remain removed.' );
sidrena_import_stream_assert( 0 === preg_match( '/(?<![A-Za-z0-9_])fgetcsv\\s*\\(/', $standalone_source . $admin_source ), 'Production importers must use the centralized explicit CSV reader.' );
sidrena_import_stream_assert( 0 === preg_match( '/(?<![A-Za-z0-9_])fputcsv\\s*\\(/', $admin_source . $pricelist_source ), 'Production CSV exporters must use the centralized explicit CSV writer.' );
sidrena_import_stream_assert(
	1 === preg_match( '/return fgetcsv\\(([^;]+)\\);/', $utils_source, $csv_read_call )
	&& 4 <= substr_count( $csv_read_call[1], ',' ),
	'Central CSV reader must pass delimiter, enclosure and escape explicitly for PHP 8.4 compatibility.'
);
sidrena_import_stream_assert(
	1 === preg_match( '/return fputcsv\\(([^;]+)\\);/', $utils_source, $csv_write_call )
	&& 4 <= substr_count( $csv_write_call[1], ',' ),
	'Central CSV writer must pass delimiter, enclosure and escape explicitly for PHP 8.4 compatibility.'
);

fwrite( STDOUT, "Sidrena streaming import smoke test passed.\n" );
