<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

/**
 * Immutable SIDRENA reference-date ruleset regression test.
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['sidrena_test_options'] = array(
	'sidrena_settings' => array(
		'default_ref_date' => '2099-12-31',
		'fmcg_ref_date'    => '1999-01-01',
		'fmsid_ref_date'   => '2000-01-01',
		'retention_days'   => 45,
	),
);
$GLOBALS['sidrena_test_meta']    = array();
$GLOBALS['sidrena_test_parent']  = array();

function get_option( $key, $default = false ) {
	return $GLOBALS['sidrena_test_options'][ $key ] ?? $default;
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, is_array( $args ) ? $args : array() );
}
function absint( $value ) {
	return abs( (int) $value );
}
function sanitize_text_field( $value ) {
	return trim( (string) $value );
}
function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) );
}
function wp_timezone() {
	return new DateTimeZone( 'Europe/Zagreb' );
}
function get_post_meta( $post_id, $key, $single = false ) {
	unset( $single );
	return $GLOBALS['sidrena_test_meta'][ (int) $post_id ][ $key ] ?? '';
}
function wp_get_post_parent_id( $post_id ) {
	return $GLOBALS['sidrena_test_parent'][ (int) $post_id ] ?? 0;
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php';

function sidrena_ruleset_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$settings = Sidrena_Utils::settings();
sidrena_ruleset_assert( '2026-09-10' === $settings['default_ref_date'], 'Persisted settings must never override the standard legal reference date.' );
sidrena_ruleset_assert( '2025-05-02' === $settings['fmcg_ref_date'], 'Persisted settings must never override the FMCG legal reference date.' );
sidrena_ruleset_assert( ! array_key_exists( 'default_ref_date', Sidrena_Utils::defaults() ), 'Standard legal date must not be an administrator default setting.' );
sidrena_ruleset_assert( ! array_key_exists( 'fmcg_ref_date', Sidrena_Utils::defaults() ), 'FMCG legal date must not be an administrator default setting.' );
sidrena_ruleset_assert( 30 === $settings['retention_days'], 'Persisted archive retention must not override the 30-day public archive ruleset.' );

$GLOBALS['sidrena_test_meta'][101] = array(
	'_sidrena_reference_group' => 'standard',
	'_sidrena_anchor_date'     => '2099-12-31',
);
sidrena_ruleset_assert( '2026-09-10' === Sidrena_Utils::current_reference_date( 101 ), 'Standard group must ignore stale/custom date meta.' );

$GLOBALS['sidrena_test_meta'][102] = array(
	'_sidrena_reference_group' => 'fmcg',
	'_sidrena_anchor_date'     => '2099-12-31',
);
sidrena_ruleset_assert( '2025-05-02' === Sidrena_Utils::current_reference_date( 102 ), 'FMCG group must remain locked to 02.05.2025.' );

$GLOBALS['sidrena_test_meta'][103] = array(
	'_sidrena_reference_group' => 'custom',
	'_sidrena_anchor_date'     => '2026-09-11',
);
sidrena_ruleset_assert( '2026-09-11' === Sidrena_Utils::current_reference_date( 103 ), 'A genuinely new item may use its first-listing date after 10.09.2026.' );

$GLOBALS['sidrena_test_meta'][104] = array(
	'_sidrena_reference_group' => 'custom',
	'_sidrena_anchor_date'     => '2026-09-10',
);
sidrena_ruleset_assert( '' === Sidrena_Utils::current_reference_date( 104 ), 'Custom date on the statutory reference date must be rejected.' );

$GLOBALS['sidrena_test_meta'][105] = array(
	'_sidrena_reference_group' => 'standard',
	'_sidrena_anchor_date'     => '2026-09-29',
);
sidrena_ruleset_assert( '2026-09-10' === Sidrena_Utils::current_reference_date( 105 ), 'Custom meta must not change a standard item.' );

$GLOBALS['sidrena_test_meta'][200] = array(
	'_sidrena_reference_group' => 'fmcg',
);
$GLOBALS['sidrena_test_meta'][201] = array();
$GLOBALS['sidrena_test_parent'][201] = 200;
sidrena_ruleset_assert( '2025-05-02' === Sidrena_Utils::current_reference_date( 201 ), 'Variation must inherit a locked FMCG ruleset date from its parent.' );

sidrena_ruleset_assert( '' === Sidrena_Utils::custom_reference_date( '2026-09-09' ), 'Custom first-listing date before the statutory cutoff must be rejected.' );
sidrena_ruleset_assert( '2026-09-11' === Sidrena_Utils::custom_reference_date( '2026-09-11' ), 'Custom first-listing date after the cutoff must be accepted.' );

fwrite( STDOUT, "SIDRENA immutable reference-date ruleset smoke test passed.\n" );
