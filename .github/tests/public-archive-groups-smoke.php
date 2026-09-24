<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://sidrene-cijene.com.hr/
 * @see https://brendigo.com/
 */

define( 'ABSPATH', __DIR__ . '/' );

class Sidrena_Utils {
	public static function sanitize_location_id( $value ) {
		return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) );
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

$source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-public.php' );
$css    = file_get_contents( dirname( __DIR__, 2 ) . '/public/css/public.css' );
sidrena_archive_assert( substr_count( $source, 'archive_groups( $location_id, true )' ) >= 2, 'Archive and downloads shortcodes must share the grouping engine.' );
sidrena_archive_assert( false !== strpos( $source, 'render_archive_groups( $groups )' ), 'Grouped archive renderer is missing.' );
sidrena_archive_assert( false !== strpos( $source, "esc_html_e( 'Preuzmi', 'sidrena' )" ), 'Archive download action is missing.' );
sidrena_archive_assert( false === strpos( $source, 'sidrena-public-archive__list' ), 'Legacy flat archive markup must not return.' );
sidrena_archive_assert( false === strpos( $css, '.sidrena-public-archive__list' ), 'Legacy flat archive CSS must be removed.' );
sidrena_archive_assert( false !== strpos( $css, '.sidrena-downloads__day>summary:focus-visible' ), 'Grouped archive summary needs a visible keyboard focus state.' );

fwrite( STDOUT, "Sidrena grouped public archive runtime test passed.\n" );
