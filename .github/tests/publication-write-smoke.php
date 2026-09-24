<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://sidrene-cijene.com.hr/
 * @see https://brendigo.com/
 */

$source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-pricelist.php' );
$start  = strpos( $source, 'private function write_public_snapshot(' );
$end    = strpos( $source, 'private function cleanup_public_snapshots(', $start );
$method = false !== $start && false !== $end ? substr( $source, $start, $end - $start ) : '';

function sidrena_publication_write_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

sidrena_publication_write_assert( '' !== $method, 'Public snapshot writer method could not be inspected.' );
sidrena_publication_write_assert( false !== strpos( $method, 'open_atomic_writer( $path )' ), 'Public snapshot must use the atomic writer.' );
sidrena_publication_write_assert( false !== strpos( $method, 'write_stream_all( $handle, $prefix )' ), 'Public snapshot prefix must handle short writes.' );
sidrena_publication_write_assert( false !== strpos( $method, 'write_stream_all( $handle, $chunk )' ), 'Public snapshot rows must handle short writes.' );
sidrena_publication_write_assert( false !== strpos( $method, 'commit_atomic_writer( $handle, $temp, $path )' ), 'Public snapshot must fsync and atomically commit.' );
sidrena_publication_write_assert( false === strpos( $method, 'fwrite(' ), 'Public snapshot must not bypass the short-write helper.' );
sidrena_publication_write_assert( false === strpos( $method, "$path . '.tmp'" ), 'Public snapshot must not use a fixed temporary filename.' );

fwrite( STDOUT, "Sidrena atomic publication smoke test passed.\n" );
