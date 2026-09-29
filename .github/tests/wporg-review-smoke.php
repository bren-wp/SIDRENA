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
$products_source = file_get_contents( $root . '/includes/class-sidrena-products.php' );
$rest_source = file_get_contents( $root . '/includes/class-sidrena-rest.php' );
$public_source = file_get_contents( $root . '/includes/class-sidrena-public.php' );
$build_source = file_get_contents( $root . '/tools/build-editions.sh' );
$plugin_check_runner = file_get_contents( $root . '/tools/run-plugin-check.sh' );

foreach ( array( $wp_main, $woo_main, $wp_readme, $woo_readme, $edition_guard, $plugin_check, $release, $bulk_source, $admin_source, $bootstrap_source, $products_source, $rest_source, $public_source, $build_source, $plugin_check_runner ) as $source ) {
	sidrena_wporg_assert( false !== $source, 'WordPress.org review guard could not read a required source file.' );
}

sidrena_wporg_assert(
	false !== strpos( $wp_main, 'Plugin Name: SIDRENA' )
	&& false !== strpos( $woo_main, 'Plugin Name: SIDRENA' ),
	'Both installed plugin display names must remain SIDRENA.'
);

sidrena_wporg_assert(
	0 === preg_match( '/^\s*\*\s*Plugin Name:.*WooCommerce/im', $woo_main )
	&& false === stripos( strtok( $woo_readme, "\n" ), 'WooCommerce' ),
	'WooCommerce trademark must not appear in the public plugin display name or readme title.'
);

sidrena_wporg_assert(
	false !== strpos( $wp_main, 'Author: brendigo' )
	&& false !== strpos( $woo_main, 'Author: Brendigo' )
	&& false !== strpos( $woo_main, 'Developer: Brendigo' )
	&& false !== strpos( $woo_main, 'Developer URI: https://brendigo.com/' ),
	'Edition ownership/developer metadata is inconsistent.'
);

sidrena_wporg_assert(
	false !== strpos( $wp_main, 'Text Domain: brendigo-sidrene-cijene-digitalni-cjenici' )
	&& false !== strpos( $woo_main, 'Text Domain: brendigo-sidrena-cijena' )
	&& false !== strpos( $plugin_check, 'brendigo-sidrene-cijene-digitalni-cjenici' )
	&& false !== strpos( $plugin_check, 'brendigo-sidrena-cijena' )
	&& false !== strpos( $release, 'brendigo-sidrene-cijene-digitalni-cjenici' )
	&& false !== strpos( $release, 'brendigo-sidrena-cijena' ),
	'Public text domains and Plugin Check/release slugs must stay synchronized.'
);

sidrena_wporg_assert(
	false !== strpos( $wp_readme, '=== SIDRENA ===' )
	&& false !== strpos( $woo_readme, '=== SIDRENA ===' ),
	'Both WordPress.org readme titles must remain SIDRENA.'
);

sidrena_wporg_assert(
	false !== strpos( $build_source, 'WP_SLUG="brendigo-sidrene-cijene-digitalni-cjenici"' )
	&& false !== strpos( $build_source, 'WOO_SLUG="brendigo-sidrena-cijena"' )
	&& false !== strpos( $build_source, 'WP_STAGE="$WORK/$WP_SLUG"' )
	&& false !== strpos( $build_source, 'WOO_STAGE="$WORK/$WOO_SLUG"' )
	&& false !== strpos( $plugin_check_runner, 'if [[ "$PLUGIN_SLUG" != "$PUBLIC_SLUG" ]]' ),
	'Production ZIP roots must equal their public slugs so Plugin Check cannot mask text-domain or trademark findings.'
);

foreach ( array( $wp_readme, $woo_readme ) as $readme ) {
	$readme_lines = preg_split( '/\R/', $readme );
	$description_marker = array_search( '== Description ==', $readme_lines, true );
	$short_description = '';
	if ( false !== $description_marker ) {
		for ( $line_index = 10; $line_index < $description_marker; ++$line_index ) {
			$candidate = trim( (string) $readme_lines[ $line_index ] );
			if ( '' !== $candidate ) {
				$short_description = $candidate;
			}
		}
	}
	sidrena_wporg_assert( '' !== $short_description && strlen( $short_description ) <= 150, 'WordPress.org short description must not exceed 150 characters.' );
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
	false !== strpos( $wp_readme, 'SIDRENA anchor prices, unlimited price-change history' )
	&& false !== strpos( $woo_readme, 'SIDRENA anchor prices, unlimited price-change history' )
	&& false !== strpos( $wp_readme, 'Is a SIDRENA reference price the same as the lowest price in the previous 30 days?' )
	&& false !== strpos( $woo_readme, 'Is a SIDRENA reference price the same as the lowest price in the previous 30 days?' )
	&& false !== strpos( $wp_readme, 'No. SIDRENA stores them as separate concepts' )
	&& false !== strpos( $woo_readme, 'No. They are stored and handled as separate concepts.' )
	&& false !== strpos( $wp_readme, '== Description ==' )
	&& false !== strpos( $woo_readme, '== Description ==' ),
	'WordPress.org readmes must use English base copy and explicitly separate SIDRENA anchor prices from the 30-day sale reference.'
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
	sidrena_wporg_assert(
		false === stripos( $source, 'usklađene cijene' )
		&& false === stripos( $source, 'sigurno poslovanje' )
		&& false === stripos( $source, '100% uskla' ),
		'Visual asset contains a legal-compliance marketing claim: ' . $visual
	);
}

$wporg_banner_wp = file_get_contents( $root . '/branding/wporg-banner-wordpress.svg' );
$wporg_banner_woo = file_get_contents( $root . '/branding/wporg-banner-woocommerce.svg' );
sidrena_wporg_assert(
	false !== strpos( $wporg_banner_wp, 'by brendigo' )
	&& false !== strpos( $wporg_banner_woo, 'by brendigo' ),
	'WordPress.org banners must visibly distinguish SIDRENA as a brendigo product.'
);


sidrena_wporg_assert(
	false !== strpos( $bulk_source, "current_user_can( 'edit_post', \$id )" )
	&& substr_count( $admin_source, "current_user_can( 'edit_post', \$product_id )" ) >= 2,
	'WooCommerce bulk/import write paths must enforce per-product edit capabilities in addition to action-level permissions.'
);

sidrena_wporg_assert(
	false !== strpos( $products_source, 'private function verified_product_form_data' )
	&& false !== strpos( $products_source, "wp_verify_nonce( \$nonce, 'woocommerce_save_data' )" )
	&& false !== strpos( $products_source, '$posted = $this->verified_product_form_data' )
	&& false === strpos( $products_source, 'can_process_product_form' )
	&& false === strpos( $products_source, 'isset( $_POST[ $key ] )' ),
	'Woo product and variation writes must process field data only after Woo nonce and object permission verification.'
);

sidrena_wporg_assert(
	false === stripos( $bootstrap_source, 'Cjenikomat' )
	&& false === stripos( $wp_main, 'Cjenikomat' )
	&& false === stripos( $woo_main, 'Cjenikomat' )
	&& false === stripos( $wp_readme, 'Cjenikomat' )
	&& false === stripos( $woo_readme, 'Cjenikomat' ),
	'Retired Cjenikomat branding must not return to production-facing plugin sources.'
);

sidrena_wporg_assert(
	false === strpos( $rest_source, "add_shortcode( 'sidrena_cjenici'" )
	&& false === strpos( $rest_source, 'public function shortcode( $atts = array() )' )
	&& false !== strpos( $public_source, "add_shortcode( 'sidrena_cjenici', array( \$this, 'downloads_shortcode' ) );" ),
	'The public price-list download shortcode must be owned only by Sidrena_Public.'
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
	&& 0 === substr_count( $production_source, "'all_admin_notices'" )
	&& false !== strpos( $bootstrap_source, 'SIDRENA zahtijeva aktivan WooCommerce za WooCommerce izdanje.' ),
	'Only scoped edition-conflict and missing-WooCommerce notices are allowed; global all_admin_notices are forbidden.'
);

sidrena_wporg_assert(
	0 === substr_count( $admin_source, 'donation_url' )
	&& 0 === substr_count( $production_source, 'revolut.me' )
	&& 2 === substr_count( $admin_source, 'Sidrena_Utils::installation_price()' ),
	'Donation links must remain absent while optional paid setup stays confined to SIDRENA Support.'
);

sidrena_wporg_assert(
	false !== strpos( $woo_main, 'Requires Plugins: woocommerce' )
	&& false !== strpos( $bootstrap_source, "'admin_notices'" )
	&& false !== strpos( $bootstrap_source, 'woocommerce_runtime_available()' )
	&& false === stripos( $bootstrap_source, 'donation' )
	&& false === stripos( $bootstrap_source, 'revolut' )
	&& false === stripos( $bootstrap_source, '80 EUR' ),
	'WooCommerce dependency handling must rely on the core Requires Plugins header without a custom dashboard notice.'
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
	&& 1 === preg_match( "/'reject_unsafe_urls'\\s*=>\\s*true/", $admin_source ),
	'The sole HTTP public-access check must remain constrained to validated same-site SIDRENA publication URLs.'
);

sidrena_wporg_assert(
	false !== strpos( $wp_readme, '== External services and user-initiated links ==' )
	&& false !== strpos( $woo_readme, '== External services and user-initiated links ==' )
	&& false !== strpos( $wp_readme, 'does not send telemetry or usage analytics' )
	&& false !== strpos( $woo_readme, 'does not send telemetry or usage analytics' )
	&& false !== strpos( $wp_readme, 'same WordPress site' )
	&& false !== strpos( $woo_readme, 'same WordPress site' )
	&& false !== strpos( $wp_readme, 'mingo.gov.hr' )
	&& false !== strpos( $woo_readme, 'mingo.gov.hr' )
	&& false !== strpos( $wp_readme, 'dirh.gov.hr' )
	&& false !== strpos( $woo_readme, 'dirh.gov.hr' )
	&& false !== strpos( $wp_readme, 'www.hok.hr' )
	&& false !== strpos( $woo_readme, 'www.hok.hr' )
	&& false !== strpos( $wp_readme, 'normal connection/request data' )
	&& false !== strpos( $woo_readme, 'normal connection/request data' )
	&& false !== strpos( $wp_readme, 'SIDRENA never uses these government/reference sites as an API or automatic data service' )
	&& false !== strpos( $woo_readme, 'SIDRENA never uses these government/reference sites as an API or automatic data service' ),
	'External-service disclosure must document same-site checks, user-clicked reference links, transferred browser metadata, and absence of telemetry.'
);

sidrena_wporg_assert(
	0 === preg_match( '/wp_(?:safe_)?remote_(?:get|post)\s*\(\s*[\'\"]https?:\/\/(?:mingo\.gov\.hr|dirh\.gov\.hr|narodne-novine\.nn\.hr|www\.nn\.hr|(?:www\.)?hok\.hr)/i', $production_source )
	&& 0 === preg_match( '/fetch\s*\(\s*[\'\"]https?:\/\/(?:mingo\.gov\.hr|dirh\.gov\.hr|narodne-novine\.nn\.hr|www\.nn\.hr|(?:www\.)?hok\.hr)/i', $production_source ),
	'Official legal/reference sites must remain user-clicked links and must never become automatic PHP or browser network endpoints.'
);

fwrite( STDOUT, "SIDRENA WordPress.org review regression guard passed.\n" );
