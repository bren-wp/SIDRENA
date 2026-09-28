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
$bulk_source = file_get_contents( $root . '/includes/class-sidrena-bulk.php' );
$admin_source = file_get_contents( $root . '/includes/class-sidrena-admin.php' );
$bootstrap_source = file_get_contents( $root . '/includes/sidrena-bootstrap.php' );

foreach ( array( $wp_main, $woo_main, $wp_readme, $woo_readme, $edition_guard, $plugin_check, $release, $bulk_source, $admin_source, $bootstrap_source ) as $source ) {
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
	false !== stripos( $woo_readme, 'independently developed' )
	&& false !== stripos( $woo_readme, 'Automattic' )
	&& false !== stripos( $woo_readme, 'not an official WooCommerce product' )
	&& false !== stripos( $woo_readme, 'describe compatibility' ),
	'WooCommerce independence/trademark clarification changed.'
);

sidrena_wporg_assert(
	false !== strpos( $wp_readme, 'Reference prices, 30-day sale-price references' )
	&& false !== strpos( $wp_readme, '== Description ==' )
	&& false !== strpos( $woo_readme, 'Reference prices, 30-day sale-price references' )
	&& false !== strpos( $woo_readme, '== Description ==' ),
	'WordPress.org readme base language must remain standard English.'
);

sidrena_wporg_assert(
	false !== strpos( $edition_guard, "'plugins' !== " . '$screen->id' )
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


sidrena_wporg_assert(
	false !== strpos( $bulk_source, "current_user_can( 'edit_post', \$id )" )
	&& substr_count( $admin_source, "current_user_can( 'edit_post', \$product_id )" ) >= 2,
	'WooCommerce bulk/import write paths must enforce per-product edit capabilities in addition to action-level permissions.'
);

sidrena_wporg_assert(
	false === stripos( $bootstrap_source, 'Cjenikomat' )
	&& false === stripos( $wp_main, 'Cjenikomat' )
	&& false === stripos( $woo_main, 'Cjenikomat' )
	&& false === stripos( $wp_readme, 'Cjenikomat' )
	&& false === stripos( $woo_readme, 'Cjenikomat' ),
	'Retired Cjenikomat branding must not return to production-facing plugin sources.'
);


$production_files = array();
foreach ( array( 'includes', 'admin', 'public', 'editions' ) as $directory ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root . '/' . $directory, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() || ! in_array( strtolower( $file->getExtension() ), array( 'php', 'js' ), true ) ) {
			continue;
		}
		$production_files[] = $file->getPathname();
	}
}

$production_source = '';
foreach ( $production_files as $file ) {
	$production_source .= "\n" . file_get_contents( $file );
}

sidrena_wporg_assert(
	2 === substr_count( $production_source, "'admin_notices'" )
	&& 0 === substr_count( $production_source, "'all_admin_notices'" ),
	'Only the two scoped dependency/conflict admin notices are allowed; global all_admin_notices are forbidden.'
);

sidrena_wporg_assert(
	1 === substr_count( $admin_source, 'Sidrena_Utils::donation_url()' )
	&& 2 === substr_count( $admin_source, 'Sidrena_Utils::installation_price()' ),
	'Donation and optional paid setup must remain confined to the SIDRENA Support screen.'
);

sidrena_wporg_assert(
	false !== strpos( $bootstrap_source, "'plugins' !== " . '$screen->id' )
	&& false !== strpos( $bootstrap_source, 'notice notice-error is-dismissible' )
	&& false === stripos( $bootstrap_source, 'donation' )
	&& false === stripos( $bootstrap_source, 'revolut' )
	&& false === stripos( $bootstrap_source, '80 EUR' ),
	'WooCommerce dependency notice must stay Plugins-screen-only, dismissible, and free of donation/setup marketing.'
);

sidrena_wporg_assert(
	1 === substr_count( $production_source, 'wp_safe_remote_get(' )
	&& 0 === substr_count( $production_source, 'wp_remote_get(' )
	&& 0 === substr_count( $production_source, 'wp_remote_post(' )
	&& 0 === substr_count( $production_source, 'wp_safe_remote_post(' )
	&& 0 === substr_count( $production_source, 'curl_init(' ),
	'Unexpected automatic network request primitive detected in production source.'
);

sidrena_wporg_assert(
	false !== strpos( $admin_source, "0 !== strpos( \$url, \$base )" )
	&& false !== strpos( $admin_source, 'wp_http_validate_url( $url )' )
	&& false !== strpos( $admin_source, "'reject_unsafe_urls' => true" ),
	'The sole HTTP public-access check must remain constrained to validated same-site SIDRENA publication URLs.'
);

sidrena_wporg_assert(
	false !== strpos( $wp_readme, '== External services and user-initiated links ==' )
	&& false !== strpos( $woo_readme, '== External services and user-initiated links ==' )
	&& false !== strpos( $wp_readme, 'does not send telemetry or usage analytics' )
	&& false !== strpos( $woo_readme, 'does not send telemetry or usage analytics' )
	&& false !== strpos( $wp_readme, 'same WordPress site' )
	&& false !== strpos( $woo_readme, 'same WordPress site' ),
	'External-service disclosure must document the same-site check and absence of telemetry.'
);

fwrite( STDOUT, "SIDRENA WordPress.org review regression guard passed.\n" );
