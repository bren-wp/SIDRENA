<?php
define( 'ABSPATH', __DIR__ . '/' );
define( 'SIDRENA_VERSION', '0.1.0' );
define( 'SIDRENA_EDITION', 'wordpress' );
define( 'SIDRENA_URL', 'https://example.test/wp-content/plugins/sidrena-wordpress/' );
define( 'OBJECT', 'OBJECT' );

class WP_Post {
	public $ID;
	public $post_type = 'page';
	public $post_status = 'publish';
	public $post_name = '';
	public function __construct( $id, $name = '' ) { $this->ID = $id; $this->post_name = $name; }
}
class WP_Error {}

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
	$page->post_status = $args['post_status'] ?? 'draft';
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

$id1 = Sidrena_Public::ensure_public_page();
sidrena_page_assert( 1001 === $id1, 'First ensure must create the public page.' );
sidrena_page_assert( 1 === $GLOBALS['sidrena_insert_count'], 'Public page must be inserted exactly once.' );
sidrena_page_assert( 1001 === (int) get_option( 'sidrena_public_page_id' ), 'Created public page ID must be stored.' );

$id2 = Sidrena_Public::ensure_public_page();
sidrena_page_assert( $id1 === $id2, 'Second ensure must reuse stored public page.' );
sidrena_page_assert( 1 === $GLOBALS['sidrena_insert_count'], 'Second ensure must not duplicate the page.' );

update_option( 'sidrena_public_page_id', 0 );
$id3 = Sidrena_Public::ensure_public_page();
sidrena_page_assert( $id1 === $id3, 'Ensure must recover an existing objava-cjenika page by slug.' );
sidrena_page_assert( 1 === $GLOBALS['sidrena_insert_count'], 'Slug recovery must not insert another page.' );

fwrite( STDOUT, "Sidrena public page automation smoke test passed.\n" );
