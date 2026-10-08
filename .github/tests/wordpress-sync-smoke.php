<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://sidrene-cijene.com.hr/
 * @see https://brendigo.com/
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Post {
	public $ID;
	public $post_type;
	public $post_status;
	public function __construct( $id, $type, $status ) { $this->ID = $id; $this->post_type = $type; $this->post_status = $status; }
}

$GLOBALS['sidrena_meta'] = array(
	200 => array(
		'_sidrena_standalone_source_post_id' => 100,
		'_sidrena_standalone_source_price_key' => '_price',
		'_sidrena_standalone_current_price' => '19.99',
	),
	100 => array(),
);
$GLOBALS['sidrena_titles'] = array( 100 => 'Existing product', 200 => 'Existing product' );
$GLOBALS['sidrena_status'] = array( 100 => 'publish', 200 => 'publish' );
$GLOBALS['sidrena_regen'] = 0;

function __( $text, $domain = 'sidrena' ) { unset( $domain ); return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function get_posts( $args ) {
	if ( isset( $args['meta_key'], $args['meta_value'] ) && '_sidrena_standalone_source_post_id' === $args['meta_key'] && 100 === (int) $args['meta_value'] ) return array( 200 );
	return array();
}
function get_post_meta( $id, $key, $single = true ) { unset( $single ); return $GLOBALS['sidrena_meta'][ (int) $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['sidrena_meta'][ (int) $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['sidrena_meta'][ (int) $id ][ $key ] ); return true; }
function get_the_title( $id ) { return $GLOBALS['sidrena_titles'][ (int) $id ] ?? ''; }
function get_post( $id ) {
	return (int) $id === 100 ? new WP_Post( 100, 'catalog_item', $GLOBALS['sidrena_status'][100] ) : null;
}
function wp_update_post( $args, $wp_error = false ) {
	unset( $wp_error );
	$id = (int) $args['ID'];
	if ( isset( $args['post_title'] ) ) $GLOBALS['sidrena_titles'][ $id ] = $args['post_title'];
	if ( isset( $args['post_status'] ) ) $GLOBALS['sidrena_status'][ $id ] = $args['post_status'];
	return $id;
}
function get_post_status( $id ) { return $GLOBALS['sidrena_status'][ (int) $id ] ?? false; }
function wp_is_post_revision( $id ) { unset( $id ); return false; }
function wp_is_post_autosave( $id ) { unset( $id ); return false; }

class Sidrena_Utils {
	public static function decimal( $value ) {
		if ( '' === $value || null === $value ) return '';
		$value = str_replace( ',', '.', trim( (string) $value ) );
		return is_numeric( $value ) ? rtrim( rtrim( number_format( (float) $value, 6, '.', '' ), '0' ), '.' ) : '';
	}
}
class Sidrena_Pricelist {
	public static function queue_regeneration() { ++$GLOBALS['sidrena_regen']; return true; }
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-standalone.php';

function sidrena_sync_assert( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); }
}

$source = new WP_Post( 100, 'catalog_item', 'publish' );
Sidrena_Standalone::instance()->sync_linked_source_on_save( 100, $source, true );

sidrena_sync_assert( ! isset( $GLOBALS['sidrena_meta'][200]['_sidrena_standalone_current_price'] ), 'Tracked source price removal must clear stale Sidrena current price.' );
sidrena_sync_assert( 'draft' === $GLOBALS['sidrena_status'][200], 'Linked item without current source price must become draft.' );
sidrena_sync_assert( $GLOBALS['sidrena_regen'] > 0, 'Stale price removal must queue public regeneration.' );

$GLOBALS['sidrena_meta'][100]['_price'] = '12,50';
Sidrena_Standalone::instance()->sync_linked_source_on_save( 100, $source, true );

sidrena_sync_assert( '12.5' === $GLOBALS['sidrena_meta'][200]['_sidrena_standalone_current_price'], 'Restored source price must sync into Sidrena.' );
sidrena_sync_assert( 'publish' === $GLOBALS['sidrena_status'][200], 'Linked item with a current price must be published.' );

// A later product-meta change must be processed even when the source plugin
// writes _price after save_post. The historical anchor must remain untouched.
$GLOBALS['sidrena_meta'][200]['_sidrena_standalone_anchor_price'] = '9.99';
$GLOBALS['sidrena_meta'][100]['_price'] = '18.40';
Sidrena_Standalone::instance()->sync_linked_source_price_on_meta( 1, 100, '_price', '18.40' );
sidrena_sync_assert( '18.4' === get_post_meta( 200, '_sidrena_standalone_current_price', true ), 'Late source-meta price change not synchronized.' );
sidrena_sync_assert( '9.99' === get_post_meta( 200, '_sidrena_standalone_anchor_price', true ), 'Source sync must not change the historical anchor price.' );

// A shop owner can explicitly manage the current price manually without it
// being overwritten by source updates or another bulk source import.
$GLOBALS['sidrena_meta'][200]['_sidrena_standalone_source_price_sync'] = 'manual';
$GLOBALS['sidrena_meta'][200]['_sidrena_standalone_current_price'] = '25';
$GLOBALS['sidrena_meta'][100]['_price'] = '30';
Sidrena_Standalone::instance()->sync_linked_source_on_save( 100, $source, true );
Sidrena_Standalone::instance()->sync_linked_source_price_on_meta( 1, 100, '_price', '30' );
sidrena_sync_assert( '25' === get_post_meta( 200, '_sidrena_standalone_current_price', true ), 'Manual source price was overwritten.' );
sidrena_sync_assert( '9.99' === get_post_meta( 200, '_sidrena_standalone_anchor_price', true ), 'Manual source price must not change the anchor.' );

// Only the selected metadata key is authoritative: never fall back to
// another available field and silently change the price source.
$GLOBALS['sidrena_meta'][200]['_sidrena_standalone_source_price_sync'] = 'auto';
$GLOBALS['sidrena_meta'][200]['_sidrena_standalone_source_price_key'] = 'special_price';
Sidrena_Standalone::instance()->sync_linked_source_on_save( 100, $source, true );
sidrena_sync_assert( ! isset( $GLOBALS['sidrena_meta'][200]['_sidrena_standalone_current_price'] ), 'Configured price key must not fall back to unrelated meta.' );
$GLOBALS['sidrena_meta'][100]['special_price'] = '31.50';
Sidrena_Standalone::instance()->sync_linked_source_on_save( 100, $source, true );
sidrena_sync_assert( '31.5' === get_post_meta( 200, '_sidrena_standalone_current_price', true ), 'Custom source price key was not used.' );

 // Third-party integrations can write a configured key without "price" or
 // "cijena" after the main save_post hook has already fired.
 $GLOBALS['sidrena_meta'][200]['_sidrena_standalone_source_price_key'] = '_retail_amount';
 $GLOBALS['sidrena_meta'][100]['_retail_amount'] = '42.75';
 Sidrena_Standalone::instance()->sync_linked_source_price_on_meta( 1, 100, '_retail_amount', '42.75' );
 sidrena_sync_assert( '42.75' === get_post_meta( 200, '_sidrena_standalone_current_price', true ), 'Late custom metadata key without price/cijena must synchronize.' );
 sidrena_sync_assert( '9.99' === get_post_meta( 200, '_sidrena_standalone_anchor_price', true ), 'Custom metadata sync must preserve verified historical anchor.' );
 $GLOBALS['sidrena_meta'][100]['_retail_amount'] = '39.00';
 Sidrena_Standalone::instance()->sync_linked_source_price_on_meta( 2, 100, '_retail_amount', '39.00' );
 sidrena_sync_assert( '39' === get_post_meta( 200, '_sidrena_standalone_current_price', true ), 'Updated custom source price was not applied.' );
 unset( $GLOBALS['sidrena_meta'][100]['_retail_amount'] );
 Sidrena_Standalone::instance()->sync_linked_source_price_on_meta( 3, 100, '_retail_amount', '' );
 sidrena_sync_assert( ! isset( $GLOBALS['sidrena_meta'][200]['_sidrena_standalone_current_price'] ), 'Deleted selected source metadata must clear the stale live price.' );
 sidrena_sync_assert( 'draft' === get_post_status( 200 ), 'Missing current price must not remain publishable.' );



// A queued import from a previous request must not overwrite a newer run.
$GLOBALS['sidrena_sync_options'] = array(
    'sidrena_standalone_sync_state' => array(
        'run_id'    => 'run-new',
        'status'    => 'queued',
        'post_type' => 'catalog_item',
        'price_key' => '_price',
        'page'      => 1,
        'created'   => 0,
        'updated'   => 0,
        'skipped'   => 0,
    ),
);
function post_type_exists( $type ) { return 'catalog_item' === $type; }
function get_option( $key, $default = false ) { return $GLOBALS['sidrena_sync_options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) {
    unset( $autoload );
    $GLOBALS['sidrena_sync_options'][ $key ] = $value;
    return true;
}
function current_time( $type ) { unset( $type ); return '2026-10-08 18:00:00'; }
class Sidrena_Audit {
    public static function log( $event, $status, $message, $data = array() ) {
        unset( $event, $status, $message, $data );
    }
}
$standalone     = Sidrena_Standalone::instance();
$original_state = get_option( 'sidrena_standalone_sync_state' );
$standalone->sync_source_batch( 'catalog_item', 1, '_price', 'run-old' );
sidrena_sync_assert( $original_state === get_option( 'sidrena_standalone_sync_state' ), 'Old import must not mutate a newer import.' );
$standalone->sync_source_batch( 'catalog_item', 2, '_price', 'run-new' );
sidrena_sync_assert( $original_state === get_option( 'sidrena_standalone_sync_state' ), 'Out-of-order batch must not mutate progress.' );
$standalone->sync_source_batch( 'catalog_item', 1, '_price' );
sidrena_sync_assert( $original_state === get_option( 'sidrena_standalone_sync_state' ), 'Legacy job cannot corrupt current state.' );
$standalone->sync_source_batch( 'catalog_item', 1, '_price', 'run-new' );
$current_state = get_option( 'sidrena_standalone_sync_state' );
sidrena_sync_assert( 'complete' === $current_state['status'], 'Current import must complete on an empty final page.' );
sidrena_sync_assert( 'run-new' === $current_state['run_id'], 'Import token must remain unchanged.' );
$standalone->sync_source_batch( 'catalog_item', 1, '_price', 'run-new' );
sidrena_sync_assert( $current_state === get_option( 'sidrena_standalone_sync_state' ), 'Completed batch must be idempotent.' );

fwrite( STDOUT, "Sidrena WordPress linked-source price smoke test passed.\\n" );
