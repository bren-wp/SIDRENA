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

$edition = isset( $argv[1] ) ? (string) $argv[1] : '';
if ( ! in_array( $edition, array( 'wordpress', 'woocommerce' ), true ) ) {
	fwrite( STDERR, "Usage: php cli-edition-smoke.php wordpress|woocommerce\n" );
	exit( 2 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WP_CLI', true );
define( 'SIDRENA_EDITION', $edition );

$GLOBALS['sidrena_cli_commands'] = array();

class WP_CLI {
	public static function add_command( $name, $callable ) {
		unset( $callable );
		$GLOBALS['sidrena_cli_commands'][] = $name;
	}
	public static function success( $message ) { unset( $message ); }
	public static function error( $message ) { throw new RuntimeException( (string) $message ); }
}

function apply_filters( $hook, $value ) {
	unset( $hook );
	return $value;
}
function sanitize_key( $value ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) );
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php';
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-cli.php';

Sidrena_CLI::register();

function sidrena_cli_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

foreach ( array( 'sidrena generate', 'sidrena status', 'sidrena audit' ) as $command ) {
	sidrena_cli_assert( in_array( $command, $GLOBALS['sidrena_cli_commands'], true ), 'Missing common CLI command: ' . $command );
}

if ( 'woocommerce' === $edition ) {
	sidrena_cli_assert( in_array( 'sidrena fill', $GLOBALS['sidrena_cli_commands'], true ), 'WooCommerce edition must register sidrena fill.' );
} else {
	sidrena_cli_assert( ! in_array( 'sidrena fill', $GLOBALS['sidrena_cli_commands'], true ), 'WordPress edition must not expose Woo-only sidrena fill.' );
}

fwrite( STDOUT, "Sidrena {$edition} CLI smoke test passed.\n" );
