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

class WP_Post {
	public $ID;
	public $post_type;
	public $post_status;
	public $post_date;

	public function __construct( $id, $status, $date, $post_type = 'sidrena_service' ) {
		$this->ID          = $id;
		$this->post_type   = $post_type;
		$this->post_status = $status;
		$this->post_date   = $date;
	}
}

$GLOBALS['sidrena_service_meta']  = array();
$GLOBALS['sidrena_queue_count']   = 0;

function wp_is_post_revision( $post_id ) {
	unset( $post_id );
	return false;
}

function get_post_meta( $post_id, $key, $single = false ) {
	unset( $single );
	return isset( $GLOBALS['sidrena_service_meta'][ $post_id ][ $key ] )
		? $GLOBALS['sidrena_service_meta'][ $post_id ][ $key ]
		: '';
}

function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['sidrena_service_meta'][ $post_id ][ $key ] = $value;
	return true;
}

function get_post_datetime( $post ) {
	return new DateTimeImmutable( $post->post_date, wp_timezone() );
}

function wp_timezone() {
	return new DateTimeZone( 'Europe/Zagreb' );
}

final class Sidrena_Utils {
	public static function standard_reference_date() {
		return '2026-09-10';
	}

	public static function decimal( $value ) {
		return number_format( (float) $value, 2, '.', '' );
	}
}

final class Sidrena_Pricelist {
	public static function queue_regeneration() {
		++$GLOBALS['sidrena_queue_count'];
	}
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-services.php';

function sidrena_service_anchor_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$services = Sidrena_Services::instance();

// A service published directly after the reference date must snapshot the saved current price.
$GLOBALS['sidrena_service_meta'][101]['_sidrena_service_current_price'] = '49.90';
$new_post    = new WP_Post( 101, 'publish', '2026-09-20 09:00:00' );
$draft_before = new WP_Post( 101, 'draft', '2026-09-20 08:55:00' );
$services->after_insert_post( 101, $new_post, true, $draft_before );

sidrena_service_anchor_assert( '49.90' === get_post_meta( 101, '_sidrena_service_anchor_price', true ), 'Direct first publish did not snapshot the submitted current service price.' );
sidrena_service_anchor_assert( '2026-09-20' === get_post_meta( 101, '_sidrena_service_anchor_date', true ), 'Direct first publish did not preserve the first publication date.' );
sidrena_service_anchor_assert( 'custom' === get_post_meta( 101, '_sidrena_service_reference_group', true ), 'New service must be marked as a custom first-listing Sidrena reference.' );
sidrena_service_anchor_assert( 1 === $GLOBALS['sidrena_queue_count'], 'First publish must queue exactly one cjenik regeneration.' );

// Later edits must not replace the original anchor.
$GLOBALS['sidrena_service_meta'][101]['_sidrena_service_current_price'] = '59.90';
$published_before = new WP_Post( 101, 'publish', '2026-09-20 09:00:00' );
$edited_post      = new WP_Post( 101, 'publish', '2026-09-20 09:00:00' );
$services->after_insert_post( 101, $edited_post, true, $published_before );

sidrena_service_anchor_assert( '49.90' === get_post_meta( 101, '_sidrena_service_anchor_price', true ), 'Later edits must not overwrite the original service anchor price.' );
sidrena_service_anchor_assert( 2 === $GLOBALS['sidrena_queue_count'], 'Published service edits must queue cjenik regeneration once.' );

// A service first published before the reference date must not be auto-snapshotted by the 10.09.2026 rule.
$GLOBALS['sidrena_service_meta'][202]['_sidrena_service_current_price'] = '39.00';
$old_post   = new WP_Post( 202, 'publish', '2026-09-09 12:00:00' );
$old_before = new WP_Post( 202, 'draft', '2026-09-09 11:55:00' );
$services->after_insert_post( 202, $old_post, true, $old_before );

sidrena_service_anchor_assert( '' === get_post_meta( 202, '_sidrena_service_anchor_price', true ), 'Pre-reference service must not receive a 10.09.2026 first-publication anchor.' );
sidrena_service_anchor_assert( 3 === $GLOBALS['sidrena_queue_count'], 'Pre-reference publish must still queue cjenik regeneration once.' );

// Non-service posts are outside this lifecycle.
$other_post = new WP_Post( 303, 'publish', '2026-09-20 12:00:00', 'post' );
$services->after_insert_post( 303, $other_post, true, null );
sidrena_service_anchor_assert( 3 === $GLOBALS['sidrena_queue_count'], 'Non-service posts must not queue Sidrena service regeneration.' );

fwrite( STDOUT, "Sidrena service first-publish anchor smoke test passed.\n" );
