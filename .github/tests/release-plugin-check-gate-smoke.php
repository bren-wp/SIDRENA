<?php
/**
 * Sidrena source file.
 * Author: brendigo
 * Author URI: https://brendigo.com/
 * Plugin URI: https://brendigo.com/sidrene-cijene/
 * Support: sidrena@brendigo.com
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
	substr_count( $release, 'uses: WordPress/plugin-check-action@v1' ) === 2,
	'Release workflow must run Plugin Check for both production editions.'
);
sidrena_release_gate_assert(
	false !== strpos( $release, 'build/release-plugin-check/sidrena-wordpress' )
	&& false !== strpos( $release, 'build/release-plugin-check/sidrena-woocommerce' ),
	'Release Plugin Check must target staged production-shaped builds.'
);
sidrena_release_gate_assert(
	false !== strpos( $release, 'cp -a "$RUNNER_TEMP/.sidrena-build/sidrena-wordpress" build/release-plugin-check/' )
	&& false !== strpos( $release, 'cp -a "$RUNNER_TEMP/.sidrena-build/sidrena-woocommerce" build/release-plugin-check/' ),
	'Release Plugin Check staging must copy the exact build output used for ZIP publication.'
);

$wp_gate    = strpos( $release, 'Plugin Check release gate — WordPress edition' );
$woo_gate   = strpos( $release, 'Plugin Check release gate — WooCommerce edition' );
$create_tag = strpos( $release, 'Create verified release tag' );
$publish    = strpos( $release, 'Publish new GitHub release' );

sidrena_release_gate_assert(
	false !== $wp_gate && false !== $woo_gate && false !== $create_tag && false !== $publish,
	'Required release gate steps are missing.'
);
sidrena_release_gate_assert(
	$wp_gate < $create_tag && $woo_gate < $create_tag && $create_tag < $publish,
	'Plugin Check must complete before creating a new tag and publishing the release.'
);

$resolve_start = strpos( $release, '- name: Resolve or create release tag from release branch' );
$resolve_end   = strpos( $release, '- name: Resolve tag target and release state' );
sidrena_release_gate_assert(
	false !== $resolve_start && false !== $resolve_end && $resolve_start < $resolve_end,
	'Unable to isolate release-tag resolution step.'
);
$resolve_block = substr( $release, $resolve_start, $resolve_end - $resolve_start );
sidrena_release_gate_assert(
	false !== strpos( $resolve_block, 'CREATE_RELEASE_TAG=true' )
	&& false === strpos( $resolve_block, 'git push origin "$TAG_NAME"' ),
	'Missing release tags must be deferred until after validation gates.'
);


$main_guard_start = strpos( $release, '- name: Require stable release branch to match main' );
$main_guard_end   = strpos( $release, '- name: Refresh first public 0.1.0 release when rebuilding from release branch' );
sidrena_release_gate_assert(
	false !== $main_guard_start && false !== $main_guard_end && $main_guard_start < $main_guard_end,
	'Unable to isolate stable release main-source guard.'
);
$main_guard_block = substr( $release, $main_guard_start, $main_guard_end - $main_guard_start );
sidrena_release_gate_assert(
	false !== strpos( $main_guard_block, "if: startsWith(github.ref, 'refs/heads/release/')" )
	&& false === strpos( $main_guard_block, "env.VERSION == '1.0.0'" )
	&& false !== strpos( $main_guard_block, 'git fetch origin main --no-tags' )
	&& false !== strpos( $main_guard_block, 'MAIN_SHA="$(git rev-parse origin/main)"' )
	&& false !== strpos( $main_guard_block, 'if [[ "$GITHUB_SHA" != "$MAIN_SHA" ]]' ),
	'Every stable release branch must be blocked unless it points at the current main commit.'
);

sidrena_release_gate_assert(
	false !== strpos( $check, "- 'release/**'" ) && false !== strpos( $check, "- 'v*'" ),
	'Plugin Check workflow must also observe release branches and version tags.'
);

sidrena_release_gate_assert(
	false !== strpos( $release, 'slug: brendigo-sidrena-digitalni-cjenici' )
	&& false !== strpos( $release, 'slug: brendigo-sidrena-cjenici' ),
	'Release Plugin Check gates must validate the public plugin slugs/text domains.'
);
sidrena_release_gate_assert(
	false === strpos( $release, 'slug: sidrena-wordpress' )
	&& false === strpos( $release, 'slug: sidrena-woocommerce' ),
	'Release Plugin Check must not validate against install-folder aliases.'
);

fwrite( STDOUT, "Sidrena release Plugin Check gate smoke test passed.\n" );
