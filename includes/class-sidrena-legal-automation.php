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
	private const PUBLICATION_DEADLINE  = '08:00';

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

		// NN 101/2026 requires a machine-readable CSV or XML publication. Keep
		// the merchant's chosen format when at least one is enabled, and repair
		// only the invalid state in which both formats are disabled.
		if ( 'yes' !== ( $settings['generate_csv'] ?? 'no' ) && 'yes' !== ( $settings['generate_xml'] ?? 'no' ) ) {
			$settings['generate_csv'] = 'yes';
		}

		foreach ( self::required_publication_flags() as $key ) {
			$settings[ $key ] = 'yes';
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
			'enable_rest_index',
			'strict_publication',
			'failure_notifications',
		);
	}
}
