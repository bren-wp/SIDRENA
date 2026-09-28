<?php
/**
 * Regression guards for WordPress.org review blockers.
 *
 * @package Sidrena
 * @author brendigo
 */

$root = dirname( __DIR__, 2 );

function sidrena_review_regression_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

$admin      = file_get_contents( $root . '/includes/class-sidrena-admin.php' );
$bulk       = file_get_contents( $root . '/includes/class-sidrena-bulk.php' );
$activator  = file_get_contents( $root . '/includes/class-sidrena-activator.php' );
$compliance = file_get_contents( $root . '/includes/class-sidrena-compliance.php' );
$guard      = file_get_contents( $root . '/includes/sidrena-edition-guard.php' );
$wp_main    = file_get_contents( $root . '/editions/wordpress/sidrena-wordpress.php' );
$woo_main   = file_get_contents( $root . '/editions/woocommerce/sidrena-woocommerce.php' );

foreach ( array( $admin, $bulk, $activator, $compliance, $guard, $wp_main, $woo_main ) as $source ) {
	sidrena_review_regression_assert( false !== $source, 'Required review-regression source is unreadable.' );
}

sidrena_review_regression_assert(
	false === strpos( $admin, "_sidrena_service_price', true" )
	&& false !== strpos( $admin, "_sidrena_service_current_price', true" ),
	'Service audit must use _sidrena_service_current_price and never the obsolete _sidrena_service_price key.'
);

foreach ( array( 'anchor', 'quantity', 'unit_price' ) as $field ) {
	sidrena_review_regression_assert(
	false !== strpos( $bulk, "validated_nonnegative_decimal( isset( \$row['{$field}'] )" ),
	'Bulk editor must use strict nonnegative backend validation for ' . $field . '.'
	);
}

sidrena_review_regression_assert(
	false === strpos( $guard, 'deactivate_plugins' )
	&& false === strpos( $guard, "wp-admin/includes/plugin.php" ),
	'Conflict handling must never change activation state of another plugin.'
);

sidrena_review_regression_assert(
	false === strpos( $activator, 'Sidrena_Public::ensure_public_page' )
	&& false === strpos( $activator, 'Sidrena_Public::create_public_page' )
	&& false === strpos( $compliance, 'Sidrena_Public::ensure_public_page' )
	&& false === strpos( $compliance, 'Sidrena_Public::create_public_page' ),
	'Activation, upgrade and watchdog code must never create a public WordPress page.'
);

$guard_pos   = strpos( $activator, "if ( ! self::needs_upgrade( \$current_db, \$current_plugin ) )" );
$return_pos  = false !== $guard_pos ? strpos( $activator, 'return;', $guard_pos ) : false;
$migrate_pos = strpos( $activator, 'self::migrate_options();', $return_pos ? $return_pos : 0 );
sidrena_review_regression_assert(
	false !== $guard_pos && false !== $return_pos && false !== $migrate_pos && $return_pos < $migrate_pos,
	'maybe_upgrade() must return before migration/write/setup work when no upgrade is needed.'
);

foreach ( array( $wp_main, $woo_main ) as $entrypoint ) {
	sidrena_review_regression_assert(
	false !== strpos( $entrypoint, "__DIR__ . '/includes/sidrena-edition-guard.php'" )
	&& false === strpos( $entrypoint, "dirname( __DIR__, 2 ) . '/includes/sidrena-edition-guard.php'" ),
	'Entrypoint conflict guard include must be plugin-local.'
	);
}

fwrite( STDOUT, "SIDRENA WordPress.org blocker regression tests passed.\n" );
