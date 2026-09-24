<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://sidrene-cijene.com.hr/
 * @see https://brendigo.com/
 */

$public = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-public.php' );
$utils  = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php' );

$start  = strpos( $public, 'private function read_snapshot_page(' );
$end    = strpos( $public, 'private function queue_snapshot_rebuild(', $start );
$reader = false !== $start && false !== $end ? substr( $public, $start, $end - $start ) : '';

function sidrena_stream_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

sidrena_stream_assert( '' !== $reader, 'Streaming snapshot reader method could not be inspected.' );
sidrena_stream_assert( false !== strpos( $utils, "cjenik-' . $id . '.jsonl'" ), 'Public snapshot path must use JSONL.' );
sidrena_stream_assert( false !== strpos( $reader, "fopen( $path, 'rb' )" ), 'Streaming snapshot reader must open the file as a stream.' );
sidrena_stream_assert( false !== strpos( $reader, 'fgets( $handle, 1048577 )' ), 'Streaming snapshot reader must consume bounded rows.' );
sidrena_stream_assert( false === strpos( $reader, 'file_get_contents(' ), 'Streaming snapshot reader must not load the full snapshot into memory.' );
sidrena_stream_assert( false !== strpos( $reader, 'min( 100, max( 10' ), 'Public page size must be bounded.' );
sidrena_stream_assert( false !== strpos( $public, "name=\"sidrena_q\"" ), 'Public pricelist search must use a GET query.' );
sidrena_stream_assert( false !== strpos( $public, "name=\"sidrena_stranica\"" ) || false !== strpos( $public, "'sidrena_stranica'" ), 'Public pricelist pagination parameter is missing.' );
sidrena_stream_assert( false === strpos( $public, "wp_enqueue_script( 'sidrena-public'" ), 'Public pricelist must not require JavaScript for search or pagination.' );

fwrite( STDOUT, "Sidrena streaming public snapshot smoke test passed.\n" );
