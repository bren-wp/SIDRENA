<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
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
				'label' => 'Ministarstvo gospodarstva, službena pojašnjenja za primjenu dodatne cijene i objavu cjenika od 1. listopada (objavljeno 22.09.2026.)',
				'date'  => '2026-09-22',
				'note'  => 'Operativna pojašnjenja za dodatnu cijenu i digitalnu objavu cjenika.',
			),
			'nn_59_2026_base_price_future' => array(
				'label' => 'NN 59/2026, Zakon o izmjenama i dopunama Zakona o zaštiti potrošača — bazna cijena',
				'date'  => '2026-06-09',
				'note'  => 'Izmijenjeni članak 7. stavci 1. do 9. počinju se primjenjivati 17.11.2026.; bazna cijena ostaje odvojena od dodatne/sidrene cijene, a konkretan dan, proizvodi i način isticanja ovise o provedbenom pravilniku.',
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
			'rest_index_enabled'     => 'yes' === $settings['enable_rest_index'],
			'manifest_enabled'       => 'yes' === $settings['publish_manifest'],
			'csv_enabled'            => 'yes' === $settings['generate_csv'],
			'xml_enabled'            => 'yes' === $settings['generate_xml'],
			'strict_publication'     => 'yes' === $settings['strict_publication'],
			'failure_notifications'  => 'yes' === $settings['failure_notifications'],
			'display_anchor'         => 'yes' === $settings['display_anchor'],
			'display_lowest_30'      => 'yes' === $settings['display_lowest_30'],
			'track_price_history'    => 'yes' === $settings['track_price_history'],
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
		if ( ! $profile['csv_enabled'] || ! $profile['xml_enabled'] ) {
			$issues[] = 'Sidrena automatizacija treba održavati i CSV i XML izlaz kako bi korisnik imao oba podržana strojno čitljiva formata.';
		}
		if ( ! $profile['public_html_enabled'] ) {
			$issues[] = 'Javna stranica cjenika mora ostati uključena kako bi objavljene datoteke i arhiva bile lako dostupne s mrežne stranice.';
		}
		if ( ! $profile['rest_index_enabled'] || ! $profile['manifest_enabled'] ) {
			$issues[] = 'REST indeks i JSON manifest trebaju ostati uključeni za automatizirani dohvat i otkrivanje aktualnih datoteka.';
		}
		if ( ! $profile['strict_publication'] ) {
			$issues[] = 'Strict publication način treba biti uključen kako neuspjeli novi fajl ne bi zamijenio zadnju ispravnu objavu.';
		}
		if ( ! $profile['display_anchor'] ) {
			$issues[] = 'Prikaz dodatne/sidrene cijene treba ostati uključen na javnim prikazima.';
		}
		if ( ! $profile['display_lowest_30'] ) {
			$issues[] = 'Prikaz 30-dnevne referentne cijene treba ostati uključen za posebne oblike prodaje.';
		}
		if ( ! $profile['track_price_history'] ) {
			$issues[] = 'Povijest cijena treba ostati uključena radi provjerljive 30-dnevne reference.';
		}
		if ( $profile['archive_retention_days'] < 30 ) {
			$issues[] = 'Javna arhiva mora imati najmanje 30 dana čuvanja.';
		}
		if ( ! $profile['daily_generation'] ) {
			$issues[] = 'Automatsko generiranje nije zakazano; provjerite dnevnu objavu proizvoda i regeneriranje cjenika nakon promjena usluga.';
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

		foreach ( array( 'default_ref_date' => '2026-09-10', 'fmcg_ref_date' => '2025-05-02' ) as $key => $value ) {
			if ( ! isset( $settings[ $key ] ) || $value !== $settings[ $key ] ) {
				$settings[ $key ] = $value;
				$repairs[]        = 'settings:' . $key;
			}
		}

		$before_normalize = $settings;
		$settings         = Sidrena_Legal_Automation::normalize_settings( $settings );
		foreach ( array( 'generation_time', 'retention_days', 'generate_csv', 'generate_xml', 'enable_rest_index', 'publish_manifest', 'enable_public_html', 'strict_publication', 'failure_notifications', 'display_anchor', 'display_lowest_30', 'track_price_history' ) as $key ) {
			if ( (string) ( $before_normalize[ $key ] ?? '' ) !== (string) ( $settings[ $key ] ?? '' ) ) {
				$repairs[] = 'settings:' . $key;
			}
		}

		$repairs = array_values( array_unique( $repairs ) );
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
		$written = file_put_contents( $index, '<!doctype html><meta charset="utf-8"><title>Sidrena</title>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return false !== $written;
	}
}
