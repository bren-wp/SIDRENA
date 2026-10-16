<?php
/**
 * Observed WooCommerce selling-price history. This is not the legally fixed
 * reference price and cannot reconstruct changes before tracking began.
 *
 * @package Brendigo_Sidrena_Cijena
 */
defined( 'ABSPATH' ) || exit;

final class Brendigo_Sidrena_Cijena_Price_History {
	const SCHEMA_OPTION = 'brendigo_sidrena_history_schema';
	const SCHEMA_VERSION = '2';
	const SEED_HOOK = 'brendigo_sidrena_history_seed_batch';
	const SEED_PAGE = 'brendigo_sidrena_history_seed_page';
	const MANUAL_SEED_HOOK = 'brendigo_sidrena_manual_history_seed_batch';
	const MANUAL_SEED_OFFSET = 'brendigo_sidrena_history_manual_seed_offset';
	private static $instance;
	private $writing = false;
	/** Latest observed price in the current request (avoids redundant Woo hooks). */
	private $recent_prices = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'brendigo_sidrena_price_history';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			source varchar(12) NOT NULL DEFAULT 'woo',
			price decimal(20,6) NOT NULL,
			currency char(3) NOT NULL,
			observed_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY product_observed (product_id,observed_at),
			KEY source_product_observed (source,product_id,observed_at),
			KEY source_product_latest (source,product_id,id),
			KEY observed_at (observed_at)
		) {$charset};";
		dbDelta( $sql );
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
	}

	private function __construct() {
		add_action( 'init', array( $this, 'maybe_upgrade' ), 8 );
		add_action( self::SEED_HOOK, array( $this, 'seed_batch' ) );
		add_action( self::MANUAL_SEED_HOOK, array( $this, 'seed_manual_batch' ) );
		add_action( 'admin_init', array( $this, 'seed_without_cron' ), 25 );
		add_shortcode( 'sidrena_povijest_cijena', array( $this, 'shortcode' ) );
		add_shortcode( 'sidrena_najniza_prije_snizenja', array( $this, 'shortcode_before_sale' ) );
		add_shortcode( 'sidrena_graf_cijena', array( $this, 'shortcode_chart' ) );
		if ( ! Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ) {
			return;
		}
		foreach ( array( 'woocommerce_new_product', 'woocommerce_update_product', 'woocommerce_new_product_variation', 'woocommerce_update_product_variation' ) as $hook ) {
			add_action( $hook, array( $this, 'capture_product' ), 99, 1 );
		}
		add_action( 'added_post_meta', array( $this, 'capture_price_meta' ), 99, 4 );
		add_action( 'updated_post_meta', array( $this, 'capture_price_meta' ), 99, 4 );
		add_action( 'woocommerce_after_product_object_save', array( $this, 'capture_saved_product' ), 100, 1 );
		add_action( 'woocommerce_product_options_pricing', array( $this, 'render_admin_history' ), 30 );
	}

	public function maybe_upgrade() {
		if ( self::SCHEMA_VERSION !== get_option( self::SCHEMA_OPTION, '' ) ) {
			self::install();
		}
		// The old Woo rows are retained by dbDelta's DEFAULT 'woo' column value.
		// Index zero is a one-time baseline of CURRENT manual prices, not a guess
		// at values from previous dates.
		if ( false === get_option( self::MANUAL_SEED_OFFSET, false ) ) {
			add_option( self::MANUAL_SEED_OFFSET, 0, '', false );
		}
		if ( (int) get_option( self::MANUAL_SEED_OFFSET, -1 ) >= 0 && ! wp_next_scheduled( self::MANUAL_SEED_HOOK ) ) {
			wp_schedule_single_event( time() + 60, self::MANUAL_SEED_HOOK );
		}
		if ( Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() && false === get_option( self::SEED_PAGE, false ) ) {
			add_option( self::SEED_PAGE, 1, '', false );
		}
		if ( Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() && (int) get_option( self::SEED_PAGE, 0 ) > 0 && ! wp_next_scheduled( self::SEED_HOOK ) ) {
			wp_schedule_single_event( time() + 45, self::SEED_HOOK );
		}
	}

	/** Populate a baseline for unchanged existing products/variations without inventing past values. */
	public function seed_batch() {
		$page = (int) get_option( self::SEED_PAGE, 0 );
		if ( ! $page || ! Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ) {
			return;
		}
		$ids = get_posts( array(
			'post_type' => array( 'product', 'product_variation' ),
			'post_status' => array( 'publish', 'private', 'inherit' ),
			'posts_per_page' => 100,
			'paged' => $page,
			'fields' => 'ids',
			'orderby' => 'ID',
			'order' => 'ASC',
			'no_found_rows' => true,
		) );
		foreach ( $ids as $id ) {
			$this->capture_product( $id );
		}
		if ( count( $ids ) < 100 ) {
			update_option( self::SEED_PAGE, 0, false );
		} else {
			update_option( self::SEED_PAGE, $page + 1, false );
			if ( ! wp_next_scheduled( self::SEED_HOOK ) ) {
				wp_schedule_single_event( time() + 60, self::SEED_HOOK );
			}
		}
	}

	public function seed_without_cron() {
		if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON || ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() ) {
			return;
		}
		if ( get_option( self::SEED_PAGE, 0 ) && ! get_transient( 'brendigo_sidrena_history_seed_throttle' ) ) {
			set_transient( 'brendigo_sidrena_history_seed_throttle', 1, MINUTE_IN_SECONDS );
			$this->seed_batch();
		}
		if ( (int) get_option( self::MANUAL_SEED_OFFSET, -1 ) >= 0 && ! get_transient( 'brendigo_sidrena_history_manual_throttle' ) ) {
			set_transient( 'brendigo_sidrena_history_manual_throttle', 1, MINUTE_IN_SECONDS );
			$this->seed_manual_batch();
		}
	}

	/** Record a baseline for standalone products and services in bounded batches. */
	public function seed_manual_batch() {
		$offset = (int) get_option( self::MANUAL_SEED_OFFSET, -1 );
		if ( $offset < 0 ) {
			return;
		}
		$rows = Brendigo_Sidrena_Cijena_Catalog::instance()->all();
		$batch = array_slice( $rows, $offset, 150 );
		foreach ( $batch as $row ) {
			$this->record_catalog_row( $row );
		}
		$next = $offset + count( $batch );
		if ( $next >= count( $rows ) ) {
			update_option( self::MANUAL_SEED_OFFSET, -1, false );
		} else {
			update_option( self::MANUAL_SEED_OFFSET, $next, false );
			if ( ! wp_next_scheduled( self::MANUAL_SEED_HOOK ) ) {
				wp_schedule_single_event( time() + 60, self::MANUAL_SEED_HOOK );
			}
		}
	}

	/** Track only changed effective selling prices in the manual catalog. */
	public function capture_catalog_changes( $previous, $current ) {
		$before = array();
		foreach ( $previous as $row ) {
			if ( isset( $row['id'] ) ) {
				$before[ (string) $row['id'] ] = $this->catalog_selling_price( $row );
			}
		}
		foreach ( $current as $row ) {
			$id = isset( $row['id'] ) ? (string) $row['id'] : '';
			if ( '' !== $id && ( ! array_key_exists( $id, $before ) || $before[ $id ] !== $this->catalog_selling_price( $row ) ) ) {
				$this->record_catalog_row( $row );
			}
		}
	}

	/** @return string Empty if no valid selling price was provided. */
	private function catalog_selling_price( $row ) {
		$sale = isset( $row['cijena_akcijska'] ) ? (string) $row['cijena_akcijska'] : '';
		$regular = isset( $row['cijena_redovna'] ) ? (string) $row['cijena_redovna'] : '';
		return '' !== $sale ? $sale : $regular;
	}

	/** Public internal API for current standalone catalog prices. */
	public function record_catalog_row( $row ) {
		$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
		$price = $this->catalog_selling_price( $row );
		if ( ! $id || '' === $price ) {
			return false;
		}
		return $this->record_price( $id, $price, 'EUR', 'manual' );
	}

	/** One source-aware write, protecting IDs that overlap between Woo and manual items. */
	private function record_price( $product_id, $raw, $currency, $source ) {
		if ( ! is_numeric( $raw ) || ! is_finite( (float) $raw ) || (float) $raw < 0 || (float) $raw > 999999999999.99 ) {
			return false;
		}
		$amount = number_format( (float) $raw, 6, '.', '' );
		global $wpdb;
		$table = self::table_name();
		$cache_key = $source . ':' . absint( $product_id );
		if ( ! array_key_exists( $cache_key, $this->recent_prices ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh price history from the plugin-owned audit table is required before inserting a distinct sample.
			$this->recent_prices[ $cache_key ] = $wpdb->get_row( $wpdb->prepare( 'SELECT price, currency FROM %i WHERE source = %s AND product_id = %d ORDER BY id DESC LIMIT 1', $table, $source, $product_id ) );
		}
		$last = $this->recent_prices[ $cache_key ];
		if ( $last && $currency === $last->currency && abs( (float) $last->price - (float) $amount ) < 0.000001 ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Append-only price observations require the dedicated table; WP metadata is not an equivalent store.
		$saved = false !== $wpdb->insert( $table, array( 'source' => $source, 'product_id' => $product_id, 'price' => $amount, 'currency' => $currency, 'observed_at' => current_time( 'mysql', true ) ), array( '%s', '%d', '%s', '%s', '%s' ) );
		if ( $saved ) {
			$this->recent_prices[ $cache_key ] = (object) array( 'price' => $amount, 'currency' => $currency );
			wp_cache_delete( 'chart:' . $source . ':' . absint( $product_id ) . ':' . $currency, 'brendigo_sidrena_history' );
		}
		return $saved;
	}

	/** The final CRUD hook runs after WooCommerce has persisted and invalidated caches. */
	public function capture_saved_product( $product ) {
		if ( $product instanceof WC_Product ) {
			$this->capture_product( $product->get_id() );
		}
	}

	public function capture_price_meta( $meta_id, $object_id, $meta_key, $value ) {
		if ( ! in_array( $meta_key, array( '_price', '_sale_price', '_regular_price' ), true ) ) {
			return;
		}
		if ( in_array( get_post_type( $object_id ), array( 'product', 'product_variation' ), true ) ) {
			$this->capture_product( $object_id );
		}
	}

	public function capture_product( $product_id ) {
		if ( $this->writing || ! function_exists( 'wc_get_product' ) ) {
			return false;
		}
		$this->writing = true;
		try {
			$product = wc_get_product( absint( $product_id ) );
			if ( ! $product || $product->is_type( 'variable' ) || $product->is_type( 'grouped' ) ) {
				return false;
			}
			$raw = (string) $product->get_price( 'edit' );
			if ( '' === $raw || ! is_numeric( $raw ) || (float) $raw < 0 ) {
				return false;
			}
			$amount = wc_format_decimal( $raw, 6 );
			$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR';
			$currency = strtoupper( substr( sanitize_text_field( $currency ), 0, 3 ) );
			return $this->record_price( $product->get_id(), $amount, $currency, 'woo' );
		} finally {
			$this->writing = false;
		}
	}

	/**
	 * Lowest observed price over the last 30 * 24h. 'complete' is true only when
	 * a prior observation establishes a baseline at the start of the window.
	 */
	public function lowest_30_days( $product_id, $source = 'woo' ) {
		global $wpdb;
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return null;
		}
		$table = self::table_name();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		$source = 'manual' === $source ? 'manual' : 'woo';
		$currency = 'woo' === $source && function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR';
		$currency = strtoupper( substr( $currency, 0, 3 ) );
		$baseline = $wpdb->get_row( $wpdb->prepare( 'SELECT price, observed_at FROM %i WHERE source = %s AND product_id = %d AND currency = %s AND observed_at <= %s ORDER BY observed_at DESC, id DESC LIMIT 1', $table, $source, $product_id, $currency, $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only aggregate of the plugin-owned history table; must reflect up-to-date legal-price evidence.
		$in_window = $wpdb->get_row( $wpdb->prepare( 'SELECT MIN(price) AS lowest, MIN(observed_at) AS first_observed, COUNT(*) AS samples FROM %i WHERE source = %s AND product_id = %d AND currency = %s AND observed_at > %s', $table, $source, $product_id, $currency, $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only aggregate of the plugin-owned history table; must reflect up-to-date legal-price evidence.
		if ( ! $baseline && ( ! $in_window || ! $in_window->samples ) ) {
			return null;
		}
		$minimum = $baseline ? (float) $baseline->price : PHP_FLOAT_MAX;
		if ( $in_window && $in_window->samples ) {
			$minimum = min( $minimum, (float) $in_window->lowest );
		}
		return array(
			'price'       => $minimum,
			'currency'    => $currency,
			'complete'    => (bool) $baseline,
			'first_seen'  => $baseline ? $baseline->observed_at : $in_window->first_observed,
		);
	}

	/**
	 * Minimum selling price during 30 complete days BEFORE a specified sale start.
	 * The current discount itself is excluded from the calculation.
	 * If the 30-day baseline is missing, complete=false and the figure is indicative only.
	 * Dates are interpreted in the site timezone, stored samples are UTC.
	 */
	public function lowest_before_sale( $product_id, $sale_start, $source = 'woo' ) {
		$product_id = absint( $product_id );
		if ( ! $product_id || ! is_string( $sale_start ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $sale_start ) ) { return null; }
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $sale_start );
		if ( ! $date || $date->format( 'Y-m-d' ) !== $sale_start ) { return null; }
		$upper = get_gmt_from_date( $sale_start . ' 00:00:00', 'Y-m-d H:i:s' );
		if ( ! $upper || $upper > current_time( 'mysql', true ) ) { return null; }
		$lower = gmdate( 'Y-m-d H:i:s', strtotime( $upper . ' UTC' ) - 30 * DAY_IN_SECONDS );
		$source = 'manual' === $source ? 'manual' : 'woo';
		$currency = 'woo' === $source && function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR';
		$currency = strtoupper( substr( (string) $currency, 0, 3 ) );
		global $wpdb;
		$table = self::table_name();
		$baseline = $wpdb->get_row( $wpdb->prepare( 'SELECT price, observed_at FROM %i WHERE source = %s AND product_id = %d AND currency = %s AND observed_at <= %s ORDER BY observed_at DESC, id DESC LIMIT 1', $table, $source, $product_id, $currency, $lower ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only aggregate of the plugin-owned history table; must reflect up-to-date legal-price evidence.
		$window = $wpdb->get_row( $wpdb->prepare( 'SELECT MIN(price) AS lowest, MIN(observed_at) AS first_observed, COUNT(*) AS samples FROM %i WHERE source = %s AND product_id = %d AND currency = %s AND observed_at > %s AND observed_at < %s', $table, $source, $product_id, $currency, $lower, $upper ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only aggregate of the plugin-owned history table; must reflect up-to-date legal-price evidence.
		if ( ! $baseline && ( ! $window || ! (int) $window->samples ) ) { return null; }
		$minimum = $baseline ? (float) $baseline->price : PHP_FLOAT_MAX;
		if ( $window && $window->samples ) { $minimum = min( $minimum, (float) $window->lowest ); }
		return array( 'price' => $minimum, 'currency' => $currency, 'complete' => (bool) $baseline, 'sale_start' => $sale_start,
			'first_seen' => $baseline ? $baseline->observed_at : $window->first_observed );
	}

	/** Render the pre-discount window only when the date is explicitly known. */
	public function shortcode_before_sale( $atts ) {
		$atts = shortcode_atts( array( 'id' => '', 'datum' => '' ), $atts, 'sidrena_najniza_prije_snizenja' );
		$id_raw = trim( (string) $atts['id'] );
		if ( '' === $id_raw && Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() && function_exists( 'get_the_ID' ) ) {
			$id_raw = (string) get_the_ID();
		}
		$manual = preg_match( '/^d([0-9]+)$/iD', $id_raw, $matches ) || ! Brendigo_Sidrena_Cijena_Plugin::woocommerce_active();
		$id = $manual && isset( $matches[1] ) ? absint( $matches[1] ) : absint( $id_raw );
		if ( ! $id ) { return ''; }
		if ( $manual && ! Brendigo_Sidrena_Cijena_Catalog::instance()->get( $id ) ) { return ''; }
		if ( ! $manual && ! $this->viewer_can_see_woo( $id ) ) { return ''; }
		$start = trim( (string) $atts['datum'] );
		if ( '' === $start && ! $manual && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $id );
			if ( $product && $product->get_date_on_sale_from() ) {
				$start = $product->get_date_on_sale_from()->date( 'Y-m-d' );
			}
		}
		$data = $this->lowest_before_sale( $id, $start, $manual ? 'manual' : 'woo' );
		if ( ! $data ) { return ''; }
		$label = $data['complete'] ? __( 'Najniža zabilježena cijena 30 dana prije sniženja:', 'brendigo-sidrena-cijena' ) : __( 'Najniža zabilježena cijena prije sniženja (nepotpunih 30 dana praćenja):', 'brendigo-sidrena-cijena' );
		$price = $manual ? esc_html( Brendigo_Sidrena_Cijena_Catalog::instance()->format_amount( $data['price'] ) ) : ( function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $this->display_amount( $id, $data['price'] ), array( 'currency' => $data['currency'] ) ) ) : '' );
		return '<span class="sidrena-before-sale">' . esc_html( $label ) . ' ' . $price . '</span>';
	}

	/** Translate stored base price into the current shop's tax-inclusive/exclusive display. */
	private function display_amount( $product_id, $raw ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( absint( $product_id ) ) : false;
		return $product && function_exists( 'wc_get_price_to_display' )
			? wc_get_price_to_display( $product, array( 'price' => (float) $raw ) )
			: (float) $raw;
	}

	public function render_admin_history() {
		if ( ! function_exists( 'get_the_ID' ) ) {
			return;
		}
		$data = $this->lowest_30_days( get_the_ID() );
		if ( ! $data ) {
			echo '<p class="form-field"><em>' . esc_html__( 'Povijest prodajnih cijena počet će se bilježiti pri sljedećem spremanju proizvoda. Prethodne cijene nije moguće rekonstruirati.', 'brendigo-sidrena-cijena' ) . '</em></p>';
			return;
		}
		$text = $data['complete'] ? __( 'Najniža prodajna cijena u prethodnih 30 dana (zabilježena):', 'brendigo-sidrena-cijena' ) : __( 'Najniža zabilježena prodajna cijena (povijest kraća od 30 dana):', 'brendigo-sidrena-cijena' );
		echo '<p class="form-field"><label>' . esc_html( $text ) . '</label> <strong>' . wp_kses_post( wc_price( $this->display_amount( get_the_ID(), $data['price'] ), array( 'currency' => $data['currency'] ) ) ) . '</strong></p>';
	}

	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'sidrena_povijest_cijena' );
		$raw = trim( (string) $atts['id'] );
		$manual = 0;
		if ( preg_match( '/^d([0-9]+)$/iD', $raw, $matches ) ) {
			$manual = absint( $matches[1] );
		} elseif ( ! Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ) {
			$manual = absint( $raw );
		}
		if ( $manual ) {
			if ( ! Brendigo_Sidrena_Cijena_Catalog::instance()->get( $manual ) ) {
				return '';
			}
			$data = $this->lowest_30_days( $manual, 'manual' );
			if ( ! $data ) {
				return '';
			}
			$label = $data['complete'] ? __( 'Najniža prodajna cijena u 30 dana:', 'brendigo-sidrena-cijena' ) : __( 'Najniža zabilježena cijena (praćenje kraće od 30 dana):', 'brendigo-sidrena-cijena' );
			return '<span class="sidrena-cijena-price-history">' . esc_html( $label ) . ' ' . esc_html( Brendigo_Sidrena_Cijena_Catalog::instance()->format_amount( $data['price'] ) ) . '</span>';
		}
		$id = absint( $raw );
		if ( ! $id && function_exists( 'get_the_ID' ) && Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ) {
			$id = get_the_ID();
		}
		if ( ! $this->viewer_can_see_woo( $id ) ) { return ''; }
		$data = $this->lowest_30_days( $id );
		if ( ! $data || ! function_exists( 'wc_price' ) ) {
			return '';
		}
		$label = $data['complete'] ? __( 'Najniža prodajna cijena u 30 dana:', 'brendigo-sidrena-cijena' ) : __( 'Najniža dosad zabilježena prodajna cijena (manje od 30 dana praćenja):', 'brendigo-sidrena-cijena' );
		return '<span class="sidrena-cijena-price-history">' . esc_html( $label ) . ' ' . wp_kses_post( wc_price( $this->display_amount( $id, $data['price'] ), array( 'currency' => $data['currency'] ) ) ) . '</span>';
	}

	/** Avoid exposing price changes for draft, protected or catalog-hidden products. */
	private function viewer_can_see_woo( $product_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) { return false; }
		$product = wc_get_product( absint( $product_id ) );
		if ( ! $product ) { return false; }
		$ids = array( (int) $product->get_id() );
		if ( $product->get_parent_id() ) { $ids[] = (int) $product->get_parent_id(); }
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post instanceof WP_Post ) { return false; }
			if ( ! in_array( $post->post_status, array( 'publish', 'inherit' ), true ) && ! current_user_can( 'edit_post', $id ) ) { return false; }
			if ( post_password_required( $post ) ) { return false; }
		}
		$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
		if ( $parent && method_exists( $parent, 'get_catalog_visibility' ) && 'hidden' === $parent->get_catalog_visibility() ) {
			$parent_id = (int) $parent->get_id();
			if ( ( ! function_exists( 'get_queried_object_id' ) || (int) get_queried_object_id() !== $parent_id ) && ! current_user_can( 'edit_post', $parent_id ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Accessible, script-free trend chart of observed selling prices (not anchor prices).
	 * Does not disclose any editor, audit, or unpublished price information.
	 */
	public function shortcode_chart( $atts ) {
		$atts = shortcode_atts( array( 'id' => '' ), $atts, 'sidrena_graf_cijena' );
		$raw = trim( (string) $atts['id'] );
		$is_woo = Brendigo_Sidrena_Cijena_Plugin::woocommerce_active();
		$manual = preg_match( '/^d([0-9]+)$/iD', $raw, $matches ) || ! $is_woo;
		$id = $manual && isset( $matches[1] ) ? absint( $matches[1] ) : absint( $raw );
		if ( ! $id && ! $manual && function_exists( 'get_the_ID' ) ) {
			$id = absint( get_the_ID() );
		}
		if ( ! $id ) { return ''; }
		if ( $manual ) {
			if ( ! Brendigo_Sidrena_Cijena_Catalog::instance()->get( $id ) ) { return ''; }
		} elseif ( ! $this->viewer_can_see_woo( $id ) ) {
			return '';
		}
		global $wpdb;
		$table = self::table_name();
		$source = $manual ? 'manual' : 'woo';
		$currency = $manual ? 'EUR' : ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR' );
		$currency = strtoupper( substr( sanitize_text_field( $currency ), 0, 3 ) );
		$from = gmdate( 'Y-m-d H:i:s', time() - 365 * DAY_IN_SECONDS );
		// Bounded query and a matching composite index keep this safe for busy catalogs.
		$chart_key = 'chart:' . $source . ':' . $id . ':' . $currency;
		$samples = wp_cache_get( $chart_key, 'brendigo_sidrena_history' );
		if ( false === $samples ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded history query is stored in the object cache below.
			$samples = $wpdb->get_results( $wpdb->prepare( 'SELECT price,observed_at FROM %i WHERE source = %s AND product_id = %d AND currency = %s AND observed_at >= %s ORDER BY observed_at DESC,id DESC LIMIT 90', $table, $source, $id, $currency, $from ), ARRAY_A );
			if ( is_array( $samples ) ) {
				wp_cache_set( $chart_key, $samples, 'brendigo_sidrena_history', MINUTE_IN_SECONDS );
			}
		}
		if ( ! is_array( $samples ) || count( $samples ) < 2 ) { return ''; }
		$samples = array_reverse( $samples );
		$prices = array_map( static function ( $row ) { return (float) $row['price']; }, $samples );
		$minimum = min( $prices );
		$maximum = max( $prices );
		$range = max( 0.01, $maximum - $minimum );
		$count = count( $samples );
		$points = array();
		foreach ( $samples as $index => $sample ) {
			$x = 15 + ( 370 * $index / ( $count - 1 ) );
			$y = 100 - ( ( (float) $sample['price'] - $minimum ) * 75 / $range );
			$points[] = number_format( $x, 1, '.', '' ) . ',' . number_format( $y, 1, '.', '' );
		}
		if ( class_exists( 'Brendigo_Sidrena_Cijena_Frontend' ) ) { Brendigo_Sidrena_Cijena_Frontend::instance()->ensure_history_style(); }
		$title = __( 'Povijest zabilježenih prodajnih cijena', 'brendigo-sidrena-cijena' );
		$note = __( 'Povijest započinje prvim zabilježenim podatkom. Graf nije dokaz cijena prije početka praćenja.', 'brendigo-sidrena-cijena' );
		$html = '<figure class="sidrena-price-chart"><figcaption><strong>' . esc_html( $title ) . '</strong></figcaption>';
		$html .= '<svg viewBox="0 0 400 120" role="img" aria-label="' . esc_attr( $title ) . '" xmlns="http://www.w3.org/2000/svg"><path d="M15 15 V105 H385" class="sidrena-price-chart__axis"/><polyline class="sidrena-price-chart__line" points="' . esc_attr( implode( ' ', $points ) ) . '"/></svg>';
		$html .= '<p class="sidrena-price-chart__note">' . esc_html( $note ) . '</p><details><summary>' . esc_html__( 'Pogledaj podatke grafa', 'brendigo-sidrena-cijena' ) . '</summary><table><thead><tr><th>' . esc_html__( 'Datum (UTC)', 'brendigo-sidrena-cijena' ) . '</th><th>' . esc_html__( 'Cijena', 'brendigo-sidrena-cijena' ) . '</th></tr></thead><tbody>';
		foreach ( $samples as $sample ) {
			$label = $manual ? Brendigo_Sidrena_Cijena_Catalog::instance()->format_amount( $sample['price'] ) : ( function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $this->display_amount( $id, $sample['price'] ), array( 'currency' => $currency ) ) ) : $sample['price'] . ' ' . $currency );
			$html .= '<tr><td>' . esc_html( $sample['observed_at'] ) . '</td><td>' . esc_html( $label ) . '</td></tr>';
		}
		return $html . '</tbody></table></details></figure>';
	}

	public function prune() {
		global $wpdb;
		$table = self::table_name();
		// Keep at least the last entry before the retention boundary for each item.
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 400 * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( 'DELETE old FROM %i AS old INNER JOIN %i AS newer ON newer.product_id = old.product_id AND newer.source = old.source AND newer.currency = old.currency AND newer.id > old.id WHERE old.observed_at < %s AND newer.observed_at < %s', $table, $table, $cutoff, $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Retention cleanup of plugin-owned records cannot use WP posts API.
	}
}
