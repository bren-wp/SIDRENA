<?php
/**
 * SIDRENA input validation and request-bound regression guard.
 *
 * @package Sidrena
 * @author brendigo
 */

declare( strict_types=1 );

$root       = dirname( __DIR__, 2 );
$bulk       = file_get_contents( $root . '/includes/class-sidrena-bulk.php' );
$standalone = file_get_contents( $root . '/includes/class-sidrena-standalone.php' );
$services   = file_get_contents( $root . '/includes/class-sidrena-services.php' );

function sidrena_input_hardening_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

foreach ( array( $bulk, $standalone, $services ) as $source ) {
	sidrena_input_hardening_assert( false !== $source, 'Unable to read SIDRENA input-hardening source.' );
}

sidrena_input_hardening_assert(
	false !== strpos( $bulk, 'array_slice( $items, 0, 100, true )' ),
	'Woo bulk save must bound the number of submitted rows.'
);
foreach ( array( "'anchor'", "'quantity'", "'unit_price'" ) as $field ) {
	sidrena_input_hardening_assert(
		false !== strpos( $bulk, "validated_nonnegative_decimal( isset( \$row[$field] )" ),
		'Woo bulk numeric field must use nonnegative server-side validation: ' . $field
	);
}
sidrena_input_hardening_assert(
	false === strpos( $bulk, "Sidrena_Utils::decimal( isset( \$row['anchor'] )" )
	&& false === strpos( $bulk, "Sidrena_Utils::decimal( isset( \$row['quantity'] )" )
	&& false === strpos( $bulk, "Sidrena_Utils::decimal( isset( \$row['unit_price'] )" ),
	'Woo bulk editor must not restore permissive numeric parsing for price/quantity fields.'
);

sidrena_input_hardening_assert(
	false !== strpos( $standalone, 'array_slice( $items, 0, 100, true )' ),
	'Standalone catalog save must bound submitted rows.'
);
sidrena_input_hardening_assert(
	false !== strpos( $services, '$valid_location_ids[ $location_id ] = true;' )
	&& 2 <= substr_count( $services, '! isset( $valid_location_ids[ $location_id ] )' ),
	'Service current and anchor location prices must reject unknown location IDs.'
);

fwrite( STDOUT, "SIDRENA input hardening smoke test passed.\n" );
