<?php
/**
 * Plugin Name: Sidrena WooCommerce
 * Plugin URI: https://brendigo.com/sidrene-cijene/
 * Description: Sidrene cijene za WooCommerce proizvode i usluge, CSV/XML cjenici, povijest cijena i arhiva 30+ dana.
 * Version: 1.0.3
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 11.1.2
 * Author: Brendigo
 * Author URI: https://brendigo.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sidrena-woocommerce
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sidrena_guard_root = dirname( __DIR__, 2 );
if ( ! is_file( $sidrena_guard_root . '/includes/sidrena-edition-guard.php' ) ) {
	$sidrena_guard_root = __DIR__;
}
$sidrena_entry_file = __FILE__;
if ( require $sidrena_guard_root . '/includes/sidrena-edition-guard.php' ) {
	return;
}
unset( $sidrena_entry_file, $sidrena_guard_root );

define( 'SIDRENA_VERSION', '1.0.3' );
define( 'SIDRENA_EDITION', 'woocommerce' );
define( 'SIDRENA_RULESET', 'NN 101/2026 · NN 105/2026 · MINGO 22.09.2026' );
define( 'SIDRENA_RULES_EFFECTIVE', '2026-10-01' );
define( 'SIDRENA_FILE', __FILE__ );
define( 'SIDRENA_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIDRENA_URL', plugin_dir_url( __FILE__ ) );

require_once SIDRENA_DIR . 'includes/sidrena-bootstrap.php';
