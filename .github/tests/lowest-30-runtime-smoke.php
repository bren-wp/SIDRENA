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
define( 'ARRAY_A', 'ARRAY_A' );
define( 'DAY_IN_SECONDS', 86400 );

function absint( $value ) { return abs( (int) $value ); }
function wp_date( $format, $timestamp = null ) { return gmdate( $format, null === $timestamp ? time() : (int) $timestamp ); }

final class Sidrena_Utils {
	public static function is_woocommerce_active() { return true; }
	public static function decimal( $value ) {
		if ( '' === $value || null === $value ) { return ''; }
		return number_format( (float) $value, 4, '.', '' );
	}
}

class WC_Product {
	protected $id = 10;
	protected $parent_id = 0;
	protected $sale = '80';
	protected $regular = '100';
	protected $manual = '';
	protected $sale_start = 0;

	public function is_on_sale( $context = 'view' ) { unset( $context ); return true; }
	public function get_sale_price( $context = 'view' ) { unset( $context ); return $this->sale; }
	public function get_regular_price( $context = 'view' ) { unset( $context ); return $this->regular; }
	public function get_meta( $key, $single = true ) { unset( $single ); return '_sidrena_lowest_30_verified' === $key ? $this->manual : ''; }
	public function get_date_on_sale_from( $context = 'view' ) {
		unset( $context );
		if ( ! $this->sale_start ) { return null; }
		return new Sidrena_Test_Date( $this->sale_start );
	}
	public function is_type( $type ) { return 'variation' === $type && 0 !== $this->parent_id; }
	public function get_id() { return $this->id; }
	public function get_parent_id() { return $this->parent_id; }
	public function configure( $manual, $sale_start ) { $this->manual = $manual; $this->sale_start = $sale_start; }
}

final class Sidrena_Test_Date {
	private $timestamp;
	public function __construct( $timestamp ) { $this->timestamp = (int) $timestamp; }
	public function getTimestamp() { return $this->timestamp; }
}

final class Sidrena_Test_WPDB {
	public $prefix = 'wp_';
	public $baseline = null;
	public $window_min = null;
	public $latest_history = null;
	public $previous_different_id = 0;
	public $inferred_start = '';
	public $queries = array();

	public function prepare( $query ) { return $query; }
	public function get_row( $query, $output ) {
		unset( $output );
		$this->queries[] = $query;
		return false !== strpos( $query, 'SELECT id, price' ) ? $this->latest_history : $this->baseline;
	}
	public function get_var( $query ) {
		$this->queries[] = $query;
		if ( false !== strpos( $query, 'MIN(price)' ) ) {
			return $this->window_min;
		}
		if ( false !== strpos( $query, 'ABS(price - %f) >= 0.000001' ) ) {
			return $this->previous_different_id;
		}
		if ( false !== strpos( $query, 'SELECT recorded_at' ) ) {
			return $this->inferred_start;
		}
		return null;
	}
}

function sidrena_lowest_30_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-sidrena-history.php';

global $wpdb;
$wpdb = new Sidrena_Test_WPDB();

$product = new WC_Product();
$sale_start = strtotime( '2026-09-29 08:00:00 UTC' );
$product->configure( '', $sale_start );
$wpdb->baseline = array( 'price' => '95.00', 'recorded_at' => '2026-08-30 08:00:00' );
$wpdb->window_min = '90.00';
$ready = Sidrena_History::instance()->lowest_30_day_reference( $product );
sidrena_lowest_30_assert( 'ready' === $ready['status'], 'Complete 30-day history must produce a ready result.' );
sidrena_lowest_30_assert( abs( (float) $ready['price'] - 90.0 ) < 0.0001, '30-day history must return the lowest effective price before the reduction.' );
sidrena_lowest_30_assert( 'history' === $ready['source'], 'Complete history must be identified as the source.' );
sidrena_lowest_30_assert(
	1 === count( array_filter( $wpdb->queries, static function ( $query ) { return false !== strpos( $query, 'MIN(price)' ); } ) ),
	'30-day minimum must use one exact database aggregate instead of loading a capped list of history rows.'
);
sidrena_lowest_30_assert(
	0 === count( array_filter( $wpdb->queries, static function ( $query ) { return false !== strpos( $query, 'LIMIT 2000' ); } ) ),
	'30-day minimum must not truncate high-frequency history at 2,000 rows.'
);

$wpdb->baseline = null;
$wpdb->window_min = '91.00';
$product->configure( '88.50', $sale_start );
$manual = Sidrena_History::instance()->lowest_30_day_reference( $product );
sidrena_lowest_30_assert( 'ready' === $manual['status'] && 'manual' === $manual['source'], 'Verified manual fallback must be used when full history is unavailable.' );
sidrena_lowest_30_assert( abs( (float) $manual['price'] - 88.5 ) < 0.0001, 'Verified manual fallback amount changed unexpectedly.' );

$product->configure( '', $sale_start );
$incomplete = Sidrena_History::instance()->lowest_30_day_reference( $product );
sidrena_lowest_30_assert( 'incomplete' === $incomplete['status'], 'Incomplete history without a verified fallback must never fabricate a compliant value.' );

$product->configure( '', 0 );
$wpdb->queries = array();
$wpdb->latest_history = array( 'id' => 5005, 'price' => '80.00' );
$wpdb->previous_different_id = 4;
$wpdb->inferred_start = '2026-09-01 09:15:00';
$wpdb->baseline = array( 'price' => '95.00', 'recorded_at' => '2026-08-02 09:15:00' );
$wpdb->window_min = '90.00';
$inferred = Sidrena_History::instance()->lowest_30_day_reference( $product );
sidrena_lowest_30_assert( 'ready' === $inferred['status'], 'Sale-start inference must remain ready even when the current price run is longer than 500 history rows.' );
sidrena_lowest_30_assert(
	0 === count( array_filter( $wpdb->queries, static function ( $query ) { return false !== strpos( $query, 'LIMIT 500' ); } ) ),
	'Sale-start inference must not truncate the history scan at 500 rows.'
);
sidrena_lowest_30_assert(
	1 === count( array_filter( $wpdb->queries, static function ( $query ) { return false !== strpos( $query, 'ABS(price - %f) >= 0.000001' ); } ) ),
	'Sale-start inference must use an exact previous-price boundary lookup.'
);

fwrite( STDOUT, "SIDRENA separate 30-day minimum runtime smoke test passed.\n" );
