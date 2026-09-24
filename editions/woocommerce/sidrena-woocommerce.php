<?php
/**
 * Plugin Name: Sidrena WooCommerce
 * Plugin URI: https://sidrene-cijene.com.hr/
 * Description: Sidrene cijene za WooCommerce proizvode i usluge, CSV/XML cjenici, povijest cijena i arhiva 30+ dana.
 * Version: 2.0.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 11.1.2
 * Author: Brendigo
 * Author URI: https://brendigo.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sidrena
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'SIDRENA_EDITION' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Aktivno može biti samo jedno Sidrena izdanje. Deaktivirajte drugo Sidrena izdanje prije korištenja ovog plugina.', 'sidrena' ) . '</p></div>';
			}
		}
	);
	return;
}

define( 'SIDRENA_VERSION', '2.0.0' );
define( 'SIDRENA_EDITION', 'woocommerce' );
define( 'SIDRENA_RULESET', 'NN 101/2026 · NN 105/2026 · MINGO 22.09.2026' );
define( 'SIDRENA_RULES_EFFECTIVE', '2026-10-01' );
define( 'SIDRENA_FILE', __FILE__ );
define( 'SIDRENA_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIDRENA_URL', plugin_dir_url( __FILE__ ) );

require_once SIDRENA_DIR . 'includes/sidrena-bootstrap.php';
