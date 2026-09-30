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
define( 'SIDRENA_VERSION', '1.0.2' );

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
	true === $method->invoke( null, '', '1.0.2' ),
	'A missing/old schema marker must run the compatibility repair path.'
);
sidrena_upgrade_assert(
	true === $method->invoke( null, Sidrena_Activator::DB_VERSION, '1.0.0' ),
	'An installation on 1.0.0 must run the idempotent compatibility path when upgrading to 1.0.2.'
);
sidrena_upgrade_assert(
	true === $method->invoke( null, Sidrena_Activator::DB_VERSION, '1.0.1' ),
	'An installation on 1.0.1 must refresh the plugin version marker once when upgrading to 1.0.2.'
);
sidrena_upgrade_assert(
	true === $method->invoke( null, Sidrena_Activator::DB_VERSION, '1.0.27' ),
	'An installation carrying the former 1.0.27 public marker must run the idempotent repair path when upgrading to 1.0.2.'
);
sidrena_upgrade_assert(
	false === $method->invoke( null, Sidrena_Activator::DB_VERSION, SIDRENA_VERSION ),
	'Current Sidrena version must not run the expensive schema upgrade path on every request.'
);

$source    = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-sidrena-activator.php' );
sidrena_upgrade_assert( '0.2.0' === Sidrena_Activator::DB_VERSION, '1.0.2 must preserve the indexed history schema marker.' );
sidrena_upgrade_assert( false !== strpos( $source, 'KEY product_seq (product_id, id)' ), 'Product history must index product/id sequence lookups.' );
sidrena_upgrade_assert( false !== strpos( $source, 'KEY variation_seq (variation_id, id)' ), 'Variation history must index variation/id sequence lookups.' );
sidrena_upgrade_assert( false !== strpos( $source, 'KEY service_seq (service_id, id)' ), 'Service history must index service/id sequence lookups.' );
$bootstrap = file_get_contents( dirname( __DIR__, 2 ) . '/includes/sidrena-bootstrap.php' );
sidrena_upgrade_assert( false !== strpos( $source, "self::ensure_storage();" ), 'Upgrade path must repair upload storage.' );
sidrena_upgrade_assert( false !== strpos( $source, "self::install_schema();" ), 'Upgrade path must repair missing database schema.' );
sidrena_upgrade_assert( false !== strpos( $source, "'sidrena_legacy_legal_date_migration'" ), 'Upgrade must preserve retired administrator-entered legal dates in an audit-only migration snapshot before removing them from active settings.' );
sidrena_upgrade_assert( false !== strpos( $source, 'Legacy administrator-entered legal dates preserved for audit only; never used as the active SIDRENA legal ruleset.' ), 'Legacy date snapshot must document that it is not a runtime legal source.' );
sidrena_upgrade_assert( false !== strpos( $source, "unset( \$settings['default_ref_date'], \$settings['fmcg_ref_date'], \$settings['fmsid_ref_date'] );" ), 'Retired editable legal dates must still be removed from active runtime settings.' );
sidrena_upgrade_assert( false !== strpos( $source, "update_option( self::PLUGIN_VERSION_OPTION, SIDRENA_VERSION, false );" ), 'Upgrade path must persist the installed Sidrena version.' );
sidrena_upgrade_assert( false !== strpos( $bootstrap, "'init'," ), 'Upgrade repair must run on init, after WordPress rewrite globals are available.' );
sidrena_upgrade_assert( false !== strpos( $bootstrap, "array( 'Sidrena_Activator', 'maybe_upgrade' )" ), 'Upgrade bootstrap callback registration is missing.' );
$plugins_loaded_pos = strpos( $bootstrap, "'plugins_loaded'," );
$init_pos           = strpos( $bootstrap, "'init'," );
$upgrade_pos        = strpos( $bootstrap, "array( 'Sidrena_Activator', 'maybe_upgrade' )" );
sidrena_upgrade_assert( false !== $plugins_loaded_pos && false !== $init_pos && false !== $upgrade_pos && $plugins_loaded_pos < $init_pos && $init_pos < $upgrade_pos, 'Upgrade repair must be registered on init only after dependency-safe plugins_loaded bootstrap.' );

fwrite( STDOUT, "Sidrena cross-version upgrade compatibility smoke test passed.\n" );
