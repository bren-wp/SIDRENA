<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sidrena_Public {
	private static $instance;
	private $last_snapshot_available = false;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'init', array( $this, 'register_rewrites' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		add_shortcode( 'sidrena_cjenik', array( $this, 'pricelist_shortcode' ) );
		add_shortcode( 'sidrena-cjenik', array( $this, 'pricelist_shortcode' ) );
		add_shortcode( 'sidrena_arhiva', array( $this, 'archive_shortcode' ) );
		add_shortcode( 'sidrena-arhiva', array( $this, 'archive_shortcode' ) );
		add_shortcode( 'sidrena_cjenici', array( $this, 'downloads_shortcode' ) );
		add_shortcode( 'sidrena-cjenici', array( $this, 'downloads_shortcode' ) );
	}

	public function register_rewrites() {
		add_rewrite_rule( '^sidrena-cjenik/?$', 'index.php?sidrena_public=cjenik', 'top' );
		add_rewrite_rule( '^arhiva-sidrene-cijene/?$', 'index.php?sidrena_public=arhiva', 'top' );
	}

	public function maybe_flush_rewrites() {
		if ( (string) get_option( 'sidrena_rewrite_version', '' ) === SIDRENA_VERSION ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( 'sidrena_rewrite_version', SIDRENA_VERSION, false );
	}

	public function query_vars( $vars ) {
		$vars[] = 'sidrena_public';
		return $vars;
	}

	public static function route_url( $route ) {
		$route = 'arhiva' === $route ? 'arhiva-sidrene-cijene' : 'sidrena-cjenik';
		return home_url( user_trailingslashit( $route ) );
	}

	public static function ensure_public_page() {
		$settings = Sidrena_Utils::settings();
		if ( 'yes' !== $settings['enable_public_html'] ) {
			return 0;
		}

		$existing_id = absint( get_option( 'sidrena_public_page_id', 0 ) );
		if ( $existing_id ) {
			$existing = get_post( $existing_id );
			if ( $existing && 'page' === $existing->post_type && 'trash' !== $existing->post_status ) {
				return $existing_id;
			}
		}

		foreach ( array( 'objava-cjenika', 'cjenici' ) as $path ) {
			$existing = get_page_by_path( $path, OBJECT, 'page' );
			if ( $existing instanceof WP_Post && 'trash' !== $existing->post_status ) {
				update_option( 'sidrena_public_page_id', absint( $existing->ID ), false );
				return absint( $existing->ID );
			}
		}

		$page_id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => __( 'Objava cjenika', 'sidrena' ),
				'post_name'      => 'objava-cjenika',
				'post_content'   => '<!-- wp:shortcode -->[sidrena_cjenici]<!-- /wp:shortcode -->',
				'comment_status' => 'closed',
			),
			true
		);

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return $page_id;
		}

		update_option( 'sidrena_public_page_id', absint( $page_id ), false );
		if ( class_exists( 'Sidrena_Audit' ) ) {
			Sidrena_Audit::log( 'public_page_create', 'success', __( 'Objavljena je javna stranica Objava cjenika.', 'sidrena' ), array( 'page_id' => absint( $page_id ) ) );
		}
		return absint( $page_id );
	}

	public function register_assets() {
		if ( ! wp_style_is( 'sidrena-frontend', 'registered' ) ) {
			wp_register_style( 'sidrena-frontend', SIDRENA_URL . 'public/css/frontend.css', array(), SIDRENA_VERSION );
		}
		wp_register_style( 'sidrena-public', SIDRENA_URL . 'public/css/public.css', array( 'sidrena-frontend' ), SIDRENA_VERSION );
		wp_register_script( 'sidrena-public', SIDRENA_URL . 'public/js/public.js', array(), SIDRENA_VERSION, true );
	}

	private function enqueue_assets() {
		wp_enqueue_style( 'sidrena-frontend', SIDRENA_URL . 'public/css/frontend.css', array(), SIDRENA_VERSION );
		wp_enqueue_style( 'sidrena-public' );
		wp_enqueue_script( 'sidrena-public' );
	}

	public function template_redirect() {
		$route = sanitize_key( (string) get_query_var( 'sidrena_public' ) );
		if ( ! in_array( $route, array( 'cjenik', 'arhiva' ), true ) ) {
			return;
		}

		$settings = Sidrena_Utils::settings();
		if ( 'yes' !== $settings['enable_public_html'] ) {
			status_header( 404 );
			nocache_headers();
			return;
		}

		$location = isset( $_GET['lokacija'] ) ? Sidrena_Utils::sanitize_location_id( wp_unslash( $_GET['lokacija'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'cjenik' === $route ) {
			$content = $this->pricelist_shortcode( array( 'lokacija' => $location ) );
			if ( ! $this->last_snapshot_available ) {
				status_header( 503 );
				header( 'Retry-After: 60' );
			} else {
				status_header( 200 );
			}
			$title = __( 'Cjenik', 'sidrena' );
		} else {
			$content = $this->archive_shortcode( array( 'lokacija' => $location ) );
			status_header( 200 );
			$title = __( 'Arhiva cjenika', 'sidrena' );
		}

		nocache_headers();
		$this->enqueue_assets();

		add_filter(
			'pre_get_document_title',
			static function () use ( $title ) {
				return $title;
			}
		);

		global $wp_query;
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->is_404 = false;
		}

		get_header();
		echo '<main id="primary" class="site-main sidrena-public-page">';
		echo '<div class="sidrena-public-shell">';
		echo '<header class="sidrena-public-head"><span class="sidrena-public-kicker">Sidrena</span><h1>' . esc_html( $title ) . '</h1></header>';
		echo wp_kses_post( $content );
		echo '</div></main>';
		get_footer();
		exit;
	}

	public function pricelist_shortcode( $atts ) {
		if ( 'yes' !== Sidrena_Utils::settings()['enable_public_html'] ) {
			return '';
		}
		$atts = shortcode_atts(
			array(
				'lokacija' => '',
				'oznaka'   => '',
			),
			$atts,
			'sidrena_cjenik'
		);

		$requested = $atts['lokacija'] ? $atts['lokacija'] : $atts['oznaka'];
		$location  = $this->resolve_location( $requested );
		if ( ! $location ) {
			return '<div class="sidrena-public-message sidrena-public-message--warning">' . esc_html__( 'Nema aktivne Sidrena lokacije.', 'sidrena' ) . '</div>';
		}

		$this->last_snapshot_available = false;
		$snapshot = $this->read_snapshot( $location['id'] );
		if ( ! $snapshot ) {
			$this->queue_snapshot_rebuild( $location['id'] );
			return '<div class="sidrena-public-message sidrena-public-message--preparing"><strong>' . esc_html__( 'Cjenik se priprema.', 'sidrena' ) . '</strong><span>' . esc_html__( 'Sidrena je zakazala izradu javnog cjenika. Pokušajte ponovno za nekoliko trenutaka.', 'sidrena' ) . '</span></div>';
		}

		$this->last_snapshot_available = true;
		$this->enqueue_assets();
		$rows = isset( $snapshot['rows'] ) && is_array( $snapshot['rows'] ) ? $snapshot['rows'] : array();
		$generated = isset( $snapshot['generated_at'] ) ? (string) $snapshot['generated_at'] : '';
		$currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '€';

		ob_start();
		?>
		<section class="sidrena-pricelist" data-sidrena-pricelist>
			<div class="sidrena-pricelist__toolbar">
				<div>
					<h2><?php echo esc_html( ! empty( $location['code'] ) ? $location['code'] : __( 'Cjenik', 'sidrena' ) ); ?></h2>
					<p><?php echo esc_html( $location['address'] ); ?><?php if ( $generated ) : ?> · <?php echo esc_html( sprintf( __( 'Ažurirano: %s', 'sidrena' ), Sidrena_Utils::format_iso_datetime( $generated ) ) ); ?><?php endif; ?></p>
				</div>
				<label class="sidrena-pricelist__search">
					<span class="screen-reader-text"><?php esc_html_e( 'Pretraži cjenik', 'sidrena' ); ?></span>
					<input type="search" placeholder="<?php esc_attr_e( 'Pretraži naziv, šifru, marku ili barkod…', 'sidrena' ); ?>" data-sidrena-search>
				</label>
			</div>

			<?php if ( empty( $rows ) ) : ?>
				<div class="sidrena-public-message"><?php esc_html_e( 'Cjenik je objavljen, ali trenutačno nema stavki za prikaz.', 'sidrena' ); ?></div>
			<?php else : ?>
			<p class="sidrena-pricelist__summary" aria-live="polite"><span><?php esc_html_e( 'Prikazano', 'sidrena' ); ?></span> <strong data-sidrena-visible-count><?php echo esc_html( count( $rows ) ); ?></strong> <span><?php echo esc_html( sprintf( __( 'od %d stavki', 'sidrena' ), count( $rows ) ) ); ?></span></p>
			<div class="sidrena-pricelist__table-wrap">
				<table class="sidrena-pricelist__table">
					<caption class="screen-reader-text"><?php esc_html_e( 'Aktualni Sidrena cjenik', 'sidrena' ); ?></caption>
					<thead>
						<tr>
							<th><?php esc_html_e( 'Naziv', 'sidrena' ); ?></th>
							<th><?php esc_html_e( 'Šifra', 'sidrena' ); ?></th>
							<th><?php esc_html_e( 'Marka', 'sidrena' ); ?></th>
							<th><?php esc_html_e( 'Cijena', 'sidrena' ); ?></th>
							<th><?php esc_html_e( 'Sidrena cijena', 'sidrena' ); ?></th>
							<th><?php esc_html_e( 'Jedinica', 'sidrena' ); ?></th>
							<th><?php esc_html_e( 'Barkod', 'sidrena' ); ?></th>
							<th><?php esc_html_e( 'Dostupnost', 'sidrena' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$type = sanitize_key( $row['type'] ?? 'product' );
							$name = 'service' === $type ? ( $row['naziv_usluge'] ?? '' ) : ( $row['naziv'] ?? '' );
							$code = 'service' === $type ? ( $row['sifra'] ?? '' ) : ( $row['sifra'] ?? '' );
							$brand = 'service' === $type ? '' : ( $row['marka'] ?? '' );
							$current = $row['maloprodajna_cijena'] ?? '';
							$anchor = $row['sidrena_cijena'] ?? '';
							$unit = 'service' === $type ? '' : ( $row['jedinica_mjere'] ?? '' );
							$unit_price = 'service' === $type ? '' : ( $row['cijena_za_jedinicu_mjere'] ?? '' );
							$barcode = 'service' === $type ? '' : ( $row['barkod'] ?? '' );
							$availability = 'service' === $type ? __( 'Usluga', 'sidrena' ) : ( $row['dostupnost'] ?? '' );
							$search = implode( ' ', array( $name, $code, $brand, $barcode, $availability, $unit ) );
							?>
							<tr data-sidrena-row data-search="<?php echo esc_attr( strtolower( remove_accents( wp_strip_all_tags( $search ) ) ) ); ?>">
								<td data-label="<?php esc_attr_e( 'Naziv', 'sidrena' ); ?>"><strong><?php echo esc_html( $name ); ?></strong><?php if ( ! empty( $row['naziv_posebnog_oblika_prodaje'] ) ) : ?><small><?php echo esc_html( $row['naziv_posebnog_oblika_prodaje'] ); ?></small><?php endif; ?></td>
								<td data-label="<?php esc_attr_e( 'Šifra', 'sidrena' ); ?>"><?php echo esc_html( $code ?: '—' ); ?></td>
								<td data-label="<?php esc_attr_e( 'Marka', 'sidrena' ); ?>"><?php echo esc_html( $brand ?: '—' ); ?></td>
								<td data-label="<?php esc_attr_e( 'Cijena', 'sidrena' ); ?>"><strong><?php echo '' !== $current ? esc_html( (string) $current . ' ' . $currency ) : '—'; ?></strong></td>
								<td data-label="<?php esc_attr_e( 'Sidrena cijena', 'sidrena' ); ?>"><?php echo '' !== $anchor ? esc_html( (string) $anchor . ' ' . $currency ) : '—'; ?></td>
								<td data-label="<?php esc_attr_e( 'Jedinica', 'sidrena' ); ?>"><?php echo esc_html( $unit ?: '—' ); ?><?php if ( '' !== $unit_price ) : ?><small><?php echo esc_html( (string) $unit_price . ' ' . $currency ); ?></small><?php endif; ?></td>
								<td data-label="<?php esc_attr_e( 'Barkod', 'sidrena' ); ?>"><?php echo esc_html( $barcode ?: '—' ); ?></td>
								<td data-label="<?php esc_attr_e( 'Dostupnost', 'sidrena' ); ?>"><span class="sidrena-availability sidrena-availability--<?php echo esc_attr( sanitize_html_class( remove_accents( strtolower( (string) $availability ) ) ) ); ?>"><?php echo esc_html( $availability ?: '—' ); ?></span></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="sidrena-pricelist__empty" data-sidrena-empty hidden><?php esc_html_e( 'Nema stavki koje odgovaraju pretrazi.', 'sidrena' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public function archive_shortcode( $atts ) {
		if ( 'yes' !== Sidrena_Utils::settings()['enable_public_html'] ) {
			return '';
		}
		$atts = shortcode_atts(
			array(
				'lokacija' => '',
				'oznaka'   => '',
			),
			$atts,
			'sidrena_arhiva'
		);

		$requested = $atts['lokacija'] ? $atts['lokacija'] : $atts['oznaka'];
		$location  = $requested ? $this->resolve_location( $requested ) : array();
		$location_id = $location ? Sidrena_Utils::sanitize_location_id( $location['id'] ) : '';

		$entries = array();
		foreach ( Sidrena_Utils::archive_index() as $entry ) {
			if ( $location_id && Sidrena_Utils::sanitize_location_id( $entry['location_id'] ?? '' ) !== $location_id ) {
				continue;
			}
			$entries[] = $entry;
		}

		$this->enqueue_assets();
		ob_start();
		?>
		<section class="sidrena-public-archive">
			<div class="sidrena-public-archive__head">
				<h2><?php esc_html_e( 'Arhiva cjenika', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Prethodno objavljene CSV/XML datoteke dostupne su najmanje tijekom propisanog razdoblja čuvanja.', 'sidrena' ); ?></p>
			</div>
			<?php if ( empty( $entries ) ) : ?>
				<div class="sidrena-public-message"><?php esc_html_e( 'Arhiva još nema objavljenih datoteka.', 'sidrena' ); ?></div>
			<?php else : ?>
				<ul class="sidrena-public-archive__list">
					<?php foreach ( $entries as $entry ) : ?>
						<li>
							<a href="<?php echo esc_url( $entry['url'] ?? '' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $entry['filename'] ?? __( 'Cjenik', 'sidrena' ) ); ?></a>
							<span><?php echo ! empty( $entry['generated_at'] ) ? esc_html( Sidrena_Utils::format_iso_datetime( $entry['generated_at'] ) ) : ''; ?></span>
							<?php if ( ! empty( $entry['sha256'] ) ) : ?><code>SHA-256 <?php echo esc_html( substr( (string) $entry['sha256'], 0, 12 ) ); ?>…</code><?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public function downloads_shortcode( $atts ) {
		if ( 'yes' !== Sidrena_Utils::settings()['enable_public_html'] ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'lokacija' => '',
				'oznaka'   => '',
			),
			$atts,
			'sidrena_cjenici'
		);
		$requested   = $atts['lokacija'] ? $atts['lokacija'] : $atts['oznaka'];
		$location    = $requested ? $this->resolve_location( $requested ) : array();
		$location_id = $location ? Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' ) : '';

		$current = array();
		foreach ( Sidrena_Utils::public_index() as $entry ) {
			if ( $location_id && Sidrena_Utils::sanitize_location_id( $entry['location_id'] ?? '' ) !== $location_id ) {
				continue;
			}
			$current[] = $entry;
		}

		$groups = array();
		$seen   = array();
		foreach ( Sidrena_Utils::archive_index() as $entry ) {
			if ( $location_id && Sidrena_Utils::sanitize_location_id( $entry['location_id'] ?? '' ) !== $location_id ) {
				continue;
			}
			$filename = sanitize_file_name( (string) ( $entry['filename'] ?? '' ) );
			if ( ! $filename || isset( $seen[ $filename ] ) ) {
				continue;
			}
			$seen[ $filename ] = true;
			$ts = absint( $entry['generated_ts'] ?? 0 );
			if ( ! $ts && ! empty( $entry['generated_at'] ) ) {
				$parsed = strtotime( (string) $entry['generated_at'] );
				$ts = $parsed ? absint( $parsed ) : 0;
			}
			$key = $ts ? wp_date( 'Y-m-d', $ts ) : __( 'Bez datuma', 'sidrena' );
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array();
			}
			$groups[ $key ][] = $entry;
		}
		krsort( $groups, SORT_STRING );

		$this->enqueue_assets();
		ob_start();
		?>
		<section class="sidrena-downloads" data-sidrena-downloads>
			<header class="sidrena-downloads__intro">
				<span class="sidrena-public-kicker"><?php esc_html_e( 'Objava cjenika', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Cjenici za preuzimanje', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Aktualne i prethodno objavljene CSV/XML datoteke dostupne su za pregled i automatsku obradu. Arhiva se prikazuje prema datumima objave.', 'sidrena' ); ?></p>
			</header>

			<div class="sidrena-downloads__summary">
				<div><span><?php esc_html_e( 'Aktualno', 'sidrena' ); ?></span><strong><?php echo esc_html( count( $current ) ); ?></strong></div>
				<div><span><?php esc_html_e( 'Arhiva', 'sidrena' ); ?></span><strong><?php echo esc_html( array_sum( array_map( 'count', $groups ) ) ); ?></strong></div>
				<div><span><?php esc_html_e( 'Izvor podataka', 'sidrena' ); ?></span><strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong></div>
			</div>

			<?php if ( ! empty( $current ) ) : ?>
				<section class="sidrena-downloads__section">
					<div class="sidrena-downloads__section-head"><h3><?php esc_html_e( 'Aktualni cjenici', 'sidrena' ); ?></h3><span><?php esc_html_e( 'zadnja uspješna objava', 'sidrena' ); ?></span></div>
					<div class="sidrena-downloads__grid">
						<?php foreach ( $current as $entry ) : ?>
							<?php echo wp_kses_post( $this->download_card( $entry ) ); ?>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<section class="sidrena-downloads__section">
				<div class="sidrena-downloads__section-head"><h3><?php esc_html_e( 'Prethodne objave', 'sidrena' ); ?></h3><span><?php esc_html_e( 'grupirano po datumu', 'sidrena' ); ?></span></div>
				<?php if ( empty( $groups ) ) : ?>
					<div class="sidrena-public-message"><?php esc_html_e( 'Arhiva još nema objavljenih datoteka.', 'sidrena' ); ?></div>
				<?php else : ?>
					<?php foreach ( $groups as $date => $entries ) : ?>
						<details class="sidrena-downloads__day" <?php echo 0 === key( array( $date => $entries ) ) ? 'open' : ''; ?>>
							<summary><strong><?php echo esc_html( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? wp_date( 'd.m.Y.', strtotime( $date ) ) : $date ); ?></strong><span><?php echo esc_html( sprintf( _n( '%d datoteka', '%d datoteka', count( $entries ), 'sidrena' ), count( $entries ) ) ); ?></span></summary>
							<div class="sidrena-downloads__grid">
								<?php foreach ( $entries as $entry ) : ?>
									<?php echo wp_kses_post( $this->download_card( $entry ) ); ?>
								<?php endforeach; ?>
							</div>
						</details>
					<?php endforeach; ?>
				<?php endif; ?>
			</section>

			<footer class="sidrena-downloads__source">
				<?php echo esc_html( sprintf( __( 'Izvor podataka: %s', 'sidrena' ), get_bloginfo( 'name' ) ) ); ?>
			</footer>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private function download_card( $entry ) {
		$filename = (string) ( $entry['filename'] ?? __( 'Cjenik', 'sidrena' ) );
		$format   = strtoupper( sanitize_key( (string) ( $entry['format'] ?? pathinfo( $filename, PATHINFO_EXTENSION ) ) ) );
		$catalog  = 'services' === sanitize_key( (string) ( $entry['catalog'] ?? '' ) ) ? __( 'Usluge', 'sidrena' ) : __( 'Proizvodi', 'sidrena' );
		$location = trim( (string) ( $entry['location_code'] ?? '' ) );
		$kind     = trim( (string) ( $entry['kind'] ?? '' ) );
		$url      = esc_url( (string) ( $entry['url'] ?? '' ) );
		$generated = ! empty( $entry['generated_at'] ) ? Sidrena_Utils::format_iso_datetime( (string) $entry['generated_at'] ) : '';

		ob_start();
		?>
		<article class="sidrena-download-card">
			<div class="sidrena-download-card__top">
				<span class="sidrena-download-card__format"><?php echo esc_html( $format ?: 'FILE' ); ?></span>
				<span><?php echo esc_html( $catalog ); ?></span>
			</div>
			<h4><?php echo esc_html( $filename ); ?></h4>
			<div class="sidrena-download-card__meta">
				<?php if ( $location || $kind ) : ?><span><?php echo esc_html( trim( $kind . ( $kind && $location ? ' · ' : '' ) . $location ) ); ?></span><?php endif; ?>
				<?php if ( $generated ) : ?><span><?php echo esc_html( $generated ); ?></span><?php endif; ?>
			</div>
			<?php if ( $url ) : ?>
				<a class="sidrena-download-card__button" href="<?php echo $url; ?>" download rel="noopener"><?php esc_html_e( 'Preuzmi', 'sidrena' ); ?></a>
			<?php else : ?>
				<span class="sidrena-download-card__button is-disabled"><?php esc_html_e( 'Datoteka nije dostupna', 'sidrena' ); ?></span>
			<?php endif; ?>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	private function resolve_location( $requested = '' ) {
		$requested = trim( (string) $requested );
		$requested = $requested ? Sidrena_Utils::sanitize_location_id( $requested ) : '';
		foreach ( Sidrena_Utils::locations() as $location ) {
			if ( 'yes' !== ( $location['enabled'] ?? '' ) ) {
				continue;
			}
			$id   = Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' );
			$code = Sidrena_Utils::sanitize_location_id( $location['code'] ?? '' );
			if ( $requested && ( $requested === $id || $requested === $code ) ) {
				return $location;
			}
			if ( ! $requested ) {
				return $location;
			}
		}
		return array();
	}

	private function read_snapshot( $location_id ) {
		$path = Sidrena_Utils::public_snapshot_path( $location_id );
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return array();
		}
		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || 1 !== absint( $data['schema'] ?? 0 ) || ! isset( $data['rows'] ) || ! is_array( $data['rows'] ) || ! isset( $data['location'] ) || ! is_array( $data['location'] ) ) {
			return array();
		}
		$expected_id = Sidrena_Utils::sanitize_location_id( $location_id );
		$snapshot_id = Sidrena_Utils::sanitize_location_id( $data['location']['id'] ?? '' );
		if ( $expected_id !== $snapshot_id || empty( $data['generated_at'] ) ) {
			return array();
		}
		return $data;
	}


	private function queue_snapshot_rebuild( $location_id ) {
		$key = 'sidrena_snapshot_rebuild_' . md5( Sidrena_Utils::sanitize_location_id( $location_id ) );
		if ( get_transient( $key ) ) {
			return;
		}
		if ( Sidrena_Pricelist::queue_regeneration() ) {
			set_transient( $key, 1, 15 * MINUTE_IN_SECONDS );
		}
	}
}
