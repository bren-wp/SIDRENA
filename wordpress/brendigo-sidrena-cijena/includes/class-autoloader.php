<?php
/**
 * Lightweight class-map autoloader.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads plugin classes on demand to keep normal requests lean.
 */
final class Brendigo_Sidrena_Cijena_Autoloader {

	/**
	 * Exact class/trait map.
	 *
	 * @var array<string,string>
	 */
	private static $map = array(
		'Brendigo_Sidrena_Cijena_Filesystem'      => 'includes/class-filesystem.php',
		'Brendigo_Sidrena_Cijena_Audit_Log'       => 'includes/class-audit-log.php',
		'Brendigo_Sidrena_Cijena_Price_History'   => 'includes/class-price-history.php',
		'Brendigo_Sidrena_Cijena_Notifications'  => 'includes/class-notifications.php',
		'Brendigo_Sidrena_Cijena_Public_API'     => 'includes/class-public-api.php',
		'Brendigo_Sidrena_Cijena_Woo_Blocks'     => 'includes/class-woo-blocks.php',
		'Brendigo_Sidrena_Cijena_Settings'        => 'includes/class-settings.php',
		'Brendigo_Sidrena_Cijena_I18n'            => 'includes/class-i18n.php',
		'Brendigo_Sidrena_Cijena_Money'           => 'includes/class-money.php',
		'Brendigo_Sidrena_Cijena_Row_Helpers'     => 'includes/trait-row-helpers.php',
		'Brendigo_Sidrena_Cijena_Catalog'         => 'includes/class-catalog.php',
		'Brendigo_Sidrena_Cijena_Upit'            => 'includes/class-upit.php',
		'Brendigo_Sidrena_Cijena_Poslovnice'      => 'includes/class-poslovnice.php',
		'Brendigo_Sidrena_Cijena_Migration'        => 'includes/class-migration.php',
		'Brendigo_Sidrena_Cijena_Feed_Rows'        => 'includes/class-feed-rows.php',
		'Brendigo_Sidrena_Cijena_Feed_Files'       => 'includes/class-feed-files.php',
		'Brendigo_Sidrena_Cijena_Archive'          => 'includes/class-archive.php',
		'Brendigo_Sidrena_Cijena_Feed_Routes'      => 'includes/class-feed-routes.php',
		'Brendigo_Sidrena_Cijena_Export'           => 'includes/class-export.php',
		'Brendigo_Sidrena_Cijena_Automation'       => 'includes/class-automation.php',
		'Brendigo_Sidrena_Cijena_Frontend'         => 'includes/class-frontend.php',
		'Brendigo_Sidrena_Cijena_Product_Meta'     => 'includes/class-product-meta.php',
		'Brendigo_Sidrena_Cijena_Activator'        => 'includes/class-activator.php',
		'Brendigo_Sidrena_Cijena_Compat'           => 'includes/class-compat.php',
		'Brendigo_Sidrena_Cijena_Import_Export'    => 'includes/class-import-export.php',
		'Brendigo_Sidrena_Cijena_Fields'           => 'includes/class-fields.php',
		'Brendigo_Sidrena_Cijena_Product_Fields'   => 'includes/class-product-fields.php',
		'Brendigo_Sidrena_Cijena_Admin_Save'       => 'includes/class-admin-save.php',
		'Brendigo_Sidrena_Cijena_Import_Parse'     => 'includes/class-import-parse.php',
		'Brendigo_Sidrena_Cijena_XLSX'             => 'includes/class-xlsx.php',
		'Brendigo_Sidrena_Cijena_Bulk_Import'      => 'includes/class-bulk-import.php',
		'Brendigo_Sidrena_Cijena_Admin'            => 'includes/class-admin.php',
		'Brendigo_Sidrena_Cijena_Woo_Bulk_Editor' => 'includes/class-woo-bulk-editor.php',
		'Brendigo_Sidrena_Cijena_CLI'              => 'includes/class-cli.php',
	);

	/**
	 * Register once.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Load one mapped plugin symbol.
	 *
	 * @param string $class Class or trait name.
	 */
	public static function autoload( $class ) {
		if ( ! isset( self::$map[ $class ] ) ) {
			return;
		}

		require_once BRENDIGO_SIDRENA_CIJENA_PATH . self::$map[ $class ];
	}
}
