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

final class Sidrena_Plugin {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function run() {
		add_filter( 'map_meta_cap', array( 'Sidrena_Utils', 'map_admin_capability' ), 10, 4 );
		add_action( 'init', array( 'Sidrena_Utils', 'register_translation_strings' ), 20 );

		Sidrena_Audit::instance()->hooks();
		Sidrena_Compliance::instance()->hooks();
		Sidrena_Service_History::instance()->hooks();
		Sidrena_Services::instance()->hooks();
		Sidrena_Pricelist::instance()->hooks();
		Sidrena_REST::instance()->hooks();
		Sidrena_Public::instance()->hooks();

		if ( Sidrena_Utils::is_wordpress_edition() ) {
			Sidrena_Standalone::instance()->hooks();
		} elseif ( Sidrena_Utils::is_woocommerce_active() ) {
			Sidrena_History::instance()->hooks();
			Sidrena_Location_History::instance()->hooks();
			Sidrena_Products::instance()->hooks();
			Sidrena_Woo_Import_Export::instance()->hooks();
			Sidrena_Compatibility::instance()->hooks();
		}

		Sidrena_CLI::register();

		if ( is_admin() ) {
			if ( Sidrena_Utils::is_woocommerce_edition() ) {
				Sidrena_Bulk::instance()->hooks();
			}
			Sidrena_Site_Health::instance()->hooks();
			Sidrena_Admin::instance()->hooks();
		}
	}
}
