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
 * Keeps the Sidrena admin area focused on the legally required workflow.
 *
 * Internal pages remain registered by Sidrena_Admin so existing bookmarks,
 * legacy links and support diagnostics continue to work. This layer only
 * rebuilds the visible WordPress sidebar submenu into a short task-based menu
 * for both plugin editions.
 */
final class Sidrena_Admin_UX {
	const SERVICE_MENU_SLUG = 'edit.php?post_type=sidrena_service';

	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'simplify_menu' ), 9999 );
		add_action( 'admin_head', array( $this, 'simplify_menu' ), 1 );
		add_filter( 'parent_file', array( $this, 'parent_file' ) );
		add_filter( 'submenu_file', array( $this, 'submenu_file' ) );
	}

	public static function primary_menu_labels() {
		return array(
			'sidrena'              => __( 'Početak', 'sidrena' ),
			'sidrena-catalog'      => __( 'Proizvodi', 'sidrena' ),
			self::SERVICE_MENU_SLUG => __( 'Usluge', 'sidrena' ),
			'sidrena-files'        => __( 'Objava cjenika', 'sidrena' ),
			'sidrena-locations'    => __( 'Lokacije / webshop', 'sidrena' ),
			'sidrena-settings'     => __( 'Zakonske postavke', 'sidrena' ),
			'sidrena-support'      => __( 'Pomoć', 'sidrena' ),
		);
	}

	public static function hidden_menu_slugs() {
		return array(
			'sidrena-compliance',
			'sidrena-archive',
			'sidrena-tools',
			'sidrena-log',
			'sidrena-rules',
			'sidrena-about',
			'sidrena-help',
			'post-new.php?post_type=sidrena_service',
		);
	}

	public function simplify_menu() {
		if ( ! Sidrena_Utils::current_user_can_manage() ) {
			return;
		}

		global $submenu;

		if ( empty( $submenu['sidrena'] ) || ! is_array( $submenu['sidrena'] ) ) {
			return;
		}

		$labels  = self::primary_menu_labels();
		$visible = array();

		foreach ( $submenu['sidrena'] as $item ) {
			if ( ! is_array( $item ) || empty( $item[2] ) ) {
				continue;
			}

			$slug = (string) $item[2];
			if ( ! isset( $labels[ $slug ] ) || isset( $visible[ $slug ] ) ) {
				continue;
			}

			$item[0] = $labels[ $slug ];
			if ( 'sidrena-support' === $slug && isset( $item[3] ) ) {
				$item[3] = __( 'Pomoć i podrška', 'sidrena' );
			}

			$visible[ $slug ] = $item;
		}

		$ordered = array();
		foreach ( array_keys( $labels ) as $slug ) {
			if ( isset( $visible[ $slug ] ) ) {
				$ordered[] = $visible[ $slug ];
			}
		}

		if ( $ordered ) {
			$submenu['sidrena'] = $ordered;
		}
	}

	public function parent_file( $parent_file ) {
		return $this->is_sidrena_service_screen() ? 'sidrena' : $parent_file;
	}

	public function submenu_file( $submenu_file ) {
		if ( $this->is_sidrena_service_screen() ) {
			return self::SERVICE_MENU_SLUG;
		}
		if ( in_array( (string) $submenu_file, self::hidden_menu_slugs(), true ) ) {
			return 'sidrena';
		}
		return $submenu_file;
	}

	private function is_sidrena_service_screen() {
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post      = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'sidrena_service' === $post_type ) {
			return true;
		}

		return $post && function_exists( 'get_post_type' ) && 'sidrena_service' === get_post_type( $post );
	}
}
