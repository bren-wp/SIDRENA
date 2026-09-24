<?php
/**
 * Plugin Name: Sidrena WordPress
 * Plugin URI: https://sidrene-cijene.com.hr/
 * Description: Sidrene cijene za običan WordPress bez WooCommercea: vlastiti katalog proizvoda i usluga, CSV/XML cjenici i arhiva 30+ dana.
 * Version: 0.5.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: Brendigo
 * Author URI: https://brendigo.com/
 * License: Sidrena Software License 1.0
 * License URI: https://github.com/bren-wp/SIDRENA/blob/main/LICENSE
 * Update URI: https://github.com/bren-wp/SIDRENA#sidrena-wordpress
 * Text Domain: sidrena
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'SIDRENA_EDITION' ) || class_exists( 'Sidrena_Plugin', false ) || class_exists( 'Sidrena_Utils', false ) ) {
	$sidrena_conflicting_file = __FILE__;

	// During an activation request WordPress loads already active plugins first.
	// Abort before the target plugin is added to active_plugins.
	register_activation_hook(
		__FILE__,
		static function () {
			wp_die(
				esc_html__( 'Drugo ili starije Sidrena izdanje je već aktivno. Deaktivirajte ga prije aktivacije ovog plugina.', 'sidrena' ),
				esc_html__( 'Sidrena — sukob izdanja', 'sidrena' ),
				array( 'back_link' => true )
			);
		}
	);

	// Repair an older installation where both editions were already marked as
	// active: the later-loaded copy deactivates itself on the next admin request.
	add_action(
		'admin_init',
		static function () use ( $sidrena_conflicting_file ) {
			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if ( function_exists( 'deactivate_plugins' ) ) {
				deactivate_plugins( plugin_basename( $sidrena_conflicting_file ), true );
			}
		},
		1
	);

	add_action(
		'admin_notices',
		static function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Aktivno može biti samo jedno Sidrena izdanje. Drugo ili starije izdanje je blokirano/deaktivirano kako bi se spriječili dvostruki hookovi, klase i objave.', 'sidrena' ) . '</p></div>';
			}
		}
	);
	return;
}

define( 'SIDRENA_VERSION', '0.5.0' );
define( 'SIDRENA_EDITION', 'wordpress' );
define( 'SIDRENA_RULESET', 'NN 101/2026 · NN 105/2026 · MINGO 22.09.2026' );
define( 'SIDRENA_RULES_EFFECTIVE', '2026-10-01' );
define( 'SIDRENA_FILE', __FILE__ );
define( 'SIDRENA_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIDRENA_URL', plugin_dir_url( __FILE__ ) );

require_once SIDRENA_DIR . 'includes/sidrena-bootstrap.php';
