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
$generate_start = strpos( $source, 'public function generate_all(' );
$generate_end   = strpos( $source, 'private function publication_alert_recipient(', $generate_start );
$generate       = false !== $generate_start && false !== $generate_end ? substr( $source, $generate_start, $generate_end - $generate_start ) : '';

function sidrena_generation_transaction_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

sidrena_generation_transaction_assert( '' !== $generate, 'Generation method could not be inspected.' );
sidrena_generation_transaction_assert( false === strpos( $generate, 'preflight_catalog(' ), 'Generation must not run a separate full-catalog strict preflight pass.' );
sidrena_generation_transaction_assert( false !== strpos( $source, 'private function validated_rows(' ), 'Strict row validation must be integrated into streaming writers.' );
sidrena_generation_transaction_assert( false !== strpos( $source, 'catalog_validation_error(' ), 'Strict validation error builder is missing.' );
sidrena_generation_transaction_assert( substr_count( $source, 'if ( is_wp_error( $row ) )' ) >= 3, 'CSV, XML and JSONL writers must abort on streamed validation errors.' );
sidrena_generation_transaction_assert( false !== strpos( $generate, '$location_entries  = array();' ), 'Generation must stage location index entries.' );
sidrena_generation_transaction_assert( false !== strpos( $generate, '$location_files    = array();' ), 'Generation must track newly written files for rollback.' );
sidrena_generation_transaction_assert( false !== strpos( $generate, '$location_failed   = false;' ), 'Generation must track transactional location failure.' );
sidrena_generation_transaction_assert( false !== strpos( $generate, '$this->discard_generated_files( $location_files );' ), 'Failed location generation must remove newly written files.' );
sidrena_generation_transaction_assert( false !== strpos( $generate, '$index     = array_merge( $index, $location_entries );' ), 'Current index entries must be committed only after location success.' );
$expected_pos = strpos( $generate, '$expected[ $this->index_key( $location, $catalog_type, $format ) ] = true;' );
$address_pos  = strpos( $generate, "if ( '' === \$address )" );
sidrena_generation_transaction_assert( false !== $expected_pos && false !== $address_pos && $expected_pos < $address_pos, 'Expected current-index keys must be marked before location validation can fail.' );
sidrena_generation_transaction_assert( false !== strpos( $generate, "'yes' === \$settings['enable_public_html']" ), 'HTML snapshot work must be skipped when public HTML is disabled.' );
sidrena_generation_transaction_assert( false !== strpos( $source, 'private function discard_generated_files(' ), 'Generated-file rollback helper is missing.' );

fwrite( STDOUT, "Sidrena transactional generation smoke test passed.\n" );
