<?php
/**
 * Manual public-page creation regression test.
 *
 * @package Sidrena
 * @author brendigo
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'SIDRENA_VERSION', '1.0.24' );
define( 'SIDRENA_EDITION', 'wordpress' );
define( 'SIDRENA_URL', 'https://example.test/wp-content/plugins/brendigo-sidrene-cijene-digitalni-cjenici/' );
define( 'OBJECT', 'OBJECT' );

class WP_Post {
	public $ID;
	public $post_type = 'page';
	public $post_status = 'publish';
	public $post_name = '';
	public $post_content = '';
	public $post_title = '';
	public function __construct( $id, $name = '' ) { $this->ID = $id; $this->post_name = $name; }
}
class WP_Error {
	public $code;
	public $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
}

$GLOBALS['sidrena_options'] = array(
	'sidrena_settings' => array( 'enable_public_html' => 'yes' ),
	'sidrena_public_page_id' => 0,
);
$GLOBALS['sidrena_pages'] = array();
$GLOBALS['sidrena_insert_count'] = 0;

function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['sidrena_options'] ) ? $GLOBALS['sidrena_options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { unset( $autoload ); $GLOBALS['sidrena_options'][ $key ] = $value; return true; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function get_post( $id ) { return $GLOBALS['sidrena_pages'][ (int) $id ] ?? null; }
function get_page_by_path( $path, $output = OBJECT, $post_type = 'page' ) {
	unset( $output, $post_type );
	foreach ( $GLOBALS['sidrena_pages'] as $page ) {
		if ( $page->post_name === $path ) return $page;
	}
	return null;
}
function wp_insert_post( $args, $wp_error = false ) {
	unset( $wp_error );
	$id = 1000 + ++$GLOBALS['sidrena_insert_count'];
	$page = new WP_Post( $id, $args['post_name'] ?? '' );
	$page->post_status  = $args['post_status'] ?? 'draft';
	$page->post_content = $args['post_content'] ?? '';
	$page->post_title   = $args['post_title'] ?? '';
	$GLOBALS['sidrena_pages'][ $id ] = $page;
	return $id;
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $text, $domain = null ) { unset( $domain ); return $text; }
function apply_filters( $tag, $value ) { unset( $tag ); return $value; }
function esc_url_raw( $url ) { return $url; }

class Sidrena_Audit {
	public static function log( $type, $status, $message, $context = array() ) { unset( $type, $status, $message, $context ); }
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php';
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-public.php';

function sidrena_page_assert( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); }
}

$id1 = Sidrena_Public::create_public_page();
sidrena_page_assert( 1001 === $id1, 'Explicit admin action must create the public page.' );
sidrena_page_assert( 1 === $GLOBALS['sidrena_insert_count'], 'Public page must be inserted exactly once.' );
sidrena_page_assert( 1001 === (int) get_option( 'sidrena_public_page_id' ), 'Created public page ID must be stored.' );
sidrena_page_assert( '<!-- wp:shortcode -->[sidrena_objava_cjenika]<!-- /wp:shortcode -->' === $GLOBALS['sidrena_pages'][1001]->post_content, 'Public page must use the publication shortcode.' );

$id2 = Sidrena_Public::create_public_page();
sidrena_page_assert( $id1 === $id2, 'Repeated explicit action must reuse the tracked page.' );
sidrena_page_assert( 1 === $GLOBALS['sidrena_insert_count'], 'Repeated explicit action must not duplicate the page.' );

update_option( 'sidrena_public_page_id', 0 );
$claimed = Sidrena_Public::create_public_page();
sidrena_page_assert( is_wp_error( $claimed ) && 'sidrena_public_page_slug_exists' === $claimed->code, 'SIDRENA must not claim an unrelated existing slug.' );
sidrena_page_assert( 1 === $GLOBALS['sidrena_insert_count'], 'Slug collision must not insert another page.' );

update_option( 'sidrena_public_page_id', 1001 );
$GLOBALS['sidrena_pages'][1001]->post_status = 'trash';
$trashed = Sidrena_Public::create_public_page();
sidrena_page_assert( is_wp_error( $trashed ) && 'sidrena_public_page_trashed' === $trashed->code, 'Tracked trashed page must not be silently recreated.' );
sidrena_page_assert( 1 === $GLOBALS['sidrena_insert_count'], 'Trashed tracked page must not be recreated automatically.' );

fwrite( STDOUT, "SIDRENA manual public page smoke test passed.\n" );
