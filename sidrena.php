<?php
/**
 * Plugin Name: Sidrena
 * Plugin URI: https://sidrena-cijena.com.hr/
 * Description: Sidrene cijene, WooCommerce i usluge s javnim CSV/XML cjenicima, arhivom 30+ dana i poviješću cijena.
 * Version: 1.6.0
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

define( 'SIDRENA_VERSION', '1.6.0' );
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
