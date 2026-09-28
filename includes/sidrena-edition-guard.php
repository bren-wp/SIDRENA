<?php
/**
 * Shared Sidrena edition-conflict guard.
 *
 * @package Sidrena
 * @author brendigo
 * @link https://brendigo.com/sidrene-cijene/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $sidrena_entry_file ) || ! is_string( $sidrena_entry_file ) || '' === $sidrena_entry_file ) {
	return false;
}

if ( ! defined( 'SIDRENA_EDITION' ) && ! class_exists( 'Sidrena_Plugin', false ) && ! class_exists( 'Sidrena_Utils', false ) ) {
	return false;
}

$sidrena_conflicting_file = $sidrena_entry_file;

register_activation_hook(
	$sidrena_conflicting_file,
	static function () {
		wp_die(
			esc_html__( 'Drugo ili starije Sidrena izdanje je već aktivno. Deaktivirajte ga prije aktivacije ovog plugina.', 'sidrena' ),
			esc_html__( 'Sidrena — sukob izdanja', 'sidrena' ),
			array( 'back_link' => true )
		);
	}
);

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
		if ( ! current_user_can( 'activate_plugins' ) || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}

		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Aktivno može biti samo jedno SIDRENA izdanje. Konfliktno izdanje je deaktivirano. Na ekranu Dodataka ostavite aktivno samo izdanje koje želite koristiti.', 'sidrena' ) . '</p></div>';
	}
);

return true;
