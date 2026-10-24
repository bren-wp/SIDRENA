<?php
/** Standalone legal contract regression test (no database required). */
define( 'ABSPATH', __DIR__ . '/' );
require_once __DIR__ . '/../wordpress/brendigo-sidrena-cijena/includes/class-legal.php';

function sidrena_expect( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}
$contract = Brendigo_Sidrena_Cijena_Legal::public_contract();
sidrena_expect( 1 === $contract['schema'], 'Versioned contract' );
sidrena_expect( 'HR' === $contract['jurisdiction'], 'Croatian jurisdiction' );
sidrena_expect( '2026-11-17' === $contract['effective_from'], 'Amended effective date' );
sidrena_expect( '2025-05-02' === $contract['reference_dates']['legacy_fmcg'], 'Legacy date' );
sidrena_expect( '2026-09-10' === $contract['reference_dates']['general_goods_services'], 'General date' );
sidrena_expect( 30 === $contract['archive_min_days'], 'Archive minimum' );
sidrena_expect( '08:00' === $contract['product_publication_deadline'], 'Daily deadline' );
sidrena_expect( '' === Brendigo_Sidrena_Cijena_Legal::reference_date( 'unknown' ), 'No inferred category' );
sidrena_expect( '2025-05-02' === Brendigo_Sidrena_Cijena_Legal::reference_date( 'legacy_fmcg' ), 'Verified legacy date' );
sidrena_expect( '2026-09-10' === Brendigo_Sidrena_Cijena_Legal::reference_date( 'general_goods_services' ), 'Verified new date' );
sidrena_expect( ! empty( $contract['source_urls'] ), 'Legal sources' );
fwrite( STDOUT, "PASS: SIDRENA legal contract\n" );
