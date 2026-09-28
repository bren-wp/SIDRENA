<?php
/**
 * Sidrena PHPStan bootstrap.
 *
 * @package Sidrena
 * @author brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

$composer_home = getenv( 'COMPOSER_HOME' );
if ( ! is_string( $composer_home ) || '' === $composer_home ) {
	throw new RuntimeException( 'COMPOSER_HOME is required for SIDRENA static analysis.' );
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
