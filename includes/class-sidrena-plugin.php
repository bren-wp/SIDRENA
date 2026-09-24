<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sidrena_Plugin {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function run() {
		add_action( 'init', array( 'Sidrena_Utils', 'register_translation_strings' ), 20 );

		// Core Sidrena works on a plain WordPress installation. WooCommerce is an
		// optional integration layer and must never be required for activation,
		// admin visibility, standalone products, services or public cjenici.
		Sidrena_Audit::instance()->hooks();
		Sidrena_Service_History::instance()->hooks();
		Sidrena_Location_History::instance()->hooks();
		Sidrena_Standalone::instance()->hooks();
		Sidrena_Services::instance()->hooks();
		Sidrena_Pricelist::instance()->hooks();
		Sidrena_REST::instance()->hooks();
		Sidrena_Public::instance()->hooks();

		if ( Sidrena_Utils::is_woocommerce_active() ) {
			Sidrena_History::instance()->hooks();
			Sidrena_Products::instance()->hooks();
			Sidrena_Woo_Import_Export::instance()->hooks();
			Sidrena_Compatibility::instance()->hooks();
		}

		Sidrena_CLI::register();

		if ( is_admin() ) {
			Sidrena_Bulk::instance()->hooks();
			Sidrena_Site_Health::instance()->hooks();
			Sidrena_Admin::instance()->hooks();
		}
	}
}
