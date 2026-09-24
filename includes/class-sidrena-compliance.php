<?php
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
		$paths = Sidrena_Utils::upload_paths();
		wp_mkdir_p( $paths['archive_dir'] );
		wp_mkdir_p( $paths['snapshot_dir'] );

		foreach ( array( $paths['base_dir'], $paths['archive_dir'], $paths['snapshot_dir'] ) as $dir ) {
			$this->protect_directory( $dir );
		}

		if ( class_exists( 'Sidrena_Public' ) ) {
			Sidrena_Public::ensure_public_page();
		}

		$readiness = self::readiness();
		Sidrena_Audit::log(
			'legal_automation_watchdog',
			$readiness['ok'] ? 'success' : 'warning',
			$readiness['ok'] ? 'Sidrena tehnička automatizacija je spremna.' : 'Sidrena tehnička automatizacija zahtijeva provjeru.',
			array(
				'issues'  => $readiness['issues'],
				'profile' => $readiness['profile'],
			),
			0
		);
	}

	private function protect_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$index = trailingslashit( $dir ) . 'index.html';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '<!doctype html><meta charset="utf-8"><title>Sidrena</title>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}
}
