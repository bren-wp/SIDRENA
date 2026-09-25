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

if ( ! defined( 'SIDRENA_VERSION' ) || ! defined( 'SIDRENA_EDITION' ) || ! defined( 'SIDRENA_FILE' ) || ! defined( 'SIDRENA_DIR' ) || ! defined( 'SIDRENA_URL' ) ) {
	return;
}

require_once SIDRENA_DIR . 'includes/class-sidrena-utils.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-legal-automation.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-activator.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-audit.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-compliance.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-site-health.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-cli.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-service-history.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-services.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-pricelist.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-rest.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-public.php';

if ( 'wordpress' === SIDRENA_EDITION ) {
	require_once SIDRENA_DIR . 'includes/class-sidrena-standalone.php';
} elseif ( 'woocommerce' === SIDRENA_EDITION ) {
	require_once SIDRENA_DIR . 'includes/class-sidrena-bulk.php';
	require_once SIDRENA_DIR . 'includes/class-sidrena-history.php';
	require_once SIDRENA_DIR . 'includes/class-sidrena-location-data.php';
	require_once SIDRENA_DIR . 'includes/class-sidrena-location-history.php';
	require_once SIDRENA_DIR . 'includes/class-sidrena-products.php';
	require_once SIDRENA_DIR . 'includes/class-sidrena-woo-import-export.php';
	require_once SIDRENA_DIR . 'includes/class-sidrena-compatibility.php';
}

require_once SIDRENA_DIR . 'includes/class-sidrena-admin.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-admin-ux.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-admin-menu.php';
require_once SIDRENA_DIR . 'includes/class-sidrena-plugin.php';

if ( ! function_exists( 'sidrena_cijena' ) ) {
	/**
	 * Theme/template helper for the active Sidrena edition.
	 *
	 * @param mixed $product Product reference or current WooCommerce product.
	 */
	function sidrena_cijena( $product = null ) {
		if ( Sidrena_Utils::is_wordpress_edition() ) {
			$raw = is_scalar( $product ) ? trim( (string) $product ) : '';
			$id  = 0;
			if ( preg_match( '/^s(\d+)$/i', $raw, $match ) ) {
				$id = absint( $match[1] );
			} elseif ( is_numeric( $product ) ) {
				$id = absint( $product );
			}
			if ( $id && class_exists( 'Sidrena_Standalone' ) && Sidrena_Standalone::POST_TYPE === get_post_type( $id ) ) {
				echo wp_kses_post( Sidrena_Standalone::instance()->price_shortcode( array( 'id' => 's' . $id ) ) );
			}
			return;
		}

		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
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

register_activation_hook( SIDRENA_FILE, array( 'Sidrena_Activator', 'activate' ) );
register_deactivation_hook( SIDRENA_FILE, array( 'Sidrena_Activator', 'deactivate' ) );

if ( Sidrena_Utils::is_woocommerce_edition() ) {
	add_action(
		'before_woocommerce_init',
		static function () {
			if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SIDRENA_FILE, true );
			}
		}
	);
}

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'sidrena', false, dirname( plugin_basename( SIDRENA_FILE ) ) . '/languages' );

		if ( Sidrena_Utils::is_woocommerce_edition() && ! Sidrena_Utils::woocommerce_runtime_available() ) {
			add_action(
				'admin_notices',
				static function () {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-error"><p>' . esc_html__( 'Sidrena WooCommerce zahtijeva aktivan WooCommerce plugin.', 'sidrena' ) . '</p></div>';
					}
				}
			);
			return;
		}

		Sidrena_Activator::maybe_upgrade();
		Sidrena_Plugin::instance()->run();
	},
	20
);
