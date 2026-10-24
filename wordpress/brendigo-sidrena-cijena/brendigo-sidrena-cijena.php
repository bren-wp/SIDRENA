<?php
/**
 * Plugin Name: SIDRENA – Sidrene cijene i cjenici
 * Plugin URI: https://brendigo.com/sidrene-cijene/
 * Description: Sidrene cijene i javni HTML/XML/CSV cjenici s arhivom za WordPress, uz dodatnu WooCommerce integraciju.
 * Version: 1.5.2
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 11.1.2
 * Author: Brendigo
 * Author URI: https://brendigo.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: brendigo-sidrena-cijena
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'BRENDIGO_SIDRENA_CIJENA_VERSION', '1.5.2' );
define( 'BRENDIGO_SIDRENA_CIJENA_FILE', __FILE__ );
define( 'BRENDIGO_SIDRENA_CIJENA_PATH', plugin_dir_path( __FILE__ ) );
define( 'BRENDIGO_SIDRENA_CIJENA_URL', plugin_dir_url( __FILE__ ) );
define( 'BRENDIGO_SIDRENA_CIJENA_META_PRICE', '_sidrena_cijena' );
define( 'BRENDIGO_SIDRENA_CIJENA_META_DATE', '_sidrena_cijena_datum' );
define( 'BRENDIGO_SIDRENA_CIJENA_META_CJM', '_sidrena_cijena_cjm' );
define( 'BRENDIGO_SIDRENA_CIJENA_META_CJM_SUFFIX', '_sidrena_cijena_cjm_suffix' );
define( 'BRENDIGO_SIDRENA_CIJENA_META_OBLIK', '_sidrena_naziv_posebnog_oblika' );
define( 'BRENDIGO_SIDRENA_CIJENA_META_JEDINICA', '_sidrena_jedinica_mjere' );
define( 'BRENDIGO_SIDRENA_CIJENA_META_JEDINICA_CIJENA', '_sidrena_cijena_jedinice_mjere' );
define( 'BRENDIGO_SIDRENA_CIJENA_META_BARCODE', '_brendigo_sidrena_barcode' );
define( 'BRENDIGO_SIDRENA_CIJENA_META_VISIBILITY', '_brendigo_sidrena_cjenik_visibility' );

require_once BRENDIGO_SIDRENA_CIJENA_PATH . 'includes/class-autoloader.php';
Brendigo_Sidrena_Cijena_Autoloader::register();

require_once BRENDIGO_SIDRENA_CIJENA_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Brendigo_Sidrena_Cijena_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Brendigo_Sidrena_Cijena_Plugin', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', BRENDIGO_SIDRENA_CIJENA_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', BRENDIGO_SIDRENA_CIJENA_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', BRENDIGO_SIDRENA_CIJENA_FILE, true );
		}
	}
);

// WordPress.org automatically loads language packs when this text domain is used.
// Text Domain / Domain Path headers also allow JIT discovery of bundled
// translations on WordPress 6.8 and newer. No deprecated manual registration.
add_action( 'plugins_loaded', array( 'Brendigo_Sidrena_Cijena_Plugin', 'init' ) );


add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( $links ) {
		$settings = '<a href="' . esc_url( admin_url( 'admin.php?page=brendigo-sidrena-cijena' ) ) . '">' . esc_html__( 'Postavke', 'brendigo-sidrena-cijena' ) . '</a>';
		$docs     = '<a href="' . esc_url( admin_url( 'admin.php?page=brendigo-sidrena-cijena&tab=upute' ) ) . '">' . esc_html__( 'Upute', 'brendigo-sidrena-cijena' ) . '</a>';
		array_unshift( $links, $settings );
		$links[] = $docs;
		return $links;
	}
);

/**
 * Brendigo-named template helper. Legacy sidrena_cijena() remains available for compatibility.
 *
 * @param WC_Product|int|null $product Product, ID, or current product.
 */
if ( ! function_exists( 'brendigo_sidrena_cijena' ) ) {
	function brendigo_sidrena_cijena( $product = null ) {
		if ( ! class_exists( 'Brendigo_Sidrena_Cijena_Frontend' ) ) {
			return;
		}

		echo wp_kses_post( Brendigo_Sidrena_Cijena_Frontend::instance()->get_html_for_context( $product ) );
	}
}

if ( ! function_exists( 'sidrena_cijena' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public legacy template helper retained for backward compatibility.
	function sidrena_cijena( $product = null ) {
		if ( ! class_exists( 'Brendigo_Sidrena_Cijena_Frontend' ) ) {
			return;
		}

		echo wp_kses_post( Brendigo_Sidrena_Cijena_Frontend::instance()->get_html_for_context( $product ) );
	}
}
