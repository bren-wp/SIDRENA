<?php
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
		add_shortcode( 'sidrena_cijena', array( $this, 'shortcode' ) );
		add_action( 'sidrena_cijena', array( $this, 'action_output' ), 10, 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );
		add_action( 'transition_post_status', array( $this, 'snapshot_newly_published' ), 10, 3 );
		add_action( 'rest_api_init', array( $this, 'register_meta' ) );
	}

	public function register_meta() {
		$keys = array(
			'_sidrena_anchor_price'                  => 'number',
			'_sidrena_anchor_date'                   => 'string',
			'_sidrena_reference_group'               => 'string',
			'_sidrena_brand'                         => 'string',
			'_sidrena_code'                          => 'string',
			'_sidrena_barcode'                       => 'string',
			'_sidrena_unit'                          => 'string',
			'_sidrena_unit_price'                    => 'number',
			'_sidrena_unit_price_status'             => 'string',
			'_sidrena_sale_name'                     => 'string',
			'_sidrena_lowest_30_manual'              => 'number',
			'_sidrena_sale_reference_exemption'      => 'string',
			'_sidrena_expiry_date'                      => 'string',
		);
		foreach ( array( 'product', 'product_variation' ) as $post_type ) {
			foreach ( $keys as $key => $type ) {
				register_post_meta(
					$post_type,
					$key,
					array(
						'type'              => $type,
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => 'number' === $type ? array( $this, 'sanitize_number_meta' ) : 'sanitize_text_field',
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

	public function simple_fields() {
		$settings = Sidrena_Utils::settings();
		echo '<div class="options_group sidrena-fields">';
		woocommerce_wp_text_input(
			array(
				'id'                => '_sidrena_anchor_price',
				'label'             => __( 'Dodatna / sidrena cijena', 'sidrena' ),
				'desc_tip'          => true,
				'description'       => __( 'Cijena na mjerodavni referentni datum, bez posebnog oblika prodaje. Za postojeći artikl provjerite vlastitu evidenciju.', 'sidrena' ),
				'type'              => 'number',
				'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
			)
		);
		woocommerce_wp_select(
			array(
				'id'          => '_sidrena_reference_group',
				'label'       => __( 'Referentna skupina', 'sidrena' ),
				'description' => __( 'FMCG koji je već bio obuhvaćen mjerom zadržava 02.05.2025.; za novobuhvaćene proizvode mjerodavan je 10.09.2026.; novouvedeni proizvod koristi datum prvog uvrštenja.', 'sidrena' ),
				'desc_tip'    => true,
				'options'     => array(
					'standard' => sprintf( __( 'Standardno (%s)', 'sidrena' ), Sidrena_Utils::date_display( $settings['default_ref_date'] ) ),
					'fmcg'     => sprintf( __( 'FMCG (%s)', 'sidrena' ), Sidrena_Utils::date_display( $settings['fmcg_ref_date'] ) ),
					'custom'   => __( 'Vlastiti datum / novouvedeni proizvod', 'sidrena' ),
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_sidrena_anchor_date',
				'label'       => __( 'Vlastiti referentni datum', 'sidrena' ),
				'type'        => 'date',
				'description' => __( 'Ostavite prazno za datum iz odabrane referentne skupine.', 'sidrena' ),
				'desc_tip'    => true,
			)
		);
		echo '</div>';

		echo '<div class="options_group sidrena-fields">';
		woocommerce_wp_text_input(
			array(
				'id'                => '_sidrena_lowest_30_manual',
				'label'             => __( 'Najniža cijena prije sniženja — ručna provjera', 'sidrena' ),
				'desc_tip'          => true,
				'description'       => __( 'Neobavezno. Koristite ako automatska 30-dnevna povijest nije potpuna ili ste vrijednost provjerili iz druge vjerodostojne evidencije. Tijekom aktivne akcije ručni unos ima prednost.', 'sidrena' ),
				'type'              => 'number',
				'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
			)
		);
		woocommerce_wp_select(
			array(
				'id'          => '_sidrena_sale_reference_exemption',
				'label'       => __( 'Izuzeće 30-dnevne referentne cijene', 'sidrena' ),
				'desc_tip'    => true,
				'description' => __( 'Odaberite samo ako je proizvod stvarno obuhvaćen odgovarajućom zakonskom iznimkom.', 'sidrena' ),
				'options'     => array(
					'none'        => __( 'Nema izuzeća', 'sidrena' ),
					'perishable'  => __( 'Lako pokvarljiva roba', 'sidrena' ),
					'fast_expiry' => __( 'Roba kojoj brzo istječe rok uporabe', 'sidrena' ),
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_sidrena_expiry_date',
				'label'       => __( 'Krajnji rok uporabe', 'sidrena' ),
				'type'        => 'date',
				'desc_tip'    => true,
				'description' => __( 'Za robu na posebnom obliku prodaje jer je lako pokvarljiva ili joj brzo istječe rok uporabe. Sidrena prikazuje datum uz cijenu kada je označeno odgovarajuće izuzeće.', 'sidrena' ),
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
				'custom_attributes' => array( 'step' => '0.0001', 'min' => '0' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_sidrena_sale_name',
				'label'       => __( 'Naziv posebnog oblika prodaje', 'sidrena' ),
				'placeholder' => __( 'npr. Akcija', 'sidrena' ),
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
				'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'            => "_sidrena_anchor_date_{$loop}",
				'name'          => "_sidrena_anchor_date[{$loop}]",
				'value'         => get_post_meta( $variation_id, '_sidrena_anchor_date', true ),
				'label'         => __( 'Referentni datum', 'sidrena' ),
				'type'          => 'date',
				'wrapper_class' => 'form-row form-row-last',
			)
		);
		woocommerce_wp_select(
			array(
				'id'            => "_sidrena_reference_group_{$loop}",
				'name'          => "_sidrena_reference_group[{$loop}]",
				'value'         => $this->variation_reference_group( $variation_id ),
				'label'         => __( 'Referentna skupina', 'sidrena' ),
				'wrapper_class' => 'form-row form-row-first',
				'options'       => array(
					'standard' => __( 'Standardno', 'sidrena' ),
					'fmcg'     => __( 'FMCG', 'sidrena' ),
					'custom'   => __( 'Vlastiti datum', 'sidrena' ),
				),
			)
		);
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
				'custom_attributes' => array( 'step' => '0.0001', 'min' => '0' ),
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
		woocommerce_wp_text_input(
			array(
				'id'                => "_sidrena_lowest_30_manual_{$loop}",
				'name'              => "_sidrena_lowest_30_manual[{$loop}]",
				'value'             => get_post_meta( $variation_id, '_sidrena_lowest_30_manual', true ),
				'label'             => __( 'Najniža cijena 30 dana — ručno', 'sidrena' ),
				'type'              => 'number',
				'wrapper_class'     => 'form-row form-row-first',
				'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
			)
		);
		woocommerce_wp_select(
			array(
				'id'            => "_sidrena_sale_reference_exemption_{$loop}",
				'name'          => "_sidrena_sale_reference_exemption[{$loop}]",
				'value'         => get_post_meta( $variation_id, '_sidrena_sale_reference_exemption', true ) ?: 'none',
				'label'         => __( 'Izuzeće 30 dana', 'sidrena' ),
				'wrapper_class' => 'form-row form-row-last',
				'options'       => array(
					'none'        => __( 'Nema izuzeća', 'sidrena' ),
					'perishable'  => __( 'Lako pokvarljiva', 'sidrena' ),
					'fast_expiry' => __( 'Brzo istječe rok', 'sidrena' ),
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'            => "_sidrena_expiry_date_{$loop}",
				'name'          => "_sidrena_expiry_date[{$loop}]",
				'value'         => get_post_meta( $variation_id, '_sidrena_expiry_date', true ),
				'label'         => __( 'Krajnji rok uporabe', 'sidrena' ),
				'type'          => 'date',
				'wrapper_class' => 'form-row form-row-wide',
			)
		);
	}

	public function save_product( $product ) {
		$map = array(
			'_sidrena_anchor_price'             => 'decimal',
			'_sidrena_anchor_date'              => 'date',
			'_sidrena_reference_group'          => 'key',
			'_sidrena_brand'                    => 'text',
			'_sidrena_code'                     => 'text',
			'_sidrena_barcode'                  => 'text',
			'_sidrena_unit'                     => 'text',
			'_sidrena_unit_price'               => 'decimal',
			'_sidrena_unit_price_status'        => 'unit_status',
			'_sidrena_sale_name'                => 'text',
			'_sidrena_lowest_30_manual'         => 'decimal',
			'_sidrena_sale_reference_exemption' => 'exemption',
			'_sidrena_expiry_date'                 => 'date',
		);
		foreach ( $map as $key => $type ) {
			if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies product-save request.
				continue;
			}
			$value = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$value = $this->sanitize_by_type( $value, $type );
			if ( '' === $value || ( 'exemption' === $type && 'none' === $value ) ) {
				$product->delete_meta_data( $key );
			} else {
				$product->update_meta_data( $key, $value );
			}
		}
		Sidrena_Pricelist::queue_regeneration();
	}

	public function save_variation( $variation_id, $loop ) {
		$fields = array(
			'_sidrena_code'                     => 'text',
			'_sidrena_barcode'                  => 'text',
			'_sidrena_anchor_price'             => 'decimal',
			'_sidrena_anchor_date'              => 'date',
			'_sidrena_reference_group'          => 'key',
			'_sidrena_unit_price_status'        => 'unit_status_inherit',
			'_sidrena_unit'                     => 'text',
			'_sidrena_unit_price'               => 'decimal',
			'_sidrena_sale_name'                => 'text',
			'_sidrena_lowest_30_manual'         => 'decimal',
			'_sidrena_sale_reference_exemption' => 'exemption',
			'_sidrena_expiry_date'                 => 'date',
		);
		foreach ( $fields as $key => $type ) {
			if ( ! isset( $_POST[ $key ][ $loop ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies variation-save request.
				continue;
			}
			$value = wp_unslash( $_POST[ $key ][ $loop ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$value = $this->sanitize_by_type( $value, $type );
			if ( '' === $value || ( 'exemption' === $type && 'none' === $value ) ) {
				delete_post_meta( $variation_id, $key );
			} else {
				update_post_meta( $variation_id, $key, $value );
			}
		}
		$this->maybe_snapshot_new_variation( $variation_id );
		Sidrena_Pricelist::queue_regeneration();
	}

	private function sanitize_by_type( $value, $type ) {
		switch ( $type ) {
			case 'decimal':
				return Sidrena_Utils::decimal( $value );
			case 'date':
				return Sidrena_Utils::sanitize_date( $value );
			case 'key':
				$value = sanitize_key( $value );
				return in_array( $value, array( 'standard', 'fmcg', 'custom' ), true ) ? $value : 'standard';
			case 'exemption':
				$value = sanitize_key( $value );
				return in_array( $value, array( 'none', 'perishable', 'fast_expiry' ), true ) ? $value : 'none';
			case 'unit_status':
				$value = sanitize_key( $value );
				return in_array( $value, array( 'review', 'required', 'not_required', 'exception' ), true ) ? $value : 'review';
			case 'unit_status_inherit':
				$value = sanitize_key( $value );
				return '' === $value || in_array( $value, array( 'review', 'required', 'not_required', 'exception' ), true ) ? $value : '';
			default:
				return sanitize_text_field( $value );
		}
	}

	public function append_reference_prices( $html, $product ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $html;
		}
		if ( ! $product instanceof WC_Product ) {
			return $html;
		}

		$settings = Sidrena_Utils::settings();
		$extra    = '';
		if ( 'yes' === $settings['display_lowest_30'] ) {
			$extra .= $this->lowest_30_html( $product );
		}
		$extra .= $this->expiry_html( $product );
		if ( 'yes' === $settings['display_anchor'] ) {
			$extra .= $this->anchor_html( $product );
		}
		return $extra ? $html . '<span class="sidrena-reference-prices">' . $extra . '</span>' : $html;
	}

	public function shortcode( $atts ) {
		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			return '';
		}

		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'sidrena_cijena' );
		$raw_id = trim( (string) $atts['id'] );
		if ( preg_match( '/^s(\d+)$/i', $raw_id, $match ) ) {
			return Sidrena_Standalone::instance()->price_shortcode( array( 'id' => 's' . absint( $match[1] ) ) );
		}

		global $product;
		$target = absint( $raw_id ) ? wc_get_product( absint( $raw_id ) ) : ( $product instanceof WC_Product ? $product : null );
		if ( ! $target ) {
			return '';
		}

		wp_enqueue_style( 'sidrena-frontend', SIDRENA_URL . 'public/css/frontend.css', array(), SIDRENA_VERSION );
		$settings = Sidrena_Utils::settings();
		$out      = '';
		if ( 'yes' === $settings['display_lowest_30'] ) {
			$out .= $this->lowest_30_html( $target );
		}
		$out .= $this->expiry_html( $target );
		if ( 'yes' === $settings['display_anchor'] ) {
			$out .= $this->anchor_html( $target );
		}
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
		if ( $product->is_type( 'variable' ) ) {
			return $this->variable_lowest_30_html( $product );
		}

		$reference = Sidrena_History::sale_reference( $product );
		if ( 'ready' !== $reference['status'] || '' === $reference['price'] ) {
			return '';
		}
		$display = function_exists( 'wc_get_price_to_display' )
			? wc_get_price_to_display( $product, array( 'price' => (float) $reference['price'] ) )
			: (float) $reference['price'];
		$line = sprintf(
			'<span class="sidrena-lowest"><span class="sidrena-lowest__label">%1$s:</span> <span class="sidrena-lowest__value">%2$s</span></span>',
			esc_html__( 'Najniža cijena u prethodnih 30 dana', 'sidrena' ),
			wp_kses_post( wc_price( $display ) )
		);
		return apply_filters( 'sidrena_lowest_30_html', $line, $product, $reference );
	}

	private function variable_lowest_30_html( $product ) {
		$values          = array();
		$required_active = 0;
		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->exists() || ! $variation->is_on_sale() ) {
				continue;
			}
			$reference = Sidrena_History::sale_reference( $variation );
			if ( 'exempt' === $reference['status'] ) {
				continue;
			}
			++$required_active;
			if ( 'ready' !== $reference['status'] || '' === $reference['price'] ) {
				return '';
			}
			$display = function_exists( 'wc_get_price_to_display' )
				? wc_get_price_to_display( $variation, array( 'price' => (float) $reference['price'] ) )
				: (float) $reference['price'];
			$values[] = (float) $display;
		}
		if ( 0 === $required_active || empty( $values ) ) {
			return '';
		}
		$min    = min( $values );
		$max    = max( $values );
		$amount = abs( $min - $max ) < 0.00001 ? wc_price( $min ) : wc_format_price_range( $min, $max );
		return sprintf(
			'<span class="sidrena-lowest sidrena-lowest--variable"><span class="sidrena-lowest__label">%1$s:</span> <span class="sidrena-lowest__value">%2$s</span></span>',
			esc_html__( 'Najniža cijena u prethodnih 30 dana', 'sidrena' ),
			wp_kses_post( $amount )
		);
	}

	private function expiry_html( $product ) {
		if ( $product->is_type( 'variable' ) ) {
			return $this->variable_expiry_html( $product );
		}

		if ( ! $product->is_on_sale() ) {
			return '';
		}

		$exemption = sanitize_key( (string) Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_sale_reference_exemption' ) );
		if ( ! in_array( $exemption, array( 'perishable', 'fast_expiry' ), true ) ) {
			return '';
		}

		$date = Sidrena_Utils::sanitize_date( Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_expiry_date' ) );
		if ( ! $date ) {
			return '';
		}

		$line = sprintf(
			'<span class="sidrena-expiry"><span class="sidrena-expiry__label">%1$s:</span> <span class="sidrena-expiry__value">%2$s</span></span>',
			esc_html__( 'Krajnji rok uporabe', 'sidrena' ),
			esc_html( Sidrena_Utils::date_display( $date ) )
		);
		return apply_filters( 'sidrena_expiry_html', $line, $product, $date, $exemption );
	}

	private function variable_expiry_html( $product ) {
		$dates = array();
		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->exists() || ! $variation->is_on_sale() ) {
				continue;
			}
			$exemption = sanitize_key( (string) Sidrena_Utils::product_meta_with_parent( $variation, '_sidrena_sale_reference_exemption' ) );
			if ( ! in_array( $exemption, array( 'perishable', 'fast_expiry' ), true ) ) {
				continue;
			}
			$date = Sidrena_Utils::sanitize_date( Sidrena_Utils::product_meta_with_parent( $variation, '_sidrena_expiry_date' ) );
			if ( $date ) {
				$dates[] = $date;
			}
		}

		$dates = array_values( array_unique( $dates ) );
		if ( empty( $dates ) ) {
			return '';
		}

		$value = 1 === count( $dates )
			? Sidrena_Utils::date_display( $dates[0] )
			: __( 'prema odabranoj varijaciji', 'sidrena' );

		return sprintf(
			'<span class="sidrena-expiry sidrena-expiry--variable"><span class="sidrena-expiry__label">%1$s:</span> <span class="sidrena-expiry__value">%2$s</span></span>',
			esc_html__( 'Krajnji rok uporabe', 'sidrena' ),
			esc_html( $value )
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
		$tooltip_html  = $tooltip ? '<span class="sidrena-anchor__tooltip" role="tooltip">' . esc_html( $tooltip ) . '</span>' : '';
		$line          = sprintf(
			'<span class="sidrena-anchor%1$s"%2$s><span class="sidrena-anchor__label">%3$s:</span> <span class="sidrena-anchor__value">%4$s</span>%5$s</span>',
			$tooltip ? ' sidrena-anchor--has-tooltip' : '',
			$tooltip ? ' tabindex="0"' : '',
			esc_html( $label ),
			wp_kses_post( wc_price( $display_price ) ),
			$tooltip_html
		);

		return apply_filters( 'sidrena_anchor_html', $line, $product, $anchor, $date );
	}

	private function variable_anchor_html( $product ) {
		$values = array();
		$dates  = array();
		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->exists() ) {
				continue;
			}
			$anchor = Sidrena_Utils::product_anchor_price( $variation_id );
			if ( '' === $anchor ) {
				continue;
			}
			$display = function_exists( 'wc_get_price_to_display' )
				? wc_get_price_to_display( $variation, array( 'price' => (float) $anchor ) )
				: (float) $anchor;
			$display = apply_filters( 'sidrena_anchor_price_to_display', $display, $variation, $anchor );
			$display = apply_filters( 'sidrena_cijena_price_to_display', $display, $variation, $anchor );
			$values[] = (float) $display;
			$dates[]  = Sidrena_Utils::current_reference_date( $variation_id );
		}

		if ( empty( $values ) ) {
			return '';
		}

		$min     = min( $values );
		$max     = max( $values );
		$date    = count( array_unique( $dates ) ) === 1 ? reset( $dates ) : Sidrena_Utils::settings()['default_ref_date'];
		$label   = Sidrena_Utils::anchor_label( $date );
		$amount  = abs( $min - $max ) < 0.00001 ? wc_price( $min ) : wc_format_price_range( $min, $max );
		$tooltip = Sidrena_Utils::anchor_tooltip();
		$tooltip_html = $tooltip ? '<span class="sidrena-anchor__tooltip" role="tooltip">' . esc_html( $tooltip ) . '</span>' : '';

		$line = sprintf(
			'<span class="sidrena-anchor sidrena-anchor--variable%1$s"%2$s><span class="sidrena-anchor__label">%3$s:</span> <span class="sidrena-anchor__value">%4$s</span>%5$s</span>',
			$tooltip ? ' sidrena-anchor--has-tooltip' : '',
			$tooltip ? ' tabindex="0"' : '',
			esc_html( $label ),
			wp_kses_post( $amount ),
			$tooltip_html
		);
		return apply_filters( 'sidrena_anchor_html', $line, $product, array( $min, $max ), $date );
	}

	public function frontend_assets() {
		$settings = Sidrena_Utils::settings();
		if ( 'yes' !== $settings['display_anchor'] && 'yes' !== $settings['display_lowest_30'] ) {
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
		$settings  = Sidrena_Utils::settings();
		$published = get_post_datetime( $post );
		$cutoff    = new DateTimeImmutable( $settings['default_ref_date'] . ' 23:59:59', wp_timezone() );
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
	}

	private function variation_reference_group( $variation_id ) {
		$group = get_post_meta( $variation_id, '_sidrena_reference_group', true );
		if ( $group ) {
			return $group;
		}
		$parent_id = wp_get_post_parent_id( $variation_id );
		$group     = $parent_id ? get_post_meta( $parent_id, '_sidrena_reference_group', true ) : '';
		return $group ?: 'standard';
	}

	public function snapshot_new_variation( $variation_id ) {
		$this->maybe_snapshot_new_variation( $variation_id );
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

		$settings = Sidrena_Utils::settings();
		$created  = get_post_datetime( $post );
		$cutoff   = new DateTimeImmutable( $settings['default_ref_date'] . ' 23:59:59', wp_timezone() );
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
