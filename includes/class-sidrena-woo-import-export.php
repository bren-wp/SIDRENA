<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sidrena_Woo_Import_Export {
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

		add_filter( 'woocommerce_product_export_column_names', array( $this, 'export_columns' ) );
		add_filter( 'woocommerce_product_export_product_default_columns', array( $this, 'export_columns' ) );

		foreach ( array_keys( $this->columns() ) as $key ) {
			add_filter( 'woocommerce_product_export_product_column_' . $key, array( $this, 'export_value' ), 10, 2 );
		}

		add_filter( 'woocommerce_csv_product_import_mapping_options', array( $this, 'import_options' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( $this, 'import_default_columns' ) );
		add_filter( 'woocommerce_product_import_pre_insert_product_object', array( $this, 'import_values' ), 10, 2 );
	}

	private function columns() {
		return array(
			'sidrena_cijena'                  => __( 'Sidrena cijena', 'sidrena' ),
			'sidrena_datum'                   => __( 'Sidrena referentni datum', 'sidrena' ),
			'sidrena_skupina'                 => __( 'Sidrena referentna skupina', 'sidrena' ),
			'sidrena_marka'                   => __( 'Sidrena marka', 'sidrena' ),
			'sidrena_barkod'                  => __( 'Sidrena barkod', 'sidrena' ),
			'sidrena_jedinicna_status'        => __( 'Sidrena jedinična cijena status', 'sidrena' ),
			'sidrena_jedinica_mjere'          => __( 'Sidrena jedinica mjere', 'sidrena' ),
			'sidrena_cijena_jedinice_mjere'   => __( 'Sidrena cijena za jedinicu mjere', 'sidrena' ),
			'sidrena_naziv_posebnog_oblika'   => __( 'Sidrena naziv posebnog oblika prodaje', 'sidrena' ),
		);
	}

	public function export_columns( $columns ) {
		foreach ( $this->columns() as $key => $label ) {
			$columns[ $key ] = $label;
		}
		return $columns;
	}

	public function export_value( $value, $product ) {
		if ( ! $product instanceof WC_Product ) {
			return $value;
		}

		$current_filter = current_filter();
		$prefix         = 'woocommerce_product_export_product_column_';
		$key            = 0 === strpos( $current_filter, $prefix ) ? substr( $current_filter, strlen( $prefix ) ) : '';

		switch ( $key ) {
			case 'sidrena_cijena':
				return Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_anchor_price' );
			case 'sidrena_datum':
				return Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_anchor_date' );
			case 'sidrena_skupina':
				return Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_reference_group', 'standard' );
			case 'sidrena_marka':
				$brand_product = $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : $product;
				return Sidrena_Utils::get_brand( $brand_product );
			case 'sidrena_barkod':
				return Sidrena_Utils::get_barcode( $product );
			case 'sidrena_jedinicna_status':
				return Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_unit_price_status', 'review' );
			case 'sidrena_jedinica_mjere':
				return Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_unit' );
			case 'sidrena_cijena_jedinice_mjere':
				return Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_unit_price' );
			case 'sidrena_naziv_posebnog_oblika':
				return Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_sale_name' );
			default:
				return $value;
		}
	}

	public function import_options( $options ) {
		foreach ( $this->columns() as $key => $label ) {
			$options[ $key ] = $label;
		}
		return $options;
	}

	public function import_default_columns( $columns ) {
		$aliases = array(
			'Sidrena cijena'                         => 'sidrena_cijena',
			'Sidrena referentni datum'               => 'sidrena_datum',
			'Sidrena cijena datum'                   => 'sidrena_datum',
			'Sidrena referentna skupina'             => 'sidrena_skupina',
			'Sidrena marka'                          => 'sidrena_marka',
			'Sidrena barkod'                         => 'sidrena_barkod',
			'Sidrena jedinična cijena status'        => 'sidrena_jedinicna_status',
			'Sidrena jedinica mjere'                 => 'sidrena_jedinica_mjere',
			'Jedinica mjere'                         => 'sidrena_jedinica_mjere',
			'Sidrena cijena za jedinicu mjere'       => 'sidrena_cijena_jedinice_mjere',
			'Cijena za jedinicu mjere'               => 'sidrena_cijena_jedinice_mjere',
			'Sidrena naziv posebnog oblika prodaje'  => 'sidrena_naziv_posebnog_oblika',
			'Naziv posebnog oblika prodaje'          => 'sidrena_naziv_posebnog_oblika',
		);
		foreach ( $aliases as $label => $key ) {
			$columns[ $label ] = $key;
		}
		return $columns;
	}

	public function import_values( $product, $data ) {
		if ( ! $product instanceof WC_Product || ! is_array( $data ) ) {
			return $product;
		}

		$this->set_decimal( $product, '_sidrena_anchor_price', $data, 'sidrena_cijena' );
		$this->set_date( $product, '_sidrena_anchor_date', $data, 'sidrena_datum' );
		$this->set_allowed_key( $product, '_sidrena_reference_group', $data, 'sidrena_skupina', array( 'standard', 'fmcg', 'custom' ), 'standard' );
		$this->set_text( $product, '_sidrena_brand', $data, 'sidrena_marka' );
		$this->set_text( $product, '_sidrena_barcode', $data, 'sidrena_barkod' );
		$this->set_allowed_key( $product, '_sidrena_unit_price_status', $data, 'sidrena_jedinicna_status', array( 'review', 'required', 'not_required', 'exception' ), 'review' );
		$this->set_text( $product, '_sidrena_unit', $data, 'sidrena_jedinica_mjere' );
		$this->set_decimal( $product, '_sidrena_unit_price', $data, 'sidrena_cijena_jedinice_mjere' );
		$this->set_text( $product, '_sidrena_sale_name', $data, 'sidrena_naziv_posebnog_oblika' );

		Sidrena_Pricelist::queue_regeneration();
		return $product;
	}

	private function set_text( $product, $meta_key, $data, $column ) {
		if ( ! array_key_exists( $column, $data ) ) {
			return;
		}
		$value = sanitize_text_field( (string) $data[ $column ] );
		if ( '' === $value ) {
			$product->delete_meta_data( $meta_key );
		} else {
			$product->update_meta_data( $meta_key, $value );
		}
	}

	private function set_decimal( $product, $meta_key, $data, $column ) {
		if ( ! array_key_exists( $column, $data ) ) {
			return;
		}
		$value = Sidrena_Utils::decimal( $data[ $column ] );
		if ( '' === $value ) {
			$product->delete_meta_data( $meta_key );
		} else {
			$product->update_meta_data( $meta_key, $value );
		}
	}

	private function set_date( $product, $meta_key, $data, $column ) {
		if ( ! array_key_exists( $column, $data ) ) {
			return;
		}
		$raw = trim( (string) $data[ $column ] );
		if ( '' === $raw ) {
			$product->delete_meta_data( $meta_key );
			return;
		}
		$value = Sidrena_Utils::sanitize_date( $raw );
		if ( $value ) {
			$product->update_meta_data( $meta_key, $value );
		}
	}

	private function set_allowed_key( $product, $meta_key, $data, $column, $allowed, $fallback ) {
		if ( ! array_key_exists( $column, $data ) ) {
			return;
		}
		$raw = trim( (string) $data[ $column ] );
		if ( '' === $raw ) {
			$product->delete_meta_data( $meta_key );
			return;
		}
		$value = sanitize_key( $raw );
		$product->update_meta_data( $meta_key, in_array( $value, $allowed, true ) ? $value : $fallback );
	}
}
