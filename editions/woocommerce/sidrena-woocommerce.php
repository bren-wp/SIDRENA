<?php
/**
 * Plugin Name: Brendigo Sidrene cijene i cjenici
 * Plugin URI: https://brendigo.com/sidrene-cijene/
 * Description: Sidrene cijene, povijest cijena, javni CSV/XML cjenici, lokacije i objava cijena za WooCommerce proizvode i usluge.
 * Version: 1.0.24
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 11.1.2
 * Author: brendigo
 * Author URI: https://brendigo.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: brendigo-sidrene-cijene-cjenici
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sidrena_guard_file        = __DIR__ . '/includes/sidrena-edition-guard.php';
$sidrena_entry_file        = __FILE__;
$sidrena_requested_edition = 'woocommerce';
if ( ! is_file( $sidrena_guard_file ) ) {
	return;
}
if ( require $sidrena_guard_file ) {
	return;
}
unset( $sidrena_guard_file, $sidrena_entry_file, $sidrena_requested_edition );

define( 'SIDRENA_VERSION', '1.0.24' );
define( 'SIDRENA_EDITION', 'woocommerce' );
define( 'SIDRENA_RULESET', 'NN 101/2026 · NN 105/2026 · NN 59/2026 · MINGO 22.09.2026' );
define( 'SIDRENA_RULES_EFFECTIVE', '2026-10-01' );
define( 'SIDRENA_FILE', __FILE__ );
define( 'SIDRENA_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIDRENA_URL', plugin_dir_url( __FILE__ ) );

require_once SIDRENA_DIR . 'includes/sidrena-bootstrap.php';
