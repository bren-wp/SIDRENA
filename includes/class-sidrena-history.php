<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce price history and the frozen reference used for a price-reduction
 * campaign. The history is intentionally longer than 30 days so that a future
 * reduction can be calculated from the price state that was valid at the start
 * of the statutory look-back window.
 */
final class Sidrena_History {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'woocommerce_update_product', array( $this, 'capture_product' ), 20 );
		add_action( 'woocommerce_update_product_variation', array( $this, 'capture_product' ), 20 );
		add_action( 'sidrena_daily_generation', array( $this, 'daily_snapshot' ), 5 );
		add_action( 'sidrena_history_seed', array( $this, 'seed_history' ) );
		add_action( 'wc_product_start_scheduled_sale', array( $this, 'capture_scheduled_sale_start' ), 20 );
		add_action( 'wc_product_end_scheduled_sale', array( $this, 'capture_scheduled_sale_end' ), 20 );
	}


	public function capture_scheduled_sale_start( $product_id ) {
		$this->capture_product( $product_id, 'scheduled-sale-start' );
		Sidrena_Pricelist::queue_regeneration();
	}

	public function capture_scheduled_sale_end( $product_id ) {
		$this->capture_product( $product_id, 'scheduled-sale-end' );
		Sidrena_Pricelist::queue_regeneration();
	}

	public function capture_product( $product_id, $source = 'save' ) {
		$settings = Sidrena_Utils::settings();
		if ( 'yes' !== $settings['track_price_history'] || ! Sidrena_Utils::is_woocommerce_active() ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		$parent_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$var_id    = $product->is_type( 'variation' ) ? $product->get_id() : 0;
		$this->insert_if_changed(
			$parent_id,
			$var_id,
			$product->get_price( 'edit' ),
			$product->get_regular_price( 'edit' ),
			$product->get_sale_price( 'edit' ),
			$source
		);

		$this->sync_sale_reference( $product );
	}

	private function insert_if_changed( $product_id, $variation_id, $price, $regular, $sale, $source ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sidrena_price_history';
		$key   = $variation_id ? 'variation_id' : 'product_id';
		$id    = $variation_id ? $variation_id : $product_id;
		$last  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT price, regular_price, sale_price FROM {$table} WHERE {$key} = %d ORDER BY recorded_at DESC, id DESC LIMIT 1",
				$id
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table and fixed column names.

		$price   = '' === $price ? null : (float) $price;
		$regular = '' === $regular ? null : (float) $regular;
		$sale    = '' === $sale ? null : (float) $sale;
		if (
			$last
			&& $this->same_numeric_value( $last['price'], $price )
			&& $this->same_numeric_value( $last['regular_price'], $regular )
			&& $this->same_numeric_value( $last['sale_price'], $sale )
		) {
			return;
		}

		$wpdb->insert(
			$table,
			array(
				'product_id'    => $product_id,
				'variation_id'  => $variation_id,
				'price'         => $price,
				'regular_price' => $regular,
				'sale_price'    => $sale,
				'recorded_at'   => current_time( 'mysql' ),
				'source'        => sanitize_key( $source ),
			),
			array( '%d', '%d', '%f', '%f', '%f', '%s', '%s' )
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

	/**
	 * Freeze the lowest pre-reduction price when a new sale starts. The frozen
	 * value remains stable for one uninterrupted reduction campaign.
	 */
	private function sync_sale_reference( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$id        = $product->get_id();
		$is_sale   = $product->is_on_sale( 'edit' );
		$was_sale  = 'yes' === get_post_meta( $id, '_sidrena_sale_active', true );
		$exemption = sanitize_key( (string) get_post_meta( $id, '_sidrena_sale_reference_exemption', true ) );

		if ( ! $is_sale ) {
			if ( $was_sale ) {
				delete_post_meta( $id, '_sidrena_sale_reference_price' );
				delete_post_meta( $id, '_sidrena_sale_reference_source' );
				delete_post_meta( $id, '_sidrena_sale_reference_started_at' );
				delete_post_meta( $id, '_sidrena_sale_reference_coverage_from' );
			}
			update_post_meta( $id, '_sidrena_sale_active', 'no' );
			return;
		}

		update_post_meta( $id, '_sidrena_sale_active', 'yes' );
		if ( $was_sale && get_post_meta( $id, '_sidrena_sale_reference_source', true ) ) {
			return;
		}

		$start = $this->sale_start_datetime( $product );
		update_post_meta( $id, '_sidrena_sale_reference_started_at', $start->format( 'Y-m-d H:i:s' ) );

		if ( in_array( $exemption, array( 'perishable', 'fast_expiry' ), true ) ) {
			update_post_meta( $id, '_sidrena_sale_reference_source', 'exempt' );
			delete_post_meta( $id, '_sidrena_sale_reference_price' );
			return;
		}

		$manual = Sidrena_Utils::decimal( get_post_meta( $id, '_sidrena_lowest_30_manual', true ) );
		if ( '' !== $manual ) {
			update_post_meta( $id, '_sidrena_sale_reference_price', $manual );
			update_post_meta( $id, '_sidrena_sale_reference_source', 'manual' );
			return;
		}

		$calculated = $this->calculate_lowest_before( $product, $start );
		if ( $calculated['ready'] ) {
			update_post_meta( $id, '_sidrena_sale_reference_price', Sidrena_Utils::decimal( $calculated['price'] ) );
			update_post_meta( $id, '_sidrena_sale_reference_source', 'auto' );
			update_post_meta( $id, '_sidrena_sale_reference_coverage_from', $calculated['coverage_from'] );
			return;
		}

		delete_post_meta( $id, '_sidrena_sale_reference_price' );
		update_post_meta( $id, '_sidrena_sale_reference_source', 'incomplete' );
		if ( ! empty( $calculated['coverage_from'] ) ) {
			update_post_meta( $id, '_sidrena_sale_reference_coverage_from', $calculated['coverage_from'] );
		}
	}

	private function sale_start_datetime( $product ) {
		$from = $product->get_date_on_sale_from( 'edit' );
		if ( $from instanceof WC_DateTime ) {
			try {
				return new DateTimeImmutable( $from->date( 'Y-m-d H:i:s' ), wp_timezone() );
			} catch ( Exception $e ) {
				// Fall through to the current WordPress time.
			}
		}
		return new DateTimeImmutable( 'now', wp_timezone() );
	}

	/**
	 * Calculate the lowest effective price in the 30 days immediately preceding
	 * the campaign. We require a known price state at or before the beginning of
	 * the window; otherwise the plugin reports an incomplete history rather than
	 * inventing a legal reference value.
	 */
	private function calculate_lowest_before( $product, DateTimeImmutable $start ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sidrena_price_history';
		$id    = $product->get_id();
		$key   = $product->is_type( 'variation' ) ? 'variation_id' : 'product_id';
		$window_start = $start->modify( '-30 days' );
		$start_sql    = $start->format( 'Y-m-d H:i:s' );
		$window_sql   = $window_start->format( 'Y-m-d H:i:s' );

		$baseline = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT price, recorded_at FROM {$table} WHERE {$key} = %d AND recorded_at <= %s ORDER BY recorded_at DESC, id DESC LIMIT 1",
				$id,
				$window_sql
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table and fixed column name.

		$first = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MIN(recorded_at) FROM {$table} WHERE {$key} = %d",
				$id
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table and fixed column name.

		if ( ! $baseline || null === $baseline['price'] || '' === $baseline['price'] ) {
			return array(
				'ready'         => false,
				'price'         => '',
				'coverage_from' => $first ? sanitize_text_field( $first ) : '',
			);
		}

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT price FROM {$table} WHERE {$key} = %d AND recorded_at > %s AND recorded_at < %s AND price IS NOT NULL ORDER BY recorded_at ASC, id ASC",
				$id,
				$window_sql,
				$start_sql
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table and fixed column name.

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

	/**
	 * Public status object for front-end display and admin diagnostics.
	 */
	public static function sale_reference( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return array( 'status' => 'not_applicable', 'price' => '', 'source' => '' );
		}

		$id        = $product->get_id();
		$exemption = sanitize_key( (string) get_post_meta( $id, '_sidrena_sale_reference_exemption', true ) );
		if ( in_array( $exemption, array( 'perishable', 'fast_expiry' ), true ) ) {
			return array( 'status' => 'exempt', 'price' => '', 'source' => $exemption );
		}
		if ( ! $product->is_on_sale() ) {
			return array( 'status' => 'not_applicable', 'price' => '', 'source' => '' );
		}

		$manual = Sidrena_Utils::decimal( get_post_meta( $id, '_sidrena_lowest_30_manual', true ) );
		if ( '' !== $manual ) {
			return array( 'status' => 'ready', 'price' => (float) $manual, 'source' => 'manual' );
		}

		$source = sanitize_key( (string) get_post_meta( $id, '_sidrena_sale_reference_source', true ) );
		$price  = Sidrena_Utils::decimal( get_post_meta( $id, '_sidrena_sale_reference_price', true ) );
		if ( 'auto' === $source && '' !== $price ) {
			return array( 'status' => 'ready', 'price' => (float) $price, 'source' => 'auto' );
		}

		return array(
			'status'        => 'incomplete',
			'price'         => '',
			'source'        => $source ?: 'incomplete',
			'coverage_from' => sanitize_text_field( (string) get_post_meta( $id, '_sidrena_sale_reference_coverage_from', true ) ),
		);
	}

	public static function count_rows() {
		global $wpdb;
		$table = $wpdb->prefix . 'sidrena_price_history';
		return absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
	}

	public function daily_snapshot() {
		$settings = Sidrena_Utils::settings();
		if ( 'yes' !== $settings['track_price_history'] || ! Sidrena_Utils::is_woocommerce_active() ) {
			return;
		}

		foreach ( $this->catalog_item_ids() as $id ) {
			$this->capture_product( $id, 'daily' );
		}
		$this->prune_history();
	}

	public function seed_history() {
		$settings = Sidrena_Utils::settings();
		if ( 'yes' !== $settings['track_price_history'] || ! Sidrena_Utils::is_woocommerce_active() ) {
			return;
		}
		foreach ( $this->catalog_item_ids() as $id ) {
			$this->capture_product( $id, 'seed' );
		}
		update_option( 'sidrena_history_seeded_at', current_time( 'mysql' ), false );
	}

	private function catalog_item_ids() {
		$page = 1;
		do {
			$query = new WC_Product_Query(
				array(
					'limit'   => 100,
					'page'    => $page,
					'status'  => array( 'publish' ),
					'return'  => 'objects',
					'orderby' => 'ID',
					'order'   => 'ASC',
				)
			);
			$products = $query->get_products();
			foreach ( $products as $product ) {
				if ( $product->is_type( 'variable' ) ) {
					foreach ( $product->get_children() as $variation_id ) {
						yield absint( $variation_id );
					}
				} else {
					yield absint( $product->get_id() );
				}
			}
			++$page;
		} while ( count( $products ) === 100 );
	}

	private function prune_history() {
		global $wpdb;
		$table  = $wpdb->prefix . 'sidrena_price_history';
		$cutoff = wp_date( 'Y-m-d H:i:s', time() - ( 400 * DAY_IN_SECONDS ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE recorded_at < %s",
				$cutoff
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
	}
}
