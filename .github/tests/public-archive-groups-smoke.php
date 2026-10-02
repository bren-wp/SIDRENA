<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'SIDRENA_URL', 'https://example.test/wp-content/plugins/sidrena/' );
define( 'SIDRENA_VERSION', '1.0.25' );

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

	public static function settings() {
		return array( 'enable_public_html' => 'yes' );
	}

	public static function format_iso_datetime( $value ) {
		return (string) $value;
	}

	public static function public_index() {
		return array(
			array( 'location_id' => 'loc-1', 'location_code' => 'LOC-1', 'filename' => 'current.csv', 'format' => 'csv', 'catalog' => 'products', 'url' => 'https://example.test/current.csv', 'generated_ts' => 1790245000, 'generated_at' => '2026-09-24T10:00:00+02:00', 'rows' => 42, 'bytes' => 2048, 'sha256' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ),
			array( 'location_id' => 'loc-1', 'location_code' => 'LOC-1', 'filename' => 'current.xml', 'format' => 'xml', 'catalog' => 'products', 'url' => 'https://example.test/current.xml', 'generated_ts' => 1790244900 ),
			array( 'location_id' => 'loc-1', 'location_code' => 'LOC-1', 'filename' => 'services.csv', 'format' => 'csv', 'catalog' => 'services', 'url' => 'https://example.test/services.csv', 'generated_ts' => 1790244800 ),
			array( 'location_id' => 'loc-2', 'location_code' => 'LOC-2', 'filename' => 'other-current.csv', 'format' => 'csv', 'catalog' => 'products', 'url' => 'https://example.test/other-current.csv', 'generated_ts' => 1790244700 ),
		);
	}

	public static function archive_index() {
		return array(
			array( 'location_id' => 'loc-1', 'filename' => 'current.csv', 'format' => 'csv', 'catalog' => 'products', 'generated_ts' => 1790244000 ),
			array( 'location_id' => 'loc-1', 'filename' => 'old-24.xml', 'format' => 'xml', 'catalog' => 'products', 'generated_ts' => 1790240400 ),
			array( 'location_id' => 'loc-1', 'filename' => 'old-23.csv', 'format' => 'csv', 'catalog' => 'products', 'generated_ts' => 1790154000 ),
			array( 'location_id' => 'loc-1', 'filename' => 'old-23.csv', 'format' => 'csv', 'catalog' => 'products', 'generated_ts' => 1790154000 ),
			array( 'location_id' => 'loc-1', 'filename' => 'fallback-22.xml', 'format' => 'xml', 'catalog' => 'products', 'generated_at' => '2026-09-22T08:30:00+02:00' ),
			array( 'location_id' => 'loc-1', 'filename' => 'undated.csv', 'format' => 'csv', 'catalog' => 'products' ),
			array( 'location_id' => 'loc-1', 'filename' => 'service-old.csv', 'format' => 'csv', 'catalog' => 'services', 'generated_ts' => 1790067600 ),
			array( 'location_id' => 'loc-2', 'filename' => 'other-old.csv', 'format' => 'csv', 'catalog' => 'products', 'generated_ts' => 1790240400 ),
		);
	}
}

function sanitize_file_name( $value ) {
	return preg_replace( '/[^A-Za-z0-9._-]/', '-', basename( (string) $value ) );
}
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function shortcode_atts( $pairs, $atts, $shortcode = '' ) { unset( $shortcode ); return array_merge( $pairs, is_array( $atts ) ? $atts : array() ); }
function apply_filters( $tag, $value ) { unset( $tag ); return $value; }
function esc_url_raw( $value ) { return (string) $value; }
function esc_url( $value ) { return (string) $value; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $text, $domain = null ) { unset( $domain ); return $text; }
function esc_html_e( $text, $domain = null ) { echo esc_html__( $text, $domain ); }
function _n( $single, $plural, $number, $domain = null ) { unset( $domain ); return 1 === (int) $number ? $single : $plural; }
function size_format( $bytes, $decimals = 0 ) {
	unset( $decimals );
	$bytes = (int) $bytes;
	return $bytes >= 1024 ? rtrim( rtrim( number_format( $bytes / 1024, 2, '.', '' ), '0' ), '.' ) . ' KB' : $bytes . ' B';
}
function absint( $value ) { return abs( (int) $value ); }
function wp_date( $format, $timestamp = null ) { return gmdate( $format, null === $timestamp ? time() : (int) $timestamp ); }
function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ) { unset( $handle, $src, $deps, $version ); }
function get_bloginfo( $show = '' ) { unset( $show ); return 'SIDRENA Test'; }
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

$groups = $method->invoke( $public, 'loc-1', true, '', 'products' );
$keys   = array_keys( $groups );

sidrena_archive_assert( array( '2026-09-24', '2026-09-23', '2026-09-22', 'undated' ) === $keys, 'Archive groups must be newest-first with undated entries last.' );
sidrena_archive_assert( 1 === count( $groups['2026-09-24'] ), 'Current public file must be excluded from previous archive groups.' );
sidrena_archive_assert( 'old-24.xml' === $groups['2026-09-24'][0]['filename'], 'Newest previous archive item is incorrect.' );
sidrena_archive_assert( 1 === count( $groups['2026-09-23'] ), 'Duplicate archive filenames must be de-duplicated.' );
sidrena_archive_assert( 'old-23.csv' === $groups['2026-09-23'][0]['filename'], 'Archive date grouping is incorrect.' );
sidrena_archive_assert( 'fallback-22.xml' === $groups['2026-09-22'][0]['filename'], 'generated_at fallback must participate in date grouping.' );
sidrena_archive_assert( 'undated.csv' === $groups['undated'][0]['filename'], 'Undated archive entries must remain available.' );

$all = $method->invoke( $public, 'loc-1', false, '', 'products' );
sidrena_archive_assert( 2 === count( $all['2026-09-24'] ), 'Archive grouping must optionally include the current file.' );

$unfiltered = $method->invoke( $public, '', true, '', 'products' );
sidrena_archive_assert( isset( $unfiltered['2026-09-24'] ), 'Empty archive location filter must retain archive groups instead of becoming a synthetic location filter.' );
sidrena_archive_assert( 2 === count( $unfiltered['2026-09-24'] ), 'Unfiltered archive must include previous files from all public locations while excluding both current files.' );
$unfiltered_names = array_column( $unfiltered['2026-09-24'], 'filename' );
sort( $unfiltered_names );
sidrena_archive_assert( array( 'old-24.xml', 'other-old.csv' ) === $unfiltered_names, 'Unfiltered archive must include previous files from each location.' );

$xml_groups = $method->invoke( $public, 'loc-1', true, 'xml', 'products' );
sidrena_archive_assert( array( '2026-09-24', '2026-09-22' ) === array_keys( $xml_groups ), 'Archive format filter must retain only matching XML publication days.' );
sidrena_archive_assert( 'old-24.xml' === $xml_groups['2026-09-24'][0]['filename'], 'Archive format filter returned the wrong newest XML file.' );

$limit_method = new ReflectionMethod( 'Sidrena_Public', 'limit_archive_groups' );
$limit_method->setAccessible( true );
$limited = $limit_method->invoke( $public, $groups, 2 );
sidrena_archive_assert( 2 === array_sum( array_map( 'count', $limited ) ), 'Archive limit must cap the total number of files across date groups.' );
sidrena_archive_assert( array( '2026-09-24', '2026-09-23' ) === array_keys( $limited ), 'Archive limit must preserve newest-first grouping order.' );

$url = $public->current_file_url_shortcode( array( 'lokacija' => 'loc-1', 'format' => 'xml', 'katalog' => 'products' ) );
sidrena_archive_assert( 'https://example.test/current.xml' === $url, 'Current file URL shortcode must resolve the requested location/catalog/format.' );
$missing_url = $public->current_file_url_shortcode( array( 'lokacija' => 'missing' ) );
sidrena_archive_assert( '' === $missing_url, 'Current file URL shortcode must not fall back to all locations for an invalid explicit location.' );

$render_files = new ReflectionMethod( 'Sidrena_Public', 'render_file_entries' );
$render_files->setAccessible( true );
$current_entry = Sidrena_Utils::public_index()[0];
foreach ( array( 'kartice', 'popis', 'tablica' ) as $view ) {
	$rendered = $render_files->invoke( $public, array( $current_entry ), $view, 'current' );
	sidrena_archive_assert( false !== strpos( $rendered, '42' ), 'Public ' . $view . ' file view must expose the stored row count.' );
	sidrena_archive_assert( false !== strpos( $rendered, '2 KB' ), 'Public ' . $view . ' file view must expose the stored file size.' );
	sidrena_archive_assert( false !== strpos( $rendered, str_repeat( 'a', 64 ) ), 'Public ' . $view . ' file view must expose the stored SHA-256 checksum.' );
	sidrena_archive_assert( false !== strpos( $rendered, '>Otvori</a>' ), 'Public ' . $view . ' file view must expose a non-download open action.' );
	sidrena_archive_assert( false !== strpos( $rendered, 'target="_blank" rel="noopener">Otvori</a>' ), 'Public ' . $view . ' open action must use a safe new-tab link.' );
	sidrena_archive_assert( false !== strpos( $rendered, ' download rel="noopener">Preuzmi</a>' ), 'Public ' . $view . ' file view must retain an explicit download action.' );
	sidrena_archive_assert( 2 === substr_count( $rendered, 'href="https://example.test/current.csv"' ), 'Public ' . $view . ' file view must render separate open and download URLs.' );
}
$invalid_checksum = $current_entry;
$invalid_checksum['sha256'] = '<invalid>';
$rendered_invalid = $render_files->invoke( $public, array( $invalid_checksum ), 'kartice', 'current' );
sidrena_archive_assert( false === strpos( $rendered_invalid, '<invalid>' ), 'Invalid checksum metadata must never be rendered publicly.' );

$source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-public.php' );
$css    = file_get_contents( dirname( __DIR__, 2 ) . '/public/css/public.css' );
sidrena_archive_assert( substr_count( $source, 'archive_groups( $location_id, true,' ) >= 2, 'Archive and downloads shortcodes must share the filtered grouping engine.' );
sidrena_archive_assert( false !== strpos( $source, "'' === \$location_id ? '' : Sidrena_Utils::sanitize_location_id( \$location_id )" ), 'Archive grouping must preserve an intentionally empty all-locations filter.' );
sidrena_archive_assert( substr_count( $source, '$this->optional_location_id( $requested )' ) >= 2, 'Archive and downloads shortcodes must reject invalid explicit location filters through the shared resolver.' );
sidrena_archive_assert( false !== strpos( $source, 'if ( null === $location_id ) {' ), 'Public location filter resolution must preserve an explicit invalid state.' );
sidrena_archive_assert( false !== strpos( $source, 'status_header( 404 );' ), 'Dedicated public routes must return HTTP 404 for an explicit unknown or disabled location.' );
sidrena_archive_assert( false !== strpos( $source, "'' === \$location_raw ? '' : Sidrena_Utils::sanitize_location_id( \$location_raw )" ), 'An explicitly empty location query parameter must remain empty instead of becoming the sanitizer fallback ID.' );
sidrena_archive_assert( false !== strpos( $source, 'render_archive_groups( $groups, $view )' ), 'View-aware grouped archive renderer is missing.' );
sidrena_archive_assert( false !== strpos( $source, "esc_html__( 'Otvori', 'sidrena' )" ), 'Archive open action is missing.' );
sidrena_archive_assert( false !== strpos( $source, "esc_html__( 'Preuzmi', 'sidrena' )" ), 'Archive download action is missing.' );
sidrena_archive_assert( false !== strpos( $source, 'private function public_file_actions_html' ), 'Public file actions must share one renderer to avoid markup drift.' );
sidrena_archive_assert( false !== strpos( $source, "add_shortcode( 'sidrena_cjenik_url'" ), 'Premium current-file URL shortcode is missing.' );
sidrena_archive_assert( false !== strpos( $source, "'prikaz'   => 'kartice'" ), 'Premium public file layout attribute is missing.' );
sidrena_archive_assert( false !== strpos( $source, "'arhiva'   => 'da'" ), 'Premium archive visibility attribute is missing.' );
$without_archive = $public->downloads_shortcode(
	array(
		'lokacija' => 'loc-1',
		'arhiva'   => 'ne',
		'naslovi'  => 'ne',
	)
);
sidrena_archive_assert( false !== strpos( $without_archive, 'sidrena-downloads__summary--compact' ), 'Downloads summary must switch to compact layout when archive output is disabled.' );
sidrena_archive_assert( false === strpos( $without_archive, '<span>Arhiva</span>' ), 'Archive summary metric must be hidden when archive output is disabled.' );
sidrena_archive_assert( false === strpos( $without_archive, 'Prethodne objave' ), 'Archive section must be hidden when archive output is disabled.' );
sidrena_archive_assert( false !== strpos( $css, '.sidrena-downloads__summary--compact' ), 'Compact two-column summary styles are missing when archive output is disabled.' );
sidrena_archive_assert( false !== strpos( $source, "'sidrena_public_file_entries'" ), 'Public file entry extension filter is missing.' );
sidrena_archive_assert( false !== strpos( $source, "'sidrena_public_archive_groups'" ), 'Public archive group extension filter is missing.' );
sidrena_archive_assert( false !== strpos( $source, "'sidrena_public_files_html'" ), 'Public file HTML extension filter is missing.' );
sidrena_archive_assert( false !== strpos( $source, "'sidrena_current_file_url'" ), 'Current file URL extension filter is missing.' );
sidrena_archive_assert( false !== strpos( $source, "public_file_integrity_html" ), 'Public checksum integrity renderer is missing.' );
sidrena_archive_assert( false !== strpos( $source, "size_format( \$bytes, 2 )" ), 'Public file size must use stored byte metadata.' );
sidrena_archive_assert( false !== strpos( $source, "preg_match( '/^[a-f0-9]{64}$/'" ), 'Public checksum renderer must validate SHA-256 metadata before output.' );
sidrena_archive_assert( false === strpos( $source, 'sidrena-public-archive__list' ), 'Legacy flat archive markup must not return.' );
sidrena_archive_assert( false === strpos( $css, '.sidrena-public-archive__list' ), 'Legacy flat archive CSS must be removed.' );
sidrena_archive_assert( false !== strpos( $css, '.sidrena-downloads__day>summary:focus-visible' ), 'Grouped archive summary needs a visible keyboard focus state.' );
sidrena_archive_assert( false !== strpos( $css, '.sidrena-downloads__list-item' ), 'Premium list layout styles are missing.' );
sidrena_archive_assert( false !== strpos( $css, '.sidrena-downloads__actions' ), 'Public open/download action group styles are missing.' );
sidrena_archive_assert( false !== strpos( $css, '.sidrena-downloads__action.is-secondary' ), 'Public open action needs a distinct secondary style.' );
sidrena_archive_assert( false !== strpos( $css, '.sidrena-downloads__table-wrap' ), 'Premium table layout styles are missing.' );
sidrena_archive_assert( false !== strpos( $css, '.sidrena-download-integrity>summary:focus-visible' ), 'Public checksum disclosure needs a visible keyboard focus state.' );

fwrite( STDOUT, "Sidrena grouped public archive runtime test passed.\n" );
