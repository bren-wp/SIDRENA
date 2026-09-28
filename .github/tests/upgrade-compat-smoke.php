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
define( 'SIDRENA_VERSION', '1.0.10' );

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-activator.php';

function sidrena_upgrade_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$method = new ReflectionMethod( 'Sidrena_Activator', 'needs_upgrade' );
$method->setAccessible( true );

sidrena_upgrade_assert(
	true === $method->invoke( null, Sidrena_Activator::DB_VERSION, '' ),
	'An installation without a plugin-version marker must run the compatibility upgrade path.'
);
sidrena_upgrade_assert(
	true === $method->invoke( null, Sidrena_Activator::DB_VERSION, '0.1.0' ),
	'An old Sidrena release must run the compatibility upgrade path.'
);
sidrena_upgrade_assert(
	true === $method->invoke( null, '', '1.0.10' ),
	'A missing/old schema marker must run the compatibility repair path.'
);
sidrena_upgrade_assert(
	false === $method->invoke( null, Sidrena_Activator::DB_VERSION, SIDRENA_VERSION ),
	'Current Sidrena version must not run the expensive schema upgrade path on every request.'
);

$source    = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-activator.php' );
$bootstrap = file_get_contents( dirname( __DIR__, 2 ) . '/includes/sidrena-bootstrap.php' );
sidrena_upgrade_assert( false !== strpos( $source, "self::ensure_storage();" ), 'Upgrade path must repair upload storage.' );
sidrena_upgrade_assert( false !== strpos( $source, "self::install_schema();" ), 'Upgrade path must repair missing database schema.' );
sidrena_upgrade_assert( false !== strpos( $source, "update_option( self::PLUGIN_VERSION_OPTION, SIDRENA_VERSION, false );" ), 'Upgrade path must persist the installed Sidrena version.' );
sidrena_upgrade_assert( false !== strpos( $bootstrap, "'init'," ), 'Upgrade repair must run on init, after WordPress rewrite globals are available.' );
sidrena_upgrade_assert( false !== strpos( $bootstrap, "array( 'Sidrena_Activator', 'maybe_upgrade' )" ), 'Upgrade bootstrap callback registration is missing.' );
$plugins_loaded_pos = strpos( $bootstrap, "'plugins_loaded'," );
$init_pos           = strpos( $bootstrap, "'init'," );
$upgrade_pos        = strpos( $bootstrap, "array( 'Sidrena_Activator', 'maybe_upgrade' )" );
sidrena_upgrade_assert( false !== $plugins_loaded_pos && false !== $init_pos && false !== $upgrade_pos && $plugins_loaded_pos < $init_pos && $init_pos < $upgrade_pos, 'Upgrade repair must be registered on init only after dependency-safe plugins_loaded bootstrap.' );

fwrite( STDOUT, "Sidrena cross-version upgrade compatibility smoke test passed.\n" );
