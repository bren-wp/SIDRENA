<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

declare( strict_types=1 );

$root      = dirname( __DIR__, 2 );
$canonical = 'https://brendigo.com/sidrene-cijene/';
$legacy    = array(
	'https://sidrene-cijene.com.hr/',
	'https://sidrene-cijene.com.hr',
	'https://sidrena-cijena.com.hr/',
	'https://sidrena-cijena.com.hr',
);

function sidrena_canonical_url_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

$rest = file_get_contents( $root . '/includes/class-sidrena-rest.php' );
$pricelist = file_get_contents( $root . '/includes/class-sidrena-pricelist.php' );
sidrena_canonical_url_assert( false !== $rest, 'Unable to read REST source.' );
sidrena_canonical_url_assert( false !== $pricelist, 'Unable to read price-list source.' );
sidrena_canonical_url_assert(
	false === strpos( $rest, "'plugin_url'" )
	&& false === strpos( $pricelist, "'plugin_url'" ),
	'Public REST and manifest output must not inject an external plugin credit URL.'
);

$targets = array(
	$root . '/includes',
	$root . '/admin',
	$root . '/public',
	$root . '/assets',
	$root . '/editions',
	$root . '/languages',
	$root . '/docs',
);
$single_files = array(
	$root . '/uninstall.php',
	$root . '/tools/build-editions.sh',
	$root . '/tools/build-support-pdf.py',
);

$violations = array();
foreach ( $targets as $directory ) {
	if ( ! is_dir( $directory ) ) {
		continue;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}
		$extension = strtolower( pathinfo( $file->getFilename(), PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, array( 'php', 'js', 'css', 'svg', 'pot', 'txt', 'md' ), true ) ) {
			continue;
		}
		$contents = file_get_contents( $file->getPathname() );
		if ( false === $contents ) {
			$violations[] = $file->getPathname() . ': unreadable';
			continue;
		}
		foreach ( $legacy as $legacy_url ) {
			if ( false !== strpos( $contents, $legacy_url ) ) {
				$violations[] = $file->getPathname() . ': ' . $legacy_url;
			}
		}
	}
}

foreach ( $single_files as $path ) {
	$contents = file_get_contents( $path );
	sidrena_canonical_url_assert( false !== $contents, 'Unable to read ' . $path );
	foreach ( $legacy as $legacy_url ) {
		if ( false !== strpos( $contents, $legacy_url ) ) {
			$violations[] = $path . ': ' . $legacy_url;
		}
	}
}

sidrena_canonical_url_assert(
	empty( $violations ),
	"Retired Sidrena URL detected in production source:\n" . implode( "\n", $violations )
);

$build = file_get_contents( $root . '/tools/build-editions.sh' );
sidrena_canonical_url_assert( false !== $build, 'Unable to read build script.' );
sidrena_canonical_url_assert(
	false === strpos( $build, "text.replace('https://sidrene-cijene.com.hr/'" ),
	'Build must not silently rewrite a retired URL; production source must already be canonical.'
);

fwrite( STDOUT, "Sidrena canonical URL source-integrity smoke test passed.\n" );
