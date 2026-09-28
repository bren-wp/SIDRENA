<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sidrena_Admin {
	private static $instance;
	private $main_assets_enqueued = false;
	private $editor_assets_enqueued = false;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		// Defensive second chance: some admin routers/plugins alter the hook suffix.
		// admin_print_styles runs before WordPress prints the style queue, so the
		// Sidrena runtime CSS can still be enqueued without inline CSS.
		add_action( 'admin_print_styles', array( $this, 'ensure_assets' ), 1 );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
		add_action( 'admin_post_sidrena_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_sidrena_save_locations', array( $this, 'save_locations' ) );
		add_action( 'admin_post_sidrena_generate', array( $this, 'generate' ) );
		add_action( 'admin_post_sidrena_export_archive_index', array( $this, 'export_archive_index' ) );
		add_action( 'admin_post_sidrena_create_public_page', array( $this, 'create_public_page' ) );
		add_action( 'admin_post_sidrena_check_public_access', array( $this, 'check_public_access' ) );

		if ( Sidrena_Utils::is_woocommerce_active() ) {
			add_action( 'admin_post_sidrena_import_anchor', array( $this, 'import_anchor' ) );
			add_action( 'admin_post_sidrena_import_location_data', array( $this, 'import_location_data' ) );
			add_action( 'admin_post_sidrena_export_missing', array( $this, 'export_missing' ) );
			add_action( 'admin_post_sidrena_export_location_template', array( $this, 'export_location_template' ) );
		}

		add_filter( 'plugin_action_links_' . plugin_basename( SIDRENA_FILE ), array( $this, 'action_links' ) );
	}

	public function assets( $hook = '' ) {
		$screen         = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_plugin_page = $this->is_sidrena_admin_screen( $hook, $screen );
		$is_service     = $screen && 'sidrena_service' === $screen->post_type;
		$is_product     = Sidrena_Utils::is_woocommerce_edition() && $screen && 'product' === $screen->post_type;

		if ( ! $is_plugin_page && ! $is_service && ! $is_product ) {
			return;
		}

		if ( $is_plugin_page ) {
			if ( $this->main_assets_enqueued ) {
				return;
			}
			$this->main_assets_enqueued = true;

			$brand_file = SIDRENA_DIR . 'admin/css/brand.css';
			$admin_file = SIDRENA_DIR . 'admin/js/admin.js';
			$brand_ver  = is_file( $brand_file ) ? SIDRENA_VERSION . '-' . filemtime( $brand_file ) : SIDRENA_VERSION;
			$admin_ver  = is_file( $admin_file ) ? SIDRENA_VERSION . '-' . filemtime( $admin_file ) : SIDRENA_VERSION;

			wp_enqueue_style( 'dashicons' );
			wp_enqueue_style( 'sidrena-brand', plugins_url( 'admin/css/brand.css', SIDRENA_FILE ), array(), $brand_ver );
			wp_enqueue_script( 'sidrena-admin', plugins_url( 'admin/js/admin.js', SIDRENA_FILE ), array(), $admin_ver, true );
			wp_localize_script(
				'sidrena-admin',
				'SidrenaAdmin',
				array(
					'removeLocation'       => __( 'Ukloniti ovu lokaciju iz konfiguracije?', 'sidrena' ),
					'keepOneLocation'      => __( 'Mora ostati barem jedna lokacija. Možete je isključiti ako je trenutačno ne želite objavljivati.', 'sidrena' ),
					'removeUnsavedProduct' => __( 'Ukloniti ovaj nespremljeni proizvod?', 'sidrena' ),
					'deleteProduct'        => __( 'Označiti ovaj proizvod za brisanje nakon spremanja?', 'sidrena' ),
					'savingForm'           => __( 'Spremanje…', 'sidrena' ),
					'invalidField'          => __( 'Provjerite označeno polje i pokušajte ponovno.', 'sidrena' ),
					'fileTooLarge'          => __( 'Datoteka je prevelika. Najveća dopuštena veličina je 5 MB.', 'sidrena' ),
					'invalidFileType'       => __( 'Odaberite podržanu CSV ili XML datoteku.', 'sidrena' ),
					'newLocation'           => __( 'Nova lokacija', 'sidrena' ),
					'emptyLocationAddress'  => __( 'Adresa nije upisana', 'sidrena' ),
					'locationAdded'         => __( 'Nova lokacija je dodana. Unesite podatke i spremite promjene.', 'sidrena' ),
					'locationRemoved'       => __( 'Lokacija je uklonjena iz obrasca. Spremite promjene za potvrdu.', 'sidrena' ),
					'wooLocationFilled'     => __( 'Adresa web trgovine unesena je u praznu webshop lokaciju. Pregledajte podatak i spremite lokacije.', 'sidrena' ),
					'wooLocationNoTarget'   => __( 'Nema prazne webshop adrese za popunjavanje. Postojeći podaci nisu promijenjeni.', 'sidrena' ),
					'safeFillChanged'       =>
						/* translators: %s: number of empty catalog fields populated from trusted WooCommerce data. */
						__( 'Popunjeno je %s praznih polja iz pouzdanih izvora trgovine. Pregledajte podatke i spremite promjene.', 'sidrena' ),
					'safeFillEmpty'         => __( 'Nema praznih polja s pouzdanim izvorom trgovine. Ostala polja ostaju nepromijenjena.', 'sidrena' ),
					'removeLocationLabel'   =>
						/* translators: %s: location code or fallback title. */
						__( 'Ukloni lokaciju %s', 'sidrena' ),
				)
			);
			return;
		}

		if ( $this->editor_assets_enqueued ) {
			return;
		}
		$this->editor_assets_enqueued = true;

		$editor_file = SIDRENA_DIR . 'admin/css/admin.css';
		$editor_ver  = is_file( $editor_file ) ? SIDRENA_VERSION . '-' . filemtime( $editor_file ) : SIDRENA_VERSION;
		wp_enqueue_style( 'sidrena-admin-editor', plugins_url( 'admin/css/admin.css', SIDRENA_FILE ), array(), $editor_ver );
	}

	public function ensure_assets() {
		$this->assets( '' );
	}

	private function is_sidrena_admin_screen( $hook = '', $screen = null ) {
		// Post editors use the compact editor stylesheet, not the full SIDRENA app shell.
		// In particular, the sidrena_service screen id itself contains "sidrena" and
		// must not be mistaken for a top-level SIDRENA application page.
		$post_type = is_object( $screen ) && isset( $screen->post_type ) ? (string) $screen->post_type : '';
		if ( 'sidrena_service' === $post_type || ( Sidrena_Utils::is_woocommerce_edition() && 'product' === $post_type ) ) {
			return false;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'sidrena' === $page || 0 === strpos( $page, 'sidrena-' ) ) {
			return true;
		}

		$hook = (string) $hook;
		if ( '' !== $hook && false !== strpos( $hook, 'sidrena' ) ) {
			return true;
		}

		$screen_id = is_object( $screen ) && isset( $screen->id ) ? (string) $screen->id : '';
		return '' !== $screen_id && false !== strpos( $screen_id, 'sidrena' );
	}

	public function admin_body_class( $classes ) {
		$screen     = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_service = $screen && 'sidrena_service' === $screen->post_type;
		$is_product = Sidrena_Utils::is_woocommerce_edition() && $screen && 'product' === $screen->post_type;

		if ( ! $this->is_sidrena_admin_screen( '', $screen ) && ! $is_service && ! $is_product ) {
			return $classes;
		}

		$edition = Sidrena_Utils::is_woocommerce_edition() ? 'woocommerce' : 'wordpress';
		return trim( $classes . ' sidrena-admin-screen sidrena-edition-body-' . $edition );
	}

	public function action_links( $links ) {
		if ( ! Sidrena_Utils::current_user_can_manage() ) {
			return $links;
		}

		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=sidrena' ) ) . '">' . esc_html__( 'Otvori Sidrenu', 'sidrena' ) . '</a>'
		);
		return $links;
	}

	public function page() {
		if ( ! Sidrena_Utils::current_user_can_manage() ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'sidrena'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page_map = array(
			'sidrena'           => 'dashboard',
			'sidrena-catalog'   => 'catalog',
			'sidrena-files'     => 'files',
			'sidrena-locations' => 'locations',
			'sidrena-settings'  => 'settings',
			'sidrena-support'   => 'support',
		);
		$tab = isset( $page_map[ $page ] ) ? $page_map[ $page ] : 'dashboard';

		$section_map = array(
			'sidrena'         => array( 'dashboard', 'compliance' ),
			'sidrena-files'   => array( 'files', 'archive' ),
			'sidrena-support' => array( 'support', 'help', 'rules', 'tools', 'log', 'about' ),
		);
		if ( isset( $_GET['sidrena_section'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$section = sanitize_key( wp_unslash( $_GET['sidrena_section'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $section_map[ $page ] ) && in_array( $section, $section_map[ $page ], true ) ) {
				$tab = $section;
			}
		}

		$is_woo       = Sidrena_Utils::is_woocommerce_edition();
		$edition_slug = $is_woo ? 'woocommerce' : 'wordpress';
		$edition_name = $is_woo ? __( 'Web trgovina', 'sidrena' ) : __( 'Samostalni katalog', 'sidrena' );
		$brand_logo   = SIDRENA_URL . 'assets/images/logo-horizontal-light.svg';
		$official_url = 'https://brendigo.com/sidrene-cijene/';
		?>
		<div class="wrap sidrena-app sidrena-edition-<?php echo esc_attr( $edition_slug ); ?>">
			<header class="sidrena-brandbar">
				<div class="sidrena-brandbar__identity">
					<img class="sidrena-brandbar__logo" src="<?php echo esc_url( $brand_logo ); ?>" width="620" height="120" loading="eager" decoding="async" alt="<?php esc_attr_e( 'SIDRENA — sidrene cijene i digitalni cjenici', 'sidrena' ); ?>">
					<span class="sidrena-brandbar__edition"><span class="dashicons <?php echo $is_woo ? 'dashicons-cart' : 'dashicons-wordpress'; ?>"></span><?php echo esc_html( $edition_name ); ?></span>
				</div>
				<div class="sidrena-brandbar__copy">
					<strong><?php esc_html_e( 'Vaš pouzdan signal u svijetu propisa o cijenama.', 'sidrena' ); ?></strong>
					<span><?php esc_html_e( 'Upravljajte stvarnim cijenama, javnim cjenicima, lokacijama i arhivom iz jednog preglednog sučelja.', 'sidrena' ); ?></span>
				</div>
				<div class="sidrena-brandbar__actions">
					<a href="<?php echo esc_url( $official_url ); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-external"></span><?php esc_html_e( 'Web stranica', 'sidrena' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-support' ) ); ?>"><span class="dashicons dashicons-editor-help"></span><?php esc_html_e( 'Pomoć', 'sidrena' ); ?></a>
				</div>
			</header>

			<div class="sidrena-contextbar">
				<div class="sidrena-contextbar__left">
					<span class="sid-context-chip"><?php esc_html_e( 'Produkcijsko okruženje', 'sidrena' ); ?></span>
				</div>
				<a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-settings' ) ); ?>"><?php esc_html_e( 'Uredi postavke arhive', 'sidrena' ); ?></a>
			</section>
			<?php $this->support_card(); ?>
		</div>

		<div class="sid-dashboard-metrics sid-dashboard-metrics--archive">
			<?php $this->dashboard_metric( __( 'Datoteke u arhivi', 'sidrena' ), $stats['files'], 'dashicons-database', __( 'CSV/XML objave', 'sidrena' ), 'blue' ); ?>
			<?php $this->dashboard_metric( __( 'Dani s objavama', 'sidrena' ), $stats['distinct_days'], 'dashicons-calendar-alt', __( 'evidentirani dani', 'sidrena' ), 'teal' ); ?>
			<?php /* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */ ?>
			<?php $this->dashboard_metric( __( 'Politika čuvanja', 'sidrena' ), '30 d', 'dashicons-lock', __( 'zaključano pravilima SIDRENA-e', 'sidrena' ), 'ok' ); ?>
			<?php $this->dashboard_metric( __( 'Integritet', 'sidrena' ), $integrity['ok'] ? __( 'U redu', 'sidrena' ) : __( 'Provjera', 'sidrena' ), $integrity['ok'] ? 'dashicons-yes-alt' : 'dashicons-warning', $integrity['ok'] ? __( 'datoteke i SHA-256', 'sidrena' ) : __( 'potrebna tehnička provjera', 'sidrena' ), $integrity['ok'] ? 'ok' : 'warn' ); ?>
		</div>
		<?php $this->archive_timeline( $archive ); ?>
		<section class="sid-card">
			<div class="sid-section-head"><div><h2><?php esc_html_e( 'Arhivirane objave', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Najnovije su prikazane prve. Datoteka se razmatra za uklanjanje tek nakon isteka vlastitog roka čuvanja, uz zaštitu trenutačno važeće objave.', 'sidrena' ); ?></p></div></div>
			<?php $this->files_table( $archive, true ); ?>
		</section>
		<?php
	}

	private function archive_timeline( $archive ) {
		$days = array();
		foreach ( $archive as $entry ) {
			$ts = absint( $entry['generated_ts'] ?? 0 );
			if ( ! $ts ) {
				continue;
			}
			$key = wp_date( 'Y-m-d', $ts );
			if ( ! isset( $days[ $key ] ) ) {
				$days[ $key ] = 0;
			}
			++$days[ $key ];
		}

		$today = new DateTimeImmutable( 'today', wp_timezone() );
		?>
		<section class="sid-card sid-archive-coverage">
			<div class="sid-section-head">
				<div>
					<span class="sid-kicker"><?php esc_html_e( 'Pregled zadnjih 35 dana', 'sidrena' ); ?></span>
					<h2><?php esc_html_e( 'Kalendar generiranih objava', 'sidrena' ); ?></h2>
					<p><?php esc_html_e( 'Svaki označeni dan znači da je najmanje jedan CSV/XML cjenik spremljen u arhivu. Ovo nije pravni “score”: uslužni cjenik se prema odluci mora obnoviti pri promjeni cijene, dok trgovac cjenik proizvoda ažurira radnim danom.', 'sidrena' ); ?></p>
				</div>
			</div>
			<div class="sid-day-strip" role="list" aria-label="<?php esc_attr_e( 'Kalendar arhive', 'sidrena' ); ?>">
				<?php for ( $offset = 34; $offset >= 0; --$offset ) : ?>
					<?php
					$date  = $today->modify( '-' . $offset . ' days' );
					$key   = $date->format( 'Y-m-d' );
					$count = $days[ $key ] ?? 0;
					?>
					<?php /* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */ ?>
					<div class="sid-day <?php echo $count ? 'has-files' : ''; ?>" role="listitem" title="<?php echo esc_attr( $date->format( 'd.m.Y.' ) . ' · ' . sprintf( _n( '%d datoteka', '%d datoteka', $count, 'sidrena' ), $count ) ); ?>">
						<span><?php echo esc_html( $date->format( 'd' ) ); ?></span>
						<i></i>
					</div>
				<?php endfor; ?>
			</div>
			<div class="sid-legend"><span><i class="is-filled"></i><?php esc_html_e( 'objava postoji', 'sidrena' ); ?></span><span><i></i><?php esc_html_e( 'nema generirane objave', 'sidrena' ); ?></span></div>
		</section>
		<?php
	}

	private function files_table( $files, $show_location = true ) {
		?>
		<div class="sid-table-wrap">
			<table class="widefat striped sid-table">
				<caption class="screen-reader-text"><?php esc_html_e( 'Generirane datoteke javnog cjenika', 'sidrena' ); ?></caption>
				<thead><tr><?php if ( $show_location ) : ?><th scope="col"><?php esc_html_e( 'Lokacija', 'sidrena' ); ?></th><?php endif; ?><th scope="col"><?php esc_html_e( 'Vrsta', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Format', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Redaka', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Objavljeno', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Čuvati do', 'sidrena' ); ?></th><th scope="col">SHA-256</th><th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Radnje', 'sidrena' ); ?></span></th></tr></thead>
				<tbody>
				<?php if ( empty( $files ) ) : ?><tr><td colspan="<?php echo esc_attr( $show_location ? 8 : 7 ); ?>"><?php esc_html_e( 'Još nema generiranih datoteka.', 'sidrena' ); ?></td></tr><?php else : ?>
					<?php foreach ( $files as $file ) : ?>
						<tr>
							<?php if ( $show_location ) : ?><td><strong><?php echo esc_html( $file['location_code'] ?? '' ); ?></strong><small class="sid-cell-sub"><?php echo esc_html( $file['kind'] ?? '' ); ?></small></td><?php endif; ?>
							<td><?php echo 'products' === ( $file['catalog'] ?? '' ) ? esc_html__( 'Proizvodi', 'sidrena' ) : esc_html__( 'Usluge', 'sidrena' ); ?></td>
							<td><span class="sid-file-pill"><?php echo esc_html( strtoupper( $file['format'] ?? '' ) ); ?></span></td>
							<td><?php echo esc_html( $file['rows'] ?? 0 ); ?></td>
							<td><?php echo esc_html( $file['generated_at'] ?? '' ); ?><small class="sid-cell-sub"><?php echo esc_html( $file['filename'] ?? '' ); ?></small></td>
							<td><?php echo ! empty( $file['retain_until_ts'] ) ? esc_html( wp_date( 'd.m.Y. H:i', absint( $file['retain_until_ts'] ) ) ) : '—'; ?></td>
							<td><code class="sid-hash" title="<?php echo esc_attr( $file['sha256'] ?? '' ); ?>"><?php echo esc_html( ! empty( $file['sha256'] ) ? substr( $file['sha256'], 0, 12 ) . '…' : '—' ); ?></code></td>
							<td><?php if ( ! empty( $file['url'] ) ) : ?><?php
								$file_name = (string) ( $file['filename'] ?? '' );
								$open_label = $file_name
									? sprintf(
										/* translators: %s: generated public price-list filename. */
										__( 'Otvori datoteku %s', 'sidrena' ),
										$file_name
									)
									: __( 'Otvori datoteku cjenika', 'sidrena' );
								?><a class="button button-small" href="<?php echo esc_url( $file['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $open_label ); ?>"><?php esc_html_e( 'Otvori', 'sidrena' ); ?></a><?php else : ?><span class="sid-status-pill is-warn"><?php esc_html_e( 'URL nedostaje', 'sidrena' ); ?></span><?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function locations_tab() {
		$locations   = Sidrena_Utils::locations();
		$stats       = $this->audit_stats();
		$woo_address = Sidrena_Utils::is_woocommerce_edition() ? Sidrena_Utils::woocommerce_store_address() : '';
		?>
		<div class="sid-page-head"><div><span class="sid-kicker"><?php esc_html_e( 'Poslovnice i webshop', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Lokacije cjenika', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Za svaku fizičku lokaciju generira se zasebna datoteka. Webshop se također vodi kao zaseban objekt. Kod fizičkih lokacija raspoloživost mora odgovarati stvarnom stanju upravo te poslovnice.', 'sidrena' ); ?></p></div></div>
		<form class="sid-card sid-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sidrena_save_locations">
			<?php wp_nonce_field( 'sidrena_save_locations' ); ?>
			<div id="sid-locations" class="sid-locations" data-sidrena-woo-address="<?php echo esc_attr( $woo_address ); ?>">
				<?php foreach ( $locations as $index => $location ) : ?><?php $this->location_card( $index, $location, $stats['products'] ); ?><?php endforeach; ?>
			</div>
			<div class="sid-form-actions"><div class="sid-form-actions__group"><button type="button" class="button sid-secondary" id="sid-add-location"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e( 'Dodaj lokaciju', 'sidrena' ); ?></button><?php if ( $woo_address ) : ?><button type="button" class="button sid-secondary" id="sid-fill-woo-location"><span class="dashicons dashicons-store"></span><?php esc_html_e( 'Popuni adresu web trgovine', 'sidrena' ); ?></button><?php endif; ?></div><button class="button button-primary sid-primary" type="submit"><?php esc_html_e( 'Spremi lokacije', 'sidrena' ); ?></button></div>
		</form>
		<template id="sid-location-template"><?php $this->location_card( '__INDEX__', array( 'id' => '', 'enabled' => 'yes', 'kind' => 'prodavaonica', 'address' => '', 'code' => '', 'sequence' => 1 ), $stats['products'], true ); ?></template>
		<?php
	}

	private function location_card( $index, $location, $product_count, $template = false ) {
		$location_id = $template ? '' : ( $location['id'] ?? '' );
		$coverage    = Sidrena_Utils::is_woocommerce_edition() && class_exists( 'Sidrena_Location_Data' ) && $location_id ? Sidrena_Location_Data::coverage( $location_id ) : 0;
		$kind        = $location['kind'] ?? 'objekt';
		?>
		<div class="sid-location">
			<div class="sid-location-head">
				<?php
				$location_title   = $location['code'] ? $location['code'] : __( 'Nova lokacija', 'sidrena' );
				$location_address = $location['address'] ? $location['address'] : __( 'Adresa nije upisana', 'sidrena' );
				$remove_label = sprintf(
					/* translators: %s: location code or fallback title. */
					__( 'Ukloni lokaciju %s', 'sidrena' ),
					$location_title
				);
				?>
				<div><span class="sid-location-icon dashicons <?php echo 'webshop' === sanitize_key( $kind ) ? 'dashicons-store' : 'dashicons-location'; ?>"></span><strong class="sid-location-title"><?php echo esc_html( $location_title ); ?></strong><small class="sid-location-address"><?php echo esc_html( $location_address ); ?></small></div>
				<div class="sid-location-actions"><label class="sid-switch"><input class="sid-location-enabled" type="checkbox" name="locations[<?php echo esc_attr( $index ); ?>][enabled]" value="yes" <?php checked( $location['enabled'] ?? '', 'yes' ); ?>><span><?php esc_html_e( 'Aktivna', 'sidrena' ); ?></span></label><button type="button" class="button-link-delete sid-remove-location" aria-label="<?php echo esc_attr( $remove_label ); ?>"><?php esc_html_e( 'Ukloni', 'sidrena' ); ?></button></div>
			</div>
			<div class="sid-fields sid-fields-location">
				<label><span><?php esc_html_e( 'ID lokacije', 'sidrena' ); ?></span><input type="text" name="locations[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $location['id'] ?? '' ); ?>" placeholder="zagreb-centar" data-required-when-active <?php echo 'yes' === ( $location['enabled'] ?? '' ) ? 'required aria-required="true"' : 'aria-required="false"'; ?>></label>
				<label><span><?php esc_html_e( 'Oblik / vrsta objekta', 'sidrena' ); ?></span><input class="sid-location-kind" type="text" name="locations[<?php echo esc_attr( $index ); ?>][kind]" value="<?php echo esc_attr( $kind ); ?>" placeholder="prodavaonica / servis / webshop" data-required-when-active <?php echo 'yes' === ( $location['enabled'] ?? '' ) ? 'required aria-required="true"' : 'aria-required="false"'; ?>></label>
				<label><span><?php esc_html_e( 'Oznaka objekta', 'sidrena' ); ?></span><input type="text" name="locations[<?php echo esc_attr( $index ); ?>][code]" value="<?php echo esc_attr( $location['code'] ?? '' ); ?>" placeholder="P-01" data-required-when-active <?php echo 'yes' === ( $location['enabled'] ?? '' ) ? 'required aria-required="true"' : 'aria-required="false"'; ?>></label>
				<label class="sid-wide"><span><?php esc_html_e( 'Adresa objekta', 'sidrena' ); ?></span><input class="sid-location-address-input" type="text" name="locations[<?php echo esc_attr( $index ); ?>][address]" value="<?php echo esc_attr( $location['address'] ?? '' ); ?>" placeholder="Ilica 150, Zagreb" data-required-when-active <?php echo 'yes' === ( $location['enabled'] ?? '' ) ? 'required aria-required="true"' : 'aria-required="false"'; ?>></label>
				<label><span><?php esc_html_e( 'Sljedeći broj pohrane', 'sidrena' ); ?></span><input type="number" min="1" name="locations[<?php echo esc_attr( $index ); ?>][sequence]" value="<?php echo esc_attr( max( 1, absint( $location['sequence'] ?? 1 ) ) ); ?>"></label>
			</div>
			<?php if ( ! $template && 'webshop' !== sanitize_key( $kind ) && $product_count > 0 ) : ?>
				<div class="sid-coverage"><span><?php esc_html_e( 'Lokacijska raspoloživost', 'sidrena' ); ?></span><progress class="sid-progress" max="<?php echo esc_attr( max( 1, $product_count ) ); ?>" value="<?php echo esc_attr( min( $coverage, $product_count ) ); ?>"><span><?php echo esc_html( $coverage . '/' . $product_count ); ?></span></progress><strong><?php echo esc_html( $coverage . '/' . $product_count ); ?></strong></div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function settings_tab() {
		$settings = Sidrena_Utils::settings();
		?>
		<div class="sid-page-head"><div><span class="sid-kicker"><?php esc_html_e( 'Jednostavno postavljanje', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Postavke', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Zakonski važna objava i evidencija uključene su automatski. Vi određujete što objavljujete i vrijeme dnevnog generiranja. Zakonski referentni datumi i 30-dnevno čuvanje javne arhive zaključani su pravilima plugina.', 'sidrena' ); ?></p></div></div>
		<form class="sid-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sidrena_save_settings">
			<?php wp_nonce_field( 'sidrena_save_settings' ); ?>

			<section class="sid-card sid-settings-section">
				<div class="sid-settings-title"><span class="dashicons dashicons-admin-home"></span><div><h2><?php esc_html_e( '1. Što objavljujete', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Odaberite proizvode, usluge ili oboje. Ostale obvezne tehničke opcije Sidrena vodi automatski.', 'sidrena' ); ?></p></div></div>
				<div class="sid-fields"><label><span><?php esc_html_e( 'Način rada', 'sidrena' ); ?></span><select name="business_mode"><option value="products" <?php selected( $settings['business_mode'], 'products' ); ?>><?php esc_html_e( 'Proizvodi / trgovina', 'sidrena' ); ?></option><option value="services" <?php selected( $settings['business_mode'], 'services' ); ?>><?php esc_html_e( 'Usluge', 'sidrena' ); ?></option><option value="mixed" <?php selected( $settings['business_mode'], 'mixed' ); ?>><?php esc_html_e( 'Proizvodi i usluge', 'sidrena' ); ?></option></select></label></div>
			</section>

			<section class="sid-card sid-settings-section">
				<div class="sid-settings-title"><span class="dashicons dashicons-shield-alt"></span><div><h2><?php esc_html_e( '2. Automatska zaštita objave', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Ove funkcije više se ne mogu slučajno isključiti u administraciji.', 'sidrena' ); ?></p></div></div>
				<div class="sid-grid sid-grid-2">
					<div class="sid-note"><div class="sid-note-icon"><span class="dashicons dashicons-media-spreadsheet"></span></div><div><strong><?php esc_html_e( 'CSV + XML uvijek uključeni', 'sidrena' ); ?></strong><p><?php esc_html_e( 'Sidrena automatski objavljuje oba strojno čitljiva formata i koristi stabilni CSV razdjelnik.', 'sidrena' ); ?></p></div></div>
					<div class="sid-note"><div class="sid-note-icon"><span class="dashicons dashicons-rest-api"></span></div><div><strong><?php esc_html_e( 'Automatizirani dohvat uvijek uključen', 'sidrena' ); ?></strong><p><?php esc_html_e( 'Javni HTML, JSON manifest i REST indeks ostaju aktivni za dohvat aktualnih podataka i datoteka.', 'sidrena' ); ?></p></div></div>
					<div class="sid-note"><div class="sid-note-icon"><span class="dashicons dashicons-tag"></span></div><div><strong><?php esc_html_e( 'Fokus na sidrenu cijenu', 'sidrena' ); ?></strong><p><?php esc_html_e( 'SIDRENA vodi sidrenu cijenu odvojeno od posebnih oblika prodaje. U cjeniku se za poseban oblik prodaje čuvaju samo status i naziv oblika.', 'sidrena' ); ?></p></div></div>
					<div class="sid-note"><div class="sid-note-icon"><span class="dashicons dashicons-lock"></span></div><div><strong><?php esc_html_e( 'Sigurna objava i nadzor', 'sidrena' ); ?></strong><p><?php esc_html_e( 'Strict publication, 30-dnevna javna arhiva, watchdog i upozorenja ostaju uključeni kako neispravna nova objava ne bi zamijenila zadnju valjanu.', 'sidrena' ); ?></p></div></div>
				</div>
				<?php
				/* translators: %1$s: general reference date; %2$s: reference date for previously covered FMCG categories. */
				$reference_dates_text = __( 'Referentni datumi koje Sidrena automatski primjenjuje: opći %1$s, ranije obuhvaćeni FMCG %2$s.', 'sidrena' );
				?>
				<p class="description"><?php echo esc_html( sprintf( $reference_dates_text, Sidrena_Utils::date_display( '2026-09-10' ), Sidrena_Utils::date_display( '2025-05-02' ) ) ); ?></p>
			</section>

			<section class="sid-card sid-settings-section">
				<div class="sid-settings-title"><span class="dashicons dashicons-clock"></span><div><h2><?php esc_html_e( '3. Raspored, arhiva i upozorenja', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Sidrena korigira vrijeme na sigurnu vrijednost ako unesete 08:00 ili kasnije. Javna arhiva automatski se čuva 30 dana.', 'sidrena' ); ?></p></div></div>
				<div class="sid-fields">
					<label><span><?php esc_html_e( 'Vrijeme dnevnog generiranja', 'sidrena' ); ?></span><input type="time" name="generation_time" value="<?php echo esc_attr( $settings['generation_time'] ); ?>"><small><?php esc_html_e( 'Preporučeno 06:30. Vrijednost mora biti prije 08:00.', 'sidrena' ); ?></small></label>
					<label class="sid-wide"><span><?php esc_html_e( 'E-mail za upozorenja', 'sidrena' ); ?></span><input type="email" maxlength="190" name="failure_email" value="<?php echo esc_attr( $settings['failure_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email', '' ) ); ?>"><small><?php esc_html_e( 'Ako ostavite prazno, koristi se WordPress administratorski e-mail.', 'sidrena' ); ?></small></label>
				</div>
			</section>

			<div class="sid-form-actions sid-sticky-actions"><button class="button button-primary sid-primary" type="submit"><?php esc_html_e( 'Spremi postavke', 'sidrena' ); ?></button></div>
		</form>
		<?php
	}

	private function tools_tab() {
		$woo = Sidrena_Utils::is_woocommerce_active();
		?>
		<div class="sid-page-head">
			<div>
				<span class="sid-kicker"><?php esc_html_e( 'Uvoz i provjera', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Alati', 'sidrena' ); ?></h2>
				<p><?php echo $woo ? esc_html__( 'WooCommerce integracija je aktivna. Dostupni su Woo uvozi, lokacijski predlošci i zajednički Sidrena alati.', 'sidrena' ) : esc_html__( 'Sidrena WordPress koristi vlastiti katalog proizvoda i alate namijenjene WordPress izdanju.', 'sidrena' ); ?></p>
			</div>
			<span class="sid-status-pill is-ok"><?php echo esc_html( Sidrena_Utils::runtime_mode_label() ); ?></span>
		</div>

		<?php if ( $woo ) : ?>
			<div class="sid-grid sid-grid-2">
				<form class="sid-card sid-tool-card sid-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="sidrena_import_anchor"><?php wp_nonce_field( 'sidrena_import_anchor' ); ?>
					<div class="sid-tool-icon"><span class="dashicons dashicons-tag"></span></div><h2><?php esc_html_e( 'Uvoz sidrenih cijena', 'sidrena' ); ?></h2><p><?php esc_html_e( 'CSV stupci: sku, anchor_price, anchor_date, reference_group. Prihvaća ; , ili TAB.', 'sidrena' ); ?></p><label class="sid-file-control"><span><?php esc_html_e( 'CSV datoteka', 'sidrena' ); ?></span><input class="sid-file-input" type="file" name="anchor_csv" accept=".csv,text/csv,text/plain" aria-describedby="sid-anchor-csv-help" required><small id="sid-anchor-csv-help"><?php esc_html_e( 'Najviše 5 MB. Odaberite stvarnu CSV datoteku s podacima za uvoz.', 'sidrena' ); ?></small></label><button class="button button-primary sid-primary" type="submit"><?php esc_html_e( 'Uvezi sidrene cijene', 'sidrena' ); ?></button>
				</form>
				<form class="sid-card sid-tool-card sid-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="sidrena_import_location_data"><?php wp_nonce_field( 'sidrena_import_location_data' ); ?>
					<div class="sid-tool-icon"><span class="dashicons dashicons-location-alt"></span></div><h2><?php esc_html_e( 'Raspoloživost i cijena po lokaciji', 'sidrena' ); ?></h2><p><?php esc_html_e( 'CSV stupci: location_id, product_id ili sku, price, anchor_price, availability. Prazna cijena za lokaciju koristi osnovnu WooCommerce vrijednost.', 'sidrena' ); ?></p><label class="sid-file-control"><span><?php esc_html_e( 'CSV datoteka', 'sidrena' ); ?></span><input class="sid-file-input" type="file" name="location_csv" accept=".csv,text/csv,text/plain" aria-describedby="sid-location-csv-help" required><small id="sid-location-csv-help"><?php esc_html_e( 'Najviše 5 MB. Datoteka mora sadržavati lokaciju i identifikator proizvoda ili SKU.', 'sidrena' ); ?></small></label><button class="button button-primary sid-primary" type="submit"><?php esc_html_e( 'Uvezi lokacijske podatke', 'sidrena' ); ?></button>
				</form>
			</div>
			<div class="sid-grid sid-grid-2">
				<section class="sid-card sid-tool-card"><div class="sid-tool-icon"><span class="dashicons dashicons-warning"></span></div><h2><?php esc_html_e( 'Nedostajuće sidrene cijene', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Izvezite WooCommerce stavke bez sidrene cijene, dopunite ih iz vjerodostojne evidencije i vratite CSV u uvoz.', 'sidrena' ); ?></p><a class="button sid-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_export_missing' ), 'sidrena_export_missing' ) ); ?>"><?php esc_html_e( 'Preuzmi CSV za dopunu', 'sidrena' ); ?></a></section>
				<section class="sid-card sid-tool-card"><div class="sid-tool-icon"><span class="dashicons dashicons-download"></span></div><h2><?php esc_html_e( 'Predložak lokacija', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Preuzmite aktivne lokacije i WooCommerce stavke, dopunite raspoloživost/cijene i vratite CSV u uvoz.', 'sidrena' ); ?></p><a class="button sid-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_export_location_template' ), 'sidrena_export_location_template' ) ); ?>"><?php esc_html_e( 'Preuzmi predložak', 'sidrena' ); ?></a></section>
			</div>
		<?php else : ?>
			<div class="sid-grid sid-grid-2">
				<section class="sid-card sid-tool-card">
					<div class="sid-tool-icon"><span class="dashicons dashicons-products"></span></div>
					<h2><?php esc_html_e( 'WordPress katalog proizvoda', 'sidrena' ); ?></h2>
					<p><?php esc_html_e( 'Dodajte proizvode ručno ili uvezite CSV/XML izravno u Sidrena katalog. WooCommerce nije potreban.', 'sidrena' ); ?></p>
					<a class="button button-primary sid-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-catalog' ) ); ?>"><?php esc_html_e( 'Otvori katalog i uvoz', 'sidrena' ); ?></a>
				</section>
				<section class="sid-card sid-tool-card">
					<div class="sid-tool-icon"><span class="dashicons dashicons-clipboard"></span></div>
					<h2><?php esc_html_e( 'Katalog usluga', 'sidrena' ); ?></h2>
					<p><?php esc_html_e( 'Usluge se mogu koristiti uz proizvode ili kao zaseban cjenik usluga.', 'sidrena' ); ?></p>
					<a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=sidrena_service' ) ); ?>"><?php esc_html_e( 'Otvori usluge', 'sidrena' ); ?></a>
				</section>
			</div>
		<?php endif; ?>

		<div class="sid-grid sid-grid-2">
			<section class="sid-card sid-tool-card"><div class="sid-tool-icon"><span class="dashicons dashicons-media-spreadsheet"></span></div><h2><?php esc_html_e( 'Evidencija javne arhive', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Izvezite indeks svih objavljenih cjenika s datumom, rokom čuvanja, brojem redaka, veličinom, SHA-256 zapisom i URL-om.', 'sidrena' ); ?></p><a class="button sid-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_export_archive_index' ), 'sidrena_export_archive_index' ) ); ?>"><?php esc_html_e( 'Preuzmi indeks arhive', 'sidrena' ); ?></a></section>
		</div>

		<section class="sid-card sid-note"><div class="sid-note-icon"><span class="dashicons dashicons-shield"></span></div><div><h2><?php echo Sidrena_Utils::is_woocommerce_edition() ? esc_html__( 'WooCommerce izdanje', 'sidrena' ) : esc_html__( 'WordPress izdanje', 'sidrena' ); ?></h2><p><?php echo Sidrena_Utils::is_woocommerce_edition() ? esc_html__( 'Ovaj plugin radi isključivo s WooCommerce katalogom. Za web bez WooCommercea instalirajte zasebni Sidrena WordPress paket.', 'sidrena' ) : esc_html__( 'Ovaj plugin koristi vlastiti WordPress katalog i ne integrira WooCommerce proizvode. Za WooCommerce trgovinu instalirajte zasebni Sidrena WooCommerce paket.', 'sidrena' ); ?></p></div></section>
		<?php
	}
	private function log_tab() {
		$rows = Sidrena_Audit::recent( 150 );
		?>
		<div class="sid-page-head"><div><span class="sid-kicker"><?php esc_html_e( 'Sljedivost', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Dnevnik važnih događaja', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Generiranje cjenika i administrativne promjene bilježe se u Dnevniku radi provjere rada i lakšeg otklanjanja pogrešaka.', 'sidrena' ); ?></p></div></div>
		<section class="sid-card">
			<div class="sid-table-wrap"><table class="widefat striped sid-log-table"><caption class="screen-reader-text"><?php esc_html_e( 'Dnevnik važnih SIDRENA događaja', 'sidrena' ); ?></caption><thead><tr><th scope="col"><?php esc_html_e( 'Vrijeme', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Događaj', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Status', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Opis', 'sidrena' ); ?></th></tr></thead><tbody>
			<?php if ( empty( $rows ) ) : ?><tr><td colspan="4"><?php esc_html_e( 'Dnevnik je zasad prazan.', 'sidrena' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $rows as $row ) : ?>
			<?php
			$status_labels = array(
				'success' => __( 'Uspješno', 'sidrena' ),
				'warning' => __( 'Upozorenje', 'sidrena' ),
				'error'   => __( 'Greška', 'sidrena' ),
			);
			$status = sanitize_key( $row['status'] ?? '' );
			?>
			<tr><td><?php echo esc_html( Sidrena_Utils::format_mysql_datetime( $row['created_at'] ) ); ?></td><td><code><?php echo esc_html( $row['event_type'] ); ?></code></td><td><span class="sid-status sid-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_labels[ $status ] ?? ucfirst( $status ) ); ?></span></td><td><?php echo esc_html( $row['message'] ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table></div>
		</section>
		<?php
	}

	private function rules_tab() {
		?>
		<div class="sid-page-head"><div><span class="sid-kicker"><?php esc_html_e( 'Službeni izvori', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Pravila koja Sidrena tehnički podržava', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Sažetak je informativan. Za konačnu primjenu uvijek provjerite službeni tekst propisa i pojašnjenja nadležnih tijela.', 'sidrena' ); ?></p></div></div>
		<div class="sid-grid sid-grid-2">
			<?php $this->rule_card( '01', __( 'Dodatna / sidrena cijena', 'sidrena' ), __( 'Za novobuhvaćene proizvode i usluge referentna je redovna cijena na 10.09.2026.; ranije obuhvaćeni FMCG zadržava 02.05.2025. Ako je stavka na referentni dan bila na akciji, uzima se prethodna redovna cijena. Novouvedena stavka nakon referentnog dana koristi cijenu prvog uvrštenja i taj datum.', 'sidrena' ), 'https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html', 'NN 101/2026, 1212' ); ?>
			<?php $this->rule_card( '02', __( 'XML ili CSV cjenik i 30-dnevna arhiva', 'sidrena' ), __( 'Trgovci koji imaju mrežnu stranicu ažuriraju cjenik proizvoda jednom dnevno, najkasnije do 08:00 za tekući radni dan; pružatelji usluga ažuriraju cjenik usluga uslijed svake promjene, najkasnije do 08:00 dana kada objavljuju izmjenu cjenika usluga. Odluka dopušta XML ili CSV, a SIDRENA radi interoperabilnosti generira oba formata. Prethodne objave moraju ostati javno dostupne najmanje 30 dana, a tehničko rješenje mora omogućiti automatizirani dohvat aktualnih cijena u realnom vremenu.', 'sidrena' ), 'https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html', 'NN 101/2026, 1213' ); ?>
			<?php $this->rule_card( '03', __( 'Detaljna pojašnjenja Ministarstva', 'sidrena' ), __( 'Pojašnjenja pokrivaju webshopove i informativne web-stranice, zasebne podatke po poslovnici, stvarnu raspoloživost po lokaciji, novouvedene proizvode i usluge, promjene šifre/naziva, akcije, usluge bez unaprijed fiksne cijene te strukturu digitalnih cjenika. Profil na društvenoj mreži sam po sebi ne smatra se mrežnom stranicom.', 'sidrena' ), 'https://mingo.gov.hr/print.aspx?id=10440&url=print', __( 'Ministarstvo gospodarstva · 22.09.2026.', 'sidrena' ) ); ?>
			<?php $this->rule_card( '05', __( 'Maloprodajna, jedinična i cijena usluge', 'sidrena' ), __( 'NN 105/2026 objavljen je 18.09.2026. i stupa na snagu osmoga dana od objave. Uređuje jasan prikaz maloprodajne i jedinične cijene te iznimke. Za usluge traži lako dostupan cjenik, naziv, vrstu i opseg usluge te uključivanje pripadajućih troškova u cijenu; cijena ugradbene ili zamjenske robe prikazuje se uz pripadajuću uslugu.', 'sidrena' ), 'https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_105_1270.html', 'NN 105/2026, 1270' ); ?>
			<section class="sid-card sid-rule-card sid-rule-future"><div class="sid-rule-number">06</div><div><span class="sid-rule-tag"><?php esc_html_e( 'Praćenje promjena', 'sidrena' ); ?></span><h3><?php esc_html_e( 'Bazna cijena u Zakonu od 17.11.2026.', 'sidrena' ); ?></h3><p><?php esc_html_e( 'Članak 7. stavci 1. do 9. izmijenjenog Zakona počinju se primjenjivati 17.11.2026. i uvode obvezu isticanja bazne cijene te objave važećih cjenika proizvoda na mrežnim stranicama. Bazna cijena nije isto što i dodatna/sidrena cijena iz NN 101/2026. Konkretan referentni dan, proizvodi i način isticanja bazne cijene uređuju se provedbenim pravilnikom, pa Sidrena ne pretpostavlja vrijednosti koje još nisu propisane.', 'sidrena' ); ?></p><a href="https://narodne-novine.nn.hr/clanci/sluzbeni/2026_06_59_728.html" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Otvori službeni izvor', 'sidrena' ); ?><span class="dashicons dashicons-external"></span></a></div></section>
		</div>
		<div class="sid-page-head sid-page-head--compact"><div><span class="sid-kicker"><?php esc_html_e( 'NN 105/2026 · primjena od 26.09.2026.', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Brza provjera cijene za jedinicu mjere', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Sidrena ne zaključuje automatski pravnu kategoriju proizvoda samo iz naziva ili WooCommerce kategorije. Administrator označava primjenjivost prema stvarnom proizvodu i službenom Pravilniku.', 'sidrena' ); ?></p></div></div>
		<div class="sid-grid sid-grid-2">
			<section class="sid-card sid-rule-guide"><div class="sid-rule-guide__head"><span class="sid-rule-guide__icon dashicons dashicons-yes-alt"></span><div><h3><?php esc_html_e( 'Kategorije za koje se ističe jedinična cijena', 'sidrena' ); ?></h3><p><?php esc_html_e( 'Članak 8. stavak 1. NN 105/2026.', 'sidrena' ); ?></p></div></div><ul class="sid-rule-list"><li><?php esc_html_e( 'Hrana i hrana za životinje, osim kada je drukčije propisano.', 'sidrena' ); ?></li><li><?php esc_html_e( 'Deterdženti, dječje pelene, boje i lakovi osim slikarskih boja.', 'sidrena' ); ?></li><li><?php esc_html_e( 'Ulja i tekućine za motore i motorna vozila te destilirana voda.', 'sidrena' ); ?></li><li><?php esc_html_e( 'Proizvodi za njegu, pranje i čišćenje lica i tijela, njegu kose te oralnu higijenu.', 'sidrena' ); ?></li></ul></section>
			<section class="sid-card sid-rule-guide"><div class="sid-rule-guide__head"><span class="sid-rule-guide__icon dashicons dashicons-info-outline"></span><div><h3><?php esc_html_e( 'Propisane iznimke', 'sidrena' ); ?></h3><p><?php esc_html_e( 'Članak 8. stavak 2. NN 105/2026.', 'sidrena' ); ?></p></div></div><ul class="sid-rule-list"><li><?php esc_html_e( 'Pakiranja ispod 50 g ili 50 ml, poklon-paketi i kompleti te roba u posebnom obliku prodaje.', 'sidrena' ); ?></li><li><?php esc_html_e( 'Poslastice za kućne ljubimce, kolači i alkoholna pića u pakiranjima do 100 ml.', 'sidrena' ); ?></li><li><?php esc_html_e( 'Smrznuti deserti do 500 ml te voće i povrće koje se prodaje komadno ili u svežnju.', 'sidrena' ); ?></li><li><?php esc_html_e( 'Za tekući medij mjerodavna je ocijeđena masa; za glaziranu hranu neto masa bez glazure.', 'sidrena' ); ?></li></ul></section>
		</div>
		<section class="sid-card sid-note"><div class="sid-note-icon"><span class="dashicons dashicons-shield"></span></div><div><h2><?php esc_html_e( 'Tehnička provjera, ne automatska pravna odluka', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Status “obvezna”, “nije primjenjiva” ili “iznimka” služi kao evidencija odluke administratora. Sidrena provjerava jesu li potrebni podaci uneseni, ali ne klasificira proizvod umjesto odgovorne osobe.', 'sidrena' ); ?></p></div></section>
		<div class="sid-page-head sid-page-head--compact"><div><span class="sid-kicker"><?php esc_html_e( 'Praktična pojašnjenja', 'sidrena' ); ?></span><h2><?php esc_html_e( 'HOK — praktično pojašnjenje', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Ovi izvori pomažu u primjeni i transparentnosti, ali ne zamjenjuju primarni tekst propisa u Narodnim novinama.', 'sidrena' ); ?></p></div></div>
		<div class="sid-grid sid-grid-2">
			<?php $this->rule_card( 'A', __( 'HOK — informacije za obrtnike', 'sidrena' ), __( 'HOK sažima obvezu isticanja dodatne/sidrene cijene od 1.10.2026., uključujući isticanje na mrežnim stranicama i referentne datume za novobuhvaćene te ranije obuhvaćene kategorije.', 'sidrena' ), 'https://www.hok.hr/novosti-iz-hok/dodatna-cijena-i-objava-cjenika-od-1-listopada-2026-najvaznije-informacije', 'Hrvatska obrtnička komora · 18.09.2026.' ); ?>
		</div>
		<section class="sid-card sid-note"><div class="sid-note-icon"><span class="dashicons dashicons-info-outline"></span></div><div><h2><?php esc_html_e( '30 dana = javna arhiva cjenika', 'sidrena' ); ?></h2><p><?php esc_html_e( 'SIDRENA čuva svaku objavljenu CSV/XML verziju 30 dana. To razdoblje nije dio izračuna sidrene cijene.', 'sidrena' ); ?></p></div></section>
		<?php
	}

	private function rule_card( $number, $title, $text, $url, $source ) {
		?><section class="sid-card sid-rule-card"><div class="sid-rule-number"><?php echo esc_html( $number ); ?></div><div><span class="sid-rule-tag"><?php echo esc_html( $source ); ?></span><h3><?php echo esc_html( $title ); ?></h3><p><?php echo esc_html( $text ); ?></p><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Otvori službeni izvor', 'sidrena' ); ?><span class="dashicons dashicons-external"></span></a></div></section><?php
	}

	public function save_settings() {
		$this->guard_post( 'sidrena_save_settings' );

		$business_mode = sanitize_key( $this->post_value( 'business_mode', 'mixed' ) );
		if ( ! in_array( $business_mode, array( 'products', 'services', 'mixed' ), true ) ) {
			$business_mode = 'mixed';
		}

		$generation_time = Sidrena_Legal_Automation::normalize_generation_time( $this->post_value( 'generation_time', '06:30' ) );
		$retention_days  = 30;
		$failure_raw     = trim( sanitize_text_field( $this->post_value( 'failure_email', '' ) ) );
		$failure_email   = sanitize_email( $failure_raw );
		if ( '' !== $failure_raw && ( '' === $failure_email || ! is_email( $failure_email ) ) ) {
			$this->redirect( 'settings', 'settings_invalid_email' );
		}

		$new = array(
			'business_mode'         => $business_mode,
			'display_anchor'        => 'yes',
			'generate_csv'          => 'yes',
			'generate_xml'          => 'yes',
			'csv_delimiter'         => ';',
			'generation_time'       => $generation_time,
			'retention_days'        => $retention_days,
			'enable_rest_index'     => 'yes',
			'publish_manifest'      => 'yes',
			'enable_public_html'    => 'yes',
			'strict_publication'    => 'yes',
			'failure_notifications' => 'yes',
			'failure_email'         => $failure_email,
		);

		update_option( 'sidrena_settings', $new, false );
		$saved = Sidrena_Utils::settings();

		wp_clear_scheduled_hook( 'sidrena_daily_generation' );
		$scheduled = wp_schedule_event( Sidrena_Utils::schedule_timestamp( $saved['generation_time'] ), 'daily', 'sidrena_daily_generation' );
		if ( ! wp_next_scheduled( 'sidrena_publication_watch' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'sidrena_publication_watch' );
		}

		Sidrena_Pricelist::queue_regeneration();
		Sidrena_Audit::log(
			'settings_save',
			false === $scheduled || is_wp_error( $scheduled ) ? 'warning' : 'success',
			false === $scheduled || is_wp_error( $scheduled ) ? __( 'Sidrena postavke su spremljene, ali dnevno generiranje nije ponovno zakazano.', 'sidrena' ) : __( 'Sidrena postavke su spremljene i zakonska automatizacija ostaje uključena.', 'sidrena' ),
			array( 'generation_time' => $saved['generation_time'], 'retention_days' => $saved['retention_days'] )
		);
		$this->redirect( 'settings', false === $scheduled || is_wp_error( $scheduled ) ? 'settings_saved_cron_warning' : 'saved' );
	}

	public function save_locations() {
		$this->guard_post( 'sidrena_save_locations' );
		$input = $this->post_array( 'locations' );
		if ( empty( $input ) ) {
			$this->redirect( 'locations', 'locations_required' );
		}

		$out     = array();
		$used    = array();
		$old_ids = array();
		foreach ( Sidrena_Utils::locations() as $old_location ) {
			if ( ! empty( $old_location['id'] ) ) {
				$old_ids[] = Sidrena_Utils::sanitize_location_id( $old_location['id'] );
			}
		}

		foreach ( $input as $location ) {
			if ( ! is_array( $location ) ) {
				continue;
		}

			$enabled = isset( $location['enabled'] ) ? 'yes' : 'no';
			$raw_id  = trim( sanitize_text_field( $location['id'] ?? '' ) );
			$kind    = trim( sanitize_text_field( $location['kind'] ?? '' ) );
			$address = trim( sanitize_text_field( $location['address'] ?? '' ) );
			$code    = trim( sanitize_text_field( $location['code'] ?? '' ) );

			if ( 'yes' === $enabled && ( '' === $raw_id || '' === $kind || '' === $address || '' === $code ) ) {
				$this->redirect( 'locations', 'locations_invalid' );
			}

			$id = $raw_id ? Sidrena_Utils::sanitize_location_id( $raw_id ) : 'lokacija-' . ( count( $out ) + 1 );
			if ( isset( $used[ $id ] ) ) {
				$this->redirect( 'locations', 'locations_invalid' );
			}
			$used[ $id ] = true;

			$out[] = array(
				'id'       => $id,
				'enabled'  => $enabled,
				'kind'     => $kind ? $kind : 'objekt',
				'address'  => $address,
				'code'     => $code ? $code : '01',
				'sequence' => max( 1, absint( $location['sequence'] ?? 1 ) ),
			);
		}

		if ( empty( $out ) ) {
			$this->redirect( 'locations', 'locations_required' );
		}

		$new_ids = wp_list_pluck( $out, 'id' );
		if ( Sidrena_Utils::is_woocommerce_edition() && class_exists( 'Sidrena_Location_Data' ) ) {
			foreach ( array_diff( $old_ids, $new_ids ) as $deleted_id ) {
				Sidrena_Location_Data::delete_location( $deleted_id );
			}
		}
		update_option( 'sidrena_locations', $out, false );
		Sidrena_Pricelist::queue_regeneration();
		/* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */
		Sidrena_Audit::log( 'locations_save', 'success', sprintf( __( 'Spremljeno lokacija: %d.', 'sidrena' ), count( $out ) ), array( 'count' => count( $out ) ) );
		$this->redirect( 'locations', 'saved' );
	}
	public function generate() {
		if ( ! Sidrena_Utils::current_user_can_manage() || ! check_admin_referer( 'sidrena_generate' ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}
		$success = Sidrena_Pricelist::instance()->generate_all();
		$this->redirect( 'files', $success ? 'generated' : 'generated_with_errors' );
	}

	public function create_public_page() {
		if ( ! Sidrena_Utils::current_user_can_manage() || ! check_admin_referer( 'sidrena_create_public_page' ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}

		$existing_id = absint( get_option( 'sidrena_public_page_id', 0 ) );
		if ( $existing_id ) {
			$existing = get_post( $existing_id );
			if ( $existing && 'trash' !== $existing->post_status ) {
				$this->redirect( 'files', 'public_page_exists' );
			}
		}

		$page_id = Sidrena_Public::ensure_public_page();
		if ( is_wp_error( $page_id ) || ! $page_id ) {
			$this->redirect( 'files', 'public_page_failed' );
		}
		$this->redirect( 'files', 'public_page_created' );
	}

	public function import_anchor() {
		$this->guard_post( 'sidrena_import_anchor' );
		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			$this->redirect( 'tools', 'import_failed' );
		}
		$handle = $this->open_uploaded_csv( 'anchor_csv' );
		if ( is_wp_error( $handle ) ) {
			$this->redirect( 'tools', 'import_failed' );
		}
		list( $resource, $delimiter, $map ) = $handle;
		$aliases = array(
			'sku'             => array( 'sku', 'sifra', 'šifra', 'sifra_proizvoda', 'šifra proizvoda', 'sifra_artikla' ),
			'anchor_price'    => array( 'anchor_price', 'sidrena_cijena', 'sidrena cijena', 'dodatna_cijena', 'dodatna cijena', 'sidrena_price' ),
			'anchor_date'     => array( 'anchor_date', 'datum_sidrene_cijene', 'referentni_datum' ),
			'reference_group' => array( 'reference_group', 'referentna_skupina', 'skupina' ),
		);
		$map = $this->resolve_aliases( $map, $aliases );
		if ( ! isset( $map['sku'], $map['anchor_price'] ) ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$this->redirect( 'tools', 'import_failed' );
		}

		$processed = 0;
		$updated   = 0;
		$skipped   = 0;
		while ( true ) {
			$row = fgetcsv( $resource, 0, $delimiter );
			if ( false === $row ) {
				break;
			}
			++$processed;
			$sku = isset( $row[ $map['sku'] ] ) ? sanitize_text_field( $row[ $map['sku'] ] ) : '';
			if ( ! $sku ) {
				++$skipped;
				continue;
			}
			$product_id = Sidrena_Utils::find_product_id_by_code( $sku );
			if ( ! $product_id || ! current_user_can( 'edit_post', $product_id ) ) {
				++$skipped;
				continue;
			}
			$price_raw = isset( $row[ $map['anchor_price'] ] ) ? sanitize_text_field( (string) $row[ $map['anchor_price'] ] ) : '';
			$price     = $this->validated_import_price( $price_raw );
			if ( null === $price ) {
				++$skipped;
				continue;
			}

			$has_anchor_date = isset( $map['anchor_date'] ) && array_key_exists( $map['anchor_date'], $row );
			$date_raw        = $has_anchor_date ? sanitize_text_field( (string) $row[ $map['anchor_date'] ] ) : '';
			$valid_date      = '' !== trim( $date_raw ) ? Sidrena_Utils::sanitize_date( $date_raw ) : '';
			if ( $has_anchor_date && '' !== trim( $date_raw ) && '' === $valid_date ) {
				++$skipped;
				continue;
			}

			$has_group = isset( $map['reference_group'] ) && array_key_exists( $map['reference_group'], $row );
			$group_raw = $has_group ? sanitize_text_field( (string) $row[ $map['reference_group'] ] ) : '';
			$group     = '' !== trim( $group_raw ) ? sanitize_key( $group_raw ) : '';
			if ( $has_group && '' !== trim( $group_raw ) && ! in_array( $group, array( 'standard', 'fmcg', 'custom' ), true ) ) {
				++$skipped;
				continue;
			}

			++$updated;
			if ( '' === $price ) {
				delete_post_meta( $product_id, '_sidrena_anchor_price' );
			} else {
				update_post_meta( $product_id, '_sidrena_anchor_price', $price );
			}
			if ( $has_anchor_date ) {
				if ( '' === $valid_date ) {
					delete_post_meta( $product_id, '_sidrena_anchor_date' );
				} else {
					update_post_meta( $product_id, '_sidrena_anchor_date', $valid_date );
				}
			}
			if ( $has_group && '' !== $group ) {
				update_post_meta( $product_id, '_sidrena_reference_group', $group );
			}
		}
		fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		Sidrena_Pricelist::queue_regeneration();
		Sidrena_Audit::log(
			'anchor_import',
			'success',
			/* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */
			sprintf( __( 'Uvoz sidrenih cijena: %1$d obrađeno, %2$d ažurirano, %3$d preskočeno.', 'sidrena' ), $processed, $updated, $skipped ),
			array( 'processed' => $processed, 'updated' => $updated, 'skipped' => $skipped )
		);
		$this->redirect( 'tools', 'imported' );
	}

	public function import_location_data() {
		$this->guard_post( 'sidrena_import_location_data' );
		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			$this->redirect( 'tools', 'location_import_failed' );
		}
		$handle = $this->open_uploaded_csv( 'location_csv' );
		if ( is_wp_error( $handle ) ) {
			$this->redirect( 'tools', 'location_import_failed' );
		}
		list( $resource, $delimiter, $map ) = $handle;
		$aliases = array(
			'location_id'  => array( 'location_id', 'lokacija', 'id_lokacije', 'id lokacije', 'oznaka_lokacije' ),
			'product_id'   => array( 'product_id', 'id_proizvoda' ),
			'sku'          => array( 'sku', 'sifra', 'šifra', 'sifra_proizvoda', 'šifra proizvoda', 'sifra_artikla' ),
			'price'        => array( 'price', 'cijena', 'maloprodajna_cijena' ),
			'anchor_price' => array( 'anchor_price', 'sidrena_cijena', 'dodatna_cijena' ),
			'availability' => array( 'availability', 'dostupnost', 'raspolozivost', 'raspoloživost' ),
		);
		$map = $this->resolve_aliases( $map, $aliases );
		if ( ! isset( $map['location_id'], $map['availability'] ) || ( ! isset( $map['sku'] ) && ! isset( $map['product_id'] ) ) ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$this->redirect( 'tools', 'location_import_failed' );
		}
		$valid_locations = array();
		foreach ( Sidrena_Utils::locations() as $location ) {
			$valid_locations[ Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' ) ] = true;
		}
		$processed = 0;
		$updated   = 0;
		$skipped   = 0;
		while ( true ) {
			$row = fgetcsv( $resource, 0, $delimiter );
			if ( false === $row ) {
				break;
			}
			++$processed;
			$location_id = Sidrena_Utils::sanitize_location_id( $row[ $map['location_id'] ] ?? '' );
			if ( ! isset( $valid_locations[ $location_id ] ) ) {
				++$skipped;
				continue;
			}
			$product_id = 0;
			if ( isset( $map['sku'] ) ) {
				$sku = sanitize_text_field( $row[ $map['sku'] ] ?? '' );
				if ( $sku ) {
					$product_id = Sidrena_Utils::find_product_id_by_code( $sku );
				}
			}
			if ( ! $product_id && isset( $map['product_id'] ) ) {
				$product_id = absint( $row[ $map['product_id'] ] ?? 0 );
			}
			$product = $product_id ? wc_get_product( $product_id ) : false;
			if ( ! $product || ! current_user_can( 'edit_post', $product_id ) ) {
				++$skipped;
				continue;
			}
			$availability = sanitize_key( remove_accents( (string) ( $row[ $map['availability'] ] ?? '' ) ) );
			$availability = 'dostupno' === $availability ? 'dostupno' : ( 'nedostupno' === $availability ? 'nedostupno' : '' );
			if ( '' === $availability ) {
				++$skipped;
				continue;
			}
			$price        = isset( $map['price'] ) ? $this->validated_import_price( $row[ $map['price'] ] ?? '' ) : '';
			$anchor_price = isset( $map['anchor_price'] ) ? $this->validated_import_price( $row[ $map['anchor_price'] ] ?? '' ) : '';
			if ( null === $price || null === $anchor_price ) {
				++$skipped;
				continue;
			}
			$parent_id    = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
			$variation_id = $product->is_type( 'variation' ) ? $product->get_id() : 0;
			Sidrena_Location_Data::upsert( $location_id, $parent_id, $variation_id, $price, $availability, $anchor_price );
			++$updated;
		}
		fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		Sidrena_Pricelist::queue_regeneration();
		Sidrena_Audit::log(
			'location_import',
			'success',
			/* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */
			sprintf( __( 'Uvoz lokacijskih podataka: %1$d obrađeno, %2$d ažurirano, %3$d preskočeno.', 'sidrena' ), $processed, $updated, $skipped ),
			array( 'processed' => $processed, 'updated' => $updated, 'skipped' => $skipped )
		);
		$this->redirect( 'tools', 'location_imported' );
	}

	private function validate_uploaded_csv_size( $tmp_name, $reported_size ) {
		$max_size      = 5 * MB_IN_BYTES;
		$reported_size = max( 0, (int) $reported_size );
		if ( $reported_size > $max_size ) {
			return new WP_Error( 'upload_too_large' );
		}

		$tmp_name = (string) $tmp_name;
		clearstatcache( true, $tmp_name );
		$actual_size = wp_filesize( $tmp_name );
		if ( false === $actual_size || $actual_size <= 0 ) {
			return new WP_Error( 'upload_empty' );
		}
		if ( $actual_size > $max_size ) {
			return new WP_Error( 'upload_too_large' );
		}
		return (int) $actual_size;
	}

	private function open_uploaded_csv( $field ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Every caller verifies its action nonce before entering this upload helper.
		if ( empty( $_FILES[ $field ] ) || ! is_array( $_FILES[ $field ] ) ) {
			return new WP_Error( 'upload_missing' );
		}
		$file = $_FILES[ $field ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified by caller; file members are validated before use.
		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'upload_error' );
		}
		$size_check = $this->validate_uploaded_csv_size( $file['tmp_name'], $file['size'] ?? 0 );
		if ( is_wp_error( $size_check ) ) {
			return $size_check;
		}
		$filename = sanitize_file_name( wp_unslash( $file['name'] ?? '' ) );
		if ( 'csv' !== strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
			return new WP_Error( 'upload_extension' );
		}
		if ( ! Sidrena_Utils::uploaded_text_type_allowed( $file['tmp_name'], $filename, array( 'csv' ) ) ) {
			return new WP_Error( 'upload_mime' );
		}
		$contents = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents || '' === $contents || false !== strpos( $contents, "\0" ) ) {
			return new WP_Error( 'upload_empty' );
		}
		$contents = Sidrena_Utils::normalize_text_encoding( $contents );
		if ( '' === $contents ) {
			return new WP_Error( 'upload_encoding' );
		}
		$resource = fopen( 'php://temp/maxmemory:1048576', 'w+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $resource ) {
			return new WP_Error( 'upload_open' );
		}
		if ( ! $this->write_stream_all( $resource, $contents ) ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'upload_write' );
		}
		rewind( $resource );
		$first_line = fgets( $resource );
		if ( false === $first_line ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'upload_empty' );
		}
		$delimiter = $this->detect_delimiter( $first_line );
		rewind( $resource );
		$head = fgetcsv( $resource, 0, $delimiter );
		if ( ! $head ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'upload_header' );
		}
		$head[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $head[0] );
		$head    = array_map( array( 'Sidrena_Utils', 'import_header_key' ), $head );
		$nonempty = array_values( array_filter( $head ) );
		if ( count( $nonempty ) !== count( array_unique( $nonempty ) ) ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'upload_duplicate_headers' );
		}
		$row_count = $this->enforce_csv_row_limit( $resource, $delimiter, 50000 );
		if ( is_wp_error( $row_count ) ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return $row_count;
		}
		return array( $resource, $delimiter, array_flip( $head ) );
	}

	private function write_stream_all( $stream, $contents ) {
		$contents = (string) $contents;
		$length   = strlen( $contents );
		$offset   = 0;
		while ( $offset < $length ) {
			$written = fwrite( $stream, substr( $contents, $offset ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			if ( false === $written || 0 === $written ) {
				return false;
			}
			$offset += $written;
		}
		return true;
	}

	private function enforce_csv_row_limit( $stream, $delimiter, $row_limit = 50000 ) {
		$row_limit = min( 50000, max( 1, absint( $row_limit ) ) );
		$count     = 0;
		while ( is_resource( $stream ) ) {
			$row = fgetcsv( $stream, 0, $delimiter );
			if ( false === $row ) {
				break;
			}
			unset( $row );
			++$count;
			if ( $count > $row_limit ) {
				return new WP_Error( 'upload_row_limit', __( 'CSV ima više od dopuštenih 50.000 redaka.', 'sidrena' ) );
			}
		}

		if ( ! is_resource( $stream ) ) {
			return new WP_Error( 'upload_open' );
		}
		rewind( $stream );
		if ( false === fgetcsv( $stream, 0, $delimiter ) ) {
			return new WP_Error( 'upload_header' );
		}
		return $count;
	}

	private function resolve_aliases( $map, $aliases ) {
		foreach ( $aliases as $canonical => $names ) {
			if ( isset( $map[ $canonical ] ) ) {
				continue;
			}
			foreach ( $names as $name ) {
				$key = Sidrena_Utils::import_header_key( $name );
				if ( isset( $map[ $key ] ) ) {
					$map[ $canonical ] = $map[ $key ];
					break;
				}
			}
			if ( 'anchor_price' === $canonical && ! isset( $map[ $canonical ] ) ) {
				foreach ( $map as $header => $index ) {
					if ( 0 === strpos( (string) $header, 'sidrena_cijena_na_' ) ) {
						$map[ $canonical ] = $index;
						break;
					}
				}
			}
		}
		return $map;
	}

	public function export_missing() {
		if ( ! Sidrena_Utils::current_user_can_manage() || ! check_admin_referer( 'sidrena_export_missing' ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="sidrena-nedostajuce-sidrene-cijene.csv"' );
		$out = fopen( 'php://output', 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		$this->safe_fputcsv( $out, array( 'sku', 'naziv', 'anchor_price', 'anchor_date', 'reference_group' ), ';' );
		foreach ( $this->catalog_items() as $item ) {
			if ( '' !== get_post_meta( $item->get_id(), '_sidrena_anchor_price', true ) ) {
				continue;
			}
			$reference_group = get_post_meta( $item->get_id(), '_sidrena_reference_group', true );
			$this->safe_fputcsv( $out, array( Sidrena_Utils::get_product_code( $item ), $item->get_name(), '', '', $reference_group ? $reference_group : 'standard' ), ';' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	public function export_price_history() {
		if ( ! Sidrena_Utils::current_user_can_manage() || ! check_admin_referer( 'sidrena_export_price_history' ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}

		global $wpdb;
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="sidrena-povijest-cijena-' . esc_attr( wp_date( 'Y-m-d' ) ) . '.csv"' );
		$out = fopen( 'php://output', 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $out ) {
			wp_die( esc_html__( 'Nije moguće otvoriti izlaznu datoteku.', 'sidrena' ) );
		}
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		$this->safe_fputcsv(
			$out,
			array( 'vrsta_zapisa', 'zabiljezeno', 'lokacija', 'product_id', 'variation_id', 'service_id', 'sifra', 'naziv', 'cijena', 'redovna_cijena', 'akcijska_cijena', 'sidrena_cijena', 'dostupnost', 'izvor' ),
			';'
		);

		if ( Sidrena_Utils::is_woocommerce_edition() ) {
			$product_table = $wpdb->prefix . 'sidrena_price_history';
			$offset        = 0;
			do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Sidrena uses bounded queries against its own plugin tables.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT product_id, variation_id, price, regular_price, sale_price, recorded_at, source FROM %i ORDER BY id ASC LIMIT %d OFFSET %d',
					$product_table,
					1000,
					$offset
				),
				ARRAY_A
			);
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$item_id = absint( $row['variation_id'] ) ? absint( $row['variation_id'] ) : absint( $row['product_id'] );
				$product = Sidrena_Utils::is_woocommerce_active() ? wc_get_product( $item_id ) : false;
				$this->safe_fputcsv(
					$out,
					array(
						'woocommerce',
						$row['recorded_at'],
						'',
						absint( $row['product_id'] ),
						absint( $row['variation_id'] ),
						'',
						$product ? Sidrena_Utils::get_product_code( $product ) : '',
						$product ? $product->get_name() : '',
						$row['price'],
						$row['regular_price'],
						$row['sale_price'],
						'',
						'',
						$row['source'],
					),
					';'
				);
			}
				$count   = is_array( $rows ) ? count( $rows ) : 0;
				$offset += 1000;
			} while ( 1000 === $count );
		}

		$service_table = $wpdb->prefix . 'sidrena_service_price_history';
		$offset        = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Sidrena uses bounded queries against its own plugin tables.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT service_id, price, recorded_at, source FROM %i ORDER BY id ASC LIMIT %d OFFSET %d',
					$service_table,
					1000,
					$offset
				),
				ARRAY_A
			);
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$this->safe_fputcsv(
					$out,
					array(
						'usluga',
						$row['recorded_at'],
						'',
						'',
						'',
						absint( $row['service_id'] ),
						'',
						get_the_title( absint( $row['service_id'] ) ),
						$row['price'],
						'',
						'',
						get_post_meta( absint( $row['service_id'] ), '_sidrena_service_anchor_price', true ),
						'',
						$row['source'],
					),
					';'
				);
			}
			$count   = is_array( $rows ) ? count( $rows ) : 0;
			$offset += 1000;
		} while ( 1000 === $count );

		if ( class_exists( 'Sidrena_Location_History' ) ) {
			$location_table = Sidrena_Location_History::table_name();
			$offset         = 0;
			do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Sidrena uses bounded queries against its own plugin tables.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT location_id, product_id, variation_id, price, anchor_price, availability, recorded_at, source FROM %i ORDER BY id ASC LIMIT %d OFFSET %d',
					$location_table,
					1000,
					$offset
				),
				ARRAY_A
			);
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$item_id = absint( $row['variation_id'] ) ? absint( $row['variation_id'] ) : absint( $row['product_id'] );
				$product = Sidrena_Utils::is_woocommerce_active() ? wc_get_product( $item_id ) : false;
				$this->safe_fputcsv(
					$out,
					array(
						'lokacija',
						$row['recorded_at'],
						$row['location_id'],
						absint( $row['product_id'] ),
						absint( $row['variation_id'] ),
						'',
						$product ? Sidrena_Utils::get_product_code( $product ) : '',
						$product ? $product->get_name() : '',
						$row['price'],
						'',
						'',
						$row['anchor_price'],
						$row['availability'],
						$row['source'],
					),
					';'
				);
			}
			$count   = is_array( $rows ) ? count( $rows ) : 0;
				$offset += 1000;
			} while ( 1000 === $count );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	private function safe_fputcsv( $handle, $fields, $delimiter = ',' ) {
		$safe = array();
		foreach ( (array) $fields as $field ) {
			$safe[] = Sidrena_Utils::csv_safe_cell( $field );
		}
		return false !== fputcsv( $handle, $safe, $delimiter );
	}

	public function export_archive_index() {
		if ( ! Sidrena_Utils::current_user_can_manage() || ! check_admin_referer( 'sidrena_export_archive_index' ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="sidrena-evidencija-arhive-' . esc_attr( wp_date( 'Y-m-d' ) ) . '.csv"' );
		$out = fopen( 'php://output', 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $out ) {
			wp_die( esc_html__( 'Nije moguće otvoriti izlaznu datoteku.', 'sidrena' ) );
		}
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		$this->safe_fputcsv( $out, array( 'lokacija', 'vrsta_objekta', 'katalog', 'format', 'naziv_datoteke', 'objavljeno', 'cuvati_do', 'redaka', 'velicina_bajta', 'sha256', 'javni_url' ), ';' );

		foreach ( Sidrena_Utils::archive_index() as $entry ) {
			$this->safe_fputcsv(
				$out,
				array(
					$entry['location_code'] ?? '',
					$entry['kind'] ?? '',
					$entry['catalog'] ?? '',
					$entry['format'] ?? '',
					$entry['filename'] ?? '',
					$entry['generated_at'] ?? '',
					$entry['retain_until'] ?? '',
					(int) ( $entry['rows'] ?? 0 ),
					(int) ( $entry['bytes'] ?? 0 ),
					$entry['sha256'] ?? '',
					$entry['url'] ?? '',
				),
				';'
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	public function export_location_template() {
		if ( ! Sidrena_Utils::current_user_can_manage() || ! check_admin_referer( 'sidrena_export_location_template' ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="sidrena-lokacije-predlozak.csv"' );
		$out = fopen( 'php://output', 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		$this->safe_fputcsv( $out, array( 'location_id', 'location_code', 'product_id', 'sku', 'naziv', 'price', 'anchor_price', 'availability' ), ';' );
		foreach ( Sidrena_Utils::locations() as $location ) {
			if ( 'yes' !== ( $location['enabled'] ?? '' ) ) {
				continue;
			}
			foreach ( $this->catalog_items() as $item ) {
				$data = Sidrena_Location_Data::get_for_product( $location['id'] ?? '', $item );
				$this->safe_fputcsv( $out, array( $location['id'] ?? '', $location['code'] ?? '', $item->get_id(), Sidrena_Utils::get_product_code( $item ), $item->get_name(), $data['price'] ?? '', $data['anchor_price'] ?? '', $data['availability'] ?? '' ), ';' );
			}
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	private function service_audit_stats() {
		$stats = array(
			'services'        => 0,
			'missing_current' => 0,
			'missing_anchor'  => 0,
			'details_missing' => 0,
		);
		$page       = 1;
		$batch_size = 250;

		do {
			$query = new WP_Query(
				array(
					'post_type'      => 'sidrena_service',
					'post_status'    => 'publish',
					'posts_per_page' => $batch_size,
					'paged'          => $page,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);
			$ids = is_array( $query->posts ) ? $query->posts : array();
			foreach ( $ids as $service_id ) {
				$service_id = absint( $service_id );
				if ( ! $service_id ) {
					continue;
				}
				++$stats['services'];
				if ( '' === Sidrena_Utils::decimal( get_post_meta( $service_id, '_sidrena_service_current_price', true ) ) ) {
					++$stats['missing_current'];
				}
				if ( '' === get_post_meta( $service_id, '_sidrena_service_anchor_price', true ) ) {
					++$stats['missing_anchor'];
				}
				if ( '' === trim( (string) get_post_meta( $service_id, '_sidrena_service_type', true ) ) || '' === trim( (string) get_post_meta( $service_id, '_sidrena_service_scope', true ) ) ) {
					++$stats['details_missing'];
				}
			}
			$count = count( $ids );
			++$page;
		} while ( $count === $batch_size );

		wp_reset_postdata();
		return $stats;
	}

	private function audit_stats() {
		$products        = 0;
		$missing_current = 0;
		$missing         = 0;
		$active_sales              = 0;
		$missing_brand             = 0;
		$missing_barcode           = 0;
		$unit_price_review         = 0;
		$unit_price_missing        = 0;
		foreach ( $this->catalog_items() as $item ) {
			++$products;
			if ( '' === Sidrena_Utils::decimal( $item->get_price() ) ) {
				++$missing_current;
			}
			if ( '' === get_post_meta( $item->get_id(), '_sidrena_anchor_price', true ) ) {
				++$missing;
			}
			$brand_product = $item->is_type( 'variation' ) ? wc_get_product( $item->get_parent_id() ) : $item;
			if ( ! trim( (string) Sidrena_Utils::get_brand( $brand_product ) ) ) {
				++$missing_brand;
			}
			if ( ! trim( (string) Sidrena_Utils::get_barcode( $item ) ) ) {
				++$missing_barcode;
			}
			$unit_status = sanitize_key( (string) Sidrena_Utils::product_meta_with_parent( $item, '_sidrena_unit_price_status', 'review' ) );
			if ( ! $unit_status || 'review' === $unit_status ) {
				++$unit_price_review;
			} elseif ( 'required' === $unit_status ) {
				$unit       = trim( (string) Sidrena_Utils::product_meta_with_parent( $item, '_sidrena_unit' ) );
				$unit_price = Sidrena_Utils::decimal( Sidrena_Utils::product_meta_with_parent( $item, '_sidrena_unit_price' ) );
				if ( '' === $unit || '' === $unit_price ) {
					++$unit_price_missing;
				}
			}
			if ( $item->is_on_sale() || '' !== trim( (string) Sidrena_Utils::product_meta_with_parent( $item, '_sidrena_sale_name' ) ) ) {
				++$active_sales;
			}
		}

		if ( Sidrena_Utils::is_wordpress_edition() ) {
			$standalone                = Sidrena_Standalone::audit_stats();
			$products                  = $standalone['products'];
			$missing_current           = $standalone['missing_current'];
			$missing                   = $standalone['missing_anchor'];
			$missing_brand             = $standalone['missing_brand'];
			$missing_barcode           = $standalone['missing_barcode'];
			$unit_price_review         = $standalone['unit_price_review'];
			$unit_price_missing        = $standalone['unit_price_missing'];
			$active_sales              = $standalone['active_sales'];
		}

		$service_stats            = $this->service_audit_stats();
		$services                 = $service_stats['services'];
		$missing_service_current = $service_stats['missing_current'];
		$missing_service_anchor  = $service_stats['missing_anchor'];
		$service_details_missing = $service_stats['details_missing'];
		$settings = Sidrena_Utils::settings();
		$issues   = $missing_current + $missing_service_current + $missing + $missing_brand + $missing_barcode + $unit_price_review + $unit_price_missing + $missing_service_anchor + $service_details_missing;
		if ( 'no' === $settings['generate_csv'] && 'no' === $settings['generate_xml'] ) {
			++$issues;
		}
		if ( ! wp_next_scheduled( 'sidrena_daily_generation' ) || ! isset( $settings['generation_time'] ) || strcmp( (string) $settings['generation_time'], '08:00' ) >= 0 ) {
			++$issues;
		}
		return array(
			'products'               => $products,
			'missing_current'        => $missing_current,
			'missing_anchor'         => $missing,
			'missing_brand'          => $missing_brand,
			'missing_barcode'        => $missing_barcode,
			'unit_price_review'      => $unit_price_review,
			'unit_price_missing'     => $unit_price_missing,
			'services'               => $services,
			'missing_service_current' => $missing_service_current,
			'missing_service_anchor' => $missing_service_anchor,
			'service_details_missing' => $service_details_missing,
			'missing_current_total'  => $missing_current + $missing_service_current,
			'missing_total'          => $missing + $missing_service_anchor,
			'active_sales'           => $active_sales,
			'issues'                  => $issues,
		);
	}

	private function catalog_items() {
		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			return;
		}
		$page = 1;
		do {
			$query = new WC_Product_Query( array( 'limit' => 100, 'page' => $page, 'status' => array( 'publish' ), 'return' => 'objects', 'orderby' => 'ID', 'order' => 'ASC' ) );
			$products = $query->get_products();
			foreach ( $products as $product ) {
				if ( $product->is_type( 'variable' ) ) {
					foreach ( $product->get_children() as $variation_id ) {
						$variation = wc_get_product( $variation_id );
						if ( $variation ) {
							yield $variation;
						}
					}
					continue;
				}
				yield $product;
			}
			$product_count = count( $products );
			++$page;
		} while ( 100 === $product_count );
	}

	private function validated_import_price( $value ) {
		return Sidrena_Utils::validated_nonnegative_decimal( $value );
	}

	private function detect_delimiter( $line ) {
		$candidates = array( ';', ',', "\t" );
		$best       = ';';
		$best_count = -1;
		foreach ( $candidates as $candidate ) {
			$count = substr_count( (string) $line, $candidate );
			if ( $count > $best_count ) {
				$best       = $candidate;
				$best_count = $count;
			}
		}
		return $best;
	}

	private function post_value( $key, $fallback = '' ) {
		// Callers invoke guard_post() before reading mutable form data and apply field-specific sanitization after retrieval.
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified by the action-specific guard before this helper is called.
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $fallback;
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return $value;
	}

	private function post_checkbox( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the action-specific guard before this helper is used.
		return isset( $_POST[ $key ] );
	}

	private function post_array( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the action-specific guard before this helper is used.
		if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) {
			return array();
		}
		// Every scalar is sanitized here; domain-specific validation follows in the caller.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified by guard_post(); map_deep sanitizes every scalar after wp_unslash().
		return map_deep( wp_unslash( $_POST[ $key ] ), 'sanitize_text_field' );
	}

	public function check_public_access() {
		$this->guard_post( 'sidrena_check_public_access' );
		$entries = Sidrena_Utils::public_index();
		$paths   = Sidrena_Utils::upload_paths();
		$base    = trailingslashit( (string) $paths['base_url'] );
		$checked = 0;
		$failed  = array();

		if ( empty( $entries ) ) {
			Sidrena_Audit::log( 'public_access_check', 'warning', __( 'Nema aktualnih javnih datoteka za HTTP provjeru.', 'sidrena' ) );
			$this->redirect( 'files', 'public_access_failed' );
		}

		foreach ( $entries as $entry ) {
			$url = isset( $entry['url'] ) ? esc_url_raw( $entry['url'] ) : '';
			if ( ! $url || 0 !== strpos( $url, $base ) || ! wp_http_validate_url( $url ) ) {
				$failed[] = basename( (string) ( $entry['filename'] ?? $url ) );
				continue;
			}

			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'             => 10,
					'redirection'         => 3,
					'reject_unsafe_urls'  => true,
					'limit_response_size' => 262144,
					'headers'     => array(
						'Accept'     => 'text/csv, application/xml, text/xml, */*;q=0.1',
						'User-Agent' => 'SIDRENA-Public-Check/' . SIDRENA_VERSION,
					),
				)
			);
			++$checked;
			if ( is_wp_error( $response ) ) {
				$failed[] = basename( (string) ( $entry['filename'] ?? $url ) ) . ': ' . $response->get_error_message();
				continue;
			}

			$code = absint( wp_remote_retrieve_response_code( $response ) );
			$body = (string) wp_remote_retrieve_body( $response );
			if ( 200 !== $code || '' === trim( $body ) || 0 === stripos( ltrim( $body ), '<!doctype html' ) || 0 === stripos( ltrim( $body ), '<html' ) ) {
				$failed[] = basename( (string) ( $entry['filename'] ?? $url ) ) . ': HTTP ' . $code;
			}
		}

		if ( $failed ) {
			Sidrena_Audit::log(
				'public_access_check',
				'error',
				/* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */
				sprintf( __( 'HTTP provjera javnih cjenika nije prošla: %1$d provjereno, %2$d problema.', 'sidrena' ), $checked, count( $failed ) ),
				array( 'checked' => $checked, 'failed' => array_slice( $failed, 0, 20 ) )
			);
			$this->redirect( 'files', 'public_access_failed' );
		}

		Sidrena_Audit::log(
			'public_access_check',
			'success',
			/* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */
			sprintf( __( 'HTTP provjera javnih cjenika uspješna: %d datoteka.', 'sidrena' ), $checked ),
			array( 'checked' => $checked )
		);
		$this->redirect( 'files', 'public_access_ok' );
	}

	private function guard_post( $action ) {
		if ( ! Sidrena_Utils::current_user_can_manage() || ! check_admin_referer( $action ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}
	}

	private function date( $value, $fallback ) {
		return Sidrena_Utils::sanitize_date( $value, $fallback );
	}

	private function redirect( $tab, $notice ) {
		$pages = array(
			'dashboard'  => array( 'sidrena', '' ),
			'compliance' => array( 'sidrena', 'compliance' ),
			'catalog'    => array( 'sidrena-catalog', '' ),
			'files'      => array( 'sidrena-files', '' ),
			'archive'    => array( 'sidrena-files', 'archive' ),
			'locations'  => array( 'sidrena-locations', '' ),
			'settings'   => array( 'sidrena-settings', '' ),
			'tools'      => array( 'sidrena-support', 'tools' ),
			'log'        => array( 'sidrena-support', 'log' ),
			'rules'      => array( 'sidrena-support', 'rules' ),
		);
		$tab    = sanitize_key( $tab );
		$target = isset( $pages[ $tab ] ) ? $pages[ $tab ] : $pages['dashboard'];
		$args   = array(
			'page'       => $target[0],
			'sid_notice' => sanitize_key( $notice ),
		);
		if ( $target[1] ) {
			$args['sidrena_section'] = $target[1];
		}
		$url = add_query_arg( $args, admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}
}
