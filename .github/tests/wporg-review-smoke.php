<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

$root = dirname( __DIR__, 2 );

function sidrena_wporg_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

$wp_main  = file_get_contents( $root . '/editions/wordpress/sidrena-wordpress.php' );
$woo_main = file_get_contents( $root . '/editions/woocommerce/sidrena-woocommerce.php' );
$wp_readme = file_get_contents( $root . '/editions/wordpress/readme.txt' );
$woo_readme = file_get_contents( $root . '/editions/woocommerce/readme.txt' );
$edition_guard = file_get_contents( $root . '/includes/sidrena-edition-guard.php' );
$plugin_check = file_get_contents( $root . '/.github/workflows/plugin-check.yml' );
$release = file_get_contents( $root . '/.github/workflows/release.yml' );

foreach ( array( $wp_main, $woo_main, $wp_readme, $woo_readme, $edition_guard, $plugin_check, $release ) as $source ) {
	sidrena_wporg_assert( false !== $source, 'WordPress.org review guard could not read a required source file.' );
}

sidrena_wporg_assert(
	false !== strpos( $wp_main, 'Plugin Name: brendigo SIDRENA – sidrene cijene i digitalni cjenici' )
	&& false !== strpos( $woo_main, 'Plugin Name: brendigo SIDRENA – sidrene cijene i cjenici za WooCommerce' ),
	'Final Croatian plugin display names changed.'
);

sidrena_wporg_assert(
	false !== strpos( $wp_main, 'Author: brendigo' )
	&& false !== strpos( $woo_main, 'Author: brendigo' )
	&& false === strpos( $wp_main, 'Author: Brendigo' )
	&& false === strpos( $woo_main, 'Author: Brendigo' ),
	'WordPress.org author casing must remain lowercase brendigo.'
);

sidrena_wporg_assert(
	false !== strpos( $wp_main, 'Text Domain: brendigo-sidrena-digitalni-cjenici' )
	&& false !== strpos( $woo_main, 'Text Domain: brendigo-sidrena-cjenici' )
	&& false !== strpos( $plugin_check, 'slug: brendigo-sidrena-digitalni-cjenici' )
	&& false !== strpos( $plugin_check, 'slug: brendigo-sidrena-cjenici' )
	&& false !== strpos( $release, 'slug: brendigo-sidrena-digitalni-cjenici' )
	&& false !== strpos( $release, 'slug: brendigo-sidrena-cjenici' ),
	'Public text domains and Plugin Check/release slugs must stay synchronized.'
);

sidrena_wporg_assert(
	false !== strpos( $wp_readme, '=== brendigo SIDRENA – sidrene cijene i digitalni cjenici ===' )
	&& false !== strpos( $woo_readme, '=== brendigo SIDRENA – sidrene cijene i cjenici za WooCommerce ===' ),
	'WordPress.org readme titles must match plugin headers.'
);

foreach ( array( $wp_readme, $woo_readme ) as $readme ) {
	preg_match( '/^Tags:\s*(.+)$/mi', $readme, $tags_match );
	$tags = isset( $tags_match[1] ) ? array_filter( array_map( 'trim', explode( ',', $tags_match[1] ) ) ) : array();
	sidrena_wporg_assert( count( $tags ) > 0 && count( $tags ) <= 5, 'WordPress.org readme must contain at most five focused tags.' );
	sidrena_wporg_assert( false !== strpos( $readme, 'docs/SIDRENA-UPUTE.pdf' ), 'Detailed PDF manual must remain documented in each readme.' );
	sidrena_wporg_assert( false !== strpos( $readme, '80 EUR' ), 'Optional paid setup must remain documented without gating plugin features.' );
}

sidrena_wporg_assert(
	false !== strpos( $woo_readme, 'nije povezan s tvrtkom Automattic' )
	&& false !== strpos( $woo_readme, 'Naziv WooCommerce koristi se samo radi točnog opisa kompatibilnosti i integracije.' ),
	'WooCommerce independence/trademark clarification changed.'
);

sidrena_wporg_assert(
	false !== strpos( $edition_guard, "'plugins' !== $screen->id" )
	&& false !== strpos( $edition_guard, 'notice notice-error is-dismissible' ),
	'Edition conflict notice must remain scoped to Plugins and dismissible.'
);

$visuals = array(
	'assets/images/logo-woocommerce.svg',
	'assets/images/logo-woocommerce-light.svg',
	'branding/wporg-banner-woocommerce.svg',
	'branding/plugin-cover-woocommerce.svg',
	'branding/app-card-woocommerce.svg',
	'branding/docs-cover-woocommerce.svg',
	'branding/website-hero-woocommerce.svg',
	'branding/social-woocommerce.svg',
	'branding/compact-woocommerce.svg',
	'branding/cta-woocommerce.svg',
);
foreach ( $visuals as $visual ) {
	$source = file_get_contents( $root . '/' . $visual );
	sidrena_wporg_assert( false !== $source, 'Unable to read visual asset: ' . $visual );
	sidrena_wporg_assert( false === stripos( $source, 'WooCommerce' ), 'Third-party WooCommerce branding returned inside visual asset: ' . $visual );
}

fwrite( STDOUT, "SIDRENA WordPress.org review regression guard passed.\n" );
