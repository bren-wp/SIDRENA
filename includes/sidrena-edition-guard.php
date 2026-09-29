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
$sidrena_attempted_key    = false !== stripos( basename( $sidrena_conflicting_file ), 'woocommerce' ) ? 'woocommerce' : 'wordpress';
$sidrena_active_key       = defined( 'SIDRENA_EDITION' ) ? (string) SIDRENA_EDITION : 'legacy';

$sidrena_edition_labels = array(
	'wordpress'   => 'SIDRENA — WordPress izdanje',
	'woocommerce' => 'SIDRENA — WooCommerce izdanje',
	'legacy'      => 'starije SIDRENA izdanje',
);

$sidrena_attempted_label  = isset( $sidrena_edition_labels[ $sidrena_attempted_key ] ) ? $sidrena_edition_labels[ $sidrena_attempted_key ] : 'SIDRENA izdanje';
$sidrena_active_label     = isset( $sidrena_edition_labels[ $sidrena_active_key ] ) ? $sidrena_edition_labels[ $sidrena_active_key ] : 'drugo SIDRENA izdanje';
$sidrena_conflict_message = sprintf(
	/* translators: 1: currently active SIDRENA edition, 2: SIDRENA edition being activated. */
	__( 'Aktivno izdanje: %1$s. Pokušavate aktivirati: %2$s. Deaktivirajte aktivno izdanje prije aktivacije drugoga. Deaktivacija ne briše SIDRENA poslovne podatke.', 'sidrena' ),
	$sidrena_active_label,
	$sidrena_attempted_label
);

register_activation_hook(
	$sidrena_conflicting_file,
	static function () use ( $sidrena_conflict_message ) {
		wp_die(
			esc_html( $sidrena_conflict_message ),
			esc_html__( 'SIDRENA — sukob izdanja', 'sidrena' ),
			array( 'back_link' => true )
		);
	}
);

add_action(
	'admin_init',
	static function () use ( $sidrena_conflicting_file ) {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
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
	static function () use ( $sidrena_conflict_message ) {
		if ( ! current_user_can( 'activate_plugins' ) || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}

		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $sidrena_conflict_message ) . '</p></div>';
	}
);

return true;
