<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */


$services      = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-services.php' );
$history       = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-service-history.php' );
$price_history = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-history.php' );
$admin          = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-admin.php' );
$css            = file_get_contents( dirname( __DIR__, 2 ) . '/public/css/frontend.css' );

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
sidrena_service_scale_assert( false === strpos( $services, "add_action( 'transition_post_status'" ), 'Service anchor snapshot must not run before submitted service price metadata is saved.' );
sidrena_service_scale_assert( false !== strpos( $services, "add_action( 'wp_after_insert_post'" ), 'Service post-save finalization hook is missing.' );
sidrena_service_scale_assert( false !== strpos( $services, 'private function snapshot_newly_published' ), 'Service first-publication anchor snapshot helper is missing.' );
sidrena_service_scale_assert( false !== strpos( $history, "\$wpdb->prepare( 'SELECT COUNT(*) FROM %i', \$table )" ), 'Service history count query must prepare the custom table identifier.' );
sidrena_service_scale_assert( false === strpos( $history, 'SELECT COUNT(*) FROM {$table}' ), 'Service history count query must not interpolate the custom table identifier.' );
sidrena_service_scale_assert( false !== strpos( $price_history, 'private function catalog_parent_ids_keyset( $post_type, $batch_size )' ), 'Daily price-history snapshot needs a shared keyset catalog iterator.' );
sidrena_service_scale_assert( false !== strpos( $price_history, 'AND ID > %d ORDER BY ID ASC LIMIT %d' ), 'Daily price-history catalog scan must advance by ID keyset.' );
sidrena_service_scale_assert( false !== strpos( $price_history, '$this->catalog_parent_ids_keyset( Sidrena_Standalone::POST_TYPE, 250 )' ), 'WordPress history snapshot must use bounded 250-item keyset batches.' );
sidrena_service_scale_assert( false !== strpos( $price_history, '$this->catalog_parent_ids_keyset( \'product\', 100 )' ), 'WooCommerce history snapshot must use bounded 100-product keyset batches.' );
sidrena_service_scale_assert( false === strpos( $price_history, "'paged'          => \$page" ), 'Daily WordPress history snapshot must not restore page/OFFSET pagination.' );
sidrena_service_scale_assert( false === strpos( $price_history, "'page'    => \$page" ), 'Daily WooCommerce history snapshot must not restore page/OFFSET pagination.' );

fwrite( STDOUT, "Sidrena service scalability smoke test passed.\n" );
