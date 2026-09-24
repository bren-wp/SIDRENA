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
 * Lightweight local audit trail for administrative and automated Sidrena events.
 * No data is transmitted outside the WordPress installation.
 */
final class Sidrena_Audit {
	const MAX_MESSAGE_BYTES = 2000;
	const MAX_CONTEXT_BYTES = 20000;
	const MAX_ROWS          = 5000;
	const RETENTION_DAYS    = 400;

	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'sidrena_daily_generation', array( $this, 'prune' ), 200 );
		add_action( 'sidrena_publication_watch', array( $this, 'prune' ), 200 );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'sidrena_audit_log';
	}

	public static function log( $event, $status = 'info', $message = '', $context = array(), $user_id = null ) {
		global $wpdb;
		$table = self::table_name();

		if ( ! self::table_exists() ) {
			return false;
		}

		$event = substr( sanitize_key( $event ), 0, 64 );
		if ( '' === $event ) {
			$event = 'sidrena_event';
		}

		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'info', 'success', 'warning', 'error' ), true ) ) {
			$status = 'info';
		}

		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$encoded = self::encode_context( $context );
		$message = self::trim_bytes( sanitize_textarea_field( (string) $message ), self::MAX_MESSAGE_BYTES );

		return false !== $wpdb->insert(
			$table,
			array(
				'event_type' => $event,
				'status'     => $status,
				'message'    => $message,
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

		if ( ! self::table_exists() ) {
			return array();
		}

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

		if ( ! self::table_exists() ) {
			return 0;
		}

		return absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
	}

	public function prune() {
		global $wpdb;
		$table = self::table_name();

		if ( ! self::table_exists() ) {
			return;
		}

		$cutoff = wp_date( 'Y-m-d H:i:s', time() - ( self::RETENTION_DAYS * DAY_IN_SECONDS ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s",
				$cutoff
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.

		$row_count = self::count_rows();
		if ( $row_count <= self::MAX_ROWS ) {
			return;
		}

		$offset  = self::MAX_ROWS - 1;
		$keep_id = absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} ORDER BY id DESC LIMIT 1 OFFSET %d",
					$offset
				)
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.

		if ( $keep_id > 0 ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE id < %d",
					$keep_id
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
		}
	}

	private static function table_exists() {
		global $wpdb;
		$table = self::table_name();
		$like  = $wpdb->esc_like( $table );
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
	}

	private static function encode_context( $context ) {
		if ( empty( $context ) ) {
			return '';
		}

		$encoded = wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $encoded ) {
			$encoded = wp_json_encode( array( 'encoding_error' => true ) );
		}

		return self::trim_bytes( (string) $encoded, self::MAX_CONTEXT_BYTES );
	}

	private static function trim_bytes( $value, $max_bytes ) {
		$value     = (string) $value;
		$max_bytes = max( 1, absint( $max_bytes ) );

		if ( strlen( $value ) <= $max_bytes ) {
			return $value;
		}

		$value = substr( $value, 0, $max_bytes - 14 );
		return rtrim( $value ) . '… [skraćeno]';
	}
}
