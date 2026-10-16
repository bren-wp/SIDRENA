<?php
/**
 * WooCommerce product CSV import / export columns.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * CSV mapping.
 */
class Brendigo_Sidrena_Cijena_Import_Export {

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/** Baselines for WooCommerce CSV rows awaiting a successful product save. */
	private $pending_audit = array();

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
	 * Register CSV filters.
	 */
	private function __construct() {
		add_filter( 'woocommerce_product_export_column_names', array( $this, 'export_columns' ) );
		add_filter( 'woocommerce_product_export_product_default_columns', array( $this, 'export_columns' ) );
		add_filter( 'woocommerce_product_export_product_column_sidrena_cijena', array( $this, 'export_price' ), 10, 2 );
		add_filter( 'woocommerce_product_export_product_column_sidrena_cijena_datum', array( $this, 'export_date' ), 10, 2 );
		add_filter( 'woocommerce_product_export_product_column_sidrena_cijena_cjm', array( $this, 'export_cjm' ), 10, 2 );
		add_filter( 'woocommerce_product_export_product_column_sidrena_cijena_cjm_suffix', array( $this, 'export_cjm_suffix' ), 10, 2 );
		add_filter( 'woocommerce_product_export_product_column_sidrena_naziv_posebnog_oblika', array( $this, 'export_oblik' ), 10, 2 );
		add_filter( 'woocommerce_product_export_product_column_sidrena_jedinica_mjere', array( $this, 'export_jedinica' ), 10, 2 );
		add_filter( 'woocommerce_product_export_product_column_sidrena_cijena_jedinice_mjere', array( $this, 'export_jedinica_cijena' ), 10, 2 );
		add_filter( 'woocommerce_csv_product_import_mapping_options', array( $this, 'import_options' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( $this, 'import_default_columns' ) );
		add_filter( 'woocommerce_product_import_pre_insert_product_object', array( $this, 'import_values' ), 10, 2 );
		add_action( 'woocommerce_product_import_inserted_product_object', array( $this, 'after_import_save' ), 10, 1 );
	}

	/**
	 * Add export columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function export_columns( $columns ) {
		$columns['sidrena_cijena']            = __( 'Sidrena cijena', 'brendigo-sidrena-cijena' );
		$columns['sidrena_cijena_datum']      = __( 'Sidrena cijena datum', 'brendigo-sidrena-cijena' );
		$columns['sidrena_cijena_cjm']                 = __( 'Sidrena cijena CJM', 'brendigo-sidrena-cijena' );
		$columns['sidrena_cijena_cjm_suffix']          = __( 'Sidrena cijena CJM dodatak', 'brendigo-sidrena-cijena' );
		$columns['sidrena_naziv_posebnog_oblika']      = __( 'Naziv posebnog oblika prodaje', 'brendigo-sidrena-cijena' );
		$columns['sidrena_jedinica_mjere']             = __( 'Jedinica mjere', 'brendigo-sidrena-cijena' );
		$columns['sidrena_cijena_jedinice_mjere']      = __( 'Cijena za jedinicu mjere', 'brendigo-sidrena-cijena' );
		return $columns;
	}

	/**
	 * Export price cell.
	 *
	 * @param mixed      $value   Default.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function export_price( $value, $product ) {
		return Brendigo_Sidrena_Cijena_Product_Meta::instance()->get_price( $product->get_id() );
	}

	/**
	 * Export date cell.
	 *
	 * @param mixed      $value   Default.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function export_date( $value, $product ) {
		return Brendigo_Sidrena_Cijena_Product_Meta::instance()->get_date( $product->get_id() );
	}

	/**
	 * Export CJM toggle.
	 *
	 * @param mixed      $value   Default.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function export_cjm( $value, $product ) {
		return Brendigo_Sidrena_Cijena_Product_Meta::instance()->is_cjm_enabled( $product->get_id() ) ? 'yes' : 'no';
	}

	/**
	 * Export CJM suffix.
	 *
	 * @param mixed      $value   Default.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function export_cjm_suffix( $value, $product ) {
		return Brendigo_Sidrena_Cijena_Product_Meta::instance()->get_cjm_suffix( $product->get_id() );
	}

	/**
	 * Export the special-sale name override.
	 *
	 * @param mixed      $value   Default.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function export_oblik( $value, $product ) {
		return Brendigo_Sidrena_Cijena_Product_Meta::instance()->get_oblik( $product->get_id() );
	}

	/**
	 * Export the unit of measure.
	 *
	 * @param mixed      $value   Default.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function export_jedinica( $value, $product ) {
		return Brendigo_Sidrena_Cijena_Product_Meta::instance()->get_jedinica( $product->get_id() );
	}

	/**
	 * Export the unit price.
	 *
	 * @param mixed      $value   Default.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function export_jedinica_cijena( $value, $product ) {
		return Brendigo_Sidrena_Cijena_Product_Meta::instance()->get_jedinica_cijena( $product->get_id() );
	}

	/**
	 * Import mapping labels.
	 *
	 * @param array<string, string> $options Options.
	 * @return array<string, string>
	 */
	public function import_options( $options ) {
		$options['sidrena_cijena']            = __( 'Sidrena cijena', 'brendigo-sidrena-cijena' );
		$options['sidrena_cijena_datum']      = __( 'Sidrena cijena datum', 'brendigo-sidrena-cijena' );
		$options['sidrena_cijena_cjm']        = __( 'Sidrena cijena CJM', 'brendigo-sidrena-cijena' );
		$options['sidrena_cijena_cjm_suffix']     = __( 'Sidrena cijena CJM dodatak', 'brendigo-sidrena-cijena' );
		$options['sidrena_naziv_posebnog_oblika'] = __( 'Naziv posebnog oblika prodaje', 'brendigo-sidrena-cijena' );
		$options['sidrena_jedinica_mjere']        = __( 'Jedinica mjere', 'brendigo-sidrena-cijena' );
		$options['sidrena_cijena_jedinice_mjere'] = __( 'Cijena za jedinicu mjere', 'brendigo-sidrena-cijena' );
		return $options;
	}

	/**
	 * Auto-map CSV headers.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function import_default_columns( $columns ) {
		$columns[ __( 'Sidrena cijena', 'brendigo-sidrena-cijena' ) ]            = 'sidrena_cijena';
		$columns[ __( 'Sidrena cijena datum', 'brendigo-sidrena-cijena' ) ]      = 'sidrena_cijena_datum';
		$columns[ __( 'Sidrena cijena CJM', 'brendigo-sidrena-cijena' ) ]        = 'sidrena_cijena_cjm';
		$columns[ __( 'Sidrena cijena CJM dodatak', 'brendigo-sidrena-cijena' ) ] = 'sidrena_cijena_cjm_suffix';
		$columns['Sidrena cijena']                                     = 'sidrena_cijena';
		$columns['Sidrena cijena datum']                               = 'sidrena_cijena_datum';
		$columns['Sidrena cijena CJM']                                 = 'sidrena_cijena_cjm';
		$columns['Sidrena cijena CJM dodatak']                         = 'sidrena_cijena_cjm_suffix';
		$columns[ __( 'Naziv posebnog oblika prodaje', 'brendigo-sidrena-cijena' ) ] = 'sidrena_naziv_posebnog_oblika';
		$columns[ __( 'Jedinica mjere', 'brendigo-sidrena-cijena' ) ]                = 'sidrena_jedinica_mjere';
		$columns[ __( 'Cijena za jedinicu mjere', 'brendigo-sidrena-cijena' ) ]      = 'sidrena_cijena_jedinice_mjere';
		$columns['Naziv posebnog oblika prodaje'] = 'sidrena_naziv_posebnog_oblika';
		$columns['Jedinica mjere']                = 'sidrena_jedinica_mjere';
		$columns['Cijena za jedinicu mjere']      = 'sidrena_cijena_jedinice_mjere';
		return $columns;
	}


	/** WooCommerce CSV import succeeded: persist provenance after the CRUD save. */
	public function after_import_save( $product ) {
		if ( ! $product instanceof WC_Product ) { return; }
		$key = spl_object_hash( $product );
		if ( ! isset( $this->pending_audit[ $key ] ) ) { return; }
		$previous = $this->pending_audit[ $key ];
		unset( $this->pending_audit[ $key ] );
		$id = absint( $product->get_id() );
		if ( ! $id ) { return; }
		$fields = array( 'sidrena_cijena' => BRENDIGO_SIDRENA_CIJENA_META_PRICE, 'sidrena_datum' => BRENDIGO_SIDRENA_CIJENA_META_DATE );
		foreach ( $fields as $field => $meta_key ) {
			Brendigo_Sidrena_Cijena_Audit_Log::instance()->record( 'woo', $id, $field, $previous[ $field ], (string) $product->get_meta( $meta_key, true ) );
		}
	}

	/**
	 * Apply imported values onto the product object meta.
	 *
	 * @param WC_Product           $product Product.
	 * @param array<string, mixed> $data    Row data.
	 * @return WC_Product
	 */
	public function import_values( $product, $data ) {
		$meta = Brendigo_Sidrena_Cijena_Product_Meta::instance();
		// Record the pre-import state in memory; write an audit record only
		// after WooCommerce confirms that the product was saved.
		if ( $product instanceof WC_Product && ( isset( $data['sidrena_cijena'] ) || isset( $data['sidrena_cijena_datum'] ) ) ) {
			$this->pending_audit[ spl_object_hash( $product ) ] = array(
				'sidrena_cijena' => (string) $product->get_meta( BRENDIGO_SIDRENA_CIJENA_META_PRICE, true ),
				'sidrena_datum' => (string) $product->get_meta( BRENDIGO_SIDRENA_CIJENA_META_DATE, true ),
			);
		}

		if ( isset( $data['sidrena_cijena'] ) ) {
			$price = $meta->normalize_price( $data['sidrena_cijena'] );
			if ( '' === $price ) {
				$product->delete_meta_data( BRENDIGO_SIDRENA_CIJENA_META_PRICE );
			} else {
				$product->update_meta_data( BRENDIGO_SIDRENA_CIJENA_META_PRICE, $price );
			}
		}

		if ( isset( $data['sidrena_cijena_datum'] ) ) {
			$date = sanitize_text_field( (string) $data['sidrena_cijena_datum'] );
			if ( '' === $date ) {
				$product->delete_meta_data( BRENDIGO_SIDRENA_CIJENA_META_DATE );
			} else {
				$product->update_meta_data( BRENDIGO_SIDRENA_CIJENA_META_DATE, $date );
			}
		}

		if ( isset( $data['sidrena_cijena_cjm'] ) ) {
			$cjm = 'yes' === strtolower( trim( (string) $data['sidrena_cijena_cjm'] ) ) ? 'yes' : '';
			if ( '' === $cjm ) {
				$product->delete_meta_data( BRENDIGO_SIDRENA_CIJENA_META_CJM );
			} else {
				$product->update_meta_data( BRENDIGO_SIDRENA_CIJENA_META_CJM, 'yes' );
			}
		}

		if ( isset( $data['sidrena_cijena_cjm_suffix'] ) ) {
			$suffix = sanitize_text_field( (string) $data['sidrena_cijena_cjm_suffix'] );
			if ( '' === $suffix ) {
				$product->delete_meta_data( BRENDIGO_SIDRENA_CIJENA_META_CJM_SUFFIX );
			} else {
				$product->update_meta_data( BRENDIGO_SIDRENA_CIJENA_META_CJM_SUFFIX, $suffix );
			}
		}

		if ( isset( $data['sidrena_naziv_posebnog_oblika'] ) ) {
			$name = sanitize_text_field( (string) $data['sidrena_naziv_posebnog_oblika'] );
			if ( '' === $name ) {
				$product->delete_meta_data( BRENDIGO_SIDRENA_CIJENA_META_OBLIK );
			} else {
				$product->update_meta_data( BRENDIGO_SIDRENA_CIJENA_META_OBLIK, $name );
			}
		}

		if ( isset( $data['sidrena_jedinica_mjere'] ) ) {
			$unit = sanitize_text_field( (string) $data['sidrena_jedinica_mjere'] );
			if ( '' === $unit ) {
				$product->delete_meta_data( BRENDIGO_SIDRENA_CIJENA_META_JEDINICA );
			} else {
				$product->update_meta_data( BRENDIGO_SIDRENA_CIJENA_META_JEDINICA, $unit );
			}
		}

		if ( isset( $data['sidrena_cijena_jedinice_mjere'] ) ) {
			$unit_price = $meta->normalize_price( $data['sidrena_cijena_jedinice_mjere'] );
			if ( '' === $unit_price ) {
				$product->delete_meta_data( BRENDIGO_SIDRENA_CIJENA_META_JEDINICA_CIJENA );
			} else {
				$product->update_meta_data( BRENDIGO_SIDRENA_CIJENA_META_JEDINICA_CIJENA, $unit_price );
			}
		}

		return $product;
	}
}
