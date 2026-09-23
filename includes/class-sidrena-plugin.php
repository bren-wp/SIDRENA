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
		Sidrena_Audit::instance()->hooks();
		Sidrena_History::instance()->hooks();
		Sidrena_Service_History::instance()->hooks();
		Sidrena_Location_History::instance()->hooks();
		Sidrena_Products::instance()->hooks();
		Sidrena_Services::instance()->hooks();
		Sidrena_Pricelist::instance()->hooks();
		Sidrena_REST::instance()->hooks();
		Sidrena_Public::instance()->hooks();

		Sidrena_CLI::register();

		if ( is_admin() ) {
			Sidrena_Bulk::instance()->hooks();
			Sidrena_Site_Health::instance()->hooks();
			Sidrena_Admin::instance()->hooks();
		}
	}
}
