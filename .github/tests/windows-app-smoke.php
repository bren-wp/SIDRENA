<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://sidrene-cijene.com.hr/
 * @see https://brendigo.com/
 */

$root = dirname( __DIR__, 2 );

function sidrena_windows_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

$required = array(
	'windows/README.md',
	'windows/Sidrena.Windows/Sidrena.Windows.csproj',
	'windows/Sidrena.Windows/App.xaml',
	'windows/Sidrena.Windows/App.xaml.cs',
	'windows/Sidrena.Windows/MainWindow.xaml',
	'windows/Sidrena.Windows/MainWindow.xaml.cs',
	'windows/Sidrena.Windows/Services/SidrenaAppServices.cs',
	'windows/Sidrena.Windows/Assets/sidrena-mark.svg',
);

foreach ( $required as $relative ) {
	sidrena_windows_assert( is_file( $root . '/' . $relative ), 'Missing Windows app file: ' . $relative );
}

$project = file_get_contents( $root . '/windows/Sidrena.Windows/Sidrena.Windows.csproj' );
$window  = file_get_contents( $root . '/windows/Sidrena.Windows/MainWindow.xaml' );
$code    = file_get_contents( $root . '/windows/Sidrena.Windows/Services/SidrenaAppServices.cs' );
$readme  = file_get_contents( $root . '/windows/README.md' );

foreach ( array( '<UseWinUI>true</UseWinUI>', 'Microsoft.WindowsAppSDK', '<WindowsPackageType>None</WindowsPackageType>', 'net8.0-windows' ) as $marker ) {
	sidrena_windows_assert( false !== strpos( $project, $marker ), 'Windows project marker missing: ' . $marker );
}

foreach ( array( 'NavigationView', 'Mica', 'Legal readiness', 'Lokalni export', 'Sidrena Desktop' ) as $marker ) {
	sidrena_windows_assert( false !== strpos( $window . $readme, $marker ), 'Premium app marker missing: ' . $marker );
}

foreach ( array( 'StandardReferenceDate', 'FmcgReferenceDate', 'EffectiveDate', 'ExportLocalPackage', 'sidrena-cjenik.csv', 'sidrena-cjenik.xml', 'objava-cjenika.html' ) as $marker ) {
	sidrena_windows_assert( false !== strpos( $code, $marker ), 'Windows service marker missing: ' . $marker );
}

$forbidden = array( 'System.Windows.Forms', 'PresentationFramework', 'DllImport', 'user32.dll', 'kernel32.dll' );
$iterator  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/windows', FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
	if ( ! $file->isFile() ) {
		continue;
	}
	$content = file_get_contents( $file->getPathname() );
	foreach ( $forbidden as $needle ) {
		sidrena_windows_assert( false === stripos( $content, $needle ), 'Legacy desktop marker found in ' . $file->getPathname() . ': ' . $needle );
	}
}

fwrite( STDOUT, "Sidrena Windows app smoke test passed." . PHP_EOL );
