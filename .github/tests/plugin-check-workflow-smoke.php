<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
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
	false !== strpos( $check, 'slug: sidrena' )
	&& false !== strpos( $check, 'slug: sidrena-for-woocommerce' ),
	'Plugin Check must use the public WordPress and WooCommerce slugs/text domains.'
);
sidrena_plugin_check_workflow_assert(
	false === strpos( $check, 'slug: sidrena-wordpress' )
	&& false === strpos( $check, 'slug: sidrena-woocommerce' ),
	'Install-folder names must not be reused as public Plugin Check slugs.'
);

fwrite( STDOUT, "Sidrena Plugin Check workflow smoke test passed.\n" );
