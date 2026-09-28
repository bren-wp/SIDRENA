<?php
/**
 * Shared SIDRENA edition-conflict guard.
 *
 * @package Sidrena
 * @author brendigo
 * @link https://brendigo.com/sidrene-cijene/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $sidrena_entry_file, $sidrena_requested_edition ) || ! is_string( $sidrena_entry_file ) || ! is_string( $sidrena_requested_edition ) ) {
	return false;
}

$sidrena_requested_edition = sanitize_key( $sidrena_requested_edition );
if ( ! in_array( $sidrena_requested_edition, array( 'wordpress', 'woocommerce' ), true ) ) {
	return false;
}

$sidrena_existing_edition = defined( 'SIDRENA_EDITION' ) ? sanitize_key( (string) SIDRENA_EDITION ) : '';
$sidrena_conflict         = ( $sidrena_existing_edition && $sidrena_existing_edition !== $sidrena_requested_edition )
	|| class_exists( 'Sidrena_Plugin', false )
	|| class_exists( 'Sidrena_Utils', false );

if ( ! $sidrena_conflict ) {
	return false;
}

register_activation_hook(
	$sidrena_entry_file,
	static function () {
		wp_die(
			esc_html__( 'Drugo SIDRENA izdanje je već aktivno. Deaktivirajte ga ručno prije aktivacije ovog izdanja.', 'sidrena' ),
			esc_html__( 'SIDRENA — sukob izdanja', 'sidrena' ),
			array( 'back_link' => true )
		);
	}
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

		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Aktivno može biti samo jedno SIDRENA izdanje. Konfliktno izdanje nije pokrenuto; administrator sam odlučuje koje će izdanje deaktivirati.', 'sidrena' ) . '</p></div>';
	}
);

return true;
