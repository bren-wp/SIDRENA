<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

$root   = dirname( __DIR__, 2 );
$readme = file_get_contents( $root . '/README.md' );

function sidrena_readme_assets_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

sidrena_readme_assets_assert( false !== $readme, 'Unable to read main README.' );

$required_assets = array(
	'assets/images/logo-horizontal.svg',
	'assets/images/logo-wordpress.svg',
	'assets/images/logo-woocommerce.svg',
	'assets/images/logo-mark.svg',
	'assets/images/app-icon.svg',
	'assets/images/favicon.svg',
	'assets/images/menu-anchor.svg',
	'wporg-assets/sidrena-wordpress/assets/icon-128x128.png',
	'wporg-assets/sidrena-woocommerce/assets/icon-128x128.png',
	'wporg-assets/sidrena-wordpress/assets/banner-772x250.png',
	'wporg-assets/sidrena-woocommerce/assets/banner-772x250.png',
	'branding/rendered/plugin-cover-wordpress.png',
	'branding/rendered/plugin-cover-woocommerce.png',
);

foreach ( $required_assets as $asset ) {
	sidrena_readme_assets_assert( false !== strpos( $readme, $asset ), 'Main README must keep the real project asset visible: ' . $asset );
	sidrena_readme_assets_assert( is_file( $root . '/' . $asset ), 'README references a missing project asset: ' . $asset );
}

for ( $i = 1; $i <= 6; ++$i ) {
	$wp  = 'wporg-assets/sidrena-wordpress/assets/screenshot-' . $i . '.png';
	$woo = 'wporg-assets/sidrena-woocommerce/assets/screenshot-' . $i . '.png';
	sidrena_readme_assets_assert( false !== strpos( $readme, $wp ) && is_file( $root . '/' . $wp ), 'Main README must keep WordPress runtime screenshot ' . $i . '.' );
	sidrena_readme_assets_assert( false !== strpos( $readme, $woo ) && is_file( $root . '/' . $woo ), 'Main README must keep WooCommerce runtime screenshot ' . $i . '.' );
}

sidrena_readme_assets_assert(
	false !== strpos( $readme, 'Stvarni ekrani plugina — bez mockupova' )
	&& false !== strpos( $readme, 'Stvarni SIDRENA vizualni identitet' ),
	'Main README must clearly distinguish real runtime screenshots and production brand assets.'
);

$readme_claim_prefix = 'jamči ';
$readme_claim_suffix = 'usklađenost';

sidrena_readme_assets_assert(
	false === stripos( $readme, 'usklađene cijene, sigurno poslovanje' )
	&& false === stripos( $readme, '100% ' . 'usklađ' )
	&& false === stripos( $readme, $readme_claim_prefix . $readme_claim_suffix ),
	'Main README must not contain legal-compliance marketing claims.'
);

fwrite( STDOUT, "Sidrena README real-assets smoke test passed.\n" );
