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
		add_action( 'admin_post_sidrena_publish_daily_archive', array( $this, 'publish_daily_archive' ) );
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
					<?php /* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */ ?>
					<span class="sid-context-chip sid-context-chip--edition"><?php echo esc_html( sprintf( __( 'SIDRENA · %s', 'sidrena' ), $edition_name ) ); ?></span>
					<span class="sid-badge">v<?php echo esc_html( SIDRENA_VERSION ); ?></span>
				</div>
				<div class="sidrena-contextbar__right">
					<span class="sid-toolbar__rule"><?php echo esc_html( SIDRENA_RULESET ); ?></span>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-support&sidrena_section=help' ) ); ?>"><?php esc_html_e( 'Dokumentacija', 'sidrena' ); ?></a>
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

			<footer class="sidrena-footer">
				<span><?php echo esc_html( Sidrena_Utils::developer_label() ); ?> · SIDRENA <?php echo esc_html( SIDRENA_VERSION ); ?></span>
				<span><a href="mailto:<?php echo esc_attr( Sidrena_Utils::support_email() ); ?>"><?php echo esc_html( Sidrena_Utils::support_email() ); ?></a> · <a href="<?php echo esc_url( $official_url ); ?>" target="_blank" rel="noopener noreferrer">brendigo.com/sidrene-cijene</a></span>
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
			'generated_with_errors'    => array( 'warning', __( 'Osvježavanje nije potpuno uspjelo. Pogledajte razloge i ispravite podatke; posljednje valjane datoteke ostaju dostupne.', 'sidrena' ) ),
			'archive_with_errors'      => array( 'warning', __( 'Objava dnevne arhive nije potpuno uspjela. Detalji su navedeni u nastavku.', 'sidrena' ) ),
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
			'settings_invalid_email'        => array( 'error', __( 'Postavke nisu spremljene. Provjerite e-mail adresu za upozorenja.', 'sidrena' ) ),
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
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p>';
		if ( 'generated_with_errors' === $notice || 'archive_with_errors' === $notice ) {
			$option = 'archive_with_errors' === $notice ? 'sidrena_last_run' : 'sidrena_last_current_refresh';
			$this->render_publication_errors( get_option( $option, array() ) );
		}
		echo '</div>';
	}

	/** Display safe, actionable warnings from the last publication attempt. */
	private function render_publication_errors( $last ) {
		if ( ! is_array( $last ) || empty( $last['errors'] ) || ! is_array( $last['errors'] ) ) {
			return;
		}
		$errors = array_values( array_filter( array_map( 'strval', $last['errors'] ) ) );
		if ( ! $errors ) {
			return;
		}
		echo '<p><strong>' . esc_html__( 'Razlozi neuspjeha — ispravite podatke i pokušajte ponovno:', 'sidrena' ) . '</strong></p><ul class="ul-disc">';
		foreach ( array_slice( $errors, 0, 8 ) as $error ) {
			echo '<li>' . esc_html( $error ) . '</li>';
		}
		echo '</ul>';
		if ( count( $errors ) > 8 ) {
			/* translators: %d: remaining publication errors. */
			echo '<p>' . esc_html( sprintf( __( 'Još %d upozorenja potražite u Dnevniku.', 'sidrena' ), count( $errors ) - 8 ) ) . '</p>';
		}
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=sidrena-catalog' ) ) . '">' . esc_html__( 'Provjeri katalog', 'sidrena' ) . '</a> · <a href="' . esc_url( admin_url( 'admin.php?page=sidrena-support&sidrena_section=log' ) ) . '">' . esc_html__( 'Otvori Dnevnik', 'sidrena' ) . '</a></p>';
	}

	private function support_tab() {
		$pdf_url         = Sidrena_Utils::support_pdf_url();
		$email_url       = Sidrena_Utils::support_email_url();
		$whatsapp_url    = Sidrena_Utils::whatsapp_url();
		$install_url     = Sidrena_Utils::installation_service_url();
		?>
		<div class="sid-page-head">
			<div>
				<span class="sid-kicker"><?php echo esc_html( Sidrena_Utils::developer_label() ); ?></span>
				<h2><?php esc_html_e( 'Pomoć', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Upute, propisi, dijagnostika i tehnička podrška nalaze se na jednom mjestu.', 'sidrena' ); ?></p>
			</div>
			<div class="sid-head-inline-actions">
				<a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-support&sidrena_section=help' ) ); ?>"><?php esc_html_e( 'Upute', 'sidrena' ); ?></a>
				<a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-support&sidrena_section=rules' ) ); ?>"><?php esc_html_e( 'Propisi', 'sidrena' ); ?></a>
				<a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-support&sidrena_section=tools' ) ); ?>"><?php esc_html_e( 'Dijagnostika', 'sidrena' ); ?></a>
				<a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-support&sidrena_section=log' ) ); ?>"><?php esc_html_e( 'Dnevnik', 'sidrena' ); ?></a>
			</div>
		</div>

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
				<?php /* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */ ?>
				<p><?php echo esc_html( sprintf( __( 'Samo ako želite da brendigo odradi instalaciju i početno postavljanje: %s jednokratno.', 'sidrena' ), Sidrena_Utils::installation_price() ) ); ?></p>
				<?php /* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */ ?>
				<a class="button button-primary sid-primary" href="<?php echo esc_url( $install_url ); ?>"><?php echo esc_html( sprintf( __( 'Zatraži postavljanje - %s', 'sidrena' ), Sidrena_Utils::installation_price() ) ); ?></a>
			</section>

		</div>

		<?php
	}

	private function about_tab() {
		?>
		<div class="sid-page-head">
			<div>
				<span class="sid-kicker"><?php esc_html_e( 'O nama', 'sidrena' ); ?></span>
				<h2><?php echo esc_html( Sidrena_Utils::developer_label() ); ?></h2>
				<p><?php esc_html_e( 'Razvoj, održavanje i podrška za oba SIDRENA izdanja.', 'sidrena' ); ?></p>
			</div>
			<a class="button sid-secondary" href="https://brendigo.com/" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-external"></span>brendigo.com</a>
		</div>
		<div class="sid-grid sid-grid-2">
			<section class="sid-card sid-contact-card">
				<span class="sid-kicker"><?php esc_html_e( 'Autor', 'sidrena' ); ?></span>
				<h2><?php echo esc_html( Sidrena_Utils::developer_label() ); ?></h2>
				<p><?php esc_html_e( 'SIDRENA je razvijena kao WordPress rješenje za upravljanje sidrenim/referentnim cijenama, javnim cjenicima i arhivom objava.', 'sidrena' ); ?></p>
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
			<div><h2><?php esc_html_e( 'Dva odvojena izdanja', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Samostalno izdanje koristi vlastiti katalog, a izdanje za web trgovinu koristi postojeći katalog trgovine. Istodobno može biti aktivno samo jedno izdanje.', 'sidrena' ); ?></p></div>
		</section>
		<?php
	}


	private function help_tab() {
		$woo = Sidrena_Utils::is_woocommerce_edition();
		?>
		<div class="sid-page-head">
			<div>
				<span class="sid-kicker"><?php esc_html_e( 'Dokumentacija', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Upute za korištenje SIDRENA', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Praktičan redoslijed od instalacije do provjere javnih cjenika za aktivno SIDRENA izdanje.', 'sidrena' ); ?></p>
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
					<li><?php esc_html_e( 'Provjerite zaključani pravni ruleset, format CSV-a, arhivu i vrijeme automatskog generiranja.', 'sidrena' ); ?></li>
				</ol>
				<a class="sid-inline-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-settings' ) ); ?>"><?php esc_html_e( 'Otvori Postavke', 'sidrena' ); ?></a>
			</section>

			<section class="sid-card">
				<span class="sid-kicker"><?php esc_html_e( '2. Katalog', 'sidrena' ); ?></span>
				<h2><?php echo $woo ? esc_html__( 'Katalog web trgovine', 'sidrena' ) : esc_html__( 'Samostalni katalog', 'sidrena' ); ?></h2>
				<p><?php echo $woo ? esc_html__( 'SIDRENA koristi postojeće proizvode i varijacije web trgovine kao izvor podataka.', 'sidrena' ) : esc_html__( 'Samostalno SIDRENA izdanje koristi vlastiti katalog proizvoda koji možete unositi ručno ili uvesti CSV/XML datotekom.', 'sidrena' ); ?></p>
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
				<a class="sid-inline-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-support&sidrena_section=tools' ) ); ?>"><?php esc_html_e( 'Otvori Alate', 'sidrena' ); ?></a>
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
		$stats            = $this->audit_stats();
		$last             = get_option( 'sidrena_last_run', array() );
		$current_refresh  = get_option( 'sidrena_last_current_refresh', array() );
		$settings         = Sidrena_Utils::settings();
		$archive_stats    = Sidrena_Utils::archive_stats();
		$integrity        = Sidrena_Utils::archive_integrity();
		$retention        = Sidrena_Utils::archive_retention_status();
		$next_run         = wp_next_scheduled( 'sidrena_daily_generation' );
		$product_history  = class_exists( 'Sidrena_History' ) ? Sidrena_History::count_rows() : 0;
		$service_history  = Sidrena_Service_History::count_rows();
		$location_history = class_exists( 'Sidrena_Location_History' ) ? Sidrena_Location_History::count_rows() : 0;
		$history_total    = $product_history + $service_history + $location_history;
		$total_items      = max( 0, absint( $stats['products'] ) + absint( $stats['services'] ) );
		$anchor_ready     = max( 0, $total_items - absint( $stats['missing_total'] ) );
		$health_checks    = $this->health_checks( $stats, $last, $settings );
		$health_issues    = count(
			array_filter(
				$health_checks,
				static function ( $check ) {
				return empty( $check[0] );
				}
			)
		);
		$health_total     = max( 1, count( $health_checks ) );
		$health_score     = max( 0, min( 100, (int) round( ( ( $health_total - $health_issues ) / $health_total ) * 100 ) ) );
		$is_ready         = 0 === $health_issues;

		$product_changes = class_exists( 'Sidrena_History' ) ? Sidrena_History::recent_changes( 5 ) : array();
		$changes         = array_merge( $product_changes, Sidrena_Service_History::recent_changes( 5 ) );
		usort(
			$changes,
			static function ( $a, $b ) {
				return strcmp( (string) $b['recorded_at'], (string) $a['recorded_at'] );
			}
		);
		$changes = array_slice( $changes, 0, 5 );

		$dashboard = array(
			'stats'           => $stats,
			'last'            => $last,
			'settings'        => $settings,
			'archive_stats'   => $archive_stats,
			'integrity'       => $integrity,
			'retention'       => $retention,
			'next_run'        => $next_run,
			'history_total'   => $history_total,
			'total_items'     => $total_items,
			'anchor_ready'    => $anchor_ready,
			'health_checks'   => $health_checks,
			'health_issues'   => $health_issues,
			'health_score'    => $health_score,
			'is_ready'        => $is_ready,
			'changes'         => $changes,
			'locations'       => Sidrena_Utils::locations(),
		);

		if ( Sidrena_Utils::is_woocommerce_edition() ) {
			$this->woocommerce_dashboard( $dashboard );
			return;
		}

		$this->wordpress_dashboard( $dashboard );
	}


	private function wordpress_dashboard( $data ) {
		$last_ts       = ! empty( $data['last']['generated_at'] ) ? strtotime( (string) $data['last']['generated_at'] ) : 0;
		$published_pct = $data['total_items'] > 0 ? (int) round( ( $data['anchor_ready'] / $data['total_items'] ) * 100 ) : 0;
		$anchor_caption = sprintf(
			/* translators: %d: number of catalog items with an anchor price. */
			__( '%d sa sidrenom cijenom', 'sidrena' ),
			$data['anchor_ready']
		);
		$published_caption = sprintf(
			/* translators: 1: number of ready catalog items, 2: total number of catalog items. */
			__( '%1$d / %2$d stavki', 'sidrena' ),
			$data['anchor_ready'],
			$data['total_items']
		);
		?>
		<div class="sid-reference-dashboard sid-reference-dashboard--wordpress">
			<div class="sid-page-head sid-reference-page-head">
				<div>
					<span class="sid-kicker"><?php esc_html_e( 'Nadzorna ploča', 'sidrena' ); ?></span>
					<h2><?php esc_html_e( 'Nadzorna ploča', 'sidrena' ); ?></h2>
					<p><?php esc_html_e( 'Pregled kataloga, objave cjenika, arhive i tehničke spremnosti.', 'sidrena' ); ?></p>
				</div>
				<div class="sid-head-inline-actions">
					<a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-catalog' ) ); ?>"><span class="dashicons dashicons-list-view"></span><?php esc_html_e( 'Upravljaj katalogom', 'sidrena' ); ?></a>
					<a class="button button-primary sid-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_generate' ), 'sidrena_generate' ) ); ?>"><span class="dashicons dashicons-controls-play"></span><?php esc_html_e( 'Objavi cjenik', 'sidrena' ); ?></a>
				</div>
			</div>

			<div class="sid-reference-metrics">
				<?php $this->dashboard_metric( __( 'Ukupno stavki', 'sidrena' ), $data['total_items'], 'dashicons-media-document', $anchor_caption, 'blue' ); ?>
				<?php $this->dashboard_metric( __( 'Objavljeno s cijenama', 'sidrena' ), $published_pct . '%', 'dashicons-money-alt', $published_caption, 'ok' ); ?>
				<?php $this->dashboard_metric( __( 'Posljednja objava', 'sidrena' ), $last_ts ? wp_date( 'd.m.Y.', $last_ts ) : '—', 'dashicons-calendar-alt', $last_ts ? wp_date( 'H:i', $last_ts ) : __( 'još nema objave', 'sidrena' ), 'purple' ); ?>
				<?php $this->dashboard_metric( __( 'Cijene ažurirane', 'sidrena' ), $data['history_total'], 'dashicons-chart-line', __( 'zapisa u povijesti', 'sidrena' ), 'teal' ); ?>
			</div>

			<div class="sid-reference-grid sid-reference-grid--wp-main">
				<section class="sid-card sid-reference-panel sid-reference-quick">
					<div class="sid-section-head"><div><h2><span class="dashicons dashicons-rocket"></span><?php esc_html_e( 'Brze akcije', 'sidrena' ); ?></h2></div></div>
					<div class="sid-reference-action-grid">
						<a class="sid-reference-action sid-reference-action--primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_generate' ), 'sidrena_generate' ) ); ?>"><span class="dashicons dashicons-upload"></span><strong><?php esc_html_e( 'Objavi cjenik', 'sidrena' ); ?></strong><small><?php esc_html_e( 'Ažuriraj javne datoteke', 'sidrena' ); ?></small></a>
						<a class="sid-reference-action" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-support&sidrena_section=tools' ) ); ?>"><span class="dashicons dashicons-migrate"></span><strong><?php esc_html_e( 'Uvoz CSV / XML', 'sidrena' ); ?></strong><small><?php esc_html_e( 'Uvezi podatke u katalog', 'sidrena' ); ?></small></a>
						<a class="sid-reference-action" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-settings' ) ); ?>"><span class="dashicons dashicons-admin-generic"></span><strong><?php esc_html_e( 'Postavke', 'sidrena' ); ?></strong><small><?php esc_html_e( 'Lokacije, prikaz i pravila', 'sidrena' ); ?></small></a>
						<a class="sid-reference-action" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-catalog' ) ); ?>"><span class="dashicons dashicons-database"></span><strong><?php esc_html_e( 'Upravljaj proizvodima', 'sidrena' ); ?></strong><small><?php esc_html_e( 'Katalog proizvoda i usluga', 'sidrena' ); ?></small></a>
					</div>
				</section>

				<section class="sid-card sid-reference-panel sid-reference-readiness">
					<div class="sid-section-head"><div><h2><span class="dashicons dashicons-shield-alt"></span><?php esc_html_e( 'Tehnička spremnost', 'sidrena' ); ?></h2></div><span class="sid-status-pill <?php echo $data['is_ready'] ? 'is-ok' : 'is-warn'; ?>"><?php echo $data['is_ready'] ? esc_html__( 'Sve u redu', 'sidrena' ) : esc_html__( 'Provjeriti', 'sidrena' ); ?></span></div>
					<div class="sid-reference-readiness-score"><strong><?php echo esc_html( $data['health_score'] . '%' ); ?></strong><span><?php esc_html_e( 'uspješnih provjera', 'sidrena' ); ?></span></div>
					<?php $this->health_list( $data['stats'], $data['last'], $data['settings'] ); ?>
				</section>
			</div>

			<div class="sid-reference-grid sid-reference-grid--wp-secondary">
				<section class="sid-card sid-reference-panel">
					<div class="sid-section-head"><div><h2><span class="dashicons dashicons-database"></span><?php esc_html_e( 'Zdravlje arhive', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Povijest cijena i javnih objava.', 'sidrena' ); ?></p></div><span class="sid-status-pill <?php echo $data['integrity']['ok'] ? 'is-ok' : 'is-warn'; ?>"><?php echo $data['integrity']['ok'] ? esc_html__( 'Uredno', 'sidrena' ) : esc_html__( 'Provjera', 'sidrena' ); ?></span></div>
					<div class="sid-reference-mini-metrics"><div><strong><?php echo esc_html( number_format_i18n( $data['history_total'] ) ); ?></strong><span><?php esc_html_e( 'zapisa cijena', 'sidrena' ); ?></span></div><div><strong><?php echo esc_html( number_format_i18n( $data['archive_stats']['files'] ) ); ?></strong><span><?php esc_html_e( 'objava u arhivi', 'sidrena' ); ?></span></div><div><strong><?php echo esc_html( absint( $data['settings']['retention_days'] ) ); ?></strong><span><?php esc_html_e( 'dana čuvanja', 'sidrena' ); ?></span></div></div>
				</section>

				<section class="sid-card sid-reference-panel">
					<div class="sid-section-head"><div><h2><span class="dashicons dashicons-location"></span><?php esc_html_e( 'Lokacije / Poslovnice', 'sidrena' ); ?></h2></div><a class="sid-inline-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-locations' ) ); ?>"><?php esc_html_e( 'Uredi lokacije', 'sidrena' ); ?></a></div>
					<?php $this->dashboard_locations( $data['locations'] ); ?>
				</section>

				<?php
				$current_price_ok = 0 === absint( $data['stats']['missing_current_total'] ?? 0 );
				$dated_price_ok   = 0 === absint( $data['stats']['missing_total'] ?? 0 );
				$price_labels_ok  = $data['total_items'] > 0 && $current_price_ok && $dated_price_ok;
				?>
				<section class="sid-card sid-reference-panel">
					<div class="sid-section-head"><div><h2><span class="dashicons dashicons-tag"></span><?php esc_html_e( 'Status oznake cijene', 'sidrena' ); ?></h2></div><span class="sid-status-pill <?php echo $price_labels_ok ? 'is-ok' : 'is-warn'; ?>"><?php echo $price_labels_ok ? esc_html__( 'Ispravno', 'sidrena' ) : esc_html__( 'Provjeriti', 'sidrena' ); ?></span></div>
					<ul class="sid-reference-checks">
						<li class="<?php echo $current_price_ok ? 'is-ok' : 'is-warn'; ?>"><span class="dashicons <?php echo $current_price_ok ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span><strong><?php esc_html_e( 'Trenutna cijena', 'sidrena' ); ?></strong><small><?php echo $current_price_ok ? esc_html__( 'Evidentirana', 'sidrena' ) : esc_html__( 'Nedostaje na jednoj ili više stavki', 'sidrena' ); ?></small></li>
						<li class="<?php echo $dated_price_ok ? 'is-ok' : 'is-warn'; ?>"><span class="dashicons <?php echo $dated_price_ok ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span><strong><?php esc_html_e( 'Cijena na datum', 'sidrena' ); ?></strong><small><?php echo $dated_price_ok ? esc_html__( 'Sidrena cijena evidentirana', 'sidrena' ) : esc_html__( 'Nedostaje na jednoj ili više stavki', 'sidrena' ); ?></small></li>
					</ul>
				</section>
			</div>

			<section class="sid-card sid-reference-panel sid-reference-price-explainer">
				<div class="sid-section-head"><div><h2><span class="dashicons dashicons-cart"></span><?php esc_html_e( 'Kako se prikazuju cijene na Vašoj stranici?', 'sidrena' ); ?></h2><p><?php esc_html_e( 'SIDRENA u središte stavlja aktualnu i sidrenu cijenu; poseban oblik prodaje i audit povijest vode se odvojeno.', 'sidrena' ); ?></p></div></div>
				<?php $this->dashboard_price_education( $data['settings'] ); ?>
			</section>
		</div>
		<?php
	}


	private function woocommerce_dashboard( $data ) {
		$last_ts = ! empty( $data['last']['generated_at'] ) ? strtotime( (string) $data['last']['generated_at'] ) : 0;
		if ( $data['is_ready'] ) {
			$health_caption = __( 'sve provjere uredne', 'sidrena' );
		} else {
			$health_caption = sprintf(
				/* translators: %d: number of technical checks that require attention. */
				_n( '%d stavka za provjeru', '%d stavki za provjeru', $data['health_issues'], 'sidrena' ),
				$data['health_issues']
			);
		}
		$health_aria_label = sprintf(
			/* translators: %d: technical readiness percentage. */
			__( 'Tehnička spremnost: %d posto', 'sidrena' ),
			$data['health_score']
		);
		$published_files_caption = sprintf(
			/* translators: %d: number of currently published public files. */
			_n( '%d aktualna datoteka', '%d aktualnih datoteka', absint( $data['integrity']['current_entries'] ), 'sidrena' ),
			absint( $data['integrity']['current_entries'] )
		);
		?>
		<div class="sid-reference-dashboard sid-reference-dashboard--woocommerce">
			<div class="sid-page-head sid-reference-page-head">
				<div>
					<span class="sid-kicker"><?php esc_html_e( 'Web trgovina', 'sidrena' ); ?></span>
					<h2><?php esc_html_e( 'Nadzorna ploča', 'sidrena' ); ?></h2>
					<p><?php esc_html_e( 'Pregled postojećih proizvoda trgovine, cijena, povijesti i objavljenih cjenika.', 'sidrena' ); ?></p>
				</div>
				<div class="sid-head-inline-actions">
					<a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-catalog' ) ); ?>"><span class="dashicons dashicons-cart"></span><?php esc_html_e( 'Proizvodi', 'sidrena' ); ?></a>
					<a class="button button-primary sid-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_generate' ), 'sidrena_generate' ) ); ?>"><span class="dashicons dashicons-update"></span><?php esc_html_e( 'Sinkroniziraj cjenike', 'sidrena' ); ?></a>
				</div>
			</div>

			<div class="sid-reference-metrics">
				<?php $this->dashboard_metric( __( 'Ukupno proizvoda', 'sidrena' ), absint( $data['stats']['products'] ), 'dashicons-products', __( 'postojeći katalog trgovine', 'sidrena' ), 'blue' ); ?>
				<?php $this->dashboard_metric( __( 'Zapisa povijesti', 'sidrena' ), $data['history_total'], 'dashicons-chart-line', __( 'promjene cijena i lokacija', 'sidrena' ), 'teal' ); ?>
				<?php $this->dashboard_metric( __( 'Aktualni cjenici', 'sidrena' ), $data['integrity']['current_entries'], 'dashicons-media-spreadsheet', __( 'javno dostupne datoteke', 'sidrena' ), 'purple' ); ?>
				<?php $this->dashboard_metric( __( 'Spremnost provjera', 'sidrena' ), $data['health_score'] . '%', 'dashicons-shield-alt', $health_caption, $data['is_ready'] ? 'ok' : 'warn' ); ?>
			</div>

			<div class="sid-reference-grid sid-reference-grid--woo-main">
				<section class="sid-card sid-reference-panel sid-changes-card">
					<div class="sid-section-head"><div><h2><?php esc_html_e( 'Nedavne promjene cijena', 'sidrena' ); ?></h2></div><a class="sid-inline-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-support&sidrena_section=log' ) ); ?>"><?php esc_html_e( 'Pogledaj sve', 'sidrena' ); ?></a></div>
					<?php $this->recent_changes_table( $data['changes'] ); ?>
				</section>

				<section class="sid-card sid-reference-panel sid-reference-education">
					<div class="sid-section-head"><div><span class="sid-kicker"><?php esc_html_e( 'Edukacija usklađenosti', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Razumijevanje cijena u SIDRENA', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Jasno razlikujte aktualnu cijenu, sidrenu cijenu i zaseban status posebnog oblika prodaje.', 'sidrena' ); ?></p></div></div>
					<?php $this->dashboard_price_education( $data['settings'] ); ?>
				</section>

				<section class="sid-card sid-reference-panel sid-reference-compliance">
					<div class="sid-section-head"><div><h2><?php esc_html_e( 'Status usklađenosti', 'sidrena' ); ?></h2></div><a class="sid-inline-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena&sidrena_section=compliance' ) ); ?>"><?php esc_html_e( 'Detalji', 'sidrena' ); ?></a></div>
					<div class="sid-reference-ring" role="img" aria-label="<?php echo esc_attr( $health_aria_label ); ?>">
						<svg viewBox="0 0 42 42" aria-hidden="true" focusable="false">
							<circle class="sid-reference-ring__track" cx="21" cy="21" r="15.9155"></circle>
							<circle class="sid-reference-ring__value" cx="21" cy="21" r="15.9155" pathLength="100" stroke-dasharray="<?php echo esc_attr( $data['health_score'] . ' 100' ); ?>"></circle>
						</svg>
						<div><strong><?php echo esc_html( $data['health_score'] . '%' ); ?></strong><span><?php esc_html_e( 'tehničkih provjera', 'sidrena' ); ?></span></div>
					</div>
					<div class="sid-reference-compliance-summary"><span class="is-ok"><?php echo esc_html( (int) ( count( $data['health_checks'] ) - $data['health_issues'] ) ); ?> <?php esc_html_e( 'uredno', 'sidrena' ); ?></span><span class="<?php echo $data['health_issues'] ? 'is-warn' : 'is-ok'; ?>"><?php echo esc_html( $data['health_issues'] ); ?> <?php esc_html_e( 'za provjeru', 'sidrena' ); ?></span></div>
				</section>
			</div>

			<div class="sid-reference-grid sid-reference-grid--woo-bottom">
				<section class="sid-card sid-reference-panel">
					<div class="sid-section-head"><div><h2><?php esc_html_e( 'Brze radnje', 'sidrena' ); ?></h2></div></div>
					<div class="sid-reference-action-grid sid-reference-action-grid--compact">
						<a class="sid-reference-action" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-files' ) ); ?>"><span class="dashicons dashicons-tag"></span><strong><?php esc_html_e( 'Pregledaj cjenik', 'sidrena' ); ?></strong></a>
						<a class="sid-reference-action" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-catalog' ) ); ?>"><span class="dashicons dashicons-update"></span><strong><?php esc_html_e( 'Sinkroniziraj proizvode', 'sidrena' ); ?></strong></a>
						<a class="sid-reference-action" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-support&sidrena_section=log' ) ); ?>"><span class="dashicons dashicons-chart-bar"></span><strong><?php esc_html_e( 'Povijest cijena', 'sidrena' ); ?></strong></a>
						<a class="sid-reference-action" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena&sidrena_section=compliance' ) ); ?>"><span class="dashicons dashicons-shield"></span><strong><?php esc_html_e( 'Provjeri usklađenost', 'sidrena' ); ?></strong></a>
						<a class="sid-reference-action" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-files' ) ); ?>"><span class="dashicons dashicons-download"></span><strong><?php esc_html_e( 'Izvezi CSV/XML', 'sidrena' ); ?></strong></a>
						<a class="sid-reference-action" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-settings' ) ); ?>"><span class="dashicons dashicons-admin-generic"></span><strong><?php esc_html_e( 'Postavke cijena', 'sidrena' ); ?></strong></a>
					</div>
				</section>

				<section class="sid-card sid-reference-panel">
					<div class="sid-section-head"><div><h2><?php esc_html_e( 'Lokacije', 'sidrena' ); ?></h2></div><a class="sid-inline-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-locations' ) ); ?>"><?php esc_html_e( 'Upravljanje lokacijama', 'sidrena' ); ?></a></div>
					<?php $this->dashboard_locations( $data['locations'] ); ?>
				</section>

				<section class="sid-card sid-reference-panel">
					<div class="sid-section-head"><div><h2><?php esc_html_e( 'Zadnje objavljeno', 'sidrena' ); ?></h2></div><a class="sid-inline-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-files' ) ); ?>"><?php esc_html_e( 'Pogledaj sve', 'sidrena' ); ?></a></div>
					<div class="sid-reference-published"><span class="dashicons dashicons-media-spreadsheet"></span><div><strong><?php echo $last_ts ? esc_html( wp_date( 'd.m.Y. H:i', $last_ts ) ) : esc_html__( 'Još nema objave', 'sidrena' ); ?></strong><small><?php echo esc_html( $published_files_caption ); ?></small></div></div>
				</section>
			</div>
		</div>
		<?php
	}


	private function dashboard_locations( $locations ) {
		$visible = array_values(
			array_filter(
				(array) $locations,
				static function ( $location ) {
					return 'yes' === ( $location['enabled'] ?? 'yes' );
				}
			)
		);
		if ( empty( $visible ) ) {
			echo '<div class="sid-table-empty">' . esc_html__( 'Nema aktivnih lokacija. Dodajte lokaciju prije objave cjenika.', 'sidrena' ) . '</div>';
			return;
		}
		?>
		<ul class="sid-reference-location-list">
			<?php foreach ( array_slice( $visible, 0, 4 ) as $location ) : ?>
				<li><span class="dashicons dashicons-location"></span><div><strong><?php echo esc_html( $location['code'] ?? $location['id'] ?? __( 'Lokacija', 'sidrena' ) ); ?></strong><small><?php echo esc_html( $location['address'] ?? '' ); ?></small></div><span class="sid-status-dot"></span></li>
			<?php endforeach; ?>
		</ul>
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
			++$max;
			$min = max( 0, $min - 1 );
		}
		$count  = count( $points );
		$coords = array();
		foreach ( $points as $index => $point ) {
			$x        = 24 + ( ( $count > 1 ? $index / ( $count - 1 ) : 0.5 ) * 672 );
			$y        = 148 - ( ( ( (float) $point['price'] - $min ) / ( $max - $min ) ) * 112 );
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
				<caption class="screen-reader-text"><?php esc_html_e( 'Nedavne promjene cijena proizvoda', 'sidrena' ); ?></caption>
				<thead><tr><th scope="col"><?php esc_html_e( 'Proizvod', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Stara cijena', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Nova cijena', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Promjena', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Datum', 'sidrena' ); ?></th></tr></thead>
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
		$health_checks  = $this->health_checks( $stats, $last, $settings );
		$health_issues  = count(
			array_filter(
				$health_checks,
				static function ( $check ) {
				return empty( $check[0] );
				}
			)
		);
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
			<?php /* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */ ?>
			<?php $this->metric_card( __( 'Sidrene cijene', 'sidrena' ), $anchor_ready . '/' . $total_items, 'dashicons-tag', $stats['missing_total'] ? sprintf( __( '%d stavki traži provjeru', 'sidrena' ), $stats['missing_total'] ) : __( 'sve evidentirane', 'sidrena' ), $stats['missing_total'] ? 'warn' : 'ok' ); ?>
			<?php /* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */ ?>
			<?php /* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */ ?>
			<?php $this->metric_card( __( 'Aktualni cjenici', 'sidrena' ), $file_coverage['ready'] . '/' . $file_coverage['expected'], 'dashicons-media-spreadsheet', $file_coverage['missing'] ? sprintf( __( '%d kombinacija nedostaje', 'sidrena' ), $file_coverage['missing'] ) : __( 'očekivane datoteke postoje', 'sidrena' ), $file_coverage['missing'] ? 'warn' : 'ok' ); ?>
			<?php /* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */ ?>
			<?php $this->metric_card( __( 'Javna arhiva', 'sidrena' ), $archive_stats['distinct_days'] . ' d', 'dashicons-backup', sprintf( __( '%d indeksiranih datoteka', 'sidrena' ), $archive_stats['files'] ), $integrity['ok'] ? 'ok' : 'warn' ); ?>
		</div>

		<div class="sid-grid sid-grid-2 sid-grid-main">
			<section class="sid-card">
				<div class="sid-section-head">
					<div><span class="sid-kicker"><?php esc_html_e( 'Automatske provjere', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Kontrolna lista spremnosti', 'sidrena' ); ?></h2></div>
					<?php /* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */ ?>
					<span class="sid-status-pill <?php echo 0 === $health_issues ? 'is-ok' : 'is-warn'; ?>"><?php echo 0 === $health_issues ? esc_html__( 'Nema tehničkih upozorenja', 'sidrena' ) : esc_html( sprintf( _n( '%d stavka za provjeru', '%d stavki za provjeru', $health_issues, 'sidrena' ), $health_issues ) ); ?></span>
				</div>
				<?php $this->health_list( $stats, $last, $settings ); ?>
			</section>

			<section class="sid-card sid-compliance-history">
				<span class="sid-kicker"><?php esc_html_e( 'Evidencija promjena', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Povijest se gradi kontinuirano', 'sidrena' ); ?></h2>
				<p><?php echo Sidrena_Utils::is_woocommerce_edition() ? esc_html__( 'Ovo izdanje vodi povijest WooCommerce cijena, cijena usluga i podataka po lokacijama. Javne CSV/XML objave čuvaju se prema postavljenoj politici arhive.', 'sidrena' ) : esc_html__( 'Ovo izdanje koristi vlastiti Sidrena katalog proizvoda i usluga bez WooCommercea. Javne CSV/XML objave čuvaju se prema postavljenoj politici arhive.', 'sidrena' ); ?></p>
				<div class="sid-history-stack">
					<div><span class="dashicons dashicons-products"></span><span><?php echo Sidrena_Utils::is_woocommerce_edition() ? esc_html__( 'Povijest cijena trgovine', 'sidrena' ) : esc_html__( 'WordPress proizvodi', 'sidrena' ); ?></span><strong><?php echo esc_html( Sidrena_Utils::is_woocommerce_edition() ? $product_hist : ( class_exists( 'Sidrena_Standalone' ) ? Sidrena_Standalone::count() : 0 ) ); ?></strong></div>
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
				<?php /* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */ ?>
				<div><h3><?php esc_html_e( 'Marka proizvoda', 'sidrena' ); ?></h3><p><?php echo 0 === $stats['missing_brand'] ? esc_html__( 'Sve stavke proizvoda imaju prepoznatu marku.', 'sidrena' ) : esc_html( sprintf( __( '%d stavki nema prepoznatu marku. Dopunite podatak u Sidrena katalogu ili WooCommerce proizvodu kada je integracija aktivna.', 'sidrena' ), $stats['missing_brand'] ) ); ?></p></div>
			</section>
			<section class="sid-card sid-check-card <?php echo $integrity['ok'] ? 'is-ok' : 'is-warn'; ?>">
				<span class="dashicons dashicons-shield-alt"></span>
				<div><h3><?php esc_html_e( 'Integritet arhive', 'sidrena' ); ?></h3><p><?php echo $integrity['ok'] ? esc_html__( 'Datoteke postoje i pohranjeni SHA-256 zapisi odgovaraju.', 'sidrena' ) : esc_html__( 'Nedostaje datoteka ili se SHA-256 ne podudara. Provjerite karticu Arhiva.', 'sidrena' ); ?></p></div>
			</section>
			<section class="sid-card sid-check-card <?php echo 0 === $file_coverage['missing'] ? 'is-ok' : 'is-warn'; ?>">
				<span class="dashicons dashicons-admin-site-alt3"></span>
				<?php /* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */ ?>
				<div><h3><?php esc_html_e( 'Lokacije × katalog × format', 'sidrena' ); ?></h3><p><?php echo esc_html( sprintf( __( 'Očekivano %1$d, aktualno %2$d. Svaka aktivna lokacija i webshop vode se kao zaseban izlaz.', 'sidrena' ), $file_coverage['expected'], $file_coverage['ready'] ) ); ?></p></div>
			</section>
		</div>

		<section class="sid-card sid-note">
			<div class="sid-note-icon"><span class="dashicons dashicons-info-outline"></span></div>
			<div><h2><?php esc_html_e( 'Sidrena cijena nije izvedena iz audit povijesti', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Javna arhiva čuva prethodno objavljene CSV/XML cjenike, a interna povijest bilježi stvarne promjene radi audita. Sidrena cijena određuje se prema zaključanom pravnom rulesetu i vjerodostojnoj poslovnoj evidenciji; ne računa se kao 30-dnevni minimum.', 'sidrena' ); ?></p></div>
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


	private function health_checks( $stats, $last, $settings ) {
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
		$automation_mode    = sanitize_key( (string) ( $settings['automation_mode'] ?? 'wp_cron' ) );
		$daily_schedule_ok = 'external' === $automation_mode || (bool) wp_next_scheduled( 'sidrena_daily_generation' );
		$watchdog_scheduled = (bool) wp_next_scheduled( 'sidrena_publication_watch' );
		$product_catalog_ready = Sidrena_Utils::is_wordpress_edition()
			? class_exists( 'Sidrena_Standalone' )
			: ( Sidrena_Utils::is_woocommerce_active() && class_exists( 'Sidrena_Products' ) );
		$checks = array(
			array( 0 === ( $stats['missing_current_total'] ?? 0 ), __( 'Sve objavljive stavke imaju aktualnu maloprodajnu cijenu', 'sidrena' ), __( 'Dopunite aktualnu cijenu proizvoda ili usluge prije objave cjenika.', 'sidrena' ) ),
			array( ! $needs_products || $product_catalog_ready, __( 'Katalog proizvoda je dostupan', 'sidrena' ), __( 'Provjerite instalaciju odgovarajućeg Sidrena izdanja i izvora proizvoda.', 'sidrena' ) ),
			array( ! $missing_address, __( 'Sve aktivne lokacije imaju adresu za naziv datoteke', 'sidrena' ), __( 'Dopunite adresu u kartici Lokacije.', 'sidrena' ) ),
			array( ! $needs_products || 0 === $stats['missing_anchor'], __( 'Proizvodi imaju sidrenu cijenu', 'sidrena' ), __( 'Dopunite nedostajuće vrijednosti u Sidrena katalogu ili u WooCommerce proizvodima kada je integracija aktivna.', 'sidrena' ) ),
			array( ! $needs_products || 0 === $stats['missing_brand'], __( 'Proizvodi imaju podatak o marki za digitalni cjenik', 'sidrena' ), __( 'Dopunite marku u Sidrena katalogu; uz WooCommerce možete koristiti i Brands/pa_brand.', 'sidrena' ) ),
			array( ! $needs_products || 0 === $stats['missing_barcode'], __( 'Proizvodi imaju barkod za digitalni cjenik', 'sidrena' ), __( 'Dopunite Barkod iz vjerodostojne poslovne evidencije u aktivnom katalogu.', 'sidrena' ) ),
			array( ! $needs_products || 0 === $stats['unit_price_review'], __( 'Primjenjivost cijene za jedinicu mjere pregledana je za proizvode', 'sidrena' ), __( 'U Katalogu označite je li jedinična cijena obvezna, nije primjenjiva ili postoji propisana iznimka prema NN 105/2026.', 'sidrena' ) ),
			array( ! $needs_products || 0 === $stats['unit_price_missing'], __( 'Stavke za koje je jedinična cijena obvezna imaju jedinicu i iznos', 'sidrena' ), __( 'Dopunite jedinicu mjere i cijenu za jedinicu mjere za označene proizvode/varijacije.', 'sidrena' ) ),
			array( 0 === $stats['missing_service_anchor'], __( 'Objavljene usluge imaju sidrenu cijenu', 'sidrena' ), __( 'Dopunite usluge kojima nedostaje referentna cijena.', 'sidrena' ) ),
			array( 0 === $stats['service_details_missing'], __( 'Objavljene usluge imaju vrstu i opseg za javni cjenik', 'sidrena' ), __( 'Dopunite vrstu i opseg usluge kako bi javni cjenik sadržavao podatke iz NN 105/2026.', 'sidrena' ) ),
			array( ! $coverage_issue, __( 'Fizičke lokacije imaju podatke o raspoloživosti po stavci', 'sidrena' ), __( 'Uvezite lokacijsku raspoloživost; globalno Woo stanje možda nije dovoljno za fizičku poslovnicu.', 'sidrena' ) ),
			array( $output_enabled, __( 'Automatska objava CSV/XML formata je aktivna', 'sidrena' ), __( 'Sidrena treba automatski održavati strojno čitljive formate.', 'sidrena' ) ),
			array( $before_eight, __( 'Automatsko dnevno generiranje postavljeno je prije 08:00', 'sidrena' ), __( 'Postavite vrijeme prije 08:00; preporuka SIDRENA-e je 06:30 radi operativne rezerve.', 'sidrena' ) ),
			array(
				$daily_schedule_ok,
				'external' === $automation_mode
					? __( 'Vanjski server cron / WP-CLI način je aktivan', 'sidrena' )
					: __( 'Dnevni WP-Cron događaj za generiranje cjenika je zakazan', 'sidrena' ),
				'external' === $automation_mode
					? __( 'Pokrenite “wp sidrena publish” iz poslužiteljskog rasporeda prije zadanog vremena.', 'sidrena' )
					: __( 'Ponovno spremite postavke ili reaktivirajte dodatak. Za strogo vrijeme izvršenja možete odabrati vanjski server cron / WP-CLI način.', 'sidrena' ),
			),
			array(
				$watchdog_scheduled,
				__( 'Watchdog nadzora objave je zakazan', 'sidrena' ),
				__( 'Ponovno spremite postavke ili reaktivirajte dodatak kako bi SIDRENA mogla nadzirati kašnjenje i neuspjeh objave.', 'sidrena' ),
			),
			array( max( 30, absint( $settings['retention_days'] ) ) >= 30, __( 'Arhiva je postavljena na najmanje 30 dana', 'sidrena' ), __( 'Povećajte razdoblje čuvanja.', 'sidrena' ) ),
			array( ! empty( Sidrena_Utils::public_index() ), __( 'Postoji barem jedan aktualni javni cjenik', 'sidrena' ), __( 'Generirajte prvi cjenik.', 'sidrena' ) ),
			array( $integrity['ok'], __( 'Indeksirane arhivske datoteke postoje i provjereni SHA-256 zapisi se podudaraju', 'sidrena' ), __( 'Otvorite Arhiva 30+ dana i provjerite nedostajuće ili promijenjene datoteke.', 'sidrena' ) ),
			array( empty( $last['errors'] ), __( 'Zadnje generiranje je završilo bez grešaka', 'sidrena' ), __( 'Pregledajte upozorenja zadnjeg generiranja.', 'sidrena' ) ),
		);
		return $checks;
	}


	private function health_list( $stats, $last, $settings ) {
		$checks = $this->health_checks( $stats, $last, $settings );

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
		$current          = Sidrena_Utils::public_index();
		$paths            = Sidrena_Utils::upload_paths();
		$last             = get_option( 'sidrena_last_run', array() );
		$current_refresh  = get_option( 'sidrena_last_current_refresh', array() );
		$settings         = Sidrena_Utils::settings();
		$public_page_id   = absint( get_option( 'sidrena_public_page_id', 0 ) );
		$public_page      = $public_page_id ? get_post( $public_page_id ) : null;
		$public_html_url  = Sidrena_Public::route_url( 'cjenik' );
		$archive_html_url = Sidrena_Public::route_url( 'arhiva' );

		if ( ! $public_page || 'trash' === $public_page->post_status ) {
			$public_page_id = 0;
			$public_page    = null;
		}

		$last_ts          = ! empty( $last['generated_at'] ) ? strtotime( (string) $last['generated_at'] ) : 0;
		$current_ts       = ! empty( $current_refresh['generated_at'] ) ? strtotime( (string) $current_refresh['generated_at'] ) : 0;
		$last_success     = $last_ts && empty( $last['errors'] ) && absint( $last['files'] ?? 0 ) > 0;
		$is_stale         = $last_ts && ( time() - $last_ts ) > ( 26 * HOUR_IN_SECONDS );
		$automation_mode    = sanitize_key( (string) ( $settings['automation_mode'] ?? 'wp_cron' ) );
		$external_scheduler = 'external' === $automation_mode;
		$wp_cron_disabled  = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$next_cron         = wp_next_scheduled( 'sidrena_daily_generation' );
		$watchdog_scheduled = (bool) wp_next_scheduled( 'sidrena_publication_watch' );
		$schedule_warning  = ! $watchdog_scheduled || ( ! $external_scheduler && ( $wp_cron_disabled || ! $next_cron ) );
		$rest_enabled     = 'yes' === $settings['enable_rest_index'];
		$html_enabled     = 'yes' === $settings['enable_public_html'];
		$current_files_caption = sprintf(
			/* translators: %d: number of currently published public files. */
			_n( '%d aktualna datoteka', '%d aktualnih datoteka', count( $current ), 'sidrena' ),
			count( $current )
		);
		$next_cron_caption = '';
		if ( $next_cron ) {
			$next_cron_caption = sprintf(
				/* translators: %s: date and time of the next scheduled WordPress cron run. */
				__( 'Sljedeći WordPress cron događaj: %s.', 'sidrena' ),
				wp_date( 'd.m.Y. H:i', $next_cron )
			);
		}
		$formats          = array();
		if ( 'yes' === $settings['generate_csv'] ) {
			$formats[] = 'CSV';
		}
		if ( 'yes' === $settings['generate_xml'] ) {
			$formats[] = 'XML';
		}
		if ( 'yes' === $settings['publish_manifest'] ) {
			$formats[] = 'JSON';
		}
		?>
		<div class="sid-reference-files">
			<div class="sid-page-head sid-reference-page-head">
				<div>
					<span class="sid-kicker"><?php esc_html_e( 'Digitalni cjenici', 'sidrena' ); ?></span>
					<h2><?php esc_html_e( 'Digitalni cjenici', 'sidrena' ); ?></h2>
					<p><?php esc_html_e( 'Upravljajte objavom, automatskim generiranjem, distribucijom i arhivom stvarnih cjenika.', 'sidrena' ); ?></p>
				</div>
				<div class="sid-head-inline-actions">
					<a class="button button-primary sid-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_generate' ), 'sidrena_generate' ) ); ?>"><span class="dashicons dashicons-update"></span><?php esc_html_e( 'Osvježi aktualni cjenik', 'sidrena' ); ?></a>
					<a class="button sid-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_publish_daily_archive' ), 'sidrena_publish_daily_archive' ) ); ?>"><span class="dashicons dashicons-backup"></span><?php esc_html_e( 'Objavi današnji arhivski cjenik', 'sidrena' ); ?></a>
					<a class="button sid-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_check_public_access' ), 'sidrena_check_public_access' ) ); ?>"><span class="dashicons dashicons-shield-alt"></span><?php esc_html_e( 'Provjeri javnu dostupnost', 'sidrena' ); ?></a>
					<a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-settings' ) ); ?>"><span class="dashicons dashicons-admin-generic"></span><?php esc_html_e( 'Postavke objave', 'sidrena' ); ?></a>
				</div>
			</div>

			<?php
			// Keep detailed diagnostics on the cjenici screen if the notice was dismissed.
			$last_attempt = is_array( $current_refresh ) && ! empty( $current_refresh['errors'] ) ? $current_refresh : $last;
			if ( is_array( $last_attempt ) && ! empty( $last_attempt['errors'] ) ) :
				?>
				<section class="sid-card sid-note sid-note-warning" role="status">
					<div class="sid-note-icon"><span class="dashicons dashicons-warning" aria-hidden="true"></span></div>
					<div><h3><?php esc_html_e( 'Otklonite probleme prije sljedeće objave', 'sidrena' ); ?></h3><?php $this->render_publication_errors( $last_attempt ); ?></div>
				</section>
			<?php endif; ?>
			<div class="sid-reference-metrics sid-reference-files-metrics">
				<?php $this->dashboard_metric( __( 'Dnevna arhiva', 'sidrena' ), $last_ts ? wp_date( 'd.m.Y. H:i', $last_ts ) : '—', $last_success ? 'dashicons-yes-alt' : 'dashicons-warning', $last_success ? __( 'zadnja uspješna objava', 'sidrena' ) : ( $last_ts ? __( 'zadnji pokušaj s upozorenjima', 'sidrena' ) : __( 'još nema objave', 'sidrena' ) ), $last_success && ! $is_stale ? 'ok' : 'warn' ); ?>
				<?php $this->dashboard_metric( __( 'Aktualni cjenik', 'sidrena' ), $current_ts ? wp_date( 'd.m.Y. H:i', $current_ts ) : '—', 'dashicons-update', $current_ts ? __( 'zadnje osvježavanje bez nove arhive', 'sidrena' ) : __( 'još nije zasebno osvježen', 'sidrena' ), $current_ts ? 'ok' : 'blue' ); ?>
				<?php $this->dashboard_metric( __( 'Sljedeća publikacija', 'sidrena' ), $next_cron ? wp_date( 'd.m.Y. H:i', $next_cron ) : '—', 'dashicons-clock', $next_cron ? __( 'automatski raspored', 'sidrena' ) : __( 'raspored nije aktivan', 'sidrena' ), $next_cron ? 'blue' : 'warn' ); ?>
				<?php $this->dashboard_metric( __( 'Aktivne datoteke', 'sidrena' ), count( $current ), 'dashicons-database', $formats ? implode( ' / ', $formats ) : __( 'nema uključenog formata', 'sidrena' ), 'purple' ); ?>
				<?php $this->dashboard_metric( __( 'Javni cjenik', 'sidrena' ), $html_enabled ? __( 'Dostupan', 'sidrena' ) : __( 'Isključen', 'sidrena' ), 'dashicons-admin-site-alt3', $html_enabled ? __( 'HTML prikaz je uključen', 'sidrena' ) : __( 'uključite ga u Postavkama', 'sidrena' ), $html_enabled ? 'ok' : 'warn' ); ?>
			</div>

			<div class="sid-reference-grid sid-reference-files-grid">
				<section class="sid-card sid-reference-panel sid-reference-publication">
					<div class="sid-section-head"><div><h2><span class="dashicons dashicons-admin-generic"></span><?php esc_html_e( 'Postavke publikacije', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Sažetak aktivnog rasporeda i načina objave.', 'sidrena' ); ?></p></div></div>
					<div class="sid-reference-setting-list">
						<div><span><?php esc_html_e( 'Automatsko generiranje', 'sidrena' ); ?></span><strong><?php echo $next_cron ? esc_html__( 'Aktivno', 'sidrena' ) : esc_html__( 'Nije zakazano', 'sidrena' ); ?></strong></div>
						<div><span><?php esc_html_e( 'Vrijeme generiranja', 'sidrena' ); ?></span><strong><?php echo esc_html( $settings['generation_time'] ); ?></strong></div>
						<div><span><?php esc_html_e( 'Formati', 'sidrena' ); ?></span><strong><?php echo esc_html( $formats ? implode( ' / ', $formats ) : '—' ); ?></strong></div>
						<div><span><?php esc_html_e( 'Čuvanje arhive', 'sidrena' ); ?></span><strong><?php echo esc_html( absint( $settings['retention_days'] ) . ' d' ); ?></strong></div>
					</div>
					<a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-settings' ) ); ?>"><?php esc_html_e( 'Uredi postavke publikacije', 'sidrena' ); ?></a>
				</section>

				<section class="sid-card sid-reference-panel sid-reference-distribution">
					<div class="sid-section-head"><div><h2><span class="dashicons dashicons-admin-links"></span><?php esc_html_e( 'Distribucija i integracije', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Javni HTML, datoteke, shortcode i programski pristup.', 'sidrena' ); ?></p></div></div>
					<div class="sid-reference-endpoints">
						<div class="<?php echo $html_enabled ? '' : 'is-disabled'; ?>"><span><?php esc_html_e( 'Javni HTML cjenik', 'sidrena' ); ?></span><code><?php echo esc_html( $public_html_url ); ?></code></div>
						<div class="<?php echo $rest_enabled ? '' : 'is-disabled'; ?>"><span><?php esc_html_e( 'REST API', 'sidrena' ); ?></span><code><?php echo esc_html( rest_url( 'sidrena/v1/cjenici' ) ); ?></code></div>
						<div><span><?php esc_html_e( 'Shortcode', 'sidrena' ); ?></span><code>[sidrena_cjenik]</code></div>
						<?php if ( 'yes' === $settings['publish_manifest'] ) : ?><div><span><?php esc_html_e( 'JSON manifest', 'sidrena' ); ?></span><code><?php echo esc_html( $paths['manifest_url'] ); ?></code></div><?php endif; ?>
					</div>
				</section>

				<section class="sid-card sid-reference-panel sid-reference-public-preview">
					<div class="sid-section-head"><div><h2><span class="dashicons dashicons-visibility"></span><?php esc_html_e( 'Javni prikaz', 'sidrena' ); ?></h2></div><?php if ( $html_enabled ) : ?><a class="sid-inline-link" href="<?php echo esc_url( $public_html_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Otvori', 'sidrena' ); ?><span class="dashicons dashicons-external"></span></a><?php endif; ?></div>
					<div class="sid-reference-public-preview__screen">
						<div class="sid-reference-public-preview__brand"><img src="<?php echo esc_url( SIDRENA_URL . 'assets/images/logo-horizontal-light.svg' ); ?>" alt="" width="180" height="35"></div>
						<div class="sid-reference-public-preview__body">
							<strong><?php esc_html_e( 'Cjenik proizvoda i usluga', 'sidrena' ); ?></strong>
							<span><?php echo esc_html( $current_files_caption ); ?></span>
							<div class="sid-reference-preview-list">
								<?php if ( empty( $current ) ) : ?><span><?php esc_html_e( 'Cjenik još nije generiran.', 'sidrena' ); ?></span><?php else : ?>
									<?php foreach ( array_slice( $current, 0, 4 ) as $file ) : ?><span><b><?php echo esc_html( strtoupper( $file['format'] ?? '' ) ); ?></b><?php echo esc_html( $file['filename'] ?? '' ); ?></span><?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</section>
			</div>

			<section class="sid-card sid-note <?php echo $is_stale || $schedule_warning ? 'sid-note-warning' : ''; ?>">
				<div class="sid-note-icon"><span class="dashicons <?php echo $is_stale || $schedule_warning ? 'dashicons-warning' : 'dashicons-clock'; ?>"></span></div>
				<div>
					<?php if ( $is_stale ) : ?>
						<h2><?php esc_html_e( 'Zadnji uspješni cjenik stariji je od 26 sati', 'sidrena' ); ?></h2>
						<p><?php esc_html_e( 'Provjerite odabrani scheduler i Dnevnik. Zadnja valjana datoteka ostaje javno dostupna dok nova objava ne prođe provjeru.', 'sidrena' ); ?></p>
					<?php elseif ( ! $watchdog_scheduled ) : ?>
						<h2><?php esc_html_e( 'Nadzor objave nije zakazan', 'sidrena' ); ?></h2>
						<p><?php esc_html_e( 'Ponovno spremite Postavke ili reaktivirajte dodatak kako bi watchdog mogao pratiti kašnjenje i neuspjeh objave.', 'sidrena' ); ?></p>
					<?php elseif ( $external_scheduler ) : ?>
						<h2><?php esc_html_e( 'Vanjski server cron / WP-CLI način je aktivan', 'sidrena' ); ?></h2>
						<p><?php esc_html_e( 'Interni dnevni WP-Cron namjerno nije zakazan. Server treba pokrenuti “wp sidrena publish” prema rasporedu; watchdog je aktivan.', 'sidrena' ); ?></p>
					<?php elseif ( $wp_cron_disabled ) : ?>
						<h2><?php esc_html_e( 'WordPress WP-Cron je isključen', 'sidrena' ); ?></h2>
						<p><?php esc_html_e( 'Odaberite vanjski server cron / WP-CLI način ili omogućite WordPress cron izvršavanje.', 'sidrena' ); ?></p>
					<?php elseif ( ! $next_cron ) : ?>
						<h2><?php esc_html_e( 'Dnevno generiranje nije zakazano', 'sidrena' ); ?></h2>
						<p><?php esc_html_e( 'Ponovno spremite Postavke ili provjerite cron konfiguraciju poslužitelja.', 'sidrena' ); ?></p>
					<?php else : ?>
						<h2><?php esc_html_e( 'Automatsko generiranje je zakazano', 'sidrena' ); ?></h2>
						<p><?php echo esc_html( $next_cron_caption ); ?></p>
					<?php endif; ?>
				</div>
			</section>

			<section class="sid-card sid-reference-panel">
				<div class="sid-section-head"><div><h2><?php esc_html_e( 'Aktualne javne datoteke', 'sidrena' ); ?></h2><p><?php esc_html_e( 'SHA-256 omogućuje naknadnu provjeru integriteta objavljene datoteke.', 'sidrena' ); ?></p></div><a class="sid-inline-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-files&sidrena_section=archive' ) ); ?>"><?php esc_html_e( 'Otvori arhivu', 'sidrena' ); ?></a></div>
				<?php $this->files_table( $current, false ); ?>
			</section>

			<section class="sid-card sid-public-page-card sid-reference-panel">
				<div class="sid-section-head">
					<div><h2><?php esc_html_e( 'WordPress stranica za javnu objavu', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Stranica “Objava cjenika” može prikazivati aktualne datoteke i javnu arhivu.', 'sidrena' ); ?></p></div>
					<?php if ( $public_page_id && $html_enabled ) : ?><span class="sid-status-pill is-ok"><?php esc_html_e( 'Objavljeno', 'sidrena' ); ?></span><?php elseif ( $public_page_id ) : ?><span class="sid-status-pill is-warn"><?php esc_html_e( 'HTML prikaz je isključen', 'sidrena' ); ?></span><?php else : ?><span class="sid-status-pill is-warn"><?php esc_html_e( 'Stranica nije izrađena', 'sidrena' ); ?></span><?php endif; ?>
				</div>
				<?php if ( $public_page_id ) : ?>
					<div class="sid-code-row"><span><?php esc_html_e( 'Javni URL', 'sidrena' ); ?></span><code><?php echo esc_html( get_permalink( $public_page_id ) ); ?></code></div>
					<a class="button sid-secondary" href="<?php echo esc_url( get_permalink( $public_page_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Otvori javnu stranicu', 'sidrena' ); ?></a>
				<?php else : ?>
					<a class="button button-primary sid-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_create_public_page' ), 'sidrena_create_public_page' ) ); ?>"><?php esc_html_e( 'Izradi i objavi stranicu', 'sidrena' ); ?></a>
				<?php endif; ?>
			</section>

			<section class="sid-card sid-code-card sid-reference-panel">
				<div class="sid-section-head"><div><h2><?php esc_html_e( 'Napredni pristup', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Dodatni endpointi i shortcodeovi za integraciju.', 'sidrena' ); ?></p></div></div>
				<div class="sid-code-row <?php echo $rest_enabled ? '' : 'is-disabled'; ?>"><span><?php esc_html_e( 'REST indeks cjenika', 'sidrena' ); ?></span><code><?php echo esc_html( rest_url( 'sidrena/v1/cjenici' ) ); ?></code></div>
				<div class="sid-code-row <?php echo $rest_enabled ? '' : 'is-disabled'; ?>"><span><?php esc_html_e( 'Cijene u realnom vremenu', 'sidrena' ); ?></span><code><?php echo esc_html( rest_url( 'sidrena/v1/cijene' ) ); ?></code></div>
				<div class="sid-code-row <?php echo $html_enabled ? '' : 'is-disabled'; ?>"><span><?php esc_html_e( 'Javna HTML arhiva', 'sidrena' ); ?></span><code><?php echo esc_html( $archive_html_url ); ?></code></div>
				<div class="sid-code-row"><span><?php esc_html_e( 'Kompletna Objava cjenika', 'sidrena' ); ?></span><code>[sidrena_objava_cjenika]</code></div>
				<div class="sid-code-row"><span><?php esc_html_e( 'Arhiva', 'sidrena' ); ?></span><code>[sidrena_arhiva]</code></div>
				<div class="sid-code-row"><span><?php esc_html_e( 'Cjenik usluga', 'sidrena' ); ?></span><code>[sidrena_usluge]</code></div>
			</section>
		</div>
		<?php
	}


	private function archive_tab() {
		$archive   = Sidrena_Utils::archive_index();
		$stats     = Sidrena_Utils::archive_stats();
		$settings  = Sidrena_Utils::settings();
		$integrity = Sidrena_Utils::archive_integrity();
		$retention = Sidrena_Utils::archive_retention_status();
		?>
		<div class="sid-page-head"><div><span class="sid-kicker"><?php esc_html_e( 'Javna povijest', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Arhiva i tehnička spremnost', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Svaka uspješna CSV/XML objava čuva se kao zasebna javna datoteka. Razdoblje javne arhive zaključano je na najmanje 30 dana prema aktualnom SIDRENA rulesetu.', 'sidrena' ); ?></p></div><a class="button sid-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_export_archive_index' ), 'sidrena_export_archive_index' ) ); ?>"><span class="dashicons dashicons-download"></span><?php esc_html_e( 'Izvezi evidenciju arhive', 'sidrena' ); ?></a></div>

		<div class="sid-archive-top">
			<section class="sid-card sid-archive-settings-card">
				<div class="sid-section-head"><div><span class="sid-kicker"><?php esc_html_e( 'Postavke arhive', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Čuvanje prethodnih cjenika', 'sidrena' ); ?></h2></div><span class="sid-status-pill is-ok"><?php esc_html_e( 'Arhiva je aktivna', 'sidrena' ); ?></span></div>
				<p><?php esc_html_e( 'Javna arhiva čuva prethodne objave najmanje 30 dana. Aktualna valjana datoteka dodatno je zaštićena od uklanjanja.', 'sidrena' ); ?></p>
				<div class="sid-retention-box">
					<?php /* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */ ?>
					<div><span><?php esc_html_e( 'Trajanje arhive', 'sidrena' ); ?></span><strong><?php echo esc_html( sprintf( __( '%d dana', 'sidrena' ), $settings['retention_days'] ) ); ?></strong></div>
					<?php /* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */ ?>
					<div><span class="dashicons dashicons-info-outline"></span><p><?php echo esc_html( sprintf( __( 'Minimum je 30 dana. Trenutačna rezerva iznad minimuma iznosi +%d dana.', 'sidrena' ), $retention['buffer_days'] ) ); ?></p></div>
				</div>
				<a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-settings' ) ); ?>"><?php esc_html_e( 'Uredi postavke arhive', 'sidrena' ); ?></a>
			</section>
			<?php $this->support_card(); ?>
		</div>

		<div class="sid-dashboard-metrics sid-dashboard-metrics--archive">
			<?php $this->dashboard_metric( __( 'Datoteke u arhivi', 'sidrena' ), $stats['files'], 'dashicons-database', __( 'CSV/XML objave', 'sidrena' ), 'blue' ); ?>
			<?php $this->dashboard_metric( __( 'Dani s objavama', 'sidrena' ), $stats['distinct_days'], 'dashicons-calendar-alt', __( 'evidentirani dani', 'sidrena' ), 'teal' ); ?>
			<?php /* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */ ?>
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


	private function dashboard_price_education( $settings ) {
		unset( $settings );
		$standard_date = Sidrena_Utils::date_display( Sidrena_Utils::standard_reference_date() );
		$fmcg_date     = Sidrena_Utils::date_display( Sidrena_Utils::fmcg_reference_date() );
		/* translators: 1: locked standard reference date, 2: locked FMCG reference date. */
		$locked_dates_caption = sprintf( __( 'Standardno %1$s; ranije obuhvaćeni FMCG %2$s. Stvarno novouvedena stavka koristi dokazivi datum prvog uvrštenja kada je to primjenjivo.', 'sidrena' ), $standard_date, $fmcg_date );
		?>
		<div class="sid-price-guide sid-price-guide--reference">
			<div class="sid-price-guide__item"><span class="sid-price-guide__icon dashicons dashicons-cart"></span><h3><?php esc_html_e( 'Aktualna cijena', 'sidrena' ); ?></h3><p><?php esc_html_e( 'Cijena koja je trenutačno primjenjiva i objavljuje se u javnom cjeniku.', 'sidrena' ); ?></p></div>
			<div class="sid-price-guide__item"><span class="sid-price-guide__icon dashicons dashicons-tag"></span><h3><?php esc_html_e( 'Sidrena cijena', 'sidrena' ); ?></h3><p><?php esc_html_e( 'Zasebna dodatna cijena prema SIDRENA rulesetu. Ne računa se kao najniža cijena u prethodnih 30 dana niti iz statusa posebnog oblika prodaje.', 'sidrena' ); ?></p></div>
			<div class="sid-price-guide__item"><span class="sid-price-guide__icon dashicons dashicons-calendar-alt"></span><h3><?php esc_html_e( 'Zaključani pravni datum', 'sidrena' ); ?></h3><p><?php echo esc_html( $locked_dates_caption ); ?></p></div>
		</div>
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

	private function support_card() {
		?>
		<section class="sid-card sid-support-card">
			<div class="sid-support-card__icon"><span class="dashicons dashicons-editor-help"></span></div>
			<div>
				<span class="sid-kicker"><?php esc_html_e( 'Pomoć i dokumentacija', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Trebate pomoć oko objave ili arhive?', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Otvorite SIDRENA Podršku za upute, dijagnostiku, kontakt i opcionalne usluge. Ovaj ekran ostaje fokusiran na arhivu i tehničku spremnost.', 'sidrena' ); ?></p>
				<div class="sid-card-actions">
					<a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-support' ) ); ?>"><?php esc_html_e( 'Otvori podršku', 'sidrena' ); ?></a>
					<a class="button sid-secondary" href="<?php echo esc_url( Sidrena_Utils::support_pdf_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'PDF upute', 'sidrena' ); ?></a>
				</div>
			</div>
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
		$ruleset  = Sidrena_Utils::legal_ruleset();
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
				<div class="sid-settings-title"><span class="dashicons dashicons-lock"></span><div><h2><?php esc_html_e( '2. Zakonska pravila', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Aktualni pravni ruleset prikazan je samo za čitanje. Zakonski referentni datumi nisu administratorske postavke i ne mogu se globalno promijeniti.', 'sidrena' ); ?></p></div></div>
				<div class="sid-reference-setting-list">
					<div><span><?php esc_html_e( 'Aktivni ruleset', 'sidrena' ); ?></span><strong><?php echo esc_html( $ruleset['id'] ); ?></strong></div>
					<div><span><?php esc_html_e( 'Standardni zakonski referentni datum', 'sidrena' ); ?></span><strong><?php echo esc_html( Sidrena_Utils::date_display( $ruleset['standard_reference_date'] ) ); ?></strong></div>
					<div><span><?php esc_html_e( 'Ranije obuhvaćene FMCG kategorije', 'sidrena' ); ?></span><strong><?php echo esc_html( Sidrena_Utils::date_display( $ruleset['fmcg_reference_date'] ) ); ?></strong></div>
					<div><span><?php esc_html_e( 'Izvor SIDRENA cijene', 'sidrena' ); ?></span><strong><?php echo esc_html( $ruleset['anchor_source'] ); ?></strong></div>
					<div><span><?php esc_html_e( 'Izvor digitalnog cjenika', 'sidrena' ); ?></span><strong><?php echo esc_html( $ruleset['pricelist_source'] ); ?></strong></div>
					<div><span><?php esc_html_e( 'Službeno pojašnjenje', 'sidrena' ); ?></span><strong><?php echo esc_html( $ruleset['clarification_source'] ); ?></strong></div>
					<div><span><?php esc_html_e( 'Zadnja pravna provjera SIDRENA ruleseta', 'sidrena' ); ?></span><strong><?php echo esc_html( Sidrena_Utils::date_display( $ruleset['verified_date'] ) ); ?></strong></div>
				</div>
				<p><a class="button sid-secondary" href="<?php echo esc_url( $ruleset['anchor_source_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Otvori izvor SIDRENA cijene', 'sidrena' ); ?></a> <a class="button sid-secondary" href="<?php echo esc_url( $ruleset['pricelist_source_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Otvori izvor digitalnog cjenika', 'sidrena' ); ?></a> <a class="button sid-secondary" href="<?php echo esc_url( $ruleset['clarification_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Otvori službeno pojašnjenje', 'sidrena' ); ?></a></p>
				<p class="description"><?php esc_html_e( 'SIDRENA je tehnički alat za evidenciju i objavu podataka. Ruleset ne predstavlja pravno jamstvo niti zamjenjuje izvornu poslovnu evidenciju ili pravni savjet.', 'sidrena' ); ?></p>
			</section>

			<section class="sid-card sid-settings-section">
				<div class="sid-settings-title"><span class="dashicons dashicons-shield-alt"></span><div><h2><?php esc_html_e( '3. Zaštita objave', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Sigurnosne i strojno čitljive površine ostaju uključene kako ih administrator ne bi slučajno isključio.', 'sidrena' ); ?></p></div></div>
				<div class="sid-grid sid-grid-2">
					<div class="sid-note"><div class="sid-note-icon"><span class="dashicons dashicons-media-spreadsheet"></span></div><div><strong><?php esc_html_e( 'CSV + XML uključeni', 'sidrena' ); ?></strong><p><?php esc_html_e( 'SIDRENA objavljuje oba strojno čitljiva formata radi interoperabilnosti; time se ne tvrdi da propis zahtijeva oba formata istodobno.', 'sidrena' ); ?></p></div></div>
					<div class="sid-note"><div class="sid-note-icon"><span class="dashicons dashicons-rest-api"></span></div><div><strong><?php esc_html_e( 'Javni i strojni pristup', 'sidrena' ); ?></strong><p><?php esc_html_e( 'Javni HTML, JSON manifest i REST indeks ostaju aktivni za aktualne podatke i arhivu.', 'sidrena' ); ?></p></div></div>
					<div class="sid-note"><div class="sid-note-icon"><span class="dashicons dashicons-tag"></span></div><div><strong><?php esc_html_e( 'SIDRENA cijena je zaseban podatak', 'sidrena' ); ?></strong><p><?php esc_html_e( 'Sidrena/referentna cijena i njezin datum vode se odvojeno od aktualne cijene, 30-dnevne najniže cijene i WooCommerce akcijske cijene.', 'sidrena' ); ?></p></div></div>
					<div class="sid-note"><div class="sid-note-icon"><span class="dashicons dashicons-shield"></span></div><div><strong><?php esc_html_e( 'Sigurna objava', 'sidrena' ); ?></strong><p><?php esc_html_e( 'Atomic write, file locking, posljednja valjana verzija, watchdog i upozorenja štite javni cjenik od nepotpunog zapisa.', 'sidrena' ); ?></p></div></div>
				</div>
			</section>

			<section class="sid-card sid-settings-section">
				<div class="sid-settings-title"><span class="dashicons dashicons-clock"></span><div><h2><?php esc_html_e( '4. Raspored, arhiva i upozorenja', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Sidrena korigira vrijeme na sigurnu vrijednost ako unesete 08:00 ili kasnije. Arhiva se ne može postaviti ispod 30 dana, ali je možete čuvati dulje.', 'sidrena' ); ?></p></div></div>
				<div class="sid-fields">
					<label><span><?php esc_html_e( 'Način dnevne objave', 'sidrena' ); ?></span><select name="automation_mode"><option value="wp_cron" <?php selected( $settings['automation_mode'] ?? 'wp_cron', 'wp_cron' ); ?>><?php esc_html_e( 'Interni WordPress WP-Cron', 'sidrena' ); ?></option><option value="external" <?php selected( $settings['automation_mode'] ?? 'wp_cron', 'external' ); ?>><?php esc_html_e( 'Vanjski server cron / WP-CLI', 'sidrena' ); ?></option></select><small><?php esc_html_e( 'Vanjski način uklanja interni dnevni cron. Server cron treba pokrenuti naredbu “wp sidrena publish”; SIDRENA watchdog i dalje nadzire kašnjenje.', 'sidrena' ); ?></small></label>
					<label><span><?php esc_html_e( 'Vrijeme dnevnog generiranja', 'sidrena' ); ?></span><input type="time" name="generation_time" value="<?php echo esc_attr( $settings['generation_time'] ); ?>"><small><?php esc_html_e( 'Preporučeno 06:30. Vrijednost mora biti prije 08:00.', 'sidrena' ); ?></small></label>
					<label><span><?php esc_html_e( 'Čuvanje javne arhive (dana)', 'sidrena' ); ?></span><input type="number" min="30" step="1" name="retention_days" value="<?php echo esc_attr( max( 30, absint( $settings['retention_days'] ) ) ); ?>"><small><?php esc_html_e( 'Najmanje 30 dana. Veća vrijednost produljuje čuvanje postojećih i novih arhivskih zapisa.', 'sidrena' ); ?></small></label>
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
					<div class="sid-tool-icon"><span class="dashicons dashicons-tag"></span></div><h2><?php esc_html_e( 'Uvoz sidrenih cijena', 'sidrena' ); ?></h2><p><?php esc_html_e( 'CSV stupci: sku, anchor_price i opcionalni reference_group. Ako se za postojeći automatski custom zapis navede anchor_date, mora točno odgovarati dokazivom prvom objavljivanju stavke; proizvoljni datumi se odbijaju. Zakonski datumi 10.09.2026. i 02.05.2025. nisu promjenjive postavke.', 'sidrena' ); ?></p><label class="sid-file-control"><span><?php esc_html_e( 'CSV datoteka', 'sidrena' ); ?></span><input class="sid-file-input" type="file" name="anchor_csv" accept=".csv,text/csv,text/plain" aria-describedby="sid-anchor-csv-help" required><small id="sid-anchor-csv-help"><?php esc_html_e( 'Najviše 5 MB. Odaberite stvarnu CSV datoteku s podacima za uvoz.', 'sidrena' ); ?></small></label><button class="button button-primary sid-primary" type="submit"><?php esc_html_e( 'Uvezi sidrene cijene', 'sidrena' ); ?></button>
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
			<section class="sid-card sid-tool-card"><div class="sid-tool-icon"><span class="dashicons dashicons-chart-line"></span></div><h2><?php esc_html_e( 'Povijest promjena cijena', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Izvezite neograničenu evidenciju stvarnih promjena cijena. Ova povijest služi auditu i nije izračun najniže cijene u 30 dana.', 'sidrena' ); ?></p><a class="button sid-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_export_price_history' ), 'sidrena_export_price_history' ) ); ?>"><?php esc_html_e( 'Preuzmi povijest cijena', 'sidrena' ); ?></a></section>
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
			<?php $this->rule_card( '01', __( 'Dodatna / sidrena cijena', 'sidrena' ), __( 'Za novobuhvaćene proizvode i usluge SIDRENA zaključava mjerodavni datum 10.09.2026.; ranije obuhvaćeni FMCG zadržava 02.05.2025. Sidrena cijena vodi se kao zasebna dodatna cijena primjenjiva prema rulesetu, ne kao 30-dnevni minimum. Za stvarno novouvedenu stavku nakon mjerodavnog datuma koristi se dokazivi datum prvog uvrštenja.', 'sidrena' ), 'https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html', 'NN 101/2026, 1212' ); ?>
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
		$automation_mode = sanitize_key( $this->post_value( 'automation_mode', 'wp_cron' ) );
		if ( ! in_array( $automation_mode, array( 'wp_cron', 'external' ), true ) ) {
			$automation_mode = 'wp_cron';
		}
		$retention_days  = max( 30, absint( $this->post_value( 'retention_days', 30 ) ) );
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
			'automation_mode'       => $automation_mode,
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
		$schedule_ok = true;
		if ( 'wp_cron' === ( $saved['automation_mode'] ?? 'wp_cron' ) ) {
			$scheduled   = wp_schedule_event( Sidrena_Utils::schedule_timestamp( $saved['generation_time'] ), 'daily', 'sidrena_daily_generation' );
			$schedule_ok = false !== $scheduled && ! is_wp_error( $scheduled );
		}
		if ( ! wp_next_scheduled( 'sidrena_publication_watch' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'sidrena_publication_watch' );
		}

		Sidrena_Pricelist::queue_regeneration();
		Sidrena_Audit::log(
			'settings_save',
			$schedule_ok ? 'success' : 'warning',
			$schedule_ok
				? ( 'external' === ( $saved['automation_mode'] ?? 'wp_cron' )
					? __( 'SIDRENA postavke su spremljene. Interni dnevni WP-Cron je isključen jer je odabran vanjski server cron/WP-CLI način; watchdog ostaje aktivan za nadzor.', 'sidrena' )
					: __( 'SIDRENA postavke su spremljene i interni dnevni WP-Cron ostaje uključen.', 'sidrena' ) )
				: __( 'SIDRENA postavke su spremljene, ali dnevni WP-Cron nije ponovno zakazan.', 'sidrena' ),
			array(
				'generation_time' => $saved['generation_time'],
				'automation_mode' => $saved['automation_mode'] ?? 'wp_cron',
				'retention_days'  => $saved['retention_days'],
			)
		);
		$this->redirect( 'settings', $schedule_ok ? 'saved' : 'settings_saved_cron_warning' );
	}

	public function save_locations() {
		$this->guard_post( 'sidrena_save_locations' );
		$input = array_slice( $this->post_array( 'locations' ), 0, 500, true );
		if ( empty( $input ) ) {
			$this->redirect( 'locations', 'locations_required' );
		}

		$out            = array();
		$used           = array();
		$used_selectors = array();
		$old_ids        = array();
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

			if ( 'yes' === $enabled ) {
				$selector_keys = array_unique(
					array(
						$id,
						Sidrena_Utils::sanitize_location_id( $code ),
					)
				);
				foreach ( $selector_keys as $selector_key ) {
					if ( isset( $used_selectors[ $selector_key ] ) && $id !== $used_selectors[ $selector_key ] ) {
						$this->redirect( 'locations', 'locations_invalid' );
					}
					$used_selectors[ $selector_key ] = $id;
				}
			}

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
		$success = Sidrena_Pricelist::instance()->refresh_current();
		$this->redirect( 'files', $success ? 'generated' : 'generated_with_errors' );
	}

	public function publish_daily_archive() {
		if ( ! Sidrena_Utils::current_user_can_manage() || ! check_admin_referer( 'sidrena_publish_daily_archive' ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}
		$success = Sidrena_Pricelist::instance()->publish_daily_archive();
		$this->redirect( 'files', $success ? 'generated' : 'archive_with_errors' );
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
		foreach ( $this->iterate_import_rows_with_product_ids( $resource, $delimiter, $map['sku'] ) as $entry ) {
			if ( is_wp_error( $entry ) ) {
				fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				$this->redirect( 'tools', 'import_failed' );
			}
			$row        = $entry['row'];
			$product_id = absint( $entry['product_id'] );
			++$processed;
			$sku = isset( $row[ $map['sku'] ] ) ? sanitize_text_field( $row[ $map['sku'] ] ) : '';
			if ( ! $sku ) {
				++$skipped;
				continue;
			}
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

			$existing_group       = Sidrena_Utils::sanitize_reference_group( get_post_meta( $product_id, '_sidrena_reference_group', true ) );
			$effective_group      = $group ? $group : $existing_group;
			$verified_custom_date = '';
			if ( $has_anchor_date && $valid_date ) {
				if ( ! $group ) {
					if ( Sidrena_Utils::standard_reference_date() === $valid_date ) {
						$effective_group = 'standard';
					} elseif ( Sidrena_Utils::fmcg_reference_date() === $valid_date ) {
						$effective_group = 'fmcg';
					} else {
						$verified_custom_date = Sidrena_Utils::verified_custom_reference_date_for_post( $product_id, $valid_date );
						if ( ! $verified_custom_date ) {
							++$skipped;
							continue;
						}
						$effective_group = 'custom';
					}
				} elseif ( 'standard' === $effective_group && Sidrena_Utils::standard_reference_date() !== $valid_date ) {
					++$skipped;
					continue;
				} elseif ( 'fmcg' === $effective_group && Sidrena_Utils::fmcg_reference_date() !== $valid_date ) {
					++$skipped;
					continue;
				} elseif ( 'custom' === $effective_group ) {
					$verified_custom_date = Sidrena_Utils::verified_custom_reference_date_for_post( $product_id, $valid_date );
					if ( ! $verified_custom_date ) {
						++$skipped;
						continue;
					}
				}
			}
			if ( 'custom' === $effective_group && ! $verified_custom_date ) {
				$verified_custom_date = Sidrena_Utils::verified_custom_reference_date_for_post( $product_id );
				if ( ! $verified_custom_date ) {
					++$skipped;
					continue;
				}
			}

			++$updated;
			if ( '' === $price ) {
				delete_post_meta( $product_id, '_sidrena_anchor_price' );
			} else {
				update_post_meta( $product_id, '_sidrena_anchor_price', $price );
			}

			if ( $group || ( $has_anchor_date && $valid_date ) ) {
				update_post_meta( $product_id, '_sidrena_reference_group', $effective_group );
			}
			if ( 'custom' === $effective_group && $verified_custom_date ) {
				update_post_meta( $product_id, '_sidrena_anchor_date', $verified_custom_date );
			} elseif ( 'custom' !== $effective_group && ( $has_group || $has_anchor_date ) ) {
				delete_post_meta( $product_id, '_sidrena_anchor_date' );
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
		$sku_index = isset( $map['sku'] ) ? $map['sku'] : null;
		foreach ( $this->iterate_import_rows_with_product_ids( $resource, $delimiter, $sku_index ) as $entry ) {
			if ( is_wp_error( $entry ) ) {
				fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				$this->redirect( 'tools', 'location_import_failed' );
			}
			$row        = $entry['row'];
			$product_id = absint( $entry['product_id'] );
			++$processed;
			$location_id = Sidrena_Utils::sanitize_location_id( $row[ $map['location_id'] ] ?? '' );
			if ( ! isset( $valid_locations[ $location_id ] ) ) {
				++$skipped;
				continue;
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
		return $this->prepare_uploaded_csv_stream( $file['tmp_name'], 50000 );
	}

	private function prepare_uploaded_csv_stream( $tmp_name, $row_limit = 50000 ) {
		$tmp_name = (string) $tmp_name;
		if ( '' === $tmp_name || ! is_file( $tmp_name ) ) {
			return new WP_Error( 'upload_missing' );
		}

		$resource = fopen( $tmp_name, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Validated upload temp file is intentionally streamed directly.
		if ( ! $resource ) {
			return new WP_Error( 'upload_open' );
		}
		$first_line = fgets( $resource );
		if ( false === $first_line || false !== strpos( $first_line, "\0" ) ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( false === $first_line ? 'upload_empty' : 'upload_binary' );
		}
		$delimiter = $this->detect_delimiter( $first_line );
		rewind( $resource );
		$head = $this->read_normalized_csv_row( $resource, $delimiter );
		if ( is_wp_error( $head ) ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return $head;
		}
		if ( ! is_array( $head ) || empty( $head ) ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'upload_header' );
		}
		$head[0]  = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $head[0] );
		$head     = array_map( array( 'Sidrena_Utils', 'import_header_key' ), $head );
		$nonempty = array_values( array_filter( $head ) );
		if ( count( $nonempty ) !== count( array_unique( $nonempty ) ) ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'upload_duplicate_headers' );
		}
		$row_count = $this->enforce_csv_row_limit( $resource, $delimiter, $row_limit );
		if ( is_wp_error( $row_count ) ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return $row_count;
		}
		return array( $resource, $delimiter, array_flip( $head ) );
	}

	private function read_normalized_csv_row( $stream, $delimiter ) {
		$row = Sidrena_Utils::csv_read_row( $stream, $delimiter );
		if ( ! is_array( $row ) ) {
			return $row;
		}
		foreach ( $row as $index => $value ) {
			$value = Sidrena_Utils::normalize_text_encoding( (string) $value );
			if ( false !== strpos( $value, "\0" ) ) {
				return new WP_Error( 'upload_binary', __( 'CSV sadrži nedopušteni binarni sadržaj.', 'sidrena' ) );
			}
			$row[ $index ] = $value;
		}
		return $row;
	}

	private function enforce_csv_row_limit( $stream, $delimiter, $row_limit = 50000 ) {
		$row_limit = min( 50000, max( 1, absint( $row_limit ) ) );
		$count     = 0;
		while ( is_resource( $stream ) ) {
			$row = $this->read_normalized_csv_row( $stream, $delimiter );
			if ( is_wp_error( $row ) ) {
				return $row;
			}
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
		$header = $this->read_normalized_csv_row( $stream, $delimiter );
		if ( is_wp_error( $header ) ) {
			return $header;
		}
		if ( false === $header ) {
			return new WP_Error( 'upload_header' );
		}
		return $count;
	}

	private function import_product_code_key( $code ) {
		$code = trim( sanitize_text_field( (string) $code ) );
		if ( '' === $code ) {
			return '';
		}
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $code, 'UTF-8' ) : strtolower( $code );
	}

	private function product_id_index_for_codes( $codes ) {
		global $wpdb;

		$keys     = array();
		$original = array();
		foreach ( (array) $codes as $code ) {
			$code = trim( sanitize_text_field( (string) $code ) );
			$key  = $this->import_product_code_key( $code );
			if ( '' !== $key && ! isset( $keys[ $key ] ) ) {
				$keys[ $key ]     = true;
				$original[ $key ] = $code;
			}
			if ( count( $keys ) >= 500 ) {
				break;
			}
		}
		if ( ! $keys ) {
			return array();
		}

		$index        = array();
		$values       = array_values( $original );
		$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
		$lookup_table = $wpdb->prefix . 'wc_product_meta_lookup';
		$query        = "SELECT MIN(product_id) AS product_id, sku
			FROM %i
			WHERE sku IN ( $placeholders )
			GROUP BY sku
			ORDER BY MIN(product_id) ASC
			LIMIT %d";
		$args         = array( $lookup_table );
		foreach ( $values as $value ) {
			$args[] = $value;
		}
		$args[] = count( $values );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifier/value placeholders are prepared below; only the internally generated bounded placeholder list is interpolated.
		$prepared = $wpdb->prepare( $query, ...$args );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Bounded Woo lookup-table query was fully prepared immediately above.
		$rows = $wpdb->get_results( $prepared, ARRAY_A );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$key = $this->import_product_code_key( $row['sku'] ?? '' );
			$id  = absint( $row['product_id'] ?? 0 );
			if ( $key && $id && ! isset( $index[ $key ] ) ) {
				$index[ $key ] = $id;
			}
		}

		foreach ( $original as $key => $code ) {
			if ( isset( $index[ $key ] ) || ! preg_match( '/^WP-(\d+)$/i', $code, $matches ) ) {
				continue;
			}
			$product = wc_get_product( absint( $matches[1] ) );
			if ( $product ) {
				$index[ $key ] = absint( $product->get_id() );
			}
		}

		$remaining = array();
		foreach ( $original as $key => $code ) {
			if ( ! isset( $index[ $key ] ) ) {
				$remaining[ $key ] = $code;
			}
		}
		if ( ! $remaining ) {
			return $index;
		}

		$values       = array_values( $remaining );
		$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
		$query        = "SELECT MIN(p.ID) AS ID, pm.meta_value AS code
			FROM %i p
			INNER JOIN %i pm ON pm.post_id = p.ID
			WHERE p.post_type IN (%s, %s)
				AND p.post_status IN (%s, %s, %s)
				AND pm.meta_key = %s
				AND pm.meta_value IN ( $placeholders )
			GROUP BY pm.meta_value
			ORDER BY MIN(p.ID) ASC
			LIMIT %d";
		$args         = array(
			$wpdb->posts,
			$wpdb->postmeta,
			'product',
			'product_variation',
			'publish',
			'private',
			'draft',
			'_sidrena_code',
		);
		foreach ( $values as $value ) {
			$args[] = $value;
		}
		$args[] = count( $values );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifier/value placeholders are prepared below; only the internally generated bounded placeholder list is interpolated.
		$prepared = $wpdb->prepare( $query, ...$args );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Bounded exact-code fallback query was fully prepared immediately above.
		$rows = $wpdb->get_results( $prepared, ARRAY_A );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$key = $this->import_product_code_key( $row['code'] ?? '' );
			$id  = absint( $row['ID'] ?? 0 );
			if ( $key && $id && ! isset( $index[ $key ] ) ) {
				$index[ $key ] = $id;
			}
		}
		return $index;
	}

	private function iterate_import_rows_with_product_ids( $stream, $delimiter, $sku_index, $chunk_size = 250 ) {
		$chunk_size = min( 250, max( 1, absint( $chunk_size ) ) );
		while ( is_resource( $stream ) ) {
			$rows  = array();
			$codes = array();
			for ( $i = 0; $i < $chunk_size; ++$i ) {
				$row = $this->read_normalized_csv_row( $stream, $delimiter );
				if ( is_wp_error( $row ) ) {
					yield $row;
					return;
				}
				if ( false === $row ) {
					break;
				}
				$rows[] = $row;
				if ( null !== $sku_index && isset( $row[ $sku_index ] ) ) {
					$code = sanitize_text_field( (string) $row[ $sku_index ] );
					if ( '' !== $code ) {
						$codes[] = $code;
					}
				}
			}
			if ( ! $rows ) {
				return;
			}

			$index = $this->product_id_index_for_codes( $codes );
			foreach ( $rows as $row ) {
				$product_id = 0;
				if ( null !== $sku_index && isset( $row[ $sku_index ] ) ) {
					$key = $this->import_product_code_key( $row[ $sku_index ] );
					if ( $key && isset( $index[ $key ] ) ) {
						$product_id = absint( $index[ $key ] );
					}
				}
				yield array(
					'row'        => $row,
					'product_id' => $product_id,
				);
			}
			if ( count( $rows ) < $chunk_size ) {
				return;
			}
		}
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
		$out = $this->open_csv_download( 'sidrena-nedostajuce-sidrene-cijene.csv' );
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
		$out = $this->open_csv_download( 'sidrena-povijest-cijena-' . wp_date( 'Y-m-d' ) . '.csv' );
		$this->safe_fputcsv(
			$out,
			array( 'vrsta_zapisa', 'zabiljezeno', 'product_id', 'variation_id', 'service_id', 'sifra', 'naziv', 'cijena', 'redovna_cijena', 'sidrena_cijena', 'izvor' ),
			';'
		);

		$product_table = $wpdb->prefix . 'sidrena_price_history';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Snapshot boundary for a stable bounded export from the plugin-owned history table.
		$product_max_id = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(id) FROM %i', $product_table ) ) );
		$product_last_id = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Keyset-bounded export from SIDRENA-owned history table.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id, product_id, variation_id, price, regular_price, recorded_at, source FROM %i WHERE id > %d AND id <= %d ORDER BY id ASC LIMIT %d',
					$product_table,
					$product_last_id,
					$product_max_id,
					1000
				),
				ARRAY_A
			);
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$product_last_id = max( $product_last_id, absint( $row['id'] ) );
				$item_id         = absint( $row['variation_id'] ) ? absint( $row['variation_id'] ) : absint( $row['product_id'] );
				if ( Sidrena_Utils::is_woocommerce_edition() ) {
					$product = Sidrena_Utils::is_woocommerce_active() ? wc_get_product( $item_id ) : false;
					$code    = $product ? Sidrena_Utils::get_product_code( $product ) : '';
					$name    = $product ? $product->get_name() : get_the_title( $item_id );
					$anchor  = Sidrena_Utils::product_anchor_price( $item_id );
					$kind    = 'woocommerce';
				} else {
					$code   = get_post_meta( $item_id, '_sidrena_standalone_code', true );
					$name   = get_the_title( $item_id );
					$anchor = get_post_meta( $item_id, '_sidrena_standalone_anchor_price', true );
					$kind   = 'wordpress';
				}
				$this->safe_fputcsv(
					$out,
					array( $kind, $row['recorded_at'], absint( $row['product_id'] ), absint( $row['variation_id'] ), '', $code, $name, $row['price'], $row['regular_price'], $anchor, $row['source'] ),
					';'
				);
			}
			$row_count = is_array( $rows ) ? count( $rows ) : 0;
		} while ( 1000 === $row_count );

		$service_table = $wpdb->prefix . 'sidrena_service_price_history';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Snapshot boundary for a stable bounded export from the plugin-owned service history table.
		$service_max_id = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(id) FROM %i', $service_table ) ) );
		$service_last_id = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Keyset-bounded export from SIDRENA-owned service history table.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id, service_id, price, recorded_at, source FROM %i WHERE id > %d AND id <= %d ORDER BY id ASC LIMIT %d',
					$service_table,
					$service_last_id,
					$service_max_id,
					1000
				),
				ARRAY_A
			);
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$service_last_id = max( $service_last_id, absint( $row['id'] ) );
				$service_id      = absint( $row['service_id'] );
				$this->safe_fputcsv(
					$out,
					array( 'usluga', $row['recorded_at'], '', '', $service_id, '', get_the_title( $service_id ), $row['price'], '', get_post_meta( $service_id, '_sidrena_service_anchor_price', true ), $row['source'] ),
					';'
				);
			}
			$row_count = is_array( $rows ) ? count( $rows ) : 0;
		} while ( 1000 === $row_count );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}


	private function open_csv_download( $filename ) {
		$filename = sanitize_file_name( (string) $filename );
		if ( '' === $filename || '.csv' !== strtolower( substr( $filename, -4 ) ) ) {
			wp_die( esc_html__( 'Neispravan naziv izvozne datoteke.', 'sidrena' ) );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$out = fopen( 'php://output', 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! is_resource( $out ) ) {
			wp_die( esc_html__( 'Nije moguće otvoriti izlaznu datoteku.', 'sidrena' ) );
		}
		if ( false === fwrite( $out, "\xEF\xBB\xBF" ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			wp_die( esc_html__( 'Nije moguće zapisati izlaznu datoteku.', 'sidrena' ) );
		}

		return $out;
	}

	private function safe_fputcsv( $handle, $fields, $delimiter = ',' ) {
		$safe = array();
		foreach ( (array) $fields as $field ) {
			$safe[] = Sidrena_Utils::csv_safe_cell( $field );
		}
		return false !== Sidrena_Utils::csv_write_row( $handle, $safe, $delimiter );
	}

	public function export_archive_index() {
		if ( ! Sidrena_Utils::current_user_can_manage() || ! check_admin_referer( 'sidrena_export_archive_index' ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}

		$out = $this->open_csv_download( 'sidrena-evidencija-arhive-' . wp_date( 'Y-m-d' ) . '.csv' );
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
		$out = $this->open_csv_download( 'sidrena-lokacije-predlozak.csv' );
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
		$automation_mode      = sanitize_key( (string) ( $settings['automation_mode'] ?? 'wp_cron' ) );
		$daily_schedule_ok   = 'external' === $automation_mode || (bool) wp_next_scheduled( 'sidrena_daily_generation' );
		$watchdog_schedule_ok = (bool) wp_next_scheduled( 'sidrena_publication_watch' );
		$generation_time_ok  = isset( $settings['generation_time'] ) && strcmp( (string) $settings['generation_time'], '08:00' ) < 0;
		if ( ! $daily_schedule_ok || ! $watchdog_schedule_ok || ! $generation_time_ok ) {
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

	private function normalize_public_check_path( $path ) {
		$path   = (string) $path;
		$passes = 0;
		do {
			$decoded = rawurldecode( $path );
			if ( $decoded === $path ) {
				break;
			}
			$path = $decoded;
			++$passes;
			if ( $passes > 5 ) {
				return '';
			}
		} while ( true );

		if ( false !== strpos( $path, "\0" ) ) {
			return '';
		}
		$path     = str_replace( '\\', '/', $path );
		$parts    = explode( '/', $path );
		$segments = array();
		foreach ( $parts as $part ) {
			if ( '' === $part || '.' === $part ) {
				continue;
			}
			if ( '..' === $part ) {
				if ( empty( $segments ) ) {
					return '';
				}
				array_pop( $segments );
				continue;
			}
			$segments[] = $part;
		}
		$normalized = '/' . implode( '/', $segments );
		return '/' === substr( $path, -1 ) ? trailingslashit( $normalized ) : $normalized;
	}

	private function is_allowed_public_check_url( $url, $base ) {
		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$url_parts  = wp_parse_url( $url );
		$base_parts = wp_parse_url( $base );
		if ( ! is_array( $url_parts ) || ! is_array( $base_parts ) ) {
			return false;
		}
		if ( isset( $url_parts['user'] ) || isset( $url_parts['pass'] ) ) {
			return false;
		}

		$url_scheme  = strtolower( (string) ( $url_parts['scheme'] ?? '' ) );
		$base_scheme = strtolower( (string) ( $base_parts['scheme'] ?? '' ) );
		$url_host    = strtolower( rtrim( (string) ( $url_parts['host'] ?? '' ), '.' ) );
		$base_host   = strtolower( rtrim( (string) ( $base_parts['host'] ?? '' ), '.' ) );
		if ( ! $url_scheme || $url_scheme !== $base_scheme || ! $url_host || $url_host !== $base_host ) {
			return false;
		}

		$default_port = 'https' === $url_scheme ? 443 : 80;
		$url_port     = isset( $url_parts['port'] ) ? absint( $url_parts['port'] ) : $default_port;
		$base_port    = isset( $base_parts['port'] ) ? absint( $base_parts['port'] ) : $default_port;
		if ( $url_port !== $base_port ) {
			return false;
		}

		$url_path  = $this->normalize_public_check_path( $url_parts['path'] ?? '/' );
		$base_path = $this->normalize_public_check_path( trailingslashit( (string) ( $base_parts['path'] ?? '/' ) ) );
		if ( ! $url_path || ! $base_path ) {
			return false;
		}
		$base_path = trailingslashit( $base_path );
		$extension = strtolower( (string) pathinfo( $url_path, PATHINFO_EXTENSION ) );
		return strlen( $url_path ) > strlen( $base_path )
			&& 0 === strpos( $url_path, $base_path )
			&& in_array( $extension, array( 'csv', 'xml' ), true )
			&& empty( $url_parts['query'] )
			&& empty( $url_parts['fragment'] );
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
			if ( ! $this->is_allowed_public_check_url( $url, $base ) ) {
				$failed[] = basename( (string) ( $entry['filename'] ?? $url ) );
				continue;
			}

			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'             => 10,
					'redirection'         => 0,
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
