<?php
/**
 * Bootstrap the Sidrena cijena plugin.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin controller.
 */
final class Brendigo_Sidrena_Cijena_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Boot the plugin after plugins_loaded.
	 */
	public static function init() {
		Brendigo_Sidrena_Cijena_Migration::maybe_migrate();

		if ( self::legacy_plugin_active() ) {
			add_action( 'admin_notices', array( __CLASS__, 'legacy_plugin_notice' ) );
			return;
		}

		self::instance();
	}

	/**
	 * Whether WooCommerce is loaded.
	 *
	 * @return bool
	 */
	public static function woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}


	/**
	 * Whether one of the two former source plugins is still active.
	 *
	 * @return bool
	 */
	private static function legacy_plugin_active() {
		return class_exists( 'Sidrena_Cijena_Plugin', false )
			|| defined( 'CJENIK_ENABLED_OPTION' )
			|| function_exists( 'cjenik_generate' );
	}

	/**
	 * Avoid duplicate price output while an old source plugin is still active.
	 */
	public static function legacy_plugin_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Brendigo Sidrena Cijena je spremna za prijelaz.', 'brendigo-sidrena-cijena' ) . '</strong> ';
		echo esc_html__( 'Deaktivirajte prethodne dodatke za sidrenu cijenu i cjenik kako biste izbjegli dvostruki prikaz i koristili samo objedinjeni Brendigo plugin. Podaci su migrirani ne-destruktivno.', 'brendigo-sidrena-cijena' );
		echo ' <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Otvori dodatke', 'brendigo-sidrena-cijena' ) . '</a></p></div>';
	}

	/**
	 * Capability that can manage plugin settings.
	 *
	 * @return string
	 */
	public static function manage_capability() {
		return self::woocommerce_active() ? 'manage_woocommerce' : 'manage_options';
	}

	/**
	 * Current user may manage the plugin.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage() {
		return current_user_can( self::manage_capability() );
	}

	/**
	 * Write an exception to the WooCommerce log and expose a forwarding hook.
	 *
	 * @param Throwable $exception Caught error. The message is not shown to visitors.
	 */
	public static function log_exception( $exception ) {
		if ( ! $exception instanceof Throwable ) {
			return;
		}

		$exception_message = sanitize_text_field( wp_strip_all_tags( (string) $exception->getMessage() ) );
		if ( function_exists( 'mb_substr' ) ) {
			$exception_message = mb_substr( $exception_message, 0, 1000 );
		} else {
			$exception_message = substr( $exception_message, 0, 1000 );
		}
		$message = 'Sidrena cijena [' . get_class( $exception ) . ']: ' . $exception_message;
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $message, array( 'source' => 'sidrena-cijena' ) );
		}

		/**
		 * Allow standalone installations to forward caught exceptions to their own logger.
		 *
		 * @param string    $message   Sanitized log message.
		 * @param Throwable $exception Original exception object.
		 */
		do_action( 'brendigo_sidrena_cijena_logged_exception', $message, $exception );
	}

	/**
	 * Get singleton.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Wire hooks.
	 */
	private function __construct() {
		Brendigo_Sidrena_Cijena_Settings::instance();
		Brendigo_Sidrena_Cijena_I18n::instance();
		Brendigo_Sidrena_Cijena_Audit_Log::instance();
		Brendigo_Sidrena_Cijena_Catalog::instance();
		Brendigo_Sidrena_Cijena_Export::instance();
		Brendigo_Sidrena_Cijena_Automation::instance();
		Brendigo_Sidrena_Cijena_Price_History::instance();
		Brendigo_Sidrena_Cijena_Notifications::instance();
		Brendigo_Sidrena_Cijena_Public_API::instance();
		Brendigo_Sidrena_Cijena_Frontend::instance();

		if ( is_admin() ) {
			Brendigo_Sidrena_Cijena_Admin::instance();
			Brendigo_Sidrena_Cijena_Bulk_Import::instance();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'sidrena-cijena', 'Brendigo_Sidrena_Cijena_CLI' );
		}

		if ( ! self::woocommerce_active() ) {
			return;
		}

		add_action( 'sidrena_cijena_snapshot_batch', array( 'Brendigo_Sidrena_Cijena_Activator', 'process_batch' ) );

		Brendigo_Sidrena_Cijena_Product_Meta::instance();
		Brendigo_Sidrena_Cijena_Compat::instance();
		Brendigo_Sidrena_Cijena_Woo_Blocks::instance();
		Brendigo_Sidrena_Cijena_Import_Export::instance();
	}

	/**
	 * Activation: defaults. Sidrena prices stay empty until someone fills them.
	 */
	public static function activate() {
		Brendigo_Sidrena_Cijena_Migration::run();
		Brendigo_Sidrena_Cijena_Settings::add_defaults();
		Brendigo_Sidrena_Cijena_Price_History::install();
		Brendigo_Sidrena_Cijena_Audit_Log::install();
		if ( self::woocommerce_active() && false === get_option( Brendigo_Sidrena_Cijena_Price_History::SEED_PAGE, false ) ) {
			add_option( Brendigo_Sidrena_Cijena_Price_History::SEED_PAGE, 1, '', false );
		}

		if ( self::woocommerce_active() ) {
			Brendigo_Sidrena_Cijena_Activator::queue_fill_prompt();
		}

		Brendigo_Sidrena_Cijena_Export::instance()->register_rewrite();
		Brendigo_Sidrena_Cijena_Export::instance()->sync_daily_schedule();
		Brendigo_Sidrena_Cijena_Automation::instance()->sync_health_schedule();
		Brendigo_Sidrena_Cijena_Export::instance()->queue_public_xml( 5, 'activation' );
		flush_rewrite_rules();
		update_option( Brendigo_Sidrena_Cijena_Export::REWRITE_OPTION, Brendigo_Sidrena_Cijena_Export::rewrite_fingerprint(), false );
	}

	/**
	 * Deactivation: clear scheduled jobs only. Product meta stays.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'sidrena_cijena_snapshot_batch' );
		wp_clear_scheduled_hook( Brendigo_Sidrena_Cijena_Price_History::SEED_HOOK );
		wp_clear_scheduled_hook( Brendigo_Sidrena_Cijena_Price_History::MANUAL_SEED_HOOK );
		wp_clear_scheduled_hook( Brendigo_Sidrena_Cijena_Export::DAILY_HOOK );
		wp_clear_scheduled_hook( Brendigo_Sidrena_Cijena_Export::GENERATE_HOOK );
		wp_clear_scheduled_hook( Brendigo_Sidrena_Cijena_Automation::HEALTH_HOOK );

		// Never leave stale generation/import locks behind after deactivation.
		delete_option( Brendigo_Sidrena_Cijena_Export::REBUILD_LOCK );
		delete_transient( Brendigo_Sidrena_Cijena_Export::REBUILD_LOCK );
		delete_transient( Brendigo_Sidrena_Cijena_Export::REBUILD_COOLDOWN );
		delete_transient( Brendigo_Sidrena_Cijena_Export::RETRY_TRANSIENT );
		delete_transient( Brendigo_Sidrena_Cijena_Automation::HEALTH_THROTTLE );
		delete_transient( Brendigo_Sidrena_Cijena_Automation::REASONS_TRANSIENT );
		Brendigo_Sidrena_Cijena_Bulk_Import::instance()->purge_all_jobs();

		flush_rewrite_rules();
	}

	/**
	 * Delete all plugin data: product meta, options, uploads, transients, cron.
	 */
	public static function purge_all_data() {
		$meta_keys = array(
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
		foreach ( $meta_keys as $meta_key ) {
			delete_post_meta_by_key( $meta_key );
		}

		delete_option( 'sidrena_cijena_settings' );
		delete_option( 'sidrena_cijena_snapshot_pending' );
		delete_option( 'sidrena_cijena_snapshot_page' );
		delete_option( 'sidrena_cijena_snapshot_offset' );
		delete_option( 'sidrena_cijena_snapshot_child' );
		delete_option( 'sidrena_cijena_fill_prompt' );
		delete_option( 'sidrena_cijena_public_xml' );
		delete_option( 'sidrena_cijena_rewrite_ver' );
		delete_option( Brendigo_Sidrena_Cijena_Catalog::OPTION_KEY );
		delete_option( Brendigo_Sidrena_Cijena_Catalog::NEXT_ID_OPTION );
		delete_option( Brendigo_Sidrena_Cijena_Upit::NEXT_ID_OPTION );
		delete_option( Brendigo_Sidrena_Cijena_Poslovnice::NEXT_ID_OPTION );
		delete_option( Brendigo_Sidrena_Cijena_Upit::OPTION_KEY );
		delete_option( Brendigo_Sidrena_Cijena_Poslovnice::OPTION_KEY );
		delete_option( Brendigo_Sidrena_Cijena_Migration::OPTION_VERSION );
		delete_option( Brendigo_Sidrena_Cijena_Automation::STATE_OPTION );
		delete_option( Brendigo_Sidrena_Cijena_Automation::RUNTIME_VERSION_OPTION );
		delete_option( Brendigo_Sidrena_Cijena_Automation::PENDING_OPTION );
		delete_option( Brendigo_Sidrena_Cijena_Export::HISTORY_OPTION );
		delete_option( Brendigo_Sidrena_Cijena_Audit_Log::SCHEMA_OPTION );
		delete_option( Brendigo_Sidrena_Cijena_Price_History::SEED_PAGE );
		delete_option( Brendigo_Sidrena_Cijena_Price_History::MANUAL_SEED_OFFSET );
		delete_option( Brendigo_Sidrena_Cijena_Notifications::STATE_OPTION );
		delete_option( Brendigo_Sidrena_Cijena_Export::REBUILD_LOCK );
		delete_transient( Brendigo_Sidrena_Cijena_Export::REBUILD_LOCK );
		delete_transient( Brendigo_Sidrena_Cijena_Export::REBUILD_COOLDOWN );
		delete_transient( Brendigo_Sidrena_Cijena_Export::RETRY_TRANSIENT );
		delete_transient( Brendigo_Sidrena_Cijena_Automation::HEALTH_THROTTLE );
		delete_transient( Brendigo_Sidrena_Cijena_Automation::REASONS_TRANSIENT );

		$uploads = wp_upload_dir( null, false );
		if ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) {
			$plugin_upload_dir = trailingslashit( $uploads['basedir'] ) . 'sidrena-cijena';
			if ( Brendigo_Sidrena_Cijena_Filesystem::is_dir( $plugin_upload_dir ) ) {
				Brendigo_Sidrena_Cijena_Filesystem::delete( $plugin_upload_dir, true );
			}
		}

		Brendigo_Sidrena_Cijena_Bulk_Import::instance()->purge_all_jobs();

		// This method is invoked only after the explicit administrator-only
		// irreversible full-purge confirmation; normal uninstall preserves history.
		global $wpdb;
		$table = Brendigo_Sidrena_Cijena_Price_History::table_name();
		// Only the explicitly confirmed full-purge path may drop this plugin-owned table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Data deletion requires a direct DDL query.
		$deleted = $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		if ( false === $deleted ) {
			throw new RuntimeException( 'Price-history table could not be purged.' );
		}
		$audit_table = Brendigo_Sidrena_Cijena_Audit_Log::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Only on administrator-confirmed permanent deletion.
		$audit_deleted = $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $audit_table ) );
		if ( false === $audit_deleted ) {
			throw new RuntimeException( 'Audit table could not be purged.' );
		}
		delete_option( Brendigo_Sidrena_Cijena_Audit_Log::SCHEMA_OPTION );
		delete_option( Brendigo_Sidrena_Cijena_Price_History::SCHEMA_OPTION );
		delete_option( Brendigo_Sidrena_Cijena_Catalog::NEXT_ID_OPTION );

		wp_clear_scheduled_hook( 'sidrena_cijena_snapshot_batch' );
		wp_clear_scheduled_hook( Brendigo_Sidrena_Cijena_Price_History::SEED_HOOK );
		wp_clear_scheduled_hook( Brendigo_Sidrena_Cijena_Price_History::MANUAL_SEED_HOOK );
		wp_clear_scheduled_hook( Brendigo_Sidrena_Cijena_Export::DAILY_HOOK );
		wp_clear_scheduled_hook( Brendigo_Sidrena_Cijena_Export::GENERATE_HOOK );
		wp_clear_scheduled_hook( Brendigo_Sidrena_Cijena_Automation::HEALTH_HOOK );
	}

}
