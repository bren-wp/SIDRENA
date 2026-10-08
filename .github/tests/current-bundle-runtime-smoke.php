<?php
/**
 * Sidrena current public bundle rollback regression.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code, $message ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function __( $value, $domain = 'sidrena' ) {
	unset( $domain );
	return $value;
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_generate_uuid4() { return str_replace( '.', '-', uniqid( 'sidrena-', true ) ); }

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-pricelist.php';

function sidrena_bundle_assert( $passed, $description ) {
	if ( ! $passed ) {
		fwrite( STDERR, $description . PHP_EOL );
		exit( 1 );
	}
}
$base = sys_get_temp_dir() . '/sidrena-bundle-test-' . uniqid();
sidrena_bundle_assert( mkdir( $base, 0700 ), 'Cannot create bundle fixture directory.' );
$csv    = $base . '/aktualni-lokacija-products.csv';
$xml    = $base . '/aktualni-lokacija-products.xml';
$csv_new = $base . '/first-stage';
$xml_new = $base . '/second-stage';
$method = new ReflectionMethod( 'Sidrena_Pricelist', 'publish_current_bundle' );
$method->setAccessible( true );
$engine = Sidrena_Pricelist::instance();

file_put_contents( $csv, "original-csv\n" );
file_put_contents( $csv_new, "new-csv\n" );
sidrena_bundle_assert( mkdir( $xml ), 'Cannot create a deterministic filesystem conflict.' );
file_put_contents( $xml_new, "new-xml\n" );
$result = $method->invoke( $engine, array( $csv => $csv_new, $xml => $xml_new ), array(), array(), time(), false );
sidrena_bundle_assert( is_wp_error( $result ), 'Second-format publication failure must return WP_Error.' );
sidrena_bundle_assert( "original-csv\n" === file_get_contents( $csv ), 'Second-format failure must restore exact previous CSV.' );
sidrena_bundle_assert( ! file_exists( $csv_new ) && ! file_exists( $xml_new ), 'Failure must clean validated staging files.' );
sidrena_bundle_assert( is_dir( $xml ), 'Rollback must never remove unrelated paths.' );
rmdir( $xml );

file_put_contents( $csv_new, "recovered-csv\n" );
file_put_contents( $xml_new, "<cjenik>confirmed</cjenik>\n" );
$result = $method->invoke( $engine, array( $csv => $csv_new, $xml => $xml_new ), array(), array(), time(), false );
sidrena_bundle_assert( true === $result, 'Complete validated bundle must be published.' );
sidrena_bundle_assert( "recovered-csv\n" === file_get_contents( $csv ), 'Successful commit must publish the new CSV.' );
sidrena_bundle_assert( "<cjenik>confirmed</cjenik>\n" === file_get_contents( $xml ), 'Successful commit must publish the matching XML.' );
sidrena_bundle_assert( ! file_exists( $csv_new ) && ! file_exists( $xml_new ), 'Successful commit must consume staging files.' );
sidrena_bundle_assert( array( $csv, $xml ) === array_values( array_filter( glob( $base . '/*' ), 'is_file' ) ), 'No recovery file may remain after a successful commit.' );

file_put_contents( $csv_new, "invalid-new-csv\n" );
$result = $method->invoke( $engine, array( $csv => $csv_new, $xml => $base . '/missing-stage' ), array(), array(), time(), false );
sidrena_bundle_assert( is_wp_error( $result ), 'A missing staged format must fail closed.' );
sidrena_bundle_assert( "recovered-csv\n" === file_get_contents( $csv ), 'Missing staged format must not overwrite the current CSV.' );
sidrena_bundle_assert( "<cjenik>confirmed</cjenik>\n" === file_get_contents( $xml ), 'Missing staged format must leave the current XML untouched.' );

foreach ( glob( $base . '/*' ) as $file ) {
	unlink( $file );
}
rmdir( $base );

fwrite( STDOUT, "SIDRENA current CSV/XML bundle commit and rollback smoke test passed.\n" );
