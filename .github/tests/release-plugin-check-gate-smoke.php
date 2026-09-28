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
	substr_count( $release, 'bash tools/run-plugin-check.sh' ) === 2
	&& false !== strpos( $release, 'bash tools/install-wp-env.sh' )
	&& false === strpos( $release, 'WordPress/plugin-check-action@v1' ),
	'Release workflow must run deterministic real Plugin Check gates for both production editions.'
);
sidrena_release_gate_assert(
	false !== strpos( $release, 'build/release-plugin-check/brendigo-sidrene-cijene-digitalni-cjenici' )
	&& false !== strpos( $release, 'build/release-plugin-check/brendigo-sidrena-cijena' ),
	'Release Plugin Check must target staged production-shaped builds.'
);
sidrena_release_gate_assert(
	false !== strpos( $release, 'cp -a "$RUNNER_TEMP/.sidrena-build/brendigo-sidrene-cijene-digitalni-cjenici" build/release-plugin-check/' )
	&& false !== strpos( $release, 'cp -a "$RUNNER_TEMP/.sidrena-build/brendigo-sidrena-cijena" build/release-plugin-check/' ),
	'Release Plugin Check staging must copy exact public-slug package roots used for ZIP publication.'
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

sidrena_release_gate_assert(
	false !== strpos( $release, 'V1023_STALE_TARGET=c7a41fa9cf31e0c0f8fb3c43769b59fd474ab4c0' )
	&& false !== strpos( $resolve_block, '"$VERSION" == "1.0.23" && "$EXISTING_TAG_TARGET" == "$V1023_STALE_TARGET"' ),
	'1.0.23 release repair must be enabled only for the known premature tag target.'
);

$retarget_start = strpos( $release, '- name: Retarget controlled existing release tag before asset mutation' );
$refresh_start  = strpos( $release, '- name: Refresh existing release metadata and controlled assets when required' );
$verify_start   = strpos( $release, '- name: Verify published GitHub release assets' );
sidrena_release_gate_assert(
	false !== $retarget_start && false !== $refresh_start && false !== $verify_start
	&& $retarget_start < $refresh_start && $refresh_start < $verify_start,
	'Controlled tag retarget must happen before mutating existing release assets and before final release verification.'
);
$retarget_block = substr( $release, $retarget_start, $refresh_start - $retarget_start );
sidrena_release_gate_assert(
	false !== strpos( $retarget_block, "env.RELEASE_EXISTS == 'true'" )
	&& false !== strpos( $retarget_block, 'REMOTE_TAG_REF="$(git ls-remote origin "refs/tags/$TAG_NAME" | awk')
	&& false !== strpos( $retarget_block, 'REMOTE_TAG_TARGET="$(git ls-remote origin "refs/tags/$TAG_NAME^{}" | awk')
	&& false !== strpos( $retarget_block, 'test "$REMOTE_TAG_TARGET" = "$V1023_STALE_TARGET"' )
	&& false !== strpos( $retarget_block, 'test "$RELEASE_TARGET" = "$GITHUB_SHA"' )
	&& false !== strpos( $retarget_block, '--force-with-lease="refs/tags/$TAG_NAME:$REMOTE_TAG_REF"' )
	&& false === strpos( $retarget_block, 'git push --force origin "refs/tags/$TAG_NAME"' ),
	'Existing 1.0.23 release retarget must verify the remote stale target and use a remote-ref force-with-lease before asset mutation.'
);

$refresh_block = substr( $release, $refresh_start, $verify_start - $refresh_start );
sidrena_release_gate_assert(
	false !== strpos( $refresh_block, 'REMOTE_TAG_TARGET="$(git ls-remote origin "refs/tags/$TAG_NAME^{}" | awk')
	&& false !== strpos( $refresh_block, 'test "$REMOTE_TAG_TARGET" = "$RELEASE_TARGET"' )
	&& false !== strpos( $refresh_block, 'gh release upload "$TAG_NAME"' ),
	'Existing 1.0.23 assets may be replaced only after the remote tag points at the validated release target.'
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
	false !== strpos( $release, 'brendigo-sidrene-cijene-digitalni-cjenici' )
	&& false !== strpos( $release, 'brendigo-sidrena-cijena' ),
	'Release Plugin Check gates must validate the public plugin slugs/text domains.'
);
sidrena_release_gate_assert(
	false === strpos( $release, 'ignore-codes: trademarked_term' )
	&& false === strpos( $release, 'slug: woo' )
	&& false === strpos( $release, 'slug: woocommerce' ),
	'Trademark checks must not be suppressed and public slugs must not be based on third-party project names.'
);

sidrena_release_gate_assert(
	false === strpos( $release, 'slug: sidrena-wordpress' )
	&& false === strpos( $release, 'slug: sidrena-woocommerce' ),
	'Release Plugin Check must not validate against install-folder aliases.'
);

fwrite( STDOUT, "Sidrena release Plugin Check gate smoke test passed.\n" );
