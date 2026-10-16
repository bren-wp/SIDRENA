<?php
/**
 * Shared price, date, type, and availability normalizers.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * One place for Croatian / WooCommerce amount and flag parsing.
 */
class Brendigo_Sidrena_Cijena_Money {

	/**
	 * Catalog / standalone price: keep Croatian thousand/decimal forms.
	 *
	 * @param mixed $price Raw value.
	 * @return string
	 */
	public static function normalize_price_catalog( $price ) {
		if ( null === $price ) {
			return '';
		}

		$price = sanitize_text_field( wp_unslash( (string) $price ) );
		if ( strlen( $price ) > 100 || preg_match( '/[%=<>]/', $price ) ) {
			return '';
		}
		// Accept only a complete amount with optional EUR notation. A character
		// whitelist alone could turn "12e3" into "123", which is unsafe.
		if ( ! preg_match( '/^\s*(?:EUR\s*)?[0-9][0-9.,\s\x{00A0}]*\s*(?:€|EUR)?\s*$/uiD', $price ) ) {
			return '';
		}
		$price = preg_replace( '/(?:EUR|€)/ui', '', $price );
		$price = str_replace( array( ' ', "\xc2\xa0" ), '', $price );
		$price = preg_replace( '/[^\d,.\-]/u', '', $price );

		if ( ! is_string( $price ) || '' === $price || '-' === $price || '.' === $price || ',' === $price ) {
			return '';
		}

		if ( preg_match( '/^-?\d{1,3}(\.\d{3})+,\d+$/', $price ) ) {
			$price = str_replace( '.', '', $price );
			$price = str_replace( ',', '.', $price );
		} elseif ( false !== strpos( $price, ',' ) && false === strpos( $price, '.' ) ) {
			$price = str_replace( ',', '.', $price );
		}

		if ( ! is_numeric( $price ) || ! is_finite( (float) $price ) || (float) $price < 0 || (float) $price > 999999999999.99 ) {
			return '';
		}

		return (string) $price;
	}

	/**
	 * WooCommerce product-meta price: wc_clean, comma decimal, then wc_format_decimal.
	 *
	 * @param mixed $price Raw value.
	 * @return string
	 */
	public static function normalize_price_woo( $price ) {
		if ( null === $price ) {
			return '';
		}

		$price = wc_clean( wp_unslash( (string) $price ) );
		$price = str_replace( array( ' ', "\xc2\xa0" ), '', $price );

		if ( '' === $price ) {
			return '';
		}

		if ( false !== strpos( $price, ',' ) && false === strpos( $price, '.' ) ) {
			$price = str_replace( ',', '.', $price );
		}

		if ( function_exists( 'wc_format_decimal' ) ) {
			$formatted = wc_format_decimal( $price );
			return ( false === $formatted || null === $formatted ) ? '' : (string) $formatted;
		}

		return $price;
	}

	/**
	 * Trailing period on a Croatian date, or empty.
	 *
	 * @param mixed $date Raw.
	 * @return string
	 */
	public static function normalize_date( $date ) {
		$date = sanitize_text_field( wp_unslash( (string) $date ) );
		$date = trim( $date );
		if ( '' === $date ) {
			return '';
		}

		$day = 0;
		$month = 0;
		$year = 0;
		if ( preg_match( '/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})\.?$/', $date, $matches ) ) {
			$day   = (int) $matches[1];
			$month = (int) $matches[2];
			$year  = (int) $matches[3];
		} elseif ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $matches ) ) {
			$year  = (int) $matches[1];
			$month = (int) $matches[2];
			$day   = (int) $matches[3];
		} else {
			return '';
		}

		if ( ! checkdate( $month, $day, $year ) ) {
			return '';
		}

		return sprintf( '%02d.%02d.%04d.', $day, $month, $year );
	}

	/**
	 * Normalized date for product-meta display.
	 *
	 * @param string $date Raw date.
	 * @return string
	 */
	public static function normalize_date_display( $date ) {
		return self::normalize_date( $date );
	}

	/**
	 * proizvod or usluga. Anything else becomes proizvod.
	 *
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function normalize_vrsta( $value ) {
		$value = strtolower( sanitize_text_field( wp_unslash( (string) $value ) ) );
		$value = str_replace( array( ' ', '_', '-' ), '', $value );

		if ( in_array( $value, array( 'usluga', 'service', 'services', 'usluge' ), true ) ) {
			return 'usluga';
		}

		return 'proizvod';
	}

	/**
	 * Legal availability flag.
	 *
	 * @param mixed $value Raw value.
	 * @return string dostupno|nedostupno
	 */
	public static function normalize_dostupnost( $value ) {
		$value = strtolower( sanitize_text_field( wp_unslash( (string) $value ) ) );
		$value = str_replace( array( ' ', '_', '-' ), '', $value );

		if ( in_array( $value, array( 'nedostupno', 'nedostupan', 'outofstock', 'out', 'no', 'ne', '0', 'false' ), true ) ) {
			return 'nedostupno';
		}

		return 'dostupno';
	}

	/**
	 * Availability stored for one outlet. Missing entries stay nedostupno so webshop stock is not copied.
	 *
	 * @param mixed                $product_id Product or variation id.
	 * @param array<string,string> $map        Product id => dostupno|nedostupno.
	 * @return string
	 */
	public static function dostupnost_from_outlet_map( $product_id, $map ) {
		$id = preg_replace( '/\D/', '', (string) $product_id );
		if ( ! is_string( $id ) || '' === $id || ! is_array( $map ) || ! isset( $map[ $id ] ) ) {
			return 'nedostupno';
		}

		return self::normalize_dostupnost( $map[ $id ] );
	}
}
