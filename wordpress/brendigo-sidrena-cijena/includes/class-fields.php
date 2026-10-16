<?php
/**
 * Sidrena product field list used by the product screen, catalog, and import.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * The seven WooCommerce product fields, with POST names and save rules.
 */
class Brendigo_Sidrena_Cijena_Fields {

	/**
	 * Product editor fields. Order matches the current save methods.
	 *
	 * kind: price (wp_unslash + normalize_price), text (sanitize_text_field),
	 * checkbox (isset, only when shop-wide CJM is on).
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function product_fields() {
		return array(
			array(
				'post'   => 'sidrena_cijena',
				'setter' => 'set_price',
				'kind'   => 'price',
			),
			array(
				'post'   => 'sidrena_cijena_datum',
				'setter' => 'set_date',
				'kind'   => 'text',
			),
			array(
				'post'   => 'sidrena_naziv_posebnog_oblika',
				'setter' => 'set_oblik',
				'kind'   => 'text',
			),
			array(
				'post'   => 'sidrena_jedinica_mjere',
				'setter' => 'set_jedinica',
				'kind'   => 'text',
			),
			array(
				'post'   => 'sidrena_cijena_jedinice_mjere',
				'setter' => 'set_jedinica_cijena',
				'kind'   => 'price',
			),
			array(
				'post'   => 'brendigo_sidrena_barcode',
				'setter' => 'set_barcode',
				'kind'   => 'text',
			),
			array(
				'post'   => 'brendigo_sidrena_cjenik_visibility',
				'setter' => 'set_cjenik_visibility',
				'kind'   => 'text',
			),
			array(
				'post'   => 'sidrena_cijena_cjm',
				'setter' => 'set_cjm_enabled',
				'kind'   => 'checkbox',
			),
			array(
				'post'   => 'sidrena_cijena_cjm_suffix',
				'setter' => 'set_cjm_suffix',
				'kind'   => 'text',
			),
		);
	}

	/**
	 * Persist simple / external product fields from $_POST.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function save_simple( $product ) {
		if ( $product->is_type( 'variable' ) || $product->is_type( 'grouped' ) ) {
			return;
		}

		self::save_posted( $product->get_id(), null );
	}

	/**
	 * Persist variation fields from $_POST arrays.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $i            Loop index.
	 */
	public static function save_variation( $variation_id, $i ) {
		self::save_posted( $variation_id, $i );
	}

	/**
	 * Read one field from $_POST. $index null = simple product.
	 *
	 * @param int      $product_id Product or variation ID.
	 * @param int|null $index      Variation loop index, or null.
	 */
	private static function save_posted( $product_id, $index ) {
		$meta   = Brendigo_Sidrena_Cijena_Product_Meta::instance();
		$cjm_on = ( 'yes' === Brendigo_Sidrena_Cijena_Settings::instance()->get( 'enable_cjm' ) );

		foreach ( self::product_fields() as $field ) {
			$needs_cjm = in_array( $field['setter'], array( 'set_cjm_enabled', 'set_cjm_suffix' ), true );
			if ( $needs_cjm && ! $cjm_on ) {
				continue;
			}

			if ( 'checkbox' === $field['kind'] ) {
				$meta->{$field['setter']}( $product_id, self::posted_checkbox( $field['post'], $index ) );
				continue;
			}

			$raw = self::posted_value( $field['post'], $index );

			if ( false === $raw ) {
				continue;
			}

			if ( 'text' === $field['kind'] ) {
				$meta->{$field['setter']}( $product_id, sanitize_text_field( $raw ) );
				continue;
			}

			$meta->{$field['setter']}( $product_id, $raw );
		}
	}

	/**
	 * Posted value, or false when the key is absent.
	 *
	 * @param string   $key   $_POST key.
	 * @param int|null $index Variation index.
	 * @return mixed|false
	 */
	private static function posted_value( $key, $index ) {
		if ( null === $index ) {
			if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				return false;
			}

			return wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) || ! array_key_exists( $index, $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return false;
		}

		return wp_unslash( $_POST[ $key ][ $index ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * Checkbox posted: simple uses isset, variation uses isset on the loop index.
	 *
	 * @param string   $key   $_POST key.
	 * @param int|null $index Variation index.
	 * @return bool
	 */
	private static function posted_checkbox( $key, $index ) {
		if ( null === $index ) {
			return isset( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		return isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) && isset( $_POST[ $key ][ $index ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}
}
