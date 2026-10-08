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

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php';
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
sidrena_schema_assert( ! in_array( 'najniza_cijena_30_dana', $product_headers, true ), 'HTML-only 30-day reference must not silently change the NN 101/2026 product CSV/XML header set.' );
sidrena_schema_assert( ! in_array( 'krajnji_rok_uporabe', $product_headers, true ), 'HTML-only expiry detail must not silently change the NN 101/2026 product CSV/XML header set.' );
sidrena_schema_assert( ! in_array( 'najniza_cijena_30_dana', $service_headers, true ), 'HTML-only service sale reference must not silently change the machine-readable service header set.' );
foreach ( array( 'vrsta_usluge', 'opseg_usluge', 'pripadajuci_troskovi', 'ugradbena_zamjenska_roba' ) as $service_detail ) {
	sidrena_schema_assert( in_array( $service_detail, $service_headers, true ), 'NN 105/2026 service display field missing: ' . $service_detail );
}

$utils_source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php' );
sidrena_schema_assert( false !== strpos( $utils_source, "const STANDARD_REFERENCE_DATE = '2026-09-10';" ), 'Standard reference date must remain immutable at 10.09.2026.' );
sidrena_schema_assert( false !== strpos( $utils_source, "const FMCG_REFERENCE_DATE     = '2025-05-02';" ), 'Existing FMCG reference date must remain immutable at 02.05.2025.' );
sidrena_schema_assert( ! array_key_exists( 'default_ref_date', Sidrena_Utils::defaults() ) && ! array_key_exists( 'fmcg_ref_date', Sidrena_Utils::defaults() ), 'Legal reference dates must not be administrator defaults.' );
sidrena_schema_assert( 1 === preg_match( "/'retention_days'\\s*=>\\s*30/", $utils_source ), 'Public price-list archive must default to 30 days.' );
sidrena_schema_assert( false !== strpos( $utils_source, "max( 30, absint( \$settings['retention_days'] ) )" ), 'Runtime archive retention must enforce a 30-day minimum without discarding a longer configured retention.' );

$admin_source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-admin.php' );
$compliance_source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-compliance.php' );
sidrena_schema_assert( false !== strpos( $admin_source, 'NN 105/2026 · primjena od 26.09.2026.' ), 'NN 105/2026 effective-date guide missing from admin rules.' );
sidrena_schema_assert( false !== strpos( $admin_source, 'Hrana i hrana za životinje' ), 'Unit-price applicability guide is missing required product categories.' );
sidrena_schema_assert( false !== strpos( $admin_source, 'Pakiranja ispod 50 g ili 50 ml' ), 'Unit-price exceptions guide is missing threshold exceptions.' );
sidrena_schema_assert( false !== strpos( $admin_source, 'ne automatska pravna odluka' ), 'Unit-price guide must preserve human legal classification.' );
sidrena_schema_assert( false !== strpos( $admin_source, 'Ministarstvo gospodarstva · 22.09.2026.' ), 'Official MINGO clarification date must remain 22.09.2026.' );
sidrena_schema_assert( false !== strpos( $utils_source, "const LEGAL_VERIFIED_DATE     = '2026-10-08';" ), 'Legal ruleset verification date must match the current review.' );
sidrena_schema_assert( false !== strpos( $utils_source, "const ANCHOR_SOURCE_URL       = 'https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html';" ), 'SIDRENA anchor-price ruleset must link to the dedicated NN 101/2026-1212 decision.' );
sidrena_schema_assert( false !== strpos( $utils_source, "const PRICELIST_SOURCE_URL    = 'https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html';" ), 'Digital-pricelist ruleset must link to the separate NN 101/2026-1213 decision.' );
sidrena_schema_assert( false !== strpos( $admin_source, 'Izvor SIDRENA cijene' ) && false !== strpos( $admin_source, 'Izvor digitalnog cjenika' ), 'Settings must display the anchor-price and digital-pricelist legal sources separately.' );
sidrena_schema_assert( false !== strpos( $admin_source, 'Zakonska pravila' ) && false !== strpos( $admin_source, 'Zadnja pravna provjera SIDRENA ruleseta' ), 'Settings must expose the legal ruleset as a read-only reference panel.' );
sidrena_schema_assert( false !== strpos( $admin_source, 'Sidrena/referentna cijena i njezin datum vode se odvojeno od aktualne cijene, 30-dnevne najniže cijene i WooCommerce akcijske cijene.' ), 'Settings must explicitly separate anchor, current, 30-day and Woo sale-price concepts.' );
sidrena_schema_assert( false !== strpos( $compliance_source, 'mingo_2026_09_22_clarifications' ), 'Official MINGO clarification source key must remain aligned to 22.09.2026.' );
sidrena_schema_assert( false !== strpos( $compliance_source, 'nn_59_2026_base_price_future' ), 'Future bazna-cijena source must remain separate from the NN 101/2026 sidrena-price layer.' );
sidrena_schema_assert( false !== strpos( $compliance_source, '17.11.2026' ), 'Bazna-price readiness must retain the statutory 17.11.2026 application marker.' );
sidrena_schema_assert( false !== strpos( $admin_source, 'https://mingo.gov.hr/print.aspx?id=10440&url=print' ), 'Official MINGO clarification URL must remain linked from the rules screen.' );
$legal_notes = file_get_contents( dirname( __DIR__, 2 ) . '/docs/legal-and-technical-notes.md' );
sidrena_schema_assert( false !== strpos( $admin_source, 'najkasnije do 08:00 dana kada objavljuju izmjenu cjenika usluga' ), 'Admin legal copy must match NN 101/2026 service publication timing.' );
sidrena_schema_assert( false !== strpos( $legal_notes, 'najkasnije do 08:00 dana kada objavljuje izmjenu cjenika usluga' ), 'Legal notes must match NN 101/2026 service publication timing.' );
sidrena_schema_assert( false === strpos( $admin_source, 'na dan stupanja promjene na snagu' ) && false === strpos( $legal_notes, 'na dan stupanja promjene na snagu' ), 'Retired service-deadline wording must not return.' );

$pricelist_source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-pricelist.php' );
$services_source  = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-services.php' );
sidrena_schema_assert( false === strpos( $pricelist_source, 'nedostaje vrsta usluge' ), 'Service type must remain optional in strict NN 101/2026 publication preflight.' );
sidrena_schema_assert( false === strpos( $pricelist_source, 'nedostaje opseg usluge' ), 'Service scope must remain optional in strict NN 101/2026 publication preflight.' );
sidrena_schema_assert( false === strpos( $pricelist_source, "'barkod'              => __( 'barkod'" ), 'Barcode must not block publication when it is not applicable.' );
sidrena_schema_assert( false === strpos( $pricelist_source, 'sale_reference' ), 'Active price-list generation must not depend on the retired 30-day sale-price reference workflow.' );
sidrena_schema_assert( false !== strpos( $pricelist_source, "'posebni_oblik_prodaje'" ) && false !== strpos( $pricelist_source, "'naziv_posebnog_oblika_prodaje'" ), 'Price-list output must retain special-sale status and name.' );
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
		'automation_mode'       => 'invalid-mode',
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
sidrena_schema_assert( 'wp_cron' === $hardened['automation_mode'], 'Unknown publication automation mode must fail safe to internal WP-Cron.' );
$external_mode = Sidrena_Legal_Automation::normalize_settings( array( 'automation_mode' => 'external' ) );
sidrena_schema_assert( 'external' === $external_mode['automation_mode'], 'Explicit external server cron/WP-CLI mode must be preserved.' );
sidrena_schema_assert( 30 === $hardened['retention_days'], 'Archive retention below the legal minimum must be hardened to 30 days.' );
$extended_retention = Sidrena_Legal_Automation::normalize_settings( array( 'retention_days' => 90 ) );
sidrena_schema_assert( 90 === $extended_retention['retention_days'], 'A safer archive retention above 30 days must be preserved.' );
foreach ( Sidrena_Legal_Automation::required_publication_flags() as $required_flag ) {
	sidrena_schema_assert( 'yes' === $hardened[ $required_flag ], 'Required publication safety flag not hardened: ' . $required_flag );
}
sidrena_schema_assert( 'yes' === $hardened['generate_csv'], 'Premium automation must keep CSV enabled.' );
sidrena_schema_assert( 'yes' === $hardened['generate_xml'], 'Premium automation must keep XML enabled alongside CSV for maximum machine-readable interoperability.' );
sidrena_schema_assert( 'yes' === $hardened['enable_public_html'], 'Premium automation must keep the public price-list surface available.' );
sidrena_schema_assert( 'yes' === $hardened['enable_rest_index'], 'Premium automation must keep the REST discovery/index surface available.' );
sidrena_schema_assert( 'yes' === $hardened['publish_manifest'], 'Premium automation must keep the file manifest available for automated discovery.' );

sidrena_schema_assert( 'yes' === $hardened['display_anchor'], 'Public Sidrena-price display must remain enabled by the safe legal profile.' );
sidrena_schema_assert( ! array_key_exists( 'display_lowest_30', $hardened ) && ! array_key_exists( 'track_price_history', $hardened ), 'Retired 30-day sale-reference settings must not return to the active legal profile.' );

$validate_product = new ReflectionMethod( 'Sidrena_Pricelist', 'validate_product_row' );
$validate_product->setAccessible( true );
$base_product = array(
	'_sidrena_item_id'              => 1,
	'_sidrena_unit_status'          => 'not_required',
	'_sidrena_location_explicit'    => 'yes',
	'naziv'                         => 'Test proizvod',
	'sifra'                         => 'TEST-1',
	'barkod'                        => '3851234567890',
	'marka'                         => 'Test',
	'maloprodajna_cijena'           => '10.00',
	'sidrena_cijena'                => '12.00',
	'datum_sidrene_cijene'          => '10.09.2026.',
	'dostupnost'                    => 'dostupno',
	'posebni_oblik_prodaje'         => 'da',
	'naziv_posebnog_oblika_prodaje' => 'Akcija',
);
sidrena_schema_assert( array() === $validate_product->invoke( $instance, $base_product, 'objekt' ), 'Product with Sidrena price/date and named special sale must pass strict publication preflight without a 30-day sale-price reference.' );

$missing_barcode = $base_product;
$missing_barcode['barkod'] = '';
sidrena_schema_assert( ! empty( $validate_product->invoke( $instance, $missing_barcode, 'objekt' ) ), 'Product without the barcode required by NN 101/2026 must fail public CSV/XML preflight.' );


$missing_sale_name = $base_product;
$missing_sale_name['naziv_posebnog_oblika_prodaje'] = '';
sidrena_schema_assert( ! empty( $validate_product->invoke( $instance, $missing_sale_name, 'objekt' ) ), 'Special-sale product without the special-sale name must fail strict publication preflight.' );

$missing_anchor_date = $base_product;
$missing_anchor_date['datum_sidrene_cijene'] = '';
sidrena_schema_assert( ! empty( $validate_product->invoke( $instance, $missing_anchor_date, 'objekt' ) ), 'Product Sidrena price without a valid reference date must fail strict publication preflight.' );

$validate_service = new ReflectionMethod( 'Sidrena_Pricelist', 'validate_service_row' );
$validate_service->setAccessible( true );
$base_service = array(
	'_sidrena_item_id'              => 2,
	'naziv_usluge'                  => 'Test usluga',
	'maloprodajna_cijena'           => '50.00',
	'sidrena_cijena'                => '60.00',
	'datum_sidrene_cijene'          => '10.09.2026.',
	'posebni_oblik_prodaje'         => 'da',
	'naziv_posebnog_oblika_prodaje' => 'Akcija',
);
sidrena_schema_assert( array() === $validate_service->invoke( $instance, $base_service, 'objekt' ), 'Service with Sidrena price/date and named special sale must pass strict publication preflight.' );

$missing_service_sale_name = $base_service;
$missing_service_sale_name['naziv_posebnog_oblika_prodaje'] = '';
sidrena_schema_assert( ! empty( $validate_service->invoke( $instance, $missing_service_sale_name, 'objekt' ) ), 'Special-sale service without the special-sale name must fail strict publication preflight.' );

$standalone_source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-standalone.php' );
sidrena_schema_assert( false !== strpos( $standalone_source, '_sidrena_standalone_location_availability' ), 'WordPress edition must retain per-location availability data.' );
sidrena_schema_assert( false !== strpos( $standalone_source, "'_sidrena_location_explicit'" ) && false !== strpos( $standalone_source, "( \$has_location_status || 'webshop' === \$location_kind ) ? 'yes' : 'no'" ), 'Physical WordPress locations must not reuse global availability as an explicit per-location status.' );
sidrena_schema_assert( false !== strpos( $standalone_source, '_sidrena_standalone_reference_group' ), 'WordPress edition must retain the immutable/reference-group Sidrena ruleset.' );
sidrena_schema_assert( false === strpos( $standalone_source, '_sidrena_standalone_sale_reference_exemption' ) && false === strpos( $standalone_source, '_sidrena_standalone_lowest_30' ), 'WordPress active catalog must not retain the retired 30-day sale-price workflow.' );

$public_source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-public.php' );
sidrena_schema_assert( false === strpos( $public_source, "najniza_cijena_30_dana" ) && false === strpos( $public_source, "krajnji_rok_uporabe" ), 'Public HTML price list must not render the retired 30-day sale-price workflow.' );
sidrena_schema_assert( false !== strpos( $public_source, "naziv_posebnog_oblika_prodaje" ), 'Public HTML must retain the name of the current special form of sale.' );
sidrena_schema_assert( false !== strpos( $public_source, "vrsta_usluge" ) && false !== strpos( $public_source, "opseg_usluge" ), 'Public service price list must expose service type and scope.' );
sidrena_schema_assert( false !== strpos( $public_source, "pripadajuci_troskovi" ) && false !== strpos( $public_source, "ugradbena_zamjenska_roba" ), 'Public service price list must expose included costs and integral replacement/install goods details.' );

fwrite( STDOUT, "Sidrena NN 101/2026 + NN 105/2026 schema and automation smoke test passed.\n" );
