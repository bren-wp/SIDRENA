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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralized technical compliance checks and safe automation repairs.
 *
 * This class intentionally reports technical readiness only. It does not make
 * a legal conclusion about the merchant's concrete business obligations.
 */
final class Sidrena_Compliance {
	const LAST_STATUS_OPTION = 'sidrena_compliance_last_status';

	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'sidrena_publication_watch', array( $this, 'watchdog' ), 5 );
		add_action( 'sidrena_daily_generation', array( $this, 'watchdog' ), 5 );
	}

	public static function legal_sources() {
		return array(
			'nn_101_2026_anchor_price' => array(
				'label' => 'NN 101/2026, Odluka o isticanju dodatne cijene kao mjera izravne kontrole cijena',
				'date'  => '2026-09-11',
				'note'  => 'Dodatna odnosno sidrena cijena za proizvode i usluge s referentnim datumom 10.09.2026.; za ranije obuhvaćene FMCG kategorije zadržava se 02.05.2025.',
			),
			'nn_101_2026_public_pricelist' => array(
				'label' => 'NN 101/2026, Odluka o objavi cjenika proizvoda i usluga kao mjera izravne kontrole cijena',
				'date'  => '2026-09-11',
				'note'  => 'Objava važećih cjenika proizvoda i usluga na mrežnim stranicama trgovca odnosno pružatelja usluge.',
			),
			'nn_105_2026_retail_unit_price' => array(
				'label' => 'NN 105/2026, Pravilnik o načinu isticanja maloprodajne cijene i cijene za jedinicu mjere proizvoda',
				'date'  => '2026-09-18',
				'note'  => 'Maloprodajna cijena i cijena za jedinicu mjere moraju biti istaknute jasno, vidljivo, čitljivo i lako uočljivo.',
			),
			'mingo_2026_09_22_clarifications' => array(
				'label' => 'Ministarstvo gospodarstva, pojašnjenja za primjenu dodatne cijene i objavu cjenika od 1. listopada',
				'date'  => '2026-09-22',
				'note'  => 'Operativna pojašnjenja za dodatnu cijenu i digitalnu objavu cjenika.',
			),
		);
	}

	public static function automation_profile() {
		$settings = Sidrena_Utils::settings();
		return array(
			'default_reference_date' => $settings['default_ref_date'],
			'fmcg_reference_date'    => $settings['fmcg_ref_date'],
			'generation_time'        => $settings['generation_time'],
			'archive_retention_days' => max( 30, absint( $settings['retention_days'] ) ),
			'public_html_enabled'    => 'yes' === $settings['enable_public_html'],
			'csv_enabled'            => 'yes' === $settings['generate_csv'],
			'xml_enabled'            => 'yes' === $settings['generate_xml'],
			'strict_publication'     => 'yes' === $settings['strict_publication'],
			'failure_notifications'  => 'yes' === $settings['failure_notifications'],
			'publication_watch'      => (bool) wp_next_scheduled( 'sidrena_publication_watch' ),
			'daily_generation'       => (bool) wp_next_scheduled( 'sidrena_daily_generation' ),
		);
	}

	public static function readiness() {
		$profile = self::automation_profile();
		$issues  = array();

		if ( '2026-09-10' !== $profile['default_reference_date'] ) {
			$issues[] = 'Zadani referentni datum za opće proizvode/usluge nije 10.09.2026.';
		}
		if ( '2025-05-02' !== $profile['fmcg_reference_date'] ) {
			$issues[] = 'FMCG referentni datum nije 02.05.2025.';
		}
		if ( ! $profile['csv_enabled'] || ! $profile['xml_enabled'] || ! $profile['public_html_enabled'] ) {
			$issues[] = 'CSV, XML i javni HTML cjenik trebaju biti uključeni za potpunu digitalnu objavu.';
		}
		if ( ! $profile['strict_publication'] ) {
			$issues[] = 'Strict publication način treba biti uključen kako neuspjeli novi fajl ne bi zamijenio zadnju ispravnu objavu.';
		}
		if ( $profile['archive_retention_days'] < 30 ) {
			$issues[] = 'Javna arhiva mora imati najmanje 30 dana čuvanja.';
		}
		if ( ! $profile['daily_generation'] ) {
			$issues[] = 'Dnevno automatsko generiranje nije zakazano.';
		}
		if ( ! $profile['publication_watch'] ) {
			$issues[] = 'Publication watchdog nije zakazan.';
		}

		return array(
			'ok'      => empty( $issues ),
			'issues'  => $issues,
			'profile' => $profile,
			'sources' => self::legal_sources(),
		);
	}

	public function watchdog() {
		$repairs = array();
		$repairs = array_merge( $repairs, $this->repair_settings() );
		$repairs = array_merge( $repairs, $this->repair_schedules() );
		$repairs = array_merge( $repairs, $this->repair_public_surface() );

		$readiness = self::readiness();
		$this->log_watchdog_result( $readiness, $repairs );
	}

	private function repair_settings() {
		$settings = get_option( 'sidrena_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		$settings = wp_parse_args( $settings, Sidrena_Utils::defaults() );
		$repairs  = array();

		$required = array(
			'default_ref_date'      => '2026-09-10',
			'fmcg_ref_date'         => '2025-05-02',
			'generate_csv'          => 'yes',
			'generate_xml'          => 'yes',
			'enable_public_html'    => 'yes',
			'strict_publication'    => 'yes',
			'failure_notifications' => 'yes',
		);

		foreach ( $required as $key => $value ) {
			if ( ! isset( $settings[ $key ] ) || $value !== $settings[ $key ] ) {
				$settings[ $key ] = $value;
				$repairs[]        = 'settings:' . $key;
			}
		}

		$retention = max( 30, absint( $settings['retention_days'] ) );
		if ( $retention !== absint( $settings['retention_days'] ) ) {
			$settings['retention_days'] = $retention;
			$repairs[]                  = 'settings:retention_days';
		}

		$time = isset( $settings['generation_time'] ) ? (string) $settings['generation_time'] : '';
		if ( ! preg_match( '/^(0[0-7]):[0-5][0-9]$/', $time ) ) {
			$settings['generation_time'] = '06:30';
			$repairs[]                   = 'settings:generation_time';
		}

		if ( $repairs ) {
			update_option( 'sidrena_settings', $settings, false );
		}

		return $repairs;
	}

	private function repair_schedules() {
		$repairs = array();

		if ( ! wp_next_scheduled( 'sidrena_daily_generation' ) ) {
			$timestamp = is_callable( array( 'Sidrena_Utils', 'schedule_timestamp' ) ) ? Sidrena_Utils::schedule_timestamp() : time() + HOUR_IN_SECONDS;
			wp_schedule_event( $timestamp, 'daily', 'sidrena_daily_generation' );
			$repairs[] = 'schedule:sidrena_daily_generation';
		}

		if ( ! wp_next_scheduled( 'sidrena_publication_watch' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'sidrena_publication_watch' );
			$repairs[] = 'schedule:sidrena_publication_watch';
		}

		return $repairs;
	}

	private function repair_public_surface() {
		$repairs = array();
		$paths   = Sidrena_Utils::upload_paths();

		foreach ( array( 'archive_dir', 'snapshot_dir' ) as $path_key ) {
			if ( ! is_dir( $paths[ $path_key ] ) ) {
				wp_mkdir_p( $paths[ $path_key ] );
				$repairs[] = 'directory:' . $path_key;
			}
		}

		foreach ( array( $paths['base_dir'], $paths['archive_dir'], $paths['snapshot_dir'] ) as $dir ) {
			if ( $this->protect_directory( $dir ) ) {
				$repairs[] = 'directory:index';
			}
		}

		if ( class_exists( 'Sidrena_Public' ) ) {
			Sidrena_Public::ensure_public_page();
			$repairs[] = 'public_page:ensure';
		}

		return $repairs;
	}

	private function log_watchdog_result( $readiness, $repairs ) {
		$hash = md5( wp_json_encode( array( $readiness['issues'], $readiness['profile'], $repairs ) ) );
		$last = get_option( self::LAST_STATUS_OPTION, array() );
		$last = is_array( $last ) ? $last : array();
		$age  = isset( $last['checked_at'] ) ? time() - absint( $last['checked_at'] ) : DAY_IN_SECONDS + 1;

		update_option(
			self::LAST_STATUS_OPTION,
			array(
				'hash'       => $hash,
				'ok'         => (bool) $readiness['ok'],
				'checked_at' => time(),
			),
			false
		);

		if ( isset( $last['hash'] ) && $hash === $last['hash'] && $age < DAY_IN_SECONDS ) {
			return;
		}

		Sidrena_Audit::log(
			'legal_automation_watchdog',
			$readiness['ok'] ? 'success' : 'warning',
			$readiness['ok'] ? 'Sidrena tehnička automatizacija je spremna.' : 'Sidrena tehnička automatizacija zahtijeva provjeru.',
			array(
				'issues'  => $readiness['issues'],
				'profile' => $readiness['profile'],
				'repairs' => array_values( array_unique( $repairs ) ),
			),
			0
		);
	}

	private function protect_directory( $dir ) {
		if ( ! is_dir( $dir ) || ( function_exists( 'wp_is_writable' ) && ! wp_is_writable( $dir ) ) ) {
			return false;
		}
		$index = trailingslashit( $dir ) . 'index.html';
		if ( file_exists( $index ) ) {
			return false;
		}
		file_put_contents( $index, '<!doctype html><meta charset="utf-8"><title>Sidrena</title>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return true;
	}
}
