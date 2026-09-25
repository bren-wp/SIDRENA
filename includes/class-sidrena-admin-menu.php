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

/**
 * Registers the small WordPress.org-friendly Sidrena admin sidebar.
 *
 * Only task-oriented pages are registered in the WordPress sidebar. Secondary
 * diagnostics and documentation are rendered as sections inside those pages,
 * so the plugin does not register hidden or duplicate admin menu screens.
 */
final class Sidrena_Admin_Menu {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 9 );
	}

	public static function items() {
		$catalog_label = Sidrena_Utils::is_woocommerce_edition() ? __( 'Proizvodi', 'sidrena' ) : __( 'Katalog', 'sidrena' );

		return array(
			array( 'sidrena', __( 'Pregled', 'sidrena' ), __( 'Pregled', 'sidrena' ) ),
			array( 'sidrena-catalog', $catalog_label, $catalog_label ),
			array( Sidrena_Admin_UX::SERVICE_MENU_SLUG, __( 'Usluge', 'sidrena' ), __( 'Usluge', 'sidrena' ) ),
			array( 'sidrena-files', __( 'Cjenici', 'sidrena' ), __( 'Cjenici', 'sidrena' ) ),
			array( 'sidrena-locations', __( 'Lokacije', 'sidrena' ), __( 'Lokacije', 'sidrena' ) ),
			array( 'sidrena-settings', __( 'Postavke', 'sidrena' ), __( 'Postavke', 'sidrena' ) ),
			array( 'sidrena-support', __( 'Pomoć', 'sidrena' ), __( 'Pomoć', 'sidrena' ) ),
		);
	}

	public function register_menu() {
		$capability = Sidrena_Utils::admin_menu_capability();

		add_menu_page(
			'Sidrena',
			'Sidrena',
			$capability,
			'sidrena',
			array( Sidrena_Admin::instance(), 'page' ),
			SIDRENA_URL . 'assets/images/menu-anchor.svg',
			58
		);

		foreach ( self::items() as $item ) {
			add_submenu_page(
				'sidrena',
				$item[1],
				$item[2],
				$capability,
				$item[0],
				array( Sidrena_Admin::instance(), 'page' )
			);
		}
	}
}
