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
 * Keeps the Sidrena admin sidebar deterministic and easy to scan.
 *
 * This class no longer hides legacy pages after they are registered. The clean
 * sidebar is registered up front by Sidrena_Admin_Menu; this layer only
 * normalizes labels, removes duplicate slugs and keeps service edit screens
 * visually grouped under Sidrena.
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
		$catalog_label = Sidrena_Utils::is_woocommerce_edition() ? __( 'Proizvodi', 'sidrena' ) : __( 'Katalog', 'sidrena' );

		return array(
			'sidrena'               => __( 'Pregled', 'sidrena' ),
			'sidrena-catalog'       => $catalog_label,
			self::SERVICE_MENU_SLUG => __( 'Usluge', 'sidrena' ),
			'sidrena-files'         => __( 'Cjenici', 'sidrena' ),
			'sidrena-locations'     => __( 'Lokacije', 'sidrena' ),
			'sidrena-settings'      => __( 'Postavke', 'sidrena' ),
			'sidrena-support'       => __( 'Pomoć', 'sidrena' ),
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
		$extra   = array();

		foreach ( $submenu['sidrena'] as $item ) {
			if ( ! is_array( $item ) || empty( $item[2] ) ) {
				continue;
			}

			$slug = (string) $item[2];
			if ( isset( $visible[ $slug ] ) ) {
				continue;
			}

			if ( isset( $labels[ $slug ] ) ) {
				$item[0] = $labels[ $slug ];
				if ( 'sidrena-support' === $slug && isset( $item[3] ) ) {
					$item[3] = __( 'Pomoć i podrška', 'sidrena' );
				}
				$visible[ $slug ] = $item;
				continue;
			}

			$extra[ $slug ] = $item;
		}

		$ordered = array();
		foreach ( array_keys( $labels ) as $slug ) {
			if ( isset( $visible[ $slug ] ) ) {
				$ordered[] = $visible[ $slug ];
			}
		}

		if ( $ordered ) {
			$submenu['sidrena'] = array_merge( $ordered, array_values( $extra ) );
		}
	}

	public function parent_file( $parent_file ) {
		return $this->is_sidrena_service_screen() ? 'sidrena' : $parent_file;
	}

	public function submenu_file( $submenu_file ) {
		if ( $this->is_sidrena_service_screen() ) {
			return self::SERVICE_MENU_SLUG;
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
