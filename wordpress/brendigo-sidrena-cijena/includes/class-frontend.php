<?php
/**
 * Storefront display of sidrena cijena.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Frontend renderer.
 */
class Brendigo_Sidrena_Cijena_Frontend {

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
	 * Register storefront hooks.
	 */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'sidrena_cijena', array( $this, 'render_action' ), 10, 1 );
		add_shortcode( 'sidrena-cijena', array( $this, 'shortcode' ) );
		add_shortcode( 'sidrena_cijena', array( $this, 'shortcode' ) );
		add_shortcode( 'brendigo-sidrena-cijena', array( $this, 'shortcode' ) );
		add_shortcode( 'sidrena-cjenik', array( $this, 'cjenik_shortcode' ) );
		add_shortcode( 'sidrena_cjenik', array( $this, 'cjenik_shortcode' ) );
		add_shortcode( 'brendigo-sidrena-cjenik', array( $this, 'cjenik_shortcode' ) );
		add_shortcode( 'sidrena-arhiva', array( $this, 'arhiva_shortcode' ) );
		add_shortcode( 'sidrena_arhiva', array( $this, 'arhiva_shortcode' ) );
		add_shortcode( 'brendigo-sidrena-arhiva', array( $this, 'arhiva_shortcode' ) );

		// 1.0.x public-page aliases. These keep existing pages working without
		// importing the former history/audit/service data model.
		add_shortcode( 'sidrena_objava_cjenika', array( $this, 'legacy_publication_shortcode' ) );
		add_shortcode( 'sidrena-objava-cjenika', array( $this, 'legacy_publication_shortcode' ) );
		add_shortcode( 'sidrena_cjenici', array( $this, 'legacy_downloads_shortcode' ) );
		add_shortcode( 'sidrena-cjenici', array( $this, 'legacy_downloads_shortcode' ) );
		add_shortcode( 'sidrena_cjenik_url', array( $this, 'legacy_current_file_url_shortcode' ) );
		add_shortcode( 'sidrena-cjenik-url', array( $this, 'legacy_current_file_url_shortcode' ) );
		add_shortcode( 'sidrena_usluge', array( $this, 'cjenik_shortcode' ) );
		add_shortcode( 'sidrena_proizvodi', array( $this, 'cjenik_shortcode' ) );
		add_shortcode( 'sidrena_stavka', array( $this, 'standalone_item_shortcode' ) );

		if ( ! Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ) {
			return;
		}

		if ( Brendigo_Sidrena_Cijena_Settings::instance()->is_auto_display_enabled() ) {
			add_filter( 'woocommerce_get_price_html', array( $this, 'append_to_price_html' ), 9999, 2 );
			add_filter( 'render_block_woocommerce/product-price', array( $this, 'append_to_price_block' ), 20, 2 );
			add_filter( 'elementor/widget/render_content', array( $this, 'append_to_elementor_widget' ), 20, 2 );
			add_filter( 'woocommerce_available_variation', array( $this, 'add_variation_data' ), 10, 3 );
		}
	}

	/**
	 * Frontend CSS / JS.
	 */
	public function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		$woocommerce_auto_display = Brendigo_Sidrena_Cijena_Plugin::woocommerce_active()
			&& Brendigo_Sidrena_Cijena_Settings::instance()->is_auto_display_enabled();

		if ( $woocommerce_auto_display || $this->content_has_price_shortcode() ) {
			$this->enqueue_price_style();
		}

		if ( $this->content_has_cjenik_shortcode() ) {
			$this->enqueue_cjenik_embed_style();
		}

		if ( ! $woocommerce_auto_display ) {
			return;
		}

		wp_enqueue_script(
			'sidrena-cijena',
			BRENDIGO_SIDRENA_CIJENA_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BRENDIGO_SIDRENA_CIJENA_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		$product = $this->current_product();
		wp_localize_script(
			'sidrena-cijena',
			'sidrenaCijenaFront',
			array(
				'html'           => $product ? $this->get_html_for_product( $product ) : '',
				'selectors'      => Brendigo_Sidrena_Cijena_Compat::price_selectors(),
				'watchRoots'     => Brendigo_Sidrena_Cijena_Compat::watch_roots(),
			)
		);
	}

	/**
	 * Append sidrena line after WooCommerce price HTML.
	 *
	 * @param string     $price_html Price HTML.
	 * @param WC_Product $product    Product.
	 * @return string
	 */
	public function append_to_price_html( $price_html, $product ) {
		if ( ! Brendigo_Sidrena_Cijena_Settings::instance()->is_auto_display_enabled() ) {
			return $price_html;
		}

		if ( is_admin() && ! wp_doing_ajax() && ! $this->is_elementor_preview() ) {
			return $price_html;
		}

		if ( ! $product instanceof WC_Product ) {
			return $price_html;
		}

		if ( '' === $price_html ) {
			return $price_html;
		}

		if ( false !== strpos( $price_html, 'sidrena-cijena' ) ) {
			return $price_html;
		}

		$html = $this->get_html_for_product( $product );
		if ( '' === $html ) {
			return $price_html;
		}

		return Brendigo_Sidrena_Cijena_Compat::merge_price_html( $price_html, $html );
	}

	/**
	 * Append sidrena line inside Elementor / AnyWhere Elementor product price widgets.
	 *
	 * @param string                 $content Widget HTML.
	 * @param \Elementor\Widget_Base $widget  Widget instance.
	 * @return string
	 */
	public function append_to_elementor_widget( $content, $widget ) {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) ) {
			return $content;
		}

		if ( 'woocommerce-product-price' !== $widget->get_name() ) {
			return $content;
		}

		if ( '' === $content || false !== strpos( $content, 'sidrena-cijena' ) ) {
			return $content;
		}

		$html = $this->get_html_for_context();
		if ( '' === $html ) {
			return $content;
		}

		return Brendigo_Sidrena_Cijena_Compat::merge_price_html( $content, $html );
	}

	/**
	 * Inject sidrena HTML into variation JSON.
	 *
	 * @param array                $data      Variation payload.
	 * @param WC_Product_Variable  $product   Parent.
	 * @param WC_Product_Variation $variation Variation.
	 * @return array
	 */
	public function add_variation_data( $data, $product, $variation ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$html                         = $this->get_html_for_product( $variation );
		$data['sidrena_cijena_html'] = $html;

		if ( '' === $html ) {
			return $data;
		}

		$price_html = isset( $data['price_html'] ) ? (string) $data['price_html'] : '';
		if ( false !== strpos( $price_html, 'sidrena-cijena' ) ) {
			return $data;
		}

		/*
		 * WooCommerce leaves price_html empty when every remaining in-stock
		 * variation has the same price. Selecting 1000 ml after 200 ml is out
		 * of stock then rendered an empty variation price, so the sidrena line
		 * never appeared. The variation's own price HTML already includes it.
		 */
		if ( '' === trim( $price_html ) ) {
			$price_html = $variation->get_price_html();
		}

		if ( '' === trim( (string) $price_html ) ) {
			$data['price_html'] = $html;
			return $data;
		}

		if ( false === strpos( $price_html, 'sidrena-cijena' ) ) {
			$price_html = Brendigo_Sidrena_Cijena_Compat::merge_price_html( $price_html, $html );
		}

		$data['price_html'] = $price_html;

		return $data;
	}

	/**
	 * Append sidrena HTML to the WooCommerce product-price block when missing.
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Block data.
	 * @return string
	 */
	public function append_to_price_block( $content, $block ) {
		if ( '' === $content || false !== strpos( $content, 'sidrena-cijena' ) ) {
			return $content;
		}

		$product = $this->product_from_block( $block );
		if ( ! $product ) {
			return $content;
		}

		return Brendigo_Sidrena_Cijena_Compat::merge_price_html( $content, $this->get_html_for_product( $product ) );
	}

	/**
	 * Universal theme hook: do_action( 'sidrena_cijena' ) or do_action( 'sidrena_cijena', $product ).
	 *
	 * @param WC_Product|int|null $product Product, ID, or current product.
	 */
	public function render_action( $product = null ) {
		echo wp_kses_post( $this->get_html_for_context( $product ) );
	}

	/**
	 * Resolve a product from the current context and return HTML.
	 *
	 * @param WC_Product|int|null $product Product, ID, or null.
	 * @return string
	 */
	public function get_html_for_context( $product = null ) {
		if ( Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() && $product instanceof WC_Product ) {
			return $this->get_html_for_product( $product );
		}

		if ( is_numeric( $product ) && $product ) {
			if ( Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ) {
				$resolved = wc_get_product( $product );
				if ( $resolved instanceof WC_Product && $this->explicit_product_allowed( $resolved ) ) {
					return $this->get_html_for_product( $resolved );
				}

				if ( $resolved instanceof WC_Product ) {
					return '';
				}
			}

			$row = Brendigo_Sidrena_Cijena_Catalog::instance()->get( $product );
			return $row ? $this->get_html_for_catalog_row( $row ) : '';
		}

		$resolved = $this->current_product();
		return $resolved ? $this->get_html_for_product( $resolved ) : '';
	}

	/**
	 * Current product from WooCommerce globals or the queried object.
	 *
	 * @return WC_Product|null
	 */
	private function current_product() {
		global $product;

		if ( $product instanceof WC_Product ) {
			return $product;
		}

		if ( function_exists( 'wc_get_product' ) ) {
			$resolved = wc_get_product( get_the_ID() );
			if ( $resolved instanceof WC_Product ) {
				return $resolved;
			}
		}

		return null;
	}

	/**
	 * Elementor editor / preview request.
	 *
	 * @return bool
	 */
	private function is_elementor_preview() {
		if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		return isset( $_GET['action'] ) && 'elementor' === sanitize_key( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Product from a block context.
	 *
	 * @param array $block Block data.
	 * @return WC_Product|null
	 */
	private function product_from_block( $block ) {
		$product_id = 0;

		if ( ! empty( $block['attrs']['productId'] ) ) {
			$product_id = absint( $block['attrs']['productId'] );
		}

		if ( ! $product_id && ! empty( $block['context']['postId'] ) ) {
			$product_id = absint( $block['context']['postId'] );
		}

		if ( ! $product_id ) {
			$product_id = get_the_ID();
		}

		$product = $product_id ? wc_get_product( $product_id ) : null;
		return $product instanceof WC_Product ? $product : null;
	}

	/**
	 * [sidrena-cjenik] prints the searchable pricelist table.
	 *
	 * @param array<string, mixed> $atts Attributes.
	 * @return string
	 */
	public function cjenik_shortcode( $atts, $content = null, $shortcode_tag = '' ) {
		$atts = shortcode_atts(
			array(
				'oznaka' => '',
				'vrsta'  => '',
			),
			$atts,
			'sidrena-cjenik'
		);

		$this->enqueue_cjenik_embed_style();
		$kind = sanitize_key( (string) $atts['vrsta'] );
		// Earlier sidrena_usluge incorrectly showed products and services together.
		if ( 'sidrena_usluge' === $shortcode_tag ) {
			$kind = 'usluge';
		} elseif ( 'sidrena_proizvodi' === $shortcode_tag ) {
			$kind = 'proizvodi';
		}
		if ( in_array( $kind, array( 'proizvodi', 'usluge' ), true ) ) {
			return $this->separate_cjenik_shortcode( $kind, (string) $atts['oznaka'] );
		}
		return Brendigo_Sidrena_Cijena_Export::instance()->get_cjenik_embed_html( (string) $atts['oznaka'] );
	}

	/** Render the already-published, type-specific HTML fragment (no live database query). */
	private function separate_cjenik_shortcode( $kind, $oznaka ) {
		static $counter = 0;
		$oznaka = Brendigo_Sidrena_Cijena_Settings::instance()->normalize_oznaka( $oznaka );
		if ( '' !== $oznaka && ! Brendigo_Sidrena_Cijena_Poslovnice::instance()->get_by_oznaka( $oznaka ) ) {
			return '';
		}
		$files = Brendigo_Sidrena_Cijena_Feed_Files::instance();
		$path = $files->get_type_html_path( $kind, $oznaka );
		if ( ! $path || ! Brendigo_Sidrena_Cijena_Filesystem::is_file( $path ) ) {
			Brendigo_Sidrena_Cijena_Export::instance()->queue_public_xml( 5, 'missing-separate-feed' );
			return '<p class="sidrena-cjenik-pending">' . esc_html__( 'Cjenik se priprema.', 'brendigo-sidrena-cijena' ) . '</p>';
		}
		$html = Brendigo_Sidrena_Cijena_Filesystem::get_contents( $path );
		if ( ! is_string( $html ) || '' === $html ) {
			return '';
		}
		$counter++;
		$html = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $html );
		return str_replace( Brendigo_Sidrena_Cijena_Export::CJENIK_DOM_TOKEN, 'sidrena-cjenik-odvojeni-' . $counter, $html );
	}

	/**
	 * [sidrena-arhiva] prints the archive file names and dates.
	 *
	 * @param array<string, mixed> $atts Attributes.
	 * @return string
	 */
	public function arhiva_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'oznaka' => '',
			),
			$atts,
			'sidrena-arhiva'
		);

		$this->enqueue_archive_embed_style();

		return Brendigo_Sidrena_Cijena_Export::instance()->get_archive_embed_html( (string) $atts['oznaka'] );
	}


	/**
	 * Compatibility renderer for the 1.0.x [sidrena_objava_cjenika] page.
	 *
	 * @param array<string,mixed> $atts Attributes.
	 * @return string
	 */
	public function legacy_publication_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'oznaka'   => '',
				'lokacija' => '',
			),
			$atts,
			'sidrena_objava_cjenika'
		);

		$oznaka = '' !== trim( (string) $atts['oznaka'] ) ? (string) $atts['oznaka'] : (string) $atts['lokacija'];
		$table  = $this->cjenik_shortcode( array( 'oznaka' => $oznaka ) );
		$links  = $this->legacy_downloads_shortcode( array( 'oznaka' => $oznaka ) );
		$archive = $this->arhiva_shortcode( array( 'oznaka' => $oznaka ) );

		return '<div class="brendigo-sidrena-publication">' . $table . $links . $archive . '</div>';
	}

	/**
	 * Compatibility renderer for the 1.0.x download-list shortcode.
	 *
	 * @param array<string,mixed> $atts Attributes.
	 * @return string
	 */
	public function legacy_downloads_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'oznaka'   => '',
				'lokacija' => '',
			),
			$atts,
			'sidrena_cjenici'
		);

		$oznaka = '' !== trim( (string) $atts['oznaka'] ) ? (string) $atts['oznaka'] : (string) $atts['lokacija'];
		$export = Brendigo_Sidrena_Cijena_Export::instance();
		$file_url = $export->get_public_url( '', $oznaka );
		$archive_url = $export->get_archive_url();

		if ( '' === $file_url ) {
			return '';
		}

		$html  = '<div class="brendigo-sidrena-downloads">';
		$html .= '<a class="button" href="' . esc_url( $file_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Otvori aktualni cjenik', 'brendigo-sidrena-cijena' ) . '</a>';
		if ( '' !== $archive_url ) {
			$html .= ' <a class="button" href="' . esc_url( $archive_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Otvori arhivu', 'brendigo-sidrena-cijena' ) . '</a>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Compatibility for the 1.0.x shortcode that returned the current file URL.
	 *
	 * @param array<string,mixed> $atts Attributes.
	 * @return string
	 */
	public function legacy_current_file_url_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'oznaka'   => '',
				'lokacija' => '',
			),
			$atts,
			'sidrena_cjenik_url'
		);
		$oznaka = '' !== trim( (string) $atts['oznaka'] ) ? (string) $atts['oznaka'] : (string) $atts['lokacija'];
		return esc_url( Brendigo_Sidrena_Cijena_Export::instance()->get_public_url( '', $oznaka ) );
	}

	/**
	 * Whether the current post contains a reference-price shortcode.
	 *
	 * @return bool
	 */
	private function content_has_price_shortcode() {
		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'sidrena_graf_cijena' )
			|| has_shortcode( $post->post_content, 'sidrena_povijest_cijena' )
			|| has_shortcode( $post->post_content, 'sidrena_najniza_prije_snizenja' )
			|| has_shortcode( $post->post_content, 'sidrena_stavka' )
			|| has_shortcode( $post->post_content, 'sidrena-cijena' )
			|| has_shortcode( $post->post_content, 'sidrena_cijena' )
			|| has_shortcode( $post->post_content, 'brendigo-sidrena-cijena' );
	}

	/**
	 * Whether the current post contains the table shortcode.
	 *
	 * @return bool
	 */
	private function content_has_cjenik_shortcode() {
		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'sidrena-cjenik' )
			|| has_shortcode( $post->post_content, 'sidrena_cjenik' )
			|| has_shortcode( $post->post_content, 'sidrena_objava_cjenika' )
			|| has_shortcode( $post->post_content, 'sidrena-objava-cjenika' )
			|| has_shortcode( $post->post_content, 'sidrena_usluge' );
	}

	/**
	 * Reference-price stylesheet, loaded only when the current request renders it.
	 */
	private function enqueue_price_style() {
		$handle = 'sidrena-cijena';
		if ( ! wp_style_is( $handle, 'registered' ) ) {
			wp_register_style(
				$handle,
				BRENDIGO_SIDRENA_CIJENA_URL . 'assets/css/frontend.css',
				array(),
				BRENDIGO_SIDRENA_CIJENA_VERSION
			);
		}

		wp_enqueue_style( $handle );

		if ( did_action( 'wp_head' ) && ! wp_style_is( $handle, 'done' ) ) {
			wp_print_styles( $handle );
		}
	}

	/** Let shortcodes rendered outside post content load their chart stylesheet. */
	public function ensure_history_style() {
		$this->enqueue_price_style();
	}

	/**
	 * Table stylesheet. Printed in place when the shortcode runs after wp_head.
	 */
	private function enqueue_cjenik_embed_style() {
		$handle = 'sidrena-cijena-cjenik-embed';
		if ( ! wp_style_is( $handle, 'registered' ) ) {
			wp_register_style(
				$handle,
				BRENDIGO_SIDRENA_CIJENA_URL . 'assets/css/cjenik-embed.css',
				array(),
				BRENDIGO_SIDRENA_CIJENA_VERSION
			);
		}

		wp_enqueue_style( $handle );

		if ( did_action( 'wp_head' ) && ! wp_style_is( $handle, 'done' ) ) {
			wp_print_styles( $handle );
		}

		$script_handle = 'sidrena-cijena-cjenik';
		if ( ! wp_script_is( $script_handle, 'registered' ) ) {
			wp_register_script(
				$script_handle,
				BRENDIGO_SIDRENA_CIJENA_URL . 'assets/js/cjenik.js',
				array(),
				BRENDIGO_SIDRENA_CIJENA_VERSION,
				true
			);
		}
		wp_enqueue_script( $script_handle );
	}

	/**
	 * Archive list stylesheet. Printed in place when the shortcode runs after wp_head.
	 */
	private function enqueue_archive_embed_style() {
		$handle = 'sidrena-cijena-arhiva-embed';
		if ( ! wp_style_is( $handle, 'registered' ) ) {
			wp_register_style(
				$handle,
				BRENDIGO_SIDRENA_CIJENA_URL . 'assets/css/archive-embed.css',
				array(),
				BRENDIGO_SIDRENA_CIJENA_VERSION
			);
		}

		wp_enqueue_style( $handle );

		if ( did_action( 'wp_head' ) && ! wp_style_is( $handle, 'done' ) ) {
			wp_print_styles( $handle );
		}
	}

	/**
	 * [sidrena-cijena] shortcode. Woo product loops, or standalone catalog id="123".
	 *
	 * @param array<string, mixed> $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'     => 0,
				'prikaz' => '',
			),
			$atts,
			'sidrena-cijena'
		);

		$prikaz = $this->normalize_prikaz( isset( $atts['prikaz'] ) ? $atts['prikaz'] : '' );
		$raw_id = isset( $atts['id'] ) ? trim( (string) $atts['id'] ) : '';
		if ( preg_match( '/^d(\d+)$/i', $raw_id, $match ) ) {
			$row = Brendigo_Sidrena_Cijena_Catalog::instance()->get( $match[1] );
			if ( ! $row ) {
				return '';
			}

			return '' !== $prikaz ? $this->catalog_plain_amount( $row, $prikaz ) : $this->get_html_for_catalog_row( $row );
		}

		$item_id = absint( $raw_id );

		if ( Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ) {
			if ( $item_id ) {
				$product = wc_get_product( $item_id );
			} else {
				$product = $this->current_product();
				if ( ! $product ) {
					$product = wc_get_product( get_the_ID() );
				}
			}

			if ( $product instanceof WC_Product ) {
				if ( $item_id && ! $this->explicit_product_allowed( $product ) ) {
					return '';
				}

				return '' !== $prikaz ? $this->product_plain_amount( $product, $prikaz ) : $this->get_html_for_product( $product );
			}
		}

		if ( ! $item_id ) {
			return '';
		}

		$row = Brendigo_Sidrena_Cijena_Catalog::instance()->get( $item_id );
		if ( ! $row ) {
			return '';
		}

		return '' !== $prikaz ? $this->catalog_plain_amount( $row, $prikaz ) : $this->get_html_for_catalog_row( $row );
	}

	/**
	 * Display an individually managed catalog product/service without WooCommerce.
	 * Usage: [sidrena_stavka id="123"] (or d123 when WooCommerce is enabled).
	 */
	public function standalone_item_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => '', 'link' => 'yes' ), $atts, 'sidrena_stavka' );
		$raw = trim( (string) $atts['id'] );
		if ( ! preg_match( '/^(?:d)?([0-9]+)$/iD', $raw, $matches ) ) {
			return '';
		}
		$row = Brendigo_Sidrena_Cijena_Catalog::instance()->get( $matches[1] );
		if ( ! $row || '' === $row['naziv'] ) {
			return '';
		}
		$this->enqueue_price_style();
		$name = esc_html( $row['naziv'] );
		$url = isset( $row['url'] ) ? esc_url( $row['url'] ) : '';
		if ( 'yes' === $atts['link'] && '' !== $url ) {
			$name = '<a href="' . $url . '" rel="noopener">' . $name . '</a>';
		}
		$kind = 'usluga' === Brendigo_Sidrena_Cijena_Catalog::instance()->row_vrsta( $row )
			? __( 'Usluga', 'brendigo-sidrena-cijena' )
			: __( 'Proizvod', 'brendigo-sidrena-cijena' );
		$prices = $this->get_html_for_catalog_row( $row );
		if ( '' === $prices ) {
			// Catalog items without a documented sidrena price may still have a current price.
			$prices = $this->catalog_current_price_html( $row );
		}
		return '<div class="sidrena-standalone-item"><span class="sidrena-standalone-item__kind">' . esc_html( $kind ) . '</span>'
			. '<strong class="sidrena-standalone-item__name">' . $name . '</strong>'
			. '<div class="sidrena-standalone-item__prices">' . wp_kses_post( $prices ) . '</div></div>';
	}

	/**
	 * Allowed prikaz values. Empty keeps the full price block.
	 *
	 * @param mixed $value Raw attribute.
	 * @return string redovna|akcijska|sidrena|''
	 */
	private function normalize_prikaz( $value ) {
		$value = sanitize_key( (string) $value );

		return in_array( $value, array( 'redovna', 'akcijska', 'sidrena' ), true ) ? $value : '';
	}

	/**
	 * One catalog amount, without label or tooltip.
	 *
	 * @param array<string, string> $row    Catalog row.
	 * @param string                $prikaz redovna|akcijska|sidrena.
	 * @return string
	 */
	private function catalog_plain_amount( $row, $prikaz ) {
		$keys = array(
			'redovna'  => 'cijena_redovna',
			'akcijska' => 'cijena_akcijska',
			'sidrena'  => 'sidrena_cijena',
		);
		$raw  = isset( $row[ $keys[ $prikaz ] ] ) ? trim( (string) $row[ $keys[ $prikaz ] ] ) : '';
		if ( '' === $raw ) {
			return '';
		}

		return esc_html( Brendigo_Sidrena_Cijena_Catalog::instance()->format_amount( $raw ) );
	}

	/**
	 * One WooCommerce amount, without label or tooltip.
	 *
	 * @param WC_Product $product Product or variation.
	 * @param string     $prikaz  redovna|akcijska|sidrena.
	 * @return string
	 */
	private function product_plain_amount( $product, $prikaz ) {
		if ( ! $product instanceof WC_Product || ! $this->viewer_may_see_product( $product ) || $product->is_type( 'grouped' ) ) {
			return '';
		}

		if ( $product->is_type( 'variable' ) ) {
			return $this->variable_plain_amount( $product, $prikaz );
		}

		$amount = $this->product_display_amount( $product, $prikaz );
		if ( null === $amount ) {
			return '';
		}

		return $this->format_plain_price( $amount );
	}

	/**
	 * Lowest variation amount. A range is prefixed with "od".
	 *
	 * @param WC_Product $product Variable product.
	 * @param string     $prikaz  redovna|akcijska|sidrena.
	 * @return string
	 */
	private function variable_plain_amount( $product, $prikaz ) {
		$child_ids = method_exists( $product, 'get_visible_children' ) ? $product->get_visible_children() : $product->get_children();
		$amounts   = array();

		foreach ( $child_ids as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->is_purchasable() || ! $this->viewer_may_see_product( $variation ) ) {
				continue;
			}

			$display = $this->product_display_amount( $variation, $prikaz );
			if ( null === $display ) {
				continue;
			}

			$amounts[] = $display;
		}

		if ( empty( $amounts ) ) {
			return '';
		}

		$min  = min( $amounts );
		$text = $this->format_plain_price( $min );
		if ( $min === max( $amounts ) ) {
			return $text;
		}

		return sprintf(
			/* translators: %s formatted price */
			esc_html__( 'od %s', 'brendigo-sidrena-cijena' ),
			$text
		);
	}

	/**
	 * Display amount for one simple product or variation.
	 *
	 * @param WC_Product $product Product or variation.
	 * @param string     $prikaz  redovna|akcijska|sidrena.
	 * @return float|null
	 */
	private function product_display_amount( $product, $prikaz ) {
		if ( 'sidrena' === $prikaz ) {
			$meta = Brendigo_Sidrena_Cijena_Product_Meta::instance();
			$raw  = $meta->get_price( $meta->display_id( $product->get_id() ) );
			if ( '' === $raw ) {
				return null;
			}

			return $meta->get_price_to_display( $product, $raw );
		}

		$raw = 'akcijska' === $prikaz ? (string) $product->get_sale_price() : (string) $product->get_regular_price();
		if ( '' === trim( $raw ) ) {
			return null;
		}

		if ( function_exists( 'wc_get_price_to_display' ) ) {
			return (float) wc_get_price_to_display(
				$product,
				array(
					'price' => (float) $raw,
				)
			);
		}

		return (float) $raw;
	}

	/**
	 * Shop-formatted amount as plain text.
	 *
	 * @param float $amount Display amount.
	 * @return string
	 */
	private function format_plain_price( $amount ) {
		if ( function_exists( 'wc_price' ) ) {
			$text = html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );

			return esc_html( trim( $text ) );
		}

		return esc_html( Brendigo_Sidrena_Cijena_Catalog::instance()->format_amount( (string) $amount ) );
	}

	/**
	 * Sidrena line plus current price for a standalone catalog row.
	 *
	 * @param array<string, string> $row Catalog row.
	 * @return string
	 */
	public function get_html_for_catalog_row( $row ) {
		if ( ! is_array( $row ) || '' === (string) $row['sidrena_cijena'] ) {
			return '';
		}

		$settings = Brendigo_Sidrena_Cijena_Settings::instance();
		$catalog  = Brendigo_Sidrena_Cijena_Catalog::instance();
		$line     = $this->render_line(
			$settings->label_for_catalog_row( $row ),
			esc_html( $catalog->format_amount( $row['sidrena_cijena'] ) ),
			$settings->is_tooltip_enabled() ? $settings->get_translated( 'tooltip' ) : '',
			true
		);

		$price_html = $this->catalog_current_price_html( $row );
		if ( '' === $price_html ) {
			return $line;
		}

		return '<span class="sidrena-cijena-item">' . $price_html . $line . '</span>';
	}

	/**
	 * Regular / sale amount for a catalog row (plain formatted euros).
	 *
	 * @param array<string, string> $row Catalog row.
	 * @return string
	 */
	private function catalog_current_price_html( $row ) {
		$catalog = Brendigo_Sidrena_Cijena_Catalog::instance();
		$regular = isset( $row['cijena_redovna'] ) ? (string) $row['cijena_redovna'] : '';
		$sale    = isset( $row['cijena_akcijska'] ) ? (string) $row['cijena_akcijska'] : '';

		if ( '' === $regular && '' === $sale ) {
			return '';
		}

		if ( '' !== $sale && $sale !== $regular ) {
			$html = '<span class="sidrena-cijena-item__price">';
			if ( '' !== $regular ) {
				$html .= '<del>' . esc_html( $catalog->format_amount( $regular ) ) . '</del> ';
			}
			$html .= '<ins>' . esc_html( $catalog->format_amount( $sale ) ) . '</ins>';
			$html .= '</span>';

			return $html;
		}

		$amount = '' !== $sale ? $sale : $regular;

		return '<span class="sidrena-cijena-item__price">' . esc_html( $catalog->format_amount( $amount ) ) . '</span>';
	}

	/**
	 * Render markup for a product or variation.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function get_html_for_product( $product ) {
		if ( ! $product instanceof WC_Product || ! $this->viewer_may_see_product( $product ) ) {
			return '';
		}

		$settings = Brendigo_Sidrena_Cijena_Settings::instance();
		$meta     = Brendigo_Sidrena_Cijena_Product_Meta::instance();

		if ( $product->is_type( 'grouped' ) ) {
			return '';
		}

		if ( $product->is_type( 'variable' ) ) {
			return $this->get_html_for_variable( $product );
		}

		$stored_id = $meta->display_id( $product->get_id() );
		$price     = $meta->get_price( $stored_id );
		if ( '' === $price ) {
			return '';
		}

		return $this->render_line(
			$meta->get_display_prefix( $stored_id ),
			$meta->format_price_html( $product, $price ),
			$settings->is_tooltip_enabled() ? $settings->get_translated( 'tooltip' ) : '',
			true
		);
	}

	/**
	 * Variable parent: show a single value or "od …" until a variation is chosen.
	 *
	 * @param WC_Product_Variable $product Parent product.
	 * @return string
	 */
	private function get_html_for_variable( $product ) {
		$settings = Brendigo_Sidrena_Cijena_Settings::instance();
		$meta     = Brendigo_Sidrena_Cijena_Product_Meta::instance();
		$prices = array();
		$prefix = $meta->get_display_prefix( $meta->display_id( $product->get_id() ) );
		$child_ids = method_exists( $product, 'get_visible_children' ) ? $product->get_visible_children() : $product->get_children();

		foreach ( $child_ids as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->is_purchasable() || ! $this->viewer_may_see_product( $variation ) ) {
				continue;
			}

			$stored_id = $meta->display_id( $variation_id );
			$value     = $meta->get_price( $stored_id );
			if ( '' === $value ) {
				continue;
			}

			$display = $meta->get_price_to_display( $variation, $value );
			if ( null === $display ) {
				continue;
			}

			$prices[] = $display;
			$prefix   = $meta->get_display_prefix( $stored_id );
		}

		if ( empty( $prices ) ) {
			return '';
		}

		$min    = min( $prices );
		$max    = max( $prices );
		$amount = wc_price( $min );

		if ( $min !== $max ) {
			$from = sprintf(
				/* translators: %s formatted price */
				__( 'od %s', 'brendigo-sidrena-cijena' ),
				wc_price( $min )
			);
			$amount = $from;
		}

		return $this->render_line(
			$prefix,
			$amount,
			$settings->is_tooltip_enabled() ? $settings->get_translated( 'tooltip' ) : '',
			true,
			true
		);
	}

	/**
	 * Draft and private products stay hidden from people who cannot edit them.
	 * A password-protected product stays hidden until the visitor unlocks it.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return bool
	 */
	private function viewer_may_see_product( $product ) {
		$ids = array( (int) $product->get_id() );
		if ( $product->get_parent_id() ) {
			$ids[] = (int) $product->get_parent_id();
		}

		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post instanceof WP_Post ) {
				return false;
			}

			if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $id ) ) {
				return false;
			}

			if ( post_password_required( $post ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * An explicit ID may be rendered off its own page only when the product is not catalog-hidden.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return bool
	 */
	private function explicit_product_allowed( $product ) {
		if ( $this->is_queried_product( $product ) ) {
			return true;
		}

		$subject = $product;
		if ( $product->get_parent_id() ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent instanceof WC_Product ) {
				$subject = $parent;
			}
		}

		return 'hidden' !== $subject->get_catalog_visibility();
	}

	/**
	 * Whether this product (or its parent) is the current singular view.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return bool
	 */
	private function is_queried_product( $product ) {
		$current_id = (int) get_queried_object_id();
		if ( $current_id < 1 ) {
			return false;
		}

		return $current_id === (int) $product->get_id() || $current_id === (int) $product->get_parent_id();
	}

	/**
	 * Shared markup.
	 *
	 * @param string $prefix        Label including date.
	 * @param mixed  $price         Raw number or already-formatted HTML.
	 * @param string $tooltip       Tooltip text.
	 * @param bool   $price_is_html Whether $price is already HTML.
	 * @param bool   $is_variable   Extra class for JS replacement.
	 * @return string
	 */
	private function render_line( $prefix, $price, $tooltip, $price_is_html = false, $is_variable = false ) {
		if ( ! is_admin() ) {
			$this->enqueue_price_style();
		}

		$formatted = $price_is_html ? (string) $price : wc_price( $price );
		$classes   = 'sidrena-cijena';
		if ( $is_variable ) {
			$classes .= ' sidrena-cijena--variable';
		}

		$has_tooltip = '' !== trim( (string) $tooltip );
		if ( $has_tooltip ) {
			$classes .= ' sidrena-cijena--has-tooltip';
		}

		$separator = Brendigo_Sidrena_Cijena_Settings::instance()->preview_separator( false );
		$tooltip_id = '';
		if ( $has_tooltip ) {
			static $tooltip_instance = 0;
			$tooltip_instance++;
			$tooltip_id = 'sidrena-cijena-tooltip-' . $tooltip_instance;
		}

		ob_start();
		?>
		<span class="<?php echo esc_attr( $classes ); ?>"<?php if ( $has_tooltip ) : ?> tabindex="0" aria-describedby="<?php echo esc_attr( $tooltip_id ); ?>"<?php endif; ?>>
			<span class="sidrena-cijena__text">
				<span class="sidrena-cijena__prefix"><?php echo esc_html( $prefix ); ?></span>
				<span class="sidrena-cijena__sep"><?php echo esc_html( $separator ); ?></span>
				<span class="sidrena-cijena__amount"><?php echo wp_kses_post( $formatted ); ?></span>
			</span>
			<?php if ( $has_tooltip ) : ?>
				<span id="<?php echo esc_attr( $tooltip_id ); ?>" class="sidrena-cijena__tooltip" role="tooltip"><?php echo esc_html( $tooltip ); ?></span>
			<?php endif; ?>
		</span>
		<?php
		return (string) ob_get_clean();
	}
}
