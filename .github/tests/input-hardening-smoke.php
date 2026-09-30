<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

declare( strict_types=1 );

$root       = dirname( __DIR__, 2 );
$bulk       = file_get_contents( $root . '/includes/class-sidrena-bulk.php' );
$standalone = file_get_contents( $root . '/includes/class-sidrena-standalone.php' );
$services   = file_get_contents( $root . '/includes/class-sidrena-services.php' );
$admin = file_get_contents( $root . '/includes/class-sidrena-admin.php' );

function sidrena_input_hardening_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

foreach ( array( $bulk, $standalone, $services, $admin ) as $source ) {
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

sidrena_input_hardening_assert(
	false !== strpos( $admin, "array_slice( \$this->post_array( 'locations' ), 0, 500, true )" )
	&& false !== strpos( $admin, '$used_selectors = array();' )
	&& false !== strpos( $admin, 'Sidrena_Utils::sanitize_location_id( $code )' )
	&& false !== strpos( $admin, 'isset( $used_selectors[ $selector_key ] )' ),
	'Location settings must bound the request and reject ambiguous enabled ID/code selectors.'
);

sidrena_input_hardening_assert(
	false !== strpos( $admin, 'private function open_csv_download( $filename )' )
	&& 4 === substr_count( $admin, '$this->open_csv_download(' )
	&& false !== strpos( $admin, "header( 'X-Content-Type-Options: nosniff' );" )
	&& false !== strpos( $admin, 'if ( ! is_resource( $out ) )' )
	&& false !== strpos( $admin, 'if ( false === fwrite( $out, "\\xEF\\xBB\\xBF" ) )' ),
	'Every admin CSV export must use the centralized checked download stream and nosniff header.'
);

sidrena_input_hardening_assert(
	false !== strpos( $admin, 'private function is_allowed_public_check_url( $url, $base )' )
	&& false !== strpos( $admin, "wp_parse_url( $url )" )
	&& false !== strpos( $admin, "rtrim( (string) ( $url_parts['host'] ?? '' ), '.' )" )
	&& false !== strpos( $admin, '$url_port !== $base_port' )
	&& false !== strpos( $admin, 'normalize_public_check_path' )
	&& false !== strpos( $admin, "'redirection'         => 0" )
	&& false === strpos( $admin, "0 !== strpos( $url, $base )" ),
	'Public HTTP self-check must validate an exact same-origin canonical path and must not follow redirects.'
);

fwrite( STDOUT, "SIDRENA input hardening smoke test passed.\n" );
