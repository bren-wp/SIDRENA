<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

declare( strict_types=1 );

$root    = dirname( __DIR__, 2 );
$release = file_get_contents( $root . '/.github/workflows/release.yml' );
$check   = file_get_contents( $root . '/.github/workflows/plugin-check.yml' );

function sidrena_release_gate_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

sidrena_release_gate_assert( false !== $release, 'Unable to read release workflow.' );
sidrena_release_gate_assert( false !== $check, 'Unable to read Plugin Check workflow.' );

sidrena_release_gate_assert(
	2 === substr_count( $release, 'bash tools/run-plugin-check.sh' )
	&& false !== strpos( $release, 'bash tools/install-wp-env.sh' )
	&& false === strpos( $release, 'WordPress/plugin-check-action@v1' ),
	'Release workflow must run deterministic real Plugin Check gates for both production editions.'
);

sidrena_release_gate_assert(
	false !== strpos( $release, 'build/release-plugin-check/brendigo-sidrene-cijene-digitalni-cjenici' )
	&& false !== strpos( $release, 'build/release-plugin-check/brendigo-sidrena-cijena' ),
	'Release Plugin Check must target the exact public-slug production builds.'
);

$build      = strpos( $release, 'Build WordPress and WooCommerce ZIPs' );
$repro      = strpos( $release, 'Verify reproducible release build' );
$fresh      = strpos( $release, 'Verify release ZIPs and slug entrypoints' );
$pdf        = strpos( $release, 'Render and validate release PDF manuals' );
$stage      = strpos( $release, 'Stage exact release builds for Plugin Check' );
$wp_gate    = strpos( $release, 'Plugin Check release gate — WordPress edition' );
$woo_gate   = strpos( $release, 'Plugin Check release gate — WooCommerce edition' );
$create_tag = strpos( $release, 'Create verified release tag from release branch' );
$publish    = strpos( $release, 'Publish GitHub release' );
$verify     = strpos( $release, 'Verify published GitHub release assets' );

sidrena_release_gate_assert(
	false !== $build && false !== $repro && false !== $fresh && false !== $pdf && false !== $stage
	&& false !== $wp_gate && false !== $woo_gate && false !== $create_tag && false !== $publish && false !== $verify,
	'Required release validation, reproducibility, publication or verification steps are missing.'
);

sidrena_release_gate_assert(
	$build < $repro && $repro < $fresh && $fresh < $pdf && $pdf < $stage
	&& $stage < $wp_gate && $stage < $woo_gate && $wp_gate < $create_tag && $woo_gate < $create_tag
	&& $create_tag < $publish && $publish < $verify,
	'Release order must prove reproducibility, validate ZIPs and run Plugin Check before tagging/publication, then verify published assets.'
);

$repro_block = substr( $release, $repro, $fresh - $repro );
sidrena_release_gate_assert(
	false !== strpos( $repro_block, 'sidrena-release-repeat' )
	&& 1 === substr_count( $repro_block, './tools/build-editions.sh' )
	&& 2 <= substr_count( $release, 'SOURCE_DATE_EPOCH="$(git show -s --format=%ct "$RELEASE_TARGET")" ./tools/build-editions.sh' )
	&& false !== strpos( $repro_block, 'cmp "$RUNNER_TEMP/sidrena-wordpress-$VERSION.zip"' )
	&& false !== strpos( $repro_block, 'cmp "$RUNNER_TEMP/sidrena-woocommerce-$VERSION.zip"' )
	&& false !== strpos( $repro_block, '.zip.sha256' ),
	'Release workflow must build twice from the same release target and byte-compare both ZIPs and checksum files before publication.'
);

$main_guard_start = strpos( $release, '- name: Require release branch to match main' );
$body_start       = strpos( $release, '- name: Prepare release body' );
sidrena_release_gate_assert(
	false !== $main_guard_start && false !== $body_start && $main_guard_start < $body_start,
	'Release branch/main guard is missing.'
);

$main_guard_block = substr( $release, $main_guard_start, $body_start - $main_guard_start );
sidrena_release_gate_assert(
	false !== strpos( $main_guard_block, "if: startsWith(github.ref, 'refs/heads/release/')" )
	&& false !== strpos( $main_guard_block, 'git fetch origin main --no-tags' )
	&& false !== strpos( $main_guard_block, 'MAIN_SHA="$(git rev-parse origin/main)"' )
	&& false !== strpos( $main_guard_block, 'if [[ "$GITHUB_SHA" != "$MAIN_SHA" ]]' ),
	'Every release branch must exactly match current main before publishing.'
);

$fresh_block = substr( $release, $fresh, $pdf - $fresh );
sidrena_release_gate_assert(
	false !== strpos( $fresh_block, 'brendigo-sidrene-cijene-digitalni-cjenici.php' )
	&& false !== strpos( $fresh_block, 'brendigo-sidrena-cijena.php' )
	&& false !== strpos( $fresh_block, 'test ! -f "$WP/sidrena-wordpress.php"' )
	&& false !== strpos( $fresh_block, 'test ! -f "$WOO/sidrena-woocommerce.php"' )
	&& false !== strpos( $fresh_block, 'Plugin Name: SIDRENA' )
	&& false !== strpos( $fresh_block, 'Text Domain: brendigo-sidrene-cijene-digitalni-cjenici' )
	&& false !== strpos( $fresh_block, 'Text Domain: brendigo-sidrena-cijena' )
	&& false !== strpos( $fresh_block, 'Requires Plugins:.*woocommerce' ),
	'Release ZIP validation must require slug-named entrypoints, SIDRENA branding, slug text domains and the WooCommerce dependency header only for the WooCommerce package.'
);

$stage_block = substr( $release, $stage, $wp_gate - $stage );
sidrena_release_gate_assert(
	false !== strpos( $stage_block, 'brendigo-sidrene-cijene-digitalni-cjenici/brendigo-sidrene-cijene-digitalni-cjenici.php' )
	&& false !== strpos( $stage_block, 'brendigo-sidrena-cijena/brendigo-sidrena-cijena.php' ),
	'Release Plugin Check staging must assert slug-named package entrypoints.'
);

$publish_block = substr( $release, $publish, $verify - $publish );
sidrena_release_gate_assert(
	false !== strpos( $publish_block, 'softprops/action-gh-release@v3' )
	&& false !== strpos( $publish_block, 'make_latest: true' )
	&& false !== strpos( $publish_block, 'sidrena-wordpress-${{ env.VERSION }}.zip' )
	&& false !== strpos( $publish_block, 'sidrena-woocommerce-${{ env.VERSION }}.zip' )
	&& false !== strpos( $publish_block, '.zip.sha256' ),
	'Release publication must publish both ZIPs and checksum files as the latest stable GitHub release.'
);

$verify_block = substr( $release, $verify );
sidrena_release_gate_assert(
	false !== strpos( $verify_block, 'gh release download "$TAG_NAME"' )
	&& false !== strpos( $verify_block, 'sha256sum -c "sidrena-wordpress-$VERSION.zip.sha256"' )
	&& false !== strpos( $verify_block, 'sha256sum -c "sidrena-woocommerce-$VERSION.zip.sha256"' )
	&& false !== strpos( $verify_block, 'brendigo-sidrene-cijene-digitalni-cjenici.php' )
	&& false !== strpos( $verify_block, 'brendigo-sidrena-cijena.php' )
	&& false !== strpos( $verify_block, 'test ! -f "$WP/sidrena-wordpress.php"' )
	&& false !== strpos( $verify_block, 'test ! -f "$WOO/sidrena-woocommerce.php"' ),
	'Published release verification must download assets, verify checksums and assert slug-named entrypoints without old package entrypoints.'
);

sidrena_release_gate_assert(
	false === strpos( $release, 'slug: sidrena-wordpress' )
	&& false === strpos( $release, 'slug: sidrena-woocommerce' )
	&& false === strpos( $release, 'ignore-codes: trademarked_term' )
	&& false === strpos( $release, 'WordPress/plugin-check-action@v1' ),
	'Release workflow must not use retired slugs or suppress trademark checks.'
);

sidrena_release_gate_assert(
	false !== strpos( $check, "- 'release/**'" ) && false !== strpos( $check, "- 'v*'" ),
	'Plugin Check must observe release branches and version tags.'
);

fwrite( STDOUT, "SIDRENA release gate smoke test passed.\n" );
