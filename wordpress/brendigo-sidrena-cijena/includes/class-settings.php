<?php
/**
 * Plugin settings stored in a single option.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings helper.
 */
class Brendigo_Sidrena_Cijena_Settings {

	const OPTION_KEY       = 'sidrena_cijena_settings';
	const DEFAULT_PREFIX          = 'Sidrena cijena';
	const LEGACY_PREFIX           = 'Cijena';
	const REFERENCE_DATE_GENERAL  = '10.09.2026.';
	const REFERENCE_DATE_FMCG     = '02.05.2025.';
	const DEFAULT_TOOLTIP         = 'Sidrena cijena je referentna maloprodajna cijena koja je za proizvod ili uslugu vrijedila na navedeni datum.';
	const PREVIOUS_TOOLTIP_HASH  = '4fdd1b2eba2678023ab9618dc15839bd2efc789ee3404c799ba199eb47dd5404';
	const LEGACY_TOOLTIP_HASH    = '2c8ca8cdc51d22e369144a40b69c504ba24db0b1a08a1e42a04bb065d1102661';

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Cached settings.
	 *
	 * @var array<string, mixed>|null
	 */
	private $settings = null;

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
	 * Default option values.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'prefix'          => self::DEFAULT_PREFIX,
			'tooltip'         => self::DEFAULT_TOOLTIP,
			'enable_tooltip'  => 'yes',
			'auto_display'    => 'yes',
			'auto_fill'       => 'no',
			'default_date'    => self::REFERENCE_DATE_GENERAL,
			'date_style'      => 'parens',
			'enable_cjm'      => 'no',
			'daily_xml'       => 'yes',
			'daily_time'      => '07:00',
			'auto_rebuild'    => 'yes',
			'auto_maintenance' => 'yes',
			'email_alerts'    => 'yes',
			'alert_email'     => '',
			'export_format'   => 'xml',
			'file_oblik'      => 'webshop',
			'file_adresa'     => '',
			'file_oznaka'     => '001',
			'file_pohrana'    => 1,
			'brand_taxonomy'  => '',
			'brand_manual'    => '',
			'multi_poslovnica' => 'no',
			'public_cjenik'    => 'yes',
			'i18n'            => array(
				'prefix'  => array(),
				'tooltip' => array(),
			),
		);
	}

	/**
	 * Write defaults on first activation.
	 */
	public static function add_defaults() {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, self::defaults() );
		}
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$settings = $this->all();

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		$defaults = self::defaults();

		if ( array_key_exists( $key, $defaults ) ) {
			return $defaults[ $key ];
		}

		return $default;
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all() {
		if ( null !== $this->settings ) {
			return $this->settings;
		}

		$stored = get_option( self::OPTION_KEY, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$dirty  = false;

		if ( isset( $stored['prefix'] ) ) {
			$stripped = self::strip_date_from_prefix( (string) $stored['prefix'] );
			if ( $stripped !== $stored['prefix'] ) {
				$stored['prefix'] = '' !== $stripped ? $stripped : self::defaults()['prefix'];
				$dirty            = true;
			}
		}

		if ( isset( $stored['prefix'] ) && self::LEGACY_PREFIX === trim( (string) $stored['prefix'] ) ) {
			$stored['prefix'] = self::DEFAULT_PREFIX;
			$dirty            = true;
		}

		if ( isset( $stored['tooltip'] ) ) {
			$tooltip_hash = hash( 'sha256', trim( (string) $stored['tooltip'] ) );
			if ( in_array( $tooltip_hash, array( self::LEGACY_TOOLTIP_HASH, self::PREVIOUS_TOOLTIP_HASH ), true ) ) {
				$stored['tooltip'] = self::DEFAULT_TOOLTIP;
				$dirty             = true;
			}
		}

		if ( isset( $stored['i18n']['prefix'] ) && is_array( $stored['i18n']['prefix'] ) ) {
			foreach ( $stored['i18n']['prefix'] as $lang => $value ) {
				if ( is_string( $value ) && self::LEGACY_PREFIX === trim( $value ) ) {
					$stored['i18n']['prefix'][ $lang ] = self::DEFAULT_PREFIX;
					$dirty                             = true;
				}
			}
		}

		if ( ! isset( $stored['default_date'] ) || self::REFERENCE_DATE_GENERAL !== trim( (string) $stored['default_date'] ) ) {
			$stored['default_date'] = self::REFERENCE_DATE_GENERAL;
			$dirty                  = true;
		}

		if ( isset( $stored['i18n']['tooltip'] ) && is_array( $stored['i18n']['tooltip'] ) ) {
			foreach ( $stored['i18n']['tooltip'] as $lang => $value ) {
				if ( is_string( $value ) && self::LEGACY_TOOLTIP_HASH === hash( 'sha256', trim( $value ) ) ) {
					$stored['i18n']['tooltip'][ $lang ] = self::DEFAULT_TOOLTIP;
					$dirty                              = true;
				}
			}
		}

		if ( isset( $stored['date_style'] ) && 'na' === $stored['date_style'] ) {
			$stored['date_style'] = 'plain';
			$prefix               = isset( $stored['prefix'] ) ? trim( (string) $stored['prefix'] ) : '';
			if ( '' !== $prefix && ! preg_match( '/\bna$/iu', $prefix ) ) {
				$stored['prefix'] = $prefix . ' na';
			}
			$dirty = true;
		}

		if ( $dirty ) {
			update_option( self::OPTION_KEY, $stored );
		}

		$this->settings = wp_parse_args( $stored, self::defaults() );

		return $this->settings;
	}

	/**
	 * Persist settings.
	 *
	 * @param array<string, mixed> $settings Raw values.
	 */
	public function save( $settings ) {
		$settings = array_merge( $this->all(), is_array( $settings ) ? $settings : array() );

		$clean = array(
			'prefix'         => isset( $settings['prefix'] ) ? self::strip_date_from_prefix( sanitize_text_field( $settings['prefix'] ) ) : self::defaults()['prefix'],
			'tooltip'        => isset( $settings['tooltip'] ) ? sanitize_textarea_field( $settings['tooltip'] ) : self::defaults()['tooltip'],
			'enable_tooltip' => ( isset( $settings['enable_tooltip'] ) && 'yes' === $settings['enable_tooltip'] ) ? 'yes' : 'no',
			'auto_display'   => ( isset( $settings['auto_display'] ) && 'yes' === $settings['auto_display'] ) ? 'yes' : 'no',
			'auto_fill'      => ( isset( $settings['auto_fill'] ) && 'yes' === $settings['auto_fill'] ) ? 'yes' : 'no',
			'default_date'   => self::REFERENCE_DATE_GENERAL,
			'date_style'     => $this->sanitize_date_style( isset( $settings['date_style'] ) ? $settings['date_style'] : 'parens' ),
			'enable_cjm'     => ( isset( $settings['enable_cjm'] ) && 'yes' === $settings['enable_cjm'] ) ? 'yes' : 'no',
			'daily_xml'      => ( isset( $settings['daily_xml'] ) && 'yes' === $settings['daily_xml'] ) ? 'yes' : 'no',
			'daily_time'     => $this->sanitize_daily_time( isset( $settings['daily_time'] ) ? $settings['daily_time'] : '07:00' ),
			'auto_rebuild'   => ( isset( $settings['auto_rebuild'] ) && 'yes' === $settings['auto_rebuild'] ) ? 'yes' : 'no',
			'auto_maintenance' => ( isset( $settings['auto_maintenance'] ) && 'yes' === $settings['auto_maintenance'] ) ? 'yes' : 'no',
			'email_alerts'    => ( isset( $settings['email_alerts'] ) && 'yes' === $settings['email_alerts'] ) ? 'yes' : 'no',
			'alert_email'     => isset( $settings['alert_email'] ) && is_email( $settings['alert_email'] ) ? sanitize_email( $settings['alert_email'] ) : '',
			'export_format'  => $this->sanitize_format( isset( $settings['export_format'] ) ? $settings['export_format'] : 'xml' ),
			'file_oblik'     => $this->sanitize_file_part( isset( $settings['file_oblik'] ) ? $settings['file_oblik'] : 'webshop', 'webshop' ),
			'file_adresa'    => sanitize_text_field( isset( $settings['file_adresa'] ) ? $settings['file_adresa'] : '' ),
			'file_oznaka'      => $this->normalize_oznaka( isset( $settings['file_oznaka'] ) ? $settings['file_oznaka'] : '001' ) ?: '001',
			'file_pohrana'     => $this->sanitize_pohrana( isset( $settings['file_pohrana'] ) ? $settings['file_pohrana'] : 1 ),
			'brand_taxonomy'   => $this->sanitize_brand_taxonomy( isset( $settings['brand_taxonomy'] ) ? $settings['brand_taxonomy'] : '' ),
			'brand_manual'     => sanitize_text_field( isset( $settings['brand_manual'] ) ? $settings['brand_manual'] : '' ),
			'multi_poslovnica' => ( isset( $settings['multi_poslovnica'] ) && 'yes' === $settings['multi_poslovnica'] ) ? 'yes' : 'no',
			'public_cjenik'    => 'yes',
			'i18n'           => $this->sanitize_i18n( isset( $settings['i18n'] ) ? $settings['i18n'] : array() ),
		);

		$current = $this->all();
		if ( $clean !== $current ) {
			update_option( self::OPTION_KEY, $clean, false );
		}
		$this->settings = $clean;

		if ( class_exists( 'Brendigo_Sidrena_Cijena_I18n' ) ) {
			Brendigo_Sidrena_Cijena_I18n::instance()->register_strings();
		}
	}

	/**
	 * Prefix or tooltip for the current shop language.
	 *
	 * @param string $key prefix|tooltip.
	 * @return string
	 */
	public function get_translated( $key ) {
		if ( class_exists( 'Brendigo_Sidrena_Cijena_I18n' ) ) {
			return Brendigo_Sidrena_Cijena_I18n::instance()->get_front_string( $key );
		}

		return (string) $this->get( $key );
	}

	/**
	 * Whether the storefront tooltip is on.
	 *
	 * @return bool
	 */
	public function is_tooltip_enabled() {
		return 'yes' === $this->get( 'enable_tooltip' );
	}

	/**
	 * Whether sidrena is appended automatically under shop prices.
	 *
	 * @return bool
	 */
	public function is_auto_display_enabled() {
		return 'yes' === $this->get( 'auto_display' );
	}

	/**
	 * Whether new products get sidrena cijena and today's date when those fields are empty.
	 *
	 * @return bool
	 */
	public function is_auto_fill_enabled() {
		return 'yes' === $this->get( 'auto_fill' );
	}

	/**
	 * Live public feed format: xml or csv. Archive files keep the type they were generated as.
	 *
	 * @return string
	 */
	public function export_format() {
		return $this->sanitize_format( $this->get( 'export_format', 'xml' ) );
	}

	/**
	 * Local WordPress-site time used for the scheduled daily publication.
	 *
	 * @return string HH:MM in 24-hour format.
	 */
	public function daily_time() {
		return $this->sanitize_daily_time( $this->get( 'daily_time', '07:00' ) );
	}

	/**
	 * Whether extra outlets publish their own feeds.
	 *
	 * True when the Poslovnice repeater has at least one outlet.
	 *
	 * @return bool
	 */
	public function is_multi_poslovnica() {
		if ( ! class_exists( 'Brendigo_Sidrena_Cijena_Poslovnice' ) ) {
			return false;
		}

		return array() !== Brendigo_Sidrena_Cijena_Poslovnice::instance()->all();
	}

	/**
	 * /sidrena-cjenik is always published.
	 *
	 * @return bool
	 */
	public function is_public_cjenik_enabled() {
		return true;
	}

	/**
	 * Whether the unit-price (CJM) controls are shown.
	 *
	 * @return bool
	 */
	public function is_cjm_enabled() {
		return 'yes' === $this->get( 'enable_cjm' );
	}

	/**
	 * Insert a unit phrase after the prefix words.
	 *
	 * "Cijena" + "za kg/l" becomes "Cijena za kg/l".
	 *
	 * @param string $prefix Base prefix.
	 * @param string $suffix Unit phrase.
	 * @return string
	 */
	public static function append_unit_suffix( $prefix, $suffix ) {
		$prefix = trim( (string) $prefix );
		$suffix = trim( (string) $suffix );
		if ( '' === $prefix || '' === $suffix ) {
			return $prefix;
		}

		if ( false !== stripos( $prefix, $suffix ) ) {
			return $prefix;
		}

		$paren = strpos( $prefix, ' (' );
		if ( false !== $paren ) {
			return trim( substr( $prefix, 0, $paren ) ) . ' ' . $suffix . substr( $prefix, $paren );
		}

		$colon = strpos( $prefix, ':' );
		if ( false !== $colon ) {
			return trim( substr( $prefix, 0, $colon ) ) . ' ' . $suffix . substr( $prefix, $colon );
		}

		return trim( $prefix . ' ' . $suffix );
	}

	/**
	 * Storefront label for one manual catalog row, including its unit when CJM is on.
	 *
	 * @param array<string, string> $row Catalog row.
	 * @return string
	 */
	public function label_for_catalog_row( $row ) {
		$prefix = $this->prefix_words();

		if ( $this->is_cjm_enabled() && is_array( $row ) && isset( $row['cjm'] ) && 'yes' === $row['cjm'] ) {
			$prefix = self::append_unit_suffix( $prefix, isset( $row['cjm_suffix'] ) ? (string) $row['cjm_suffix'] : '' );
		}

		$date = Brendigo_Sidrena_Cijena_Catalog::instance()->display_date( is_array( $row ) ? $row : array() );
		if ( '' === $date ) {
			return $prefix;
		}

		return $this->format_prefix_with_date( $prefix, $date );
	}

	/**
	 * Object code for filenames and /sidrena-cijena-{oznaka}.xml.
	 *
	 * @param mixed $value Raw code.
	 * @return string
	 */
	public function normalize_oznaka( $value ) {
		$value = is_string( $value ) || is_numeric( $value ) ? (string) $value : '';
		$value = sanitize_text_field( $value );
		$value = preg_replace( '/[^A-Za-z0-9\-]/', '', $value );
		$value = is_string( $value ) ? trim( $value, '-' ) : '';

		return $value;
	}

	/**
	 * Filename part that keeps spaces, hyphens, dots, and the typed case.
	 *
	 * Underscores are turned into spaces so they stay out of the field separator.
	 * Other punctuation (commas in a street address) becomes a space so the
	 * archive name can be opened from a public URL.
	 *
	 * @param mixed  $value    Raw.
	 * @param string $fallback Fallback when empty.
	 * @return string
	 */
	public function sanitize_file_part( $value, $fallback = '' ) {
		$value = is_string( $value ) || is_numeric( $value ) ? (string) $value : '';
		$value = sanitize_text_field( $value );
		$value = str_replace( array( '_', '/', '\\', ':', '?', '*', '"', '<', '>', '|' ), ' ', $value );
		$value = preg_replace( '/[^\p{L}\p{N} .\-]+/u', ' ', $value );
		$value = preg_replace( '/\s+/u', ' ', $value );
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value ) {
			return $fallback;
		}

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, 80, 'UTF-8' );
		}

		return substr( $value, 0, 80 );
	}

	/**
	 * Taxonomies a shop can use as the product brand.
	 *
	 * @return array<string, string> slug => label
	 */
	public function brand_taxonomy_choices() {
		if ( ! class_exists( 'Brendigo_Sidrena_Cijena_Plugin' ) || ! Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ) {
			return array();
		}

		$skip  = array( 'product_type', 'product_visibility', 'product_shipping_class' );
		$taxes = get_object_taxonomies( 'product', 'objects' );
		$out   = array();

		if ( ! is_array( $taxes ) ) {
			return $out;
		}

		foreach ( $taxes as $tax ) {
			if ( ! $tax instanceof WP_Taxonomy || in_array( $tax->name, $skip, true ) ) {
				continue;
			}

			$label = isset( $tax->labels->singular_name ) ? (string) $tax->labels->singular_name : (string) $tax->label;
			$out[ $tax->name ] = $label . ' (' . $tax->name . ')';
		}

		asort( $out );

		return $out;
	}

	/**
	 * Keep a brand taxonomy only when it is still registered.
	 *
	 * @param mixed $value Raw slug.
	 * @return string
	 */
	public function sanitize_brand_taxonomy( $value ) {
		$value = is_string( $value ) ? sanitize_key( $value ) : '';
		if ( '' === $value ) {
			return '';
		}

		$choices = $this->brand_taxonomy_choices();

		return isset( $choices[ $value ] ) ? $value : '';
	}

	/**
	 * Shop-wide brand used when a product or manual row has none of its own.
	 *
	 * @return string
	 */
	public function manual_marka() {
		return trim( (string) $this->get( 'brand_manual', '' ) );
	}

	/**
	 * Brand for one manual row, falling back to the settings value.
	 *
	 * @param string $marka Row value.
	 * @return string
	 */
	public function row_marka( $marka ) {
		$marka = trim( (string) $marka );

		return '' !== $marka ? $marka : $this->manual_marka();
	}

	/**
	 * Brand written to the public feed for a WooCommerce product.
	 *
	 * Terms on the product win. A variation with no terms uses the parent.
	 * The settings value is used when the taxonomy has no term.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return string
	 */
	public function product_marka( $product ) {
		$manual = $this->manual_marka();
		$tax    = (string) $this->get( 'brand_taxonomy', '' );

		if ( '' === $tax || ! taxonomy_exists( $tax ) || ! $product instanceof WC_Product ) {
			return $manual;
		}

		$ids = array( (int) $product->get_id() );
		if ( $product->is_type( 'variation' ) ) {
			$ids[] = (int) $product->get_parent_id();
		}

		foreach ( $ids as $id ) {
			if ( $id < 1 ) {
				continue;
			}

			$terms = wp_get_post_terms( $id, $tax, array( 'fields' => 'names' ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$names = array();
			foreach ( $terms as $term ) {
				$term = trim( (string) $term );
				if ( '' !== $term ) {
					$names[] = $term;
				}
			}

			if ( array() !== $names ) {
				return implode( ', ', $names );
			}
		}

		return $manual;
	}

	/**
	 * Store one brand name on the selected taxonomy.
	 *
	 * @param WC_Product $product Product or variation.
	 * @param string     $marka   Brand name.
	 * @return bool
	 */
	public function assign_product_marka( $product, $marka ) {
		$tax   = (string) $this->get( 'brand_taxonomy', '' );
		$marka = sanitize_text_field( (string) $marka );

		if ( '' === $tax || '' === $marka || ! taxonomy_exists( $tax ) || ! $product instanceof WC_Product ) {
			return false;
		}

		$product_id = $product->is_type( 'variation' ) ? (int) $product->get_parent_id() : (int) $product->get_id();
		if ( $product_id < 1 ) {
			return false;
		}

		$term = term_exists( $marka, $tax );
		if ( ! $term ) {
			$term = wp_insert_term( $marka, $tax );
		}

		if ( is_wp_error( $term ) ) {
			return false;
		}

		$term_id = is_array( $term ) && isset( $term['term_id'] ) ? absint( $term['term_id'] ) : absint( $term );
		if ( $term_id < 1 ) {
			return false;
		}

		$current = wp_get_object_terms( $product_id, $tax, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $current ) ) {
			$current = array_values( array_map( 'absint', (array) $current ) );
			if ( 1 === count( $current ) && $term_id === $current[0] ) {
				return false;
			}
		}

		$result = wp_set_object_terms( $product_id, array( $term_id ), $tax, false );

		return ! is_wp_error( $result );
	}

	/**
	 * Suggested store address for the legal filename (WooCommerce address or host).
	 *
	 * @return string
	 */
	public function default_file_address() {
		$parts = array();
		foreach ( array( 'woocommerce_store_address', 'woocommerce_store_address_2', 'woocommerce_store_postcode', 'woocommerce_store_city' ) as $key ) {
			$value = trim( (string) get_option( $key, '' ) );
			if ( '' !== $value ) {
				$parts[] = $value;
			}
		}

		if ( array() !== $parts ) {
			return implode( ' ', $parts );
		}

		$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		return '' !== $host ? $host : 'webshop';
	}



	/**
	 * Storage sequence, 1+.
	 *
	 * @param mixed $value Raw.
	 * @return int
	 */
	public function sanitize_pohrana( $value ) {
		$n = absint( $value );

		return $n > 0 ? $n : 1;
	}

	/**
	 * Stored translation for one language, or empty.
	 *
	 * @param string $key  prefix|tooltip.
	 * @param string $lang Language code.
	 * @return string
	 */
	public function get_i18n_value( $key, $lang ) {
		$i18n = $this->get( 'i18n' );
		if ( ! is_array( $i18n ) || empty( $i18n[ $key ] ) || ! is_array( $i18n[ $key ] ) ) {
			return '';
		}

		$lang = (string) $lang;
		if ( isset( $i18n[ $key ][ $lang ] ) && is_string( $i18n[ $key ][ $lang ] ) ) {
			return trim( $i18n[ $key ][ $lang ] );
		}

		return '';
	}

	/**
	 * Remove an embedded date so the prefix is only the words.
	 *
	 * @param string $prefix Raw prefix.
	 * @return string
	 */
	public static function strip_date_from_prefix( $prefix ) {
		$prefix = preg_replace( '/\s*\(\s*\d{1,2}\.\d{1,2}\.\d{4}\.?\s*\)\s*/', '', $prefix );
		$prefix = preg_replace( '/\s*:\s*\d{1,2}\.\d{1,2}\.\d{4}\.?\s*/', '', $prefix );
		$prefix = preg_replace( '/\s+\d{1,2}\.\d{1,2}\.\d{4}\.?\s*/', ' ', $prefix );
		$prefix = is_string( $prefix ) ? trim( preg_replace( '/\s+/', ' ', $prefix ) ) : '';

		return $prefix;
	}

	/**
	 * Prefix + default date as shown on the shop.
	 *
	 * @return string
	 */
	public function get_preview_label() {
		$prefix = $this->prefix_words( true );
		$date   = trim( (string) $this->get( 'default_date' ) );
		if ( '' === $date ) {
			return $prefix;
		}

		return $this->format_prefix_with_date( $prefix, rtrim( $date, '.' ) . '.' );
	}

	/**
	 * Space before the amount. A colon only when prefix words are shown.
	 *
	 * @param bool $stored_only Use the saved prefix, not a translation.
	 * @return string
	 */
	public function preview_separator( $stored_only = true ) {
		return '' === $this->prefix_words( $stored_only ) ? ' ' : ': ';
	}

	/**
	 * Prefix words for the line. Empty plus parentheses keeps "Cijena".
	 * Empty plus the plain date format stays empty, so the line is the date and the amount.
	 *
	 * @param bool $stored_only Use the saved prefix, not a translation.
	 * @return string
	 */
	public function prefix_words( $stored_only = false ) {
		$raw    = $stored_only ? (string) $this->get( 'prefix' ) : $this->get_translated( 'prefix' );
		$prefix = self::strip_date_from_prefix( $raw );
		if ( '' === $prefix && 'plain' !== $this->get( 'date_style' ) ) {
			$prefix = $stored_only ? self::defaults()['prefix'] : __( 'Cijena', 'brendigo-sidrena-cijena' );
		}

		return $prefix;
	}

	/**
	 * Combine prefix words and a date for the storefront.
	 *
	 * @param string $prefix Label without date.
	 * @param string $date   Normalized date.
	 * @return string
	 */
	public function format_prefix_with_date( $prefix, $date ) {
		$prefix = trim( (string) $prefix );
		$date   = trim( (string) $date );

		if ( '' === $date ) {
			return $prefix;
		}

		if ( '' === $prefix && 'plain' === $this->get( 'date_style' ) ) {
			return $date;
		}

		if ( 'plain' === $this->get( 'date_style' ) ) {
			return $prefix . ' ' . $date;
		}

		return $prefix . ' (' . $date . ')';
	}

	/**
	 * Per-language prefix and tooltip (not the default language).
	 *
	 * @param mixed $raw Raw i18n payload.
	 * @return array{prefix:array<string,string>,tooltip:array<string,string>}
	 */
	private function sanitize_i18n( $raw ) {
		$out = array(
			'prefix'  => array(),
			'tooltip' => array(),
		);

		if ( ! is_array( $raw ) ) {
			return $out;
		}

		$default_lang = class_exists( 'Brendigo_Sidrena_Cijena_I18n' ) ? Brendigo_Sidrena_Cijena_I18n::instance()->get_default_language() : '';

		foreach ( array( 'prefix', 'tooltip' ) as $key ) {
			if ( empty( $raw[ $key ] ) || ! is_array( $raw[ $key ] ) ) {
				continue;
			}

			foreach ( $raw[ $key ] as $lang => $value ) {
				$lang = strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $lang ) );
				if ( '' === $lang || $lang === $default_lang ) {
					continue;
				}

				if ( 'prefix' === $key ) {
					$out[ $key ][ $lang ] = self::strip_date_from_prefix( sanitize_text_field( (string) $value ) );
				} else {
					$out[ $key ][ $lang ] = sanitize_textarea_field( (string) $value );
				}
			}
		}

		return $out;
	}

	/**
	 * Allowed date formats: parens or plain.
	 *
	 * @param mixed $style Raw style.
	 * @return string
	 */
	private function sanitize_date_style( $style ) {
		$style = is_string( $style ) ? $style : '';
		if ( 'na' === $style ) {
			return 'plain';
		}

		return in_array( $style, array( 'parens', 'plain' ), true ) ? $style : 'parens';
	}

	/**
	 * Normalize a local publication time to HH:MM. Invalid values fall back
	 * to the default publication time 07:00.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_daily_time( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ) {
			return '07:00';
		}

		return $value;
	}

	/**
	 * xml or csv.
	 *
	 * @param mixed $value Raw.
	 * @return string
	 */
	private function sanitize_format( $value ) {
		$value = is_string( $value ) ? strtolower( $value ) : '';

		return 'csv' === $value ? 'csv' : 'xml';
	}
}
