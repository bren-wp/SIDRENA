<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://sidrene-cijene.com.hr/
 * @see https://brendigo.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the small WordPress.org-friendly Sidrena admin sidebar.
 *
 * The legacy Sidrena_Admin::menu() method is intentionally not used for the
 * visible sidebar because it registers diagnostic and documentation pages as
 * first-class menu items. Those screens can still be reached through explicit
 * support flows when needed, but the main WordPress sidebar starts from the
 * legal operating workflow only.
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
		add_action( 'admin_menu', array( $this, 'replace_legacy_menu' ), 9 );
	}

	public static function items() {
		return array(
			array( 'sidrena', __( 'Početak', 'sidrena' ), __( 'Početak', 'sidrena' ) ),
			array( 'sidrena-catalog', __( 'Proizvodi', 'sidrena' ), __( 'Proizvodi', 'sidrena' ) ),
			array( Sidrena_Admin_UX::SERVICE_MENU_SLUG, __( 'Usluge', 'sidrena' ), __( 'Usluge', 'sidrena' ) ),
			array( 'sidrena-files', __( 'Objava cjenika', 'sidrena' ), __( 'Objava cjenika', 'sidrena' ) ),
			array( 'sidrena-locations', __( 'Lokacije / webshop', 'sidrena' ), __( 'Lokacije / webshop', 'sidrena' ) ),
			array( 'sidrena-settings', __( 'Zakonske postavke', 'sidrena' ), __( 'Zakonske postavke', 'sidrena' ) ),
			array( 'sidrena-support', __( 'Pomoć', 'sidrena' ), __( 'Pomoć', 'sidrena' ) ),
		);
	}

	public function replace_legacy_menu() {
		remove_action( 'admin_menu', array( Sidrena_Admin::instance(), 'menu' ) );
		$this->register_menu();
	}

	public function register_menu() {
		$capability = Sidrena_Utils::admin_menu_capability();

		add_menu_page(
			'Sidrena',
			'Sidrena',
			$capability,
			'sidrena',
			array( Sidrena_Admin::instance(), 'page' ),
			'dashicons-media-spreadsheet',
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
