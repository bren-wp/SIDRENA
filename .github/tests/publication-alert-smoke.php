<?php
define( 'ABSPATH', __DIR__ . '/' );
define( 'SIDRENA_VERSION', '0.1.0' );
define( 'SIDRENA_EDITION', 'wordpress' );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['sidrena_options'] = array(
	'sidrena_settings' => array(
		'failure_notifications' => 'yes',
		'failure_email'         => 'alerts@example.test',
		'business_email'        => 'business@example.test',
	),
	'admin_email' => 'admin@example.test',
);
$GLOBALS['sidrena_transients'] = array();
$GLOBALS['sidrena_mails'] = array();

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['sidrena_options'] ) ? $GLOBALS['sidrena_options'][ $key ] : $default;
}
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function sanitize_email( $value ) { return filter_var( (string) $value, FILTER_VALIDATE_EMAIL ) ? (string) $value : ''; }
function is_email( $value ) { return false !== filter_var( (string) $value, FILTER_VALIDATE_EMAIL ); }
function get_transient( $key ) { return $GLOBALS['sidrena_transients'][ $key ] ?? false; }
function set_transient( $key, $value, $expiration ) { unset( $expiration ); $GLOBALS['sidrena_transients'][ $key ] = $value; return true; }
function get_bloginfo( $field ) { unset( $field ); return 'Sidrena Test'; }
function wp_specialchars_decode( $value, $flags = ENT_QUOTES ) { return html_entity_decode( (string) $value, $flags ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function home_url( $path = '/' ) { return 'https://example.test' . $path; }
function wp_date( $format, $timestamp = null ) { return gmdate( $format, $timestamp ?: 1760000000 ); }
function __( $text, $domain = null ) { unset( $domain ); return $text; }
function wp_mail( $to, $subject, $message ) {
	$GLOBALS['sidrena_mails'][] = compact( 'to', 'subject', 'message' );
	return true;
}

class Sidrena_Audit {
	public static function log( $type, $status, $message, $context = array() ) {
		unset( $type, $status, $message, $context );
	}
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-utils.php';
require dirname( __DIR__, 2 ) . '/includes/class-sidrena-pricelist.php';

function sidrena_alert_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$instance = Sidrena_Pricelist::instance();
$method   = new ReflectionMethod( 'Sidrena_Pricelist', 'maybe_send_publication_alert' );
$method->setAccessible( true );

$sent = $method->invoke( $instance, 'generation', 'Generiranje nije prošlo.', array( 'Nedostaje sidrena cijena.' ) );
sidrena_alert_assert( true === $sent, 'First publication alert was not sent.' );
sidrena_alert_assert( 1 === count( $GLOBALS['sidrena_mails'] ), 'Exactly one alert email should be sent.' );
sidrena_alert_assert( 'alerts@example.test' === $GLOBALS['sidrena_mails'][0]['to'], 'Configured failure email was not used.' );
sidrena_alert_assert( false !== strpos( $GLOBALS['sidrena_mails'][0]['message'], 'Nedostaje sidrena cijena.' ), 'Alert details missing from message.' );

$sent_again = $method->invoke( $instance, 'generation', 'Ponovni pokušaj.', array() );
sidrena_alert_assert( false === $sent_again, 'Throttled alert unexpectedly sent twice.' );
sidrena_alert_assert( 1 === count( $GLOBALS['sidrena_mails'] ), 'Throttle did not prevent a duplicate email.' );

$GLOBALS['sidrena_options']['sidrena_settings']['failure_email'] = 'invalid';
$GLOBALS['sidrena_transients'] = array();
$late = $method->invoke( $instance, 'late', 'Današnja objava kasni.', array() );
sidrena_alert_assert( true === $late, 'Fallback alert was not sent.' );
sidrena_alert_assert( 'business@example.test' === $GLOBALS['sidrena_mails'][1]['to'], 'Business email fallback was not used.' );

$GLOBALS['sidrena_options']['sidrena_settings']['failure_notifications'] = 'no';
$GLOBALS['sidrena_transients'] = array();
$disabled = $method->invoke( $instance, 'late', 'Ne šalji.', array() );
sidrena_alert_assert( false === $disabled, 'Disabled notifications still sent an email.' );
sidrena_alert_assert( 2 === count( $GLOBALS['sidrena_mails'] ), 'Disabled notifications changed mail count.' );

fwrite( STDOUT, "Sidrena publication alert smoke test passed.\n" );
