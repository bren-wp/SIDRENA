<?php
/**
 * XML / CSV catalog and missing-price exports.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Downloadable pricelist export.
 */
class Brendigo_Sidrena_Cijena_Export {

	const BATCH_SIZE       = 100;
	const PUBLIC_STEM      = 'sidrena-cijena';
	const PUBLIC_OPTION    = 'sidrena_cijena_public_xml';
	const DAILY_HOOK       = 'sidrena_cijena_daily_xml';
	const GENERATE_HOOK    = 'sidrena_cijena_generate_xml';
	const ARCHIVE_SLUG     = 'arhiva-sidrene-cijene';
	const CJENIK_SLUG      = 'sidrena-cjenik';
	const CJENIK_DOM_TOKEN = 'sidrena-cjenik-dom-7f3a9c';
	const ARCHIVE_MAX      = 30;
	const REWRITE_OPTION   = 'sidrena_cijena_rewrite_ver';
	const REWRITE_VERSION  = '10';
	const REBUILD_LOCK     = 'sidrena_cijena_rebuild_lock';
	const REBUILD_COOLDOWN = 'sidrena_cijena_rebuild_cooldown';
	const RETRY_TRANSIENT  = 'sidrena_cijena_rebuild_retry_count';
	const HISTORY_OPTION   = 'sidrena_cijena_publication_history';
	const HISTORY_MAX      = 12;

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hook download, regenerate, rewrite, and public serve.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_rewrite' ) );
		add_action( 'admin_init', array( $this, 'maybe_flush_rewrites' ) );
		add_action( 'init', array( $this, 'sync_daily_schedule' ), 20 );
		add_action( 'init', array( $this, 'maybe_ensure_public_xml_on_init' ), 30 );
		add_action( self::DAILY_HOOK, array( $this, 'run_daily_regenerate' ) );
		add_action( self::GENERATE_HOOK, array( $this, 'run_queued_generate' ) );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'parse_request', array( $this, 'maybe_serve_public_xml' ), 0 );
		add_action( 'template_redirect', array( $this, 'maybe_serve_public_xml' ), 0 );
		add_action( 'admin_init', array( $this, 'handle_download' ) );
	}

	/**
	 * Pretty URLs: /sidrena-cijena.xml|.csv, /arhiva-sidrene-cijene, /sidrena-cjenik
	 */
	public function register_rewrite() {
		Brendigo_Sidrena_Cijena_Feed_Routes::instance()->register_rewrite();
	}

	/**
	 * Rewrite option value including Woo vs standalone mode.
	 *
	 * @return string
	 */
	public static function rewrite_fingerprint() {
		return Brendigo_Sidrena_Cijena_Feed_Routes::rewrite_fingerprint();
	}

	/**
	 * Flush permalinks once after adding archive routes.
	 */
	public function maybe_flush_rewrites() {
		Brendigo_Sidrena_Cijena_Feed_Routes::instance()->maybe_flush_rewrites();
	}

	/**
	 * Expose the public XML and archive query vars.
	 *
	 * @param array<int, string> $vars Query vars.
	 * @return array<int, string>
	 */
	public function register_query_var( $vars ) {
		return Brendigo_Sidrena_Cijena_Feed_Routes::instance()->register_query_var($vars);
	}

	/**
	 * Stream a file when the settings export form is submitted.
	 */

	public function handle_download() {
		if ( ! isset( $_POST['sidrena_cijena_action'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['sidrena_cijena_action'] ) );
		if ( ! in_array( $action, array( 'export_cjenik', 'export_missing', 'export_template' ), true ) ) {
			return;
		}

		if ( ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() ) {
			return;
		}

		check_admin_referer( 'sidrena_cijena_export', 'sidrena_cijena_export_nonce' );

		if ( 'export_template' === $action ) {
			Brendigo_Sidrena_Cijena_Feed_Files::instance()->send_import_template();
			return;
		}

		$requested_format = isset( $_POST['sidrena_cijena_export_format'] ) ? sanitize_key( wp_unslash( $_POST['sidrena_cijena_export_format'] ) ) : 'csv';
		$format           = 'xml' === $requested_format ? 'xml' : 'csv';
		$missing_only = ( 'export_missing' === $action );
		$public       = ! $missing_only;
		$rows         = $this->collect_rows( $missing_only, $public );
		$stamp        = gmdate( 'Y-m-d' );
		$basename     = $missing_only ? 'sidrena-nedostaje-' . $stamp : 'sidrena-cjenik-' . $stamp;

		if ( 'xml' === $format ) {
			Brendigo_Sidrena_Cijena_Feed_Files::instance()->send_xml( $basename . '.xml', $rows, $missing_only, $public );
		} else {
			Brendigo_Sidrena_Cijena_Feed_Files::instance()->send_csv( $basename . '.csv', $rows, $public );
		}
	}

	/**
	 * Collect catalog rows: name, sku, prices.
	 *
	 * @param bool $missing_only Only products/variations without sidrena price.
	 * @param bool $public       Published catalog for the public XML (adds id/url).
	 * @return array<int, array<string, string>>
	 */
	public function collect_rows( $missing_only = false, $public = false, $only_ids = null, $catalog_scope = null ) {
		return Brendigo_Sidrena_Cijena_Feed_Rows::instance()->collect_rows($missing_only, $public, $only_ids, $catalog_scope);
	}

	/**
	 * xml or csv from settings.
	 *
	 * @return string
	 */
	public function export_format() {
		return Brendigo_Sidrena_Cijena_Feed_Files::instance()->export_format();
	}

	/**
	 * Live public filename for a format.
	 *
	 * @param string $format xml|csv.
	 * @return string
	 */
	public function live_filename( $format = '', $oznaka = '' ) {
		return Brendigo_Sidrena_Cijena_Feed_Files::instance()->live_filename($format, $oznaka);
	}

	/**
	 * Archive name from the 18 September 2026 ministry example:
	 * prodavaonica_Ilica 150 Zagreb_P-01_104_01.10.2026_07:45.xml
	 *
	 * Spaces, hyphens, and the typed case stay. Date is DD.MM.YYYY and time
	 * is HH:MM in the site timezone. A Windows disk cannot store the colon,
	 * so that server writes 07-45 instead.
	 *
	 * @param string              $format    xml|csv.
	 * @param int                 $timestamp Unix time of publication, 0 = now.
	 * @param int                 $pohrana   Storage sequence, 0 = next unused number (does not consume).
	 * @param array<string,mixed>|null $outlet Outlet row, or null for the single shop.
	 * @return string
	 */
	public function legal_filename( $format = '', $timestamp = 0, $pohrana = 0, $outlet = null ) {
		return Brendigo_Sidrena_Cijena_Archive::instance()->legal_filename($format, $timestamp, $pohrana, $outlet);
	}

	/**
	 * Last published legal filename, or a preview of the next one.
	 *
	 * @param string $format xml|csv.
	 * @return string
	 */
	public function published_filename( $format = '' ) {
		return Brendigo_Sidrena_Cijena_Archive::instance()->published_filename($format);
	}

	/**
	 * Build an XML document string.
	 *
	 * @param array<int, array<string, string>> $rows           Rows.
	 * @param string                            $root           Root element name.
	 * @param bool                              $public_fields  Include id and url.
	 * @return string
	 */
	public function build_xml_string( $rows, $root, $public_fields = false ) {
		return Brendigo_Sidrena_Cijena_Feed_Files::instance()->build_xml_string($rows, $root, $public_fields);
	}

	/**
	 * Absolute directory for the public XML file.
	 *
	 * @return string
	 */
	public function get_public_dir() {
		return Brendigo_Sidrena_Cijena_Feed_Files::instance()->get_public_dir();
	}

	/**
	 * Absolute path of the live public feed.
	 *
	 * @param string $format xml|csv, empty = current setting.
	 * @return string
	 */
	public function get_public_path( $format = '', $oznaka = '' ) {
		return Brendigo_Sidrena_Cijena_Feed_Files::instance()->get_public_path($format, $oznaka);
	}

	/**
	 * Absolute path of the generated HTML table for the public page and shortcode.
	 *
	 * @param string $oznaka Outlet code, empty for the full catalog.
	 * @return string
	 */
	public function get_cjenik_html_path( $oznaka = '' ) {
		return Brendigo_Sidrena_Cijena_Feed_Files::instance()->get_cjenik_html_path($oznaka);
	}

	/**
	 * Public URL (pretty permalink, or query fallback).
	 *
	 * @param string $format xml|csv, empty = current setting.
	 * @return string
	 */
	public function get_public_url( $format = '', $oznaka = '' ) {
		return Brendigo_Sidrena_Cijena_Feed_Files::instance()->get_public_url($format, $oznaka);
	}

	/**
	 * Public archive index URL.
	 *
	 * @return string
	 */
	public function get_archive_url() {
		return Brendigo_Sidrena_Cijena_Feed_Files::instance()->get_archive_url();
	}

	/**
	 * Public HTML pricelist URL (standalone mode).
	 *
	 * @return string
	 */
	public function get_cjenik_url( $oznaka = '' ) {
		return Brendigo_Sidrena_Cijena_Feed_Files::instance()->get_cjenik_url( $oznaka );
	}

	/**
	 * Public URL for one archived feed file.
	 *
	 * @param string $name_or_date Legal filename or legacy d-m-Y.
	 * @param string $ext          xml|csv (legacy date URLs).
	 * @return string
	 */
	public function get_archive_file_url( $name_or_date, $ext = 'xml' ) {
		return Brendigo_Sidrena_Cijena_Feed_Files::instance()->get_archive_file_url($name_or_date, $ext);
	}

	/**
	 * Directory for dated archive XML files.
	 *
	 * @return string
	 */
	public function get_archive_dir() {
		return Brendigo_Sidrena_Cijena_Archive::instance()->get_archive_dir();
	}

	/**
	 * Copy today's live feed into the rolling 30-day archive.
	 *
	 * @return string|WP_Error Destination path or error.
	 */
	public function archive_public_xml() {
		return Brendigo_Sidrena_Cijena_Archive::instance()->archive_public_xml();
	}

	/**
	 * Archived feed files, newest first. Older days keep their original xml/csv type.
	 *
	 * @return array<int, array{date:string,ext:string,name:string,path:string,url:string,bytes:int}>
	 */
	public function get_archive_files() {
		return Brendigo_Sidrena_Cijena_Archive::instance()->get_archive_files();
	}

	/**
	 * Align the next daily publication with the user-selected local site time.
	 *
	 * A single event is used instead of a recurring 24-hour WP-Cron event so the
	 * local publication time does not drift by one hour across DST changes.
	 *
	 * @param bool $force Replace the existing event even when it looks valid.
	 */
	public function sync_daily_schedule( $force = false ) {
		if ( 'yes' !== Brendigo_Sidrena_Cijena_Settings::instance()->get( 'daily_xml' ) ) {
			wp_clear_scheduled_hook( self::DAILY_HOOK );
			return;
		}

		$event = function_exists( 'wp_get_scheduled_event' ) ? wp_get_scheduled_event( self::DAILY_HOOK ) : false;
		if ( ! $force && $event && empty( $event->schedule ) && $this->timestamp_is_daily_time( (int) $event->timestamp ) ) {
			return;
		}

		// Also removes the pre-1.2.7 recurring daily event during upgrade.
		wp_clear_scheduled_hook( self::DAILY_HOOK );
		wp_schedule_single_event( $this->next_daily_timestamp(), self::DAILY_HOOK );
	}

	/**
	 * Cron callback: rewrite the public catalog, archive it, then schedule the
	 * following local-calendar publication. The finally block keeps the schedule
	 * self-healing even if a future integration throws unexpectedly.
	 */
	public function run_daily_regenerate() {
		try {
			$this->publish_public_catalog( 'daily', true );
		} finally {
			$this->sync_daily_schedule( true );
		}
	}

	/**
	 * Queue a one-off public XML build (activation / first run).
	 */

	public function queue_public_xml( $delay = 5, $reason = '' ) {
		$reason = sanitize_key( $reason );
		if ( '' !== $reason && class_exists( 'Brendigo_Sidrena_Cijena_Automation' ) ) {
			Brendigo_Sidrena_Cijena_Automation::instance()->record_queue_reason( $reason );
		}

		$delay  = max( 1, min( 300, absint( $delay ) ) );
		$target = time() + $delay;
		$next   = wp_next_scheduled( self::GENERATE_HOOK );

		// Coalesce a burst of product/meta updates into one background rebuild.
		// If an existing event is unexpectedly far away, pull it forward rather
		// than adding a second generation job.
		if ( $next ) {
			if ( (int) $next > ( $target + 30 ) ) {
				wp_unschedule_event( $next, self::GENERATE_HOOK );
				wp_schedule_single_event( $target, self::GENERATE_HOOK );
			}
			return;
		}

		wp_schedule_single_event( $target, self::GENERATE_HOOK );
	}

	/**
	 * Cron callback for the one-off build.
	 */

	public function run_queued_generate() {
		$this->publish_public_catalog( 'queued', true );
	}

	/**
	 * Generate the public HTML/XML/CSV bundle and archive the same publication.
	 *
	 * All entry points use this method so manual, scheduled, queued and CLI
	 * publications share identical success/failure handling and diagnostics.
	 *
	 * @param string $source             Publication source: manual|daily|queued|cli.
	 * @param bool   $retry_on_failure   Queue a bounded retry after a background failure.
	 * @return array<string,mixed>|WP_Error
	 */
	public function publish_public_catalog( $source = 'manual', $retry_on_failure = false ) {
		$source  = sanitize_key( $source );
		$source  = in_array( $source, array( 'manual', 'daily', 'queued', 'cli' ), true ) ? $source : 'manual';
		$started = microtime( true );

		$result = $this->regenerate_public_xml();
		if ( is_wp_error( $result ) ) {
			$this->record_publication( 'failed', $source, $result, array(), $started );
			if ( $retry_on_failure ) {
				$this->schedule_background_retry();
			}
			return $result;
		}

		$archive = $this->archive_public_xml();
		if ( is_wp_error( $archive ) ) {
			$this->record_publication( 'failed', $source, $archive, $result, $started );
			if ( $retry_on_failure ) {
				$this->schedule_background_retry();
			}
			return $archive;
		}

		delete_transient( self::RETRY_TRANSIENT );
		$this->record_publication( 'success', $source, null, $result, $started );

		return $result;
	}

	/**
	 * Recent publication diagnostics, newest first.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_publication_history() {
		$history = get_option( self::HISTORY_OPTION, array() );
		return is_array( $history ) ? array_slice( $history, 0, self::HISTORY_MAX ) : array();
	}

	/**
	 * Store a bounded, non-autoloaded publication journal for administrators.
	 * Error details are reduced to the safe WP_Error code; raw exception data is
	 * never persisted here.
	 *
	 * @param string                    $status  success|failed.
	 * @param string                    $source  Publication source.
	 * @param WP_Error|null             $error   Optional failure.
	 * @param array<string,mixed>       $result  Generation metadata.
	 * @param float                     $started microtime(true) captured before generation.
	 */
	private function record_publication( $status, $source, $error, $result, $started ) {
		$history = $this->get_publication_history();
		$entry   = array(
			'time'        => time(),
			'status'      => 'success' === $status ? 'success' : 'failed',
			'source'      => sanitize_key( $source ),
			'format'      => isset( $result['format'] ) ? sanitize_key( (string) $result['format'] ) : $this->export_format(),
			'count'       => isset( $result['count'] ) ? absint( $result['count'] ) : 0,
			'duration_ms' => max( 0, (int) round( ( microtime( true ) - (float) $started ) * 1000 ) ),
			'error'       => $error instanceof WP_Error ? sanitize_key( $error->get_error_code() ) : '',
		);

		array_unshift( $history, $entry );
		$history = array_slice( $history, 0, self::HISTORY_MAX );
		update_option( self::HISTORY_OPTION, $history, false );
		do_action( 'brendigo_sidrena_publication_result', $entry['status'], $entry['source'], $entry['error'] );
	}

	/**
	 * Retry a failed background generation/archive at most three times.
	 */
	private function schedule_background_retry() {
		$attempt = (int) get_transient( self::RETRY_TRANSIENT ) + 1;
		if ( $attempt > 3 ) {
			delete_transient( self::RETRY_TRANSIENT );
			return;
		}

		set_transient( self::RETRY_TRANSIENT, $attempt, HOUR_IN_SECONDS );
		$this->queue_public_xml( min( 300, 60 * $attempt ), 'retry-' . $attempt );
	}

	/**
	 * Public feed hits never build the catalog. A missing file is queued once.
	 */
	public function maybe_ensure_public_xml_on_init() {
		Brendigo_Sidrena_Cijena_Feed_Routes::instance()->maybe_ensure_public_xml_on_init();
	}

	/**
	 * One catalog rebuild at a time. The lock expires if the process dies.
	 *
	 * @return bool
	 */
	private function acquire_rebuild_lock() {
		if ( $this->is_rebuild_locked() ) {
			return false;
		}

		// add_option() is atomic because option_name is unique, unlike get+set transient.
		return add_option( self::REBUILD_LOCK, time(), '', 'no' );
	}

	/**
	 * Whether another request currently owns the public-feed rebuild lock.
	 * Stale locks are cleared automatically after 15 minutes.
	 *
	 * @return bool
	 */
	public function is_rebuild_locked() {
		$stamp = (int) get_option( self::REBUILD_LOCK, 0 );
		if ( $stamp <= 0 ) {
			// Remove the pre-1.2 transient lock if it still exists after an upgrade.
			delete_transient( self::REBUILD_LOCK );
			return false;
		}

		if ( ( time() - $stamp ) >= 15 * MINUTE_IN_SECONDS ) {
			delete_option( self::REBUILD_LOCK );
			return false;
		}

		return true;
	}

	/**
	 * Release the database-backed rebuild lock.
	 */
	private function release_rebuild_lock() {
		delete_option( self::REBUILD_LOCK );
		delete_transient( self::REBUILD_LOCK );
	}

	/**
	 * Build the public XML if the file is missing. Wp-admin only; public requests queue cron.
	 *
	 * @return array<string, mixed>|WP_Error|true True when the file already exists.
	 */

	public function maybe_ensure_public_xml() {
		if ( ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() ) {
			Brendigo_Sidrena_Cijena_Feed_Routes::instance()->schedule_public_rebuild_if_missing();
			return true;
		}

		if ( Brendigo_Sidrena_Cijena_Settings::instance()->is_multi_poslovnica() ) {
			$oznaka = Brendigo_Sidrena_Cijena_Feed_Routes::instance()->requested_oznaka();
			if ( '' === $oznaka ) {
				return true;
			}

			if ( Brendigo_Sidrena_Cijena_Filesystem::exists( $this->get_public_path( $this->export_format(), $oznaka ) ) ) {
				return true;
			}

			return $this->regenerate_one_outlet( $oznaka );
		}

		if ( Brendigo_Sidrena_Cijena_Filesystem::exists( $this->get_public_path() ) ) {
			return true;
		}

		return $this->regenerate_public_xml();
	}

	public function get_next_daily_timestamp() {
		$next = wp_next_scheduled( self::DAILY_HOOK );
		return $next ? (int) $next : 0;
	}

	/**
	 * Whether a timestamp belongs to the configured publication slot for its
	 * local calendar day. Comparing against a reconstructed local date also
	 * handles DST-normalized times such as a nonexistent 02:30 spring slot.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return bool
	 */
	private function timestamp_is_daily_time( $timestamp ) {
		$timestamp = (int) $timestamp;
		if ( $timestamp <= 0 ) {
			return false;
		}

		$dt = new DateTimeImmutable( '@' . $timestamp );
		$dt = $dt->setTimezone( wp_timezone() );
		return $timestamp === $this->daily_timestamp_for_date( $dt->format( 'Y-m-d' ) );
	}

	/**
	 * Next configured daily publication in the WordPress timezone.
	 *
	 * @return int
	 */
	private function next_daily_timestamp() {
		$tz    = wp_timezone();
		$now   = new DateTimeImmutable( 'now', $tz );
		$today = $now->format( 'Y-m-d' );
		$next  = $this->daily_timestamp_for_date( $today );

		if ( $next <= $now->getTimestamp() ) {
			$tomorrow = $now->modify( '+1 day' )->format( 'Y-m-d' );
			$next     = $this->daily_timestamp_for_date( $tomorrow );
		}

		return $next;
	}

	/**
	 * Most recent configured publication boundary, including today when its time
	 * has already passed. Used by health checks to recover a missed daily run.
	 *
	 * @return int
	 */
	public function get_previous_daily_timestamp() {
		$tz    = wp_timezone();
		$now   = new DateTimeImmutable( 'now', $tz );
		$today = $this->daily_timestamp_for_date( $now->format( 'Y-m-d' ) );

		if ( $today <= $now->getTimestamp() ) {
			return $today;
		}

		$yesterday = $now->modify( '-1 day' )->format( 'Y-m-d' );
		return $this->daily_timestamp_for_date( $yesterday );
	}

	/**
	 * Convert one local calendar date plus the configured HH:MM into UTC epoch.
	 *
	 * @param string $date Local Y-m-d date.
	 * @return int
	 */
	private function daily_timestamp_for_date( $date ) {
		$time = Brendigo_Sidrena_Cijena_Settings::instance()->daily_time();
		$dt   = new DateTimeImmutable( $date . ' ' . $time . ':00', wp_timezone() );
		return $dt->getTimestamp();
	}

	/**
	 * Last public XML generation meta.
	 *
	 * @return array<string, mixed>
	 */

	public function get_public_meta() {
		$stored = get_option( self::PUBLIC_OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Write the public catalog XML to uploads.
	 *
	 * @return array<string, mixed>|WP_Error
	 */

	public function regenerate_public_xml() {
		if ( ! $this->acquire_rebuild_lock() ) {
			return new WP_Error( 'sidrena_xml_busy', __( 'Javni cjenik se već generira.', 'brendigo-sidrena-cijena' ) );
		}

		try {
			if ( Brendigo_Sidrena_Cijena_Settings::instance()->is_multi_poslovnica() ) {
				$result = $this->regenerate_poslovnice_feeds();
			} else {
				if ( Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() && ! function_exists( 'wc_get_products' ) ) {
					return new WP_Error( 'sidrena_xml_wc', __( 'WooCommerce nije spreman za generiranje cjenika.', 'brendigo-sidrena-cijena' ) );
				}

				try {
					$rows   = $this->collect_rows( false, true, null, null );
					$result = Brendigo_Sidrena_Cijena_Feed_Files::instance()->write_feed_file( $rows, '' );
					if ( ! is_wp_error( $result ) ) {
						update_option( self::PUBLIC_OPTION, $result );
					}
				} catch ( Throwable $e ) {
					$result = $this->feed_failure( $e );
				}
			}

			if ( ! is_wp_error( $result ) && class_exists( 'Brendigo_Sidrena_Cijena_Automation' ) ) {
				Brendigo_Sidrena_Cijena_Automation::instance()->clear_pending();
			}

			return $result;
		} finally {
			$this->release_rebuild_lock();
		}
	}

	/**
	 * Rebuild every outlet feed that has products.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function regenerate_poslovnice_feeds() {
		if ( Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() && ! function_exists( 'wc_get_products' ) ) {
			return new WP_Error( 'sidrena_xml_wc', __( 'WooCommerce nije spreman za generiranje cjenika.', 'brendigo-sidrena-cijena' ) );
		}

		try {
			$prev    = $this->get_public_meta();
			$stored  = ( isset( $prev['outlets'] ) && is_array( $prev['outlets'] ) ) ? $prev['outlets'] : array();
			$total   = 0;
			$latest  = 0;

			$bundle   = Brendigo_Sidrena_Cijena_Feed_Rows::instance()->collect_public_by_scope();
			$web_rows = isset( $bundle['webshop'] ) ? $bundle['webshop'] : array();
			$web      = Brendigo_Sidrena_Cijena_Feed_Files::instance()->write_feed_file( $web_rows, '' );
			if ( is_wp_error( $web ) ) {
				return $web;
			}
			$total  += (int) $web['count'];
			$latest  = max( $latest, (int) $web['generated'] );

			$scoped = isset( $bundle['outlets'] ) && is_array( $bundle['outlets'] ) ? $bundle['outlets'] : array();
			foreach ( Brendigo_Sidrena_Cijena_Poslovnice::instance()->with_products() as $outlet ) {
				$rows   = isset( $scoped[ $outlet['oznaka'] ] ) ? $scoped[ $outlet['oznaka'] ] : null;
				$result = $this->write_outlet_feed( $outlet, $stored, $rows );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				$stored[ $outlet['oznaka'] ] = $result;
				$total                      += (int) $result['count'];
				$latest                      = max( $latest, (int) $result['generated'] );
			}

			$meta = array(
				'generated' => $latest > 0 ? $latest : time(),
				'count'     => $total,
				'format'    => $this->export_format(),
				'filename'  => isset( $prev['filename'] ) ? $prev['filename'] : '',
				'pohrana'   => isset( $prev['pohrana'] ) ? $prev['pohrana'] : 0,
				'outlets'   => $stored,
			);
			$meta['webshop'] = $web;
			update_option( self::PUBLIC_OPTION, $meta );

			return $meta;
		} catch ( Throwable $e ) {
			return $this->feed_failure( $e );
		}
	}

	/**
	 * Rebuild one outlet when its public URL is opened and the file is missing.
	 *
	 * @param string $oznaka Object code.
	 * @return array<string, mixed>|WP_Error|true
	 */

	private function regenerate_one_outlet( $oznaka ) {
		$outlet = Brendigo_Sidrena_Cijena_Poslovnice::instance()->get_by_oznaka( $oznaka );
		if ( ! is_array( $outlet ) ) {
			return new WP_Error( 'sidrena_poslovnica_missing', __( 'Poslovnica nema objavljen cjenik.', 'brendigo-sidrena-cijena' ) );
		}

		try {
			$prev   = $this->get_public_meta();
			$stored = ( isset( $prev['outlets'] ) && is_array( $prev['outlets'] ) ) ? $prev['outlets'] : array();
			$result = $this->write_outlet_feed( $outlet, $stored );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$stored[ $outlet['oznaka'] ] = $result;
			$prev['generated']           = (int) $result['generated'];
			$prev['format']              = $this->export_format();
			$prev['outlets']             = $stored;
			update_option( self::PUBLIC_OPTION, $prev );

			return $result;
		} catch ( Throwable $e ) {
			return $this->feed_failure( $e );
		}
	}

	/**
	 * Log a feed failure and return a fixed message safe to show in wp-admin.
	 *
	 * @param Throwable $exception Caught error.
	 * @return WP_Error
	 */

	private function feed_failure( $exception ) {
		Brendigo_Sidrena_Cijena_Plugin::log_exception( $exception );

		return new WP_Error( 'sidrena_xml_fatal', __( 'Javni cjenik nije mogao biti generiran.', 'brendigo-sidrena-cijena' ) );
	}

	/**
	 * Write one outlet feed and keep its previous archive filename.
	 *
	 * @param array<string, mixed>             $outlet Outlet.
	 * @param array<string, array<string, mixed>> $stored Previous per-outlet meta.
	 * @param array<int, array<string, string>>|null $rows Prebuilt rows, or null to collect.
	 * @return array<string, mixed>|WP_Error
	 */
	private function write_outlet_feed( $outlet, $stored, $rows = null ) {
		if ( null === $rows ) {
			$rows = $this->collect_rows( false, true, isset( $outlet['products'] ) ? $outlet['products'] : array(), $outlet['oznaka'] );
		}
		$result = Brendigo_Sidrena_Cijena_Feed_Files::instance()->write_feed_file( $rows, $outlet['oznaka'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$previous            = isset( $stored[ $outlet['oznaka'] ] ) && is_array( $stored[ $outlet['oznaka'] ] ) ? $stored[ $outlet['oznaka'] ] : array();
		$result['filename']  = isset( $previous['filename'] ) ? (string) $previous['filename'] : '';
		$result['pohrana']   = isset( $previous['pohrana'] ) ? (int) $previous['pohrana'] : 0;

		return $result;
	}

	/**
	 * Last generation meta for one outlet.
	 *
	 * @param string $oznaka Object code.
	 * @return array<string, mixed>
	 */
	public function get_outlet_meta( $oznaka ) {
		return Brendigo_Sidrena_Cijena_Archive::instance()->get_outlet_meta($oznaka);
	}

	/**
	 * Serve public XML, archive index, or an archived file.
	 */
	public function maybe_serve_public_xml() {
		Brendigo_Sidrena_Cijena_Feed_Routes::instance()->maybe_serve_public_xml();
	}

	/**
	 * File list for the [sidrena-arhiva] shortcode. Name and date only.
	 *
	 * @param string $oznaka Object code, empty for every file.
	 * @return string
	 */
	public function get_archive_embed_html( $oznaka = '' ) {
		return Brendigo_Sidrena_Cijena_Archive::instance()->get_archive_embed_html( $oznaka );
	}

	/**
	 * Searchable pricelist table for the public page and the [sidrena-cjenik] shortcode.
	 *
	 * Reads the file written with the public feed. A missing file queues one rebuild.
	 *
	 * @param string                                  $oznaka Outlet code, empty for the full list.
	 * @param array<int, array<string, string>>|null $rows   Already built rows. Public calls leave this null.
	 * @return string
	 */
	public function get_cjenik_embed_html( $oznaka = '', $rows = null ) {
		return Brendigo_Sidrena_Cijena_Feed_Files::instance()->get_cjenik_embed_html($oznaka, $rows);
	}
}
