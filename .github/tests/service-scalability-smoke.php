<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://sidrene-cijene.com.hr/
 * @see https://brendigo.com/
 */

$services = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-services.php' );
$admin    = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-admin.php' );
$css      = file_get_contents( dirname( __DIR__, 2 ) . '/public/css/frontend.css' );

function sidrena_service_scale_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

sidrena_service_scale_assert( 0 === preg_match( "/'posts_per_page'\s*=>\s*-1/", $services ), 'Service shortcode must not query all services at once.' );
sidrena_service_scale_assert( 0 === preg_match( "/'posts_per_page'\s*=>\s*-1/", $admin ), 'Admin service audit must not query all services at once.' );
sidrena_service_scale_assert( false !== strpos( $services, "'po_stranici' => 50" ), 'Service shortcode needs a bounded default page size.' );
sidrena_service_scale_assert( false !== strpos( $services, "min( 100, max( 10" ), 'Service shortcode page size must be bounded to 10–100.' );
sidrena_service_scale_assert( false !== strpos( $services, "'sidrena_usluge_stranica'" ), 'Service shortcode page query parameter is missing.' );
sidrena_service_scale_assert( false !== strpos( $services, "private function service_page_query(" ), 'Service shortcode bounded query helper is missing.' );
sidrena_service_scale_assert( false !== strpos( $admin, 'private function service_audit_stats()' ), 'Admin service audit batching helper is missing.' );
sidrena_service_scale_assert( false !== strpos( $admin, '$batch_size = 250;' ), 'Admin service audit batch size is missing.' );
sidrena_service_scale_assert( false !== strpos( $css, '.sidrena-services__pagination' ), 'Service pagination CSS is missing.' );
sidrena_service_scale_assert( false !== strpos( $css, '.sidrena-services__pagination a:focus-visible' ), 'Service pagination keyboard focus style is missing.' );

fwrite( STDOUT, "Sidrena service scalability smoke test passed.\n" );
