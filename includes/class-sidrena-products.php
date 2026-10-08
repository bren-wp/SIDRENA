<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sidrena_Products {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			return;
		}

		add_action( 'woocommerce_product_options_pricing', array( $this, 'simple_fields' ) );
		add_action( 'woocommerce_product_options_inventory_product_data', array( $this, 'catalog_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product' ) );
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation' ), 10, 2 );
		add_action( 'woocommerce_new_product_variation', array( $this, 'snapshot_new_variation' ), 20, 1 );
		add_filter( 'woocommerce_get_price_html', array( $this, 'append_reference_prices' ), 999, 2 );
		add_filter( 'woocommerce_available_variation', array( $this, 'variation_reference_payload' ), 20, 3 );
		add_shortcode( 'sidrena_cijena', array( $this, 'shortcode' ) );
		add_shortcode( 'sidrena-cijena', array( $this, 'shortcode' ) );
		add_action( 'sidrena_cijena', array( $this, 'action_output' ), 10, 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );
		add_action( 'transition_post_status', array( $this, 'snapshot_newly_published' ), 10, 3 );
		add_action( 'rest_api_init', array( $this, 'register_meta' ) );
	}

	public function register_meta() {
		$keys = array(
			'_sidrena_anchor_price'       => 'number',
			'_sidrena_lowest_30_verified' => 'number',
			'_sidrena_reference_group'    => 'string',
			'_sidrena_brand'              => 'string',
			'_sidrena_code'               => 'string',
			'_sidrena_barcode'            => 'string',
			'_sidrena_unit'               => 'string',
			'_sidrena_unit_price'         => 'number',
			'_sidrena_unit_price_status'  => 'string',
			'_sidrena_sale_name'          => 'string',
			'_sidrena_cjenik_visibility'  => 'string',
		);
		foreach ( array( 'product', 'product_variation' ) as $post_type ) {
			foreach ( $keys as $key => $type ) {
				$sanitize_callback = 'number' === $type ? array( $this, 'sanitize_number_meta' ) : 'sanitize_text_field';
				if ( '_sidrena_reference_group' === $key ) {
					$sanitize_callback = array( $this, 'sanitize_reference_group_meta' );
				}
				register_post_meta(
					$post_type,
					$key,
					array(
						'type'              => $type,
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => $sanitize_callback,
						'auth_callback'     => static function () {
							return current_user_can( 'edit_products' );
						},
					)
				);
			}
		}
	}

	public function sanitize_number_meta( $value ) {
		return '' === $value ? '' : (float) $value;
	}

	public function sanitize_custom_reference_date_meta( $value ) {
		return Sidrena_Utils::custom_reference_date( $value );
	}

	public function sanitize_reference_group_meta( $value ) {
		return Sidrena_Utils::sanitize_reference_group( $value );
	}

	public function simple_fields() {
		echo '<div class="options_group sidrena-fields">';
		woocommerce_wp_text_input(
			array(
				'id'                => '_sidrena_anchor_price',
				'label'             => __( 'Sidrena cijena', 'sidrena' ),
				'desc_tip'          => true,
				'description'       => __( 'Sidrena cijena prema odabranom pravnom rulesetu. Poseban oblik prodaje vodi se odvojeno i ne određuje ovu vrijednost.', 'sidrena' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);
		$product_id      = get_the_ID();
		$reference_group = $product_id ? Sidrena_Utils::sanitize_reference_group( get_post_meta( $product_id, '_sidrena_reference_group', true ) ) : 'standard';
		if ( 'custom' === $reference_group ) {
			$custom_date = Sidrena_Utils::verified_custom_reference_date_for_post( $product_id, get_post_meta( $product_id, '_sidrena_anchor_date', true ) );
			echo '<p class="form-field"><label>' . esc_html__( 'Pravni datum sidrene cijene', 'sidrena' ) . '</label><strong>' . esc_html( Sidrena_Utils::date_display( $custom_date ) ) . '</strong><span class="description">' . esc_html__( 'Automatski zaključano iz dokazivog prvog objavljivanja proizvoda. Datum nije ručno promjenjiv.', 'sidrena' ) . '</span></p>';
		} else {
			woocommerce_wp_select(
				array(
					'id'          => '_sidrena_reference_group',
					'label'       => __( 'Pravni datum sidrene cijene', 'sidrena' ),
					'description' => __( 'Odaberite samo zakonski ruleset za postojeći proizvod. Novouvedeni proizvod SIDRENA prepoznaje automatski pri prvom objavljivanju.', 'sidrena' ),
					'desc_tip'    => true,
					'options'     => array(
						/* translators: %s: formatted reference date. */
						'standard' => sprintf( __( 'Zaključano: standardno (%s)', 'sidrena' ), Sidrena_Utils::date_display( Sidrena_Utils::standard_reference_date() ) ),
						/* translators: %s: formatted FMCG reference date. */
						'fmcg'     => sprintf( __( 'Zaključano: postojeći FMCG (%s)', 'sidrena' ), Sidrena_Utils::date_display( Sidrena_Utils::fmcg_reference_date() ) ),
					),
				)
			);
		}
		echo '<p class="form-field"><span class="description">' . esc_html__( '10.09.2026. i 02.05.2025. nisu postavke. Za stvarno novouvedenu stavku SIDRENA koristi i zaključava datum prvog objavljivanja.', 'sidrena' ) . '</span></p>';
		woocommerce_wp_text_input(
			array(
				'id'                => '_sidrena_lowest_30_verified',
				'label'             => __( 'Najniža cijena u prethodnih 30 dana', 'sidrena' ),
				'description'       => __( 'Odvojeno od sidrene cijene. Koristi se samo tijekom posebnog oblika prodaje ako SIDRENA nema potpunu povijest za automatski izračun. Unesite samo poslovno provjerenu vrijednost.', 'sidrena' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);
		echo '</div>';
	}

	public function catalog_fields() {
		echo '<div class="options_group sidrena-fields">';
		woocommerce_wp_text_input(
			array(
				'id'          => '_sidrena_code',
				'label'       => __( 'Šifra za cjenik', 'sidrena' ),
				'description' => __( 'Neobavezno. Koristi se samo ako proizvod nema WooCommerce SKU. Ako su oba prazna, Sidrena koristi stabilnu oznaku WP-ID.', 'sidrena' ),
				'desc_tip'    => true,
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_sidrena_barcode',
				'label'       => __( 'Barkod za cjenik', 'sidrena' ),
				'description' => __( 'Koristi se kada WooCommerce Global Unique ID / barkod nije dostupan. Unesite stvarni barkod iz poslovne evidencije.', 'sidrena' ),
				'desc_tip'    => true,
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_sidrena_brand',
				'label'       => __( 'Marka za cjenik', 'sidrena' ),
				'description' => __( 'Koristi se ako marka nije dostupna kroz WooCommerce Brands ili atribut pa_brand.', 'sidrena' ),
				'desc_tip'    => true,
			)
		);
		woocommerce_wp_select(
			array(
				'id'          => '_sidrena_unit_price_status',
				'label'       => __( 'Jedinična cijena — primjenjivost', 'sidrena' ),
				'desc_tip'    => true,
				'description' => __( 'Provjerite primjenjivost čl. 8. NN 105/2026. Jedinična cijena obvezna je za propisane skupine robe, uz propisane iznimke. Sidrena ne zaključuje automatski pravni status proizvoda.', 'sidrena' ),
				'options'     => array(
					'review'       => __( 'Potrebna provjera', 'sidrena' ),
					'required'     => __( 'Jedinična cijena je obvezna', 'sidrena' ),
					'not_required' => __( 'Nije primjenjiva', 'sidrena' ),
					'exception'    => __( 'Primjenjuje se propisana iznimka', 'sidrena' ),
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => '_sidrena_quantity',
				'label'             => __( 'Količina pakiranja', 'sidrena' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '0.0001',
					'min'  => '0',
				),
				'description'       => __( 'Npr. 750 za 750 g ili 1,5 za 1,5 l. Ako je jedinična cijena obvezna, a iznos je prazan, Sidrena je može izračunati automatski.', 'sidrena' ),
				'desc_tip'          => true,
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_sidrena_quantity_unit',
				'label'       => __( 'Jedinica pakiranja', 'sidrena' ),
				'placeholder' => 'g / kg / ml / l / m / kom',
				'description' => __( 'Podržane su uobičajene jedinice mase, volumena, duljine, površine, obujma i komada.', 'sidrena' ),
				'desc_tip'    => true,
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_sidrena_unit',
				'label'       => __( 'Jedinica mjere', 'sidrena' ),
				'desc_tip'    => true,
				'description' => __( 'Npr. kg, l, m, m² ili druga odgovarajuća jedinica kada je jedinična cijena primjenjiva.', 'sidrena' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => '_sidrena_unit_price',
				'label'             => __( 'Cijena za jedinicu mjere', 'sidrena' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '0.0001',
					'min'  => '0',
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_sidrena_sale_name',
				'label'       => __( 'Poseban oblik prodaje — naziv', 'sidrena' ),
				'placeholder' => __( 'npr. Akcija', 'sidrena' ),
			)
		);
		woocommerce_wp_select(
			array(
				'id'          => '_sidrena_cjenik_visibility',
				'label'       => __( 'Javni cjenik — uključivanje', 'sidrena' ),
				'desc_tip'    => true,
				'description' => __( 'Automatski poštuje WooCommerce vidljivost. Uvijek uključi može uključiti objavljen proizvod skriven iz Woo kataloga, ali ne može objaviti privatni, draft ili lozinkom zaštićeni proizvod.', 'sidrena' ),
				'options'     => array(
					'auto'    => __( 'Automatski', 'sidrena' ),
					'include' => __( 'Uvijek uključi', 'sidrena' ),
					// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- This is a UI option key, not a query exclusion parameter.
					'exclude' => __( 'Isključi iz Sidrena cjenika', 'sidrena' ),
				),
			)
		);
		echo '</div>';
	}

	public function variation_fields( $loop, $variation_data, $variation ) {
		$variation_id = $variation->ID;
		woocommerce_wp_text_input(
			array(
				'id'            => "_sidrena_code_{$loop}",
				'name'          => "_sidrena_code[{$loop}]",
				'value'         => get_post_meta( $variation_id, '_sidrena_code', true ),
				'label'         => __( 'Šifra za cjenik (ako nema SKU-a)', 'sidrena' ),
				'wrapper_class' => 'form-row form-row-wide',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'            => "_sidrena_barcode_{$loop}",
				'name'          => "_sidrena_barcode[{$loop}]",
				'value'         => get_post_meta( $variation_id, '_sidrena_barcode', true ),
				'label'         => __( 'Barkod za cjenik', 'sidrena' ),
				'wrapper_class' => 'form-row form-row-wide',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => "_sidrena_anchor_price_{$loop}",
				'name'              => "_sidrena_anchor_price[{$loop}]",
				'value'             => get_post_meta( $variation_id, '_sidrena_anchor_price', true ),
				'label'             => __( 'Sidrena cijena', 'sidrena' ),
				'type'              => 'number',
				'wrapper_class'     => 'form-row form-row-first',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);
		echo '<p class="form-row form-row-last"><span class="description">' . esc_html__( 'Datum novouvedene varijacije određuje SIDRENA iz stvarnog datuma objavljivanja; nije slobodno uređivo polje.', 'sidrena' ) . '</span></p>';
		woocommerce_wp_text_input(
			array(
				'id'                => "_sidrena_lowest_30_verified_{$loop}",
				'name'              => "_sidrena_lowest_30_verified[{$loop}]",
				'value'             => get_post_meta( $variation_id, '_sidrena_lowest_30_verified', true ),
				'label'             => __( 'Najniža cijena u prethodnih 30 dana', 'sidrena' ),
				'description'       => __( 'Odvojeno od sidrene cijene; ručni fallback samo ako automatska 30-dnevna povijest nije potpuna.', 'sidrena' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'wrapper_class'     => 'form-row form-row-last',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);
		$variation_group = $this->variation_reference_group( $variation_id );
		if ( 'custom' === $variation_group ) {
			$variation_date = Sidrena_Utils::current_reference_date( $variation_id );
			echo '<p class="form-row form-row-first"><label>' . esc_html__( 'Pravni datum sidrene cijene', 'sidrena' ) . '</label><strong>' . esc_html( Sidrena_Utils::date_display( $variation_date ) ) . '</strong><span class="description">' . esc_html__( 'Automatski zaključano iz prvog objavljivanja varijacije/proizvoda.', 'sidrena' ) . '</span></p>';
		} else {
			woocommerce_wp_select(
				array(
					'id'            => "_sidrena_reference_group_{$loop}",
					'name'          => "_sidrena_reference_group[{$loop}]",
					'value'         => $variation_group,
					'label'         => __( 'Pravni datum sidrene cijene', 'sidrena' ),
					'wrapper_class' => 'form-row form-row-first',
					'options'       => array(
						'standard' => __( 'Zaključano: 10.09.2026.', 'sidrena' ),
						'fmcg'     => __( 'Zaključano FMCG: 02.05.2025.', 'sidrena' ),
					),
				)
			);
		}
		woocommerce_wp_select(
			array(
				'id'            => "_sidrena_unit_price_status_{$loop}",
				'name'          => "_sidrena_unit_price_status[{$loop}]",
				'value'         => get_post_meta( $variation_id, '_sidrena_unit_price_status', true ),
				'label'         => __( 'Jedinična cijena', 'sidrena' ),
				'wrapper_class' => 'form-row form-row-first',
				'options'       => array(
					''             => __( 'Naslijedi s proizvoda', 'sidrena' ),
					'review'       => __( 'Potrebna provjera', 'sidrena' ),
					'required'     => __( 'Obvezna', 'sidrena' ),
					'not_required' => __( 'Nije primjenjiva', 'sidrena' ),
					'exception'    => __( 'Propisana iznimka', 'sidrena' ),
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => "_sidrena_quantity_{$loop}",
				'name'              => "_sidrena_quantity[{$loop}]",
				'value'             => get_post_meta( $variation_id, '_sidrena_quantity', true ),
				'label'             => __( 'Količina pakiranja', 'sidrena' ),
				'type'              => 'number',
				'wrapper_class'     => 'form-row form-row-first',
				'custom_attributes' => array(
					'step' => '0.0001',
					'min'  => '0',
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'            => "_sidrena_quantity_unit_{$loop}",
				'name'          => "_sidrena_quantity_unit[{$loop}]",
				'value'         => get_post_meta( $variation_id, '_sidrena_quantity_unit', true ),
				'label'         => __( 'Jedinica pakiranja', 'sidrena' ),
				'placeholder'   => 'g / kg / ml / l / m / kom',
				'wrapper_class' => 'form-row form-row-last',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'            => "_sidrena_unit_{$loop}",
				'name'          => "_sidrena_unit[{$loop}]",
				'value'         => get_post_meta( $variation_id, '_sidrena_unit', true ),
				'label'         => __( 'Jedinica mjere', 'sidrena' ),
				'wrapper_class' => 'form-row form-row-last',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => "_sidrena_unit_price_{$loop}",
				'name'              => "_sidrena_unit_price[{$loop}]",
				'value'             => get_post_meta( $variation_id, '_sidrena_unit_price', true ),
				'label'             => __( 'Cijena za jedinicu mjere', 'sidrena' ),
				'type'              => 'number',
				'wrapper_class'     => 'form-row form-row-wide',
				'custom_attributes' => array(
					'step' => '0.0001',
					'min'  => '0',
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'            => "_sidrena_sale_name_{$loop}",
				'name'          => "_sidrena_sale_name[{$loop}]",
				'value'         => get_post_meta( $variation_id, '_sidrena_sale_name', true ),
				'label'         => __( 'Naziv posebne prodaje', 'sidrena' ),
				'wrapper_class' => 'form-row form-row-last',
			)
		);
	}

	private function verified_product_form_data( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id || ! current_user_can( 'edit_post', $product_id ) ) {
			return null;
		}
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) ) {
			return null;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) ) {
			return null;
		}

		// Nonce and object permission are verified above; individual values are sanitized by type before storage.
		return wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	public function save_product( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$posted = $this->verified_product_form_data( $product->get_id() );
		if ( ! is_array( $posted ) ) {
			return;
		}

		$map = array(
			'_sidrena_anchor_price'       => 'decimal',
			'_sidrena_lowest_30_verified' => 'decimal',
			'_sidrena_reference_group'    => 'key',
			'_sidrena_brand'              => 'text',
			'_sidrena_code'               => 'text',
			'_sidrena_barcode'            => 'text',
			'_sidrena_unit'               => 'text',
			'_sidrena_unit_price'         => 'decimal',
			'_sidrena_unit_price_status'  => 'unit_status',
			'_sidrena_quantity'           => 'decimal',
			'_sidrena_quantity_unit'      => 'unit_key',
			'_sidrena_sale_name'          => 'text',
			'_sidrena_cjenik_visibility'  => 'cjenik_visibility',
		);
		foreach ( $map as $key => $type ) {
			if ( ! isset( $posted[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( $posted[ $key ] );
			$value = $this->sanitize_by_type( $value, $type );
			if ( null === $value ) {
				continue;
			}
			if ( '' === $value || ( 'exemption' === $type && 'none' === $value ) ) {
				$product->delete_meta_data( $key );
			} else {
				$product->update_meta_data( $key, $value );
			}
		}
		$group = Sidrena_Utils::sanitize_reference_group( $product->get_meta( '_sidrena_reference_group', true ) );
		if ( 'custom' === $group ) {
			$custom_date = Sidrena_Utils::verified_custom_reference_date_for_post( $product->get_id(), $product->get_meta( '_sidrena_anchor_date', true ) );
			if ( $custom_date ) {
				$product->update_meta_data( '_sidrena_anchor_date', $custom_date );
			} else {
				$group = 'standard';
				$product->delete_meta_data( '_sidrena_anchor_date' );
			}
		} else {
			$product->delete_meta_data( '_sidrena_anchor_date' );
		}
		$product->update_meta_data( '_sidrena_reference_group', $group );
		$this->maybe_calculate_unit_price( $product );
		Sidrena_Pricelist::queue_regeneration();
	}
	public function save_variation( $variation_id, $loop ) {
		$variation_id = absint( $variation_id );
		$loop         = absint( $loop );
		$parent_id    = wp_get_post_parent_id( $variation_id );
		if ( ! $variation_id || ! $parent_id || ! current_user_can( 'edit_post', $variation_id ) ) {
			return;
		}

		$posted = $this->verified_product_form_data( $parent_id );
		if ( ! is_array( $posted ) ) {
			return;
		}

		$fields = array(
			'_sidrena_code'               => 'text',
			'_sidrena_barcode'            => 'text',
			'_sidrena_anchor_price'       => 'decimal',
			'_sidrena_lowest_30_verified' => 'decimal',
			'_sidrena_reference_group'    => 'key',
			'_sidrena_unit_price_status'  => 'unit_status_inherit',
			'_sidrena_quantity'           => 'decimal',
			'_sidrena_quantity_unit'      => 'unit_key',
			'_sidrena_unit'               => 'text',
			'_sidrena_unit_price'         => 'decimal',
			'_sidrena_sale_name'          => 'text',
		);
		foreach ( $fields as $key => $type ) {
			if ( ! isset( $posted[ $key ][ $loop ] ) ) {
				continue;
			}
			$value = sanitize_text_field( $posted[ $key ][ $loop ] );
			$value = $this->sanitize_by_type( $value, $type );
			if ( null === $value ) {
				continue;
			}
			if ( '' === $value || ( 'exemption' === $type && 'none' === $value ) ) {
				delete_post_meta( $variation_id, $key );
			} else {
				update_post_meta( $variation_id, $key, $value );
			}
		}

		$group = Sidrena_Utils::sanitize_reference_group( get_post_meta( $variation_id, '_sidrena_reference_group', true ) );
		if ( 'custom' === $group ) {
			$custom_date = Sidrena_Utils::verified_custom_reference_date_for_post( $variation_id, get_post_meta( $variation_id, '_sidrena_anchor_date', true ) );
			if ( $custom_date ) {
				update_post_meta( $variation_id, '_sidrena_anchor_date', $custom_date );
			} else {
				$group = 'standard';
				delete_post_meta( $variation_id, '_sidrena_anchor_date' );
			}
		} else {
			delete_post_meta( $variation_id, '_sidrena_anchor_date' );
		}
		update_post_meta( $variation_id, '_sidrena_reference_group', $group );

		$variation = wc_get_product( $variation_id );
		if ( $variation ) {
			$this->maybe_calculate_unit_price( $variation );
			$variation->save_meta_data();
		}
		$this->maybe_snapshot_new_variation( $variation_id );
		Sidrena_Pricelist::queue_regeneration();
	}

	private function maybe_calculate_unit_price( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$status = sanitize_key( (string) $product->get_meta( '_sidrena_unit_price_status', true ) );
		if ( '' === $status && $product->is_type( 'variation' ) && $product->get_parent_id() ) {
			$status = sanitize_key( (string) get_post_meta( $product->get_parent_id(), '_sidrena_unit_price_status', true ) );
		}
		if ( 'required' !== $status || '' !== Sidrena_Utils::decimal( $product->get_meta( '_sidrena_unit_price', true ) ) ) {
			return;
		}

		$quantity = Sidrena_Utils::decimal( $product->get_meta( '_sidrena_quantity', true ) );
		$unit     = Sidrena_Utils::normalize_unit( $product->get_meta( '_sidrena_quantity_unit', true ) );
		if ( ( '' === $quantity || '' === $unit ) && $product->is_type( 'variation' ) && $product->get_parent_id() ) {
			if ( '' === $quantity ) {
				$quantity = Sidrena_Utils::decimal( get_post_meta( $product->get_parent_id(), '_sidrena_quantity', true ) );
			}
			if ( '' === $unit ) {
				$unit = Sidrena_Utils::normalize_unit( get_post_meta( $product->get_parent_id(), '_sidrena_quantity_unit', true ) );
			}
		}

		$raw_price = $product->get_price( 'edit' );
		if ( '' === $raw_price || '' === $quantity || '' === $unit ) {
			return;
		}

		$retail = (float) $raw_price;
		if ( function_exists( 'wc_get_price_including_tax' ) ) {
			$retail = wc_get_price_including_tax( $product, array( 'price' => (float) $raw_price ) );
		}
		$calculated = Sidrena_Utils::calculate_unit_price( $retail, $quantity, $unit );
		if ( ! $calculated ) {
			return;
		}
		$product->update_meta_data( '_sidrena_unit', $calculated['unit'] );
		$product->update_meta_data( '_sidrena_unit_price', $calculated['unit_price'] );
	}

	private function sanitize_by_type( $value, $type ) {
		switch ( $type ) {
			case 'decimal':
				return Sidrena_Utils::validated_nonnegative_decimal( $value );
			case 'date':
				return Sidrena_Utils::sanitize_date( $value );
			case 'custom_date':
				return Sidrena_Utils::custom_reference_date( $value );
			case 'key':
				return Sidrena_Utils::sanitize_reference_group( $value );
			case 'exemption':
				$value = sanitize_key( $value );
				return in_array( $value, array( 'none', 'perishable', 'fast_expiry' ), true ) ? $value : 'none';
			case 'unit_status':
				$value = sanitize_key( $value );
				return in_array( $value, array( 'review', 'required', 'not_required', 'exception' ), true ) ? $value : 'review';
			case 'unit_status_inherit':
				$value = sanitize_key( $value );
				return '' === $value || in_array( $value, array( 'review', 'required', 'not_required', 'exception' ), true ) ? $value : '';
			case 'cjenik_visibility':
				$value = sanitize_key( $value );
				return in_array( $value, array( 'auto', 'include', 'exclude' ), true ) ? $value : 'auto';
			case 'unit_key':
				return Sidrena_Utils::normalize_unit( $value );
			default:
				return sanitize_text_field( $value );
		}
	}

	public function append_reference_prices( $html, $product ) {
		if ( false !== strpos( (string) $html, 'sidrena-reference-prices' ) ) {
			return $html;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $html;
		}
		if ( ! $product instanceof WC_Product ) {
			return $html;
		}

		$settings = Sidrena_Utils::settings();
		$extra    = '';
		if ( 'yes' === $settings['display_anchor'] ) {
			$extra .= $this->anchor_html( $product );
		}
		$extra .= $this->lowest_30_html( $product );
		return $extra ? $html . '<span class="sidrena-reference-prices">' . $extra . '</span>' : $html;
	}

	public function variation_reference_payload( $data, $product, $variation ) {
		unset( $product );
		if ( ! is_array( $data ) || ! $variation instanceof WC_Product ) {
			return $data;
		}

		$settings = Sidrena_Utils::settings();
		$out      = '';
		if ( 'yes' === $settings['display_anchor'] ) {
			$out .= $this->anchor_html( $variation );
		}
		$out .= $this->lowest_30_html( $variation );

		$html                           = $out ? '<span class="sidrena-reference-prices">' . $out . '</span>' : '';
		$data['sidrena_reference_html'] = (string) apply_filters(
			'sidrena_variation_reference_html',
			$html,
			$variation,
			$data
		);

		return $data;
	}

	public function shortcode( $atts ) {
		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			return '';
		}

		$atts   = shortcode_atts( array( 'id' => 0 ), $atts, 'sidrena_cijena' );
		$raw_id = trim( (string) $atts['id'] );
		if ( preg_match( '/^s\d+$/i', $raw_id ) ) {
			return '';
		}

		global $product;
		$target = absint( $raw_id ) ? wc_get_product( absint( $raw_id ) ) : ( $product instanceof WC_Product ? $product : null );
		if ( ! $target ) {
			return '';
		}

		wp_enqueue_style( 'sidrena-frontend', SIDRENA_URL . 'public/css/frontend.css', array(), SIDRENA_VERSION );
		$settings = Sidrena_Utils::settings();
		$out      = '';
		if ( 'yes' === $settings['display_anchor'] ) {
			$out .= $this->anchor_html( $target );
		}
		$out .= $this->lowest_30_html( $target );
		return $out ? '<span class="sidrena-reference-prices">' . $out . '</span>' : '';
	}

	public function action_output( $target_product = null ) {
		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			return;
		}
		if ( ! $target_product instanceof WC_Product ) {
			global $product;
			$target_product = $product instanceof WC_Product ? $product : null;
		}
		if ( ! $target_product ) {
			return;
		}
		echo wp_kses_post( $this->shortcode( array( 'id' => $target_product->get_id() ) ) );
	}

	private function lowest_30_html( $product ) {
		if ( ! class_exists( 'Sidrena_History' ) || ! $product instanceof WC_Product || ! is_callable( array( $product, 'is_on_sale' ) ) || ! $product->is_on_sale( 'edit' ) ) {
			return '';
		}
		if ( $product->is_type( 'variable' ) ) {
			return $this->variable_lowest_30_html( $product );
		}

		$result = Sidrena_History::instance()->lowest_30_day_reference( $product );
		if ( 'ready' !== ( $result['status'] ?? '' ) || '' === ( $result['price'] ?? '' ) ) {
			return '';
		}

		$display_price = (float) $result['price'];
		if ( function_exists( 'wc_get_price_to_display' ) ) {
			$display_price = wc_get_price_to_display( $product, array( 'price' => $display_price ) );
		}
		$source = 'manual' === ( $result['source'] ?? '' )
			? __( 'provjerena vrijednost', 'sidrena' )
			: __( 'iz povijesti cijena', 'sidrena' );
		return sprintf(
			'<span class="sidrena-lowest-30"><span class="sidrena-lowest-30__label">%1$s:</span> <span class="sidrena-lowest-30__value">%2$s</span><small>%3$s</small></span>',
			esc_html__( 'Najniža cijena u prethodnih 30 dana', 'sidrena' ),
			wp_kses_post( wc_price( $display_price ) ),
			esc_html( $source )
		);
	}

	private function variable_lowest_30_html( $product ) {
		$values       = array();
		$on_sale      = 0;
		$ready_values = 0;
		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->exists() || ! $variation->is_on_sale( 'edit' ) ) {
				continue;
			}
			++$on_sale;
			$result = Sidrena_History::instance()->lowest_30_day_reference( $variation );
			if ( 'ready' !== ( $result['status'] ?? '' ) || '' === ( $result['price'] ?? '' ) ) {
				continue;
			}
			$display  = function_exists( 'wc_get_price_to_display' )
				? wc_get_price_to_display( $variation, array( 'price' => (float) $result['price'] ) )
				: (float) $result['price'];
			$values[] = (float) $display;
			++$ready_values;
		}
		if ( 0 === $on_sale || $ready_values !== $on_sale || ! $values ) {
			return '';
		}
		$min    = min( $values );
		$max    = max( $values );
		$amount = abs( $min - $max ) < 0.00001 ? wc_price( $min ) : wc_format_price_range( $min, $max );
		return sprintf(
			'<span class="sidrena-lowest-30 sidrena-lowest-30--variable"><span class="sidrena-lowest-30__label">%1$s:</span> <span class="sidrena-lowest-30__value">%2$s</span></span>',
			esc_html__( 'Najniža cijena u prethodnih 30 dana', 'sidrena' ),
			wp_kses_post( $amount )
		);
	}

	private function anchor_html( $product ) {
		if ( $product->is_type( 'variable' ) ) {
			return $this->variable_anchor_html( $product );
		}

		$id     = $product->get_id();
		$anchor = Sidrena_Utils::product_anchor_price( $id );
		if ( '' === $anchor ) {
			return '';
		}

		$display_price = (float) $anchor;
		if ( function_exists( 'wc_get_price_to_display' ) ) {
			$display_price = wc_get_price_to_display( $product, array( 'price' => (float) $anchor ) );
		}
		$display_price = apply_filters( 'sidrena_anchor_price_to_display', $display_price, $product, $anchor );
		$display_price = apply_filters( 'sidrena_cijena_price_to_display', $display_price, $product, $anchor );
		$date          = Sidrena_Utils::current_reference_date( $id );
		$label         = Sidrena_Utils::anchor_label( $date );
		$tooltip       = Sidrena_Utils::anchor_tooltip();
		$tooltip_id    = 'sidrena-anchor-tip-' . absint( $id );
		$tooltip_html  = $tooltip ? '<span class="sidrena-anchor__info" aria-hidden="true">i</span><span id="' . esc_attr( $tooltip_id ) . '" class="sidrena-anchor__tooltip" role="tooltip">' . esc_html( $tooltip ) . '</span>' : '';
		$line          = sprintf(
			'<span class="sidrena-anchor%1$s"%2$s><span class="sidrena-anchor__label">%3$s:</span> <span class="sidrena-anchor__value">%4$s</span>%5$s</span>',
			$tooltip ? ' sidrena-anchor--has-tooltip' : '',
			$tooltip ? ' tabindex="0" aria-describedby="' . esc_attr( $tooltip_id ) . '"' : '',
			esc_html( $label ),
			wp_kses_post( wc_price( $display_price ) ),
			$tooltip_html
		);

		return apply_filters( 'sidrena_anchor_html', $line, $product, $anchor, $date );
	}

	private function variable_anchor_html( $product ) {
		$values_by_date = array();
		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->exists() ) {
				continue;
			}
			$anchor = Sidrena_Utils::product_anchor_price( $variation_id );
			$date   = Sidrena_Utils::current_reference_date( $variation_id );
			// An unverified new-item date must never be presented as the
			// standard or FMCG reference date on the parent product.
			if ( '' === $anchor || '' === $date ) {
				continue;
			}
			$display = function_exists( 'wc_get_price_to_display' )
				? wc_get_price_to_display( $variation, array( 'price' => (float) $anchor ) )
				: (float) $anchor;
			$display = apply_filters( 'sidrena_anchor_price_to_display', $display, $variation, $anchor );
			$display = apply_filters( 'sidrena_cijena_price_to_display', $display, $variation, $anchor );
			$values_by_date[ $date ][] = (float) $display;
		}

		if ( empty( $values_by_date ) ) {
			return '';
		}

		ksort( $values_by_date );
		$lines   = array();
		$tooltip = Sidrena_Utils::anchor_tooltip();
		foreach ( $values_by_date as $date => $values ) {
			$min          = min( $values );
			$max          = max( $values );
			$label        = Sidrena_Utils::anchor_label( $date );
			$amount       = abs( $min - $max ) < 0.00001 ? wc_price( $min ) : wc_format_price_range( $min, $max );
			$tooltip_id   = 'sidrena-anchor-tip-' . absint( $product->get_id() ) . '-' . str_replace( '-', '', $date );
			$tooltip_html = $tooltip ? '<span class="sidrena-anchor__info" aria-hidden="true">i</span><span id="' . esc_attr( $tooltip_id ) . '" class="sidrena-anchor__tooltip" role="tooltip">' . esc_html( $tooltip ) . '</span>' : '';

			$line = sprintf(
				'<span class="sidrena-anchor sidrena-anchor--variable%1$s"%2$s><span class="sidrena-anchor__label">%3$s:</span> <span class="sidrena-anchor__value">%4$s</span>%5$s</span>',
				$tooltip ? ' sidrena-anchor--has-tooltip' : '',
				$tooltip ? ' tabindex="0" aria-describedby="' . esc_attr( $tooltip_id ) . '"' : '',
				esc_html( $label ),
				wp_kses_post( $amount ),
				$tooltip_html
			);
			// Preserve the existing extension point, once per real reference date.
			$lines[] = apply_filters( 'sidrena_anchor_html', $line, $product, array( $min, $max ), $date );
		}
		return implode( '', $lines );
	}

	public function frontend_assets() {
		$settings = Sidrena_Utils::settings();
		if ( 'yes' !== $settings['display_anchor'] ) {
			return;
		}
		wp_enqueue_style( 'sidrena-frontend', SIDRENA_URL . 'public/css/frontend.css', array(), SIDRENA_VERSION );
	}

	public function snapshot_newly_published( $new_status, $old_status, $post ) {
		if ( 'product' !== $post->post_type || 'publish' !== $new_status || 'publish' === $old_status || ! Sidrena_Utils::is_woocommerce_active() ) {
			return;
		}
		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return;
		}
		$published = get_post_datetime( $post );
		$cutoff    = new DateTimeImmutable( Sidrena_Utils::standard_reference_date() . ' 23:59:59', wp_timezone() );
		if ( ! $published || $published <= $cutoff ) {
			return;
		}
		$date = $published->format( 'Y-m-d' );
		$ids  = $product->is_type( 'variable' ) ? $product->get_children() : array( $product->get_id() );
		foreach ( $ids as $id ) {
			if ( '' !== get_post_meta( $id, '_sidrena_anchor_price', true ) ) {
				continue;
			}
			$item  = wc_get_product( $id );
			$price = $item ? $item->get_regular_price( 'edit' ) : '';
			if ( '' === $price && $item ) {
				$price = $item->get_price( 'edit' );
			}
			if ( '' !== $price ) {
				update_post_meta( $id, '_sidrena_anchor_price', wc_format_decimal( $price ) );
				update_post_meta( $id, '_sidrena_anchor_date', $date );
				update_post_meta( $id, '_sidrena_reference_group', 'custom' );
			}
		}
		Sidrena_Pricelist::queue_regeneration();
	}

	private function variation_reference_group( $variation_id ) {
		$group = get_post_meta( $variation_id, '_sidrena_reference_group', true );
		if ( $group ) {
			return $group;
		}
		$parent_id = wp_get_post_parent_id( $variation_id );
		$group     = $parent_id ? get_post_meta( $parent_id, '_sidrena_reference_group', true ) : '';
		return $group ? $group : 'standard';
	}

	public function snapshot_new_variation( $variation_id ) {
		$this->maybe_snapshot_new_variation( $variation_id );
		Sidrena_Pricelist::queue_regeneration();
	}

	private function maybe_snapshot_new_variation( $variation_id ) {
		if ( ! Sidrena_Utils::is_woocommerce_active() || '' !== get_post_meta( $variation_id, '_sidrena_anchor_price', true ) ) {
			return;
		}

		$variation = wc_get_product( $variation_id );
		$post      = get_post( $variation_id );
		if ( ! $variation || ! $variation->is_type( 'variation' ) || ! $post ) {
			return;
		}

		$created = get_post_datetime( $post );
		$cutoff  = new DateTimeImmutable( Sidrena_Utils::standard_reference_date() . ' 23:59:59', wp_timezone() );
		if ( ! $created || $created <= $cutoff ) {
			return;
		}

		$price = $variation->get_regular_price( 'edit' );
		if ( '' === $price ) {
			$price = $variation->get_price( 'edit' );
		}
		if ( '' === $price ) {
			return;
		}

		update_post_meta( $variation_id, '_sidrena_anchor_price', wc_format_decimal( $price ) );
		update_post_meta( $variation_id, '_sidrena_anchor_date', $created->format( 'Y-m-d' ) );
		update_post_meta( $variation_id, '_sidrena_reference_group', 'custom' );
	}
}
