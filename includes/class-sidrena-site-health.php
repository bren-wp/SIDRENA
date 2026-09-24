<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo LTD Developer
 * @link https://sidrene-cijene.com.hr/
 * @see https://brendigo.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sidrena_Site_Health {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_filter( 'site_status_tests', array( $this, 'tests' ) );
		add_filter( 'debug_information', array( $this, 'debug_information' ) );
	}

	public function tests( $tests ) {
		if ( ! isset( $tests['direct'] ) || ! is_array( $tests['direct'] ) ) {
			$tests['direct'] = array();
		}
		$tests['direct']['sidrena_schedule'] = array(
			'label' => __( 'Sidrena raspored generiranja', 'sidrena' ),
			'test'  => array( $this, 'test_schedule' ),
		);
		$tests['direct']['sidrena_archive'] = array(
			'label' => __( 'Sidrena javna arhiva', 'sidrena' ),
			'test'  => array( $this, 'test_archive' ),
		);
		$tests['direct']['sidrena_runtime'] = array(
			'label' => __( 'Sidrena način rada', 'sidrena' ),
			'test'  => array( $this, 'test_runtime' ),
		);
		return $tests;
	}

	public function test_runtime() {
		$services = wp_count_posts( 'sidrena_service' );
		$services = $services && isset( $services->publish ) ? absint( $services->publish ) : 0;

		if ( Sidrena_Utils::is_wordpress_edition() ) {
			if ( ! class_exists( 'Sidrena_Standalone' ) || ! class_exists( 'Sidrena_Services' ) ) {
				return array(
					'label'       => __( 'Sidrena WordPress moduli nisu potpuno učitani', 'sidrena' ),
					'status'      => 'critical',
					'badge'       => array( 'label' => 'Sidrena', 'color' => 'red' ),
					'description' => '<p>' . esc_html__( 'Nedostaje modul WordPress kataloga ili usluga. Ponovno instalirajte Sidrena WordPress paket.', 'sidrena' ) . '</p>',
					'actions'     => '',
					'test'        => 'sidrena_runtime',
				);
			}

			$products = Sidrena_Standalone::count();
			return array(
				'label'       => __( 'Sidrena WordPress izdanje je aktivno', 'sidrena' ),
				'status'      => 'good',
				'badge'       => array( 'label' => 'Sidrena', 'color' => 'blue' ),
				'description' => '<p>' . esc_html( sprintf( __( 'Vlastiti WordPress katalog je aktivan: %1$d proizvoda i %2$d objavljenih usluga. WooCommerce proizvodi se u ovom izdanju ne koriste.', 'sidrena' ), $products, $services ) ) . '</p>',
				'actions'     => '',
				'test'        => 'sidrena_runtime',
			);
		}

		if ( ! Sidrena_Utils::woocommerce_runtime_available() || ! class_exists( 'Sidrena_Products' ) || ! class_exists( 'Sidrena_Services' ) ) {
			return array(
				'label'       => __( 'Sidrena WooCommerce nije potpuno spremna', 'sidrena' ),
				'status'      => 'critical',
				'badge'       => array( 'label' => 'Sidrena', 'color' => 'red' ),
				'description' => '<p>' . esc_html__( 'WooCommerce ili jedan od potrebnih Sidrena WooCommerce modula nije aktivan. Provjerite instalaciju oba plugina.', 'sidrena' ) . '</p>',
				'actions'     => '',
				'test'        => 'sidrena_runtime',
			);
		}

		return array(
			'label'       => __( 'Sidrena WooCommerce izdanje je aktivno', 'sidrena' ),
			'status'      => 'good',
			'badge'       => array( 'label' => 'Sidrena', 'color' => 'blue' ),
			'description' => '<p>' . esc_html( sprintf( __( 'WooCommerce katalog je izvor proizvoda, uz %d objavljenih Sidrena usluga. Standalone WordPress katalog nije dio ovog izdanja.', 'sidrena' ), $services ) ) . '</p>',
			'actions'     => '',
			'test'        => 'sidrena_runtime',
		);
	}
	public function test_schedule() {
		$settings = Sidrena_Utils::settings();
		$next     = wp_next_scheduled( 'sidrena_daily_generation' );
		$time     = isset( $settings['generation_time'] ) ? (string) $settings['generation_time'] : '06:30';
		$late     = preg_match( '/^(\d{2}):(\d{2})$/', $time, $parts ) && ( (int) $parts[1] > 7 || ( 7 === (int) $parts[1] && (int) $parts[2] > 59 ) );

		if ( ! $next ) {
			return array(
				'label'       => __( 'Sidrena nema zakazan dnevni zadatak', 'sidrena' ),
				'status'      => 'critical',
				'badge'       => array( 'label' => 'Sidrena', 'color' => 'red' ),
				'description' => '<p>' . esc_html__( 'Dnevno generiranje CSV/XML cjenika nije zakazano. Otvorite Sidrena > Postavke i ponovno spremite postavke ili koristite alat za popravak rasporeda.', 'sidrena' ) . '</p>',
				'actions'     => '',
				'test'        => 'sidrena_schedule',
			);
		}

		if ( $late ) {
			return array(
				'label'       => __( 'Sidrena je postavljena na generiranje nakon 08:00', 'sidrena' ),
				'status'      => 'recommended',
				'badge'       => array( 'label' => 'Sidrena', 'color' => 'orange' ),
				'description' => '<p>' . esc_html__( 'Za poslovne procese koji zahtijevaju objavu do 08:00 odaberite ranije vrijeme i osigurajte pouzdano izvršavanje WordPress crona ili vanjskog server crona.', 'sidrena' ) . '</p>',
				'actions'     => '',
				'test'        => 'sidrena_schedule',
			);
		}

		return array(
			'label'       => __( 'Sidrena dnevni raspored je aktivan', 'sidrena' ),
			'status'      => 'good',
			'badge'       => array( 'label' => 'Sidrena', 'color' => 'blue' ),
			'description' => '<p>' . sprintf( esc_html__( 'Sljedeće generiranje: %s. Za strogo vremenski pouzdano izvršavanje preporučuje se server cron koji poziva WordPress cron.', 'sidrena' ), esc_html( wp_date( 'd.m.Y. H:i', $next ) ) ) . '</p>',
			'actions'     => '',
			'test'        => 'sidrena_schedule',
		);
	}

	public function test_archive() {
		$settings = Sidrena_Utils::settings();
		$retain   = max( 30, absint( $settings['retention_days'] ) );
		$paths    = Sidrena_Utils::upload_paths();
		$writable = is_dir( $paths['archive_dir'] ) && wp_is_writable( $paths['archive_dir'] );

		if ( ! $writable ) {
			return array(
				'label'       => __( 'Sidrena arhiva nije zapisiva', 'sidrena' ),
				'status'      => 'critical',
				'badge'       => array( 'label' => 'Sidrena', 'color' => 'red' ),
				'description' => '<p>' . esc_html__( 'WordPress ne može zapisivati u Sidrena mapu arhive. Provjerite dozvole direktorija uploads/sidrena/arhiva.', 'sidrena' ) . '</p>',
				'actions'     => '',
				'test'        => 'sidrena_archive',
			);
		}

		return array(
			'label'       => __( 'Sidrena arhiva je spremna', 'sidrena' ),
			'status'      => 'good',
			'badge'       => array( 'label' => 'Sidrena', 'color' => 'blue' ),
			'description' => '<p>' . sprintf( esc_html__( 'Mapa je zapisiva, a konfigurirano čuvanje javnih objava je najmanje %d dana.', 'sidrena' ), $retain ) . '</p>',
			'actions'     => '',
			'test'        => 'sidrena_archive',
		);
	}

	public function debug_information( $info ) {
		$settings = Sidrena_Utils::settings();
		$last     = get_option( 'sidrena_last_run', array() );
		$next     = wp_next_scheduled( 'sidrena_daily_generation' );
		$fields   = array(
			'version' => array( 'label' => __( 'Verzija', 'sidrena' ), 'value' => SIDRENA_VERSION ),
			'edition' => array( 'label' => __( 'Izdanje', 'sidrena' ), 'value' => Sidrena_Utils::runtime_mode_label() ),
			'business_mode' => array( 'label' => __( 'Poslovni model', 'sidrena' ), 'value' => $settings['business_mode'] ),
			'retention' => array( 'label' => __( 'Arhiva', 'sidrena' ), 'value' => max( 30, absint( $settings['retention_days'] ) ) . ' dana' ),
			'generation_time' => array( 'label' => __( 'Vrijeme generiranja', 'sidrena' ), 'value' => $settings['generation_time'] ),
			'next_run' => array( 'label' => __( 'Sljedeće generiranje', 'sidrena' ), 'value' => $next ? wp_date( DATE_ATOM, $next ) : __( 'nije zakazano', 'sidrena' ) ),
			'last_run' => array( 'label' => __( 'Posljednje generiranje', 'sidrena' ), 'value' => ! empty( $last['generated_at'] ) ? $last['generated_at'] : __( 'još nije izvršeno', 'sidrena' ) ),
		);

		if ( Sidrena_Utils::is_wordpress_edition() ) {
			$fields['product_source'] = array( 'label' => __( 'Izvor proizvoda', 'sidrena' ), 'value' => __( 'Sidrena WordPress katalog', 'sidrena' ) );
			$fields['products'] = array( 'label' => __( 'WordPress proizvodi', 'sidrena' ), 'value' => class_exists( 'Sidrena_Standalone' ) ? Sidrena_Standalone::count() : 0 );
		} else {
			$fields['product_source'] = array( 'label' => __( 'Izvor proizvoda', 'sidrena' ), 'value' => __( 'WooCommerce', 'sidrena' ) );
			$fields['woocommerce'] = array( 'label' => __( 'WooCommerce runtime', 'sidrena' ), 'value' => Sidrena_Utils::woocommerce_runtime_available() ? __( 'aktivan', 'sidrena' ) : __( 'nije dostupan', 'sidrena' ) );
		}

		$info['sidrena'] = array(
			'label'  => Sidrena_Utils::runtime_mode_label(),
			'fields' => $fields,
		);
		return $info;
	}

}
