<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( preg_replace( '/[\x00-\x1F\x7F]+/', '', (string) $value ) );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) );
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		unset( $domain );
		return $text;
	}
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-legal-automation.php';
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-pricelist.php';

function sidrena_schema_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$instance = Sidrena_Pricelist::instance();

$product_method = new ReflectionMethod( 'Sidrena_Pricelist', 'product_headers' );
$product_method->setAccessible( true );
$product_headers = $product_method->invoke( $instance );

$service_method = new ReflectionMethod( 'Sidrena_Pricelist', 'service_headers' );
$service_method->setAccessible( true );
$service_headers = $service_method->invoke( $instance );

$required_products = array(
	'naziv',
	'sifra',
	'marka',
	'jedinica_mjere',
	'cijena_za_jedinicu_mjere',
	'maloprodajna_cijena',
	'posebni_oblik_prodaje',
	'naziv_posebnog_oblika_prodaje',
	'sidrena_cijena',
	'barkod',
	'dostupnost',
);

$required_services = array(
	'naziv_usluge',
	'maloprodajna_cijena',
	'posebni_oblik_prodaje',
	'naziv_posebnog_oblika_prodaje',
	'sidrena_cijena',
);

foreach ( $required_products as $header ) {
	sidrena_schema_assert( in_array( $header, $product_headers, true ), 'Required NN 101/2026 product field missing: ' . $header );
}
foreach ( $required_services as $header ) {
	sidrena_schema_assert( in_array( $header, $service_headers, true ), 'Required NN 101/2026 service field missing: ' . $header );
}

sidrena_schema_assert( count( $product_headers ) === count( array_unique( $product_headers ) ), 'Duplicate product headers detected.' );
sidrena_schema_assert( count( $service_headers ) === count( array_unique( $service_headers ) ), 'Duplicate service headers detected.' );
sidrena_schema_assert( in_array( 'datum_sidrene_cijene', $product_headers, true ), 'Reference date traceability missing from product output.' );
sidrena_schema_assert( in_array( 'datum_sidrene_cijene', $service_headers, true ), 'Reference date traceability missing from service output.' );
foreach ( array( 'vrsta_usluge', 'opseg_usluge', 'pripadajuci_troskovi', 'ugradbena_zamjenska_roba' ) as $service_detail ) {
	sidrena_schema_assert( in_array( $service_detail, $service_headers, true ), 'NN 105/2026 service display field missing: ' . $service_detail );
}

$utils_source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php' );
sidrena_schema_assert( false !== strpos( $utils_source, "'default_ref_date'     => '2026-09-10'" ), 'Default reference date must remain 10.09.2026.' );
sidrena_schema_assert( false !== strpos( $utils_source, "'fmcg_ref_date'        => '2025-05-02'" ), 'Existing FMCG reference date must remain 02.05.2025.' );
sidrena_schema_assert( false !== strpos( $utils_source, "'retention_days'       => 45" ), 'Default archive retention should preserve an operational margin above 30 days.' );
sidrena_schema_assert( false !== strpos( $utils_source, "max( 30, absint( \$settings['retention_days'] ) )" ), 'Archive retention must never fall below 30 days.' );

$admin_source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-admin.php' );
$compliance_source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-compliance.php' );
sidrena_schema_assert( false !== strpos( $admin_source, 'NN 105/2026 · primjena od 26.09.2026.' ), 'NN 105/2026 effective-date guide missing from admin rules.' );
sidrena_schema_assert( false !== strpos( $admin_source, 'Hrana i hrana za životinje' ), 'Unit-price applicability guide is missing required product categories.' );
sidrena_schema_assert( false !== strpos( $admin_source, 'Pakiranja ispod 50 g ili 50 ml' ), 'Unit-price exceptions guide is missing threshold exceptions.' );
sidrena_schema_assert( false !== strpos( $admin_source, 'ne automatska pravna odluka' ), 'Unit-price guide must preserve human legal classification.' );
sidrena_schema_assert( false !== strpos( $admin_source, 'Ministarstvo gospodarstva · 22.09.2026.' ), 'Official MINGO clarification date must remain 22.09.2026.' );
sidrena_schema_assert( false !== strpos( $compliance_source, 'mingo_2026_09_22_clarifications' ), 'Official MINGO clarification source key must remain aligned to 22.09.2026.' );
sidrena_schema_assert( false !== strpos( $compliance_source, 'nn_59_2026_base_price_future' ), 'Future bazna-cijena source must remain separate from the NN 101/2026 sidrena-price layer.' );
sidrena_schema_assert( false !== strpos( $compliance_source, '17.11.2026' ), 'Bazna-price readiness must retain the statutory 17.11.2026 application marker.' );
sidrena_schema_assert( false !== strpos( $admin_source, 'https://mingo.gov.hr/print.aspx?id=10440&url=print' ), 'Official MINGO clarification URL must remain linked from the rules screen.' );

$pricelist_source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-pricelist.php' );
$services_source  = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-services.php' );
sidrena_schema_assert( false === strpos( $pricelist_source, 'nedostaje vrsta usluge' ), 'Service type must remain optional in strict NN 101/2026 publication preflight.' );
sidrena_schema_assert( false === strpos( $pricelist_source, 'nedostaje opseg usluge' ), 'Service scope must remain optional in strict NN 101/2026 publication preflight.' );
sidrena_schema_assert( false === strpos( $pricelist_source, "'barkod'              => __( 'barkod'" ), 'Barcode must not block publication when it is not applicable.' );
sidrena_schema_assert( false === strpos( $services_source, "add_action( 'transition_post_status'" ), 'Service anchor snapshot must not run before service meta is saved.' );
sidrena_schema_assert( false !== strpos( $services_source, "add_action( 'wp_after_insert_post'" ), 'Service finalization must run after custom meta save hooks.' );
sidrena_schema_assert( false !== strpos( $services_source, '$this->snapshot_newly_published( $post_id, $post );' ), 'First publication must snapshot the submitted current price after meta save.' );

sidrena_schema_assert( '06:30' === Sidrena_Legal_Automation::normalize_generation_time( '08:00' ), 'Generation at 08:00 or later must be clamped before the publication deadline.' );
sidrena_schema_assert( '06:30' === Sidrena_Legal_Automation::normalize_generation_time( '09:15' ), 'Generation after the publication deadline must be clamped.' );
sidrena_schema_assert( '07:59' === Sidrena_Legal_Automation::normalize_generation_time( '7:59' ), 'Generation before 08:00 must be accepted and normalized.' );
sidrena_schema_assert( '06:30' === Sidrena_Legal_Automation::normalize_generation_time( 'not-a-time' ), 'Invalid generation time must fall back to the safe default.' );
sidrena_schema_assert( '08:00' === Sidrena_Legal_Automation::publication_deadline(), 'Publication deadline marker must remain 08:00.' );

$hardened = Sidrena_Legal_Automation::normalize_settings(
	array(
		'generation_time'       => '12:15',
		'retention_days'        => 7,
		'generate_csv'          => 'no',
		'generate_xml'          => 'no',
		'enable_public_html'    => 'no',
		'publish_manifest'      => 'no',
		'strict_publication'    => 'no',
		'failure_notifications' => 'no',
	)
);
sidrena_schema_assert( '06:30' === $hardened['generation_time'], 'Unsafe generation time was not automatically hardened.' );
sidrena_schema_assert( 30 === $hardened['retention_days'], 'Archive retention must be hardened to at least 30 days.' );
foreach ( Sidrena_Legal_Automation::required_publication_flags() as $required_flag ) {
	sidrena_schema_assert( 'yes' === $hardened[ $required_flag ], 'Required publication safety flag not hardened: ' . $required_flag );
}
sidrena_schema_assert( 'yes' === $hardened['generate_csv'], 'At least one machine-readable format must be enabled when both CSV and XML are disabled.' );
sidrena_schema_assert( 'no' === $hardened['generate_xml'], 'XML must remain a user choice when CSV already satisfies the machine-readable publication requirement.' );
sidrena_schema_assert( 'no' === $hardened['enable_public_html'], 'Public HTML is optional and must not be forced by legal automation.' );
sidrena_schema_assert( 'no' === $hardened['publish_manifest'], 'Manifest publication is optional and must not be forced by legal automation.' );

sidrena_schema_assert( 'yes' === $hardened['display_anchor'], 'Public sidrena-price display must remain enabled by the safe legal profile.' );
sidrena_schema_assert( 'yes' === $hardened['display_lowest_30'], '30-day reference display must remain enabled by the safe legal profile.' );
sidrena_schema_assert( 'yes' === $hardened['track_price_history'], 'Price history must remain enabled so 30-day references stay auditable.' );

$validate_product = new ReflectionMethod( 'Sidrena_Pricelist', 'validate_product_row' );
$validate_product->setAccessible( true );
$base_product = array(
	'_sidrena_item_id'               => 1,
	'_sidrena_unit_status'           => 'not_required',
	'_sidrena_location_explicit'     => 'yes',
	'_sidrena_sale_reference_status' => 'ready',
	'_sidrena_sale_reference_source' => 'manual',
	'_sidrena_expiry_date'           => '',
	'naziv'                          => 'Test proizvod',
	'sifra'                          => 'TEST-1',
	'marka'                          => 'Test',
	'maloprodajna_cijena'            => '10.00',
	'sidrena_cijena'                 => '12.00',
	'dostupnost'                     => 'dostupno',
	'posebni_oblik_prodaje'          => 'da',
	'naziv_posebnog_oblika_prodaje'  => 'Akcija',
);
sidrena_schema_assert( array() === $validate_product->invoke( $instance, $base_product, 'objekt' ), 'Complete physical-location product sale must pass strict publication preflight.' );

$incomplete_product = $base_product;
$incomplete_product['_sidrena_sale_reference_status'] = 'incomplete';
sidrena_schema_assert( ! empty( $validate_product->invoke( $instance, $incomplete_product, 'objekt' ) ), 'Product sale without a 30-day reference must fail strict publication preflight.' );

$perishable_product = $base_product;
$perishable_product['_sidrena_sale_reference_status'] = 'exempt';
$perishable_product['_sidrena_sale_reference_source'] = 'perishable';
sidrena_schema_assert( ! empty( $validate_product->invoke( $instance, $perishable_product, 'objekt' ) ), 'Perishable-sale exemption without expiry date must fail strict publication preflight.' );
$perishable_product['_sidrena_expiry_date'] = '2026-10-15';
sidrena_schema_assert( array() === $validate_product->invoke( $instance, $perishable_product, 'objekt' ), 'Perishable-sale exemption with expiry date must pass strict publication preflight.' );

$validate_service = new ReflectionMethod( 'Sidrena_Pricelist', 'validate_service_row' );
$validate_service->setAccessible( true );
$base_service = array(
	'_sidrena_item_id'               => 2,
	'_sidrena_sale_reference_status' => 'ready',
	'_sidrena_sale_reference_source' => 'manual',
	'naziv_usluge'                   => 'Test usluga',
	'maloprodajna_cijena'            => '50.00',
	'sidrena_cijena'                 => '60.00',
	'posebni_oblik_prodaje'          => 'da',
	'naziv_posebnog_oblika_prodaje'  => 'Akcija',
);
sidrena_schema_assert( array() === $validate_service->invoke( $instance, $base_service, 'objekt' ), 'Physical-location service sale with a 30-day reference must pass strict publication preflight.' );

$distance_service = $base_service;
$distance_service['_sidrena_sale_reference_status'] = 'exempt';
$distance_service['_sidrena_sale_reference_source'] = 'distance';
sidrena_schema_assert( ! empty( $validate_service->invoke( $instance, $distance_service, 'objekt' ) ), 'Distance-contract service exemption must not bypass the 30-day rule for a physical-location price list.' );
sidrena_schema_assert( array() === $validate_service->invoke( $instance, $distance_service, 'webshop' ), 'Distance-contract service exemption may be recorded for the webshop channel.' );

$standalone_source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-standalone.php' );
sidrena_schema_assert( false !== strpos( $standalone_source, '_sidrena_standalone_location_availability' ), 'WordPress edition must retain per-location availability data.' );
sidrena_schema_assert( false !== strpos( $standalone_source, "'_sidrena_location_explicit'     => ( " . '$has_location_status' . " || 'webshop' === " . '$location_kind' . " ) ? 'yes' : 'no'" ), 'Physical WordPress locations must not reuse global availability as an explicit per-location status.' );
sidrena_schema_assert( false !== strpos( $standalone_source, '_sidrena_standalone_sale_reference_exemption' ), 'WordPress edition must support product 30-day reference exemptions.' );
sidrena_schema_assert( false !== strpos( $standalone_source, '_sidrena_standalone_expiry_date' ), 'WordPress edition must retain expiry date for perishable/fast-expiry sale exemptions.' );
sidrena_schema_assert( false !== strpos( $standalone_source, "sidrena-expiry" ), 'WordPress public price output must render the saved expiry date for a perishable/fast-expiry sale exemption.' );

fwrite( STDOUT, "Sidrena NN 101/2026 + NN 105/2026 schema and automation smoke test passed.\n" );
