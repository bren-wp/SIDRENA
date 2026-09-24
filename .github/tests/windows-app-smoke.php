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
	'windows/packaging/SidrenaSetup.iss',
	'windows/packaging/SidrenaPortable.iss',
	'.github/workflows/windows-release-assets.yml',
);

foreach ( $required as $relative ) {
	sidrena_windows_assert( is_file( $root . '/' . $relative ), 'Missing Windows app file: ' . $relative );
}

$project          = file_get_contents( $root . '/windows/Sidrena.Windows/Sidrena.Windows.csproj' );
$window           = file_get_contents( $root . '/windows/Sidrena.Windows/MainWindow.xaml' );
$code             = file_get_contents( $root . '/windows/Sidrena.Windows/Services/SidrenaAppServices.cs' );
$readme           = file_get_contents( $root . '/windows/README.md' );
$setup_packaging  = file_get_contents( $root . '/windows/packaging/SidrenaSetup.iss' );
$portable_package = file_get_contents( $root . '/windows/packaging/SidrenaPortable.iss' );
$release_workflow = file_get_contents( $root . '/.github/workflows/windows-release-assets.yml' );

foreach ( array( '<UseWinUI>true</UseWinUI>', 'Microsoft.WindowsAppSDK', '<WindowsPackageType>None</WindowsPackageType>', 'net8.0-windows', '<OutputType>WinExe</OutputType>' ) as $marker ) {
	sidrena_windows_assert( false !== strpos( $project, $marker ), 'Windows project marker missing: ' . $marker );
}

foreach ( array( 'NavigationView', 'Mica', 'Legal readiness', 'Lokalni export', 'Sidrena Desktop' ) as $marker ) {
	sidrena_windows_assert( false !== strpos( $window . $readme, $marker ), 'Premium app marker missing: ' . $marker );
}

foreach ( array( 'nativna Windows desktop aplikacija', 'Bez Electrona', 'bez Tauri webviewa', 'bez web stranice u desktop prozoru' ) as $marker ) {
	sidrena_windows_assert( false !== strpos( $readme, $marker ), 'Native app requirement missing: ' . $marker );
}

foreach ( array( 'StandardReferenceDate', 'FmcgReferenceDate', 'EffectiveDate', 'ExportLocalPackage', 'sidrena-cjenik.csv', 'sidrena-cjenik.xml', 'objava-cjenika.html' ) as $marker ) {
	sidrena_windows_assert( false !== strpos( $code, $marker ), 'Windows service marker missing: ' . $marker );
}

foreach ( array( 'OutputBaseFilename=setup', 'Sidrena.Windows.exe', 'AppName=Sidrena Desktop' ) as $marker ) {
	sidrena_windows_assert( false !== strpos( $setup_packaging, $marker ), 'Setup packaging marker missing: ' . $marker );
}

foreach ( array( 'OutputBaseFilename=portable', 'CreateUninstallRegKey=no', 'Uninstallable=no', 'Sidrena.Windows.exe' ) as $marker ) {
	sidrena_windows_assert( false !== strpos( $portable_package, $marker ), 'Portable packaging marker missing: ' . $marker );
}

foreach ( array( 'portable.exe', 'setup.exe', 'gh release upload', 'Verify complete release asset set', 'sidrena-wordpress-$env:VERSION.zip', 'sidrena-woocommerce-$env:VERSION.zip' ) as $marker ) {
	sidrena_windows_assert( false !== strpos( $release_workflow, $marker ), 'Windows release workflow marker missing: ' . $marker );
}

$forbidden = array(
	'System.Windows.Forms',
	'PresentationFramework',
	'DllImport',
	'user32.dll',
	'kernel32.dll',
	'Electron',
	'electron',
	'Tauri',
	'tauri',
	'WebView2',
	'Microsoft.Web.WebView2',
	'<WebView',
	'node_modules',
	'package.json',
	'vite',
	'react',
	'nextjs',
	'next.js',
);

$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/windows', FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
	if ( ! $file->isFile() ) {
		continue;
	}

	$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
	if ( 'windows/README.md' === $relative ) {
		continue;
	}

	$content = file_get_contents( $file->getPathname() );
	foreach ( $forbidden as $needle ) {
		sidrena_windows_assert( false === stripos( $content, $needle ), 'Non-native or legacy desktop marker found in ' . $file->getPathname() . ': ' . $needle );
	}
}

fwrite( STDOUT, "Sidrena native Windows app smoke test passed." . PHP_EOL );
