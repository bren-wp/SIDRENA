<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

define( 'ABSPATH', __DIR__ . '/' );

$root = dirname( __DIR__, 2 );
$admin_source = file_get_contents( $root . '/includes/class-sidrena-admin.php' );

$GLOBALS['sidrena_health_settings'] = array(
	'automation_mode' => 'external',
	'generation_time' => '06:30',
	'retention_days'  => 90,
	'business_mode'   => 'mixed',
);
$GLOBALS['sidrena_health_schedules'] = array(
	'sidrena_daily_generation' => false,
	'sidrena_publication_watch' => 1800000000,
);
$GLOBALS['sidrena_health_dir'] = sys_get_temp_dir() . '/sidrena-health-' . uniqid( '', true );
mkdir( $GLOBALS['sidrena_health_dir'] . '/archive', 0777, true );
mkdir( $GLOBALS['sidrena_health_dir'] . '/current', 0777, true );

function __( $text, $domain = null ) {
	unset( $domain );
	return $text;
}
function esc_html__( $text, $domain = null ) {
	unset( $domain );
	return $text;
}
function esc_html( $text ) {
	return (string) $text;
}
function sanitize_key( $value ) {
	return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) );
}
function absint( $value ) {
	return abs( (int) $value );
}
function wp_next_scheduled( $hook ) {
	return $GLOBALS['sidrena_health_schedules'][ $hook ] ?? false;
}
function wp_date( $format, $timestamp = null ) {
	return date( $format, $timestamp ?: time() );
}
function wp_is_writable( $path ) {
	return is_writable( $path );
}
function wp_count_posts( $post_type ) {
	unset( $post_type );
	$result = new stdClass();
	$result->publish = 0;
	return $result;
}
function get_option( $key, $default = false ) {
	unset( $key );
	return $default;
}

final class Sidrena_Utils {
	public static function settings() {
		return $GLOBALS['sidrena_health_settings'];
	}
	public static function upload_paths() {
		return array(
			'archive_dir' => $GLOBALS['sidrena_health_dir'] . '/archive/',
			'current_dir' => $GLOBALS['sidrena_health_dir'] . '/current/',
		);
	}
	public static function is_wordpress_edition() {
		return true;
	}
	public static function runtime_mode_label() {
		return 'SIDRENA — WordPress izdanje';
	}
}

require dirname( __DIR__, 2 ) . '/includes/class-sidrena-site-health.php';

function sidrena_health_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

$health = Sidrena_Site_Health::instance();

$external = $health->test_schedule();
sidrena_health_assert( 'recommended' === $external['status'], 'External server cron mode must not be reported as a missing internal-cron critical error.' );
sidrena_health_assert( false !== strpos( $external['description'], 'wp sidrena publish' ), 'External scheduler Site Health guidance must document the dedicated SIDRENA WP-CLI command.' );

$GLOBALS['sidrena_health_schedules']['sidrena_publication_watch'] = false;
$missing_watchdog = $health->test_schedule();
sidrena_health_assert( 'critical' === $missing_watchdog['status'], 'Missing SIDRENA publication watchdog must remain critical in external mode.' );

$GLOBALS['sidrena_health_settings']['automation_mode'] = 'wp_cron';
$GLOBALS['sidrena_health_schedules']['sidrena_publication_watch'] = 1800000000;
$GLOBALS['sidrena_health_schedules']['sidrena_daily_generation'] = false;
$missing_daily = $health->test_schedule();
sidrena_health_assert( 'critical' === $missing_daily['status'], 'Internal WP-Cron mode must require the daily SIDRENA publication event.' );

$GLOBALS['sidrena_health_schedules']['sidrena_daily_generation'] = 1800003600;
$internal = $health->test_schedule();
sidrena_health_assert( 'good' === $internal['status'], 'Healthy internal scheduler and watchdog must report a good Site Health status.' );

sidrena_health_assert(
	false !== strpos( $admin_source, "'external' === $automation_mode || (bool) wp_next_scheduled( 'sidrena_daily_generation' )" ),
	'Admin dashboard health must treat external scheduler mode as intentionally having no internal daily WP-Cron event.'
);
sidrena_health_assert(
	false !== strpos( $admin_source, '$daily_schedule_ok = \'external\' === $automation_mode || (bool) wp_next_scheduled' ),
	'Admin audit issue count must not flag external scheduler mode merely because internal daily WP-Cron is absent.'
);

sidrena_health_assert(
	false !== strpos( $admin_source, "$schedule_warning = ! $external_scheduler && ( $wp_cron_disabled || ! $next_cron );" )
	&& false !== strpos( $admin_source, "elseif ( $external_scheduler )" )
	&& false !== strpos( $admin_source, 'Interni dnevni WP-Cron namjerno nije zakazan.' ),
	'Price-list screen must show external scheduler mode as intentional instead of a missing internal-cron warning.'
);

$archive = $health->test_archive();
sidrena_health_assert( 'good' === $archive['status'], 'Writable current/archive directories must pass Site Health.' );
sidrena_health_assert( false !== strpos( $archive['description'], '90' ), 'Site Health archive status must report the configured retention above the 30-day minimum.' );

@rmdir( $GLOBALS['sidrena_health_dir'] . '/archive' );
@rmdir( $GLOBALS['sidrena_health_dir'] . '/current' );
@rmdir( $GLOBALS['sidrena_health_dir'] );

fwrite( STDOUT, "Sidrena Site Health scheduler smoke test passed.\n" );
