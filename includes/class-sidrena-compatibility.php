<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

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
		add_filter( 'fl_builder_render_module_content', array( $this, 'filter_beaver' ), 25, 2 );
		add_filter( 'bricks/element/render', array( $this, 'filter_bricks' ), 25, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_script' ), 30 );
	}

	public function filter_block( $content, $block ) {
		$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		$block_match = false !== strpos( $name, 'woocommerce/product-price' ) || false !== strpos( $name, 'product-price' ) || false !== strpos( $name, 'wc-product-price' );
		if ( ! $block_match ) {
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
			'ae-woo-price',
			'jet-woo-builder-archive-product-price',
			'woocommerce-price',
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
		if ( ! in_array( $tag, array( 'product_price', 'woocommerce_product_price', 'woodmart_product_price' ), true ) ) {
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


	public function filter_beaver( $content, $module ) {
		$slug = is_object( $module ) && isset( $module->slug ) ? strtolower( (string) $module->slug ) : '';
		if ( false === strpos( $slug, 'woo' ) || false === strpos( $slug, 'price' ) ) {
			return $content;
		}
		return $this->append_if_needed( $content );
	}

	public function filter_bricks( $content, $element ) {
		$name = '';
		if ( is_object( $element ) ) {
			if ( isset( $element->name ) ) {
				$name = strtolower( (string) $element->name );
			} elseif ( is_callable( array( $element, 'get_name' ) ) ) {
				$name = strtolower( (string) $element->get_name() );
			}
		}
		if ( false === strpos( $name, 'product-price' ) && false === strpos( $name, 'woocommerce' ) ) {
			return $content;
		}
		return $this->append_if_needed( $content );
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
					'.entry-summary .price',
					'.single-product-summary .price',
					'.elementor-widget-woocommerce-product-price .price',
					'.elementor-widget-woocommerce-product-price',
					'.elementor-widget-jet-single-price .price',
					'.jet-woo-product-price .price',
					'.jet-single-price .price',
					'.shopengine-product-price .price',
					'.oxy-product-price .price',
					'.oxy-woo-element .price',
					'.et_pb_wc_price .price',
					'.et_pb_module.et_pb_wc_price p.price',
					'.brxe-product-price .price',
					'.brxe-woocommerce-product-price .price',
					'.fl-module-woocommerce-product-price .price',
					'.fl-woo-price .price',
					'.bde-woocommerce-product-price .price',
					'.breakdance-woocommerce-product-price .price',
					'.brz-woo-price .price',
					'.fusion-tb-woo-price .price',
					'.fusion-woo-price-tb .price',
					'.wd-single-price .price',
					'.wd-product-price .price',
					'.product-page-price',
					'.product-info .price-wrapper .price',
					'.wpb_wrapper > p.price',
					'.vc_woo_product_price .price',
					'.wc-block-components-product-price',
					'.wp-block-woocommerce-product-price .price',
				),
			)
		);
	}
}
