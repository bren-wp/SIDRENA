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
 * Keeps the Sidrena admin area focused for non-technical users.
 *
 * The original internal pages remain registered by Sidrena_Admin so existing
 * bookmarked URLs, legacy links and support diagnostics continue to work.
 * This layer only rebuilds the visible WordPress sidebar submenu into a short
 * task-based menu for both plugin editions.
 */
final class Sidrena_Admin_UX {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'simplify_menu' ), 1000 );
	}

	public static function primary_menu_labels() {
		return array(
			'sidrena'           => __( 'Početak', 'sidrena' ),
			'sidrena-catalog'   => __( 'Proizvodi', 'sidrena' ),
			'sidrena-files'     => __( 'Cjenici i objava', 'sidrena' ),
			'sidrena-locations' => __( 'Lokacije', 'sidrena' ),
			'sidrena-settings'  => __( 'Postavke', 'sidrena' ),
			'sidrena-support'   => __( 'Pomoć', 'sidrena' ),
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
			if ( ! isset( $labels[ $slug ] ) ) {
				continue;
			}

			$item[0] = $labels[ $slug ];
			if ( 'sidrena-support' === $slug && isset( $item[3] ) ) {
				$item[3] = __( 'Pomoć i podrška', 'sidrena' );
			}

			$visible[ $slug ] = $item;
		}

		$ordered = array();
		foreach ( $labels as $slug => $label ) {
			unset( $label );
			if ( isset( $visible[ $slug ] ) ) {
				$ordered[] = $visible[ $slug ];
			}
		}

		if ( $ordered ) {
			$submenu['sidrena'] = $ordered;
		}
	}
}
