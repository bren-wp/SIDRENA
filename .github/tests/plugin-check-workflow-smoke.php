<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

$root  = dirname( __DIR__, 2 );
$check = file_get_contents( $root . '/.github/workflows/plugin-check.yml' );

function sidrena_plugin_check_workflow_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

sidrena_plugin_check_workflow_assert( false !== $check, 'Unable to read Plugin Check workflow.' );
sidrena_plugin_check_workflow_assert(
	false === strpos( $check, "- 'polish/**'" ),
	'Polish branches must rely on pull_request Plugin Check runs to avoid duplicate push/PR concurrency churn.'
);
sidrena_plugin_check_workflow_assert(
	false !== strpos( $check, "pull_request:\n    branches:\n      - main" ),
	'Plugin Check must continue to run for pull requests targeting main.'
);
sidrena_plugin_check_workflow_assert(
	false !== strpos( $check, "- 'release/**'" ) && false !== strpos( $check, "- 'v*'" ),
	'Release branches and version tags must remain covered by Plugin Check.'
);
sidrena_plugin_check_workflow_assert(
	false !== strpos( $check, 'cancel-in-progress: true' ),
	'Plugin Check concurrency must keep stale runs cancellable.'
);
sidrena_plugin_check_workflow_assert(
	1 === substr_count( $check, 'timeout-minutes: 10' )
	&& 2 === substr_count( $check, 'timeout-minutes: 15' ),
	'Plugin Check build and edition jobs must retain bounded execution time.'
);

sidrena_plugin_check_workflow_assert(
	false !== strpos( $check, 'slug: brendigo-sidrene-cijene-digitalni-cjenici' )
	&& false !== strpos( $check, 'slug: brendigo-sidrene-cijene-cjenici' ),
	'Plugin Check must use the public WordPress and WooCommerce slugs/text domains.'
);
sidrena_plugin_check_workflow_assert(
	false === strpos( $check, 'ignore-codes: trademarked_term' )
	&& false === strpos( $check, 'slug: woo' )
	&& false === strpos( $check, 'slug: woocommerce' ),
	'Trademark checks must not be suppressed and public slugs must not be based on third-party project names.'
);

sidrena_plugin_check_workflow_assert(
	false === strpos( $check, 'slug: sidrena-wordpress' )
	&& false === strpos( $check, 'slug: sidrena-woocommerce' ),
	'Install-folder names must not be reused as public Plugin Check slugs.'
);

$workflow_sources = '';
foreach ( glob( $root . '/.github/workflows/*.yml' ) as $workflow_file ) {
	$workflow_source = file_get_contents( $workflow_file );
	if ( false === $workflow_source ) {
		fwrite( STDERR, 'Unable to read workflow: ' . basename( $workflow_file ) . PHP_EOL );
		exit( 1 );
	}
	$workflow_sources .= "\n" . $workflow_source;
}

foreach (
	array(
		'actions/checkout@v4',
		'actions/setup-node@v4',
		'actions/upload-artifact@v4',
		'actions/download-artifact@v4',
		'softprops/action-gh-release@v2',
	) as $deprecated_action
) {
	sidrena_plugin_check_workflow_assert(
		false === strpos( $workflow_sources, $deprecated_action ),
		'Deprecated GitHub Actions runtime reference returned: ' . $deprecated_action
	);
}

sidrena_plugin_check_workflow_assert( substr_count( $workflow_sources, 'actions/checkout@v7' ) >= 7, 'All repository workflows must use checkout v7.' );
sidrena_plugin_check_workflow_assert( substr_count( $workflow_sources, 'actions/setup-node@v7' ) >= 2, 'Node-based workflows must use setup-node v7.' );
sidrena_plugin_check_workflow_assert( substr_count( $workflow_sources, 'actions/upload-artifact@v7' ) >= 2, 'Artifact uploads must use upload-artifact v7.' );
sidrena_plugin_check_workflow_assert( substr_count( $workflow_sources, 'actions/download-artifact@v8' ) >= 2, 'Artifact downloads must use download-artifact v8.' );
sidrena_plugin_check_workflow_assert( 1 === substr_count( $workflow_sources, 'softprops/action-gh-release@v3' ), 'Release publishing must use action-gh-release v3.' );

fwrite( STDOUT, "Sidrena Plugin Check workflow smoke test passed.\n" );
