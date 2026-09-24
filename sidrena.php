<?php
/**
 * Plugin Name: Sidrena
 * Plugin URI: https://sidrene-cijene.com.hr/
 * Description: Sidrene cijene za WordPress sa ili bez WooCommercea, uslugama, javnim CSV/XML cjenicima i arhivom 30+ dana.
 * Version: 1.6.4
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: Brendigo
 * Author URI: https://brendigo.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sidrena
 * Domain Path: /languages
 * WC requires at least: 8.0
 * WC tested up to: 11.1.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIDRENA_VERSION', '1.6.4' );
define( 'SIDRENA_RULESET', 'NN 101/2026 · NN 105/2026 · MINGO 22.09.2026' );
define( 'SIDRENA_RULES_EFFECTIVE', '2026-10-01' );
define( 'SIDRENA_FILE', __FILE__ );
define( 'SIDRENA_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIDRENA_URL', plugin_dir_url( __FILE__ ) );

require_once SIDRENA_DIR . 'includes/class-sidrena-utils.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-activator.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-audit.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-site-health.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-cli.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-bulk.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-history.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-service-history.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-location-data.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-location-history.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-standalone.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-products.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-woo-import-export.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-compatibility.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-services.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-pricelist.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-rest.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-public.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-admin.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-plugin.php';

if ( ! function_exists( 'sidrena_cijena' ) ) {
	/**
	 * Universal template helper for themes and page builders.
	 *
	 * WooCommerce product: sidrena_cijena( 123 )
	 * Standalone product:  sidrena_cijena( 's123' )
	 *
	 * @param mixed $product Product ID/object, standalone s<ID> reference, or current WooCommerce product.
	 */
	function sidrena_cijena( $product = null ) {
		$raw = is_scalar( $product ) ? trim( (string) $product ) : '';
		if ( preg_match( '/^s(\d+)$/i', $raw, $match ) && class_exists( 'Sidrena_Standalone' ) ) {
			echo wp_kses_post( Sidrena_Standalone::instance()->price_shortcode( array( 'id' => 's' . absint( $match[1] ) ) ) );
			return;
		}

		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			$id = is_numeric( $product ) ? absint( $product ) : 0;
			if ( $id && class_exists( 'Sidrena_Standalone' ) && Sidrena_Standalone::POST_TYPE === get_post_type( $id ) ) {
				echo wp_kses_post( Sidrena_Standalone::instance()->price_shortcode( array( 'id' => 's' . $id ) ) );
			}
			return;
		}

		if ( is_numeric( $product ) ) {
			$product = wc_get_product( absint( $product ) );
		}
		if ( class_exists( 'Sidrena_Products' ) ) {
			Sidrena_Products::instance()->action_output( $product );
		}
	}
}

register_activation_hook( __FILE__, array( 'Sidrena_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Sidrena_Activator', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'sidrena', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		Sidrena_Activator::maybe_upgrade();
		Sidrena_Plugin::instance()->run();
	}
);
