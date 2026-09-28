<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

$root = dirname( __DIR__, 2 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'WP_CLI', false );
define( 'SIDRENA_VERSION', 'test' );
define( 'SIDRENA_EDITION', 'woocommerce' );
define( 'SIDRENA_FILE', $root . '/editions/woocommerce/sidrena-woocommerce.php' );
define( 'SIDRENA_DIR', $root . '/' );
define( 'SIDRENA_URL', 'https://example.test/wp-content/plugins/brendigo-sidrena-cijena/' );

$GLOBALS['sidrena_actions'] = array();

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $priority, $accepted_args );
	$GLOBALS['sidrena_actions'][ $hook ][] = $callback;
}
function register_activation_hook( $file, $callback ) { unset( $file, $callback ); }
function register_deactivation_hook( $file, $callback ) { unset( $file, $callback ); }
function current_user_can( $capability ) { return 'activate_plugins' === $capability; }
function get_current_screen() { return (object) array( 'id' => 'plugins' ); }
function esc_html__( $text, $domain = null ) { unset( $domain ); return $text; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', (string) $value ) ); }

require $root . '/includes/sidrena-bootstrap.php';

function sidrena_dependency_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

sidrena_dependency_assert( ! class_exists( 'WooCommerce' ), 'Test precondition failed: WooCommerce must be absent.' );
sidrena_dependency_assert( ! function_exists( 'wc_get_product' ), 'Test precondition failed: WooCommerce functions must be absent.' );
sidrena_dependency_assert( ! class_exists( 'Sidrena_Products' ), 'WooCommerce product runtime loaded before dependency verification.' );
sidrena_dependency_assert( ! class_exists( 'Sidrena_History' ), 'WooCommerce history runtime loaded before dependency verification.' );
sidrena_dependency_assert( ! empty( $GLOBALS['sidrena_actions']['plugins_loaded'] ), 'Dependency-safe plugins_loaded bootstrap is missing.' );

foreach ( $GLOBALS['sidrena_actions']['plugins_loaded'] as $callback ) {
	call_user_func( $callback );
}

sidrena_dependency_assert( ! class_exists( 'Sidrena_Products' ), 'WooCommerce product runtime loaded even though WooCommerce is unavailable.' );
sidrena_dependency_assert( empty( $GLOBALS['sidrena_actions']['init'] ), 'Upgrade/scheduling runtime must remain inactive without WooCommerce.' );
sidrena_dependency_assert( empty( $GLOBALS['sidrena_actions']['admin_menu'] ), 'SIDRENA WooCommerce admin runtime must not start without WooCommerce.' );
sidrena_dependency_assert( ! empty( $GLOBALS['sidrena_actions']['admin_notices'] ), 'A scoped dependency notice must be registered when WooCommerce is unavailable.' );

ob_start();
foreach ( $GLOBALS['sidrena_actions']['admin_notices'] as $callback ) {
	call_user_func( $callback );
}
$notice = ob_get_clean();

sidrena_dependency_assert(
	false !== strpos( $notice, 'SIDRENA zahtijeva aktivan WooCommerce za WooCommerce izdanje.' ),
	'Dependency notice does not clearly explain that WooCommerce is required.'
);

fwrite( STDOUT, "SIDRENA WooCommerce missing-dependency smoke test passed.\n" );
