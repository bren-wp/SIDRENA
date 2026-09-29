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
		$automation_mode             = sanitize_key( (string) ( $settings['automation_mode'] ?? 'wp_cron' ) );
		$settings['automation_mode'] = in_array( $automation_mode, array( 'wp_cron', 'external' ), true ) ? $automation_mode : 'wp_cron';
		$settings['retention_days']  = max( 30, absint( $settings['retention_days'] ?? 30 ) );
		$settings['csv_delimiter']   = ';';
		unset( $settings['default_ref_date'], $settings['fmcg_ref_date'], $settings['fmsid_ref_date'], $settings['display_lowest_30'], $settings['track_price_history'] );

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
		);
	}
}
