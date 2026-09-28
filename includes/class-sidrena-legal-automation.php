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

final class Sidrena_Legal_Automation {
	public const GENERAL_REFERENCE_DATE = '2026-09-10';
	public const FMCG_REFERENCE_DATE    = '2025-05-02';

	private const SAFE_GENERATION_TIME = '06:30';
	private const PUBLICATION_DEADLINE = '08:00';

	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_filter( 'option_sidrena_settings', array( $this, 'normalize_runtime_settings' ) );
		add_filter( 'pre_update_option_sidrena_settings', array( $this, 'normalize_saved_settings' ), 10, 2 );
	}

	public function normalize_runtime_settings( $settings ) {
		return self::normalize_settings( $settings );
	}

	public function normalize_saved_settings( $settings, $old_settings ) {
		unset( $old_settings );
		return self::normalize_settings( $settings );
	}

	public static function normalize_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		$settings['generation_time'] = self::normalize_generation_time( $settings['generation_time'] ?? self::SAFE_GENERATION_TIME );
		$settings['retention_days']  = max( 30, absint( $settings['retention_days'] ?? 45 ) );
		$settings['csv_delimiter']   = ';';
		unset( $settings['default_ref_date'], $settings['fmcg_ref_date'], $settings['fmsid_ref_date'] );

		foreach ( self::required_publication_flags() as $key ) {
			$settings[ $key ] = 'yes';
		}

		foreach (
			array(
				'business_name',
				'business_address',
				'business_oib',
				'business_email',
				'business_phone',
				'business_registry',
				'business_registry_number',
				'business_vat_id',
				'business_supervisory_authority',
				'show_business_identity',
				'label_mode',
				'label_custom',
				'anchor_tooltip_enabled',
				'anchor_tooltip_text',
			) as $legacy_key
		) {
			unset( $settings[ $legacy_key ] );
		}

		return $settings;
	}

	public static function general_reference_date() {
		return self::GENERAL_REFERENCE_DATE;
	}

	public static function fmcg_reference_date() {
		return self::FMCG_REFERENCE_DATE;
	}

	public static function custom_reference_date( $date ) {
		$date = is_scalar( $date ) ? trim( sanitize_text_field( (string) $date ) ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date || $date <= self::GENERAL_REFERENCE_DATE ) {
			return '';
		}
		return $date;
	}

	public static function reference_date_for_group( $group, $custom_date = '' ) {
		$group = sanitize_key( (string) $group );
		if ( 'fmcg' === $group ) {
			return self::FMCG_REFERENCE_DATE;
		}
		if ( 'custom' === $group ) {
			return self::custom_reference_date( $custom_date );
		}
		return self::GENERAL_REFERENCE_DATE;
	}

	public static function normalize_generation_time( $time ) {
		$time = is_scalar( $time ) ? sanitize_text_field( (string) $time ) : '';
		if ( ! preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $match ) ) {
			return self::SAFE_GENERATION_TIME;
		}

		$hour    = (int) $match[1];
		$minute  = (int) $match[2];
		$current = ( $hour * 60 ) + $minute;
		if ( $current >= self::publication_deadline_minutes() ) {
			return self::SAFE_GENERATION_TIME;
		}

		return sprintf( '%02d:%02d', $hour, $minute );
	}

	public static function publication_deadline() {
		return self::PUBLICATION_DEADLINE;
	}

	public static function publication_deadline_minutes() {
		return 8 * 60;
	}

	public static function required_publication_flags() {
		return array(
			'generate_csv',
			'generate_xml',
			'enable_rest_index',
			'publish_manifest',
			'enable_public_html',
			'strict_publication',
			'failure_notifications',
			'display_anchor',
			'display_lowest_30',
			'track_price_history',
		);
	}
}
