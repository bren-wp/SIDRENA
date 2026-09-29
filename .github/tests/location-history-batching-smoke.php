<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

$source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-location-history.php' );

function sidrena_location_batch_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

sidrena_location_batch_assert( false !== strpos( $source, 'sidrena_location_history_batch_size' ), 'Location-history batch-size filter is missing.' );
sidrena_location_batch_assert( false !== strpos( $source, 'SELECT id, location_id, product_id, variation_id, price, anchor_price, availability' ), 'Location snapshot must select the keyset id.' );
sidrena_location_batch_assert( false !== strpos( $source, 'WHERE id > %d' ), 'Location snapshot must use keyset pagination.' );
sidrena_location_batch_assert( false !== strpos( $source, 'LIMIT %d' ), 'Location snapshot must use bounded batches.' );
sidrena_location_batch_assert( false !== strpos( $source, '$row_count = count( $rows );' ), 'Location snapshot must cache batch size before the loop condition.' );
sidrena_location_batch_assert( false !== strpos( $source, '$row_count === $batch_size' ), 'Location snapshot batch loop termination is missing.' );
sidrena_location_batch_assert(
	false === strpos( $source, 'SELECT location_id, product_id, variation_id, price, anchor_price, availability FROM {$current_table} ORDER BY id ASC' ),
	'Unbounded location-history full-table snapshot query must not return.'
);
sidrena_location_batch_assert( false === strpos( $source, 'prune_history' ), 'Location price history must remain unlimited and must not restore time-based pruning.' );
sidrena_location_batch_assert( false === strpos( $source, 'DELETE FROM %i WHERE recorded_at < %s' ), 'Location price history must not delete older audit rows by age.' );

fwrite( STDOUT, "Sidrena location-history batching smoke test passed.\n" );
