<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sidrena_Utils {
	public static function defaults() {
		return array(
			'business_mode'        => 'mixed',
			'display_anchor'       => 'yes',
			'display_lowest_30'    => 'yes',
			'label_mode'           => 'date_only',
			'label_custom'         => 'Cijena na %s',
			'anchor_tooltip_enabled' => 'yes',
			'anchor_tooltip_text'    => 'Sidrena cijena prikazuje referentnu cijenu evidentiranu za mjerodavni datum.',
			'default_ref_date'     => '2026-09-10',
			'fmcg_ref_date'        => '2025-05-02',
			'generate_csv'         => 'yes',
			'generate_xml'         => 'yes',
			'csv_delimiter'        => ';',
			'generation_time'      => '06:30',
			'retention_days'       => 45,
			'enable_rest_index'    => 'yes',
			'publish_manifest'     => 'yes',
			'enable_public_html'   => 'yes',
			'strict_publication'   => 'yes',
			'track_price_history'  => 'yes',
		);
	}

	public static function settings() {
		$settings = get_option( 'sidrena_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		if ( empty( $settings['fmcg_ref_date'] ) && ! empty( $settings['fmsid_ref_date'] ) ) {
			$settings['fmcg_ref_date'] = self::sanitize_date( $settings['fmsid_ref_date'], '2025-05-02' );
		}
		unset( $settings['fmsid_ref_date'] );
		$settings = wp_parse_args( $settings, self::defaults() );
		$settings['retention_days'] = max( 30, absint( $settings['retention_days'] ) );
		return $settings;
	}


	public static function admin_capability() {
		$capability = apply_filters( 'sidrena_admin_capability', 'manage_sidrena' );
		$capability = is_string( $capability ) ? sanitize_key( $capability ) : 'manage_sidrena';
		return $capability ? $capability : 'manage_sidrena';
	}

	public static function donation_url() {
		$url = apply_filters( 'sidrena_donation_url', 'https://sidrene-cijene.com.hr/#donirajte' );
		return is_string( $url ) ? esc_url_raw( $url ) : '';
	}

	public static function locations() {
		$locations = get_option( 'sidrena_locations', array() );
		if ( ! is_array( $locations ) || empty( $locations ) ) {
			$locations = array(
				array(
					'id'       => 'webshop',
					'enabled'  => 'yes',
					'kind'     => 'webshop',
					'address'  => '',
					'code'     => 'WEB-01',
					'sequence' => 1,
				),
			);
		}
		return $locations;
	}

	public static function decimal( $value ) {
		if ( '' === $value || null === $value ) {
			return '';
		}

		$value = trim( str_replace( array( "\xC2\xA0", ' ' ), '', (string) $value ) );
		if ( '' === $value ) {
			return '';
		}

		$comma = strrpos( $value, ',' );
		$dot   = strrpos( $value, '.' );
		if ( false !== $comma && false !== $dot ) {
			if ( $comma > $dot ) {
				$value = str_replace( '.', '', $value );
				$value = str_replace( ',', '.', $value );
			} else {
				$value = str_replace( ',', '', $value );
			}
		} elseif ( false !== $comma ) {
			$value = str_replace( ',', '.', $value );
		}

		if ( ! is_numeric( $value ) ) {
			return '';
		}
		$number = (float) $value;
		if ( ! is_finite( $number ) || abs( $number ) > 99999999999999.0 ) {
			return '';
		}

		if ( function_exists( 'wc_format_decimal' ) ) {
			return wc_format_decimal( $value );
		}

		return rtrim( rtrim( number_format( $number, 6, '.', '' ), '0' ), '.' );
	}

	public static function money( $value, $decimals = 2 ) {
		if ( '' === $value || null === $value ) {
			return '';
		}
		return number_format( (float) $value, $decimals, ',', '' );
	}

	public static function sanitize_date( $date, $fallback = '' ) {
		$date = sanitize_text_field( (string) $date );
		$dt   = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		if ( $dt && $dt->format( 'Y-m-d' ) === $date ) {
			return $date;
		}
		return $fallback;
	}


	public static function format_mysql_datetime( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( ! $value ) {
			return '';
		}
		try {
			$dt = new DateTimeImmutable( $value, wp_timezone() );
			return $dt->format( 'd.m.Y. H:i:s' );
		} catch ( Exception $e ) {
			return $value;
		}
	}

	public static function date_display( $date ) {
		$date = self::sanitize_date( $date );
		if ( ! $date ) {
			return '';
		}
		$dt = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		return $dt ? $dt->format( 'd.m.Y.' ) : '';
	}

	public static function current_reference_date( $product_id = 0 ) {
		$settings = self::settings();
		if ( $product_id ) {
			$lookup_ids = array( (int) $product_id );
			$parent_id  = wp_get_post_parent_id( $product_id );
			if ( $parent_id ) {
				$lookup_ids[] = (int) $parent_id;
			}

			foreach ( $lookup_ids as $lookup_id ) {
				$custom = get_post_meta( $lookup_id, '_sidrena_anchor_date', true );
				if ( $custom ) {
					return sanitize_text_field( $custom );
				}
			}

			foreach ( $lookup_ids as $lookup_id ) {
				$group = get_post_meta( $lookup_id, '_sidrena_reference_group', true );
				if ( 'fmcg' === $group ) {
					return $settings['fmcg_ref_date'];
				}
				if ( 'standard' === $group ) {
					return $settings['default_ref_date'];
				}
			}
		}
		return $settings['default_ref_date'];
	}

	public static function anchor_label( $date ) {
		$settings = self::settings();
		$display  = self::date_display( $date );

		if ( 'date_only' === $settings['label_mode'] ) {
			return sprintf(
				/* translators: %s is a date. */
				__( 'Cijena na %s', 'sidrena' ),
				$display
			);
		}

		$template = self::translate_user_string( trim( (string) $settings['label_custom'] ), 'label_custom' );
		if ( false === strpos( $template, '%s' ) ) {
			$template .= ' %s';
		}
		return sprintf( $template, $display );
	}

	public static function anchor_tooltip() {
		$settings = self::settings();
		if ( 'yes' !== $settings['anchor_tooltip_enabled'] ) {
			return '';
		}
		return self::translate_user_string( trim( (string) $settings['anchor_tooltip_text'] ), 'anchor_tooltip_text' );
	}

	public static function register_translation_strings() {
		$settings = self::settings();
		$strings  = array(
			'label_custom'        => (string) $settings['label_custom'],
			'anchor_tooltip_text' => (string) $settings['anchor_tooltip_text'],
		);

		foreach ( $strings as $name => $value ) {
			if ( '' === trim( $value ) ) {
				continue;
			}
			if ( function_exists( 'pll_register_string' ) ) {
				pll_register_string( 'Sidrena ' . $name, $value, 'Sidrena', false );
			}
			do_action( 'wpml_register_single_string', 'Sidrena', $name, $value );
		}
	}

	public static function translate_user_string( $value, $name ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}
		if ( function_exists( 'pll__' ) ) {
			$value = pll__( $value );
		}
		return (string) apply_filters( 'wpml_translate_single_string', $value, 'Sidrena', $name );
	}

	public static function upload_paths() {
		$uploads = wp_upload_dir();
		$base    = trailingslashit( $uploads['basedir'] ) . 'sidrena/';
		$url     = trailingslashit( $uploads['baseurl'] ) . 'sidrena/';
		return array(
			'base_dir'     => $base,
			'archive_dir'  => $base . 'arhiva/',
			'base_url'     => $url,
			'archive_url'  => $url . 'arhiva/',
			'manifest'      => $base . 'manifest.json',
			'manifest_url'  => $url . 'manifest.json',
			'snapshot_dir'  => $base . 'public/',
			'snapshot_url'  => $url . 'public/',
		);
	}

	public static function sanitize_location_id( $value ) {
		$value = sanitize_title( $value );
		return $value ? $value : 'lokacija';
	}

	public static function filename_part( $value ) {
		$value = wp_strip_all_tags( (string) $value );
		$value = preg_replace( '/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', ' ', $value );
		$value = preg_replace( '/\s+/u', ' ', $value );
		$value = trim( (string) $value, " .\t\n\r\0\x0B_-" );
		return $value ? $value : 'objekt';
	}

	public static function public_snapshot_path( $location_id ) {
		$paths = self::upload_paths();
		$id    = self::sanitize_location_id( $location_id );
		return $paths['snapshot_dir'] . 'cjenik-' . $id . '.json';
	}

	public static function csv_safe_cell( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		$value = (string) $value;
		if ( '' === $value || is_numeric( str_replace( array( ' ', ',' ), array( '', '.' ), $value ) ) ) {
			return $value;
		}

		// Spreadsheet applications may interpret text as a formula even when a
		// dangerous prefix is preceded by whitespace. Prefix such text with an
		// apostrophe while leaving genuine numeric values untouched.
		if ( preg_match( '/^[\\x00-\\x20]*[=+\\-@]/', $value ) || preg_match( '/^[\\t\\r\\n]/', $value ) ) {
			return "'" . $value;
		}
		return $value;
	}

	public static function import_header_key( $header ) {
		$header = trim( (string) $header );
		if ( '' === $header ) {
			return '';
		}
		if ( function_exists( 'remove_accents' ) ) {
			$header = remove_accents( $header );
		} else {
			$header = strtr(
				$header,
				array(
					'č' => 'c', 'ć' => 'c', 'đ' => 'd', 'š' => 's', 'ž' => 'z',
					'Č' => 'C', 'Ć' => 'C', 'Đ' => 'D', 'Š' => 'S', 'Ž' => 'Z',
				)
			);
		}
		$header = function_exists( 'mb_strtolower' ) ? mb_strtolower( $header, 'UTF-8' ) : strtolower( $header );
		$header = preg_replace( '/[^a-z0-9]+/', '_', $header );
		return trim( (string) $header, '_' );
	}

	public static function normalize_unit( $unit ) {
		$unit = trim( (string) $unit );
		$unit = function_exists( 'mb_strtolower' ) ? mb_strtolower( $unit, 'UTF-8' ) : strtolower( $unit );
		$unit = str_replace( array( ' ', '.', '²', '^2', '³', '^3' ), array( '', '', '2', '2', '3', '3' ), $unit );
		$aliases = array(
			'miligram' => 'mg', 'miligrami' => 'mg',
			'gram' => 'g', 'grama' => 'g', 'grami' => 'g',
			'dekagram' => 'dag', 'dekagrama' => 'dag',
			'kilogram' => 'kg', 'kilograma' => 'kg',
			'mililitar' => 'ml', 'mililitara' => 'ml',
			'centilitar' => 'cl', 'centilitara' => 'cl',
			'decilitar' => 'dl', 'decilitara' => 'dl',
			'lit' => 'l', 'litra' => 'l', 'litre' => 'l', 'litara' => 'l', 'liter' => 'l',
			'metar' => 'm', 'metra' => 'm', 'metara' => 'm',
			'komad' => 'kom', 'komada' => 'kom', 'ko' => 'kom', 'pcs' => 'kom', 'pc' => 'kom',
		);
		return isset( $aliases[ $unit ] ) ? $aliases[ $unit ] : $unit;
	}

	public static function parse_quantity_with_unit( $value ) {
		$value = trim( str_replace( "\xC2\xA0", ' ', (string) $value ) );
		if ( '' === $value || ! preg_match( '/^([0-9][0-9\\s.,]*)\\s*([^0-9\\s.,].*)?$/u', $value, $matches ) ) {
			return array();
		}

		$number = self::decimal( $matches[1] );
		if ( '' === $number || (float) $number <= 0 ) {
			return array();
		}

		$unit = isset( $matches[2] ) ? self::normalize_unit( $matches[2] ) : '';
		return array(
			'quantity' => $number,
			'unit'     => $unit,
		);
	}

	public static function calculate_unit_price( $retail_price, $quantity, $quantity_unit ) {
		$retail_price = self::decimal( $retail_price );
		$quantity     = self::decimal( $quantity );
		$unit_key     = self::normalize_unit( $quantity_unit );
		if ( '' === $retail_price || '' === $quantity || (float) $quantity <= 0 ) {
			return array();
		}

		$units = array(
			'mg'  => array( 'base' => 'kg', 'multiplier' => 0.000001 ),
			'g'   => array( 'base' => 'kg', 'multiplier' => 0.001 ),
			'dag' => array( 'base' => 'kg', 'multiplier' => 0.01 ),
			'kg'  => array( 'base' => 'kg', 'multiplier' => 1.0 ),
			'ml'  => array( 'base' => 'l', 'multiplier' => 0.001 ),
			'cl'  => array( 'base' => 'l', 'multiplier' => 0.01 ),
			'dl'  => array( 'base' => 'l', 'multiplier' => 0.1 ),
			'l'   => array( 'base' => 'l', 'multiplier' => 1.0 ),
			'mm'  => array( 'base' => 'm', 'multiplier' => 0.001 ),
			'cm'  => array( 'base' => 'm', 'multiplier' => 0.01 ),
			'dm'  => array( 'base' => 'm', 'multiplier' => 0.1 ),
			'm'   => array( 'base' => 'm', 'multiplier' => 1.0 ),
			'mm2' => array( 'base' => 'm²', 'multiplier' => 0.000001 ),
			'cm2' => array( 'base' => 'm²', 'multiplier' => 0.0001 ),
			'dm2' => array( 'base' => 'm²', 'multiplier' => 0.01 ),
			'm2'  => array( 'base' => 'm²', 'multiplier' => 1.0 ),
			'cm3' => array( 'base' => 'm³', 'multiplier' => 0.000001 ),
			'dm3' => array( 'base' => 'm³', 'multiplier' => 0.001 ),
			'm3'  => array( 'base' => 'm³', 'multiplier' => 1.0 ),
			'kom' => array( 'base' => 'kom', 'multiplier' => 1.0 ),
		);
		if ( ! isset( $units[ $unit_key ] ) ) {
			return array();
		}

		$base_amount = (float) $quantity * (float) $units[ $unit_key ]['multiplier'];
		if ( $base_amount <= 0 ) {
			return array();
		}
		$price = (float) $retail_price / $base_amount;
		if ( ! is_finite( $price ) || $price < 0 || $price > 99999999999999.0 ) {
			return array();
		}
		return array(
			'unit'       => $units[ $unit_key ]['base'],
			'unit_price' => self::decimal( number_format( $price, 4, '.', '' ) ),
		);
	}


	public static function normalize_text_encoding( $contents ) {
		$contents = (string) $contents;
		if ( '' === $contents ) {
			return '';
		}
		if ( 0 === strncmp( $contents, "\xEF\xBB\xBF", 3 ) ) {
			$contents = substr( $contents, 3 );
		}
		if ( 1 === preg_match( '//u', $contents ) ) {
			return $contents;
		}

		$encodings = array( 'Windows-1250', 'ISO-8859-2' );
		// Croatian Š/Ž/š/ž occupy the C1 range in Windows-1250 and
		// A9/AE/B9/BE in ISO-8859-2. Prefer the matching decoder when those
		// distinguishing bytes are present; otherwise the shared Croatian
		// letters (Č/Ć/Đ/č/ć/đ) decode identically in both encodings.
		if ( preg_match( '/[\\xA9\\xAE\\xB9\\xBE]/', $contents ) ) {
			$encodings = array( 'ISO-8859-2', 'Windows-1250' );
		} elseif ( preg_match( '/[\\x80-\\x9F]/', $contents ) ) {
			$encodings = array( 'Windows-1250', 'ISO-8859-2' );
		}

		$source_questions = substr_count( $contents, '?' );
		foreach ( $encodings as $encoding ) {
			$converted = false;
			if ( function_exists( 'mb_convert_encoding' ) ) {
				try {
					$converted = @mb_convert_encoding( $contents, 'UTF-8', $encoding ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				} catch ( Throwable $e ) {
					$converted = false;
				}
				if ( is_string( $converted ) && 1 === preg_match( '//u', $converted ) && false === strpos( $converted, "\xEF\xBF\xBD" ) && substr_count( $converted, '?' ) <= $source_questions ) {
					return $converted;
				}
			}
			if ( function_exists( 'iconv' ) ) {
				$converted = @iconv( $encoding, 'UTF-8//IGNORE', $contents ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( is_string( $converted ) && 1 === preg_match( '//u', $converted ) && false === strpos( $converted, "\xEF\xBF\xBD" ) && substr_count( $converted, '?' ) <= $source_questions ) {
					return $converted;
				}
			}
		}
		return wp_check_invalid_utf8( $contents, true );
	}


	public static function format_iso_datetime( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		try {
			$dt = new DateTimeImmutable( $value );
			return wp_date( 'd.m.Y. H:i', $dt->getTimestamp() );
		} catch ( Exception $e ) {
			return sanitize_text_field( $value );
		}
	}

	public static function schedule_timestamp( $time_string = '' ) {
		$settings    = self::settings();
		$time_string = $time_string ? $time_string : $settings['generation_time'];
		if ( ! preg_match( '/^(\d{2}):(\d{2})$/', $time_string, $matches ) ) {
			$matches = array( '', '06', '30' );
		}

		$timezone = wp_timezone();
		$now      = new DateTimeImmutable( 'now', $timezone );
		$next     = $now->setTime( (int) $matches[1], (int) $matches[2], 0 );
		if ( $next <= $now ) {
			$next = $next->modify( '+1 day' );
		}
		return $next->getTimestamp();
	}

	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	public static function is_public_wc_product( $product ) {
		if ( ! is_object( $product ) || ! is_callable( array( $product, 'get_id' ) ) || ! is_callable( array( $product, 'is_type' ) ) ) {
			return false;
		}

		$product_id = absint( $product->get_id() );
		$post_id    = $product->is_type( 'variation' ) && is_callable( array( $product, 'get_parent_id' ) )
			? absint( $product->get_parent_id() )
			: $product_id;
		$post       = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
			return false;
		}
		if ( function_exists( 'is_post_publicly_viewable' ) && ! is_post_publicly_viewable( $post ) ) {
			return false;
		}

		if ( $product->is_type( 'variation' ) ) {
			$variation_post = $product_id ? get_post( $product_id ) : null;
			if ( ! $variation_post || 'publish' !== $variation_post->post_status ) {
				return false;
			}
		}

		return true;
	}

	public static function product_anchor_price( $product_id ) {
		$value = get_post_meta( $product_id, '_sidrena_anchor_price', true );
		return '' === $value ? '' : (float) $value;
	}

	public static function product_meta_with_parent( $product, $key, $default = '' ) {
		if ( ! $product || ! is_callable( array( $product, 'get_id' ) ) ) {
			return $default;
		}

		$value = get_post_meta( $product->get_id(), $key, true );
		if ( '' !== $value && null !== $value ) {
			return $value;
		}

		if ( is_callable( array( $product, 'is_type' ) ) && $product->is_type( 'variation' ) ) {
			$parent_id = $product->get_parent_id();
			if ( $parent_id ) {
				$value = get_post_meta( $parent_id, $key, true );
				if ( '' !== $value && null !== $value ) {
					return $value;
				}
			}
		}
		return $default;
	}

	public static function get_product_code( $product ) {
		if ( ! $product || ! is_callable( array( $product, 'get_id' ) ) ) {
			return '';
		}

		$sku = is_callable( array( $product, 'get_sku' ) ) ? trim( (string) $product->get_sku() ) : '';
		if ( $sku ) {
			return $sku;
		}

		$custom = trim( (string) self::product_meta_with_parent( $product, '_sidrena_code' ) );
		if ( $custom ) {
			return $custom;
		}

		return 'WP-' . absint( $product->get_id() );
	}

	public static function find_product_id_by_code( $code ) {
		$code = sanitize_text_field( (string) $code );
		if ( ! $code || ! self::is_woocommerce_active() ) {
			return 0;
		}

		$product_id = absint( wc_get_product_id_by_sku( $code ) );
		if ( $product_id ) {
			return $product_id;
		}

		if ( preg_match( '/^WP-(\d+)$/i', $code, $matches ) ) {
			$product = wc_get_product( absint( $matches[1] ) );
			if ( $product ) {
				return absint( $product->get_id() );
			}
		}

		$ids = get_posts(
			array(
				'post_type'      => array( 'product', 'product_variation' ),
				'post_status'    => array( 'publish', 'private', 'draft' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_sidrena_code',
				'meta_value'     => $code,
				'no_found_rows'  => true,
			)
		);
		return empty( $ids ) ? 0 : absint( $ids[0] );
	}

	public static function get_brand( $product ) {
		if ( ! $product ) {
			return '';
		}

		$product_id = $product->get_id();
		if ( taxonomy_exists( 'product_brand' ) ) {
			$terms = wp_get_post_terms( $product_id, 'product_brand', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				return implode( ', ', $terms );
			}
		}

		$attribute = $product->get_attribute( 'pa_brand' );
		if ( $attribute ) {
			return $attribute;
		}
		return (string) get_post_meta( $product_id, '_sidrena_brand', true );
	}

	public static function get_barcode( $product ) {
		if ( ! $product ) {
			return '';
		}
		if ( is_callable( array( $product, 'get_global_unique_id' ) ) ) {
			$value = trim( (string) $product->get_global_unique_id() );
			if ( '' !== $value ) {
				return $value;
			}
		}

		$value = trim( (string) get_post_meta( $product->get_id(), '_global_unique_id', true ) );
		if ( '' !== $value ) {
			return $value;
		}

		return trim( (string) get_post_meta( $product->get_id(), '_sidrena_barcode', true ) );
	}

	public static function public_index() {
		$index = get_option( 'sidrena_public_index', array() );
		return is_array( $index ) ? $index : array();
	}

	public static function archive_index() {
		$index = get_option( 'sidrena_archive_index', array() );
		return is_array( $index ) ? $index : array();
	}

	public static function archive_stats() {
		$archive = self::archive_index();
		$days    = array();
		$oldest  = 0;
		$newest  = 0;
		foreach ( $archive as $entry ) {
			$ts = absint( $entry['generated_ts'] ?? 0 );
			if ( ! $ts ) {
				continue;
			}
			$days[ wp_date( 'Y-m-d', $ts ) ] = true;
			$oldest = ! $oldest || $ts < $oldest ? $ts : $oldest;
			$newest = $ts > $newest ? $ts : $newest;
		}
		return array(
			'files'         => count( $archive ),
			'distinct_days' => count( $days ),
			'oldest_ts'     => $oldest,
			'newest_ts'     => $newest,
		);
	}

	public static function archive_integrity() {
		$paths         = self::upload_paths();
		$current       = self::public_index();
		$archive       = self::archive_index();
		$current_names = array();
		$missing       = array();
		$hash_mismatch = array();

		foreach ( $current as $entry ) {
			$filename = isset( $entry['filename'] ) ? basename( (string) $entry['filename'] ) : '';
			if ( $filename ) {
				$current_names[ $filename ] = true;
			}
		}

		foreach ( $archive as $entry ) {
			$filename = isset( $entry['filename'] ) ? basename( (string) $entry['filename'] ) : '';
			if ( ! $filename ) {
				continue;
			}
			$path = $paths['archive_dir'] . $filename;
			if ( ! is_file( $path ) ) {
				$missing[] = $filename;
				continue;
			}
			$expected_hash = isset( $entry['sha256'] ) ? strtolower( trim( (string) $entry['sha256'] ) ) : '';
			if ( $expected_hash && function_exists( 'hash_file' ) ) {
				$actual_hash = strtolower( (string) hash_file( 'sha256', $path ) );
				if ( $actual_hash && ! hash_equals( $expected_hash, $actual_hash ) ) {
					$hash_mismatch[] = $filename;
				}
			}
		}

		$current_missing = array();
		foreach ( array_keys( $current_names ) as $filename ) {
			if ( ! is_file( $paths['archive_dir'] . $filename ) ) {
				$current_missing[] = $filename;
			}
		}

		return array(
			'archive_entries' => count( $archive ),
			'current_entries' => count( $current ),
			'missing_files'   => $missing,
			'current_missing' => $current_missing,
			'hash_mismatch'   => $hash_mismatch,
			'ok'              => empty( $missing ) && empty( $current_missing ) && empty( $hash_mismatch ),
		);
	}

	public static function archive_retention_status() {
		$settings = self::settings();
		$stats    = self::archive_stats();
		$days     = max( 30, absint( $settings['retention_days'] ) );
		$age      = 0;

		if ( ! empty( $stats['oldest_ts'] ) && ! empty( $stats['newest_ts'] ) ) {
			$age = max( 1, (int) floor( ( $stats['newest_ts'] - $stats['oldest_ts'] ) / DAY_IN_SECONDS ) + 1 );
		}

		return array(
			'configured_days' => $days,
			'archive_span'    => $age,
			'minimum_days'    => 30,
			'buffer_days'     => max( 0, $days - 30 ),
			'building'        => $age > 0 && $age < 30,
		);
	}

	public static function rules_are_effective() {
		$effective = new DateTimeImmutable( SIDRENA_RULES_EFFECTIVE . ' 00:00:00', wp_timezone() );
		$now       = new DateTimeImmutable( 'now', wp_timezone() );
		return $now >= $effective;
	}
}
