<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */


$source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-pricelist.php' );
$start  = strpos( $source, 'private function write_public_snapshot(' );
$end    = strpos( $source, 'private function cleanup_public_snapshots(', $start );
$method = false !== $start && false !== $end ? substr( $source, $start, $end - $start ) : '';
$manifest_start = strpos( $source, 'private function write_manifest(' );
$manifest       = false !== $manifest_start ? substr( $source, $manifest_start ) : '';

function sidrena_publication_write_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

sidrena_publication_write_assert( '' !== $method, 'Public snapshot writer method could not be inspected.' );
sidrena_publication_write_assert( false !== strpos( $method, 'open_atomic_writer( $path )' ), 'Public snapshot must use the atomic writer.' );
sidrena_publication_write_assert( false !== strpos( $method, "'schema'       => 2" ), 'Public snapshot must use streaming schema 2.' );
sidrena_publication_write_assert( false !== strpos( $method, "'format'       => 'jsonl'" ), 'Public snapshot must declare JSONL format.' );
sidrena_publication_write_assert( false !== strpos( $method, 'write_stream_all( $handle, $header . "\\n" )' ), 'Public snapshot header must handle short writes.' );
sidrena_publication_write_assert( false !== strpos( $method, 'write_stream_all( $handle, $encoded . "\\n" )' ), 'Public snapshot rows must handle short writes.' );
sidrena_publication_write_assert( false !== strpos( $method, 'commit_atomic_writer( $handle, $temp, $path )' ), 'Public snapshot must fsync and atomically commit.' );
sidrena_publication_write_assert( false === strpos( $method, 'fwrite(' ), 'Public snapshot must not bypass the short-write helper.' );
sidrena_publication_write_assert( false === strpos( $method, ".tmp'" ), 'Public snapshot must not use a fixed temporary filename.' );
sidrena_publication_write_assert( false === strpos( $method, ',"rows":[' ), 'Streaming public snapshot must not build one monolithic JSON rows array.' );

sidrena_publication_write_assert( '' !== $manifest, 'Manifest writer method could not be inspected.' );
sidrena_publication_write_assert( false !== strpos( $manifest, 'open_atomic_writer( $paths[\'manifest\'] )' ), 'Manifest must use the shared unique atomic writer.' );
sidrena_publication_write_assert( false !== strpos( $manifest, 'write_stream_all( $handle, $payload )' ), 'Manifest must handle short writes through the shared stream helper.' );
sidrena_publication_write_assert( false !== strpos( $manifest, 'commit_atomic_writer( $handle, $temp, $paths[\'manifest\'] )' ), 'Manifest must fsync and atomically commit through the shared writer.' );
sidrena_publication_write_assert( false === strpos( $manifest, 'file_put_contents(' ), 'Manifest must not bypass the shared durable atomic writer.' );
sidrena_publication_write_assert( false === strpos( $manifest, '$paths[\'manifest\'] . \'.tmp\'' ), 'Manifest must not use a fixed temporary filename.' );

fwrite( STDOUT, "Sidrena atomic publication smoke test passed.\n" );
