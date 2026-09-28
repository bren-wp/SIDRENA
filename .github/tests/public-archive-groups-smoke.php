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

class Sidrena_Utils {
	public static function sanitize_location_id( $value ) {
		$value = strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) );
		return $value ? $value : 'lokacija';
	}

	public static function locations() {
		return array(
			array( 'id' => 'loc-1', 'code' => 'LOC-1', 'enabled' => 'yes' ),
			array( 'id' => 'loc-2', 'code' => 'LOC-2', 'enabled' => 'yes' ),
			array( 'id' => 'loc-off', 'code' => 'LOC-OFF', 'enabled' => 'no' ),
		);
	}

	public static function public_index() {
		return array(
			array( 'location_id' => 'loc-1', 'filename' => 'current.csv' ),
			array( 'location_id' => 'loc-2', 'filename' => 'other-current.csv' ),
		);
	}

	public static function archive_index() {
		return array(
			array( 'location_id' => 'loc-1', 'filename' => 'current.csv', 'generated_ts' => 1790244000 ),
			array( 'location_id' => 'loc-1', 'filename' => 'old-24.xml', 'generated_ts' => 1790240400 ),
			array( 'location_id' => 'loc-1', 'filename' => 'old-23.csv', 'generated_ts' => 1790154000 ),
			array( 'location_id' => 'loc-1', 'filename' => 'old-23.csv', 'generated_ts' => 1790154000 ),
			array( 'location_id' => 'loc-1', 'filename' => 'fallback-22.xml', 'generated_at' => '2026-09-22T08:30:00+02:00' ),
			array( 'location_id' => 'loc-1', 'filename' => 'undated.csv' ),
			array( 'location_id' => 'loc-2', 'filename' => 'other-old.csv', 'generated_ts' => 1790240400 ),
		);
	}
}

function sanitize_file_name( $value ) {
	return preg_replace( '/[^A-Za-z0-9._-]/', '-', basename( (string) $value ) );
}
function absint( $value ) { return abs( (int) $value ); }
function wp_date( $format, $timestamp = null ) { return gmdate( $format, null === $timestamp ? time() : (int) $timestamp ); }
function __( $text, $domain = null ) { unset( $domain ); return $text; }

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-public.php';

function sidrena_archive_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$method = new ReflectionMethod( 'Sidrena_Public', 'archive_groups' );
$method->setAccessible( true );
$public = Sidrena_Public::instance();

$location_filter = new ReflectionMethod( 'Sidrena_Public', 'optional_location_id' );
$location_filter->setAccessible( true );
sidrena_archive_assert( '' === $location_filter->invoke( $public, '' ), 'Empty optional location filter must remain empty and mean all locations.' );
sidrena_archive_assert( 'loc-1' === $location_filter->invoke( $public, 'LOC-1' ), 'Enabled location code must resolve to its canonical location ID.' );
sidrena_archive_assert( null === $location_filter->invoke( $public, 'loc-off' ), 'Disabled location must be rejected by optional public filters.' );
sidrena_archive_assert( null === $location_filter->invoke( $public, 'missing' ), 'Unknown location must be rejected by optional public filters.' );

$groups = $method->invoke( $public, 'loc-1', true );
$keys   = array_keys( $groups );

sidrena_archive_assert( array( '2026-09-24', '2026-09-23', '2026-09-22', 'undated' ) === $keys, 'Archive groups must be newest-first with undated entries last.' );
sidrena_archive_assert( 1 === count( $groups['2026-09-24'] ), 'Current public file must be excluded from previous archive groups.' );
sidrena_archive_assert( 'old-24.xml' === $groups['2026-09-24'][0]['filename'], 'Newest previous archive item is incorrect.' );
sidrena_archive_assert( 1 === count( $groups['2026-09-23'] ), 'Duplicate archive filenames must be de-duplicated.' );
sidrena_archive_assert( 'old-23.csv' === $groups['2026-09-23'][0]['filename'], 'Archive date grouping is incorrect.' );
sidrena_archive_assert( 'fallback-22.xml' === $groups['2026-09-22'][0]['filename'], 'generated_at fallback must participate in date grouping.' );
sidrena_archive_assert( 'undated.csv' === $groups['undated'][0]['filename'], 'Undated archive entries must remain available.' );

$all = $method->invoke( $public, 'loc-1', false );
sidrena_archive_assert( 2 === count( $all['2026-09-24'] ), 'Archive grouping must optionally include the current file.' );

$unfiltered = $method->invoke( $public, '', true );
sidrena_archive_assert( isset( $unfiltered['2026-09-24'] ), 'Empty archive location filter must retain archive groups instead of becoming a synthetic location filter.' );
sidrena_archive_assert( 2 === count( $unfiltered['2026-09-24'] ), 'Unfiltered archive must include previous files from all public locations while excluding both current files.' );
$unfiltered_names = array_column( $unfiltered['2026-09-24'], 'filename' );
sort( $unfiltered_names );
sidrena_archive_assert( array( 'old-24.xml', 'other-old.csv' ) === $unfiltered_names, 'Unfiltered archive must include previous files from each location.' );

$source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-public.php' );
$css    = file_get_contents( dirname( __DIR__, 2 ) . '/public/css/public.css' );
sidrena_archive_assert( substr_count( $source, 'archive_groups( $location_id, true )' ) >= 2, 'Archive and downloads shortcodes must share the grouping engine.' );
sidrena_archive_assert( false !== strpos( $source, "'' === $location_id ? '' : Sidrena_Utils::sanitize_location_id( $location_id )" ), 'Archive grouping must preserve an intentionally empty all-locations filter.' );
sidrena_archive_assert( substr_count( $source, '$this->optional_location_id( $requested )' ) >= 2, 'Archive and downloads shortcodes must reject invalid explicit location filters through the shared resolver.' );
sidrena_archive_assert( false !== strpos( $source, 'if ( null === $location_id ) {' ), 'Public location filter resolution must preserve an explicit invalid state.' );
sidrena_archive_assert( false !== strpos( $source, 'status_header( 404 );' ), 'Dedicated public routes must return HTTP 404 for an explicit unknown or disabled location.' );
sidrena_archive_assert( false !== strpos( $source, "'' === $location_raw ? '' : Sidrena_Utils::sanitize_location_id( $location_raw )" ), 'An explicitly empty location query parameter must remain empty instead of becoming the sanitizer fallback ID.' );
sidrena_archive_assert( false !== strpos( $source, 'render_archive_groups( $groups )' ), 'Grouped archive renderer is missing.' );
sidrena_archive_assert( false !== strpos( $source, "esc_html_e( 'Preuzmi', 'sidrena' )" ), 'Archive download action is missing.' );
sidrena_archive_assert( false === strpos( $source, 'sidrena-public-archive__list' ), 'Legacy flat archive markup must not return.' );
sidrena_archive_assert( false === strpos( $css, '.sidrena-public-archive__list' ), 'Legacy flat archive CSS must be removed.' );
sidrena_archive_assert( false !== strpos( $css, '.sidrena-downloads__day>summary:focus-visible' ), 'Grouped archive summary needs a visible keyboard focus state.' );

fwrite( STDOUT, "Sidrena grouped public archive runtime test passed.\n" );
