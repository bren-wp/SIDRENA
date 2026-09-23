<?php
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
		add_action( 'admin_post_sidrena_import_anchor', array( $this, 'import_anchor' ) );
		add_action( 'admin_post_sidrena_import_location_data', array( $this, 'import_location_data' ) );
		add_action( 'admin_post_sidrena_export_missing', array( $this, 'export_missing' ) );
		add_action( 'admin_post_sidrena_export_location_template', array( $this, 'export_location_template' ) );
		add_action( 'admin_post_sidrena_export_archive_index', array( $this, 'export_archive_index' ) );
		add_action( 'admin_post_sidrena_export_price_history', array( $this, 'export_price_history' ) );
		add_action( 'admin_post_sidrena_create_public_page', array( $this, 'create_public_page' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SIDRENA_FILE ), array( $this, 'action_links' ) );
	}

	public function menu() {
		add_menu_page(
			'Sidrena',
			'Sidrena',
			'manage_options',
			'sidrena',
			array( $this, 'page' ),
			SIDRENA_URL . 'assets/images/logo-mark.svg',
			81
		);
	}

	public function assets( $hook ) {
		$screen         = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_plugin_page = false !== strpos( (string) $hook, 'sidrena' );
		$is_service     = $screen && 'sidrena_service' === $screen->post_type;
		$is_product     = $screen && 'product' === $screen->post_type;

		if ( ! $is_plugin_page && ! $is_service && ! $is_product ) {
			return;
		}

		wp_enqueue_style( 'sidrena-admin', SIDRENA_URL . 'admin/css/admin.css', array(), SIDRENA_VERSION );
		if ( $is_plugin_page ) {
			wp_enqueue_script( 'sidrena-admin', SIDRENA_URL . 'admin/js/admin.js', array(), SIDRENA_VERSION, true );
		}
	}

	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=sidrena' ) ) . '">' . esc_html__( 'Otvori Sidrenu', 'sidrena' ) . '</a>'
		);
		return $links;
	}

	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = array(
			'dashboard'  => array( 'dashicons-chart-area', __( 'Pregled', 'sidrena' ) ),
			'compliance' => array( 'dashicons-yes-alt', __( 'Usklađenost', 'sidrena' ) ),
			'catalog'    => array( 'dashicons-products', __( 'Katalog', 'sidrena' ) ),
			'files'     => array( 'dashicons-media-spreadsheet', __( 'Cjenici', 'sidrena' ) ),
			'archive'   => array( 'dashicons-backup', __( 'Arhiva 30+ dana', 'sidrena' ) ),
			'locations' => array( 'dashicons-location-alt', __( 'Lokacije', 'sidrena' ) ),
			'settings'  => array( 'dashicons-admin-generic', __( 'Postavke', 'sidrena' ) ),
			'tools'     => array( 'dashicons-admin-tools', __( 'Alati', 'sidrena' ) ),
			'log'       => array( 'dashicons-list-view', __( 'Dnevnik', 'sidrena' ) ),
			'rules'     => array( 'dashicons-media-document', __( 'Propisi', 'sidrena' ) ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'dashboard';
		}
		?>
		<div class="wrap sidrena-wrap">
			<header class="sid-head">
				<div class="sid-brand">
					<span class="sid-logo-shell"><img src="<?php echo esc_url( SIDRENA_URL . 'assets/images/logo-mark.svg' ); ?>" alt=""></span>
					<div>
						<div class="sid-eyebrow"><?php esc_html_e( 'Brendigo · WordPress / WooCommerce', 'sidrena' ); ?></div>
						<h1>Sidrena</h1>
						<p><?php esc_html_e( 'Sidrene cijene, 30-dnevna povijest i javni CSV/XML cjenici za Hrvatsku', 'sidrena' ); ?></p>
					</div>
				</div>
				<div class="sid-head-actions">
					<a class="sid-link-button" href="https://sidrena-cijena.com.hr/" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-external"></span><?php esc_html_e( 'Web plugina', 'sidrena' ); ?></a>
					<span class="sid-badge">v<?php echo esc_html( SIDRENA_VERSION ); ?> · FREE</span>
				</div>
			</header>

			<nav class="sid-tabs" aria-label="<?php esc_attr_e( 'Sidrena navigacija', 'sidrena' ); ?>">
				<?php foreach ( $tabs as $slug => $data ) : ?>
					<a class="sid-tab <?php echo $tab === $slug ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena&tab=' . $slug ) ); ?>">
						<span class="dashicons <?php echo esc_attr( $data[0] ); ?>"></span><span><?php echo esc_html( $data[1] ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php $this->render_notice(); ?>

			<main class="sid-content">
				<?php
				switch ( $tab ) {
					case 'compliance':
						$this->compliance_tab();
						break;
					case 'catalog':
						Sidrena_Bulk::instance()->render();
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
					default:
						$this->dashboard_tab();
				}
				?>
			</main>

			<footer class="sid-footer">
				<span><?php esc_html_e( 'Sidrena je besplatan open-source dodatak.', 'sidrena' ); ?></span>
				<span>Brendigo · <a href="https://brendigo.com/" target="_blank" rel="noopener noreferrer">brendigo.com</a></span>
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
			'public_page_created'      => array( 'success', __( 'Javna stranica Cjenici je izrađena i objavljena.', 'sidrena' ) ),
			'public_page_exists'       => array( 'success', __( 'Javna stranica Cjenici već postoji.', 'sidrena' ) ),
			'public_page_failed'       => array( 'error', __( 'Javnu stranicu nije bilo moguće izraditi. Provjerite ovlasti i WordPress zapisnik.', 'sidrena' ) ),
			'bulk_saved'               => array( 'success', __( 'Katalog je spremljen, a ponovno generiranje cjenika stavljeno je u red.', 'sidrena' ) ),
		);
		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}
		$type = $messages[ $notice ][0];
		$text = $messages[ $notice ][1];
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
	}

	private function dashboard_tab() {
		$stats         = $this->audit_stats();
		$last          = get_option( 'sidrena_last_run', array() );
		$settings      = Sidrena_Utils::settings();
		$archive_stats = Sidrena_Utils::archive_stats();
		$integrity     = Sidrena_Utils::archive_integrity();
		$retention     = Sidrena_Utils::archive_retention_status();
		$next_run      = wp_next_scheduled( 'sidrena_daily_generation' );
		?>
		<section class="sid-intro sid-card">
			<div>
				<span class="sid-kicker"><?php echo esc_html( SIDRENA_RULESET ); ?></span>
				<h2><?php esc_html_e( 'Jedno mjesto za sidrene cijene i digitalne cjenike', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Sidrena vodi referentne cijene uz WooCommerce proizvode, prati povijest cijena za posebne oblike prodaje te generira strojno čitljive cjenike i javnu arhivu prethodnih objava.', 'sidrena' ); ?></p>
			</div>
			<div class="sid-intro-actions">
				<a class="button button-primary sid-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_generate' ), 'sidrena_generate' ) ); ?>"><span class="dashicons dashicons-update"></span><?php esc_html_e( 'Generiraj cjenike', 'sidrena' ); ?></a>
				<a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena&tab=tools' ) ); ?>"><span class="dashicons dashicons-upload"></span><?php esc_html_e( 'Uvoz podataka', 'sidrena' ); ?></a>
			</div>
		</section>

		<div class="sid-grid sid-grid-4">
			<?php $this->metric_card( __( 'WooCommerce stavke', 'sidrena' ), $stats['products'], 'dashicons-products', __( 'proizvodi i varijacije', 'sidrena' ) ); ?>
			<?php $this->metric_card( __( 'Bez sidrene cijene', 'sidrena' ), $stats['missing_total'], 'dashicons-warning', $stats['missing_total'] ? __( 'potrebna provjera', 'sidrena' ) : __( 'sve popunjeno', 'sidrena' ), $stats['missing_total'] ? 'warn' : 'ok' ); ?>
			<?php $this->metric_card( __( 'Arhivirane datoteke', 'sidrena' ), $archive_stats['files'], 'dashicons-backup', sprintf( __( '%d dana s objavama', 'sidrena' ), $archive_stats['distinct_days'] ) ); ?>
			<?php $this->metric_card( __( 'Sniženja bez pune 30-dnevne baze', 'sidrena' ), $stats['sale_incomplete'] + $stats['service_sale_incomplete'], 'dashicons-chart-line', ( $stats['sale_incomplete'] + $stats['service_sale_incomplete'] ) ? __( 'proizvodi/usluge za provjeru', 'sidrena' ) : __( 'nema upozorenja', 'sidrena' ), ( $stats['sale_incomplete'] + $stats['service_sale_incomplete'] ) ? 'warn' : 'ok' ); ?>
		</div>

		<div class="sid-grid sid-grid-2 sid-grid-main">
			<section class="sid-card">
				<div class="sid-section-head">
					<div><span class="sid-kicker"><?php esc_html_e( 'Kontrola prije objave', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Tehnička provjera spremnosti', 'sidrena' ); ?></h2></div>
					<span class="sid-status-pill <?php echo 0 === $stats['issues'] ? 'is-ok' : 'is-warn'; ?>"><?php echo 0 === $stats['issues'] ? esc_html__( 'Bez tehničkih upozorenja', 'sidrena' ) : esc_html( sprintf( _n( '%d upozorenje', '%d upozorenja', $stats['issues'], 'sidrena' ), $stats['issues'] ) ); ?></span>
				</div>
				<?php $this->health_list( $stats, $last, $settings ); ?>
			</section>

			<section class="sid-card sid-card-dark">
				<span class="sid-kicker"><?php esc_html_e( 'Automatska objava', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Cjenik prije 08:00 + arhiva najmanje 30 dana', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Zadano generiranje je u 06:30 kako bi ostala sigurnosna rezerva prije roka 08:00. WordPress WP-Cron ovisi o posjetima stranici; za poslovno kritičan termin preporučuje se pouzdan server cron koji redovito pokreće wp-cron.php.', 'sidrena' ); ?></p>
				<div class="sid-dark-stats">
					<div><span><?php esc_html_e( 'Postavljeno vrijeme', 'sidrena' ); ?></span><strong><?php echo esc_html( $settings['generation_time'] ); ?></strong></div>
					<div><span><?php esc_html_e( 'Čuvanje arhive', 'sidrena' ); ?></span><strong><?php echo esc_html( $settings['retention_days'] ); ?> d</strong></div>
					<div><span><?php esc_html_e( 'Rezerva iznad minimuma', 'sidrena' ); ?></span><strong>+<?php echo esc_html( $retention['buffer_days'] ); ?> d</strong></div>
					<div><span><?php esc_html_e( 'Sljedeći WP-Cron', 'sidrena' ); ?></span><strong><?php echo $next_run ? esc_html( wp_date( 'd.m. H:i', $next_run ) ) : '—'; ?></strong></div>
				</div>
				<?php if ( ! empty( $last['generated_at'] ) ) : ?><p class="sid-dark-meta"><?php esc_html_e( 'Zadnje generiranje:', 'sidrena' ); ?> <?php echo esc_html( $last['generated_at'] ); ?> · <?php echo esc_html( wp_timezone_string() ?: 'UTC' ); ?></p><?php endif; ?>
			</section>
		</div>

		<section class="sid-card sid-integrity-card <?php echo $integrity['ok'] ? 'is-ok' : 'is-warn'; ?>">
			<div class="sid-integrity-icon"><span class="dashicons <?php echo $integrity['ok'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span></div>
			<div>
				<span class="sid-kicker"><?php esc_html_e( 'Integritet javne arhive', 'sidrena' ); ?></span>
				<h2><?php echo $integrity['ok'] ? esc_html__( 'Sve indeksirane datoteke postoje i SHA-256 zapisi se podudaraju', 'sidrena' ) : esc_html__( 'Arhiva traži tehničku provjeru', 'sidrena' ); ?></h2>
				<p><?php echo esc_html( sprintf( __( 'Aktualno: %1$d · arhiva: %2$d · nedostaje: %3$d · hash odstupanja: %4$d', 'sidrena' ), $integrity['current_entries'], $integrity['archive_entries'], count( $integrity['missing_files'] ), count( $integrity['hash_mismatch'] ) ) ); ?></p>
			</div>
			<a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena&tab=archive' ) ); ?>"><?php esc_html_e( 'Provjeri arhivu', 'sidrena' ); ?></a>
		</section>

		<div class="sid-grid sid-grid-3">
			<section class="sid-card sid-mini-card"><span class="dashicons dashicons-calendar-alt"></span><div><h3><?php esc_html_e( 'Sidreni datum', 'sidrena' ); ?></h3><p><?php echo esc_html( Sidrena_Utils::date_display( $settings['default_ref_date'] ) ); ?> · <?php esc_html_e( 'novobuhvaćeni proizvodi i usluge', 'sidrena' ); ?></p></div></section>
			<section class="sid-card sid-mini-card"><span class="dashicons dashicons-archive"></span><div><h3><?php esc_html_e( 'FMCG datum', 'sidrena' ); ?></h3><p><?php echo esc_html( Sidrena_Utils::date_display( $settings['fmcg_ref_date'] ) ); ?> · <?php esc_html_e( 'postojeće obuhvaćene kategorije', 'sidrena' ); ?></p></div></section>
			<section class="sid-card sid-mini-card"><span class="dashicons dashicons-shield"></span><div><h3><?php esc_html_e( 'Privatnost', 'sidrena' ); ?></h3><p><?php esc_html_e( 'Bez telemetrije, licenci i obaveznih vanjskih servisa.', 'sidrena' ); ?></p></div></section>
		</div>

		<div class="sid-card sid-note">
			<div class="sid-note-icon"><span class="dashicons dashicons-info-outline"></span></div>
			<div><h2><?php esc_html_e( 'Sidrena ne izmišlja povijesne cijene', 'sidrena' ); ?></h2>
			<p><?php esc_html_e( 'Ako WordPress/WooCommerce nema vjerodostojnu povijest za referentni datum ili punih 30 dana prije početka sniženja, dodatak označava podatak kao nepotpun. Povijesnu vrijednost tada provjerite u vlastitoj evidenciji i unesite ručno ili CSV uvozom.', 'sidrena' ); ?></p>
			<p class="description"><?php esc_html_e( 'Ovo je tehnički alat za provedbu i evidenciju. Ne predstavlja pravno mišljenje niti jamstvo usklađenosti konkretnog poslovnog subjekta.', 'sidrena' ); ?></p></div>
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
		$product_hist   = Sidrena_History::count_rows();
		$service_hist   = Sidrena_Service_History::count_rows();
		$location_hist  = Sidrena_Location_History::count_rows();
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
				<p><?php esc_html_e( 'Sidrena odvojeno vodi povijest WooCommerce cijena, cijena usluga i lokacijskih cijena/raspoloživosti. Zapisi se čuvaju 400 dana, dok se javne CSV/XML objave čuvaju najmanje onoliko dana koliko je postavljeno u arhivi (minimum 30).', 'sidrena' ); ?></p>
				<div class="sid-history-stack">
					<div><span class="dashicons dashicons-products"></span><span><?php esc_html_e( 'WooCommerce povijest', 'sidrena' ); ?></span><strong><?php echo esc_html( $product_hist ); ?></strong></div>
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
				<div><h3><?php esc_html_e( 'Marka proizvoda', 'sidrena' ); ?></h3><p><?php echo 0 === $stats['missing_brand'] ? esc_html__( 'Sve WooCommerce stavke imaju prepoznatu marku.', 'sidrena' ) : esc_html( sprintf( __( '%d stavki nema prepoznatu marku. Za proizvod bez robne marke provjerite kako ga treba označiti u vlastitoj evidenciji.', 'sidrena' ), $stats['missing_brand'] ) ); ?></p></div>
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
		$checks = array(
			array( ! $needs_products || Sidrena_Utils::is_woocommerce_active(), __( 'WooCommerce je dostupan za način rada s proizvodima', 'sidrena' ), __( 'Aktivirajte WooCommerce ili promijenite način rada.', 'sidrena' ) ),
			array( ! $missing_address, __( 'Sve aktivne lokacije imaju adresu za naziv datoteke', 'sidrena' ), __( 'Dopunite adresu u kartici Lokacije.', 'sidrena' ) ),
			array( ! $needs_products || 0 === $stats['missing_anchor'], __( 'WooCommerce stavke imaju sidrenu cijenu', 'sidrena' ), __( 'Izvezite popis nedostajućih i dopunite povijesne vrijednosti.', 'sidrena' ) ),
			array( ! $needs_products || 0 === $stats['missing_brand'], __( 'WooCommerce stavke imaju podatak o marki za digitalni cjenik', 'sidrena' ), __( 'Dopunite marku kroz WooCommerce Brands, atribut pa_brand ili Sidrena polje Marka.', 'sidrena' ) ),
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
		?>
		<div class="sid-page-head">
			<div><span class="sid-kicker"><?php esc_html_e( 'Aktualne objave', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Javni cjenici', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Svaka aktivna lokacija dobiva zasebnu CSV/XML datoteku. U slučaju greške zadnja uspješno generirana datoteka ostaje aktualna.', 'sidrena' ); ?></p></div>
			<div class="sid-head-inline-actions">
				<?php if ( $public_page_id ) : ?>
					<a class="button sid-secondary" href="<?php echo esc_url( get_permalink( $public_page_id ) ); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-external"></span><?php esc_html_e( 'Otvori javnu stranicu', 'sidrena' ); ?></a>
				<?php else : ?>
					<a class="button sid-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_create_public_page' ), 'sidrena_create_public_page' ) ); ?>"><span class="dashicons dashicons-admin-page"></span><?php esc_html_e( 'Izradi stranicu Cjenici', 'sidrena' ); ?></a>
				<?php endif; ?>
				<a class="button button-primary sid-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_generate' ), 'sidrena_generate' ) ); ?>"><span class="dashicons dashicons-update"></span><?php esc_html_e( 'Generiraj sada', 'sidrena' ); ?></a>
			</div>
		</div>

		<div class="sid-grid sid-grid-3">
			<section class="sid-card sid-mini-stat"><span><?php esc_html_e( 'Aktualnih datoteka', 'sidrena' ); ?></span><strong><?php echo esc_html( count( $current ) ); ?></strong></section>
			<section class="sid-card sid-mini-stat"><span><?php esc_html_e( 'Zadnje generiranje', 'sidrena' ); ?></span><strong class="sid-mini-date"><?php echo ! empty( $last['generated_at'] ) ? esc_html( $last['generated_at'] ) : '—'; ?></strong></section>
			<section class="sid-card sid-mini-stat"><span><?php esc_html_e( 'Manifest', 'sidrena' ); ?></span><strong><?php echo 'yes' === $settings['publish_manifest'] ? esc_html__( 'Uključen', 'sidrena' ) : esc_html__( 'Isključen', 'sidrena' ); ?></strong></section>
		</div>

		<section class="sid-card">
			<div class="sid-section-head"><div><h2><?php esc_html_e( 'Datoteke dostupne javnosti', 'sidrena' ); ?></h2><p><?php esc_html_e( 'SHA-256 omogućuje naknadnu provjeru da sadržaj arhivirane datoteke nije promijenjen.', 'sidrena' ); ?></p></div></div>
			<?php $this->files_table( $current, false ); ?>
		</section>

		<section class="sid-card sid-public-page-card">
			<div class="sid-section-head">
				<div><span class="sid-kicker"><?php esc_html_e( 'Javna dostupnost', 'sidrena' ); ?></span><h2><?php esc_html_e( 'WordPress stranica s aktualnim cjenicima i arhivom', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Sidrena može izraditi običnu javnu WordPress stranicu “Cjenici” sa shortcodeom koji prikazuje aktualne datoteke i prethodne objave iz javne arhive.', 'sidrena' ); ?></p></div>
				<?php if ( $public_page_id ) : ?>
					<span class="sid-status-pill is-ok"><?php esc_html_e( 'Objavljeno', 'sidrena' ); ?></span>
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
			<div class="sid-code-row"><span><?php esc_html_e( 'REST indeks cjenika', 'sidrena' ); ?></span><code><?php echo esc_html( rest_url( 'sidrena/v1/cjenici' ) ); ?></code></div><div class="sid-code-row"><span><?php esc_html_e( 'Cijene u realnom vremenu', 'sidrena' ); ?></span><code><?php echo esc_html( rest_url( 'sidrena/v1/cijene' ) ); ?></code></div>
			<?php if ( 'yes' === $settings['publish_manifest'] ) : ?><div class="sid-code-row"><span>JSON manifest</span><code><?php echo esc_html( $paths['manifest_url'] ); ?></code></div><?php endif; ?>
			<div class="sid-code-row"><span><?php esc_html_e( 'Javna lista', 'sidrena' ); ?></span><code>[sidrena_cjenici]</code></div>
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
		<div class="sid-page-head"><div><span class="sid-kicker"><?php esc_html_e( 'Javna povijest', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Arhiva cjenika najmanje 30 dana', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Prethodno važeće datoteke ostaju u javnoj arhivi do isteka postavljenog razdoblja čuvanja. Zadano je 45 dana kako bi postojala operativna rezerva iznad minimuma.', 'sidrena' ); ?></p></div><a class="button sid-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_export_archive_index' ), 'sidrena_export_archive_index' ) ); ?>"><span class="dashicons dashicons-download"></span><?php esc_html_e( 'Izvezi evidenciju arhive', 'sidrena' ); ?></a></div>
		<div class="sid-grid sid-grid-4">
			<?php $this->metric_card( __( 'Datoteke', 'sidrena' ), $stats['files'], 'dashicons-media-default', __( 'u arhivskom indeksu', 'sidrena' ) ); ?>
			<?php $this->metric_card( __( 'Dani s objavama', 'sidrena' ), $stats['distinct_days'], 'dashicons-calendar', __( 'informativno; usluge se objavljuju i pri promjeni', 'sidrena' ) ); ?>
			<?php $this->metric_card( __( 'Politika čuvanja', 'sidrena' ), $settings['retention_days'] . ' d', 'dashicons-lock', sprintf( __( '+%d dana rezerve', 'sidrena' ), $retention['buffer_days'] ), 'ok' ); ?>
			<?php $this->metric_card( __( 'Integritet', 'sidrena' ), $integrity['ok'] ? __( 'U redu', 'sidrena' ) : __( 'Provjera', 'sidrena' ), $integrity['ok'] ? 'dashicons-yes-alt' : 'dashicons-warning', $integrity['ok'] ? __( 'datoteke + SHA-256', 'sidrena' ) : __( 'nedostaje datoteka ili hash odstupa', 'sidrena' ), $integrity['ok'] ? 'ok' : 'warn' ); ?>
		</div>
		<?php $this->archive_timeline( $archive ); ?>
		<section class="sid-card">
			<div class="sid-section-head"><div><h2><?php esc_html_e( 'Arhivirane objave', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Najnovije su prikazane prve. Brisanje se provodi tek kada datoteka prijeđe postavljeno razdoblje čuvanja.', 'sidrena' ); ?></p></div></div>
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
				<?php if ( empty( $files ) ) : ?><tr><td colspan="8"><?php esc_html_e( 'Još nema generiranih datoteka.', 'sidrena' ); ?></td></tr><?php else : ?>
					<?php foreach ( $files as $file ) : ?>
						<tr>
							<?php if ( $show_location ) : ?><td><strong><?php echo esc_html( $file['location_code'] ?? '' ); ?></strong><small class="sid-cell-sub"><?php echo esc_html( $file['kind'] ?? '' ); ?></small></td><?php endif; ?>
							<td><?php echo 'products' === ( $file['catalog'] ?? '' ) ? esc_html__( 'Proizvodi', 'sidrena' ) : esc_html__( 'Usluge', 'sidrena' ); ?></td>
							<td><span class="sid-file-pill"><?php echo esc_html( strtoupper( $file['format'] ?? '' ) ); ?></span></td>
							<td><?php echo esc_html( $file['rows'] ?? 0 ); ?></td>
							<td><?php echo esc_html( $file['generated_at'] ?? '' ); ?><small class="sid-cell-sub"><?php echo esc_html( $file['filename'] ?? '' ); ?></small></td>
							<td><?php echo ! empty( $file['retain_until_ts'] ) ? esc_html( wp_date( 'd.m.Y. H:i', absint( $file['retain_until_ts'] ) ) ) : '—'; ?></td>
							<td><code class="sid-hash" title="<?php echo esc_attr( $file['sha256'] ?? '' ); ?>"><?php echo esc_html( ! empty( $file['sha256'] ) ? substr( $file['sha256'], 0, 12 ) . '…' : '—' ); ?></code></td>
							<td><a class="button button-small" href="<?php echo esc_url( $file['url'] ?? '' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Otvori', 'sidrena' ); ?></a></td>
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
		$coverage    = $location_id ? Sidrena_Location_Data::coverage( $location_id ) : 0;
		$kind        = $location['kind'] ?? 'objekt';
		?>
		<div class="sid-location">
			<div class="sid-location-head">
				<div><span class="sid-location-icon dashicons <?php echo 'webshop' === sanitize_key( $kind ) ? 'dashicons-store' : 'dashicons-location'; ?>"></span><strong><?php echo esc_html( $location['code'] ?: __( 'Nova lokacija', 'sidrena' ) ); ?></strong><small><?php echo esc_html( $location['address'] ?: __( 'Adresa nije upisana', 'sidrena' ) ); ?></small></div>
				<div class="sid-location-actions"><label class="sid-switch"><input type="checkbox" name="locations[<?php echo esc_attr( $index ); ?>][enabled]" value="yes" <?php checked( $location['enabled'] ?? '', 'yes' ); ?>><span><?php esc_html_e( 'Aktivna', 'sidrena' ); ?></span></label><button type="button" class="button-link-delete sid-remove-location"><?php esc_html_e( 'Ukloni', 'sidrena' ); ?></button></div>
			</div>
			<div class="sid-fields sid-fields-location">
				<label><span><?php esc_html_e( 'ID lokacije', 'sidrena' ); ?></span><input type="text" name="locations[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $location['id'] ?? '' ); ?>" placeholder="zagreb-centar" required></label>
				<label><span><?php esc_html_e( 'Oblik / vrsta objekta', 'sidrena' ); ?></span><input type="text" name="locations[<?php echo esc_attr( $index ); ?>][kind]" value="<?php echo esc_attr( $kind ); ?>" placeholder="prodavaonica / servis / webshop" required></label>
				<label><span><?php esc_html_e( 'Oznaka objekta', 'sidrena' ); ?></span><input type="text" name="locations[<?php echo esc_attr( $index ); ?>][code]" value="<?php echo esc_attr( $location['code'] ?? '' ); ?>" placeholder="P-01" required></label>
				<label class="sid-wide"><span><?php esc_html_e( 'Adresa objekta', 'sidrena' ); ?></span><input type="text" name="locations[<?php echo esc_attr( $index ); ?>][address]" value="<?php echo esc_attr( $location['address'] ?? '' ); ?>" placeholder="Ilica 150, Zagreb" required></label>
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

			<section class="sid-card sid-settings-section"><div class="sid-settings-title"><span class="dashicons dashicons-tag"></span><div><h2><?php esc_html_e( 'Referentne cijene na webu', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Dodatna cijena prikazuje se uz WooCommerce cijenu. Tijekom akcije može se prikazati i provjerena najniža cijena iz prethodnih 30 dana.', 'sidrena' ); ?></p></div></div>
				<div class="sid-toggle-grid"><label class="sid-toggle-card"><input type="checkbox" name="display_anchor" value="yes" <?php checked( $settings['display_anchor'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong><?php esc_html_e( 'Prikaži sidrenu cijenu', 'sidrena' ); ?></strong><small><?php esc_html_e( 'Uz aktualnu WooCommerce cijenu.', 'sidrena' ); ?></small></span></label><label class="sid-toggle-card"><input type="checkbox" name="display_lowest_30" value="yes" <?php checked( $settings['display_lowest_30'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong><?php esc_html_e( 'Prikaži najnižu cijenu 30 dana', 'sidrena' ); ?></strong><small><?php esc_html_e( 'Samo kod aktivnog sniženja i kada je podatak provjerljiv.', 'sidrena' ); ?></small></span></label></div>
				<div class="sid-fields"><label><span><?php esc_html_e( 'Standardni referentni datum', 'sidrena' ); ?></span><input type="date" name="default_ref_date" value="<?php echo esc_attr( $settings['default_ref_date'] ); ?>"></label><label><span><?php esc_html_e( 'FMCG referentni datum', 'sidrena' ); ?></span><input type="date" name="fmcg_ref_date" value="<?php echo esc_attr( $settings['fmcg_ref_date'] ); ?>"></label></div>
			</section>

			<section class="sid-card sid-settings-section"><div class="sid-settings-title"><span class="dashicons dashicons-media-spreadsheet"></span><div><h2><?php esc_html_e( 'Digitalni cjenici i arhiva', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Datoteke se objavljuju u uploads/sidrena/arhiva i ostaju javno dostupne najmanje 30 dana.', 'sidrena' ); ?></p></div></div>
				<div class="sid-toggle-grid"><label class="sid-toggle-card"><input type="checkbox" name="generate_csv" value="yes" <?php checked( $settings['generate_csv'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong>CSV</strong><small><?php esc_html_e( 'Strojno čitljiv cjenik.', 'sidrena' ); ?></small></span></label><label class="sid-toggle-card"><input type="checkbox" name="generate_xml" value="yes" <?php checked( $settings['generate_xml'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong>XML</strong><small><?php esc_html_e( 'Strojno čitljiv cjenik.', 'sidrena' ); ?></small></span></label><label class="sid-toggle-card"><input type="checkbox" name="publish_manifest" value="yes" <?php checked( $settings['publish_manifest'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong>JSON manifest</strong><small><?php esc_html_e( 'Indeks aktualnih i arhivskih datoteka s hashom.', 'sidrena' ); ?></small></span></label><label class="sid-toggle-card"><input type="checkbox" name="enable_rest_index" value="yes" <?php checked( $settings['enable_rest_index'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong>REST API</strong><small><?php esc_html_e( 'Javni indeks i aktualne maloprodajne cijene za automatizirani dohvat u realnom vremenu.', 'sidrena' ); ?></small></span></label></div>
				<div class="sid-fields"><label><span><?php esc_html_e( 'Vrijeme dnevnog generiranja', 'sidrena' ); ?></span><input type="time" name="generation_time" value="<?php echo esc_attr( $settings['generation_time'] ); ?>"><small><?php esc_html_e( 'Preporuka: dovoljno prije 08:00.', 'sidrena' ); ?></small></label><label><span><?php esc_html_e( 'Čuvanje arhive (dana)', 'sidrena' ); ?></span><input type="number" min="30" max="3650" name="retention_days" value="<?php echo esc_attr( $settings['retention_days'] ); ?>"><small><?php esc_html_e( 'Plugin ne dopušta manje od 30 dana.', 'sidrena' ); ?></small></label><label><span><?php esc_html_e( 'CSV razdjelnik', 'sidrena' ); ?></span><select name="csv_delimiter"><option value=";" <?php selected( $settings['csv_delimiter'], ';' ); ?>>;</option><option value="," <?php selected( $settings['csv_delimiter'], ',' ); ?>>,</option><option value="\t" <?php selected( $settings['csv_delimiter'], '\t' ); ?>>TAB</option></select></label></div>
			</section>

			<section class="sid-card sid-settings-section"><div class="sid-settings-title"><span class="dashicons dashicons-chart-line"></span><div><h2><?php esc_html_e( 'Povijest cijena', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Lokalna povijest služi za izračun najniže cijene u 30 dana prije početka sniženja. Čuva se dulje od 30 dana kako bi početak svakog budućeg prozora imao poznato stanje cijene.', 'sidrena' ); ?></p></div></div><div class="sid-toggle-grid"><label class="sid-toggle-card"><input type="checkbox" name="track_price_history" value="yes" <?php checked( $settings['track_price_history'], 'yes' ); ?>><span class="sid-toggle-ui"></span><span><strong><?php esc_html_e( 'Prati cijene proizvoda i usluga', 'sidrena' ); ?></strong><small><?php esc_html_e( 'Promjene + dnevni snapshot za WooCommerce i Sidrena usluge.', 'sidrena' ); ?></small></span></label></div></section>

			<div class="sid-form-actions sid-sticky-actions"><button class="button button-primary sid-primary" type="submit"><?php esc_html_e( 'Spremi postavke', 'sidrena' ); ?></button></div>
		</form>
		<?php
	}

	private function tools_tab() {
		?>
		<div class="sid-page-head"><div><span class="sid-kicker"><?php esc_html_e( 'Uvoz i provjera', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Alati', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Masovno dopunite povijesne sidrene cijene i lokacijsku raspoloživost bez ručnog otvaranja svakog proizvoda.', 'sidrena' ); ?></p></div></div>
		<div class="sid-grid sid-grid-2">
			<form class="sid-card sid-tool-card" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sidrena_import_anchor"><?php wp_nonce_field( 'sidrena_import_anchor' ); ?>
				<div class="sid-tool-icon"><span class="dashicons dashicons-tag"></span></div><h2><?php esc_html_e( 'Uvoz sidrenih cijena', 'sidrena' ); ?></h2><p><?php esc_html_e( 'CSV stupci: sku, anchor_price, anchor_date, reference_group. Prihvaća ; , ili TAB.', 'sidrena' ); ?></p><input class="sid-file-input" type="file" name="anchor_csv" accept=".csv,text/csv,text/plain" required><button class="button button-primary sid-primary" type="submit"><?php esc_html_e( 'Uvezi sidrene cijene', 'sidrena' ); ?></button>
			</form>
			<form class="sid-card sid-tool-card" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sidrena_import_location_data"><?php wp_nonce_field( 'sidrena_import_location_data' ); ?>
				<div class="sid-tool-icon"><span class="dashicons dashicons-location-alt"></span></div><h2><?php esc_html_e( 'Raspoloživost i cijena po lokaciji', 'sidrena' ); ?></h2><p><?php esc_html_e( 'CSV stupci: location_id, product_id ili sku, price, anchor_price, availability. Availability: dostupno ili nedostupno. Prazna price ili anchor_price koristi osnovnu WooCommerce vrijednost.', 'sidrena' ); ?></p><input class="sid-file-input" type="file" name="location_csv" accept=".csv,text/csv,text/plain" required><button class="button button-primary sid-primary" type="submit"><?php esc_html_e( 'Uvezi lokacijske podatke', 'sidrena' ); ?></button>
			</form>
		</div>
		<div class="sid-grid sid-grid-2">
			<section class="sid-card sid-tool-card"><div class="sid-tool-icon"><span class="dashicons dashicons-warning"></span></div><h2><?php esc_html_e( 'Nedostajuće sidrene cijene', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Izvezite WooCommerce stavke bez upisane sidrene cijene, dopunite ih iz vjerodostojne evidencije i vratite CSV u gornji uvoz.', 'sidrena' ); ?></p><a class="button sid-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_export_missing' ), 'sidrena_export_missing' ) ); ?>"><?php esc_html_e( 'Preuzmi CSV za dopunu', 'sidrena' ); ?></a></section>
			<section class="sid-card sid-tool-card"><div class="sid-tool-icon"><span class="dashicons dashicons-download"></span></div><h2><?php esc_html_e( 'Predložak lokacija', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Preuzmite sve aktivne lokacije i WooCommerce stavke u jednom CSV-u, dopunite raspoloživost i po potrebi cijenu, pa ga uvezite.', 'sidrena' ); ?></p><a class="button sid-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_export_location_template' ), 'sidrena_export_location_template' ) ); ?>"><?php esc_html_e( 'Preuzmi predložak', 'sidrena' ); ?></a></section>
		</div>
		<div class="sid-grid sid-grid-2">
			<section class="sid-card sid-tool-card"><div class="sid-tool-icon"><span class="dashicons dashicons-chart-area"></span></div><h2><?php esc_html_e( 'Izvoz povijesti cijena', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Jedan CSV s internom WooCommerce poviješću, poviješću usluga te lokacijskim cijenama, sidrenim cijenama i raspoloživošću. Korisno za internu provjeru i arhivu.', 'sidrena' ); ?></p><a class="button sid-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_export_price_history' ), 'sidrena_export_price_history' ) ); ?>"><?php esc_html_e( 'Preuzmi povijest CSV', 'sidrena' ); ?></a></section>
			<section class="sid-card sid-tool-card"><div class="sid-tool-icon"><span class="dashicons dashicons-media-spreadsheet"></span></div><h2><?php esc_html_e( 'Evidencija javne arhive', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Izvezite indeks svih javno objavljenih cjenika s datumom objave, rokom čuvanja, brojem redaka, veličinom, SHA-256 zapisom i javnim URL-om.', 'sidrena' ); ?></p><a class="button sid-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sidrena_export_archive_index' ), 'sidrena_export_archive_index' ) ); ?>"><?php esc_html_e( 'Preuzmi indeks arhive', 'sidrena' ); ?></a></section>
		</div>
		<section class="sid-card sid-note"><div class="sid-note-icon"><span class="dashicons dashicons-shield"></span></div><div><h2><?php esc_html_e( 'Podaci ostaju na vašem WordPressu', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Nema telemetrije, cloud računa, licencnog servera niti automatskog slanja poslovnih podataka Brendigu ili trećim stranama. Javno se izlažu samo cjenici koje administrator generira.', 'sidrena' ); ?></p></div></section>
		<?php
	}


	private function log_tab() {
		$rows = Sidrena_Audit::recent( 150 );
		?>
		<div class="sid-page-head"><div><span class="sid-kicker"><?php esc_html_e( 'Lokalna sljedivost', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Dnevnik važnih događaja', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Generiranje cjenika i administrativne promjene bilježe se lokalno radi lakše provjere rada. Dnevnik se ne šalje Brendigu niti trećim stranama.', 'sidrena' ); ?></p></div></div>
		<section class="sid-card">
			<div class="sid-table-wrap"><table class="widefat striped sid-log-table"><thead><tr><th><?php esc_html_e( 'Vrijeme', 'sidrena' ); ?></th><th><?php esc_html_e( 'Događaj', 'sidrena' ); ?></th><th><?php esc_html_e( 'Status', 'sidrena' ); ?></th><th><?php esc_html_e( 'Opis', 'sidrena' ); ?></th></tr></thead><tbody>
			<?php if ( empty( $rows ) ) : ?><tr><td colspan="4"><?php esc_html_e( 'Dnevnik je zasad prazan.', 'sidrena' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $rows as $row ) : ?>
			<tr><td><?php echo esc_html( Sidrena_Utils::format_mysql_datetime( $row['created_at'] ) ); ?></td><td><code><?php echo esc_html( $row['event_type'] ); ?></code></td><td><span class="sid-status sid-status-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( ucfirst( $row['status'] ) ); ?></span></td><td><?php echo esc_html( $row['message'] ); ?></td></tr>
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
			<?php $this->rule_card( '05', __( 'Maloprodajna, jedinična i cijena usluge', 'sidrena' ), __( 'Pravilnik uređuje jasan prikaz maloprodajne i jedinične cijene te iznimke. Za usluge traži lako dostupan cjenik, naziv, vrstu i opseg usluge te uključivanje pripadajućih troškova u cijenu; cijena ugradbene ili zamjenske robe prikazuje se uz pripadajuću uslugu.', 'sidrena' ), 'https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_105_1270.html', 'NN 105/2026, 1270' ); ?>
			<section class="sid-card sid-rule-card sid-rule-future"><div class="sid-rule-number">06</div><div><span class="sid-rule-tag"><?php esc_html_e( 'Praćenje promjena', 'sidrena' ); ?></span><h3><?php esc_html_e( 'Bazna cijena u Zakonu od 17.11.2026.', 'sidrena' ); ?></h3><p><?php esc_html_e( 'Članak 7. stavci 1. do 9. izmijenjenog Zakona počinju se primjenjivati 17.11.2026. i uvode obvezu isticanja bazne cijene te objave važećih cjenika proizvoda na mrežnim stranicama. Način isticanja i objave uređuje se pravilnicima, pa Sidrena te kasnije obveze prikazuje odvojeno od mjera iz NN 101/2026.', 'sidrena' ); ?></p><a href="https://narodne-novine.nn.hr/clanci/sluzbeni/2026_06_59_728.html" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Otvori službeni izvor', 'sidrena' ); ?><span class="dashicons dashicons-external"></span></a></div></section>
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

		$new = array(
			'business_mode'       => $business_mode,
			'display_anchor'      => isset( $_POST['display_anchor'] ) ? 'yes' : 'no',
			'display_lowest_30'   => isset( $_POST['display_lowest_30'] ) ? 'yes' : 'no',
			'label_mode'          => 'date_only',
			'label_custom'        => $old['label_custom'],
			'default_ref_date'    => $this->date( $this->post_value( 'default_ref_date', '2026-09-10' ), '2026-09-10' ),
			'fmcg_ref_date'       => $this->date( $this->post_value( 'fmcg_ref_date', '2025-05-02' ), '2025-05-02' ),
			'generate_csv'        => isset( $_POST['generate_csv'] ) ? 'yes' : 'no',
			'generate_xml'        => isset( $_POST['generate_xml'] ) ? 'yes' : 'no',
			'csv_delimiter'       => $csv_delimiter,
			'generation_time'     => $generation_time,
			'retention_days'      => max( 30, min( 3650, absint( $this->post_value( 'retention_days', 45 ) ) ) ),
			'enable_rest_index'   => isset( $_POST['enable_rest_index'] ) ? 'yes' : 'no',
			'publish_manifest'    => isset( $_POST['publish_manifest'] ) ? 'yes' : 'no',
			'track_price_history' => isset( $_POST['track_price_history'] ) ? 'yes' : 'no',
		);

		update_option( 'sidrena_settings', $new, false );
		wp_clear_scheduled_hook( 'sidrena_daily_generation' );
		wp_schedule_event( Sidrena_Utils::schedule_timestamp( $new['generation_time'] ), 'daily', 'sidrena_daily_generation' );
		Sidrena_Audit::log( 'settings_save', 'success', __( 'Sidrena postavke su spremljene.', 'sidrena' ), array( 'generation_time' => $new['generation_time'], 'retention_days' => $new['retention_days'] ) );
		$this->redirect( 'settings', 'saved' );
	}

	public function save_locations() {
		$this->guard_post( 'sidrena_save_locations' );
		$input = isset( $_POST['locations'] ) && is_array( $_POST['locations'] ) ? wp_unslash( $_POST['locations'] ) : array();
		$out   = array();
		$used  = array();
		$old_ids = array();
		foreach ( Sidrena_Utils::locations() as $old_location ) {
			if ( ! empty( $old_location['id'] ) ) {
				$old_ids[] = Sidrena_Utils::sanitize_location_id( $old_location['id'] );
			}
		}

		foreach ( $input as $index => $location ) {
			$raw_id = sanitize_text_field( $location['id'] ?? '' );
			$id     = $raw_id ? Sidrena_Utils::sanitize_location_id( $raw_id ) : 'lokacija-' . ( absint( $index ) + 1 );
			$base   = $id;
			$suffix = 2;
			while ( isset( $used[ $id ] ) ) {
				$id = $base . '-' . $suffix;
				++$suffix;
			}
			$used[ $id ] = true;

			$out[] = array(
				'id'       => $id,
				'enabled'  => isset( $location['enabled'] ) ? 'yes' : 'no',
				'kind'     => sanitize_text_field( $location['kind'] ?? 'objekt' ),
				'address'  => sanitize_text_field( $location['address'] ?? '' ),
				'code'     => sanitize_text_field( $location['code'] ?? '01' ),
				'sequence' => max( 1, absint( $location['sequence'] ?? 1 ) ),
			);
		}

		if ( empty( $out ) ) {
			$out = Sidrena_Utils::locations();
		}
		$new_ids = wp_list_pluck( $out, 'id' );
		foreach ( array_diff( $old_ids, $new_ids ) as $deleted_id ) {
			Sidrena_Location_Data::delete_location( $deleted_id );
		}
		update_option( 'sidrena_locations', $out, false );
		Sidrena_Audit::log( 'locations_save', 'success', sprintf( __( 'Spremljeno lokacija: %d.', 'sidrena' ), count( $out ) ), array( 'count' => count( $out ) ) );
		$this->redirect( 'locations', 'saved' );
	}

	public function generate() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'sidrena_generate' ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}
		$success = Sidrena_Pricelist::instance()->generate_all();
		$this->redirect( 'files', $success ? 'generated' : 'generated_with_errors' );
	}

	public function create_public_page() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'sidrena_create_public_page' ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}

		$existing_id = absint( get_option( 'sidrena_public_page_id', 0 ) );
		if ( $existing_id ) {
			$existing = get_post( $existing_id );
			if ( $existing && 'trash' !== $existing->post_status ) {
				$this->redirect( 'files', 'public_page_exists' );
			}
		}

		$page_id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => __( 'Cjenici', 'sidrena' ),
				'post_name'      => 'cjenici',
				'post_content'   => '<!-- wp:shortcode -->[sidrena_cjenici archive="yes"]<!-- /wp:shortcode -->',
				'comment_status' => 'closed',
			),
			true
		);

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			$this->redirect( 'files', 'public_page_failed' );
		}

		update_option( 'sidrena_public_page_id', absint( $page_id ), false );
		Sidrena_Audit::log( 'public_page_create', 'success', __( 'Objavljena je javna stranica Cjenici.', 'sidrena' ), array( 'page_id' => absint( $page_id ) ) );
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
			'sku'             => array( 'sku', 'sifra', 'šifra' ),
			'anchor_price'    => array( 'anchor_price', 'sidrena_cijena', 'dodatna_cijena', 'sidrena_price' ),
			'anchor_date'     => array( 'anchor_date', 'datum_sidrene_cijene', 'referentni_datum' ),
			'reference_group' => array( 'reference_group', 'referentna_skupina', 'skupina' ),
		);
		$map = $this->resolve_aliases( $map, $aliases );
		if ( ! isset( $map['sku'], $map['anchor_price'] ) ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$this->redirect( 'tools', 'import_failed' );
		}

		while ( ( $row = fgetcsv( $resource, 0, $delimiter ) ) !== false ) {
			$sku = isset( $row[ $map['sku'] ] ) ? sanitize_text_field( $row[ $map['sku'] ] ) : '';
			if ( ! $sku ) {
				continue;
			}
			$product_id = Sidrena_Utils::find_product_id_by_code( $sku );
			if ( ! $product_id ) {
				continue;
			}
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
		Sidrena_Audit::log( 'anchor_import', 'success', __( 'Uvezen je CSV sidrenih cijena.', 'sidrena' ) );
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
			'location_id'  => array( 'location_id', 'lokacija', 'id_lokacije' ),
			'product_id'   => array( 'product_id', 'id_proizvoda' ),
			'sku'          => array( 'sku', 'sifra', 'šifra' ),
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
		while ( ( $row = fgetcsv( $resource, 0, $delimiter ) ) !== false ) {
			$location_id = Sidrena_Utils::sanitize_location_id( $row[ $map['location_id'] ] ?? '' );
			if ( ! isset( $valid_locations[ $location_id ] ) ) {
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
				continue;
			}
			$availability = sanitize_key( remove_accents( (string) ( $row[ $map['availability'] ] ?? '' ) ) );
			$availability = 'dostupno' === $availability ? 'dostupno' : ( 'nedostupno' === $availability ? 'nedostupno' : '' );
			if ( '' === $availability ) {
				continue;
			}
			$price        = isset( $map['price'] ) ? Sidrena_Utils::decimal( $row[ $map['price'] ] ?? '' ) : '';
			$anchor_price = isset( $map['anchor_price'] ) ? Sidrena_Utils::decimal( $row[ $map['anchor_price'] ] ?? '' ) : '';
			$parent_id    = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
			$variation_id = $product->is_type( 'variation' ) ? $product->get_id() : 0;
			Sidrena_Location_Data::upsert( $location_id, $parent_id, $variation_id, $price, $availability, $anchor_price );
		}
		fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		Sidrena_Pricelist::queue_regeneration();
		Sidrena_Audit::log( 'location_import', 'success', __( 'Uvezen je CSV lokacijskih cijena i raspoloživosti.', 'sidrena' ) );
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
		$resource = fopen( $file['tmp_name'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $resource ) {
			return new WP_Error( 'upload_open' );
		}
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
		$head    = array_map( 'sanitize_key', $head );
		return array( $resource, $delimiter, array_flip( $head ) );
	}

	private function resolve_aliases( $map, $aliases ) {
		foreach ( $aliases as $canonical => $names ) {
			if ( isset( $map[ $canonical ] ) ) {
				continue;
			}
			foreach ( $names as $name ) {
				$key = sanitize_key( $name );
				if ( isset( $map[ $key ] ) ) {
					$map[ $canonical ] = $map[ $key ];
					break;
				}
			}
		}
		return $map;
	}

	public function export_missing() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'sidrena_export_missing' ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="sidrena-nedostajuce-sidrene-cijene.csv"' );
		$out = fopen( 'php://output', 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $out, array( 'sku', 'naziv', 'anchor_price', 'anchor_date', 'reference_group' ), ';' );
		foreach ( $this->catalog_items() as $item ) {
			if ( '' !== get_post_meta( $item->get_id(), '_sidrena_anchor_price', true ) ) {
				continue;
			}
			fputcsv( $out, array( Sidrena_Utils::get_product_code( $item ), $item->get_name(), '', '', get_post_meta( $item->get_id(), '_sidrena_reference_group', true ) ?: 'standard' ), ';' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	public function export_price_history() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'sidrena_export_price_history' ) ) {
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
		fputcsv(
			$out,
			array( 'vrsta_zapisa', 'zabiljezeno', 'lokacija', 'product_id', 'variation_id', 'service_id', 'sifra', 'naziv', 'cijena', 'redovna_cijena', 'akcijska_cijena', 'sidrena_cijena', 'dostupnost', 'izvor' ),
			';'
		);

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
				fputcsv(
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
				fputcsv(
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
				fputcsv(
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

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	public function export_archive_index() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'sidrena_export_archive_index' ) ) {
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
		fputcsv( $out, array( 'lokacija', 'vrsta_objekta', 'katalog', 'format', 'naziv_datoteke', 'objavljeno', 'cuvati_do', 'redaka', 'velicina_bajta', 'sha256', 'javni_url' ), ';' );

		foreach ( Sidrena_Utils::archive_index() as $entry ) {
			fputcsv(
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
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'sidrena_export_location_template' ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="sidrena-lokacije-predlozak.csv"' );
		$out = fopen( 'php://output', 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $out, array( 'location_id', 'location_code', 'product_id', 'sku', 'naziv', 'price', 'anchor_price', 'availability' ), ';' );
		foreach ( Sidrena_Utils::locations() as $location ) {
			if ( 'yes' !== ( $location['enabled'] ?? '' ) ) {
				continue;
			}
			foreach ( $this->catalog_items() as $item ) {
				$data = Sidrena_Location_Data::get_for_product( $location['id'] ?? '', $item );
				fputcsv( $out, array( $location['id'] ?? '', $location['code'] ?? '', $item->get_id(), Sidrena_Utils::get_product_code( $item ), $item->get_name(), $data['price'] ?? '', $data['anchor_price'] ?? '', $data['availability'] ?? '' ), ';' );
			}
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	private function audit_stats() {
		$products        = 0;
		$missing         = 0;
		$sale_incomplete           = 0;
		$active_sales              = 0;
		$perishable_expiry_missing = 0;
		$missing_brand             = 0;
		foreach ( $this->catalog_items() as $item ) {
			++$products;
			if ( '' === get_post_meta( $item->get_id(), '_sidrena_anchor_price', true ) ) {
				++$missing;
			}
			$brand_product = $item->is_type( 'variation' ) ? wc_get_product( $item->get_parent_id() ) : $item;
			if ( ! trim( (string) Sidrena_Utils::get_brand( $brand_product ) ) ) {
				++$missing_brand;
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

		$service_query = new WP_Query( array( 'post_type' => 'sidrena_service', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
		$services                 = count( $service_query->posts );
		$missing_service_anchor  = 0;
		$service_details_missing = 0;
		$service_sale_incomplete = 0;
		foreach ( $service_query->posts as $service_id ) {
			if ( '' === get_post_meta( $service_id, '_sidrena_service_anchor_price', true ) ) {
				++$missing_service_anchor;
			}
			if ( '' === trim( (string) get_post_meta( $service_id, '_sidrena_service_type', true ) ) || '' === trim( (string) get_post_meta( $service_id, '_sidrena_service_scope', true ) ) ) {
				++$service_details_missing;
			}
			if ( 'yes' === get_post_meta( $service_id, '_sidrena_service_sale', true ) ) {
				$reference = Sidrena_Service_History::sale_reference( $service_id );
				if ( 'incomplete' === $reference['status'] ) {
					++$service_sale_incomplete;
				}
			}
		}
		$settings = Sidrena_Utils::settings();
		$issues   = $missing + $missing_brand + $missing_service_anchor + $service_details_missing + $sale_incomplete + $service_sale_incomplete + $perishable_expiry_missing;
		if ( ( in_array( $settings['business_mode'], array( 'products', 'mixed' ), true ) && ! Sidrena_Utils::is_woocommerce_active() ) || ( 'no' === $settings['generate_csv'] && 'no' === $settings['generate_xml'] ) ) {
			++$issues;
		}
		if ( ! wp_next_scheduled( 'sidrena_daily_generation' ) || ! isset( $settings['generation_time'] ) || strcmp( (string) $settings['generation_time'], '08:00' ) >= 0 ) {
			++$issues;
		}
		return array(
			'products'               => $products,
			'missing_anchor'         => $missing,
			'missing_brand'          => $missing_brand,
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

	private function guard_post( $action ) {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( $action ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}
	}

	private function date( $value, $fallback ) {
		return Sidrena_Utils::sanitize_date( $value, $fallback );
	}

	private function redirect( $tab, $notice ) {
		$url = add_query_arg( array( 'page' => 'sidrena', 'tab' => sanitize_key( $tab ), 'sid_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}
}
