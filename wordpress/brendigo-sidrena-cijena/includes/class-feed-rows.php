<?php
/**
 * Build public and download catalog rows from WooCommerce and the manual catalog.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build public and download catalog rows from WooCommerce and the manual catalog.
 */
class Brendigo_Sidrena_Cijena_Feed_Rows {

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
	 * Collect catalog rows: name, sku, prices.
	 *
	 * WooCommerce rows are the shop default language. A translation is not a second row.
	 *
	 * @param bool $missing_only Only products/variations without sidrena price.
	 * @param bool $public       Published catalog for the public XML (adds id/url).
	 * @return array<int, array<string, string>>
	 */
	public function collect_rows( $missing_only = false, $public = false, $only_ids = null, $catalog_scope = null ) {
		return Brendigo_Sidrena_Cijena_I18n::instance()->run_in_default_language(
			function () use ( $missing_only, $public, $only_ids, $catalog_scope ) {
				return $this->collect_rows_now( $missing_only, $public, $only_ids, $catalog_scope );
			}
		);
	}

	/**
	 * Collect catalog rows while the default language is already current.
	 *
	 * @param bool              $missing_only  Only products/variations without sidrena price.
	 * @param bool              $public        Published catalog for the public XML (adds id/url).
	 * @param array<int,string>|null $only_ids Outlet product ids, or null for the full catalog.
	 * @param string|null       $catalog_scope Outlet code, empty for the webshop catalog, null for every manual row.
	 * @return array<int, array<string, string>>
	 */
	private function collect_rows_now( $missing_only = false, $public = false, $only_ids = null, $catalog_scope = null ) {
		if ( ! Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ) {
			$assigned = array();
			if ( is_string( $catalog_scope ) && '' !== $catalog_scope ) {
				$outlet = Brendigo_Sidrena_Cijena_Poslovnice::instance()->get_by_oznaka( $catalog_scope );
				$assigned = ( is_array( $outlet ) && isset( $outlet['products'] ) ) ? $outlet['products'] : array();
			} elseif ( is_string( $catalog_scope ) && '' === $catalog_scope ) {
				$assigned = array_keys( Brendigo_Sidrena_Cijena_Poslovnice::instance()->assigned_ids_lookup() );
			}

			return Brendigo_Sidrena_Cijena_Catalog::instance()->to_export_rows_for( $missing_only, $public, $catalog_scope, $assigned );
		}

		if ( is_array( $only_ids ) ) {
			$rows = $this->collect_rows_for_ids( $only_ids, $missing_only, $public );
			if ( is_string( $catalog_scope ) && '' !== $catalog_scope ) {
				$rows = $this->apply_outlet_dostupnost( $rows, $catalog_scope );
			}
			$this->append_extra_catalog_rows( $rows, $missing_only, $public, $catalog_scope, $only_ids );
			return $rows;
		}

		$rows = $this->collect_woo_rows( $missing_only, $public );
		$this->append_extra_catalog_rows( $rows, $missing_only, $public, $catalog_scope, null );

		return $rows;
	}

	/**
	 * WooCommerce products and variations only.
	 *
	 * @param bool $missing_only Empty sidrena only.
	 * @param bool $public       Public catalog columns.
	 * @return array<int, array<string, string>>
	 */
	public function collect_woo_rows( $missing_only, $public ) {
		$meta = Brendigo_Sidrena_Cijena_Product_Meta::instance();
		$rows = array();
		$page = 1;
		$lang = $this->polylang_catalog_lang();

		/*
		 * WooCommerce drops unknown query vars before WP_Query. Polylang only
		 * limits the catalog when `lang` reaches that query.
		 */
		$forward_lang = static function ( $args, $query_vars ) {
			if ( isset( $query_vars['lang'] ) && is_string( $query_vars['lang'] ) && '' !== $query_vars['lang'] ) {
				$args['lang'] = $query_vars['lang'];
			}

			return $args;
		};

		if ( '' !== $lang ) {
			add_filter( 'woocommerce_product_data_store_cpt_get_products_query', $forward_lang, 10, 2 );
		}

		try {
			while ( true ) {
				$query = array(
					'status'  => $public ? array( 'publish' ) : array( 'publish', 'private', 'draft', 'pending' ),
					'type'    => array( 'simple', 'variable', 'external', 'subscription', 'variable-subscription' ),
					'limit'   => Brendigo_Sidrena_Cijena_Export::BATCH_SIZE,
					'page'    => $page,
					'return'  => 'ids',
					'orderby' => 'ID',
					'order'   => 'ASC',
				);
				if ( '' !== $lang ) {
					$query['lang'] = $lang;
				}

				$ids = wc_get_products( $query );

				if ( empty( $ids ) ) {
					break;
				}

				update_meta_cache( 'post', $ids );

				foreach ( $ids as $product_id ) {
					$product = wc_get_product( $product_id );
					if ( ! $product ) {
						continue;
					}

					if ( $product->is_type( 'variable' ) || $product->is_type( 'variable-subscription' ) ) {
						$children = $product->get_children();
						if ( ! empty( $children ) ) {
							update_meta_cache( 'post', $children );
						}
						foreach ( $children as $variation_id ) {
							$variation = wc_get_product( $variation_id );
							if ( $variation ) {
								$this->maybe_add_row( $rows, $variation, $meta, $missing_only, $public );
							}
						}
						continue;
					}

					$this->maybe_add_row( $rows, $product, $meta, $missing_only, $public );
				}

				if ( count( $ids ) < Brendigo_Sidrena_Cijena_Export::BATCH_SIZE ) {
					break;
				}

				++$page;
			}

			return $rows;
		} finally {
			if ( '' !== $lang ) {
				remove_filter( 'woocommerce_product_data_store_cpt_get_products_query', $forward_lang, 10 );
			}
		}
	}

	/**
	 * Extra catalog rows (services, made-to-order) after WooCommerce products.
	 *
	 * @param array<int, array<string, string>> $rows         Collector.
	 * @param bool                              $missing_only Filter empty sidrena.
	 * @param bool                              $public       Include public catalog fields.
	 */
	public function append_extra_catalog_rows( &$rows, $missing_only, $public, $catalog_scope = null, $assigned_ids = null ) {
		if ( ! Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ) {
			return;
		}

		$catalog = Brendigo_Sidrena_Cijena_Catalog::instance();
		if ( null === $catalog_scope ) {
			$extra = $catalog->to_export_rows( $missing_only, $public );
		} elseif ( '' === $catalog_scope ) {
			$extra = $catalog->to_export_rows_for( $missing_only, $public, '', array_keys( Brendigo_Sidrena_Cijena_Poslovnice::instance()->assigned_ids_lookup() ) );
		} else {
			$ids   = is_array( $assigned_ids ) ? $assigned_ids : array();
			$extra = $catalog->to_export_rows_for( $missing_only, $public, $catalog_scope, $ids );
		}

		foreach ( $extra as $row ) {
			if ( $public && isset( $row['id'] ) && '' !== (string) $row['id'] ) {
				$row['id'] = 'd' . $row['id'];
			}
			unset( $row['poslovnica'] );
			$rows[] = $row;
		}
	}


	/**
	 * Woo rows for an explicit id list (simple products, variations, or variable parents).
	 *
	 * @param array<int, string> $only_ids     Product ids.
	 * @param bool               $missing_only Filter empty sidrena.
	 * @param bool               $public       Include public catalog fields.
	 * @return array<int, array<string, string>>
	 */
	public function collect_rows_for_ids( $only_ids, $missing_only, $public ) {
		$i18n   = Brendigo_Sidrena_Cijena_I18n::instance();
		$lookup = array();
		foreach ( $only_ids as $id ) {
			$source = $i18n->source_product_id( (int) absint( $id ) );
			if ( $source > 0 ) {
				$lookup[ (string) $source ] = true;
			}
		}

		if ( array() === $lookup ) {
			return array();
		}

		update_meta_cache( 'post', array_map( 'intval', array_keys( $lookup ) ) );

		$meta = Brendigo_Sidrena_Cijena_Product_Meta::instance();
		$rows = array();
		$seen = array();

		foreach ( array_keys( $lookup ) as $id ) {
			$product = wc_get_product( (int) $id );
			if ( ! $product ) {
				continue;
			}

			if ( $product->is_type( 'variable' ) || $product->is_type( 'variable-subscription' ) ) {
				$children = $product->get_children();
				if ( ! empty( $children ) ) {
					update_meta_cache( 'post', $children );
				}
				foreach ( $children as $variation_id ) {
					$variation_id = (int) $variation_id;
					if ( isset( $seen[ $variation_id ] ) ) {
						continue;
					}
					$seen[ $variation_id ] = true;
					$variation             = wc_get_product( $variation_id );
					if ( $variation ) {
						$this->maybe_add_row( $rows, $variation, $meta, $missing_only, $public );
					}
				}
				continue;
			}

			$product_id = (int) $product->get_id();
			if ( isset( $seen[ $product_id ] ) ) {
				continue;
			}
			$seen[ $product_id ] = true;
			$this->maybe_add_row( $rows, $product, $meta, $missing_only, $public );
		}

		return $rows;
	}

	/**
	 * Append one product or variation row.
	 *
	 * @param array<int, array<string, string>> $rows         Collector.
	 * @param WC_Product                        $product      Product.
	 * @param Brendigo_Sidrena_Cijena_Product_Meta       $meta         Meta helper.
	 * @param bool                              $missing_only Filter empty sidrena.
	 * @param bool                              $public       Include public catalog fields.
	 */
	public function maybe_add_row( &$rows, $product, $meta, $missing_only, $public = false ) {
		if ( ! Brendigo_Sidrena_Cijena_I18n::instance()->is_source_product( $product->get_id() ) ) {
			return;
		}

		if ( $public && ! $this->include_in_public_feed( $product ) ) {
			return;
		}

		$sidrena = $meta->get_price( $product->get_id() );

		if ( $missing_only && '' !== $sidrena ) {
			return;
		}

		$row = array(
			'naziv'           => $this->feed_naziv( $product ),
			'sku'             => (string) $product->get_sku(),
			'cijena_redovna'  => (string) $product->get_regular_price(),
			'cijena_akcijska' => (string) $product->get_sale_price(),
			'sidrena_cijena'  => $sidrena,
			'vrsta'           => 'proizvod',
			'sidrena_datum'   => $meta->get_display_date( $product->get_id() ),
		);

		if ( $public ) {
			$row['id']                     = (string) $product->get_id();
			$row['url']                    = (string) get_permalink( $product->get_id() );
			$row['barkod']                 = $this->product_barcode( $product );
			$row['marka']                  = Brendigo_Sidrena_Cijena_Settings::instance()->product_marka( $product );
			$row['dostupnost']             = $this->product_dostupnost( $product );
			$row['jedinica_mjere']         = $meta->get_jedinica( $product->get_id() );
			$row['cijena_jedinice_mjere']  = $this->format_feed_amount( $meta->get_price_to_display( $product, $meta->get_jedinica_cijena( $product->get_id() ) ) );
			$row['maloprodajna_cijena']    = $this->format_feed_amount( $meta->get_price_to_display( $product, $this->active_raw_price( $product ) ) );
			$row['naziv_posebnog_oblika']  = $meta->public_oblik_name( $product );
			$row['sidrena_cijena']         = $this->format_feed_amount( $meta->get_price_to_display( $product, $sidrena ) );
		}

		$rows[] = $row;
	}

	/**
	 * Catalog name: parent title plus all assigned variation attributes.
	 *
	 * WooCommerce may store only the parent title when a variation has three or
	 * more attributes, or an attribute slug with a hyphen. The price list still
	 * needs those values so each row identifies the item.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return string
	 */
	public function feed_naziv( $product ) {
		$name = wp_strip_all_tags( $product->get_name() );

		if ( ! $product->is_type( 'variation' ) ) {
			return $name;
		}

		$parent_id = $product->get_parent_id();
		$base      = $parent_id ? wp_strip_all_tags( (string) get_post_field( 'post_title', $parent_id ) ) : '';
		if ( '' === $base ) {
			return $name;
		}

		$suffix = function_exists( 'wc_get_formatted_variation' )
			? trim( wp_strip_all_tags( wc_get_formatted_variation( $product, true, false ) ) )
			: '';

		if ( '' === $suffix || false !== strpos( $base, $suffix ) ) {
			return $base;
		}

		return $base . ' - ' . $suffix;
	}

	/**
	 * GTIN / UPC / EAN / ISBN from WooCommerce, then common barcode meta keys.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return string
	 */
	public function product_barcode( $product ) {
		$barcode = '';

		if ( method_exists( $product, 'get_global_unique_id' ) ) {
			$barcode = $this->clean_barcode( $product->get_global_unique_id() );
		}

		if ( '' === $barcode ) {
			$id = $product->get_id();
			foreach ( array( BRENDIGO_SIDRENA_CIJENA_META_BARCODE, '_cjenik_barcode', '_global_unique_id', '_gtin', '_ean', '_upc', '_isbn', '_barcode', 'barcode', '_alg_ean', '_wpm_gtin_code', 'hwp_product_gtin' ) as $key ) {
				$barcode = $this->clean_barcode( get_post_meta( $id, $key, true ) );
				if ( '' !== $barcode ) {
					break;
				}
			}
		}

		/**
		 * Barcode written to the public XML/CSV feed.
		 *
		 * @param string     $barcode Detected GTIN/UPC/EAN/ISBN, or empty.
		 * @param WC_Product $product Product or variation.
		 */
		return (string) apply_filters( 'sidrena_cijena_product_barcode', $barcode, $product );
	}

	/**
	 * First non-empty barcode token, with whitespace removed.
	 *
	 * @param mixed $raw Meta or getter value.
	 * @return string
	 */
	public function clean_barcode( $raw ) {
		$value = is_scalar( $raw ) ? preg_replace( '/\s+/', '', (string) $raw ) : '';
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Legal availability flag: dostupno or nedostupno.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return string
	 */
	public function product_dostupnost( $product ) {
		$value = $product->is_in_stock() ? 'dostupno' : 'nedostupno';

		/**
		 * Availability written to the public XML/CSV feed.
		 *
		 * @param string     $value   dostupno|nedostupno.
		 * @param WC_Product $product Product or variation.
		 */
		$value = apply_filters( 'sidrena_cijena_product_dostupnost', $value, $product );

		return 'nedostupno' === $value ? 'nedostupno' : 'dostupno';
	}

	/**
	 * Price the customer pays now: sale price while the sale is active, otherwise regular.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return string Raw stored amount.
	 */
	public function active_raw_price( $product ) {
		if ( $product->is_on_sale() ) {
			$sale = (string) $product->get_sale_price();
			if ( '' !== $sale ) {
				return $sale;
			}
		}

		$regular = (string) $product->get_regular_price();
		if ( '' !== $regular ) {
			return $regular;
		}

		return (string) $product->get_price();
	}

	/**
	 * Dot-decimal amount for the machine-readable feed.
	 *
	 * @param float|null $amount Display amount.
	 * @return string
	 */
	public function format_feed_amount( $amount ) {
		if ( null === $amount || '' === $amount ) {
			return '';
		}

		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		return number_format( (float) $amount, $decimals, '.', '' );
	}

	/**
	 * Published products only. Password-protected and catalog-hidden products stay out of public files.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return bool
	 */
	public function include_in_public_feed( $product ) {
		if ( ! $product instanceof WC_Product || 'publish' !== $product->get_status() ) {
			return false;
		}

		$post = get_post( $product->get_id() );
		if ( $post instanceof WP_Post && '' !== (string) $post->post_password ) {
			return false;
		}

		$visibility = (string) get_post_meta( $product->get_id(), BRENDIGO_SIDRENA_CIJENA_META_VISIBILITY, true );
		if ( '' === $visibility ) {
			$visibility = (string) get_post_meta( $product->get_id(), '_cjenik_visibility_override', true );
		}
		if ( 'exclude' === $visibility ) {
			return false;
		}

		$parent_id = $product->get_parent_id();
		if ( $parent_id ) {
			$parent_post = get_post( $parent_id );
			if ( ! $parent_post instanceof WP_Post || 'publish' !== $parent_post->post_status || '' !== (string) $parent_post->post_password ) {
				return false;
			}

			if ( 'include' === $visibility ) {
				return true;
			}

			$parent = wc_get_product( $parent_id );
			return ! ( $parent instanceof WC_Product && 'hidden' === $parent->get_catalog_visibility() );
		}

		if ( 'include' === $visibility ) {
			return true;
		}

		return 'hidden' !== $product->get_catalog_visibility();
	}

	/**
	 * Public rows grouped once: webshop (all Woo + webshop catalog) and each outlet.
	 *
	 * @return array{webshop: array<int, array<string, string>>, outlets: array<string, array<int, array<string, string>>>}
	 */
	public function collect_public_by_scope() {
		return Brendigo_Sidrena_Cijena_I18n::instance()->run_in_default_language(
			function () {
				return $this->collect_public_by_scope_now();
			}
		);
	}

	/**
	 * Public rows grouped once, while the default language is already current.
	 *
	 * @return array{webshop: array<int, array<string, string>>, outlets: array<string, array<int, array<string, string>>>}
	 */
	private function collect_public_by_scope_now() {
		if ( ! Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ) {
			$web     = $this->collect_rows( false, true, null, '' );
			$outlets = array();
			foreach ( Brendigo_Sidrena_Cijena_Poslovnice::instance()->with_products() as $outlet ) {
				$outlets[ $outlet['oznaka'] ] = $this->collect_rows( false, true, isset( $outlet['products'] ) ? $outlet['products'] : array(), $outlet['oznaka'] );
			}

			return array(
				'webshop' => $web,
				'outlets' => $outlets,
			);
		}

		$woo = $this->collect_woo_rows( false, true );

		$web = $woo;
		$this->append_extra_catalog_rows( $web, false, true, '', array_keys( Brendigo_Sidrena_Cijena_Poslovnice::instance()->assigned_ids_lookup() ) );

		$outlets = array();
		foreach ( Brendigo_Sidrena_Cijena_Poslovnice::instance()->with_products() as $outlet ) {
			$ids  = isset( $outlet['products'] ) ? $outlet['products'] : array();
			$rows = $this->apply_outlet_dostupnost( $this->filter_woo_rows_for_assigned_ids( $woo, $ids ), $outlet['oznaka'] );
			$this->append_extra_catalog_rows( $rows, false, true, $outlet['oznaka'], $ids );
			$outlets[ $outlet['oznaka'] ] = $rows;
		}

		return array(
			'webshop' => $web,
			'outlets' => $outlets,
		);
	}

	/**
	 * Keep Woo rows whose id is in the outlet assignment, expanding variable parents.
	 *
	 * @param array<int, array<string, string>> $woo_rows  Full Woo public rows.
	 * @param array<int, string>                $only_ids  Assigned product ids.
	 * @return array<int, array<string, string>>
	 */
	public function filter_woo_rows_for_assigned_ids( $woo_rows, $only_ids ) {
		$expanded = $this->collect_rows_for_ids( $only_ids, false, true );
		$by_id    = array();
		foreach ( $woo_rows as $row ) {
			if ( isset( $row['id'] ) && '' !== (string) $row['id'] ) {
				$by_id[ (string) $row['id'] ] = $row;
			}
		}

		$out = array();
		foreach ( $expanded as $row ) {
			$id = isset( $row['id'] ) ? (string) $row['id'] : '';
			if ( '' !== $id && isset( $by_id[ $id ] ) ) {
				$out[] = $by_id[ $id ];
				continue;
			}

			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Replace WooCommerce stock on outlet rows with the availability stored for that poslovnica.
	 * Catalog rows (id prefix d) keep the flag imported on that row.
	 *
	 * @param array<int, array<string, string>> $rows   Feed rows.
	 * @param string                            $oznaka Outlet code.
	 * @return array<int, array<string, string>>
	 */
	public function apply_outlet_dostupnost( $rows, $oznaka ) {
		$map = $this->stock_map_for_source_language( Brendigo_Sidrena_Cijena_Poslovnice::instance()->stock_map( $oznaka ) );
		foreach ( $rows as $index => $row ) {
			$id = isset( $row['id'] ) ? (string) $row['id'] : '';
			if ( '' === $id || 'd' === $id[0] ) {
				continue;
			}
			$rows[ $index ]['dostupnost'] = Brendigo_Sidrena_Cijena_Money::dostupnost_from_outlet_map( $id, $map );
		}

		return $rows;
	}

	/**
	 * Polylang slug for the catalog query. Empty when Polylang is not active.
	 *
	 * @return string
	 */
	private function polylang_catalog_lang() {
		if ( ! function_exists( 'pll_default_language' ) ) {
			return '';
		}

		$lang = Brendigo_Sidrena_Cijena_I18n::instance()->get_default_language();
		return is_string( $lang ) ? $lang : '';
	}

	/**
	 * Outlet availability keyed by the default-language product id.
	 *
	 * A flag stored on a translation still matches that source row. The saved
	 * outlet option is left as the merchant stored it.
	 *
	 * @param array<string, string> $map Product id => dostupno|nedostupno.
	 * @return array<string, string>
	 */
	private function stock_map_for_source_language( $map ) {
		if ( ! is_array( $map ) ) {
			return array();
		}

		$i18n = Brendigo_Sidrena_Cijena_I18n::instance();
		if ( ! $i18n->uses_post_translations() ) {
			return $map;
		}

		$out = array();
		foreach ( $map as $id => $flag ) {
			$source = $i18n->source_product_id( (int) $id );
			if ( $source <= 0 ) {
				continue;
			}

			$key = (string) $source;
			if ( (string) $id === $key || ! isset( $out[ $key ] ) ) {
				$out[ $key ] = $flag;
			}
		}

		return $out;
	}
}
