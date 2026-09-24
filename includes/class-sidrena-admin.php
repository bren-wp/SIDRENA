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

final class Sidrena_Admin {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_sidrena_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_sidrena_save_locations', array( $this, 'save_locations' ) );
		add_action( 'admin_post_sidrena_generate', array( $this, 'generate' ) );
		add_action( 'admin_post_sidrena_export_archive_index', array( $this, 'export_archive_index' ) );
		add_action( 'admin_post_sidrena_export_price_history', array( $this, 'export_price_history' ) );
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

	public function menu() {
		$capability = Sidrena_Utils::admin_menu_capability();

		add_menu_page(
			'Sidrena',
			'Sidrena',
			$capability,
			'sidrena',
			array( $this, 'page' ),
			SIDRENA_URL . 'assets/images/logo-mark.svg',
			58
		);

		$items = array(
			array( 'sidrena', __( 'Pregled', 'sidrena' ), __( 'Pregled', 'sidrena' ) ),
			array( 'sidrena-compliance', __( 'Tehnička spremnost', 'sidrena' ), __( 'Usklađenost', 'sidrena' ) ),
			array( 'sidrena-catalog', __( 'Katalog', 'sidrena' ), __( 'Katalog', 'sidrena' ) ),
			array( 'sidrena-files', __( 'Digitalni cjenici', 'sidrena' ), __( 'Cjenici', 'sidrena' ) ),
			array( 'sidrena-archive', __( 'Arhiva 30+ dana', 'sidrena' ), __( 'Arhiva 30+ dana', 'sidrena' ) ),
			array( 'sidrena-locations', __( 'Lokacije', 'sidrena' ), __( 'Lokacije', 'sidrena' ) ),
			array( 'sidrena-settings', __( 'Postavke', 'sidrena' ), __( 'Postavke', 'sidrena' ) ),
			array( 'sidrena-tools', __( 'Alati', 'sidrena' ), __( 'Alati', 'sidrena' ) ),
			array( 'sidrena-log', __( 'Dnevnik', 'sidrena' ), __( 'Dnevnik', 'sidrena' ) ),
			array( 'sidrena-rules', __( 'Propisi', 'sidrena' ), __( 'Propisi', 'sidrena' ) ),
			array( 'sidrena-support', __( 'Podrška i usluge', 'sidrena' ), __( 'Podrška', 'sidrena' ) ),
			array( 'sidrena-about', __( 'O nama', 'sidrena' ), __( 'O nama', 'sidrena' ) ),
			array( 'sidrena-help', __( 'Upute za korištenje', 'sidrena' ), __( 'Upute', 'sidrena' ) ),
		);

		foreach ( $items as $item ) {
			add_submenu_page(
				'sidrena',
				$item[1],
				$item[2],
				$capability,
				$item[0],
				array( $this, 'page' )
			);
		}
	}

	public function assets( $hook ) {
		$screen         = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_plugin_page = false !== strpos( (string) $hook, 'sidrena' );
		$is_service     = $screen && 'sidrena_service' === $screen->post_type;
		$is_product     = Sidrena_Utils::is_woocommerce_edition() && $screen && 'product' === $screen->post_type;

		if ( ! $is_plugin_page && ! $is_service && ! $is_product ) {
			return;
		}

		wp_enqueue_style( 'sidrena-admin', SIDRENA_URL . 'admin/css/admin.css', array(), SIDRENA_VERSION );
		wp_enqueue_style( 'sidrena-admin-160', SIDRENA_URL . 'admin/css/admin-160.css', array( 'sidrena-admin' ), SIDRENA_VERSION );
		if ( $is_plugin_page ) {
			wp_enqueue_script( 'sidrena-admin', SIDRENA_URL . 'admin/js/admin.js', array(), SIDRENA_VERSION, true );
			wp_localize_script(
				'sidrena-admin',
				'SidrenaAdmin',
				array(
					'removeLocation'       => __( 'Ukloniti ovu lokaciju iz konfiguracije?', 'sidrena' ),
					'keepOneLocation'      => __( 'Mora ostati barem jedna lokacija. Možete je isključiti ako je trenutačno ne želite objavljivati.', 'sidrena' ),
					'removeUnsavedProduct' => __( 'Ukloniti ovaj nespremljeni proizvod?', 'sidrena' ),
					'deleteProduct'        => __( 'Označiti ovaj proizvod za brisanje nakon spremanja?', 'sidrena' ),
				)
			);
		}
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
			'sidrena'            => 'dashboard',
			'sidrena-compliance' => 'compliance',
			'sidrena-catalog'    => 'catalog',
			'sidrena-files'      => 'files',
			'sidrena-archive'    => 'archive',
			'sidrena-locations'  => 'locations',
			'sidrena-settings'   => 'settings',
			'sidrena-tools'      => 'tools',
			'sidrena-log'        => 'log',
			'sidrena-rules'      => 'rules',
			'sidrena-support'    => 'support',
			'sidrena-about'      => 'about',
			'sidrena-help'       => 'help',
		);
		$tab = isset( $page_map[ $page ] ) ? $page_map[ $page ] : 'dashboard';

		$legacy_tabs = array( 'dashboard', 'compliance', 'catalog', 'files', 'archive', 'locations', 'settings', 'tools', 'log', 'rules', 'support', 'about', 'help' );
		if ( isset( $_GET['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$legacy_tab = sanitize_key( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( in_array( $legacy_tab, $legacy_tabs, true ) ) {
				$tab = $legacy_tab;
			}
		}

		$donation_url = Sidrena_Utils::donation_url();
		?>
		<div class="wrap sidrena-wrap sidrena-160">
			<header class="sid-brand-banner">
				<div class="sid-brand-banner__identity">
					<img class="sid-brand-banner__logo" src="<?php echo esc_url( SIDRENA_URL . 'assets/images/logo-horizontal.svg' ); ?>" alt="<?php esc_attr_e( 'Sidrena', 'sidrena' ); ?>">
					<span class="sid-brand-banner__tagline"><?php esc_html_e( 'SIDRENE CIJENE. VIŠE KONTROLE.', 'sidrena' ); ?></span>
				</div>
				<div class="sid-brand-banner__message">
					<span><?php esc_html_e( 'Transparentne cijene', 'sidrena' ); ?></span>
					<strong><?php esc_html_e( 'za sigurniju i jasniju online kupovinu.', 'sidrena' ); ?></strong>
				</div>
			</header>

			<div class="sid-toolbar">
				<div class="sid-toolbar__meta">
					<span class="sid-badge">v<?php echo esc_html( SIDRENA_VERSION ); ?></span>
					<span class="sid-mode-badge <?php echo Sidrena_Utils::is_woocommerce_edition() ? 'is-woo' : 'is-standalone'; ?>"><span class="dashicons <?php echo Sidrena_Utils::is_woocommerce_edition() ? 'dashicons-cart' : 'dashicons-wordpress-alt'; ?>"></span><?php echo esc_html( Sidrena_Utils::runtime_mode_label() ); ?></span>
					<span class="sid-toolbar__rule"><?php echo esc_html( SIDRENA_RULESET ); ?></span>
				</div>
				<div class="sid-toolbar__actions">
					<a class="sid-link-button" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-support' ) ); ?>"><span class="dashicons dashicons-sos"></span><?php esc_html_e( 'Podrška', 'sidrena' ); ?></a>
					<?php if ( $donation_url ) : ?>
						<a class="sid-link-button sid-donate-link" href="<?php echo esc_url( $donation_url ); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-heart"></span><?php esc_html_e( 'Donacija', 'sidrena' ); ?></a>
					<?php endif; ?>
				</div>
			</div>

			<?php $this->render_notice(); ?>

			<main class="sid-content">
				<?php
				switch ( $tab ) {
					case 'compliance':
						$this->compliance_tab();
						break;
					case 'catalog':
						if ( Sidrena_Utils::is_wordpress_edition() ) {
							Sidrena_Standalone::instance()->render();
						} else {
							Sidrena_Bulk::instance()->render();
						}
						break;
					case 'files':
						$this->files_tab();
						break;
					case 'archive':
						$this->archive_tab();
						break;
					case 'locations':
						$this->locations_tab();
						break;
					case 'settings':
						$this->settings_tab();
						break;
					case 'tools':
						$this->tools_tab();
						break;
					case 'log':
						$this->log_tab();
						break;
					case 'rules':
						$this->rules_tab();
						break;
					case 'support':
						$this->support_tab();
						break;
					case 'about':
						$this->about_tab();
						break;
					case 'help':
						$this->help_tab();
						break;
					default:
						$this->dashboard_tab();
				}
				?>
			</main>

			<footer class="sid-footer sid-footer-160">
				<span><?php echo esc_html( Sidrena_Utils::developer_label() ); ?></span>
				<span><a href="mailto:<?php echo esc_attr( Sidrena_Utils::support_email() ); ?>"><?php echo esc_html( Sidrena_Utils::support_email() ); ?></a> · <a href="https://brendigo.com/" target="_blank" rel="noopener noreferrer">brendigo.com</a></span>
			</footer>
		</div>
		<?php
	}

	private function render_notice() {
		if ( ! isset( $_GET['sid_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$notice = sanitize_key( wp_unslash( $_GET['sid_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$messages = array(
			'saved'                    => array( 'success', __( 'Promjene su spremljene.', 'sidrena' ) ),
			'generated'                => array( 'success', __( 'Novi cjenici su generirani i dodani u javnu arhivu.', 'sidrena' ) ),
			'generated_with_errors'    => array( 'warning', __( 'Generiranje je završeno s upozorenjima. Posljednje ispravne datoteke zadržane su kao aktualne tamo gdje nova datoteka nije mogla nastati.', 'sidrena' ) ),
			'imported'                 => array( 'success', __( 'Uvoz sidrenih cijena je dovršen.', 'sidrena' ) ),
			'location_imported'        => array( 'success', __( 'Podaci po lokacijama su uvezeni. Cjenici će koristiti unesenu raspoloživost i, gdje postoji, cijenu po lokaciji.', 'sidrena' ) ),
			'import_failed'            => array( 'error', __( 'CSV nije moguće uvesti. Provjerite format, veličinu, zaglavlja i podatke.', 'sidrena' ) ),
			'location_import_failed'   => array( 'error', __( 'CSV lokacija nije moguće uvesti. Provjerite location_id, product_id/SKU i stupac availability.', 'sidrena' ) ),
			'public_page_created'      => array( 'success', __( 'Javna stranica Objava cjenika je izrađena i objavljena.', 'sidrena' ) ),
			'public_page_exists'       => array( 'success', __( 'Javna stranica Objava cjenika već postoji.', 'sidrena' ) ),
			'public_page_failed'       => array( 'error', __( 'Javnu stranicu nije bilo moguće izraditi. Provjerite ovlasti i WordPress zapisnik.', 'sidrena' ) ),
			'bulk_saved'                  => array( 'success', __( 'Katalog je spremljen, a ponovno generiranje cjenika stavljeno je u red.', 'sidrena' ) ),
			'locations_required'           => array( 'error', __( 'Mora postojati barem jedna lokacija. Ako je trenutačno ne želite objavljivati, ostavite je spremljenu i isključite opciju Aktivna.', 'sidrena' ) ),
			'locations_invalid'            => array( 'error', __( 'Lokacije nisu spremljene. Aktivna lokacija mora imati jedinstveni ID, vrstu objekta, oznaku i adresu.', 'sidrena' ) ),
			'settings_saved_cron_warning'  => array( 'warning', __( 'Postavke su spremljene, ali WordPress nije uspio ponovno zakazati dnevno generiranje. Provjerite WP-Cron ili konfigurirajte server cron.', 'sidrena' ) ),
			'standalone_imported'          => array( 'success', __( 'Uvoz WordPress kataloga je dovršen.', 'sidrena' ) ),
			'standalone_import_failed'     => array( 'error', __( 'WordPress katalog nije moguće uvesti. Provjerite CSV/XML format, veličinu, zaglavlja i obvezne podatke.', 'sidrena' ) ),
			'standalone_saved_with_errors' => array( 'warning', __( 'Katalog je djelomično spremljen. Neke stavke nije bilo moguće zapisati; provjerite Dnevnik i pokušajte ponovno.', 'sidrena' ) ),
			'standalone_sync_started'     => array( 'success', __( 'Sinkronizacija postojećeg WordPress sadržaja je pokrenuta u pozadini. Status i rezultat prikazuju se u Katalogu.', 'sidrena' ) ),
			'standalone_sync_failed'      => array( 'error', __( 'Sinkronizaciju nije bilo moguće pokrenuti. Provjerite odabrani tip sadržaja i WordPress cron.', 'sidrena' ) ),
			'public_access_ok'              => array( 'success', __( 'Provjera javne dostupnosti je uspješna. Aktualne javne datoteke odgovorile su valjanim HTTP odgovorom.', 'sidrena' ) ),
			'public_access_failed'          => array( 'error', __( 'Jedna ili više javnih datoteka nisu prošle HTTP provjeru. Otvorite Dnevnik za detalje i provjerite cache, CDN, firewall ili pravila pristupa.', 'sidrena' ) ),
		);
		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}
		$type = $messages[ $notice ][0];
		$text = $messages[ $notice ][1];
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
	}


	private function support_tab() {
		$pdf_url         = Sidrena_Utils::support_pdf_url();
		$email_url       = Sidrena_Utils::support_email_url();
		$whatsapp_url    = Sidrena_Utils::whatsapp_url();
		$install_url     = Sidrena_Utils::installation_service_url();
		$donation_url    = Sidrena_Utils::donation_url();
		?>
		<div class="sid-page-head">
			<div>
				<span class="sid-kicker"><?php echo esc_html( Sidrena_Utils::developer_label() ); ?></span>
				<h2><?php esc_html_e( 'Podrška i instalacija', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Za tehničku pomoć koristite e-mail ili WhatsApp. Po želji možete naručiti jednokratnu instalaciju i početno postavljanje.', 'sidrena' ); ?></p>
			</div>
		</div>

		<section class="sid-card sid-note">
			<div class="sid-note-icon"><span class="dashicons dashicons-admin-tools"></span></div>
			<div><h2><?php esc_html_e( 'Jednokratna instalacija i početno postavljanje', 'sidrena' ); ?></h2><p><?php echo esc_html( sprintf( __( 'Ako želite da Brendigo instalira i početno postavi plugin, cijena usluge je %s jednokratno.', 'sidrena' ), Sidrena_Utils::installation_price() ) ); ?></p></div>
		</section>

		<div class="sid-grid sid-grid-2">
			<section class="sid-card sid-tool-card">
				<div class="sid-tool-icon"><span class="dashicons dashicons-pdf"></span></div>
				<h2><?php esc_html_e( 'PDF podrška', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'PDF s kontaktima i informacijama o instalaciji uključen je u instalacijski paket.', 'sidrena' ); ?></p>
				<a class="button button-primary sid-primary" href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Otvori PDF', 'sidrena' ); ?></a>
			</section>

			<section class="sid-card sid-tool-card">
				<div class="sid-tool-icon"><span class="dashicons dashicons-email-alt"></span></div>
				<h2><?php esc_html_e( 'E-mail podrška', 'sidrena' ); ?></h2>
				<p><strong><?php echo esc_html( Sidrena_Utils::support_email() ); ?></strong></p>
				<a class="button sid-secondary" href="<?php echo esc_url( $email_url ); ?>"><?php esc_html_e( 'Pošalji e-mail', 'sidrena' ); ?></a>
			</section>

			<section class="sid-card sid-tool-card">
				<div class="sid-tool-icon"><span class="dashicons dashicons-format-chat"></span></div>
				<h2><?php esc_html_e( 'WhatsApp podrška', 'sidrena' ); ?></h2>
				<p><?php echo esc_html( Sidrena_Utils::whatsapp_number() ); ?></p>
				<a class="button sid-secondary" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Otvori WhatsApp', 'sidrena' ); ?></a>
			</section>

			<section class="sid-card sid-tool-card">
				<div class="sid-tool-icon"><span class="dashicons dashicons-admin-tools"></span></div>
				<h2><?php esc_html_e( 'Jednokratno početno postavljanje', 'sidrena' ); ?></h2>
				<p><?php echo esc_html( sprintf( __( 'Samo ako želite da Brendigo odradi instalaciju i početno postavljanje: %s jednokratno.', 'sidrena' ), Sidrena_Utils::installation_price() ) ); ?></p>
				<a class="button button-primary sid-primary" href="<?php echo esc_url( $install_url ); ?>"><?php echo esc_html( sprintf( __( 'Zatraži postavljanje - %s', 'sidrena' ), Sidrena_Utils::installation_price() ) ); ?></a>
			</section>


		</div>

		<?php if ( $donation_url ) : ?>
		<section class="sid-card sid-note">
			<div class="sid-note-icon"><span class="dashicons dashicons-heart"></span></div>
			<div>
				<h2><?php esc_html_e( 'Dobrovoljna donacija za razvoj', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Donacija je dobrovoljna i otvara se izravno na Revolutu.', 'sidrena' ); ?></p>
				<a class="button sid-support-button" href="<?php echo esc_url( $donation_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Otvori Revolut', 'sidrena' ); ?></a>
			</div>
		</section>
		<?php endif; ?>

		<?php
	}
	private function about_tab() {
		?>
		<div class="sid-page-head">
			<div>
				<span class="sid-kicker"><?php esc_html_e( 'O nama', 'sidrena' ); ?></span>
				<h2><?php echo esc_html( Sidrena_Utils::developer_label() ); ?></h2>
				<p><?php esc_html_e( 'Razvoj, održavanje i podrška za Sidrena WordPress i Sidrena WooCommerce izdanje.', 'sidrena' ); ?></p>
			</div>
			<a class="button sid-secondary" href="https://brendigo.com/" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-external"></span>brendigo.com</a>
		</div>
		<div class="sid-grid sid-grid-2">
			<section class="sid-card sid-contact-card">
				<span class="sid-kicker"><?php esc_html_e( 'Autor', 'sidrena' ); ?></span>
				<h2><?php echo esc_html( Sidrena_Utils::developer_label() ); ?></h2>
				<p><?php esc_html_e( 'Sidrena je razvijena kao WordPress rješenje za upravljanje sidrenim/referentnim cijenama, javnim cjenicima i arhivom objava.', 'sidrena' ); ?></p>
			</section>
			<section class="sid-card sid-contact-card">
				<span class="sid-kicker"><?php esc_html_e( 'Kontakt', 'sidrena' ); ?></span>
				<h2><?php echo esc_html( Sidrena_Utils::support_email() ); ?></h2>
				<p><?php echo esc_html( Sidrena_Utils::whatsapp_number() ); ?></p>
				<a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-support' ) ); ?>"><?php esc_html_e( 'Otvori Podršku', 'sidrena' ); ?></a>
			</section>
		</div>
		<section class="sid-card sid-note">
			<div class="sid-note-icon"><span class="dashicons dashicons-info-outline"></span></div>
			<div><h2><?php esc_html_e( 'Dva odvojena izdanja', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Sidrena WordPress namijenjena je web stranicama bez WooCommercea, a Sidrena WooCommerce trgovinama koje koriste WooCommerce. Istodobno može biti aktivno samo jedno izdanje.', 'sidrena' ); ?></p></div>
		</section>
		<?php
	}


	private function help_tab() {
		$woo = Sidrena_Utils::is_woocommerce_edition();
		?>
		<div class="sid-page-head">
			<div>
				<span class="sid-kicker"><?php esc_html_e( 'Dokumentacija', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Upute za korištenje Sidrene', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Praktičan redoslijed od instalacije do provjere javnih cjenika za aktivno Sidrena izdanje.', 'sidrena' ); ?></p>
			</div>
			<div class="sid-head-inline-actions"><a class="button sid-secondary" href="<?php echo esc_url( Sidrena_Utils::support_pdf_url() ); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-pdf"></span><?php esc_html_e( 'PDF upute', 'sidrena' ); ?></a><a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-support' ) ); ?>"><?php esc_html_e( 'Podrška', 'sidrena' ); ?></a></div>
		</div>

		<div class="sid-grid sid-grid-2">
			<section class="sid-card">
				<span class="sid-kicker"><?php esc_html_e( '1. Početno postavljanje', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Odaberite način rada i lokacije', 'sidrena' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'U Postavkama odaberite proizvode, usluge ili mješoviti način rada.', 'sidrena' ); ?></li>
					<li><?php esc_html_e( 'U Lokacijama unesite svaki prodajni/uslužni objekt i zaseban webshop ako ga koristite.', 'sidrena' ); ?></li>
					<li><?php esc_html_e( 'Provjerite referentne datume, format CSV-a, arhivu i vrijeme automatskog generiranja.', 'sidrena' ); ?></li>
				</ol>
				<a class="sid-inline-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-settings' ) ); ?>"><?php esc_html_e( 'Otvori Postavke', 'sidrena' ); ?></a>
			</section>

			<section class="sid-card">
				<span class="sid-kicker"><?php esc_html_e( '2. Katalog', 'sidrena' ); ?></span>
				<h2><?php echo $woo ? esc_html__( 'WooCommerce katalog', 'sidrena' ) : esc_html__( 'WordPress katalog', 'sidrena' ); ?></h2>
				<p><?php echo $woo ? esc_html__( 'Sidrena WooCommerce koristi WooCommerce proizvode i varijacije kao izvor proizvoda.', 'sidrena' ) : esc_html__( 'Sidrena WordPress koristi vlastiti katalog proizvoda koji možete unositi ručno ili uvesti CSV/XML datotekom.', 'sidrena' ); ?></p>
				<p><?php esc_html_e( 'Za jediničnu cijenu prvo označite primjenjivost. Kada je obvezna, količina pakiranja i jedinica mogu poslužiti za automatski izračun ako iznos nije ručno unesen.', 'sidrena' ); ?></p>
				<a class="sid-inline-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-catalog' ) ); ?>"><?php esc_html_e( 'Otvori Katalog', 'sidrena' ); ?></a>
			</section>

			<section class="sid-card">
				<span class="sid-kicker"><?php esc_html_e( '3. Usluge', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Cijena, vrsta, opseg i troškovi', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Kod usluga unesite aktualnu i sidrenu cijenu te, gdje je relevantno, vrstu i opseg usluge, pripadajuće troškove i ugradbenu ili zamjensku robu.', 'sidrena' ); ?></p>
				<p><?php esc_html_e( 'Ne koristite tekst poput “po dogovoru” kao zamjenu za numeričku maloprodajnu cijenu bez prethodne provjere primjenjivih pravila za konkretan slučaj.', 'sidrena' ); ?></p>
			</section>

			<section class="sid-card">
				<span class="sid-kicker"><?php esc_html_e( '4. Uvoz i lokacije', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Masovne izmjene bez ručnog otvaranja svake stavke', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Alati prihvaćaju UTF-8 te pokušavaju normalizirati Windows-1250 i ISO-8859-2. Hrvatska zaglavlja, decimalni zarez i tipični zapisi količine podržani su u uvozu.', 'sidrena' ); ?></p>
				<a class="sid-inline-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-tools' ) ); ?>"><?php esc_html_e( 'Otvori Alate', 'sidrena' ); ?></a>
			</section>

			<section class="sid-card">
				<span class="sid-kicker"><?php esc_html_e( '5. Objavljivanje', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Generirajte i provjerite cjenike', 'sidrena' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'Otvorite Usklađenost i riješite tehnička upozorenja.', 'sidrena' ); ?></li>
					<li><?php esc_html_e( 'U Cjenicima kliknite Generiraj sada.', 'sidrena' ); ?></li>
					<li><?php esc_html_e( 'Kliknite Provjeri javnu dostupnost i zatim otvorite svaku aktualnu datoteku.', 'sidrena' ); ?></li>
					<li><?php esc_html_e( 'Provjerite Arhivu 30+ dana i Dnevnik.', 'sidrena' ); ?></li>
				</ol>
				<a class="sid-inline-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-files' ) ); ?>"><?php esc_html_e( 'Otvori Cjenike', 'sidrena' ); ?></a>
			</section>

			<section class="sid-card">
				<span class="sid-kicker"><?php esc_html_e( '6. Automatizacija', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Cron, WP-CLI i dijagnostika', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Za poslovno kritične rokove oslonite se na pouzdani server cron koji pokreće WordPress cron ili na WP-CLI automatizaciju. Site Health prikazuje Sidrena raspored i stanje arhive.', 'sidrena' ); ?></p>
				<code>wp sidrena generate</code><br><code>wp sidrena status</code><br><code>wp sidrena audit</code>
			</section>
		</div>

		<section class="sid-card sid-note">
			<div class="sid-note-icon"><span class="dashicons dashicons-book-alt"></span></div>
			<div>
				<h2><?php esc_html_e( 'Važno prije produkcije', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Sidrena je tehnički alat. Povijesne i referentne cijene moraju dolaziti iz stvarne poslovne evidencije. Nakon svake veće promjene kataloga provjerite javne CSV/XML datoteke, HTML prikaz, arhivu i dnevnik.', 'sidrena' ); ?></p>
			</div>
		</section>
		<?php
	}

	private function dashboard_tab() {
		$stats           = $this->audit_stats();
		$last            = get_option( 'sidrena_last_run', array() );
		$settings        = Sidrena_Utils::settings();
		$archive_stats   = Sidrena_Utils::archive_stats();
		$integrity       = Sidrena_Utils::archive_integrity();
		$retention       = Sidrena_Utils::archive_retention_status();
		$next_run        = wp_next_scheduled( 'sidrena_daily_generation' );
		$product_history = class_exists( 'Sidrena_History' ) ? Sidrena_History::count_rows() : 0;
		$service_history = Sidrena_Service_History::count_rows();
		$location_history = class_exists( 'Sidrena_Location_History' ) ? Sidrena_Location_History::count_rows() : 0;
		$history_total   = $product_history + $service_history + $location_history;
		$anchor_ready    = max( 0, ( $stats['products'] + $stats['services'] ) - $stats['missing_total'] );
		$series          = class_exists( 'Sidrena_History' ) ? Sidrena_History::latest_series( 30 ) : array();
		if ( empty( $series ) ) {
			$series = Sidrena_Service_History::latest_series( 30 );
		}
		$product_changes = class_exists( 'Sidrena_History' ) ? Sidrena_History::recent_changes( 5 ) : array();
		$changes         = array_merge( $product_changes, Sidrena_Service_History::recent_changes( 5 ) );
		usort(
			$changes,
			static function ( $a, $b ) {
				return strcmp( (string) $b['recorded_at'], (string) $a['recorded_at'] );
			}
		);
		$changes = array_slice( $changes, 0, 5 );
		$is_ready        = 0 === $stats['issues'] && $integrity['ok'];
		?>
		<div class="sid-dashboard-metrics">
			<?php $this->dashboard_metric( __( 'Sidrene cijene', 'sidrena' ), $anchor_ready, 'dashicons-tag', __( 'popunjene stavke', 'sidrena' ), 'blue' ); ?>
			<?php $this->dashboard_metric( __( 'Digitalni cjenici', 'sidrena' ), $integrity['current_entries'], 'dashicons-media-spreadsheet', __( 'trenutačno važeće datoteke', 'sidrena' ), 'blue' ); ?>
			<?php $this->dashboard_metric( __( 'Povijest cijena', 'sidrena' ), $history_total, 'dashicons-chart-line', __( 'evidentirani zapisi', 'sidrena' ), 'teal' ); ?>
			<?php $this->dashboard_metric( __( 'Arhiva 30+ dana', 'sidrena' ), $archive_stats['files'], 'dashicons-calendar-alt', sprintf( __( '%d dana politike čuvanja', 'sidrena' ), $settings['retention_days'] ), 'teal' ); ?>
			<?php $this->dashboard_metric( __( 'Tehnička spremnost', 'sidrena' ), $is_ready ? __( 'Spremno', 'sidrena' ) : __( 'Provjera', 'sidrena' ), 'dashicons-shield-alt', $is_ready ? __( 'bez tehničkih upozorenja', 'sidrena' ) : sprintf( _n( '%d stavka za provjeru', '%d stavki za provjeru', $stats['issues'], 'sidrena' ), $stats['issues'] ), $is_ready ? 'ok' : 'warn' ); ?>
		</div>

		<div class="sid-dashboard-actions">
			<a class="sid-action sid-action--primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_generate' ), 'sidrena_generate' ) ); ?>"><span class="dashicons dashicons-media-spreadsheet"></span><strong><?php esc_html_e( 'Generiraj cjenik', 'sidrena' ); ?></strong><span class="dashicons dashicons-arrow-right-alt2"></span></a>
			<a class="sid-action sid-action--soft-blue" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-archive' ) ); ?>"><span class="dashicons dashicons-calendar-alt"></span><strong><?php esc_html_e( 'Pregledaj arhivu 30+ dana', 'sidrena' ); ?></strong></a>
			<a class="sid-action sid-action--soft-orange" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-files' ) ); ?>"><span class="dashicons dashicons-download"></span><strong><?php esc_html_e( 'CSV/XML i javni cjenici', 'sidrena' ); ?></strong></a>
			<a class="sid-action sid-action--soft-purple" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-compliance' ) ); ?>"><span class="dashicons dashicons-search"></span><strong><?php esc_html_e( 'Provjeri tehničku spremnost', 'sidrena' ); ?></strong></a>
		</div>

		<div class="sid-dashboard-main">
			<section class="sid-card sid-price-card">
				<div class="sid-section-head">
					<div><span class="sid-kicker"><?php esc_html_e( 'Povijest', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Kretanje cijena', 'sidrena' ); ?></h2></div>
					<span class="sid-status-pill is-ok"><?php esc_html_e( 'Zadnjih 30 dana', 'sidrena' ); ?></span>
				</div>
				<?php $this->render_price_chart( $series ); ?>
			</section>

			<section class="sid-card sid-readiness-card">
				<div class="sid-section-head">
					<div><span class="sid-kicker"><?php esc_html_e( 'Kontrola', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Status tehničke spremnosti', 'sidrena' ); ?></h2></div>
				</div>
				<div class="sid-readiness-summary <?php echo $is_ready ? 'is-ok' : 'is-warn'; ?>">
					<span class="dashicons <?php echo $is_ready ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
					<div><strong><?php echo $is_ready ? esc_html__( 'Bez tehničkih upozorenja', 'sidrena' ) : esc_html__( 'Potrebna je provjera podataka', 'sidrena' ); ?></strong><small><?php esc_html_e( 'Status je tehnička provjera, ne pravna potvrda usklađenosti.', 'sidrena' ); ?></small></div>
				</div>
				<?php $this->health_list( $stats, $last, $settings ); ?>
				<a class="sid-inline-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-compliance' ) ); ?>"><?php esc_html_e( 'Otvori detaljnu provjeru', 'sidrena' ); ?><span class="dashicons dashicons-arrow-right-alt2"></span></a>
			</section>
		</div>

		<div class="sid-dashboard-bottom">
			<section class="sid-card sid-changes-card">
				<div class="sid-section-head"><div><span class="sid-kicker"><?php esc_html_e( 'Sljedivost', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Najnovije promjene cijena', 'sidrena' ); ?></h2></div><a class="sid-inline-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-log' ) ); ?>"><?php esc_html_e( 'Dnevnik', 'sidrena' ); ?><span class="dashicons dashicons-arrow-right-alt2"></span></a></div>
				<?php $this->recent_changes_table( $changes ); ?>
			</section>

			<section class="sid-card sid-archive-summary">
				<div class="sid-section-head"><div><span class="sid-kicker"><?php esc_html_e( 'Javna arhiva', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Arhiva 30+ dana', 'sidrena' ); ?></h2></div><a class="sid-inline-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-archive' ) ); ?>"><?php esc_html_e( 'Pregledaj arhivu', 'sidrena' ); ?><span class="dashicons dashicons-arrow-right-alt2"></span></a></div>
				<div class="sid-archive-count"><span class="dashicons dashicons-calendar-alt"></span><div><strong><?php echo esc_html( number_format_i18n( $archive_stats['files'] ) ); ?></strong><small><?php esc_html_e( 'arhiviranih CSV/XML objava', 'sidrena' ); ?></small></div></div>
				<div class="sid-progress"><span class="<?php echo $settings['retention_days'] >= 30 ? 'is-ok' : 'is-warn'; ?>"></span></div>
				<ul class="sid-check-list">
					<li><span class="dashicons dashicons-yes-alt"></span><?php echo esc_html( sprintf( __( 'Čuvanje: %d dana', 'sidrena' ), $settings['retention_days'] ) ); ?></li>
					<li><span class="dashicons <?php echo $integrity['ok'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span><?php echo $integrity['ok'] ? esc_html__( 'SHA-256 integritet uredan', 'sidrena' ) : esc_html__( 'Integritet zahtijeva provjeru', 'sidrena' ); ?></li>
					<li><span class="dashicons <?php echo $next_run ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span><?php echo $next_run ? esc_html( sprintf( __( 'Sljedeća objava: %s', 'sidrena' ), wp_date( 'd.m. H:i', $next_run ) ) ) : esc_html__( 'Dnevni raspored nije aktivan', 'sidrena' ); ?></li>
				</ul>
				<p class="description"><?php echo esc_html( sprintf( __( 'Operativna rezerva iznad minimuma: +%d dana.', 'sidrena' ), $retention['buffer_days'] ) ); ?></p>
			</section>
		</div>

		<section class="sid-card sid-note sid-production-note">
			<div class="sid-note-icon"><span class="dashicons dashicons-info-outline"></span></div>
			<div><h2><?php esc_html_e( 'Povijesni podaci moraju biti stvarni', 'sidrena' ); ?></h2>
			<p><?php esc_html_e( 'Sidrena ne rekonstruira niti izmišlja cijene prije instalacije. Kada nema potpune i vjerodostojne povijesti, podatak se označava za provjeru i može se unijeti samo iz pouzdane poslovne evidencije.', 'sidrena' ); ?></p></div>
		</section>
		<?php
	}


	private function dashboard_metric( $label, $value, $icon, $caption, $tone = 'blue' ) {
		?>
		<section class="sid-dashboard-metric sid-dashboard-metric--<?php echo esc_attr( $tone ); ?>">
			<span class="sid-dashboard-metric__icon dashicons <?php echo esc_attr( $icon ); ?>"></span>
			<div><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( is_numeric( $value ) ? number_format_i18n( $value ) : $value ); ?></strong><small><?php echo esc_html( $caption ); ?></small></div>
		</section>
		<?php
	}

	private function render_price_chart( $series ) {
		if ( empty( $series['points'] ) ) {
			?>
			<div class="sid-chart-empty">
				<span class="dashicons dashicons-chart-line"></span>
				<strong><?php esc_html_e( 'Povijest cijena još nema dovoljno podataka za graf.', 'sidrena' ); ?></strong>
				<p><?php esc_html_e( 'Sidrena će ovdje prikazati stvarno kretanje cijene nakon što zabilježi promjene ili dnevne snapshotove.', 'sidrena' ); ?></p>
			</div>
			<?php
			return;
		}

		$points = $series['points'];
		$prices = wp_list_pluck( $points, 'price' );
		$min    = (float) min( $prices );
		$max    = (float) max( $prices );
		if ( abs( $max - $min ) < 0.000001 ) {
			$max += 1;
			$min = max( 0, $min - 1 );
		}
		$count  = count( $points );
		$coords = array();
		foreach ( $points as $index => $point ) {
			$x = 24 + ( ( $count > 1 ? $index / ( $count - 1 ) : 0.5 ) * 672 );
			$y = 148 - ( ( ( (float) $point['price'] - $min ) / ( $max - $min ) ) * 112 );
			$coords[] = round( $x, 1 ) . ',' . round( $y, 1 );
		}
		$line_points = implode( ' ', $coords );
		$area_points = '24,160 ' . $line_points . ' 696,160';
		$change       = (float) $series['change_pct'];
		?>
		<div class="sid-price-summary">
			<div><span><?php esc_html_e( 'Trenutna cijena', 'sidrena' ); ?></span><strong><?php echo esc_html( Sidrena_Utils::money( $series['current'] ) . ' €' ); ?></strong></div>
			<div><span><?php esc_html_e( 'Najviša cijena', 'sidrena' ); ?></span><strong><?php echo esc_html( Sidrena_Utils::money( $series['maximum'] ) . ' €' ); ?></strong></div>
			<div><span><?php esc_html_e( 'Najniža cijena', 'sidrena' ); ?></span><strong><?php echo esc_html( Sidrena_Utils::money( $series['minimum'] ) . ' €' ); ?></strong></div>
			<div class="<?php echo $change <= 0 ? 'is-good' : 'is-neutral'; ?>"><span><?php esc_html_e( 'Promjena', 'sidrena' ); ?></span><strong><?php echo esc_html( ( $change > 0 ? '+' : '' ) . number_format_i18n( $change, 1 ) . '%' ); ?></strong></div>
		</div>
		<div class="sid-chart-title"><?php echo esc_html( $series['name'] ); ?></div>
		<svg class="sid-price-chart" viewBox="0 0 720 176" role="img" aria-label="<?php esc_attr_e( 'Graf kretanja cijene u posljednjih 30 dana', 'sidrena' ); ?>" preserveAspectRatio="none">
			<line class="sid-chart-grid" x1="24" y1="36" x2="696" y2="36"></line>
			<line class="sid-chart-grid" x1="24" y1="92" x2="696" y2="92"></line>
			<line class="sid-chart-grid" x1="24" y1="148" x2="696" y2="148"></line>
			<polygon class="sid-chart-area" points="<?php echo esc_attr( $area_points ); ?>"></polygon>
			<polyline class="sid-chart-line" points="<?php echo esc_attr( $line_points ); ?>"></polyline>
			<?php foreach ( $coords as $coord ) : $xy = explode( ',', $coord ); ?>
				<circle class="sid-chart-point" cx="<?php echo esc_attr( $xy[0] ); ?>" cy="<?php echo esc_attr( $xy[1] ); ?>" r="3.5"></circle>
			<?php endforeach; ?>
		</svg>
		<?php $last_point = end( $points ); ?>
		<div class="sid-chart-axis"><span><?php echo esc_html( wp_date( 'd.m.', strtotime( $points[0]['recorded_at'] ) ) ); ?></span><span><?php esc_html_e( '30 dana', 'sidrena' ); ?></span><span><?php echo esc_html( wp_date( 'd.m.', strtotime( $last_point['recorded_at'] ) ) ); ?></span></div>
		<?php
	}

	private function recent_changes_table( $changes ) {
		if ( empty( $changes ) ) {
			echo '<div class="sid-table-empty">' . esc_html__( 'Nema zabilježenih promjena cijena. Prikazat će se nakon prve stvarne promjene.', 'sidrena' ) . '</div>';
			return;
		}
		?>
		<div class="sid-table-scroll">
			<table class="sid-modern-table">
				<thead><tr><th><?php esc_html_e( 'Proizvod', 'sidrena' ); ?></th><th><?php esc_html_e( 'Stara cijena', 'sidrena' ); ?></th><th><?php esc_html_e( 'Nova cijena', 'sidrena' ); ?></th><th><?php esc_html_e( 'Promjena', 'sidrena' ); ?></th><th><?php esc_html_e( 'Datum', 'sidrena' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $changes as $change ) : $pct = (float) $change['change_pct']; ?>
					<tr>
						<td><strong><?php echo esc_html( $change['name'] ); ?></strong></td>
						<td><?php echo esc_html( Sidrena_Utils::money( $change['old_price'] ) . ' €' ); ?></td>
						<td><strong><?php echo esc_html( Sidrena_Utils::money( $change['new_price'] ) . ' €' ); ?></strong></td>
						<td><span class="sid-change-pill <?php echo $pct <= 0 ? 'is-down' : 'is-up'; ?>"><?php echo esc_html( ( $pct > 0 ? '+' : '' ) . number_format_i18n( $pct, 1 ) . '%' ); ?></span></td>
						<td><?php echo esc_html( Sidrena_Utils::format_mysql_datetime( $change['recorded_at'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function support_card() {
		$donation_url = Sidrena_Utils::donation_url();
		?>
		<section class="sid-card sid-support-card">
			<div class="sid-support-card__icon"><span class="dashicons dashicons-sos"></span></div>
			<div>
				<span class="sid-kicker"><?php esc_html_e( 'Podrška po izboru korisnika', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Plugin možete postaviti sami ili angažirati Brendigo', 'sidrena' ); ?></h2>
				<p><?php echo esc_html( sprintf( __( 'Korištenje plugina nije uvjetovano kupnjom usluge. Ako želite da Brendigo odradi instalaciju i početno postavljanje, cijena je %s jednokratno.', 'sidrena' ), Sidrena_Utils::installation_price() ) ); ?></p>
				<div class="sid-head-inline-actions">
					<a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-support' ) ); ?>"><?php esc_html_e( 'Otvori podršku', 'sidrena' ); ?></a>
					<?php if ( $donation_url ) : ?><a class="button sid-support-button" href="<?php echo esc_url( $donation_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Dobrovoljna donacija', 'sidrena' ); ?></a><?php endif; ?>
				</div>
			</div>
		</section>
		<?php
	}
	private function metric_card( $label, $value, $icon, $caption, $state = '' ) {
		?>
		<section class="sid-card sid-metric <?php echo $state ? 'is-' . esc_attr( $state ) : ''; ?>">
			<div class="sid-metric-top"><span><?php echo esc_html( $label ); ?></span><i class="dashicons <?php echo esc_attr( $icon ); ?>"></i></div>
			<strong><?php echo esc_html( $value ); ?></strong>
			<small><?php echo esc_html( $caption ); ?></small>
		</section>
		<?php
	}

	private function compliance_tab() {
		$stats          = $this->audit_stats();
		$settings       = Sidrena_Utils::settings();
		$last           = get_option( 'sidrena_last_run', array() );
		$file_coverage  = $this->public_file_coverage();
		$archive_stats  = Sidrena_Utils::archive_stats();
		$integrity      = Sidrena_Utils::archive_integrity();
		$product_hist   = class_exists( 'Sidrena_History' ) ? Sidrena_History::count_rows() : 0;
		$service_hist   = Sidrena_Service_History::count_rows();
		$location_hist  = class_exists( 'Sidrena_Location_History' ) ? Sidrena_Location_History::count_rows() : 0;
		$total_items    = $stats['products'] + $stats['services'];
		$anchor_ready   = max( 0, $total_items - $stats['missing_total'] );
		$sales_total    = $stats['active_sales'] + $this->active_service_sales();
		$sales_pending  = $stats['sale_incomplete'] + $stats['service_sale_incomplete'];
		$sales_ready    = max( 0, $sales_total - $sales_pending );
		?>
		<div class="sid-page-head">
			<div>
				<span class="sid-kicker"><?php esc_html_e( 'Tehnička kontrola podataka', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Centar usklađenosti', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Pregledava podatke i tehničke preduvjete koje Sidrena može provjeriti. Ovo nije pravna ocjena poslovanja niti zamjena za stručnu provjeru primjenjivih obveza.', 'sidrena' ); ?></p>
			</div>
			<a class="button button-primary sid-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_generate' ), 'sidrena_generate' ) ); ?>"><span class="dashicons dashicons-update"></span><?php esc_html_e( 'Osvježi cjenike', 'sidrena' ); ?></a>
		</div>

		<div class="sid-grid sid-grid-4">
			<?php $this->metric_card( __( 'Sidrene cijene', 'sidrena' ), $anchor_ready . '/' . $total_items, 'dashicons-tag', $stats['missing_total'] ? sprintf( __( '%d stavki traži provjeru', 'sidrena' ), $stats['missing_total'] ) : __( 'sve evidentirane', 'sidrena' ), $stats['missing_total'] ? 'warn' : 'ok' ); ?>
			<?php $this->metric_card( __( 'Aktivna sniženja', 'sidrena' ), $sales_ready . '/' . $sales_total, 'dashicons-chart-line', $sales_pending ? sprintf( __( '%d bez pune reference', 'sidrena' ), $sales_pending ) : __( 'bez otvorenih upozorenja', 'sidrena' ), $sales_pending ? 'warn' : 'ok' ); ?>
			<?php $this->metric_card( __( 'Aktualni cjenici', 'sidrena' ), $file_coverage['ready'] . '/' . $file_coverage['expected'], 'dashicons-media-spreadsheet', $file_coverage['missing'] ? sprintf( __( '%d kombinacija nedostaje', 'sidrena' ), $file_coverage['missing'] ) : __( 'očekivane datoteke postoje', 'sidrena' ), $file_coverage['missing'] ? 'warn' : 'ok' ); ?>
			<?php $this->metric_card( __( 'Javna arhiva', 'sidrena' ), $archive_stats['distinct_days'] . ' d', 'dashicons-backup', sprintf( __( '%d indeksiranih datoteka', 'sidrena' ), $archive_stats['files'] ), $integrity['ok'] ? 'ok' : 'warn' ); ?>
		</div>

		<div class="sid-grid sid-grid-2 sid-grid-main">
			<section class="sid-card">
				<div class="sid-section-head">
					<div><span class="sid-kicker"><?php esc_html_e( 'Automatske provjere', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Kontrolna lista spremnosti', 'sidrena' ); ?></h2></div>
					<span class="sid-status-pill <?php echo 0 === $stats['issues'] && 0 === $file_coverage['missing'] && $integrity['ok'] ? 'is-ok' : 'is-warn'; ?>"><?php echo 0 === $stats['issues'] && 0 === $file_coverage['missing'] && $integrity['ok'] ? esc_html__( 'Nema tehničkih upozorenja', 'sidrena' ) : esc_html__( 'Potrebna provjera', 'sidrena' ); ?></span>
				</div>
				<?php $this->health_list( $stats, $last, $settings ); ?>
			</section>

			<section class="sid-card sid-compliance-history">
				<span class="sid-kicker"><?php esc_html_e( 'Evidencija promjena', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Povijest se gradi kontinuirano', 'sidrena' ); ?></h2>
				<p><?php echo Sidrena_Utils::is_woocommerce_edition() ? esc_html__( 'Ovo izdanje vodi povijest WooCommerce cijena, cijena usluga i podataka po lokacijama. Javne CSV/XML objave čuvaju se prema postavljenoj politici arhive.', 'sidrena' ) : esc_html__( 'Ovo izdanje koristi vlastiti Sidrena katalog proizvoda i usluga bez WooCommercea. Javne CSV/XML objave čuvaju se prema postavljenoj politici arhive.', 'sidrena' ); ?></p>
				<div class="sid-history-stack">
					<div><span class="dashicons dashicons-products"></span><span><?php echo Sidrena_Utils::is_woocommerce_edition() ? esc_html__( 'WooCommerce povijest', 'sidrena' ) : esc_html__( 'WordPress proizvodi', 'sidrena' ); ?></span><strong><?php echo esc_html( Sidrena_Utils::is_woocommerce_edition() ? $product_hist : ( class_exists( 'Sidrena_Standalone' ) ? Sidrena_Standalone::count() : 0 ) ); ?></strong></div>
					<div><span class="dashicons dashicons-clipboard"></span><span><?php esc_html_e( 'Povijest usluga', 'sidrena' ); ?></span><strong><?php echo esc_html( $service_hist ); ?></strong></div>
					<div><span class="dashicons dashicons-location-alt"></span><span><?php esc_html_e( 'Lokacijska povijest', 'sidrena' ); ?></span><strong><?php echo esc_html( $location_hist ); ?></strong></div>
					<div><span class="dashicons dashicons-backup"></span><span><?php esc_html_e( 'Čuvanje javne arhive', 'sidrena' ); ?></span><strong><?php echo esc_html( max( 30, absint( $settings['retention_days'] ) ) ); ?> d</strong></div>
				</div>
				<a class="button sid-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_export_price_history' ), 'sidrena_export_price_history' ) ); ?>"><?php esc_html_e( 'Izvezi evidenciju promjena', 'sidrena' ); ?></a>
			</section>
		</div>

		<div class="sid-grid sid-grid-3">
			<section class="sid-card sid-check-card <?php echo 0 === $stats['missing_brand'] ? 'is-ok' : 'is-warn'; ?>">
				<span class="dashicons dashicons-awards"></span>
				<div><h3><?php esc_html_e( 'Marka proizvoda', 'sidrena' ); ?></h3><p><?php echo 0 === $stats['missing_brand'] ? esc_html__( 'Sve stavke proizvoda imaju prepoznatu marku.', 'sidrena' ) : esc_html( sprintf( __( '%d stavki nema prepoznatu marku. Dopunite podatak u Sidrena katalogu ili WooCommerce proizvodu kada je integracija aktivna.', 'sidrena' ), $stats['missing_brand'] ) ); ?></p></div>
			</section>
			<section class="sid-card sid-check-card <?php echo $integrity['ok'] ? 'is-ok' : 'is-warn'; ?>">
				<span class="dashicons dashicons-shield-alt"></span>
				<div><h3><?php esc_html_e( 'Integritet arhive', 'sidrena' ); ?></h3><p><?php echo $integrity['ok'] ? esc_html__( 'Datoteke postoje i pohranjeni SHA-256 zapisi odgovaraju.', 'sidrena' ) : esc_html__( 'Nedostaje datoteka ili se SHA-256 ne podudara. Provjerite karticu Arhiva.', 'sidrena' ); ?></p></div>
			</section>
			<section class="sid-card sid-check-card <?php echo 0 === $file_coverage['missing'] ? 'is-ok' : 'is-warn'; ?>">
				<span class="dashicons dashicons-admin-site-alt3"></span>
				<div><h3><?php esc_html_e( 'Lokacije × katalog × format', 'sidrena' ); ?></h3><p><?php echo esc_html( sprintf( __( 'Očekivano %1$d, aktualno %2$d. Svaka aktivna lokacija i webshop vode se kao zaseban izlaz.', 'sidrena' ), $file_coverage['expected'], $file_coverage['ready'] ) ); ?></p></div>
			</section>
		</div>

		<section class="sid-card sid-note">
			<div class="sid-note-icon"><span class="dashicons dashicons-info-outline"></span></div>
			<div><h2><?php esc_html_e( '30 dana arhive nije isto što i 30-dnevna najniža cijena', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Javna arhiva čuva prethodno objavljene CSV/XML cjenike. Interna povijest cijena zasebno služi za provjeru najniže cijene prije sniženja. Sidrena namjerno ne rekonstruira niti izmišlja povijest koja nije zabilježena ili uvezena iz vjerodostojne evidencije.', 'sidrena' ); ?></p></div>
		</section>
		<?php
	}

	private function public_file_coverage() {
		$settings  = Sidrena_Utils::settings();
		$locations = array();
		foreach ( Sidrena_Utils::locations() as $location ) {
			if ( 'yes' === ( $location['enabled'] ?? '' ) ) {
				$locations[] = $location;
			}
		}

		$catalogs = array();
		if ( in_array( $settings['business_mode'], array( 'products', 'mixed' ), true ) ) {
			$catalogs[] = 'products';
		}
		if ( in_array( $settings['business_mode'], array( 'services', 'mixed' ), true ) ) {
			$catalogs[] = 'services';
		}

		$formats = array();
		if ( 'yes' === $settings['generate_csv'] ) {
			$formats[] = 'csv';
		}
		if ( 'yes' === $settings['generate_xml'] ) {
			$formats[] = 'xml';
		}

		$expected_keys = array();
		foreach ( $locations as $location ) {
			foreach ( $catalogs as $catalog ) {
				foreach ( $formats as $format ) {
					$key = implode( '|', array( Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' ), $catalog, $format ) );
					$expected_keys[ $key ] = true;
				}
			}
		}

		$ready = 0;
		foreach ( Sidrena_Utils::public_index() as $entry ) {
			$key = implode( '|', array( Sidrena_Utils::sanitize_location_id( $entry['location_id'] ?? '' ), sanitize_key( $entry['catalog'] ?? '' ), sanitize_key( $entry['format'] ?? '' ) ) );
			if ( isset( $expected_keys[ $key ] ) ) {
				++$ready;
				unset( $expected_keys[ $key ] );
			}
		}

		$expected = count( $locations ) * count( $catalogs ) * count( $formats );
		return array(
			'expected' => $expected,
			'ready'    => min( $expected, $ready ),
			'missing'  => max( 0, $expected - $ready ),
		);
	}

	private function active_service_sales() {
		$query = new WP_Query(
			array(
				'post_type'      => 'sidrena_service',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
				'meta_key'       => '_sidrena_service_sale',
				'meta_value'     => 'yes',
			)
		);
		return absint( $query->found_posts );
	}

	private function health_list( $stats, $last, $settings ) {
		$locations       = Sidrena_Utils::locations();
		$missing_address = 0;
		$coverage_issue  = false;
		foreach ( $locations as $location ) {
			if ( 'yes' !== ( $location['enabled'] ?? '' ) ) {
				continue;
			}
			if ( empty( $location['address'] ) ) {
				++$missing_address;
			}
			if ( Sidrena_Utils::is_woocommerce_active() && 'webshop' !== sanitize_key( $location['kind'] ?? '' ) && $stats['products'] > 0 ) {
				if ( Sidrena_Location_Data::coverage( $location['id'] ?? '' ) < $stats['products'] ) {
					$coverage_issue = true;
				}
			}
		}

		$needs_products = in_array( $settings['business_mode'], array( 'products', 'mixed' ), true );
		$output_enabled = 'yes' === $settings['generate_csv'] || 'yes' === $settings['generate_xml'];
		$integrity      = Sidrena_Utils::archive_integrity();
		$before_eight   = isset( $settings['generation_time'] ) && strcmp( (string) $settings['generation_time'], '08:00' ) < 0;
		$cron_scheduled = (bool) wp_next_scheduled( 'sidrena_daily_generation' );
		$product_catalog_ready = Sidrena_Utils::is_wordpress_edition()
			? class_exists( 'Sidrena_Standalone' )
			: ( Sidrena_Utils::is_woocommerce_active() && class_exists( 'Sidrena_Products' ) );
		$identity = Sidrena_Utils::business_identity();
		$identity_required = ! empty( $identity['show'] );
		$identity_contact_ready = ! $identity_required || ( ! empty( $identity['name'] ) && ! empty( $identity['address'] ) && ! empty( $identity['email'] ) && ! empty( $identity['phone'] ) );
		$identity_registry_ready = ! $identity_required || ( ! empty( $identity['registry'] ) && ! empty( $identity['registry_number'] ) );
		$oib_ready = empty( $identity['oib'] ) || Sidrena_Utils::is_valid_oib( $identity['oib'] );
		$checks = array(
			array( $identity_contact_ready, __( 'Javni identitet subjekta ima naziv, adresu, e-mail i telefon', 'sidrena' ), __( 'Dopunite podatke obrta/tvrtke u Postavkama ili isključite njihov prikaz ako ih objavljujete drugim dijelom web stranice.', 'sidrena' ) ),
			array( $identity_registry_ready, __( 'Podaci javnog registra su uneseni', 'sidrena' ), __( 'Dopunite naziv registra i broj upisa u Postavkama ako javni profil subjekta prikazujete kroz Sidrenu.', 'sidrena' ) ),
			array( $oib_ready, __( 'Uneseni OIB ima valjanu kontrolnu znamenku', 'sidrena' ), __( 'Provjerite OIB u podacima obrta/tvrtke.', 'sidrena' ) ),
			array( ! $needs_products || $product_catalog_ready, __( 'Katalog proizvoda je dostupan', 'sidrena' ), __( 'Provjerite instalaciju odgovarajućeg Sidrena izdanja i izvora proizvoda.', 'sidrena' ) ),
			array( ! $missing_address, __( 'Sve aktivne lokacije imaju adresu za naziv datoteke', 'sidrena' ), __( 'Dopunite adresu u kartici Lokacije.', 'sidrena' ) ),
			array( ! $needs_products || 0 === $stats['missing_anchor'], __( 'Proizvodi imaju sidrenu cijenu', 'sidrena' ), __( 'Dopunite nedostajuće vrijednosti u Sidrena katalogu ili u WooCommerce proizvodima kada je integracija aktivna.', 'sidrena' ) ),
			array( ! $needs_products || 0 === $stats['missing_brand'], __( 'Proizvodi imaju podatak o marki za digitalni cjenik', 'sidrena' ), __( 'Dopunite marku u Sidrena katalogu; uz WooCommerce možete koristiti i Brands/pa_brand.', 'sidrena' ) ),
			array( ! $needs_products || 0 === $stats['missing_barcode'], __( 'Proizvodi imaju barkod za digitalni cjenik', 'sidrena' ), __( 'Dopunite Barkod iz vjerodostojne poslovne evidencije u aktivnom katalogu.', 'sidrena' ) ),
			array( ! $needs_products || 0 === $stats['unit_price_review'], __( 'Primjenjivost cijene za jedinicu mjere pregledana je za proizvode', 'sidrena' ), __( 'U Katalogu označite je li jedinična cijena obvezna, nije primjenjiva ili postoji propisana iznimka prema NN 105/2026.', 'sidrena' ) ),
			array( ! $needs_products || 0 === $stats['unit_price_missing'], __( 'Stavke za koje je jedinična cijena obvezna imaju jedinicu i iznos', 'sidrena' ), __( 'Dopunite jedinicu mjere i cijenu za jedinicu mjere za označene proizvode/varijacije.', 'sidrena' ) ),
			array( 0 === $stats['missing_service_anchor'], __( 'Objavljene usluge imaju sidrenu cijenu', 'sidrena' ), __( 'Dopunite usluge kojima nedostaje referentna cijena.', 'sidrena' ) ),
			array( 0 === $stats['service_details_missing'], __( 'Objavljene usluge imaju vrstu i opseg za javni cjenik', 'sidrena' ), __( 'Dopunite vrstu i opseg usluge kako bi javni cjenik sadržavao podatke iz NN 105/2026.', 'sidrena' ) ),
			array( 0 === $stats['sale_incomplete'], __( 'Aktivna sniženja proizvoda imaju provjerljivu 30-dnevnu referencu ili evidentirano izuzeće', 'sidrena' ), __( 'Za nepotpunu povijest proizvoda unesite provjerenu ručnu vrijednost.', 'sidrena' ) ),
			array( 0 === $stats['perishable_expiry_missing'], __( 'Lako pokvarljiva roba i roba kojoj brzo istječe rok ima evidentiran krajnji rok uporabe', 'sidrena' ), __( 'Dopunite krajnji rok uporabe na aktivnim sniženjima označenim tim izuzećem.', 'sidrena' ) ),
			array( 0 === $stats['service_sale_incomplete'], __( 'Aktivna sniženja usluga imaju provjerljivu 30-dnevnu referencu ili evidentiranu primjenjivu iznimku', 'sidrena' ), __( 'Za nepotpunu povijest usluge unesite provjerenu ručnu vrijednost ili, ako je stvarno primjenjivo, označite iznimku.', 'sidrena' ) ),
			array( ! $coverage_issue, __( 'Fizičke lokacije imaju podatke o raspoloživosti po stavci', 'sidrena' ), __( 'Uvezite lokacijsku raspoloživost; globalno Woo stanje možda nije dovoljno za fizičku poslovnicu.', 'sidrena' ) ),
			array( $output_enabled, __( 'Uključen je barem jedan strojno čitljiv format', 'sidrena' ), __( 'Uključite CSV i/ili XML.', 'sidrena' ) ),
			array( $before_eight, __( 'Automatsko dnevno generiranje postavljeno je prije 08:00', 'sidrena' ), __( 'Postavite vrijeme prije 08:00; preporuka Sidrene je 06:30 radi operativne rezerve.', 'sidrena' ) ),
			array( $cron_scheduled, __( 'Dnevni WP-Cron događaj za generiranje cjenika je zakazan', 'sidrena' ), __( 'Ponovno spremite postavke ili reaktivirajte dodatak. Za strogo vrijeme izvršenja koristite pravi poslužiteljski cron koji pokreće WP-Cron.', 'sidrena' ) ),
			array( max( 30, absint( $settings['retention_days'] ) ) >= 30, __( 'Arhiva je postavljena na najmanje 30 dana', 'sidrena' ), __( 'Povećajte razdoblje čuvanja.', 'sidrena' ) ),
			array( ! empty( Sidrena_Utils::public_index() ), __( 'Postoji barem jedan aktualni javni cjenik', 'sidrena' ), __( 'Generirajte prvi cjenik.', 'sidrena' ) ),
			array( $integrity['ok'], __( 'Indeksirane arhivske datoteke postoje i provjereni SHA-256 zapisi se podudaraju', 'sidrena' ), __( 'Otvorite Arhiva 30+ dana i provjerite nedostajuće ili promijenjene datoteke.', 'sidrena' ) ),
			array( empty( $last['errors'] ), __( 'Zadnje generiranje je završilo bez grešaka', 'sidrena' ), __( 'Pregledajte upozorenja zadnjeg generiranja.', 'sidrena' ) ),
		);

		echo '<div class="sid-health">';
		foreach ( $checks as $check ) {
			echo '<div class="sid-health-row ' . esc_attr( $check[0] ? 'is-ok' : 'is-warn' ) . '"><span class="sid-health-icon dashicons ' . esc_attr( $check[0] ? 'dashicons-yes-alt' : 'dashicons-warning' ) . '"></span><div><strong>' . esc_html( $check[1] ) . '</strong>';
			if ( ! $check[0] ) {
				echo '<small>' . esc_html( $check[2] ) . '</small>';
			}
			echo '</div></div>';
		}
		echo '</div>';

		if ( ! empty( $last['errors'] ) && is_array( $last['errors'] ) ) {
			echo '<div class="sid-errors"><strong>' . esc_html__( 'Zadnja upozorenja', 'sidrena' ) . '</strong><ul>';
			foreach ( $last['errors'] as $error ) {
				echo '<li>' . esc_html( $error ) . '</li>';
			}
			echo '</ul></div>';
		}
	}

	private function files_tab() {
		$current  = Sidrena_Utils::public_index();
		$paths    = Sidrena_Utils::upload_paths();
		$last     = get_option( 'sidrena_last_run', array() );
		$settings       = Sidrena_Utils::settings();
		$public_page_id = absint( get_option( 'sidrena_public_page_id', 0 ) );
		$public_page    = $public_page_id ? get_post( $public_page_id ) : null;
		if ( ! $public_page || 'trash' === $public_page->post_status ) {
			$public_page_id = 0;
			$public_page    = null;
		}
		$public_html_url = Sidrena_Public::route_url( 'cjenik' );
		$archive_html_url = Sidrena_Public::route_url( 'arhiva' );
		$last_ts = ! empty( $last['generated_at'] ) ? strtotime( (string) $last['generated_at'] ) : 0;
		$is_stale = $last_ts && ( time() - $last_ts ) > ( 26 * HOUR_IN_SECONDS );
		$wp_cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$next_cron        = wp_next_scheduled( 'sidrena_daily_generation' );
		$rest_enabled     = 'yes' === $settings['enable_rest_index'];
		$html_enabled     = 'yes' === $settings['enable_public_html'];
		?>
		<div class="sid-page-head">
			<div><span class="sid-kicker"><?php esc_html_e( 'Aktualne objave', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Javni cjenici', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Svaka aktivna lokacija dobiva zasebnu CSV/XML datoteku. U slučaju greške zadnja uspješno generirana datoteka ostaje aktualna.', 'sidrena' ); ?></p></div>
			<div class="sid-head-inline-actions">
				<?php if ( $public_page_id ) : ?>
					<a class="button sid-secondary" href="<?php echo esc_url( get_permalink( $public_page_id ) ); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-external"></span><?php esc_html_e( 'Otvori javnu stranicu', 'sidrena' ); ?></a>
				<?php else : ?>
					<a class="button sid-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_create_public_page' ), 'sidrena_create_public_page' ) ); ?>"><span class="dashicons dashicons-admin-page"></span><?php esc_html_e( 'Izradi stranicu Objava cjenika', 'sidrena' ); ?></a>
				<?php endif; ?>
				<a class="button sid-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_check_public_access' ), 'sidrena_check_public_access' ) ); ?>"><span class="dashicons dashicons-shield-alt"></span><?php esc_html_e( 'Provjeri javnu dostupnost', 'sidrena' ); ?></a>
				<a class="button button-primary sid-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_generate' ), 'sidrena_generate' ) ); ?>"><span class="dashicons dashicons-update"></span><?php esc_html_e( 'Generiraj sada', 'sidrena' ); ?></a>
			</div>
		</div>

		<div class="sid-grid sid-grid-3">
			<section class="sid-card sid-mini-stat"><span><?php esc_html_e( 'Aktualnih datoteka', 'sidrena' ); ?></span><strong><?php echo esc_html( count( $current ) ); ?></strong></section>
			<section class="sid-card sid-mini-stat"><span><?php esc_html_e( 'Zadnje generiranje', 'sidrena' ); ?></span><strong class="sid-mini-date"><?php echo ! empty( $last['generated_at'] ) ? esc_html( $last['generated_at'] ) : '—'; ?></strong></section>
			<section class="sid-card sid-mini-stat"><span><?php esc_html_e( 'Manifest', 'sidrena' ); ?></span><strong><?php echo 'yes' === $settings['publish_manifest'] ? esc_html__( 'Uključen', 'sidrena' ) : esc_html__( 'Isključen', 'sidrena' ); ?></strong></section>
		</div>

		<section class="sid-card sid-note <?php echo $is_stale || $wp_cron_disabled || ! $next_cron ? 'sid-note-warning' : ''; ?>">
			<div class="sid-note-icon"><span class="dashicons <?php echo $is_stale || $wp_cron_disabled || ! $next_cron ? 'dashicons-warning' : 'dashicons-clock'; ?>"></span></div>
			<div>
				<?php if ( $is_stale ) : ?>
					<h2><?php esc_html_e( 'Zadnji uspješni cjenik stariji je od 26 sati', 'sidrena' ); ?></h2>
					<p><?php esc_html_e( 'Provjerite WP-Cron, server cron i Dnevnik. Zadnja valjana datoteka ostaje javno dostupna dok nova objava ne prođe provjeru.', 'sidrena' ); ?></p>
				<?php elseif ( $wp_cron_disabled ) : ?>
					<h2><?php esc_html_e( 'WordPress WP-Cron je isključen', 'sidrena' ); ?></h2>
					<p><?php esc_html_e( 'Automatsko dnevno generiranje tada ovisi o vašem server cron zadatku ili WP-CLI automatizaciji. Provjerite da se izvršava prije postavljenog roka.', 'sidrena' ); ?></p>
				<?php elseif ( ! $next_cron ) : ?>
					<h2><?php esc_html_e( 'Dnevno generiranje nije zakazano', 'sidrena' ); ?></h2>
					<p><?php esc_html_e( 'Ponovno spremite Postavke. Ako WordPress i dalje ne može zakazati događaj, provjerite cron konfiguraciju poslužitelja.', 'sidrena' ); ?></p>
				<?php else : ?>
					<h2><?php esc_html_e( 'Automatsko generiranje je zakazano', 'sidrena' ); ?></h2>
					<p><?php echo esc_html( sprintf( __( 'Sljedeći WordPress cron događaj: %s. Za poslovno kritičan termin preporučuje se pouzdan server cron.', 'sidrena' ), wp_date( 'd.m.Y. H:i', $next_cron ) ) ); ?></p>
				<?php endif; ?>
			</div>
		</section>

		<section class="sid-card">
			<div class="sid-section-head"><div><h2><?php esc_html_e( 'Datoteke dostupne javnosti', 'sidrena' ); ?></h2><p><?php esc_html_e( 'SHA-256 omogućuje naknadnu provjeru da sadržaj arhivirane datoteke nije promijenjen.', 'sidrena' ); ?></p></div></div>
			<?php $this->files_table( $current, false ); ?>
		</section>

		<section class="sid-card sid-public-page-card">
			<div class="sid-section-head">
				<div><span class="sid-kicker"><?php esc_html_e( 'Javna dostupnost', 'sidrena' ); ?></span><h2><?php esc_html_e( 'WordPress stranica s aktualnim cjenicima i arhivom', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Sidrena može izraditi običnu javnu WordPress stranicu “Objava cjenika” sa shortcodeom koji prikazuje aktualne datoteke i prethodne objave iz javne arhive.', 'sidrena' ); ?></p></div>
				<?php if ( $public_page_id && $html_enabled ) : ?>
					<span class="sid-status-pill is-ok"><?php esc_html_e( 'Objavljeno', 'sidrena' ); ?></span>
				<?php elseif ( $public_page_id ) : ?>
					<span class="sid-status-pill is-warn"><?php esc_html_e( 'HTML prikaz je isključen', 'sidrena' ); ?></span>
				<?php else : ?>
					<span class="sid-status-pill is-warn"><?php esc_html_e( 'Stranica nije izrađena', 'sidrena' ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( $public_page_id ) : ?>
				<div class="sid-code-row"><span><?php esc_html_e( 'Javni URL', 'sidrena' ); ?></span><code><?php echo esc_html( get_permalink( $public_page_id ) ); ?></code></div>
			<?php else : ?>
				<a class="button button-primary sid-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_create_public_page' ), 'sidrena_create_public_page' ) ); ?>"><?php esc_html_e( 'Izradi i objavi stranicu', 'sidrena' ); ?></a>
			<?php endif; ?>
		</section>

		<section class="sid-card sid-code-card">
			<h2><?php esc_html_e( 'Strojni pristup', 'sidrena' ); ?></h2>
			<div class="sid-code-row <?php echo $rest_enabled ? '' : 'is-disabled'; ?>"><span><?php esc_html_e( 'REST indeks cjenika', 'sidrena' ); ?><?php if ( ! $rest_enabled ) : ?><small><?php esc_html_e( 'Isključeno u Postavkama', 'sidrena' ); ?></small><?php endif; ?></span><code><?php echo esc_html( rest_url( 'sidrena/v1/cjenici' ) ); ?></code></div><div class="sid-code-row <?php echo $rest_enabled ? '' : 'is-disabled'; ?>"><span><?php esc_html_e( 'Cijene u realnom vremenu', 'sidrena' ); ?><?php if ( ! $rest_enabled ) : ?><small><?php esc_html_e( 'Isključeno u Postavkama', 'sidrena' ); ?></small><?php endif; ?></span><code><?php echo esc_html( rest_url( 'sidrena/v1/cijene' ) ); ?></code></div>
			<?php if ( 'yes' === $settings['publish_manifest'] ) : ?><div class="sid-code-row"><span>JSON manifest</span><code><?php echo esc_html( $paths['manifest_url'] ); ?></code></div><?php endif; ?>
			<div class="sid-code-row <?php echo $html_enabled ? '' : 'is-disabled'; ?>"><span><?php esc_html_e( 'Javni HTML cjenik', 'sidrena' ); ?><?php if ( ! $html_enabled ) : ?><small><?php esc_html_e( 'Isključeno u Postavkama', 'sidrena' ); ?></small><?php endif; ?></span><code><?php echo esc_html( $public_html_url ); ?></code></div>
			<div class="sid-code-row <?php echo $html_enabled ? '' : 'is-disabled'; ?>"><span><?php esc_html_e( 'Javna HTML arhiva', 'sidrena' ); ?><?php if ( ! $html_enabled ) : ?><small><?php esc_html_e( 'Isključeno u Postavkama', 'sidrena' ); ?></small><?php endif; ?></span><code><?php echo esc_html( $archive_html_url ); ?></code></div>
			<div class="sid-code-row"><span><?php esc_html_e( 'Kompletna Objava cjenika', 'sidrena' ); ?></span><code>[sidrena_objava_cjenika]</code></div>
			<div class="sid-code-row"><span><?php esc_html_e( 'Pretraživi cjenik', 'sidrena' ); ?></span><code>[sidrena_cjenik]</code></div>
			<div class="sid-code-row"><span><?php esc_html_e( 'Arhiva', 'sidrena' ); ?></span><code>[sidrena_arhiva]</code></div>
			<div class="sid-code-row"><span><?php esc_html_e( 'Javna lista datoteka', 'sidrena' ); ?></span><code>[sidrena_cjenici]</code></div>
			<div class="sid-code-row"><span><?php esc_html_e( 'Cjenik usluga', 'sidrena' ); ?></span><code>[sidrena_usluge]</code></div>
		</section>
		<?php
	}

	private function archive_tab() {
		$archive   = Sidrena_Utils::archive_index();
		$stats     = Sidrena_Utils::archive_stats();
		$settings  = Sidrena_Utils::settings();
		$integrity = Sidrena_Utils::archive_integrity();
		$retention = Sidrena_Utils::archive_retention_status();
		?>
		<div class="sid-page-head"><div><span class="sid-kicker"><?php esc_html_e( 'Javna povijest', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Arhiva i tehnička spremnost', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Svaka uspješna CSV/XML objava čuva se kao zasebna javna datoteka. Minimalno razdoblje čuvanja je 30 dana, a zadana Sidrena postavka je 45 dana.', 'sidrena' ); ?></p></div><a class="button sid-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_export_archive_index' ), 'sidrena_export_archive_index' ) ); ?>"><span class="dashicons dashicons-download"></span><?php esc_html_e( 'Izvezi evidenciju arhive', 'sidrena' ); ?></a></div>

		<div class="sid-archive-top">
			<section class="sid-card sid-archive-settings-card">
				<div class="sid-section-head"><div><span class="sid-kicker"><?php esc_html_e( 'Postavke arhive', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Čuvanje prethodnih cjenika', 'sidrena' ); ?></h2></div><span class="sid-status-pill is-ok"><?php esc_html_e( 'Arhiva je aktivna', 'sidrena' ); ?></span></div>
				<p><?php esc_html_e( 'Postavljeno razdoblje čuvanja ne može biti kraće od 30 dana. Aktualna datoteka dodatno se ne uklanja samo zato što je starija od arhivskog prozora.', 'sidrena' ); ?></p>
				<div class="sid-retention-box">
					<div><span><?php esc_html_e( 'Trajanje arhive', 'sidrena' ); ?></span><strong><?php echo esc_html( sprintf( __( '%d dana', 'sidrena' ), $settings['retention_days'] ) ); ?></strong></div>
					<div><span class="dashicons dashicons-info-outline"></span><p><?php echo esc_html( sprintf( __( 'Minimum je 30 dana. Trenutačna rezerva iznad minimuma iznosi +%d dana.', 'sidrena' ), $retention['buffer_days'] ) ); ?></p></div>
				</div>
				<a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-settings' ) ); ?>"><?php esc_html_e( 'Uredi postavke arhive', 'sidrena' ); ?></a>
			</section>
			<?php $this->support_card(); ?>
		</div>

		<div class="sid-dashboard-metrics sid-dashboard-metrics--archive">
			<?php $this->dashboard_metric( __( 'Datoteke u arhivi', 'sidrena' ), $stats['files'], 'dashicons-database', __( 'CSV/XML objave', 'sidrena' ), 'blue' ); ?>
			<?php $this->dashboard_metric( __( 'Dani s objavama', 'sidrena' ), $stats['distinct_days'], 'dashicons-calendar-alt', __( 'evidentirani dani', 'sidrena' ), 'teal' ); ?>
			<?php $this->dashboard_metric( __( 'Politika čuvanja', 'sidrena' ), $settings['retention_days'] . ' d', 'dashicons-lock', sprintf( __( '+%d dana rezerve', 'sidrena' ), $retention['buffer_days'] ), 'ok' ); ?>
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
					<?php $date = $today->modify( '-' . $offset . ' days' ); $key = $date->format( 'Y-m-d' ); $count = $days[ $key ] ?? 0; ?>
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
				<thead><tr><?php if ( $show_location ) : ?><th><?php esc_html_e( 'Lokacija', 'sidrena' ); ?></th><?php endif; ?><th><?php esc_html_e( 'Vrsta', 'sidrena' ); ?></th><th><?php esc_html_e( 'Format', 'sidrena' ); ?></th><th><?php esc_html_e( 'Redaka', 'sidrena' ); ?></th><th><?php esc_html_e( 'Objavljeno', 'sidrena' ); ?></th><th><?php esc_html_e( 'Čuvati do', 'sidrena' ); ?></th><th>SHA-256</th><th></th></tr></thead>
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
							<td><?php if ( ! empty( $file['url'] ) ) : ?><a class="button button-small" href="<?php echo esc_url( $file['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Otvori', 'sidrena' ); ?></a><?php else : ?><span class="sid-status-pill is-warn"><?php esc_html_e( 'URL nedostaje', 'sidrena' ); ?></span><?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function locations_tab() {
		$locations = Sidrena_Utils::locations();
		$stats     = $this->audit_stats();
		?>
		<div class="sid-page-head"><div><span class="sid-kicker"><?php esc_html_e( 'Poslovnice i webshop', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Lokacije cjenika', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Za svaku fizičku lokaciju generira se zasebna datoteka. Webshop se također vodi kao zaseban objekt. Kod fizičkih lokacija raspoloživost mora odgovarati stvarnom stanju upravo te poslovnice.', 'sidrena' ); ?></p></div></div>
		<form class="sid-card sid-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sidrena_save_locations">
			<?php wp_nonce_field( 'sidrena_save_locations' ); ?>
			<div id="sid-locations" class="sid-locations">
				<?php foreach ( $locations as $index => $location ) : ?><?php $this->location_card( $index, $location, $stats['products'] ); ?><?php endforeach; ?>
			</div>
			<div class="sid-form-actions"><button type="button" class="button sid-secondary" id="sid-add-location"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e( 'Dodaj lokaciju', 'sidrena' ); ?></button><button class="button button-primary sid-primary" type="submit"><?php esc_html_e( 'Spremi lokacije', 'sidrena' ); ?></button></div>
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
				<div><span class="sid-location-icon dashicons <?php echo 'webshop' === sanitize_key( $kind ) ? 'dashicons-store' : 'dashicons-location'; ?>"></span><strong><?php echo esc_html( $location['code'] ?: __( 'Nova lokacija', 'sidrena' ) ); ?></strong><small><?php echo esc_html( $location['address'] ?: __( 'Adresa nije upisana', 'sidrena' ) ); ?></small></div>
				<div class="sid-location-actions"><label class="sid-switch"><input class="sid-location-enabled" type="checkbox" name="locations[<?php echo esc_attr( $index ); ?>][enabled]" value="yes" <?php checked( $location['enabled'] ?? '', 'yes' ); ?>><span><?php esc_html_e( 'Aktivna', 'sidrena' ); ?></span></label><button type="button" class="button-link-delete sid-remove-location"><?php esc_html_e( 'Ukloni', 'sidrena' ); ?></button></div>
			</div>
			<div class="sid-fields sid-fields-location">
				<label><span><?php esc_html_e( 'ID lokacije', 'sidrena' ); ?></span><input type="text" name="locations[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $location['id'] ?? '' ); ?>" placeholder="zagreb-centar" data-required-when-active <?php echo 'yes' === ( $location['enabled'] ?? '' ) ? 'required aria-required="true"' : 'aria-required="false"'; ?>></label>
				<label><span><?php esc_html_e( 'Oblik / vrsta objekta', 'sidrena' ); ?></span><input type="text" name="locations[<?php echo esc_attr( $index ); ?>][kind]" value="<?php echo esc_attr( $kind ); ?>" placeholder="prodavaonica / servis / webshop" data-required-when-active <?php echo 'yes' === ( $location['enabled'] ?? '' ) ? 'required aria-required="true"' : 'aria-required="false"'; ?>></label>
				<label><span><?php esc_html_e( 'Oznaka objekta', 'sidrena' ); ?></span><input type="text" name="locations[<?php echo esc_attr( $index ); ?>][code]" value="<?php echo esc_attr( $location['code'] ?? '' ); ?>" placeholder="P-01" data-required-when-active <?php echo 'yes' === ( $location['enabled'] ?? '' ) ? 'required aria-required="true"' : 'aria-required="false"'; ?>></label>
				<label class="sid-wide"><span><?php esc_html_e( 'Adresa objekta', 'sidrena' ); ?></span><input type="text" name="locations[<?php echo esc_attr( $index ); ?>][address]" value="<?php echo esc_attr( $location['address'] ?? '' ); ?>" placeholder="Ilica 150, Zagreb" data-required-when-active <?php echo 'yes' === ( $location['enabled'] ?? '' ) ? 'required aria-required="true"' : 'aria-required="false"'; ?>></label>
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
		<div class="sid-page-head"><div><span class="sid-kicker"><?php esc_html_e( 'Konfiguracija', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Postavke', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Zadane vrijednosti prate trenutno objavljena pravila, ali ih administrator može prilagoditi stvarnom poslovnom modelu.', 'sidrena' ); ?></p></div></div>
		<form class="sid-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sidrena_save_settings">
			<?php wp_nonce_field( 'sidrena_save_settings' ); ?>

			<section class="sid-card sid-settings-section"><div class="sid-settings-title"><span class="dashicons dashicons-admin-home"></span><div><h2><?php esc_html_e( 'Način rada', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Odredite objavljuje li subjekt proizvode, usluge ili oboje.', 'sidrena' ); ?></p></div></div>
				<div class="sid-fields"><label><span><?php esc_html_e( 'Poslovni model', 'sidrena' ); ?></span><select name="business_mode"><option value="products" <?php selected( $settings['business_mode'], 'products' ); ?>><?php esc_html_e( 'Proizvodi / trgovina', 'sidrena' ); ?></option><option value="services" <?php selected( $settings['business_mode'], 'services' ); ?>><?php esc_html_e( 'Usluge', 'sidrena' ); ?></option><option value="mixed" <?php selected( $settings['business_mode'], 'mixed' ); ?>><?php esc_html_e( 'Proizvodi i usluge', 'sidrena' ); ?></option></select></label></div>
			</section>

			<section class="sid-card sid-settings-section">
				<div class="sid-settings-title"><span class="dashicons dashicons-building"></span><div><h2><?php esc_html_e( 'Podaci obrta / tvrtke', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Podaci za identitet i kontakt na javnoj stranici Objava cjenika. Ova polja nisu dodatni stupci propisanog CSV/XML cjenika.', 'sidrena' ); ?></p></div></div>
				<div class="sid-toggle-grid"><label class="sid-toggle-card"><input type="checkbox" name="show_business_identity" value="yes" <?php checked( $settings['show_business_identity'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong><?php esc_html_e( 'Prikaži podatke subjekta', 'sidrena' ); ?></strong><small><?php esc_html_e( 'Prikazuje se iznad javne objave cjenika kada su podaci uneseni.', 'sidrena' ); ?></small></span></label></div>
				<div class="sid-fields">
					<label><span><?php esc_html_e( 'Naziv obrta / tvrtke', 'sidrena' ); ?></span><input type="text" maxlength="190" name="business_name" value="<?php echo esc_attr( $settings['business_name'] ); ?>" autocomplete="organization"></label>
					<label class="sid-wide"><span><?php esc_html_e( 'Sjedište / poslovna adresa', 'sidrena' ); ?></span><input type="text" maxlength="250" name="business_address" value="<?php echo esc_attr( $settings['business_address'] ); ?>" autocomplete="street-address"></label>
					<label><span>OIB</span><input type="text" inputmode="numeric" maxlength="11" pattern="[0-9]{11}" name="business_oib" value="<?php echo esc_attr( $settings['business_oib'] ); ?>"><small><?php esc_html_e( 'Ako ga unosite, Sidrena provjerava kontrolnu znamenku.', 'sidrena' ); ?></small></label>
					<label><span><?php esc_html_e( 'E-mail poslovnog subjekta', 'sidrena' ); ?></span><input type="email" maxlength="190" name="business_email" value="<?php echo esc_attr( $settings['business_email'] ); ?>" autocomplete="email"></label>
					<label><span><?php esc_html_e( 'Telefon', 'sidrena' ); ?></span><input type="text" maxlength="40" name="business_phone" value="<?php echo esc_attr( $settings['business_phone'] ); ?>" autocomplete="tel"></label>
					<label><span><?php esc_html_e( 'Naziv registra', 'sidrena' ); ?></span><input type="text" maxlength="190" name="business_registry" value="<?php echo esc_attr( $settings['business_registry'] ); ?>" placeholder="<?php esc_attr_e( 'npr. Sudski registar ili Obrtni registar', 'sidrena' ); ?>"></label>
					<label><span><?php esc_html_e( 'Broj upisa u registar', 'sidrena' ); ?></span><input type="text" maxlength="100" name="business_registry_number" value="<?php echo esc_attr( $settings['business_registry_number'] ); ?>"></label>
					<label><span><?php esc_html_e( 'PDV identifikacijski broj', 'sidrena' ); ?></span><input type="text" maxlength="32" name="business_vat_id" value="<?php echo esc_attr( $settings['business_vat_id'] ); ?>" placeholder="HR12345678901"></label>
					<label><span><?php esc_html_e( 'Nadležno / nadzorno tijelo', 'sidrena' ); ?></span><input type="text" maxlength="190" name="business_supervisory_authority" value="<?php echo esc_attr( $settings['business_supervisory_authority'] ); ?>" placeholder="<?php esc_attr_e( 'ako je primjenjivo', 'sidrena' ); ?>"></label>
				</div>
			</section>

			<section class="sid-card sid-settings-section"><div class="sid-settings-title"><span class="dashicons dashicons-tag"></span><div><h2><?php esc_html_e( 'Referentne cijene na webu', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Dodatna cijena prikazuje se uz aktualnu cijenu proizvoda. Tijekom akcije može se prikazati i provjerena najniža cijena iz prethodnih 30 dana kada je taj podatak dostupan.', 'sidrena' ); ?></p></div></div>
				<div class="sid-toggle-grid"><label class="sid-toggle-card"><input type="checkbox" name="display_anchor" value="yes" <?php checked( $settings['display_anchor'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong><?php esc_html_e( 'Prikaži sidrenu cijenu', 'sidrena' ); ?></strong><small><?php esc_html_e( 'Uz aktualnu cijenu proizvoda u aktivnom katalogu.', 'sidrena' ); ?></small></span></label><label class="sid-toggle-card"><input type="checkbox" name="display_lowest_30" value="yes" <?php checked( $settings['display_lowest_30'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong><?php esc_html_e( 'Prikaži najnižu cijenu 30 dana', 'sidrena' ); ?></strong><small><?php esc_html_e( 'Samo kod aktivnog sniženja i kada je podatak provjerljiv.', 'sidrena' ); ?></small></span></label></div>
				<div class="sid-fields"><label><span><?php esc_html_e( 'Standardni referentni datum', 'sidrena' ); ?></span><input type="date" name="default_ref_date" value="<?php echo esc_attr( $settings['default_ref_date'] ); ?>"></label><label><span><?php esc_html_e( 'FMCG referentni datum', 'sidrena' ); ?></span><input type="date" name="fmcg_ref_date" value="<?php echo esc_attr( $settings['fmcg_ref_date'] ); ?>"></label><label><span><?php esc_html_e( 'Format oznake', 'sidrena' ); ?></span><select name="label_mode"><option value="date_only" <?php selected( $settings['label_mode'], 'date_only' ); ?>><?php esc_html_e( 'Cijena na 10.09.2026.', 'sidrena' ); ?></option><option value="custom" <?php selected( $settings['label_mode'], 'custom' ); ?>><?php esc_html_e( 'Vlastiti tekst', 'sidrena' ); ?></option></select></label><label><span><?php esc_html_e( 'Vlastita oznaka', 'sidrena' ); ?></span><input type="text" name="label_custom" value="<?php echo esc_attr( $settings['label_custom'] ); ?>" placeholder="Sidrena cijena (%s)"><small><?php esc_html_e( 'Koristite %s na mjestu datuma.', 'sidrena' ); ?></small></label></div><div class="sid-toggle-grid"><label class="sid-toggle-card"><input type="checkbox" name="anchor_tooltip_enabled" value="yes" <?php checked( $settings['anchor_tooltip_enabled'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong><?php esc_html_e( 'Objašnjenje na hover/fokus', 'sidrena' ); ?></strong><small><?php esc_html_e( 'Pristupačan tooltip uz sidrenu cijenu.', 'sidrena' ); ?></small></span></label></div><div class="sid-fields"><label class="sid-wide"><span><?php esc_html_e( 'Tekst objašnjenja', 'sidrena' ); ?></span><textarea name="anchor_tooltip_text" rows="3"><?php echo esc_textarea( $settings['anchor_tooltip_text'] ); ?></textarea><small><?php esc_html_e( 'Može se prevoditi kroz Polylang/WPML registrirane stringove.', 'sidrena' ); ?></small></label></div>
			</section>

			<section class="sid-card sid-settings-section"><div class="sid-settings-title"><span class="dashicons dashicons-media-spreadsheet"></span><div><h2><?php esc_html_e( 'Digitalni cjenici i arhiva', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Datoteke se objavljuju u uploads/sidrena/arhiva i ostaju javno dostupne najmanje 30 dana.', 'sidrena' ); ?></p></div></div>
				<div class="sid-toggle-grid"><label class="sid-toggle-card"><input type="checkbox" name="generate_csv" value="yes" <?php checked( $settings['generate_csv'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong>CSV</strong><small><?php esc_html_e( 'Strojno čitljiv cjenik.', 'sidrena' ); ?></small></span></label><label class="sid-toggle-card"><input type="checkbox" name="generate_xml" value="yes" <?php checked( $settings['generate_xml'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong>XML</strong><small><?php esc_html_e( 'Strojno čitljiv cjenik.', 'sidrena' ); ?></small></span></label><label class="sid-toggle-card"><input type="checkbox" name="publish_manifest" value="yes" <?php checked( $settings['publish_manifest'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong>JSON manifest</strong><small><?php esc_html_e( 'Indeks aktualnih i arhivskih datoteka s hashom.', 'sidrena' ); ?></small></span></label><label class="sid-toggle-card"><input type="checkbox" name="enable_rest_index" value="yes" <?php checked( $settings['enable_rest_index'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong>REST API</strong><small><?php esc_html_e( 'Javni indeks i aktualne maloprodajne cijene za automatizirani dohvat u realnom vremenu.', 'sidrena' ); ?></small></span></label><label class="sid-toggle-card"><input type="checkbox" name="enable_public_html" value="yes" <?php checked( $settings['enable_public_html'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong><?php esc_html_e( 'Javni HTML cjenik', 'sidrena' ); ?></strong><small><?php esc_html_e( '/sidrena-cjenik i /arhiva-sidrene-cijene koriste spremljeni snapshot, bez čitanja cijelog kataloga na svakom posjetu.', 'sidrena' ); ?></small></span></label><label class="sid-toggle-card"><input type="checkbox" name="strict_publication" value="yes" <?php checked( $settings['strict_publication'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong><?php esc_html_e( 'Stroga provjera prije objave', 'sidrena' ); ?></strong><small><?php esc_html_e( 'Ne zamjenjuje zadnji valjani cjenik ako obvezni podaci nisu potpuni.', 'sidrena' ); ?></small></span></label></div>
				<div class="sid-toggle-grid"><label class="sid-toggle-card"><input type="checkbox" name="failure_notifications" value="yes" <?php checked( $settings['failure_notifications'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong><?php esc_html_e( 'E-mail upozorenje kod problema s objavom', 'sidrena' ); ?></strong><small><?php esc_html_e( 'Šalje ograničeno upozorenje kada generiranje završi s greškama ili današnja objava kasni nakon planiranog vremena.', 'sidrena' ); ?></small></span></label></div>
				<div class="sid-fields"><label><span><?php esc_html_e( 'E-mail za upozorenja', 'sidrena' ); ?></span><input type="email" maxlength="190" name="failure_email" value="<?php echo esc_attr( $settings['failure_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email', '' ) ); ?>"><small><?php esc_html_e( 'Ako je prazno, koristi se e-mail poslovnog subjekta, a zatim WordPress administratorski e-mail.', 'sidrena' ); ?></small></label><label><span><?php esc_html_e( 'Vrijeme dnevnog generiranja', 'sidrena' ); ?></span><input type="time" name="generation_time" value="<?php echo esc_attr( $settings['generation_time'] ); ?>"><small><?php esc_html_e( 'Preporuka: dovoljno prije 08:00.', 'sidrena' ); ?></small></label><label><span><?php esc_html_e( 'Čuvanje arhive (dana)', 'sidrena' ); ?></span><input type="number" min="30" max="3650" name="retention_days" value="<?php echo esc_attr( $settings['retention_days'] ); ?>"><small><?php esc_html_e( 'Plugin ne dopušta manje od 30 dana.', 'sidrena' ); ?></small></label><label><span><?php esc_html_e( 'CSV razdjelnik', 'sidrena' ); ?></span><select name="csv_delimiter"><option value=";" <?php selected( $settings['csv_delimiter'], ';' ); ?>>;</option><option value="," <?php selected( $settings['csv_delimiter'], ',' ); ?>>,</option><option value="\t" <?php selected( $settings['csv_delimiter'], '\t' ); ?>>TAB</option></select></label></div>
			</section>

			<section class="sid-card sid-settings-section"><div class="sid-settings-title"><span class="dashicons dashicons-chart-line"></span><div><h2><?php esc_html_e( 'Povijest cijena', 'sidrena' ); ?></h2><p><?php echo Sidrena_Utils::is_woocommerce_edition() ? esc_html__( 'Ovo izdanje prati dostupnu povijest WooCommerce proizvoda i Sidrena usluga.', 'sidrena' ) : esc_html__( 'WordPress izdanje čuva aktualnu, sidrenu i potvrđenu 30-dnevnu vrijednost izravno u vlastitom katalogu, uz povijest Sidrena usluga.', 'sidrena' ); ?></p></div></div><div class="sid-toggle-grid"><label class="sid-toggle-card"><input type="checkbox" name="track_price_history" value="yes" <?php checked( $settings['track_price_history'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong><?php esc_html_e( 'Prati dostupnu automatsku povijest cijena', 'sidrena' ); ?></strong><small><?php echo Sidrena_Utils::is_woocommerce_edition() ? esc_html__( 'Prati WooCommerce proizvode i Sidrena usluge.', 'sidrena' ) : esc_html__( 'Prati Sidrena usluge; proizvodi koriste vlastiti WordPress katalog.', 'sidrena' ); ?></small></span></label></div></section>

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
				<form class="sid-card sid-tool-card" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="sidrena_import_anchor"><?php wp_nonce_field( 'sidrena_import_anchor' ); ?>
					<div class="sid-tool-icon"><span class="dashicons dashicons-tag"></span></div><h2><?php esc_html_e( 'Uvoz sidrenih cijena', 'sidrena' ); ?></h2><p><?php esc_html_e( 'CSV stupci: sku, anchor_price, anchor_date, reference_group. Prihvaća ; , ili TAB.', 'sidrena' ); ?></p><input class="sid-file-input" type="file" name="anchor_csv" accept=".csv,text/csv,text/plain" required><button class="button button-primary sid-primary" type="submit"><?php esc_html_e( 'Uvezi sidrene cijene', 'sidrena' ); ?></button>
				</form>
				<form class="sid-card sid-tool-card" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="sidrena_import_location_data"><?php wp_nonce_field( 'sidrena_import_location_data' ); ?>
					<div class="sid-tool-icon"><span class="dashicons dashicons-location-alt"></span></div><h2><?php esc_html_e( 'Raspoloživost i cijena po lokaciji', 'sidrena' ); ?></h2><p><?php esc_html_e( 'CSV stupci: location_id, product_id ili sku, price, anchor_price, availability. Prazna cijena za lokaciju koristi osnovnu WooCommerce vrijednost.', 'sidrena' ); ?></p><input class="sid-file-input" type="file" name="location_csv" accept=".csv,text/csv,text/plain" required><button class="button button-primary sid-primary" type="submit"><?php esc_html_e( 'Uvezi lokacijske podatke', 'sidrena' ); ?></button>
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
			<section class="sid-card sid-tool-card"><div class="sid-tool-icon"><span class="dashicons dashicons-chart-area"></span></div><h2><?php esc_html_e( 'Izvoz evidencije cijena', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Izvezite dostupnu internu evidenciju WooCommerce promjena kada je integracija aktivna, usluga i lokacijskih podataka.', 'sidrena' ); ?></p><a class="button sid-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_export_price_history' ), 'sidrena_export_price_history' ) ); ?>"><?php esc_html_e( 'Preuzmi povijest CSV', 'sidrena' ); ?></a></section>
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
			<div class="sid-table-wrap"><table class="widefat striped sid-log-table"><thead><tr><th><?php esc_html_e( 'Vrijeme', 'sidrena' ); ?></th><th><?php esc_html_e( 'Događaj', 'sidrena' ); ?></th><th><?php esc_html_e( 'Status', 'sidrena' ); ?></th><th><?php esc_html_e( 'Opis', 'sidrena' ); ?></th></tr></thead><tbody>
			<?php if ( empty( $rows ) ) : ?><tr><td colspan="4"><?php esc_html_e( 'Dnevnik je zasad prazan.', 'sidrena' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $rows as $row ) : ?>
			<?php $status_labels = array( 'success' => __( 'Uspješno', 'sidrena' ), 'warning' => __( 'Upozorenje', 'sidrena' ), 'error' => __( 'Greška', 'sidrena' ) ); $status = sanitize_key( $row['status'] ?? '' ); ?>
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
			<?php $this->rule_card( '02', __( 'CSV/XML cjenik i 30-dnevna arhiva', 'sidrena' ), __( 'Trgovci koji imaju mrežnu stranicu ažuriraju cjenik proizvoda jednom dnevno radnim danom, najkasnije do 08:00; pružatelji usluga pri promjeni cijene, najkasnije do 08:00 na dan stupanja promjene na snagu. Prethodne objave moraju ostati javno dostupne najmanje 30 dana, a tehničko rješenje mora omogućiti automatizirani dohvat aktualnih cijena u realnom vremenu.', 'sidrena' ), 'https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html', 'NN 101/2026, 1213' ); ?>
			<?php $this->rule_card( '03', __( 'Detaljna pojašnjenja Ministarstva', 'sidrena' ), __( 'Pojašnjenja pokrivaju webshopove i informativne web-stranice, zasebne podatke po poslovnici, stvarnu raspoloživost po lokaciji, novouvedene proizvode i usluge, promjene šifre/naziva, akcije, usluge bez unaprijed fiksne cijene te strukturu digitalnih cjenika. Profil na društvenoj mreži sam po sebi ne smatra se mrežnom stranicom.', 'sidrena' ), 'https://mingo.gov.hr/print.aspx?id=10440&url=print', __( 'Ministarstvo gospodarstva · 22.09.2026.', 'sidrena' ) ); ?>
			<?php $this->rule_card( '04', __( 'Najniža cijena u prethodnih 30 dana', 'sidrena' ), __( 'Kod posebnih oblika prodaje robe referentna je najniža cijena primjenjivana za isti proizvod tijekom 30 dana prije početka sniženja. Za lako pokvarljivu robu i robu kojoj brzo istječe rok vrijede posebna pravila; pri takvom sniženju mora biti istaknut i krajnji rok uporabe.', 'sidrena' ), 'https://narodne-novine.nn.hr/clanci/sluzbeni/2026_06_59_728.html', 'NN 59/2026, 728' ); ?>
			<?php $this->rule_card( '05', __( 'Maloprodajna, jedinična i cijena usluge', 'sidrena' ), __( 'NN 105/2026 objavljen je 18.09.2026. i stupa na snagu osmoga dana od objave. Uređuje jasan prikaz maloprodajne i jedinične cijene te iznimke. Za usluge traži lako dostupan cjenik, naziv, vrstu i opseg usluge te uključivanje pripadajućih troškova u cijenu; cijena ugradbene ili zamjenske robe prikazuje se uz pripadajuću uslugu.', 'sidrena' ), 'https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_105_1270.html', 'NN 105/2026, 1270' ); ?>
			<section class="sid-card sid-rule-card sid-rule-future"><div class="sid-rule-number">06</div><div><span class="sid-rule-tag"><?php esc_html_e( 'Praćenje promjena', 'sidrena' ); ?></span><h3><?php esc_html_e( 'Bazna cijena u Zakonu od 17.11.2026.', 'sidrena' ); ?></h3><p><?php esc_html_e( 'Članak 7. stavci 1. do 9. izmijenjenog Zakona počinju se primjenjivati 17.11.2026. i uvode obvezu isticanja bazne cijene te objave važećih cjenika proizvoda na mrežnim stranicama. Način isticanja i objave uređuje se pravilnicima, pa Sidrena te kasnije obveze prikazuje odvojeno od mjera iz NN 101/2026.', 'sidrena' ); ?></p><a href="https://narodne-novine.nn.hr/clanci/sluzbeni/2026_06_59_728.html" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Otvori službeni izvor', 'sidrena' ); ?><span class="dashicons dashicons-external"></span></a></div></section>
		</div>
		<div class="sid-page-head sid-page-head--compact"><div><span class="sid-kicker"><?php esc_html_e( 'Praktična pojašnjenja', 'sidrena' ); ?></span><h2><?php esc_html_e( 'HOK i Državni inspektorat', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Ovi izvori pomažu u primjeni i transparentnosti, ali ne zamjenjuju primarni tekst propisa u Narodnim novinama.', 'sidrena' ); ?></p></div></div>
		<div class="sid-grid sid-grid-2">
			<?php $this->rule_card( 'A', __( 'HOK — informacije za obrtnike', 'sidrena' ), __( 'HOK sažima obvezu isticanja dodatne/sidrene cijene od 1.10.2026., uključujući isticanje na mrežnim stranicama i referentne datume za novobuhvaćene te ranije obuhvaćene kategorije.', 'sidrena' ), 'https://www.hok.hr/novosti-iz-hok/dodatna-cijena-i-objava-cjenika-od-1-listopada-2026-najvaznije-informacije', 'Hrvatska obrtnička komora · 18.09.2026.' ); ?>
			<?php $this->rule_card( 'B', __( 'DIRH — identitet i kontakt trgovca', 'sidrena' ), __( 'Državni inspektorat podsjeća da prije sklapanja ugovora na daljinu potrošaču trebaju biti jasno dostupni podaci o trgovcu, uključujući naziv i sjedište, telefon i e-mail. Sidrena te podatke može prikazati uz Objavu cjenika, odvojeno od CSV/XML stupaca.', 'sidrena' ), 'https://dirh.gov.hr/print.aspx?id=619&url=print', 'Državni inspektorat' ); ?>
		</div>
		<section class="sid-card sid-note"><div class="sid-note-icon"><span class="dashicons dashicons-info-outline"></span></div><div><h2><?php esc_html_e( 'Dvije različite “30 dana” obveze', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Arhiva cjenika i najniža cijena prije sniženja nisu ista stvar. Sidrena vodi oboje odvojeno: javne CSV/XML objave čuva najmanje 30 dana, a internu povijest proizvoda i usluga koristi kao tehničku podlogu za 30-dnevnu referencu prije sniženja.', 'sidrena' ); ?></p></div></section>
		<?php
	}

	private function rule_card( $number, $title, $text, $url, $source ) {
		?><section class="sid-card sid-rule-card"><div class="sid-rule-number"><?php echo esc_html( $number ); ?></div><div><span class="sid-rule-tag"><?php echo esc_html( $source ); ?></span><h3><?php echo esc_html( $title ); ?></h3><p><?php echo esc_html( $text ); ?></p><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Otvori službeni izvor', 'sidrena' ); ?><span class="dashicons dashicons-external"></span></a></div></section><?php
	}

	public function save_settings() {
		$this->guard_post( 'sidrena_save_settings' );
		$old = Sidrena_Utils::settings();

		$business_mode = sanitize_key( $this->post_value( 'business_mode', 'mixed' ) );
		if ( ! in_array( $business_mode, array( 'products', 'services', 'mixed' ), true ) ) {
			$business_mode = 'mixed';
		}

		$csv_delimiter = $this->post_value( 'csv_delimiter', ';' );
		if ( ! in_array( $csv_delimiter, array( ';', ',', '\t' ), true ) ) {
			$csv_delimiter = ';';
		}

		$generation_time = sanitize_text_field( $this->post_value( 'generation_time', '06:30' ) );
		if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $generation_time ) ) {
			$generation_time = '06:30';
		}

		$label_mode = sanitize_key( $this->post_value( 'label_mode', 'date_only' ) );
		if ( ! in_array( $label_mode, array( 'date_only', 'custom' ), true ) ) {
			$label_mode = 'date_only';
		}
		$label_custom = sanitize_text_field( $this->post_value( 'label_custom', 'Cijena na %s' ) );
		if ( '' === trim( $label_custom ) ) {
			$label_custom = 'Cijena na %s';
		}
		$tooltip_text = sanitize_textarea_field( $this->post_value( 'anchor_tooltip_text', $old['anchor_tooltip_text'] ?? '' ) );

		$new = array(
			'business_mode'       => $business_mode,
			'business_name'       => sanitize_text_field( $this->post_value( 'business_name', '' ) ),
			'business_address'    => sanitize_text_field( $this->post_value( 'business_address', '' ) ),
			'business_oib'        => Sidrena_Utils::sanitize_oib( $this->post_value( 'business_oib', '' ) ),
			'business_email'      => sanitize_email( $this->post_value( 'business_email', '' ) ),
			'business_phone'      => Sidrena_Utils::sanitize_business_phone( $this->post_value( 'business_phone', '' ) ),
			'business_registry'   => sanitize_text_field( $this->post_value( 'business_registry', '' ) ),
			'business_registry_number' => sanitize_text_field( $this->post_value( 'business_registry_number', '' ) ),
			'business_vat_id'     => sanitize_text_field( $this->post_value( 'business_vat_id', '' ) ),
			'business_supervisory_authority' => sanitize_text_field( $this->post_value( 'business_supervisory_authority', '' ) ),
			'show_business_identity' => isset( $_POST['show_business_identity'] ) ? 'yes' : 'no',
			'display_anchor'      => isset( $_POST['display_anchor'] ) ? 'yes' : 'no',
			'display_lowest_30'   => isset( $_POST['display_lowest_30'] ) ? 'yes' : 'no',
			'label_mode'          => $label_mode,
			'label_custom'        => $label_custom,
			'anchor_tooltip_enabled' => isset( $_POST['anchor_tooltip_enabled'] ) ? 'yes' : 'no',
			'anchor_tooltip_text' => $tooltip_text,
			'default_ref_date'    => $this->date( $this->post_value( 'default_ref_date', '2026-09-10' ), '2026-09-10' ),
			'fmcg_ref_date'       => $this->date( $this->post_value( 'fmcg_ref_date', '2025-05-02' ), '2025-05-02' ),
			'generate_csv'        => isset( $_POST['generate_csv'] ) ? 'yes' : 'no',
			'generate_xml'        => isset( $_POST['generate_xml'] ) ? 'yes' : 'no',
			'csv_delimiter'       => $csv_delimiter,
			'generation_time'     => $generation_time,
			'retention_days'      => max( 30, min( 3650, absint( $this->post_value( 'retention_days', 45 ) ) ) ),
			'enable_rest_index'   => isset( $_POST['enable_rest_index'] ) ? 'yes' : 'no',
			'publish_manifest'    => isset( $_POST['publish_manifest'] ) ? 'yes' : 'no',
			'enable_public_html'   => isset( $_POST['enable_public_html'] ) ? 'yes' : 'no',
			'strict_publication'   => isset( $_POST['strict_publication'] ) ? 'yes' : 'no',
			'track_price_history' => isset( $_POST['track_price_history'] ) ? 'yes' : 'no',
			'failure_notifications' => isset( $_POST['failure_notifications'] ) ? 'yes' : 'no',
			'failure_email'       => sanitize_email( $this->post_value( 'failure_email', '' ) ),
		);

		update_option( 'sidrena_settings', $new, false );
		wp_clear_scheduled_hook( 'sidrena_daily_generation' );
		$scheduled = wp_schedule_event( Sidrena_Utils::schedule_timestamp( $new['generation_time'] ), 'daily', 'sidrena_daily_generation' );
		Sidrena_Pricelist::queue_regeneration();
		Sidrena_Audit::log(
			'settings_save',
			false === $scheduled || is_wp_error( $scheduled ) ? 'warning' : 'success',
			false === $scheduled || is_wp_error( $scheduled ) ? __( 'Sidrena postavke su spremljene, ali dnevno generiranje nije ponovno zakazano.', 'sidrena' ) : __( 'Sidrena postavke su spremljene.', 'sidrena' ),
			array( 'generation_time' => $new['generation_time'], 'retention_days' => $new['retention_days'] )
		);
		$this->redirect( 'settings', false === $scheduled || is_wp_error( $scheduled ) ? 'settings_saved_cron_warning' : 'saved' );
	}

	public function save_locations() {
		$this->guard_post( 'sidrena_save_locations' );
		$input = isset( $_POST['locations'] ) && is_array( $_POST['locations'] ) ? wp_unslash( $_POST['locations'] ) : array();
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
				'kind'     => $kind ?: 'objekt',
				'address'  => $address,
				'code'     => $code ?: '01',
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
		while ( ( $row = fgetcsv( $resource, 0, $delimiter ) ) !== false ) {
			++$processed;
			$sku = isset( $row[ $map['sku'] ] ) ? sanitize_text_field( $row[ $map['sku'] ] ) : '';
			if ( ! $sku ) {
				++$skipped;
				continue;
			}
			$product_id = Sidrena_Utils::find_product_id_by_code( $sku );
			if ( ! $product_id ) {
				++$skipped;
				continue;
			}
			++$updated;
			$price = isset( $row[ $map['anchor_price'] ] ) ? Sidrena_Utils::decimal( $row[ $map['anchor_price'] ] ) : '';
			if ( '' === $price ) {
				delete_post_meta( $product_id, '_sidrena_anchor_price' );
			} else {
				update_post_meta( $product_id, '_sidrena_anchor_price', $price );
			}
			if ( isset( $map['anchor_date'], $row[ $map['anchor_date'] ] ) ) {
				$date       = sanitize_text_field( $row[ $map['anchor_date'] ] );
				$valid_date = Sidrena_Utils::sanitize_date( $date );
				if ( $valid_date ) {
					update_post_meta( $product_id, '_sidrena_anchor_date', $valid_date );
				} elseif ( '' === $date ) {
					delete_post_meta( $product_id, '_sidrena_anchor_date' );
				}
			}
			if ( isset( $map['reference_group'], $row[ $map['reference_group'] ] ) ) {
				$group = sanitize_key( $row[ $map['reference_group'] ] );
				if ( in_array( $group, array( 'standard', 'fmcg', 'custom' ), true ) ) {
					update_post_meta( $product_id, '_sidrena_reference_group', $group );
				}
			}
		}
		fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		Sidrena_Pricelist::queue_regeneration();
		Sidrena_Audit::log(
			'anchor_import',
			'success',
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
		while ( ( $row = fgetcsv( $resource, 0, $delimiter ) ) !== false ) {
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
			if ( ! $product ) {
				++$skipped;
				continue;
			}
			$availability = sanitize_key( remove_accents( (string) ( $row[ $map['availability'] ] ?? '' ) ) );
			$availability = 'dostupno' === $availability ? 'dostupno' : ( 'nedostupno' === $availability ? 'nedostupno' : '' );
			if ( '' === $availability ) {
				++$skipped;
				continue;
			}
			$price        = isset( $map['price'] ) ? Sidrena_Utils::decimal( $row[ $map['price'] ] ?? '' ) : '';
			$anchor_price = isset( $map['anchor_price'] ) ? Sidrena_Utils::decimal( $row[ $map['anchor_price'] ] ?? '' ) : '';
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
			sprintf( __( 'Uvoz lokacijskih podataka: %1$d obrađeno, %2$d ažurirano, %3$d preskočeno.', 'sidrena' ), $processed, $updated, $skipped ),
			array( 'processed' => $processed, 'updated' => $updated, 'skipped' => $skipped )
		);
		$this->redirect( 'tools', 'location_imported' );
	}

	private function open_uploaded_csv( $field ) {
		if ( empty( $_FILES[ $field ] ) || ! is_array( $_FILES[ $field ] ) ) {
			return new WP_Error( 'upload_missing' );
		}
		$file = $_FILES[ $field ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated before use.
		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'upload_error' );
		}
		if ( (int) ( $file['size'] ?? 0 ) > 5 * MB_IN_BYTES ) {
			return new WP_Error( 'upload_too_large' );
		}
		$filename = sanitize_file_name( wp_unslash( $file['name'] ?? '' ) );
		if ( 'csv' !== strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
			return new WP_Error( 'upload_extension' );
		}
		$contents = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents || '' === $contents || false !== strpos( $contents, "\0" ) ) {
			return new WP_Error( 'upload_empty' );
		}
		$contents = Sidrena_Utils::normalize_text_encoding( $contents );
		if ( '' === $contents ) {
			return new WP_Error( 'upload_encoding' );
		}
		$resource = fopen( 'php://temp', 'w+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $resource ) {
			return new WP_Error( 'upload_open' );
		}
		fwrite( $resource, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
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

	private function enforce_csv_row_limit( $resource, $delimiter, $row_limit = 50000 ) {
		$row_limit = min( 50000, max( 1, absint( $row_limit ) ) );
		$count     = 0;
		while ( is_resource( $resource ) && false !== ( $row = fgetcsv( $resource, 0, $delimiter ) ) ) {
			unset( $row );
			++$count;
			if ( $count > $row_limit ) {
				return new WP_Error( 'upload_row_limit', __( 'CSV ima više od dopuštenih 50.000 redaka.', 'sidrena' ) );
			}
		}

		if ( ! is_resource( $resource ) ) {
			return new WP_Error( 'upload_open' );
		}
		rewind( $resource );
		if ( false === fgetcsv( $resource, 0, $delimiter ) ) {
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
			$this->safe_fputcsv( $out, array( Sidrena_Utils::get_product_code( $item ), $item->get_name(), '', '', get_post_meta( $item->get_id(), '_sidrena_reference_group', true ) ?: 'standard' ), ';' );
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
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT product_id, variation_id, price, regular_price, sale_price, recorded_at, source FROM {$product_table} ORDER BY id ASC LIMIT %d OFFSET %d",
					1000,
					$offset
				),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$item_id = absint( $row['variation_id'] ) ?: absint( $row['product_id'] );
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
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT service_id, price, recorded_at, source FROM {$service_table} ORDER BY id ASC LIMIT %d OFFSET %d",
					1000,
					$offset
				),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
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
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT location_id, product_id, variation_id, price, anchor_price, availability, recorded_at, source FROM {$location_table} ORDER BY id ASC LIMIT %d OFFSET %d",
					1000,
					$offset
				),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$item_id = absint( $row['variation_id'] ) ?: absint( $row['product_id'] );
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
			'missing_anchor'  => 0,
			'details_missing' => 0,
			'sale_incomplete' => 0,
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
				if ( '' === get_post_meta( $service_id, '_sidrena_service_anchor_price', true ) ) {
					++$stats['missing_anchor'];
				}
				if ( '' === trim( (string) get_post_meta( $service_id, '_sidrena_service_type', true ) ) || '' === trim( (string) get_post_meta( $service_id, '_sidrena_service_scope', true ) ) ) {
					++$stats['details_missing'];
				}
				if ( 'yes' === get_post_meta( $service_id, '_sidrena_service_sale', true ) ) {
					$reference = Sidrena_Service_History::sale_reference( $service_id );
					if ( 'incomplete' === $reference['status'] ) {
						++$stats['sale_incomplete'];
					}
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
		$missing         = 0;
		$sale_incomplete           = 0;
		$active_sales              = 0;
		$perishable_expiry_missing = 0;
		$missing_brand             = 0;
		$missing_barcode           = 0;
		$unit_price_review         = 0;
		$unit_price_missing        = 0;
		foreach ( $this->catalog_items() as $item ) {
			++$products;
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
			if ( $item->is_on_sale() ) {
				++$active_sales;
				$reference = Sidrena_History::sale_reference( $item );
				if ( 'incomplete' === $reference['status'] ) {
					++$sale_incomplete;
				}
				$exemption = sanitize_key( (string) Sidrena_Utils::product_meta_with_parent( $item, '_sidrena_sale_reference_exemption' ) );
				if ( in_array( $exemption, array( 'perishable', 'fast_expiry' ), true ) && ! Sidrena_Utils::sanitize_date( Sidrena_Utils::product_meta_with_parent( $item, '_sidrena_expiry_date' ) ) ) {
					++$perishable_expiry_missing;
				}
			}
		}


		if ( Sidrena_Utils::is_wordpress_edition() ) {
			$standalone                = Sidrena_Standalone::audit_stats();
			$products                  = $standalone['products'];
			$missing                   = $standalone['missing_anchor'];
			$missing_brand             = $standalone['missing_brand'];
			$missing_barcode           = $standalone['missing_barcode'];
			$unit_price_review         = $standalone['unit_price_review'];
			$unit_price_missing        = $standalone['unit_price_missing'];
			$active_sales              = $standalone['active_sales'];
			$sale_incomplete           = $standalone['sale_incomplete'];
			$perishable_expiry_missing = 0;
		}

		$service_stats            = $this->service_audit_stats();
		$services                 = $service_stats['services'];
		$missing_service_anchor  = $service_stats['missing_anchor'];
		$service_details_missing = $service_stats['details_missing'];
		$service_sale_incomplete = $service_stats['sale_incomplete'];
		$settings = Sidrena_Utils::settings();
		$issues   = $missing + $missing_brand + $missing_barcode + $unit_price_review + $unit_price_missing + $missing_service_anchor + $service_details_missing + $sale_incomplete + $service_sale_incomplete + $perishable_expiry_missing;
		if ( 'no' === $settings['generate_csv'] && 'no' === $settings['generate_xml'] ) {
			++$issues;
		}
		if ( ! wp_next_scheduled( 'sidrena_daily_generation' ) || ! isset( $settings['generation_time'] ) || strcmp( (string) $settings['generation_time'], '08:00' ) >= 0 ) {
			++$issues;
		}
		return array(
			'products'               => $products,
			'missing_anchor'         => $missing,
			'missing_brand'          => $missing_brand,
			'missing_barcode'        => $missing_barcode,
			'unit_price_review'      => $unit_price_review,
			'unit_price_missing'     => $unit_price_missing,
			'services'               => $services,
			'missing_service_anchor' => $missing_service_anchor,
			'service_details_missing' => $service_details_missing,
			'missing_total'          => $missing + $missing_service_anchor,
			'active_sales'           => $active_sales,
			'sale_incomplete'         => $sale_incomplete,
			'perishable_expiry_missing' => $perishable_expiry_missing,
			'service_sale_incomplete' => $service_sale_incomplete,
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
			++$page;
		} while ( count( $products ) === 100 );
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

	private function post_value( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default;
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
					'limit_response_size' => 262144,
					'headers'     => array(
						'Accept'     => 'text/csv, application/xml, text/xml, */*;q=0.1',
						'User-Agent' => 'Sidrena-Public-Check/' . SIDRENA_VERSION,
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
				sprintf( __( 'HTTP provjera javnih cjenika nije prošla: %1$d provjereno, %2$d problema.', 'sidrena' ), $checked, count( $failed ) ),
				array( 'checked' => $checked, 'failed' => array_slice( $failed, 0, 20 ) )
			);
			$this->redirect( 'files', 'public_access_failed' );
		}

		Sidrena_Audit::log(
			'public_access_check',
			'success',
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
			'dashboard'  => 'sidrena',
			'compliance' => 'sidrena-compliance',
			'catalog'    => 'sidrena-catalog',
			'extra'      => 'sidrena-extra',
			'files'      => 'sidrena-files',
			'archive'    => 'sidrena-archive',
			'locations'  => 'sidrena-locations',
			'settings'   => 'sidrena-settings',
			'tools'      => 'sidrena-tools',
			'log'        => 'sidrena-log',
			'rules'      => 'sidrena-rules',
		);
		$tab  = sanitize_key( $tab );
		$page = isset( $pages[ $tab ] ) ? $pages[ $tab ] : 'sidrena';
		$url  = add_query_arg( array( 'page' => $page, 'sid_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}
}
