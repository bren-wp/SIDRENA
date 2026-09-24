<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://sidrene-cijene.com.hr/
 * @see https://brendigo.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sidrena_REST {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_shortcode( 'sidrena_cjenici', array( $this, 'shortcode' ) );
	}

	public function routes() {
		register_rest_route(
			'sidrena/v1',
			'/cjenici',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'index' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'sidrena/v1',
			'/cijene',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'prices' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'type' => array(
						'default'           => 'all',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static function ( $value ) {
							return in_array( $value, array( 'all', 'products', 'services' ), true );
						},
					),
					'location' => array(
						'default'           => '',
						'sanitize_callback' => array( 'Sidrena_Utils', 'sanitize_location_id' ),
					),
					'page' => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 100,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'sidrena/v1',
			'/display/(?P<id>s?\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'display' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'required'          => true,
						'sanitize_callback' => static function ( $value ) {
							return strtolower( trim( (string) $value ) );
						},
						'validate_callback' => static function ( $value ) {
							return 1 === preg_match( '/^s?\d+$/i', (string) $value );
						},
					),
				),
			)
		);
	}

	private function realtime_enabled() {
		$settings = Sidrena_Utils::settings();
		return 'yes' === $settings['enable_rest_index'];
	}

	private function no_cache_response( $data ) {
		$response = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}

	public function index() {
		if ( ! $this->realtime_enabled() ) {
			return new WP_Error( 'disabled', __( 'Javni indeks cjenika je isključen.', 'sidrena' ), array( 'status' => 404 ) );
		}

		$settings = Sidrena_Utils::settings();
		$last     = get_option( 'sidrena_last_run', array() );
		$paths    = Sidrena_Utils::upload_paths();

		return $this->no_cache_response(
			array(
				'schema'          => 3,
				'generator'       => 'Sidrena ' . SIDRENA_VERSION,
				'plugin_url'      => 'https://sidrene-cijene.com.hr/',
				'ruleset'         => SIDRENA_RULESET,
				'rules_effective' => SIDRENA_RULES_EFFECTIVE,
				'catalog_mode'    => Sidrena_Utils::runtime_mode(),
				'woocommerce_active' => Sidrena_Utils::is_woocommerce_active(),
				'product_count'   => Sidrena_Utils::is_wordpress_edition() && class_exists( 'Sidrena_Standalone' ) ? Sidrena_Standalone::count() : null,
				'generated_at'    => isset( $last['generated_at'] ) ? $last['generated_at'] : null,
				'retention_days'  => max( 30, absint( $settings['retention_days'] ) ),
				'manifest_url'    => 'yes' === $settings['publish_manifest'] ? $paths['manifest_url'] : null,
				'realtime_url'    => rest_url( 'sidrena/v1/cijene' ),
				'current'         => Sidrena_Utils::public_index(),
				'archive'         => Sidrena_Utils::archive_index(),
			)
		);
	}

	public function prices( WP_REST_Request $request ) {
		if ( ! $this->realtime_enabled() ) {
			return new WP_Error( 'disabled', __( 'Javni dohvat cijena u realnom vremenu je isključen.', 'sidrena' ), array( 'status' => 404 ) );
		}

		$type     = sanitize_key( (string) $request->get_param( 'type' ) );
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ) );
		$location = $this->resolve_location( (string) $request->get_param( 'location' ) );

		$data = array(
			'schema'       => 1,
			'generator'    => 'Sidrena ' . SIDRENA_VERSION,
			'as_of'        => current_time( DATE_ATOM ),
			'location'            => $location,
			'page'                => $page,
			'per_page'            => $per_page,
			'catalog_mode'        => Sidrena_Utils::runtime_mode(),
			'woocommerce_active'  => Sidrena_Utils::is_woocommerce_active(),
			'products'            => array(),
			'services'            => array(),
		);

		if ( in_array( $type, array( 'all', 'products' ), true ) ) {
			$data['products'] = $this->realtime_products( $location, $page, $per_page );
		}
		if ( in_array( $type, array( 'all', 'services' ), true ) ) {
			$data['services'] = $this->realtime_services( $location, $page, $per_page );
		}

		return $this->no_cache_response( $data );
	}


	public function display( WP_REST_Request $request ) {
		$raw = strtolower( trim( (string) $request->get_param( 'id' ) ) );

		if ( Sidrena_Utils::is_wordpress_edition() ) {
			$id = preg_match( '/^s(\d+)$/', $raw, $match ) ? absint( $match[1] ) : absint( $raw );
			if ( ! $id || ! class_exists( 'Sidrena_Standalone' ) || Sidrena_Standalone::POST_TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
				return new WP_Error( 'product_not_found', __( 'Proizvod nije pronađen.', 'sidrena' ), array( 'status' => 404 ) );
			}
			return $this->no_cache_response(
				array(
					'id'   => 's' . $id,
					'html' => Sidrena_Standalone::instance()->price_shortcode( array( 'id' => 's' . $id ) ),
				)
			);
		}

		if ( ! Sidrena_Utils::is_woocommerce_active() || ! preg_match( '/^\d+$/', $raw ) ) {
			return new WP_Error( 'product_not_found', __( 'Proizvod nije pronađen.', 'sidrena' ), array( 'status' => 404 ) );
		}

		$id      = absint( $raw );
		$product = $id ? wc_get_product( $id ) : false;
		if ( ! $product || ! $product->exists() ) {
			return new WP_Error( 'product_not_found', __( 'Proizvod nije pronađen.', 'sidrena' ), array( 'status' => 404 ) );
		}
		if ( ! Sidrena_Utils::is_public_wc_product( $product ) ) {
			return new WP_Error( 'product_not_public', __( 'Proizvod nije javno dostupan.', 'sidrena' ), array( 'status' => 404 ) );
		}

		return $this->no_cache_response(
			array(
				'id'   => $product->get_id(),
				'html' => Sidrena_Products::instance()->shortcode( array( 'id' => $product->get_id() ) ),
			)
		);
	}
	private function resolve_location( $requested ) {
		$locations = Sidrena_Utils::locations();
		if ( $requested ) {
			foreach ( $locations as $location ) {
				if ( 'yes' === ( $location['enabled'] ?? '' ) && Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' ) === $requested ) {
					return $location;
				}
			}
		}
		foreach ( $locations as $location ) {
			if ( 'yes' === ( $location['enabled'] ?? '' ) ) {
				return $location;
			}
		}
		return array();
	}

	private function realtime_products( $location, $page, $per_page ) {
		if ( Sidrena_Utils::is_wordpress_edition() ) {
			return $this->realtime_standalone_products( $location, $page, $per_page );
		}
		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			return array( 'items' => array(), 'total' => 0, 'total_pages' => 0 );
		}

		$result = wc_get_products(
			array(
				'limit'    => $per_page,
				'page'     => $page,
				'status'   => 'publish',
				'orderby'  => 'ID',
				'order'    => 'ASC',
				'paginate' => true,
				'return'   => 'objects',
			)
		);

		$items = array();
		foreach ( is_object( $result ) && isset( $result->products ) ? $result->products : array() as $product ) {
			if ( ! Sidrena_Utils::is_public_wc_product( $product ) ) {
				continue;
			}
			if ( is_callable( array( $product, 'get_catalog_visibility' ) ) && 'hidden' === $product->get_catalog_visibility() ) {
				continue;
			}
			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					if ( $variation && $variation->exists() && Sidrena_Utils::is_public_wc_product( $variation ) ) {
						$items[] = $this->product_item( $variation, $location );
					}
				}
				continue;
			}
			$items[] = $this->product_item( $product, $location );
		}

		return array(
			'items'       => $items,
			'total'       => is_object( $result ) && isset( $result->total ) ? absint( $result->total ) : count( $items ),
			'total_pages' => is_object( $result ) && isset( $result->max_num_pages ) ? absint( $result->max_num_pages ) : 1,
		);
	}

	private function realtime_standalone_products( $location, $page, $per_page ) {
		$result = Sidrena_Standalone::paged_rows( $page, $per_page, $location );
		$items  = array();
		foreach ( $result['items'] as $row ) {
			$items[] = $this->standalone_product_item( $row, $location );
		}
		return array(
			'items'       => $items,
			'total'       => absint( $result['total'] ),
			'total_pages' => absint( $result['total_pages'] ),
		);
	}

	private function standalone_product_item( $row, $location ) {
		$id = absint( $row['_sidrena_item_id'] ?? 0 );
		return array(
			'id'                           => 's' . $id,
			'parent_id'                    => 0,
			'lokacija_id'                  => Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' ),
			'lokacija_sifra'               => sanitize_text_field( $location['code'] ?? '' ),
			'naziv'                        => sanitize_text_field( $row['naziv'] ?? '' ),
			'sifra'                        => sanitize_text_field( $row['sifra'] ?? '' ),
			'marka'                        => sanitize_text_field( $row['marka'] ?? '' ),
			'jedinica_mjere'               => sanitize_text_field( $row['jedinica_mjere'] ?? '' ),
			'cijena_za_jedinicu_mjere'     => Sidrena_Utils::decimal( $row['cijena_za_jedinicu_mjere'] ?? '' ),
			'jedinicna_cijena_status'       => sanitize_key( $row['_sidrena_unit_status'] ?? 'review' ),
			'maloprodajna_cijena'          => Sidrena_Utils::decimal( $row['maloprodajna_cijena'] ?? '' ),
			'posebni_oblik_prodaje'        => 'da' === ( $row['posebni_oblik_prodaje'] ?? '' ) ? 'da' : 'ne',
			'naziv_posebnog_oblika_prodaje'=> sanitize_text_field( $row['naziv_posebnog_oblika_prodaje'] ?? '' ),
			'sidrena_cijena'               => Sidrena_Utils::decimal( $row['sidrena_cijena'] ?? '' ),
			'datum_sidrene_cijene'         => sanitize_text_field( $row['datum_sidrene_cijene'] ?? '' ),
			'barkod'                       => sanitize_text_field( $row['barkod'] ?? '' ),
			'dostupnost'                   => sanitize_key( $row['dostupnost'] ?? 'dostupno' ),
			'updated_at'                   => $id ? get_post_modified_time( 'c', true, $id ) : null,
		);
	}
	private function product_item( $product, $location ) {
		$override             = ! empty( $location['id'] ) ? Sidrena_Location_Data::get_for_product( $location['id'], $product ) : array();
		$has_location_price    = isset( $override['price'] ) && '' !== $override['price'];
		$has_location_anchor   = isset( $override['anchor_price'] ) && '' !== $override['anchor_price'];
		$current               = $has_location_price ? Sidrena_Utils::decimal( $override['price'] ) : $product->get_price( 'edit' );
		$anchor                = $has_location_anchor ? Sidrena_Utils::decimal( $override['anchor_price'] ) : Sidrena_Utils::product_anchor_price( $product->get_id() );
		$availability          = $override['availability'] ?? '';

		// Keep the real-time endpoint aligned with the public CSV/XML cjenik:
		// WooCommerce base prices follow the shop tax-entry setting, while imported
		// location prices are already treated as final retail amounts.
		if ( ! $has_location_price && '' !== $current && function_exists( 'wc_get_price_including_tax' ) ) {
			$current = wc_get_price_including_tax( $product, array( 'price' => (float) $current ) );
		}
		if ( ! $has_location_anchor && '' !== $anchor && function_exists( 'wc_get_price_including_tax' ) ) {
			$anchor = wc_get_price_including_tax( $product, array( 'price' => (float) $anchor ) );
		}
		if ( ! $availability ) {
			$availability = $product->is_in_stock() ? 'dostupno' : 'nedostupno';
		}
		$sale_name = sanitize_text_field( (string) Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_sale_name' ) );

		return array(
			'id'                           => $product->get_id(),
			'parent_id'                    => $product->is_type( 'variation' ) ? $product->get_parent_id() : 0,
			'lokacija_id'                  => Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' ),
			'lokacija_sifra'               => sanitize_text_field( $location['code'] ?? '' ),
			'naziv'                        => $product->get_name(),
			'sifra'                        => Sidrena_Utils::get_product_code( $product ),
			'marka'                        => Sidrena_Utils::get_brand( $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : $product ),
			'jedinica_mjere'               => Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_unit' ),
			'cijena_za_jedinicu_mjere'     => Sidrena_Utils::decimal( Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_unit_price' ) ),
			'jedinicna_cijena_status'       => sanitize_key( (string) Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_unit_price_status', 'review' ) ),
			'maloprodajna_cijena'          => Sidrena_Utils::decimal( $current ),
			'posebni_oblik_prodaje'        => $product->is_on_sale( 'edit' ) ? 'da' : 'ne',
			'naziv_posebnog_oblika_prodaje'=> $product->is_on_sale( 'edit' ) ? $sale_name : '',
			'sidrena_cijena'               => Sidrena_Utils::decimal( $anchor ),
			'datum_sidrene_cijene'         => '' === $anchor ? '' : Sidrena_Utils::current_reference_date( $product->get_id() ),
			'barkod'                       => Sidrena_Utils::get_barcode( $product ),
			'dostupnost'                   => $availability,
			'updated_at'                   => isset( $override['updated_at'] ) ? $override['updated_at'] : get_post_modified_time( 'c', true, $product->get_id() ),
		);
	}

	private function realtime_services( $location, $page, $per_page ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'sidrena_service',
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$items       = array();
		$location_id = Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' );
		foreach ( $query->posts as $service ) {
			$location_prices = get_post_meta( $service->ID, '_sidrena_service_location_prices', true );
			$location_anchors = get_post_meta( $service->ID, '_sidrena_service_location_anchor_prices', true );
			$location_prices = is_array( $location_prices ) ? $location_prices : array();
			$location_anchors = is_array( $location_anchors ) ? $location_anchors : array();
			$current = isset( $location_prices[ $location_id ] ) && '' !== $location_prices[ $location_id ] ? $location_prices[ $location_id ] : get_post_meta( $service->ID, '_sidrena_service_current_price', true );
			$anchor  = isset( $location_anchors[ $location_id ] ) && '' !== $location_anchors[ $location_id ] ? $location_anchors[ $location_id ] : get_post_meta( $service->ID, '_sidrena_service_anchor_price', true );
			$date    = get_post_meta( $service->ID, '_sidrena_service_anchor_date', true );
			$sale    = 'yes' === get_post_meta( $service->ID, '_sidrena_service_sale', true );

			$items[] = array(
				'id'                            => $service->ID,
				'lokacija_id'                   => $location_id,
				'lokacija_sifra'                => sanitize_text_field( $location['code'] ?? '' ),
				'naziv_usluge'                  => get_the_title( $service ),
				'vrsta_usluge'                  => get_post_meta( $service->ID, '_sidrena_service_type', true ),
				'opseg_usluge'                  => get_post_meta( $service->ID, '_sidrena_service_scope', true ),
				'pripadajuci_troskovi'          => get_post_meta( $service->ID, '_sidrena_service_costs', true ),
				'ugradbena_zamjenska_roba'      => get_post_meta( $service->ID, '_sidrena_service_goods', true ),
				'maloprodajna_cijena'           => Sidrena_Utils::decimal( $current ),
				'posebni_oblik_prodaje'         => $sale ? 'da' : 'ne',
				'naziv_posebnog_oblika_prodaje' => $sale ? get_post_meta( $service->ID, '_sidrena_service_sale_name', true ) : '',
				'sidrena_cijena'                 => Sidrena_Utils::decimal( $anchor ),
				'datum_sidrene_cijene'           => '' === Sidrena_Utils::decimal( $anchor ) ? '' : ( $date ? $date : Sidrena_Utils::settings()['default_ref_date'] ),
				'updated_at'                     => get_post_modified_time( 'c', true, $service->ID ),
			);
		}

		return array(
			'items'       => $items,
			'total'       => absint( $query->found_posts ),
			'total_pages' => absint( $query->max_num_pages ),
		);
	}

	public function shortcode( $atts = array() ) {
		$atts = shortcode_atts( array( 'archive' => 'yes' ), $atts, 'sidrena_cjenici' );
		$current = Sidrena_Utils::public_index();
		$archive = 'yes' === $atts['archive'] ? Sidrena_Utils::archive_index() : array();
		if ( empty( $current ) && empty( $archive ) ) {
			return '<p>' . esc_html__( 'Cjenik još nije generiran.', 'sidrena' ) . '</p>';
		}

		wp_enqueue_style( 'sidrena-frontend', SIDRENA_URL . 'public/css/frontend.css', array(), SIDRENA_VERSION );
		$out  = '<div class="sidrena-public-files">';
		$out .= '<h2>' . esc_html__( 'Aktualni cjenici', 'sidrena' ) . '</h2>';
		$out .= $this->files_list( $current );

		if ( 'yes' === $atts['archive'] ) {
			$current_names = array();
			foreach ( $current as $file ) {
				if ( ! empty( $file['filename'] ) ) {
					$current_names[ $file['filename'] ] = true;
				}
			}
			$older = array();
			foreach ( $archive as $file ) {
				if ( empty( $file['filename'] ) || isset( $current_names[ $file['filename'] ] ) ) {
					continue;
				}
				$older[] = $file;
			}
			if ( ! empty( $older ) ) {
				$out .= '<details class="sidrena-archive"><summary>' . esc_html__( 'Arhiva prethodnih cjenika', 'sidrena' ) . '</summary>';
				$out .= $this->files_list( $older );
				$out .= '</details>';
			}
		}

		$out .= '</div>';
		return $out;
	}

	private function files_list( $files ) {
		if ( empty( $files ) ) {
			return '<p>' . esc_html__( 'Nema dostupnih datoteka.', 'sidrena' ) . '</p>';
		}

		$out = '<ul class="sidrena-file-list">';
		foreach ( $files as $file ) {
			$catalog = 'products' === ( $file['catalog'] ?? '' ) ? __( 'Proizvodi', 'sidrena' ) : __( 'Usluge', 'sidrena' );
			$label = sprintf(
				/* translators: 1: location code, 2: catalog type, 3: file format, 4: generation time. */
				__( '%1$s · %2$s · %3$s · %4$s', 'sidrena' ),
				isset( $file['location_code'] ) ? $file['location_code'] : '',
				$catalog,
				strtoupper( $file['format'] ?? '' ),
				isset( $file['generated_at'] ) ? $file['generated_at'] : ''
			);
			$out .= '<li><a rel="nofollow" href="' . esc_url( $file['url'] ?? '' ) . '">' . esc_html( $label ) . '</a></li>';
		}
		$out .= '</ul>';
		return $out;
	}
}
