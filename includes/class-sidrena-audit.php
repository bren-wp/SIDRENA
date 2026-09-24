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

/**
 * Lightweight local audit trail for administrative and automated Sidrena events.
 * No data is transmitted outside the WordPress installation.
 */
final class Sidrena_Audit {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'sidrena_daily_generation', array( $this, 'prune' ), 200 );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'sidrena_audit_log';
	}

	public static function log( $event, $status = 'info', $message = '', $context = array(), $user_id = null ) {
		global $wpdb;
		$table = self::table_name();

		$event  = substr( sanitize_key( $event ), 0, 64 );
		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'info', 'success', 'warning', 'error' ), true ) ) {
			$status = 'info';
		}

		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$encoded = '';
		if ( ! empty( $context ) ) {
			$encoded = wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			if ( false === $encoded ) {
				$encoded = '';
			}
		}

		$wpdb->insert(
			$table,
			array(
				'event_type' => $event,
				'status'     => $status,
				'message'    => sanitize_textarea_field( (string) $message ),
				'context'    => $encoded,
				'user_id'    => absint( $user_id ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	public static function recent( $limit = 100 ) {
		global $wpdb;
		$table = self::table_name();
		$limit = min( 250, max( 1, absint( $limit ) ) );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, event_type, status, message, context, user_id, created_at FROM {$table} ORDER BY id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
	}

	public static function count_rows() {
		global $wpdb;
		$table = self::table_name();
		return absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
	}

	public function prune() {
		global $wpdb;
		$table  = self::table_name();
		$cutoff = wp_date( 'Y-m-d H:i:s', time() - ( 400 * DAY_IN_SECONDS ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s",
				$cutoff
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
	}
}
