<?php
/**
 * Automatic feed refresh, cron repair, and lightweight housekeeping.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keeps generated public data in sync with catalog changes without forcing a
 * full rebuild during every product save request.
 */
final class Brendigo_Sidrena_Cijena_Automation {

	const HEALTH_HOOK            = 'sidrena_cijena_automation_health';
	const STATE_OPTION           = 'sidrena_cijena_automation_state';
	const RUNTIME_VERSION_OPTION = 'sidrena_cijena_runtime_version';
	const HEALTH_THROTTLE        = 'sidrena_cijena_automation_health_throttle';
	const REASONS_TRANSIENT      = 'sidrena_cijena_rebuild_reasons';
	const PENDING_OPTION         = 'sidrena_cijena_pending_publication';
	const STALE_AFTER            = 93600; // 26 hours.
	const DAILY_GRACE           = 1800;  // 30 minutes after the configured publication time.

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Prevent recursive queueing while this request performs maintenance.
	 *
	 * @var bool
	 */
	private $suspended = false;

	/**
	 * Catalog-change reasons collected during the current request. They are
	 * persisted once at shutdown to avoid repeated option writes from the many
	 * WooCommerce hooks that can fire for a single product save.
	 *
	 * @var array<string,int>
	 */
	private $pending_reasons = array();

	/**
	 * Whether the shutdown persistence callback was registered.
	 *
	 * @var bool
	 */
	private $pending_persist_registered = false;

	/**
	 * Successful publication in the current request cancels deferred dirty-state
	 * persistence.
	 *
	 * @var bool
	 */
	private $pending_cleared = false;

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
	 * Register automatic refresh and health hooks.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'sync_health_schedule' ), 25 );
		add_action( 'init', array( $this, 'maybe_run_upgrade_maintenance' ), 35 );
		add_action( self::HEALTH_HOOK, array( $this, 'run_health_check' ) );
		add_action( 'admin_init', array( $this, 'maybe_run_health_check' ), 5 );
		add_action( 'wp_loaded', array( $this, 'maybe_run_without_wp_cron' ), 90 );

		// WooCommerce listeners are unnecessary for standalone WordPress sites.
		if ( Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ) {
			add_action( 'added_post_meta', array( $this, 'post_meta_changed' ), 20, 4 );
			add_action( 'updated_post_meta', array( $this, 'post_meta_changed' ), 20, 4 );
			add_action( 'deleted_post_meta', array( $this, 'post_meta_changed' ), 20, 4 );
			add_action( 'save_post_product', array( $this, 'product_post_saved' ), 30, 3 );
			add_action( 'save_post_product_variation', array( $this, 'product_post_saved' ), 30, 3 );
			add_action( 'trashed_post', array( $this, 'product_post_removed' ), 20, 1 );
			add_action( 'untrashed_post', array( $this, 'product_post_removed' ), 20, 1 );
			add_action( 'before_delete_post', array( $this, 'product_post_removed' ), 20, 1 );
			add_action( 'set_object_terms', array( $this, 'object_terms_changed' ), 20, 6 );
			add_action( 'edited_term', array( $this, 'brand_term_changed' ), 20, 3 );
			add_action( 'delete_term', array( $this, 'brand_term_changed' ), 20, 3 );
			add_action( 'woocommerce_new_product', array( $this, 'woocommerce_product_changed' ), 30, 2 );
			add_action( 'woocommerce_update_product', array( $this, 'woocommerce_product_changed' ), 30, 2 );
			add_action( 'woocommerce_delete_product', array( $this, 'woocommerce_product_changed' ), 30, 2 );
			add_action( 'woocommerce_trash_product', array( $this, 'woocommerce_product_changed' ), 30, 2 );
			add_action( 'woocommerce_untrash_product', array( $this, 'woocommerce_product_changed' ), 30, 2 );
			add_action( 'woocommerce_new_product_variation', array( $this, 'woocommerce_product_changed' ), 30, 2 );
			add_action( 'woocommerce_update_product_variation', array( $this, 'woocommerce_product_changed' ), 30, 2 );
			add_action( 'woocommerce_delete_product_variation', array( $this, 'woocommerce_product_changed' ), 30, 2 );
			add_action( 'woocommerce_product_set_stock', array( $this, 'woocommerce_stock_changed' ), 30, 1 );
			add_action( 'woocommerce_variation_set_stock', array( $this, 'woocommerce_stock_changed' ), 30, 1 );
			add_action( 'woocommerce_product_set_stock_status', array( $this, 'woocommerce_stock_changed' ), 30, 1 );
			add_action( 'woocommerce_variation_set_stock_status', array( $this, 'woocommerce_stock_changed' ), 30, 1 );
		}
		add_action( 'updated_option', array( $this, 'option_changed' ), 20, 3 );
	}

	/**
	 * Whether automatic catalog-triggered rebuilds are enabled.
	 *
	 * @return bool
	 */
	public function auto_rebuild_enabled() {
		return 'yes' === Brendigo_Sidrena_Cijena_Settings::instance()->get( 'auto_rebuild', 'yes' );
	}

	/**
	 * Temporarily suppress catalog-triggered rebuilds during a managed bulk job.
	 * Returns the previous state so callers can safely restore nested usage.
	 *
	 * @param bool $suspend Whether to suspend.
	 * @return bool Previous suspension state.
	 */
	public function suspend_rebuilds( $suspend = true ) {
		$previous        = $this->suspended;
		$this->suspended = (bool) $suspend;
		return $previous;
	}

	/**
	 * Whether automatic housekeeping is enabled.
	 *
	 * @return bool
	 */
	public function maintenance_enabled() {
		return 'yes' === Brendigo_Sidrena_Cijena_Settings::instance()->get( 'auto_maintenance', 'yes' );
	}

	/**
	 * Queue a deduplicated public-feed refresh for a catalog change.
	 *
	 * @param string $reason        Short machine-readable reason.
	 * @param bool   $managed_done  True only for the final step of a managed bulk request.
	 */
	public function queue_rebuild( $reason = 'catalog-change', $managed_done = false ) {
		if ( $this->suspended || ( ! $managed_done && $this->is_managed_batch_request() ) ) {
			return;
		}

		$this->mark_pending( $reason );

		// Scheduled-only mode intentionally keeps the current public version live
		// until the configured daily publication slot. The dirty state below makes
		// that pending work visible in wp-admin and survives restarts.
		if ( ! $this->auto_rebuild_enabled() ) {
			return;
		}

		Brendigo_Sidrena_Cijena_Export::instance()->queue_public_xml( 15, $reason );
	}

	/**
	 * Mark public output as older than the current catalog without doing a DB
	 * write for every WooCommerce hook in the same request.
	 *
	 * @param string $reason Machine-readable change reason.
	 */
	public function mark_pending( $reason = 'catalog-change' ) {
		$reason = sanitize_key( $reason );
		if ( '' === $reason ) {
			$reason = 'catalog-change';
		}

		$this->pending_cleared            = false;
		$this->pending_reasons[ $reason ] = time();

		if ( ! $this->pending_persist_registered ) {
			add_action( 'shutdown', array( $this, 'persist_pending_state' ), PHP_INT_MAX );
			$this->pending_persist_registered = true;
		}
	}

	/**
	 * Persist the request's pending-publication marker once.
	 */
	public function persist_pending_state() {
		if ( $this->pending_cleared || empty( $this->pending_reasons ) ) {
			return;
		}

		$state   = $this->get_pending_state();
		$now     = time();
		$reasons = isset( $state['reasons'] ) && is_array( $state['reasons'] ) ? $state['reasons'] : array();
		foreach ( $this->pending_reasons as $reason => $stamp ) {
			$reasons[ $reason ] = (int) $stamp;
		}
		if ( count( $reasons ) > 12 ) {
			asort( $reasons );
			$reasons = array_slice( $reasons, -12, null, true );
		}

		$first = isset( $state['since'] ) ? (int) $state['since'] : 0;
		update_option(
			self::PENDING_OPTION,
			array(
				'since'   => $first > 0 ? $first : $now,
				'updated' => $now,
				'reasons' => $reasons,
			),
			false
		);
	}

	/**
	 * Pending-publication state for admin diagnostics.
	 *
	 * @return array<string,mixed>
	 */
	public function get_pending_state() {
		$state = get_option( self::PENDING_OPTION, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Clear the dirty marker after a complete public publication succeeds.
	 */
	public function clear_pending() {
		$this->pending_cleared = true;
		$this->pending_reasons = array();
		delete_option( self::PENDING_OPTION );
	}

	/**
	 * Woo/native product post save. Product object and direct post edits both land here.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post.
	 * @param bool    $update  Whether updating.
	 */
	public function product_post_saved( $post_id, $post, $update ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
			return;
		}

		$this->queue_rebuild( 'product-save' );
	}

	/**
	 * Product deletion/trash/restore affects public visibility.
	 *
	 * @param int $post_id Post id.
	 */
	public function product_post_removed( $post_id ) {
		$post_type = get_post_type( $post_id );
		if ( in_array( $post_type, array( 'product', 'product_variation' ), true ) ) {
			$this->queue_rebuild( 'product-lifecycle' );
		}
	}

	/**
	 * Direct meta writes from REST, importers and third-party stock tools.
	 *
	 * @param int    $meta_id    Meta id.
	 * @param int    $object_id  Product id.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public function post_meta_changed( $meta_id, $object_id, $meta_key, $meta_value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! in_array( get_post_type( $object_id ), array( 'product', 'product_variation' ), true ) ) {
			return;
		}

		if ( ! in_array( (string) $meta_key, $this->feed_meta_keys(), true ) ) {
			return;
		}

		$this->queue_rebuild( 'product-meta' );
	}

	/**
	 * Brand-term updates can change the generated marka field.
	 *
	 * @param int          $object_id Object id.
	 * @param array|int    $terms     Terms.
	 * @param array        $tt_ids    Term taxonomy ids.
	 * @param string       $taxonomy  Taxonomy.
	 * @param bool         $append    Append.
	 * @param array        $old_tt_ids Old term taxonomy ids.
	 */
	public function object_terms_changed( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! in_array( get_post_type( $object_id ), array( 'product', 'product_variation' ), true ) ) {
			return;
		}

		$brand_taxonomy = (string) Brendigo_Sidrena_Cijena_Settings::instance()->get( 'brand_taxonomy', '' );
		if ( '' !== $brand_taxonomy && $brand_taxonomy === (string) $taxonomy ) {
			$this->queue_rebuild( 'brand-term' );
		}
	}

	/**
	 * A renamed or deleted configured brand term changes exported brand text even
	 * when no product post itself is saved.
	 *
	 * @param int    $term_id  Term id.
	 * @param int    $tt_id    Term taxonomy id.
	 * @param string $taxonomy Taxonomy.
	 */
	public function brand_term_changed( $term_id, $tt_id, $taxonomy ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$brand_taxonomy = (string) Brendigo_Sidrena_Cijena_Settings::instance()->get( 'brand_taxonomy', '' );
		if ( '' !== $brand_taxonomy && $brand_taxonomy === (string) $taxonomy ) {
			$this->queue_rebuild( 'brand-term-edit' );
		}
	}

	/**
	 * Woo CRUD hooks cover integrations that bypass the normal wp-admin post save
	 * path but still use the WooCommerce data store.
	 *
	 * @param int   $product_id Product id.
	 * @param mixed $product    Optional product object.
	 */
	public function woocommerce_product_changed( $product_id, $product = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( absint( $product_id ) > 0 ) {
			$this->queue_rebuild( 'woo-product-crud' );
		}
	}

	/**
	 * Woo stock APIs can be called without a normal product editor request.
	 *
	 * @param mixed $product Product object or id depending on Woo hook.
	 */
	public function woocommerce_stock_changed( $product ) {
		$product_id = is_object( $product ) && is_callable( array( $product, 'get_id' ) ) ? absint( $product->get_id() ) : absint( $product );
		if ( $product_id > 0 ) {
			$this->queue_rebuild( 'woo-stock' );
		}
	}

	/**
	 * Selected Woo/site options alter generated prices, URLs, or availability.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $value     New value.
	 */
	public function option_changed( $option, $old_value, $value ) {
		if ( $old_value === $value ) {
			return;
		}

		if ( in_array( (string) $option, array( 'timezone_string', 'gmt_offset' ), true ) ) {
			Brendigo_Sidrena_Cijena_Export::instance()->sync_daily_schedule( true );
			return;
		}

		$watched = array(
			'home',
			'siteurl',
			'woocommerce_currency',
			'woocommerce_prices_include_tax',
			'woocommerce_tax_display_shop',
			'woocommerce_manage_stock',
			'woocommerce_default_country',
			'woocommerce_store_address',
			'woocommerce_store_address_2',
			'woocommerce_store_city',
			'woocommerce_store_postcode',
		);

		if ( in_array( (string) $option, $watched, true ) ) {
			$this->queue_rebuild( 'shop-option' );
		}
	}

	/**
	 * Ensure the hourly maintenance event exists while maintenance is enabled.
	 */
	public function sync_health_schedule() {
		if ( ! $this->maintenance_enabled() ) {
			wp_clear_scheduled_hook( self::HEALTH_HOOK );
			return;
		}

		if ( wp_next_scheduled( self::HEALTH_HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::HEALTH_HOOK );
	}

	/**
	 * Run the lightweight health check at most every 30 minutes on wp-admin traffic.
	 * This repairs a missing schedule even when the normal hourly event was lost.
	 */
	/** Best-effort last-resort repair if WP-Cron is disabled. Independent hosting cron is still required for guaranteed timing. */
	public function maybe_run_without_wp_cron() {
		if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON || ! $this->maintenance_enabled() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		if ( 'yes' !== Brendigo_Sidrena_Cijena_Settings::instance()->get( 'daily_xml', 'yes' ) ) {
			return;
		}
		$export = Brendigo_Sidrena_Cijena_Export::instance();
		$slot = $export->get_previous_daily_timestamp();
		$meta = $export->get_public_meta();
		if ( ! $slot || time() < $slot + self::DAILY_GRACE || (int) ( isset( $meta['generated'] ) ? $meta['generated'] : 0 ) >= $slot ) {
			return;
		}
		if ( get_transient( 'brendigo_sidrena_no_cron_attempt' ) ) {
			return;
		}
		set_transient( 'brendigo_sidrena_no_cron_attempt', 1, 10 * MINUTE_IN_SECONDS );
		add_action( 'shutdown', array( $this, 'run_missed_without_wp_cron' ), 5 );
	}

	public function run_missed_without_wp_cron() {
		$export = Brendigo_Sidrena_Cijena_Export::instance();
		$meta = $export->get_public_meta();
		$slot = $export->get_previous_daily_timestamp();
		if ( $slot && (int) ( isset( $meta['generated'] ) ? $meta['generated'] : 0 ) < $slot ) {
			$export->publish_public_catalog( 'daily', false );
			$export->sync_daily_schedule( true );
		}
	}

	public function maybe_run_health_check() {
		if ( ! $this->maintenance_enabled() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( get_transient( self::HEALTH_THROTTLE ) ) {
			return;
		}

		set_transient( self::HEALTH_THROTTLE, 1, 30 * MINUTE_IN_SECONDS );
		$this->run_health_check();
	}

	/**
	 * Repair schedules, prune generated artifacts and queue a stale/missing feed.
	 * Heavy feed generation stays in the existing background generate hook.
	 *
	 * @return array<string,mixed>
	 */
	public function run_health_check() {
		if ( ! $this->maintenance_enabled() ) {
			return array();
		}

		$this->suspended = true;
		$queued          = false;
		$reason          = '';

		try {
			$export = Brendigo_Sidrena_Cijena_Export::instance();
			$export->sync_daily_schedule();
			$export->is_rebuild_locked(); // Also clears an expired lock.

			Brendigo_Sidrena_Cijena_Archive::instance()->prune_archive();
			if ( class_exists( 'Brendigo_Sidrena_Cijena_Price_History' ) ) {
				Brendigo_Sidrena_Cijena_Price_History::instance()->prune();
			}
			Brendigo_Sidrena_Cijena_Bulk_Import::instance()->purge_old_jobs();
			$stale_publish_files = Brendigo_Sidrena_Cijena_Feed_Files::instance()->cleanup_stale_publish_files();

			$daily_enabled = 'yes' === Brendigo_Sidrena_Cijena_Settings::instance()->get( 'daily_xml', 'yes' );
			if ( $this->auto_rebuild_enabled() || $daily_enabled ) {
				$reason = $this->feed_repair_reason();
				if ( '' !== $reason ) {
					// Health recovery must work in scheduled-only mode too. A disabled
					// instant-refresh toggle must never suppress recovery of a missed
					// daily publication or a missing/corrupt public file.
					$export->queue_public_xml( 10, $reason );
					$queued = true;
				}
			}

			$meta  = $export->get_public_meta();
			$last_daily = $daily_enabled ? $export->get_previous_daily_timestamp() : 0;
			if ( $last_daily > 0 && time() >= $last_daily + self::DAILY_GRACE && (int) ( isset( $meta['generated'] ) ? $meta['generated'] : 0 ) < $last_daily ) {
				do_action( 'brendigo_sidrena_publication_missed' );
			}
			$state = array(
				'last_check'     => time(),
				'last_reason'    => $reason,
				'queued_rebuild' => $queued ? 'yes' : 'no',
				'next_daily'     => $export->get_next_daily_timestamp(),
				'generated'           => isset( $meta['generated'] ) ? (int) $meta['generated'] : 0,
				'stale_files_removed' => absint( $stale_publish_files ),
			);
			update_option( self::STATE_OPTION, $state, false );

			return $state;
		} finally {
			$this->suspended = false;
		}
	}

	/**
	 * Run one-time post-update maintenance whenever plugin files change version.
	 */
	public function maybe_run_upgrade_maintenance() {
		$seen = (string) get_option( self::RUNTIME_VERSION_OPTION, '' );
		if ( BRENDIGO_SIDRENA_CIJENA_VERSION === $seen ) {
			return;
		}

		$this->suspended = true;
		try {
			Brendigo_Sidrena_Cijena_Export::instance()->sync_daily_schedule();
			$this->sync_health_schedule();
			if ( $this->maintenance_enabled() ) {
				Brendigo_Sidrena_Cijena_Bulk_Import::instance()->purge_old_jobs();
				Brendigo_Sidrena_Cijena_Archive::instance()->prune_archive();
			}
			if ( $this->auto_rebuild_enabled() ) {
				Brendigo_Sidrena_Cijena_Export::instance()->queue_public_xml( 10, 'plugin-update' );
			}
			update_option( self::RUNTIME_VERSION_OPTION, BRENDIGO_SIDRENA_CIJENA_VERSION, false );
		} finally {
			$this->suspended = false;
		}
	}


	/**
	 * Store a reason for a queued rebuild. Called by the export scheduler too, so
	 * activation/settings/CLI queues appear in the same diagnostic history.
	 *
	 * @param string $reason Reason.
	 */
	public function record_queue_reason( $reason ) {
		$this->remember_reason( $reason );
	}

	/**
	 * Store a short rolling list of causes that were coalesced into one rebuild.
	 *
	 * @param string $reason Reason.
	 */
	private function remember_reason( $reason ) {
		$reason = sanitize_key( $reason );
		if ( '' === $reason ) {
			return;
		}

		$reasons = get_transient( self::REASONS_TRANSIENT );
		$reasons = is_array( $reasons ) ? $reasons : array();
		$now     = time();
		if ( isset( $reasons[ $reason ] ) && ( $now - (int) $reasons[ $reason ] ) < 30 ) {
			return;
		}
		$reasons[ $reason ] = $now;
		if ( count( $reasons ) > 12 ) {
			asort( $reasons );
			$reasons = array_slice( $reasons, -12, null, true );
		}
		set_transient( self::REASONS_TRANSIENT, $reasons, 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Detect a missing or stale public feed without reading the complete catalog.
	 *
	 * @return string Empty when healthy.
	 */
	private function feed_repair_reason() {
		$export     = Brendigo_Sidrena_Cijena_Export::instance();
		$format     = $export->export_format();
		$files      = Brendigo_Sidrena_Cijena_Feed_Files::instance();
		$filesystem = Brendigo_Sidrena_Cijena_Filesystem::get();

		// Do not turn a filesystem-initialization problem into an endless rebuild loop.
		// The next maintenance pass can verify the files again when WP_Filesystem is available.
		if ( ! $filesystem ) {
			return '';
		}

		$feed_path = $export->get_public_path( $format, '' );
		$html_path = $files->get_cjenik_html_path( '' );
		if ( ! $filesystem->exists( $feed_path ) ) {
			return 'feed-missing';
		}
		if ( ! $filesystem->exists( $html_path ) ) {
			return 'html-cjenik-missing';
		}

		$meta     = $export->get_public_meta();
		$web_meta = isset( $meta['webshop'] ) && is_array( $meta['webshop'] ) ? $meta['webshop'] : $meta;
		if ( ! $this->published_files_match_meta( $filesystem, $feed_path, $html_path, $web_meta ) ) {
			return 'published-files-incomplete';
		}

		if ( Brendigo_Sidrena_Cijena_Settings::instance()->is_multi_poslovnica() ) {
			$outlet_meta = isset( $meta['outlets'] ) && is_array( $meta['outlets'] ) ? $meta['outlets'] : array();
			foreach ( Brendigo_Sidrena_Cijena_Poslovnice::instance()->with_products() as $outlet ) {
				$oznaka = isset( $outlet['oznaka'] ) ? (string) $outlet['oznaka'] : '';
				if ( '' === $oznaka ) {
					return 'outlet-feed-missing';
				}

				$outlet_feed = $export->get_public_path( $format, $oznaka );
				$outlet_html = $files->get_cjenik_html_path( $oznaka );
				if ( ! $filesystem->exists( $outlet_feed ) ) {
					return 'outlet-feed-missing';
				}
				if ( ! $filesystem->exists( $outlet_html ) ) {
					return 'outlet-html-missing';
				}

				$one_meta = isset( $outlet_meta[ $oznaka ] ) && is_array( $outlet_meta[ $oznaka ] ) ? $outlet_meta[ $oznaka ] : array();
				if ( ! $this->published_files_match_meta( $filesystem, $outlet_feed, $outlet_html, $one_meta ) ) {
					return 'outlet-files-incomplete';
				}
			}
		}

		$generated     = isset( $meta['generated'] ) ? (int) $meta['generated'] : 0;
		$daily_enabled = 'yes' === Brendigo_Sidrena_Cijena_Settings::instance()->get( 'daily_xml', 'yes' );

		if ( $daily_enabled ) {
			$expected = $export->get_previous_daily_timestamp();
			if ( $expected > 0 && time() >= ( $expected + self::DAILY_GRACE ) && $generated < $expected ) {
				return 'daily-publication-missed';
			}

			if ( $generated < 1 || ( time() - $generated ) > self::STALE_AFTER ) {
				return 'feed-stale';
			}
		}

		return '';
	}


	/**
	 * Compare generated files with metadata from the last successful publication.
	 * Size checks catch truncation cheaply; SHA-256 catches same-size corruption.
	 * Older metadata without hashes remains compatible and is upgraded on the next
	 * successful publication.
	 *
	 * @param WP_Filesystem_Base  $filesystem WordPress filesystem instance.
	 * @param string              $feed_path  Feed path.
	 * @param string              $html_path  HTML snapshot path.
	 * @param array<string,mixed> $meta       Publication metadata.
	 * @return bool
	 */
	private function published_files_match_meta( $filesystem, $feed_path, $html_path, $meta ) {
		if ( empty( $meta ) ) {
			return true;
		}

		$expected_feed = isset( $meta['bytes'] ) ? (int) $meta['bytes'] : 0;
		$expected_html = isset( $meta['html_bytes'] ) ? (int) $meta['html_bytes'] : 0;
		if ( $expected_feed > 0 && (int) $filesystem->size( $feed_path ) !== $expected_feed ) {
			return false;
		}
		if ( $expected_html > 0 && (int) $filesystem->size( $html_path ) !== $expected_html ) {
			return false;
		}

		$expected_feed_hash = isset( $meta['sha256'] ) ? strtolower( (string) $meta['sha256'] ) : '';
		$expected_html_hash = isset( $meta['html_sha256'] ) ? strtolower( (string) $meta['html_sha256'] ) : '';
		if ( '' !== $expected_feed_hash ) {
			$contents = $filesystem->get_contents( $feed_path );
			if ( ! is_string( $contents ) || ! hash_equals( $expected_feed_hash, hash( 'sha256', $contents ) ) ) {
				return false;
			}
		}
		if ( '' !== $expected_html_hash ) {
			$contents = $filesystem->get_contents( $html_path );
			if ( ! is_string( $contents ) || ! hash_equals( $expected_html_hash, hash( 'sha256', $contents ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Imports and snapshot batches already queue once at completion. Suppress the
	 * high-frequency meta hooks inside those requests to prevent partial rebuilds.
	 *
	 * @return bool
	 */
	private function is_managed_batch_request() {
		if ( wp_doing_ajax() ) {
			$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( in_array( $action, array( 'sidrena_cijena_import_run', 'sidrena_cijena_snapshot' ), true ) ) {
				return true;
			}
		}

		if ( wp_doing_cron() && 'yes' === get_option( 'sidrena_cijena_snapshot_pending', '' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Product meta fields that affect generated XML/CSV/HTML.
	 *
	 * @return array<int,string>
	 */
	private function feed_meta_keys() {
		return array(
			'_regular_price',
			'_sale_price',
			'_price',
			'_sku',
			'_stock',
			'_stock_status',
			'_manage_stock',
			'_backorders',
			'_tax_status',
			'_tax_class',
			'_global_unique_id',
			'_gtin',
			'_ean',
			'_upc',
			'_isbn',
			'_barcode',
			'barcode',
			'_alg_ean',
			'_wpm_gtin_code',
			'hwp_product_gtin',
			BRENDIGO_SIDRENA_CIJENA_META_PRICE,
			BRENDIGO_SIDRENA_CIJENA_META_DATE,
			BRENDIGO_SIDRENA_CIJENA_META_CJM,
			BRENDIGO_SIDRENA_CIJENA_META_CJM_SUFFIX,
			BRENDIGO_SIDRENA_CIJENA_META_OBLIK,
			BRENDIGO_SIDRENA_CIJENA_META_JEDINICA,
			BRENDIGO_SIDRENA_CIJENA_META_JEDINICA_CIJENA,
			BRENDIGO_SIDRENA_CIJENA_META_BARCODE,
			BRENDIGO_SIDRENA_CIJENA_META_VISIBILITY,
		);
	}
}
