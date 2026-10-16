<?php
/**
 * Read / write sidrena cijena product meta.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product meta helpers.
 */
class Brendigo_Sidrena_Cijena_Product_Meta {

	/** Dashboard aggregate cache. */
	private const OVERVIEW_TRANSIENT = 'brendigo_sidrena_overview_metrics';

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register product meta. Existing sidrena values are never overwritten on save.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'woocommerce_after_product_object_save', array( $this, 'maybe_autofill' ), 20, 1 );
		add_action( 'added_post_meta', array( $this, 'maybe_clear_overview_cache' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'maybe_clear_overview_cache' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'maybe_clear_overview_cache' ), 10, 4 );
		add_action( 'transition_post_status', array( $this, 'maybe_clear_overview_cache_on_status' ), 10, 3 );
	}

	/**
	 * Expose meta to the REST API for products and variations.
	 */
	public function register_meta() {
		$common = array(
			'single'        => true,
			'type'          => 'string',
			'show_in_rest'  => true,
			'auth_callback' => static function ( $allowed, $meta_key, $post_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return current_user_can( 'edit_post', (int) $post_id );
			},
		);

		$sanitizers = array(
			BRENDIGO_SIDRENA_CIJENA_META_PRICE => static function ( $value ) {
				return Brendigo_Sidrena_Cijena_Product_Meta::instance()->normalize_price( $value );
			},
			BRENDIGO_SIDRENA_CIJENA_META_DATE => static function ( $value ) {
				return Brendigo_Sidrena_Cijena_Money::normalize_date( $value );
			},
			BRENDIGO_SIDRENA_CIJENA_META_CJM => static function ( $value ) {
				return in_array( strtolower( trim( (string) $value ) ), array( 'yes', '1', 'true', 'on' ), true ) ? 'yes' : '';
			},
			BRENDIGO_SIDRENA_CIJENA_META_CJM_SUFFIX => static function ( $value ) {
				return sanitize_text_field( $value );
			},
			BRENDIGO_SIDRENA_CIJENA_META_OBLIK => static function ( $value ) {
				return sanitize_text_field( $value );
			},
			BRENDIGO_SIDRENA_CIJENA_META_JEDINICA => static function ( $value ) {
				return sanitize_text_field( $value );
			},
			BRENDIGO_SIDRENA_CIJENA_META_JEDINICA_CIJENA => static function ( $value ) {
				return Brendigo_Sidrena_Cijena_Product_Meta::instance()->normalize_price( $value );
			},
			BRENDIGO_SIDRENA_CIJENA_META_BARCODE => static function ( $value ) {
				$barcode = preg_replace( '/\s+/', '', sanitize_text_field( $value ) );
				return is_string( $barcode ) ? $barcode : '';
			},
			BRENDIGO_SIDRENA_CIJENA_META_VISIBILITY => static function ( $value ) {
				$value = sanitize_key( $value );
				return in_array( $value, array( 'include', 'exclude' ), true ) ? $value : '';
			},
		);

		foreach ( array( 'product', 'product_variation' ) as $type ) {
			foreach ( $sanitizers as $meta_key => $sanitize_callback ) {
				$args = $common;
				$args['sanitize_callback'] = $sanitize_callback;
				register_post_meta( $type, $meta_key, $args );
			}
		}
	}

	/**
	 * Small cached aggregate used by the overview screen.
	 *
	 * A prepared aggregate query is materially cheaper here than loading product
	 * objects or running two WP_Query meta_query scans on every dashboard view.
	 * The cache is invalidated whenever the reference-price meta or product status
	 * changes, so normal admin edits remain immediately visible.
	 *
	 * @return array{count:int,preview_id:int}
	 */
	public function overview_metrics() {
		$cached = get_transient( self::OVERVIEW_TRANSIENT );
		if ( is_array( $cached ) && isset( $cached['count'], $cached['preview_id'] ) ) {
			return array(
				'count'      => max( 0, (int) $cached['count'] ),
				'preview_id' => max( 0, (int) $cached['preview_id'] ),
			);
		}

		global $wpdb;

		$count_sql = $wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID)
			 FROM %i p
			 INNER JOIN %i pm ON pm.post_id = p.ID
			 WHERE pm.meta_key = %s
			   AND pm.meta_value <> ''
			   AND p.post_type IN (%s, %s)
			   AND p.post_status IN (%s, %s)",
			$wpdb->posts,
			$wpdb->postmeta,
			BRENDIGO_SIDRENA_CIJENA_META_PRICE,
			'product',
			'product_variation',
			'publish',
			'private'
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above and its scalar result is transient-cached.
		$count = (int) $wpdb->get_var( $count_sql );

		$preview_sql = $wpdb->prepare(
			"SELECT p.ID
			 FROM %i p
			 INNER JOIN %i pm ON pm.post_id = p.ID
			 WHERE pm.meta_key = %s
			   AND pm.meta_value <> ''
			   AND p.post_type IN (%s, %s)
			   AND p.post_status = %s
			 ORDER BY p.post_modified_gmt DESC, p.ID DESC
			 LIMIT 1",
			$wpdb->posts,
			$wpdb->postmeta,
			BRENDIGO_SIDRENA_CIJENA_META_PRICE,
			'product',
			'product_variation',
			'publish'
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above and its single-ID result is transient-cached.
		$preview_id = (int) $wpdb->get_var( $preview_sql );

		$metrics = array(
			'count'      => max( 0, $count ),
			'preview_id' => max( 0, $preview_id ),
		);
		set_transient( self::OVERVIEW_TRANSIENT, $metrics, 5 * MINUTE_IN_SECONDS );

		return $metrics;
	}

	/**
	 * Invalidate cached overview metrics when the reference-price meta changes.
	 *
	 * @param mixed  $meta_id    Meta id(s), unused.
	 * @param int    $object_id  Post id, unused.
	 * @param string $meta_key   Changed meta key.
	 * @param mixed  $meta_value Changed value, unused.
	 */
	public function maybe_clear_overview_cache( $meta_id, $object_id, $meta_key, $meta_value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( BRENDIGO_SIDRENA_CIJENA_META_PRICE === $meta_key ) {
			delete_transient( self::OVERVIEW_TRANSIENT );
		}
	}

	/**
	 * Invalidate cached overview metrics when a product changes publication state.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       Post object.
	 */
	public function maybe_clear_overview_cache_on_status( $new_status, $old_status, $post ) {
		if ( $new_status === $old_status || ! $post instanceof WP_Post ) {
			return;
		}

		if ( in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
			delete_transient( self::OVERVIEW_TRANSIENT );
		}
	}

	/**
	 * Get stored sidrena price (raw string).
	 *
	 * @param int $product_id Product or variation ID.
	 * @return string
	 */
	public function get_price( $product_id ) {
		$value = get_post_meta( $product_id, BRENDIGO_SIDRENA_CIJENA_META_PRICE, true );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Post that holds sidrena for storefront display.
	 *
	 * WPML and Polylang store each translation as its own product. Sidrena is
	 * saved on the default-language product, and those plugins do not copy this
	 * field, so a translated product id has no price of its own.
	 *
	 * @param int $product_id Product or variation ID currently being viewed.
	 * @return int
	 */
	public function display_id( $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id <= 0 ) {
			return 0;
		}

		if ( '' !== $this->get_price( $product_id ) ) {
			return $product_id;
		}

		if ( ! class_exists( 'Brendigo_Sidrena_Cijena_I18n' ) ) {
			return $product_id;
		}

		$source = Brendigo_Sidrena_Cijena_I18n::instance()->source_product_id( $product_id );
		if ( $source > 0 && $source !== $product_id && '' !== $this->get_price( $source ) ) {
			return $source;
		}

		return $product_id;
	}

	/**
	 * Stored sidrena amount adjusted to the shop tax display setting.
	 *
	 * Sidrena is stored in the same tax basis as `_regular_price`. Regular
	 * price HTML goes through `wc_get_price_to_display()`; this must too,
	 * otherwise shops that enter prices without tax and display them with
	 * tax show sidrena as the raw net amount.
	 *
	 * @param WC_Product $product   Product or variation (tax class / status).
	 * @param string     $raw_price Optional raw amount. Defaults to stored meta.
	 * @return float|null Display amount, or null when there is no price.
	 */
	public function get_price_to_display( $product, $raw_price = null ) {
		if ( ! $product instanceof WC_Product ) {
			return null;
		}

		$raw = null === $raw_price ? $this->get_price( $product->get_id() ) : (string) $raw_price;
		if ( '' === $raw ) {
			return null;
		}

		if ( function_exists( 'wc_format_decimal' ) ) {
			$raw = wc_format_decimal( $raw );
			if ( false === $raw || null === $raw || '' === $raw ) {
				return null;
			}
		}

		if ( function_exists( 'wc_get_price_to_display' ) ) {
			$amount = (float) wc_get_price_to_display(
				$product,
				array(
					'price' => (float) $raw,
				)
			);
		} else {
			$amount = (float) $raw;
		}

		/**
		 * Display amount for a stored sidrena price (after WooCommerce tax display).
		 *
		 * Sidrena is stored in the same tax basis as `_regular_price`. Shops that
		 * show MPC with a custom VAT calculation (not WooCommerce Tax) can adjust
		 * the amount here so it matches the regular price on the storefront.
		 *
		 * @param float      $amount  Amount after wc_get_price_to_display().
		 * @param WC_Product $product Product or variation.
		 * @param string     $raw     Stored sidrena amount (same basis as _regular_price).
		 */
		return (float) apply_filters( 'sidrena_cijena_price_to_display', $amount, $product, $raw );
	}

	/**
	 * Turn a shop-display amount back into the stored `_regular_price` basis.
	 *
	 * When the display amount already matches the current stored price, that
	 * stored value is returned unchanged so reimporting the same public file
	 * does not drift on tax rounding.
	 *
	 * @param WC_Product $product        Product or variation.
	 * @param string     $display_price  Amount as shown on the price list.
	 * @param string     $current_stored Stored amount kept when the display matches.
	 * @return string Stored decimal, or empty when the display amount is empty.
	 */
	public function display_price_to_stored( $product, $display_price, $current_stored = '' ) {
		if ( ! $product instanceof WC_Product ) {
			return $this->normalize_price( $display_price );
		}

		$display = $this->normalize_price( $display_price );
		if ( '' === $display || ! is_numeric( $display ) ) {
			return '';
		}

		$current_stored = is_scalar( $current_stored ) ? trim( (string) $current_stored ) : '';
		if ( '' !== $current_stored ) {
			$current_display = $this->get_price_to_display( $product, $current_stored );
			if ( null !== $current_display && $this->display_amounts_match( $current_display, $display ) ) {
				return $current_stored;
			}
		}

		if ( ! $this->tax_changes_display( $product ) ) {
			return $display;
		}

		$show_incl  = 'incl' === get_option( 'woocommerce_tax_display_shop' );
		$store_incl = function_exists( 'wc_prices_include_tax' ) && wc_prices_include_tax();
		if ( $show_incl === $store_incl || ! class_exists( 'WC_Tax' ) ) {
			return $display;
		}

		$rates  = WC_Tax::get_rates( $product->get_tax_class() );
		$amount = (float) $display;
		if ( $show_incl && ! $store_incl ) {
			$taxes  = WC_Tax::calc_tax( $amount, $rates, true );
			$stored = $amount - (float) array_sum( $taxes );
		} else {
			$taxes  = WC_Tax::calc_tax( $amount, $rates, false );
			$stored = $amount + (float) array_sum( $taxes );
		}

		if ( $stored < 0 ) {
			$stored = 0;
		}

		$normalized = $this->normalize_price( $stored );
		return '' === $normalized ? $display : $normalized;
	}

	/**
	 * Format a stored sidrena amount the same way WooCommerce formats shop prices.
	 *
	 * @param WC_Product $product   Product or variation.
	 * @param string     $raw_price Optional raw amount. Defaults to stored meta.
	 * @return string Price HTML, or empty string.
	 */
	public function format_price_html( $product, $raw_price = null ) {
		$amount = $this->get_price_to_display( $product, $raw_price );
		if ( null === $amount ) {
			return '';
		}

		return wc_price( $amount );
	}

	/**
	 * Get stored reference date (d.m.Y. or empty).
	 *
	 * @param int $product_id Product or variation ID.
	 * @return string
	 */
	public function get_date( $product_id ) {
		$value = get_post_meta( $product_id, BRENDIGO_SIDRENA_CIJENA_META_DATE, true );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Save sidrena price. Empty string deletes the meta.
	 *
	 * @param int    $product_id Product or variation ID.
	 * @param string $price      Raw price.
	 * @return bool Whether stored data changed.
	 */
	public function set_price( $product_id, $price ) {
		$price = $this->normalize_price( $price );

		if ( '' === $price ) {
			return $this->delete_meta_if_present( $product_id, BRENDIGO_SIDRENA_CIJENA_META_PRICE );
		}

		return $this->update_meta_if_changed( $product_id, BRENDIGO_SIDRENA_CIJENA_META_PRICE, $price );
	}

	/**
	 * Save optional per-product date.
	 *
	 * @param int    $product_id Product or variation ID.
	 * @param string $date       Date string.
	 * @return bool Whether stored data changed.
	 */
	public function set_date( $product_id, $date ) {
		$date = Brendigo_Sidrena_Cijena_Money::normalize_date( $date );

		if ( '' === $date ) {
			return $this->delete_meta_if_present( $product_id, BRENDIGO_SIDRENA_CIJENA_META_DATE );
		}

		return $this->update_meta_if_changed( $product_id, BRENDIGO_SIDRENA_CIJENA_META_DATE, $date );
	}

	/**
	 * Whether the product has the CJM unit suffix enabled.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return bool
	 */
	public function is_cjm_enabled( $product_id ) {
		return 'yes' === get_post_meta( $product_id, BRENDIGO_SIDRENA_CIJENA_META_CJM, true );
	}

	/**
	 * Enable or disable CJM on a product.
	 *
	 * @param int  $product_id Product or variation ID.
	 * @param bool $enabled    Toggle.
	 */
	public function set_cjm_enabled( $product_id, $enabled ) {
		if ( $enabled ) {
			$this->update_meta_if_changed( $product_id, BRENDIGO_SIDRENA_CIJENA_META_CJM, 'yes' );
			return;
		}

		$this->delete_meta_if_present( $product_id, BRENDIGO_SIDRENA_CIJENA_META_CJM );
	}

	/**
	 * CJM prefix suffix (e.g. "za kg/l").
	 *
	 * @param int $product_id Product or variation ID.
	 * @return string
	 */
	public function get_cjm_suffix( $product_id ) {
		$value = get_post_meta( $product_id, BRENDIGO_SIDRENA_CIJENA_META_CJM_SUFFIX, true );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Save CJM suffix.
	 *
	 * @param int    $product_id Product or variation ID.
	 * @param string $suffix     Suffix text.
	 */
	public function set_cjm_suffix( $product_id, $suffix ) {
		$suffix = sanitize_text_field( $suffix );

		if ( '' === $suffix ) {
			$this->delete_meta_if_present( $product_id, BRENDIGO_SIDRENA_CIJENA_META_CJM_SUFFIX );
			return;
		}

		$this->update_meta_if_changed( $product_id, BRENDIGO_SIDRENA_CIJENA_META_CJM_SUFFIX, $suffix );
	}

	/**
	 * Optional name that replaces "akcija" while a sale price is active.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return string
	 */
	public function get_oblik( $product_id ) {
		$value = get_post_meta( $product_id, BRENDIGO_SIDRENA_CIJENA_META_OBLIK, true );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Save the special-sale name override. Empty deletes it.
	 *
	 * @param int    $product_id Product or variation ID.
	 * @param string $name       Sale-form name.
	 */
	public function set_oblik( $product_id, $name ) {
		$name = sanitize_text_field( $name );

		if ( '' === $name ) {
			$this->delete_meta_if_present( $product_id, BRENDIGO_SIDRENA_CIJENA_META_OBLIK );
			return;
		}

		$this->update_meta_if_changed( $product_id, BRENDIGO_SIDRENA_CIJENA_META_OBLIK, $name );
	}

	/**
	 * Name written to the public feed while the charged price is the sale price.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return string akcija, a per-product override, or empty.
	 */
	public function public_oblik_name( $product ) {
		if ( ! $product instanceof WC_Product || ! $product->is_on_sale() ) {
			return '';
		}

		if ( '' === (string) $product->get_sale_price() ) {
			return '';
		}

		$override = trim( $this->get_oblik( $product->get_id() ) );
		return '' !== $override ? $override : 'akcija';
	}

	/**
	 * Unit of measure for the public feed (kg, l, kom).
	 *
	 * @param int $product_id Product or variation ID.
	 * @return string
	 */
	public function get_jedinica( $product_id ) {
		$value = get_post_meta( $product_id, BRENDIGO_SIDRENA_CIJENA_META_JEDINICA, true );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Save the unit of measure. Empty deletes it.
	 *
	 * @param int    $product_id Product or variation ID.
	 * @param string $unit       Unit.
	 */
	public function set_jedinica( $product_id, $unit ) {
		$unit = sanitize_text_field( $unit );

		if ( '' === $unit ) {
			$this->delete_meta_if_present( $product_id, BRENDIGO_SIDRENA_CIJENA_META_JEDINICA );
			return;
		}

		$this->update_meta_if_changed( $product_id, BRENDIGO_SIDRENA_CIJENA_META_JEDINICA, $unit );
	}

	/**
	 * Unit price stored on the same tax basis as the regular price.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return string
	 */
	public function get_jedinica_cijena( $product_id ) {
		$value = get_post_meta( $product_id, BRENDIGO_SIDRENA_CIJENA_META_JEDINICA_CIJENA, true );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Save the unit price. Empty deletes it.
	 *
	 * @param int    $product_id Product or variation ID.
	 * @param string $price      Raw price.
	 */
	public function set_jedinica_cijena( $product_id, $price ) {
		$price = $this->normalize_price( $price );

		if ( '' === $price ) {
			$this->delete_meta_if_present( $product_id, BRENDIGO_SIDRENA_CIJENA_META_JEDINICA_CIJENA );
			return;
		}

		$this->update_meta_if_changed( $product_id, BRENDIGO_SIDRENA_CIJENA_META_JEDINICA_CIJENA, $price );
	}


	/**
	 * Optional EAN/GTIN/barcode used by the public pricelist.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return string
	 */
	public function get_barcode( $product_id ) {
		$value = get_post_meta( $product_id, BRENDIGO_SIDRENA_CIJENA_META_BARCODE, true );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Save an optional EAN/GTIN/barcode.
	 *
	 * @param int    $product_id Product or variation ID.
	 * @param string $barcode    Raw barcode.
	 * @return bool Whether stored data changed.
	 */
	public function set_barcode( $product_id, $barcode ) {
		$barcode = preg_replace( '/\s+/', '', sanitize_text_field( $barcode ) );
		$barcode = is_string( $barcode ) ? $barcode : '';

		if ( '' === $barcode ) {
			return $this->delete_meta_if_present( $product_id, BRENDIGO_SIDRENA_CIJENA_META_BARCODE );
		}

		return $this->update_meta_if_changed( $product_id, BRENDIGO_SIDRENA_CIJENA_META_BARCODE, $barcode );
	}

	/**
	 * Public pricelist visibility override: auto, include or exclude.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return string
	 */
	public function get_cjenik_visibility( $product_id ) {
		$value = (string) get_post_meta( $product_id, BRENDIGO_SIDRENA_CIJENA_META_VISIBILITY, true );
		return in_array( $value, array( 'include', 'exclude' ), true ) ? $value : 'auto';
	}

	/**
	 * Save public pricelist visibility override.
	 *
	 * @param int    $product_id Product or variation ID.
	 * @param string $visibility auto, include or exclude.
	 * @return bool Whether stored data changed.
	 */
	public function set_cjenik_visibility( $product_id, $visibility ) {
		$visibility = sanitize_key( $visibility );
		if ( ! in_array( $visibility, array( 'include', 'exclude' ), true ) ) {
			return $this->delete_meta_if_present( $product_id, BRENDIGO_SIDRENA_CIJENA_META_VISIBILITY );
		}

		return $this->update_meta_if_changed( $product_id, BRENDIGO_SIDRENA_CIJENA_META_VISIBILITY, $visibility );
	}

	/**
	 * Update one string meta value only when the normalized value changed.
	 *
	 * @param int    $product_id Product id.
	 * @param string $meta_key   Meta key.
	 * @param string $value      Normalized value.
	 * @return bool Whether a write occurred.
	 */
	private function update_meta_if_changed( $product_id, $meta_key, $value ) {
		$product_id = absint( $product_id );
		if ( $product_id < 1 ) {
			return false;
		}

		$current = get_post_meta( $product_id, $meta_key, true );
		if ( is_scalar( $current ) && (string) $current === (string) $value ) {
			return false;
		}

		if ( false === update_post_meta( $product_id, $meta_key, $value ) ) {
			return false;
		}
		$this->audit_reference_meta( $product_id, $meta_key, $current, $value );
		return true;
	}

	/**
	 * Delete one meta key only when it currently exists.
	 *
	 * @param int    $product_id Product id.
	 * @param string $meta_key   Meta key.
	 * @return bool Whether a delete occurred.
	 */
	private function delete_meta_if_present( $product_id, $meta_key ) {
		$product_id = absint( $product_id );
		if ( $product_id < 1 || ! metadata_exists( 'post', $product_id, $meta_key ) ) {
			return false;
		}

		$previous = get_post_meta( $product_id, $meta_key, true );
		if ( ! delete_post_meta( $product_id, $meta_key ) ) {
			return false;
		}
		$this->audit_reference_meta( $product_id, $meta_key, $previous, '' );
		return true;
	}

	/** Record only changes to plugin-owned anchor metadata; never store customer/order data. */
	private function audit_reference_meta( $product_id, $meta_key, $old, $new ) {
		$fields = array(
			BRENDIGO_SIDRENA_CIJENA_META_PRICE => 'sidrena_cijena',
			BRENDIGO_SIDRENA_CIJENA_META_DATE => 'sidrena_datum',
			BRENDIGO_SIDRENA_CIJENA_META_BARCODE => 'barkod',
			BRENDIGO_SIDRENA_CIJENA_META_VISIBILITY => 'visibility',
		);
		if ( isset( $fields[ $meta_key ] ) ) {
			Brendigo_Sidrena_Cijena_Audit_Log::instance()->record( 'woo', $product_id, $fields[ $meta_key ], $old, $new );
		}
	}

	/**
	 * Copy regular price and today's date into empty sidrena fields on new / unsaved products.
	 *
	 * @param WC_Product $product Product or variation just persisted.
	 */
	public function maybe_autofill( $product ) {
		if ( ! Brendigo_Sidrena_Cijena_Settings::instance()->is_auto_fill_enabled() ) {
			return;
		}

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		if ( $product->is_type( 'variable' ) || $product->is_type( 'grouped' ) || $product->is_type( 'variable-subscription' ) ) {
			return;
		}

		$this->snapshot_if_empty( $product, self::today_date() );
	}

	/**
	 * Whether regular price can be copied into an empty sidrena field.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return bool
	 */
	public function can_snapshot( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}

		if ( $product->is_type( 'variable' ) || $product->is_type( 'grouped' ) ) {
			return false;
		}

		if ( '' !== $this->get_price( $product->get_id() ) ) {
			return false;
		}

		$regular = $product->get_regular_price();

		return ! ( '' === $regular || null === $regular );
	}

	/**
	 * Snapshot regular price into sidrena meta if empty.
	 *
	 * @param WC_Product $product Product or variation.
	 * @param string     $date    Optional reference date to store when the date field is also empty.
	 * @return bool True when a value was written.
	 */
	public function snapshot_if_empty( $product, $date = '' ) {
		if ( ! $this->can_snapshot( $product ) ) {
			return false;
		}

		$id = $product->get_id();
		$this->set_price( $id, $product->get_regular_price() );

		$date = is_string( $date ) ? trim( $date ) : '';
		if ( '' !== $date && '' === $this->get_date( $id ) ) {
			$this->set_date( $id, $date );
		}

		return true;
	}

	/**
	 * Today's date in the Croatian d.m.Y. form (WordPress timezone).
	 *
	 * @return string
	 */
	public static function today_date() {
		$date = function_exists( 'wp_date' ) ? wp_date( 'd.m.Y.' ) : date_i18n( 'd.m.Y.' );
		$date = trim( (string) $date );

		if ( '' === $date ) {
			return '';
		}

		return rtrim( $date, '.' ) . '.';
	}

	/**
	 * Visible label: prefix + (date). Date is the product override or the shop default.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return string
	 */
	public function get_display_prefix( $product_id ) {
		$label = $this->get_label_text( $product_id );
		$date  = $this->get_display_date( $product_id );

		return Brendigo_Sidrena_Cijena_Settings::instance()->format_prefix_with_date( $label, $date );
	}

	/**
	 * Date shown in parentheses: product field, otherwise shop default.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return string
	 */
	public function get_display_date( $product_id ) {
		$custom = trim( $this->get_date( $product_id ) );
		if ( '' !== $custom ) {
			return $this->normalize_date( $custom );
		}

		return $this->normalize_date( (string) Brendigo_Sidrena_Cijena_Settings::instance()->get( 'default_date' ) );
	}

	/**
	 * Prefix words only (plus CJM suffix), without the date.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return string
	 */
	private function get_label_text( $product_id ) {
		$label = Brendigo_Sidrena_Cijena_Settings::instance()->prefix_words();

		return $this->apply_cjm_suffix( $label, $product_id );
	}

	/**
	 * Ensure a trailing period on a Croatian date.
	 *
	 * @param string $date Raw date.
	 * @return string
	 */
	private function normalize_date( $date ) {
		return Brendigo_Sidrena_Cijena_Money::normalize_date_display( $date );
	}

	/**
	 * Insert the CJM unit text after the prefix words.
	 *
	 * "Sidrena cijena" + "za kg/l" → "Sidrena cijena za kg/l"
	 *
	 * @param string $prefix     Base prefix.
	 * @param int    $product_id Product or variation ID.
	 * @return string
	 */
	private function apply_cjm_suffix( $prefix, $product_id ) {
		if ( ! Brendigo_Sidrena_Cijena_Settings::instance()->is_cjm_enabled() ) {
			return $prefix;
		}

		if ( ! $this->is_cjm_enabled( $product_id ) ) {
			return $prefix;
		}

		return Brendigo_Sidrena_Cijena_Settings::append_unit_suffix( $prefix, $this->get_cjm_suffix( $product_id ) );
	}

	/**
	 * Normalize a posted price to WooCommerce storage format.
	 *
	 * @param mixed $price Raw value.
	 * @return string
	 */
	public function normalize_price( $price ) {
		return Brendigo_Sidrena_Cijena_Money::normalize_price_woo( $price );
	}

	/**
	 * Whether shop tax display can change a stored amount.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return bool
	 */
	private function tax_changes_display( $product ) {
		if ( ! function_exists( 'wc_tax_enabled' ) || ! wc_tax_enabled() ) {
			return false;
		}

		return $product->is_taxable();
	}

	/**
	 * Compare two display amounts at the shop's price precision.
	 *
	 * @param float|string $left  Display amount.
	 * @param float|string $right Display amount.
	 * @return bool
	 */
	private function display_amounts_match( $left, $right ) {
		$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;

		return round( (float) $left, $decimals ) === round( (float) $right, $decimals );
	}
}
