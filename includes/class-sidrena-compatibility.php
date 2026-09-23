<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sidrena_Compatibility {
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

		add_filter( 'render_block', array( $this, 'filter_block' ), 25, 2 );
		add_filter( 'elementor/widget/render_content', array( $this, 'filter_elementor' ), 25, 2 );
		add_filter( 'do_shortcode_tag', array( $this, 'filter_shortcode' ), 25, 4 );
		add_filter( 'et_module_shortcode_output', array( $this, 'filter_divi' ), 25, 2 );
		add_filter( 'fusion_element_woo_price_content', array( $this, 'filter_generic_price_output' ), 25, 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_script' ), 30 );
	}

	public function filter_block( $content, $block ) {
		$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		if ( false === strpos( $name, 'woocommerce/product-price' ) && false === strpos( $name, 'woocommerce/product-price-' ) ) {
			return $content;
		}

		$product = null;
		if ( ! empty( $block['attrs']['productId'] ) ) {
			$product = wc_get_product( absint( $block['attrs']['productId'] ) );
		}
		return $this->append_if_needed( $content, $product );
	}

	public function filter_elementor( $content, $widget ) {
		if ( ! is_object( $widget ) || ! is_callable( array( $widget, 'get_name' ) ) ) {
			return $content;
		}
		$name = strtolower( (string) $widget->get_name() );
		$allowed = array(
			'woocommerce-product-price',
			'product-price',
			'jet-single-price',
			'shopengine-product-price',
			'archive-products',
		);
		$match = false;
		foreach ( $allowed as $needle ) {
			if ( false !== strpos( $name, $needle ) ) {
				$match = true;
				break;
			}
		}
		return $match ? $this->append_if_needed( $content ) : $content;
	}

	public function filter_shortcode( $output, $tag, $attr, $m ) {
		unset( $attr, $m );
		$tag = strtolower( (string) $tag );
		if ( ! in_array( $tag, array( 'product_price', 'woocommerce_product_price' ), true ) ) {
			return $output;
		}
		return $this->append_if_needed( $output );
	}

	public function filter_divi( $output, $module_slug ) {
		if ( false === strpos( strtolower( (string) $module_slug ), 'wc_price' ) ) {
			return $output;
		}
		return $this->append_if_needed( $output );
	}

	public function filter_generic_price_output( $output ) {
		return $this->append_if_needed( $output );
	}

	private function append_if_needed( $content, $product = null ) {
		if ( false !== strpos( (string) $content, 'sidrena-reference-prices' ) ) {
			return $content;
		}

		if ( ! $product instanceof WC_Product ) {
			global $product;
			if ( ! $product instanceof WC_Product ) {
				$id = get_queried_object_id();
				$product = $id ? wc_get_product( $id ) : null;
			}
		}

		if ( ! $product instanceof WC_Product ) {
			return $content;
		}

		$extra = Sidrena_Products::instance()->shortcode( array( 'id' => $product->get_id() ) );
		return $extra ? $content . $extra : $content;
	}

	public function frontend_script() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$product_id = get_queried_object_id();
		if ( ! $product_id ) {
			return;
		}

		wp_enqueue_script( 'sidrena-compat', SIDRENA_URL . 'public/js/compat.js', array(), SIDRENA_VERSION, true );
		wp_localize_script(
			'sidrena-compat',
			'SidrenaCompat',
			array(
				'productId' => absint( $product_id ),
				'endpoint'  => esc_url_raw( rest_url( 'sidrena/v1/display/' ) ),
				'selectors' => array(
					'.woocommerce-variation-price .price',
					'.summary .price',
					'.elementor-widget-woocommerce-product-price .price',
					'.elementor-widget-woocommerce-product-price',
					'.oxy-product-price .price',
					'.wpb_wrapper > p.price',
					'.fl-module-woocommerce .price',
					'.et_pb_wc_price .price',
					'.brxe-product-price .price',
					'.shopengine-product-price .price',
					'.jet-woo-product-price',
					'.wd-single-price',
					'.product-page-price',
					'.fusion-woo-price',
					'.wc-block-components-product-price',
				),
			)
		);
	}
}
