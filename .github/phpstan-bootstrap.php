<?php
/**
 * Sidrena PHPStan bootstrap.
 *
 * @package Sidrena
 * @author brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

namespace {
	$composer_home = getenv( 'COMPOSER_HOME' );
	if ( ! is_string( $composer_home ) || '' === $composer_home ) {
		throw new \RuntimeException( 'COMPOSER_HOME is required for SIDRENA static analysis.' );
	}

	$stubs = array(
		$composer_home . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php',
		$composer_home . '/vendor/php-stubs/woocommerce-stubs/woocommerce-stubs.php',
		$composer_home . '/vendor/php-stubs/woocommerce-stubs/woocommerce-packages-stubs.php',
	);

	foreach ( $stubs as $stub ) {
		if ( is_file( $stub ) ) {
			require_once $stub;
		}
	}

	$runtime_constants = array(
		'ARRAY_A'           => 'ARRAY_A',
		'OBJECT'            => 'OBJECT',
		'MINUTE_IN_SECONDS' => 60,
		'HOUR_IN_SECONDS'   => 3600,
		'DAY_IN_SECONDS'    => 86400,
		'MB_IN_BYTES'       => 1048576,
		'SIDRENA_DIR'       => dirname( __DIR__ ) . '/',
		'SIDRENA_URL'       => 'https://example.test/wp-content/plugins/sidrena/',
	);

	foreach ( $runtime_constants as $name => $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	if ( ! class_exists( 'WP_CLI' ) ) {
		/**
		 * Minimal WP-CLI surface required only by PHPStan.
		 */
		class WP_CLI {
			public static function add_command( $name, $callable ) {
				unset( $name, $callable );
			}

			public static function success( $message ) {
				unset( $message );
			}

			public static function error( $message ) {
				unset( $message );
			}
		}
	}
}

namespace WP_CLI\Utils {
	if ( ! function_exists( __NAMESPACE__ . '\\format_items' ) ) {
		/**
		 * Minimal WP-CLI formatter stub required only by PHPStan.
		 */
		function format_items( $format, $items, $fields ) {
			unset( $format, $items, $fields );
		}
	}
}
