<?php
require dirname( __DIR__, 2 ) . '/static-php/src/SidrenaStatic.php';

function sidrena_static_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

function sidrena_static_remove_tree( $path ) {
	if ( ! is_dir( $path ) ) {
		return;
	}
	$items = scandir( $path );
	foreach ( is_array( $items ) ? $items : array() as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$full = $path . DIRECTORY_SEPARATOR . $item;
		if ( is_dir( $full ) ) {
			sidrena_static_remove_tree( $full );
		} else {
			@unlink( $full );
		}
	}
	@rmdir( $path );
}

$base = sys_get_temp_dir() . '/sidrena-static-' . bin2hex( random_bytes( 6 ) );
mkdir( $base . '/storage/source', 0755, true );

$product_csv = implode(
	";",
	array(
		'NAZIV PROIZVODA',
		'ŠIFRA PROIZVODA',
		'MARKA',
		'KOLIČINA PAKIRANJA',
		'JEDINICA PAKIRANJA',
		'JEDINIČNA CIJENA STATUS',
		'JEDINICA MJERE',
		'CIJENA ZA JEDINICU MJERE',
		'MALOPRODAJNA CIJENA',
		'POSEBNI OBLIK PRODAJE',
		'NAZIV POSEBNOG OBLIKA PRODAJE',
		'SIDRENA CIJENA',
		'DATUM SIDRENE CIJENE',
		'BARKOD',
		'DOSTUPNOST',
	)
) . "\n";
$product_csv .= '=Formula test;KAVA-750;Primjer;750;g;required;;;6,00;ne;;5,50;10.09.2026;3850000000000;dostupno' . "\n\n";
file_put_contents( $base . '/storage/source/products.csv', $product_csv );

$service_csv = "naziv_usluge;vrsta_usluge;opseg_usluge;pripadajuci_troskovi;ugradbena_zamjenska_roba;maloprodajna_cijena;posebni_oblik_prodaje;naziv_posebnog_oblika_prodaje;sidrena_cijena;datum_sidrene_cijene\n";
$service_csv .= "Montaža uređaja;montaža;do 60 min;dolazak uključen;;45,00;ne;;40,00;10.09.2026\n";
file_put_contents( $base . '/storage/source/services.csv', $service_csv );

$config = array(
	'site_name' => 'Test',
	'timezone' => 'Europe/Zagreb',
	'strict_validation' => true,
	'auto_generate_on_request' => false,
	'retention_days' => 30,
	'location' => array(
		'id' => 'webshop',
		'kind' => 'webshop',
		'code' => 'WEB-01',
		'address' => 'Zagreb',
		'sequence_products' => 1,
		'sequence_services' => 2,
	),
);

$sidrena = new SidrenaStatic( $config, $base );
$status  = $sidrena->generate();

sidrena_static_assert( ! empty( $status['ok'] ), 'Static generation did not succeed.' );
sidrena_static_assert( 1 === (int) $status['counts']['products'], 'Expected one generated product.' );
sidrena_static_assert( 1 === (int) $status['counts']['services'], 'Expected one generated service.' );

$snapshot = $sidrena->readSnapshot();
sidrena_static_assert( 1 === count( $snapshot['products'] ), 'Snapshot product count mismatch.' );
sidrena_static_assert( 'kg' === $snapshot['products'][0]['jedinica_mjere'], 'Automatic base unit calculation failed.' );
sidrena_static_assert( '8' === $snapshot['products'][0]['cijena_za_jedinicu_mjere'], 'Automatic unit price calculation failed.' );
sidrena_static_assert( '2026-09-10' === $snapshot['products'][0]['datum_sidrene_cijene'], 'Croatian date normalization failed.' );
sidrena_static_assert( 'Montaža uređaja' === $snapshot['services'][0]['naziv_usluge'], 'Croatian UTF-8 service data was damaged.' );

$manifest = $sidrena->readManifest();
foreach ( array( 'products_csv', 'products_xml', 'services_csv', 'services_xml' ) as $key ) {
	sidrena_static_assert( isset( $manifest['files'][ $key ] ), 'Missing manifest file descriptor: ' . $key );
	$file = $sidrena->resolveDownload( $key );
	sidrena_static_assert( is_array( $file ) && is_file( $file['path'] ), 'Download resolution failed for: ' . $key );
	sidrena_static_assert( ! empty( $manifest['files'][ $key ]['sha256'] ), 'SHA-256 missing for: ' . $key );
}

$product_file = $sidrena->resolveDownload( 'products_csv' );
$csv = file_get_contents( $product_file['path'] );
sidrena_static_assert( false !== strpos( $csv, "'=Formula test" ), 'CSV formula-injection protection failed.' );

$xml_file = $sidrena->resolveDownload( 'products_xml' );
$xml = file_get_contents( $xml_file['path'] );
sidrena_static_assert( false !== strpos( $xml, '<proizvodi>' ), 'Products XML root missing.' );
sidrena_static_assert( false !== strpos( $xml, '<sifra>KAVA-750</sifra>' ), 'Products XML content missing.' );

$archive = $sidrena->readArchiveIndex( 20 );
sidrena_static_assert( 4 === count( $archive ), 'Expected four archive entries from one generation.' );
$archive_download = $sidrena->resolveDownload( '', $archive[0]['id'] );
sidrena_static_assert( is_array( $archive_download ) && is_file( $archive_download['path'] ), 'Archive download resolution failed.' );

// Regression: an invalid availability value must stop publication and preserve
// the previous manifest/snapshot/current files.
$manifest_before = file_get_contents( $base . '/storage/generated/manifest.json' );
$current_before  = hash_file( 'sha256', $product_file['path'] );
$broken_product_csv = str_replace( ';dostupno' . "\n", ';dostupnoo' . "\n", $product_csv );
file_put_contents( $base . '/storage/source/products.csv', $broken_product_csv );

$failed = false;
try {
	$sidrena->generate();
} catch ( RuntimeException $e ) {
	$failed = true;
	sidrena_static_assert( false !== strpos( $e->getMessage(), 'nepoznata vrijednost dostupnosti' ), 'Invalid availability failure was not explicit.' );
}
sidrena_static_assert( $failed, 'Invalid availability must block strict publication.' );
sidrena_static_assert( $manifest_before === file_get_contents( $base . '/storage/generated/manifest.json' ), 'Failed publication changed the active manifest.' );
$product_after = $sidrena->resolveDownload( 'products_csv' );
sidrena_static_assert( is_array( $product_after ) && $current_before === hash_file( 'sha256', $product_after['path'] ), 'Failed publication changed the active products CSV.' );
$snapshot_after = $sidrena->readSnapshot();
sidrena_static_assert( 'dostupno' === $snapshot_after['products'][0]['dostupnost'], 'Failed publication changed the active snapshot.' );
sidrena_static_assert( 4 === count( $sidrena->readArchiveIndex( 20 ) ), 'Failed preflight must not add archive entries.' );

// Regression: impossible Croatian dates must be rejected rather than silently
// normalized by DateTimeImmutable.
$invalid_date_csv = str_replace( '10.09.2026', '31.02.2026.', $product_csv );
file_put_contents( $base . '/storage/source/products.csv', $invalid_date_csv );
$date_failed = false;
try {
	$sidrena->generate();
} catch ( RuntimeException $e ) {
	$date_failed = true;
	sidrena_static_assert( false !== strpos( $e->getMessage(), 'referentni datum' ), 'Invalid date failure was not explicit.' );
}
sidrena_static_assert( $date_failed, 'Impossible anchor date must block strict publication.' );
sidrena_static_assert( $manifest_before === file_get_contents( $base . '/storage/generated/manifest.json' ), 'Invalid date changed the active manifest.' );
sidrena_static_assert( 4 === count( $sidrena->readArchiveIndex( 20 ) ), 'Invalid date must not add archive entries.' );

sidrena_static_assert( 'static-php' === $sidrena->runtimeInfo()['edition'], 'Static edition runtime marker missing.' );
sidrena_static_assert( SidrenaStatic::VERSION === '1.7.0', 'Static edition version mismatch.' );

sidrena_static_remove_tree( $base );
fwrite( STDOUT, "Sidrena Static PHP smoke test passed.\n" );
