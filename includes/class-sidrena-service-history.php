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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks service price changes and freezes the 30-day pre-reduction reference
 * when a service enters a special form of sale. This is separate from the
 * public 30+ day cjenik archive: the archive preserves published files, while
 * this table records price states used for consumer-price diagnostics.
 */
final class Sidrena_Service_History {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'save_post_sidrena_service', array( $this, 'capture_service' ), 30, 2 );
		add_action( 'sidrena_daily_generation', array( $this, 'daily_snapshot' ), 6 );
		add_action( 'sidrena_history_seed', array( $this, 'seed_history' ), 20 );
	}

	public function capture_service( $service_id, $post = null, $source = 'save' ) {
		$service_id = absint( $service_id );
		if ( ! $service_id || 'yes' !== Sidrena_Utils::settings()['track_price_history'] ) {
			return;
		}
		if ( $post instanceof WP_Post && 'sidrena_service' !== $post->post_type ) {
			return;
		}
		if ( wp_is_post_revision( $service_id ) || 'trash' === get_post_status( $service_id ) ) {
			return;
		}

		// Freeze the reference before recording the newly saved sale price.
		$this->sync_sale_reference( $service_id );
		$this->insert_if_changed(
			$service_id,
			get_post_meta( $service_id, '_sidrena_service_current_price', true ),
			$source
		);
	}

	private function insert_if_changed( $service_id, $price, $source ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sidrena_service_price_history';
		$last  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT price FROM {$table} WHERE service_id = %d ORDER BY recorded_at DESC, id DESC LIMIT 1",
				$service_id
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.

		$price = Sidrena_Utils::decimal( $price );
		if ( '' === $price ) {
			$price = null;
		}
		if ( $this->same_numeric_value( $last, $price ) ) {
			return;
		}

		$wpdb->insert(
			$table,
			array(
				'service_id'  => $service_id,
				'price'       => $price,
				'recorded_at' => current_time( 'mysql' ),
				'source'      => sanitize_key( $source ),
			),
			array( '%d', '%f', '%s', '%s' )
		);
	}

	private function same_numeric_value( $stored, $current ) {
		if ( null === $current ) {
			return null === $stored || '' === $stored;
		}
		if ( null === $stored || '' === $stored ) {
			return false;
		}
		return abs( (float) $stored - (float) $current ) < 0.000001;
	}

	private function sync_sale_reference( $service_id ) {
		$is_sale  = 'yes' === get_post_meta( $service_id, '_sidrena_service_sale', true );
		$was_sale = 'yes' === get_post_meta( $service_id, '_sidrena_service_sale_active', true );

		if ( ! $is_sale ) {
			if ( $was_sale ) {
				delete_post_meta( $service_id, '_sidrena_service_sale_reference_price' );
				delete_post_meta( $service_id, '_sidrena_service_sale_reference_source' );
				delete_post_meta( $service_id, '_sidrena_service_sale_reference_started_at' );
				delete_post_meta( $service_id, '_sidrena_service_sale_reference_coverage_from' );
			}
			update_post_meta( $service_id, '_sidrena_service_sale_active', 'no' );
			return;
		}

		update_post_meta( $service_id, '_sidrena_service_sale_active', 'yes' );
		if ( $was_sale && get_post_meta( $service_id, '_sidrena_service_sale_reference_source', true ) ) {
			return;
		}

		$started = new DateTimeImmutable( 'now', wp_timezone() );
		update_post_meta( $service_id, '_sidrena_service_sale_reference_started_at', $started->format( 'Y-m-d H:i:s' ) );

		$not_applicable = sanitize_key( (string) get_post_meta( $service_id, '_sidrena_service_lowest_30_exception', true ) );
		if ( in_array( $not_applicable, array( 'advertising', 'distance', 'off_premises' ), true ) ) {
			update_post_meta( $service_id, '_sidrena_service_sale_reference_source', 'exempt' );
			delete_post_meta( $service_id, '_sidrena_service_sale_reference_price' );
			return;
		}

		$manual = Sidrena_Utils::decimal( get_post_meta( $service_id, '_sidrena_service_lowest_30_manual', true ) );
		if ( '' !== $manual ) {
			update_post_meta( $service_id, '_sidrena_service_sale_reference_price', $manual );
			update_post_meta( $service_id, '_sidrena_service_sale_reference_source', 'manual' );
			return;
		}

		$calculated = $this->calculate_lowest_before( $service_id, $started );
		if ( $calculated['ready'] ) {
			update_post_meta( $service_id, '_sidrena_service_sale_reference_price', Sidrena_Utils::decimal( $calculated['price'] ) );
			update_post_meta( $service_id, '_sidrena_service_sale_reference_source', 'auto' );
			update_post_meta( $service_id, '_sidrena_service_sale_reference_coverage_from', $calculated['coverage_from'] );
			return;
		}

		delete_post_meta( $service_id, '_sidrena_service_sale_reference_price' );
		update_post_meta( $service_id, '_sidrena_service_sale_reference_source', 'incomplete' );
		if ( ! empty( $calculated['coverage_from'] ) ) {
			update_post_meta( $service_id, '_sidrena_service_sale_reference_coverage_from', $calculated['coverage_from'] );
		}
	}

	private function calculate_lowest_before( $service_id, DateTimeImmutable $start ) {
		global $wpdb;
		$table        = $wpdb->prefix . 'sidrena_service_price_history';
		$window_start = $start->modify( '-30 days' );
		$start_sql    = $start->format( 'Y-m-d H:i:s' );
		$window_sql   = $window_start->format( 'Y-m-d H:i:s' );

		$baseline = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT price, recorded_at FROM {$table} WHERE service_id = %d AND recorded_at <= %s ORDER BY recorded_at DESC, id DESC LIMIT 1",
				$service_id,
				$window_sql
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.

		$first = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MIN(recorded_at) FROM {$table} WHERE service_id = %d",
				$service_id
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.

		if ( ! $baseline || null === $baseline['price'] || '' === $baseline['price'] ) {
			return array(
				'ready'         => false,
				'price'         => '',
				'coverage_from' => $first ? sanitize_text_field( $first ) : '',
			);
		}

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT price FROM {$table} WHERE service_id = %d AND recorded_at > %s AND recorded_at < %s AND price IS NOT NULL ORDER BY recorded_at ASC, id ASC",
				$service_id,
				$window_sql,
				$start_sql
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.

		$values = array( (float) $baseline['price'] );
		foreach ( is_array( $rows ) ? $rows : array() as $value ) {
			if ( '' !== $value && null !== $value ) {
				$values[] = (float) $value;
			}
		}

		return array(
			'ready'         => ! empty( $values ),
			'price'         => empty( $values ) ? '' : min( $values ),
			'coverage_from' => sanitize_text_field( $baseline['recorded_at'] ),
		);
	}

	public static function sale_reference( $service_id ) {
		$service_id = absint( $service_id );
		if ( ! $service_id || 'yes' !== get_post_meta( $service_id, '_sidrena_service_sale', true ) ) {
			return array( 'status' => 'not_applicable', 'price' => '', 'source' => '' );
		}

		$exception = sanitize_key( (string) get_post_meta( $service_id, '_sidrena_service_lowest_30_exception', true ) );
		if ( in_array( $exception, array( 'advertising', 'distance', 'off_premises' ), true ) ) {
			return array( 'status' => 'exempt', 'price' => '', 'source' => $exception );
		}

		$manual = Sidrena_Utils::decimal( get_post_meta( $service_id, '_sidrena_service_lowest_30_manual', true ) );
		if ( '' !== $manual ) {
			return array( 'status' => 'ready', 'price' => (float) $manual, 'source' => 'manual' );
		}

		$source = sanitize_key( (string) get_post_meta( $service_id, '_sidrena_service_sale_reference_source', true ) );
		$price  = Sidrena_Utils::decimal( get_post_meta( $service_id, '_sidrena_service_sale_reference_price', true ) );
		if ( 'auto' === $source && '' !== $price ) {
			return array( 'status' => 'ready', 'price' => (float) $price, 'source' => 'auto' );
		}

		return array(
			'status'        => 'incomplete',
			'price'         => '',
			'source'        => $source ?: 'incomplete',
			'coverage_from' => sanitize_text_field( (string) get_post_meta( $service_id, '_sidrena_service_sale_reference_coverage_from', true ) ),
		);
	}


	public static function recent_changes( $limit = 5 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sidrena_service_price_history';
		$limit = min( 20, max( 1, absint( $limit ) ) );
		$scan  = max( 120, $limit * 30 );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, service_id, price, recorded_at
				FROM {$table}
				WHERE price IS NOT NULL
				ORDER BY id DESC
				LIMIT %d",
				$scan
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.

		$newest  = array();
		$changes = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$service_id = absint( $row['service_id'] );
			if ( ! $service_id ) {
				continue;
			}

			if ( ! isset( $newest[ $service_id ] ) ) {
				$newest[ $service_id ] = $row;
				continue;
			}

			$new = $newest[ $service_id ];
			if ( abs( (float) $new['price'] - (float) $row['price'] ) < 0.000001 ) {
				$newest[ $service_id ] = $row;
				continue;
			}

			$old = (float) $row['price'];
			$now = (float) $new['price'];
			$changes[] = array(
				'item_id'     => $service_id,
				'name'        => wp_strip_all_tags( get_the_title( $service_id ) ),
				'old_price'   => $old,
				'new_price'   => $now,
				'recorded_at' => sanitize_text_field( $new['recorded_at'] ),
				'change_pct'  => 0.0 !== $old ? ( ( $now - $old ) / $old ) * 100 : 0,
				'kind'        => 'service',
			);
			unset( $newest[ $service_id ] );

			if ( count( $changes ) >= $limit ) {
				break;
			}
		}

		return $changes;
	}

	public static function latest_series( $days = 30 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sidrena_service_price_history';
		$days  = min( 90, max( 7, absint( $days ) ) );

		$latest = $wpdb->get_row(
			"SELECT service_id, price, recorded_at
			FROM {$table}
			WHERE price IS NOT NULL
			ORDER BY id DESC LIMIT 1",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.

		if ( ! $latest ) {
			return array();
		}

		$service_id = absint( $latest['service_id'] );
		$cutoff_dt  = new DateTimeImmutable( '-' . $days . ' days', wp_timezone() );
		$cutoff     = $cutoff_dt->format( 'Y-m-d H:i:s' );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT price, recorded_at FROM {$table}
				WHERE service_id = %d AND price IS NOT NULL AND recorded_at >= %s
				ORDER BY recorded_at ASC, id ASC",
				$service_id,
				$cutoff
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.

		if ( empty( $rows ) ) {
			$rows = array( $latest );
		}

		$points = array();
		foreach ( $rows as $row ) {
			$points[] = array(
				'price'       => (float) $row['price'],
				'recorded_at' => sanitize_text_field( $row['recorded_at'] ),
			);
		}

		$prices  = wp_list_pluck( $points, 'price' );
		$first   = reset( $prices );
		$current = end( $prices );

		return array(
			'item_id'    => $service_id,
			'name'       => wp_strip_all_tags( get_the_title( $service_id ) ),
			'current'    => (float) $current,
			'minimum'    => (float) min( $prices ),
			'maximum'    => (float) max( $prices ),
			'change_pct' => 0.0 !== (float) $first ? ( ( (float) $current - (float) $first ) / (float) $first ) * 100 : 0,
			'points'     => $points,
			'days'       => $days,
			'kind'       => 'service',
		);
	}

	public static function count_rows() {
		global $wpdb;
		$table = $wpdb->prefix . 'sidrena_service_price_history';
		return absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
	}

	public function daily_snapshot() {
		if ( 'yes' !== Sidrena_Utils::settings()['track_price_history'] ) {
			return;
		}
		foreach ( $this->service_ids() as $service_id ) {
			$this->capture_service( $service_id, null, 'daily' );
		}
		$this->prune_history();
	}

	public function seed_history() {
		if ( 'yes' !== Sidrena_Utils::settings()['track_price_history'] ) {
			return;
		}
		foreach ( $this->service_ids() as $service_id ) {
			$this->capture_service( $service_id, null, 'seed' );
		}
	}

	private function service_ids() {
		$page = 1;
		do {
			$query = new WP_Query(
				array(
					'post_type'      => 'sidrena_service',
					'post_status'    => 'publish',
					'posts_per_page' => 250,
					'paged'          => $page,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);
			foreach ( $query->posts as $service_id ) {
				yield absint( $service_id );
			}
			$count = count( $query->posts );
			++$page;
		} while ( 250 === $count );
	}

	private function prune_history() {
		global $wpdb;
		$table  = $wpdb->prefix . 'sidrena_service_price_history';
		$cutoff = wp_date( 'Y-m-d H:i:s', time() - ( 400 * DAY_IN_SECONDS ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE recorded_at < %s",
				$cutoff
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
	}
}
