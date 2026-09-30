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

$wp_gate    = strpos( $release, 'Plugin Check release gate — WordPress edition' );
$woo_gate   = strpos( $release, 'Plugin Check release gate — WooCommerce edition' );
$create_tag = strpos( $release, 'Create verified release tag' );
$publish    = strpos( $release, 'Publish new GitHub release' );
$verify     = strpos( $release, 'Verify published GitHub release assets' );
$cleanup    = strpos( $release, 'Keep only the current GitHub release and version tag' );

sidrena_release_gate_assert(
	false !== $wp_gate && false !== $woo_gate && false !== $create_tag && false !== $publish && false !== $verify && false !== $cleanup,
	'Required release validation, publication or cleanup steps are missing.'
);
sidrena_release_gate_assert(
	$wp_gate < $create_tag && $woo_gate < $create_tag && $create_tag < $publish && $publish < $verify && $verify < $cleanup,
	'Release order must validate Plugin Check before tagging/publication and verify published assets before destructive history cleanup.'
);

$main_guard_start = strpos( $release, '- name: Require stable release branch to match main' );
$resolve_start    = strpos( $release, '- name: Resolve or create release tag from release branch' );
$resolve_end      = strpos( $release, '- name: Resolve tag target and release state' );
sidrena_release_gate_assert(
	false !== $main_guard_start && false !== $resolve_start && false !== $resolve_end
	&& $main_guard_start < $resolve_start && $resolve_start < $resolve_end,
	'Stable release branch/main guard or release-tag resolver is missing.'
);

$main_guard_block = substr( $release, $main_guard_start, $resolve_start - $main_guard_start );
sidrena_release_gate_assert(
	false !== strpos( $main_guard_block, "if: startsWith(github.ref, 'refs/heads/release/')" )
	&& false !== strpos( $main_guard_block, 'git fetch origin main --no-tags' )
	&& false !== strpos( $main_guard_block, 'MAIN_SHA="$(git rev-parse origin/main)"' )
	&& false !== strpos( $main_guard_block, 'if [[ "$GITHUB_SHA" != "$MAIN_SHA" ]]' ),
	'Every stable release branch must exactly match current main before publishing.'
);

$resolve_block = substr( $release, $resolve_start, $resolve_end - $resolve_start );
sidrena_release_gate_assert(
	false !== strpos( $resolve_block, '"$VERSION" == "1.0.0"' )
	&& false !== strpos( $resolve_block, 'REFRESH_RELEASE=true' )
	&& false !== strpos( $resolve_block, 'CREATE_RELEASE_TAG=true' )
	&& false === strpos( $resolve_block, '0.9.0' )
	&& false === strpos( $resolve_block, '1.0.23' )
	&& false === strpos( $resolve_block, 'git push origin "$TAG_NAME"' ),
	'Only the consolidated 1.0.0 release may use the controlled existing-tag refresh path, and tag creation must remain deferred.'
);

$retarget_start = strpos( $release, '- name: Retarget controlled 1.0.0 release tag before asset mutation' );
$refresh_start  = strpos( $release, '- name: Refresh existing release metadata and controlled assets when required' );
sidrena_release_gate_assert(
	false !== $retarget_start && false !== $refresh_start && $retarget_start < $refresh_start && $refresh_start < $verify,
	'Controlled 1.0.0 retarget must occur before release asset mutation and published-asset verification.'
);

$retarget_block = substr( $release, $retarget_start, $refresh_start - $retarget_start );
sidrena_release_gate_assert(
	false !== strpos( $retarget_block, "env.VERSION == '1.0.0'" )
	&& false !== strpos( $retarget_block, 'test "$RELEASE_TARGET" = "$GITHUB_SHA"' )
	&& false !== strpos( $retarget_block, 'REMOTE_TAG_REF="$(git ls-remote origin "refs/tags/$TAG_NAME"' )
	&& false !== strpos( $retarget_block, '--force-with-lease="refs/tags/$TAG_NAME:$REMOTE_TAG_REF"' )
	&& false === strpos( $retarget_block, 'git push --force origin' ),
	'Existing 1.0.0 tag refresh must be main-bound and protected by remote-ref force-with-lease.'
);

$refresh_block = substr( $release, $refresh_start, $verify - $refresh_start );
sidrena_release_gate_assert(
	false !== strpos( $refresh_block, 'test "$VERSION" = "1.0.0"' )
	&& false !== strpos( $refresh_block, 'REMOTE_TAG_TARGET="$(git ls-remote origin "refs/tags/$TAG_NAME^{}"' )
	&& false !== strpos( $refresh_block, 'test "$REMOTE_TAG_TARGET" = "$RELEASE_TARGET"' )
	&& false !== strpos( $refresh_block, 'gh release upload "$TAG_NAME"' )
	&& false !== strpos( $refresh_block, '--clobber --repo "$GITHUB_REPOSITORY"' ),
	'Existing 1.0.0 assets may be replaced only after the remote tag resolves to the validated release target.'
);

$cleanup_block = substr( $release, $cleanup );
sidrena_release_gate_assert(
	false !== strpos( $cleanup_block, "startsWith(github.ref, 'refs/heads/release/')" )
	&& false === strpos( $cleanup_block, "env.VERSION == '1.0.0'" )
	&& false !== strpos( $cleanup_block, 'gh api --method DELETE "repos/${GITHUB_REPOSITORY}/releases/${RELEASE_ID}"' )
	&& false !== strpos( $cleanup_block, "git ls-remote --tags --refs origin 'refs/tags/v*'" )
	&& false !== strpos( $cleanup_block, 'git push origin --delete "$TAG"' )
	&& false !== strpos( $cleanup_block, 'REMAINING_VERSION_TAGS=' )
	&& false === strpos( $cleanup_block, 'done < <(git tag -l)' ),
	'Verified stable-release cleanup must keep only the current release while removing only retired GitHub releases and v* release tags, never arbitrary repository tags.'
);

sidrena_release_gate_assert(
	false === strpos( $release, 'V1023_STALE_TARGET' )
	&& false === strpos( $release, 'Refresh first public 0.1.0 release' )
	&& false === strpos( $release, 'Retarget stale 0.9.0 tag' ),
	'Obsolete one-off historical release repair paths must not remain in the consolidated workflow.'
);

sidrena_release_gate_assert(
	false !== strpos( $check, "- 'release/**'" ) && false !== strpos( $check, "- 'v*'" ),
	'Plugin Check must observe release branches and version tags.'
);
sidrena_release_gate_assert(
	false !== strpos( $release, 'brendigo-sidrene-cijene-digitalni-cjenici' )
	&& false !== strpos( $release, 'brendigo-sidrena-cijena' )
	&& false === strpos( $release, 'slug: sidrena-wordpress' )
	&& false === strpos( $release, 'slug: sidrena-woocommerce' ),
	'Release Plugin Check must use the established public plugin slugs/text domains.'
);
sidrena_release_gate_assert(
	false === strpos( $release, 'ignore-codes: trademarked_term' ),
	'Trademark checks must not be suppressed.'
);

fwrite( STDOUT, "SIDRENA release gate smoke test passed.\n" );
