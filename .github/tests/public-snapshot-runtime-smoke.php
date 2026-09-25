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
define( 'SIDRENA_VERSION', '0.4.0' );
define( 'SIDRENA_EDITION', 'wordpress' );
define( 'SIDRENA_URL', 'https://example.test/wp-content/plugins/sidrena-wordpress/' );

$base = sys_get_temp_dir() . '/sidrena-stream-' . getmypid() . '-' . uniqid();
mkdir( $base, 0777, true );

function wp_upload_dir() {
	global $base;
	return array(
		'basedir' => $base,
		'baseurl' => 'https://example.test/uploads',
	);
}
function trailingslashit( $value ) { return rtrim( (string) $value, '/\\' ) . '/'; }
function sanitize_title( $value ) {
	$value = strtolower( trim( (string) $value ) );
	$value = preg_replace( '/[^a-z0-9_-]+/', '-', $value );
	return trim( (string) $value, '-' );
}
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function remove_accents( $value ) {
	return strtr( (string) $value, array( 'Č'=>'C','Ć'=>'C','Š'=>'S','Ž'=>'Z','Đ'=>'D','č'=>'c','ć'=>'c','š'=>'s','ž'=>'z','đ'=>'d' ) );
}
function absint( $value ) { return abs( (int) $value ); }

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php';
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-public.php';

function sidrena_stream_runtime_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$path = Sidrena_Utils::public_snapshot_path( 'lokacija-1' );
mkdir( dirname( $path ), 0777, true );

$header = array(
	'schema'       => 2,
	'format'       => 'jsonl',
	'generator'    => 'Sidrena 0.4.0',
	'generated_at' => '2026-09-24T17:00:00+02:00',
	'location'     => array(
		'id'      => 'lokacija-1',
		'code'    => 'L1',
		'kind'    => 'trgovina',
		'address' => 'Test 1',
	),
);
$handle = fopen( $path, 'wb' );
fwrite( $handle, json_encode( $header, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
for ( $i = 1; $i <= 120; $i++ ) {
	$row = array(
		'type'                 => 'product',
		'naziv'                => 'Proizvod ' . $i,
		'sifra'                => 'S' . str_pad( (string) $i, 4, '0', STR_PAD_LEFT ),
		'marka'                => 'Brend ' . ( $i % 7 ),
		'maloprodajna_cijena'  => number_format( 1 + ( $i / 10 ), 2, '.', '' ),
		'sidrena_cijena'       => number_format( 2 + ( $i / 10 ), 2, '.', '' ),
		'barkod'               => '3850000' . str_pad( (string) $i, 6, '0', STR_PAD_LEFT ),
		'dostupnost'           => 'dostupno',
	);
	fwrite( $handle, json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
}
fclose( $handle );

$method = new ReflectionMethod( 'Sidrena_Public', 'read_snapshot_page' );
$method->setAccessible( true );
$public = Sidrena_Public::instance();

$page2 = $method->invoke( $public, 'lokacija-1', '', 2, 50 );
sidrena_stream_runtime_assert( 120 === $page2['total'], 'Streaming reader must count all rows.' );
sidrena_stream_runtime_assert( 2 === $page2['page'], 'Streaming reader must return requested valid page.' );
sidrena_stream_runtime_assert( 3 === $page2['total_pages'], 'Streaming reader total page count is incorrect.' );
sidrena_stream_runtime_assert( 50 === count( $page2['rows'] ), 'Streaming reader page size is incorrect.' );
sidrena_stream_runtime_assert( 'Proizvod 51' === $page2['rows'][0]['naziv'], 'Streaming reader page offset is incorrect.' );

$bounded = $method->invoke( $public, 'lokacija-1', '', 1, 500 );
sidrena_stream_runtime_assert( 100 === count( $bounded['rows'] ), 'Streaming reader must cap page size at 100.' );
sidrena_stream_runtime_assert( 100 === $bounded['per_page'], 'Streaming reader must report capped page size.' );

$search = $method->invoke( $public, 'lokacija-1', '3850000000077', 1, 50 );
sidrena_stream_runtime_assert( 1 === $search['total'], 'Streaming search must match the complete snapshot, not only the current page.' );
sidrena_stream_runtime_assert( 'Proizvod 77' === $search['rows'][0]['naziv'], 'Streaming search returned the wrong row.' );

$clamped = $method->invoke( $public, 'lokacija-1', '', 999, 50 );
sidrena_stream_runtime_assert( 3 === $clamped['page'], 'Out-of-range public page must clamp to the final page.' );
sidrena_stream_runtime_assert( 20 === count( $clamped['rows'] ), 'Clamped final page must contain the remaining rows.' );

unlink( $path );
rmdir( dirname( $path ) );
rmdir( dirname( dirname( $path ) ) );
rmdir( $base );

fwrite( STDOUT, "Sidrena streaming public snapshot runtime test passed.\n" );
