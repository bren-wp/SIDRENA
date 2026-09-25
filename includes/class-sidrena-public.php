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
		add_shortcode( 'sidrena_objava_cjenika', array( $this, 'publication_shortcode' ) );
		add_shortcode( 'sidrena-objava-cjenika', array( $this, 'publication_shortcode' ) );
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
				$legacy_content = trim( (string) $existing->post_content );
				if ( in_array( $legacy_content, array( '[sidrena_cjenici]', '<!-- wp:shortcode -->[sidrena_cjenici]<!-- /wp:shortcode -->' ), true ) ) {
					wp_update_post( array( 'ID' => $existing_id, 'post_content' => '<!-- wp:shortcode -->[sidrena_objava_cjenika]<!-- /wp:shortcode -->' ) );
				}
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
				'post_content'   => '<!-- wp:shortcode -->[sidrena_objava_cjenika]<!-- /wp:shortcode -->',
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
	}

	private function enqueue_assets() {
		wp_enqueue_style( 'sidrena-frontend', SIDRENA_URL . 'public/css/frontend.css', array(), SIDRENA_VERSION );
		wp_enqueue_style( 'sidrena-public' );
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
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderers escape all dynamic values before returning markup.
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
				'lokacija'      => '',
				'oznaka'        => '',
				'po_stranici'   => 50,
			),
			$atts,
			'sidrena_cjenik'
		);

		$requested = $atts['lokacija'] ? $atts['lokacija'] : $atts['oznaka'];
		$location  = $this->resolve_location( $requested );
		if ( ! $location ) {
			return '<div class="sidrena-public-message sidrena-public-message--warning">' . esc_html__( 'Nema aktivne Sidrena lokacije.', 'sidrena' ) . '</div>';
		}

		$search = isset( $_GET['sidrena_q'] ) ? sanitize_text_field( wp_unslash( $_GET['sidrena_q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = trim( $search );
		if ( function_exists( 'mb_substr' ) ) {
			$search = mb_substr( $search, 0, 120 );
		} else {
			$search = substr( $search, 0, 120 );
		}
		$page     = isset( $_GET['sidrena_stranica'] ) ? max( 1, absint( wp_unslash( $_GET['sidrena_stranica'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = min( 100, max( 10, absint( $atts['po_stranici'] ) ) );

		$this->last_snapshot_available = false;
		$snapshot = $this->read_snapshot_page( $location['id'], $search, $page, $per_page );
		if ( ! $snapshot ) {
			$this->queue_snapshot_rebuild( $location['id'] );
			return '<div class="sidrena-public-message sidrena-public-message--preparing"><strong>' . esc_html__( 'Cjenik se priprema.', 'sidrena' ) . '</strong><span>' . esc_html__( 'Sidrena je zakazala izradu javnog cjenika. Pokušajte ponovno za nekoliko trenutaka.', 'sidrena' ) . '</span></div>';
		}

		$this->last_snapshot_available = true;
		$this->enqueue_assets();
		$rows        = isset( $snapshot['rows'] ) && is_array( $snapshot['rows'] ) ? $snapshot['rows'] : array();
		$generated   = isset( $snapshot['generated_at'] ) ? (string) $snapshot['generated_at'] : '';
		$total       = absint( $snapshot['total'] ?? 0 );
		$page        = max( 1, absint( $snapshot['page'] ?? 1 ) );
		$total_pages = absint( $snapshot['total_pages'] ?? 0 );
		$currency    = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '€';
		$first_item  = $total ? ( ( $page - 1 ) * $per_page ) + 1 : 0;
		$last_item   = $total ? min( $total, $first_item + count( $rows ) - 1 ) : 0;

		ob_start();
		?>
		<section class="sidrena-pricelist">
			<div class="sidrena-pricelist__toolbar">
				<div>
					<h2><?php echo esc_html( ! empty( $location['code'] ) ? $location['code'] : __( 'Cjenik', 'sidrena' ) ); ?></h2>
					<p><?php echo esc_html( $location['address'] ); ?><?php if ( $generated ) : ?> · <?php echo esc_html( sprintf( __( 'Ažurirano: %s', 'sidrena' ), Sidrena_Utils::format_iso_datetime( $generated ) ) ); ?><?php endif; ?></p>
				</div>
				<form class="sidrena-pricelist__search" role="search" method="get">
					<label>
						<span class="screen-reader-text"><?php esc_html_e( 'Pretraži cjenik', 'sidrena' ); ?></span>
						<input type="search" name="sidrena_q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Pretraži naziv, šifru, marku ili barkod…', 'sidrena' ); ?>" maxlength="120">
					</label>
					<?php if ( isset( $_GET['lokacija'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<input type="hidden" name="lokacija" value="<?php echo esc_attr( Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' ) ); ?>">
					<?php endif; ?>
					<button type="submit"><?php esc_html_e( 'Pretraži cjenik', 'sidrena' ); ?></button>
					<?php if ( '' !== $search ) : ?><a class="sidrena-pricelist__reset" href="<?php echo esc_url( $this->pricelist_url( 1, '', $location['id'] ?? '' ) ); ?>"><?php esc_html_e( 'Očisti pretragu', 'sidrena' ); ?></a><?php endif; ?>
				</form>
			</div>

			<?php if ( 0 === $total ) : ?>
				<div class="sidrena-public-message"><?php echo '' !== $search ? esc_html__( 'Nema stavki koje odgovaraju pretrazi.', 'sidrena' ) : esc_html__( 'Cjenik je objavljen, ali trenutačno nema stavki za prikaz.', 'sidrena' ); ?></div>
			<?php else : ?>
			<p class="sidrena-pricelist__summary" aria-live="polite">
				<?php if ( '' !== $search ) : ?>
					<?php echo esc_html( sprintf( __( 'Pronađeno %d stavki.', 'sidrena' ), $total ) ); ?>
				<?php else : ?>
					<?php echo esc_html( sprintf( __( 'Prikazano %1$d–%2$d od %3$d stavki.', 'sidrena' ), $first_item, $last_item, $total ) ); ?>
				<?php endif; ?>
			</p>
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
							$type         = sanitize_key( $row['type'] ?? 'product' );
							$name         = 'service' === $type ? ( $row['naziv_usluge'] ?? '' ) : ( $row['naziv'] ?? '' );
							$code         = $row['sifra'] ?? '';
							$brand        = 'service' === $type ? '' : ( $row['marka'] ?? '' );
							$current      = $row['maloprodajna_cijena'] ?? '';
							$anchor       = $row['sidrena_cijena'] ?? '';
							$unit         = 'service' === $type ? '' : ( $row['jedinica_mjere'] ?? '' );
							$unit_price   = 'service' === $type ? '' : ( $row['cijena_za_jedinicu_mjere'] ?? '' );
							$barcode      = 'service' === $type ? '' : ( $row['barkod'] ?? '' );
							$availability = 'service' === $type ? __( 'Usluga', 'sidrena' ) : ( $row['dostupnost'] ?? '' );
							?>
							<tr>
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
			<?php if ( $total_pages > 1 ) : ?>
			<nav class="sidrena-pricelist__pagination" aria-label="<?php esc_attr_e( 'Stranice cjenika', 'sidrena' ); ?>">
				<?php if ( $page > 1 ) : ?><a rel="prev" href="<?php echo esc_url( $this->pricelist_url( $page - 1, $search, $location['id'] ?? '' ) ); ?>"><?php esc_html_e( 'Prethodna stranica', 'sidrena' ); ?></a><?php endif; ?>
				<span><?php echo esc_html( sprintf( __( 'Stranica %1$d od %2$d', 'sidrena' ), $page, $total_pages ) ); ?></span>
				<?php if ( $page < $total_pages ) : ?><a rel="next" href="<?php echo esc_url( $this->pricelist_url( $page + 1, $search, $location['id'] ?? '' ) ); ?>"><?php esc_html_e( 'Sljedeća stranica', 'sidrena' ); ?></a><?php endif; ?>
			</nav>
			<?php endif; ?>
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

		$requested   = $atts['lokacija'] ? $atts['lokacija'] : $atts['oznaka'];
		$location    = $requested ? $this->resolve_location( $requested ) : array();
		$location_id = $location ? Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' ) : '';
		$groups      = $this->archive_groups( $location_id, true );
		$total       = array_sum( array_map( 'count', $groups ) );

		$this->enqueue_assets();
		ob_start();
		?>
		<section class="sidrena-public-archive sidrena-downloads">
			<div class="sidrena-public-archive__head">
				<div>
					<h2><?php esc_html_e( 'Arhiva cjenika', 'sidrena' ); ?></h2>
					<p><?php esc_html_e( 'Prethodno objavljene CSV/XML datoteke dostupne su najmanje tijekom propisanog razdoblja čuvanja.', 'sidrena' ); ?></p>
				</div>
				<span class="sidrena-public-archive__count"><?php echo esc_html( sprintf( _n( '%d datoteka', '%d datoteka', $total, 'sidrena' ), $total ) ); ?></span>
			</div>
			<section class="sidrena-downloads__section">
				<div class="sidrena-downloads__section-head">
					<h3><?php esc_html_e( 'Prethodne objave', 'sidrena' ); ?></h3>
					<span><?php esc_html_e( 'grupirano po datumu', 'sidrena' ); ?></span>
				</div>
				<?php if ( empty( $groups ) ) : ?>
					<div class="sidrena-public-message"><?php esc_html_e( 'Arhiva još nema objavljenih datoteka.', 'sidrena' ); ?></div>
				<?php else : ?>
					<?php echo $this->render_archive_groups( $groups ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internal renderer escapes all dynamic values. ?>
				<?php endif; ?>
			</section>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public function publication_shortcode( $atts ) {
		if ( 'yes' !== Sidrena_Utils::settings()['enable_public_html'] ) {
			return '';
		}
		$atts = shortcode_atts(
			array(
				'lokacija' => '',
				'oznaka'   => '',
			),
			$atts,
			'sidrena_objava_cjenika'
		);
		$this->enqueue_assets();
		$identity = Sidrena_Utils::business_identity();
		ob_start();
		?>
		<section class="sidrena-publication">
			<header class="sidrena-publication__hero">
				<span class="sidrena-public-kicker"><?php esc_html_e( 'Objava cjenika', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Aktualne cijene i prethodne objave', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Na jednom mjestu dostupni su aktualni pretraživi cjenik, CSV/XML datoteke za preuzimanje i arhiva prethodnih objava.', 'sidrena' ); ?></p>
			</header>
			<?php if ( ! empty( $identity['show'] ) && array_filter( $identity ) ) : ?>
			<section class="sidrena-business-card" aria-label="<?php esc_attr_e( 'Podaci poslovnog subjekta', 'sidrena' ); ?>">
				<div><span><?php esc_html_e( 'Poslovni subjekt', 'sidrena' ); ?></span><strong><?php echo esc_html( $identity['name'] ?: get_bloginfo( 'name' ) ); ?></strong><?php if ( $identity['address'] ) : ?><small><?php echo esc_html( $identity['address'] ); ?></small><?php endif; ?></div>
				<dl>
					<?php if ( $identity['oib'] ) : ?><div><dt>OIB</dt><dd><?php echo esc_html( $identity['oib'] ); ?></dd></div><?php endif; ?>
					<?php if ( $identity['email'] ) : ?><div><dt><?php esc_html_e( 'E-mail', 'sidrena' ); ?></dt><dd><a href="mailto:<?php echo esc_attr( $identity['email'] ); ?>"><?php echo esc_html( $identity['email'] ); ?></a></dd></div><?php endif; ?>
					<?php if ( $identity['phone'] ) : ?><div><dt><?php esc_html_e( 'Telefon', 'sidrena' ); ?></dt><dd><?php echo esc_html( $identity['phone'] ); ?></dd></div><?php endif; ?>
					<?php if ( $identity['registry'] || $identity['registry_number'] ) : ?><div><dt><?php esc_html_e( 'Registar', 'sidrena' ); ?></dt><dd><?php echo esc_html( trim( $identity['registry'] . ' ' . $identity['registry_number'] ) ); ?></dd></div><?php endif; ?>
					<?php if ( $identity['vat_id'] ) : ?><div><dt><?php esc_html_e( 'PDV ID', 'sidrena' ); ?></dt><dd><?php echo esc_html( $identity['vat_id'] ); ?></dd></div><?php endif; ?>
					<?php if ( $identity['supervisory_authority'] ) : ?><div><dt><?php esc_html_e( 'Nadležno tijelo', 'sidrena' ); ?></dt><dd><?php echo esc_html( $identity['supervisory_authority'] ); ?></dd></div><?php endif; ?>
				</dl>
			</section>
			<?php endif; ?>
			<div class="sidrena-publication__section"><?php echo $this->pricelist_shortcode( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes all dynamic values. ?></div>
			<div class="sidrena-publication__section"><?php echo wp_kses_post( $this->downloads_shortcode( $atts ) ); ?></div>
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

		$groups = $this->archive_groups( $location_id, true );

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
					<?php echo $this->render_archive_groups( $groups ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internal renderer escapes all dynamic values. ?>
				<?php endif; ?>
			</section>

			<footer class="sidrena-downloads__source">
				<?php echo esc_html( sprintf( __( 'Izvor podataka: %s', 'sidrena' ), get_bloginfo( 'name' ) ) ); ?>
			</footer>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private function archive_groups( $location_id = '', $exclude_current = true ) {
		$location_id   = Sidrena_Utils::sanitize_location_id( $location_id );
		$current_names = array();
		if ( $exclude_current ) {
			foreach ( Sidrena_Utils::public_index() as $entry ) {
				if ( $location_id && Sidrena_Utils::sanitize_location_id( $entry['location_id'] ?? '' ) !== $location_id ) {
					continue;
				}
				$filename = sanitize_file_name( (string) ( $entry['filename'] ?? '' ) );
				if ( $filename ) {
					$current_names[ $filename ] = true;
				}
			}
		}

		$groups = array();
		$seen   = array();
		foreach ( Sidrena_Utils::archive_index() as $entry ) {
			if ( $location_id && Sidrena_Utils::sanitize_location_id( $entry['location_id'] ?? '' ) !== $location_id ) {
				continue;
			}
			$filename = sanitize_file_name( (string) ( $entry['filename'] ?? '' ) );
			if ( ! $filename || isset( $seen[ $filename ] ) || isset( $current_names[ $filename ] ) ) {
				continue;
			}
			$seen[ $filename ] = true;
			$ts = absint( $entry['generated_ts'] ?? 0 );
			if ( ! $ts && ! empty( $entry['generated_at'] ) ) {
				$parsed = strtotime( (string) $entry['generated_at'] );
				$ts     = $parsed ? absint( $parsed ) : 0;
			}
			$key = $ts ? wp_date( 'Y-m-d', $ts ) : 'undated';
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array();
			}
			$groups[ $key ][] = $entry;
		}

		uksort(
			$groups,
			static function ( $a, $b ) {
				if ( 'undated' === $a ) {
					return 1;
				}
				if ( 'undated' === $b ) {
					return -1;
				}
				return strcmp( $b, $a );
			}
		);
		foreach ( $groups as &$entries ) {
			usort(
				$entries,
				static function ( $a, $b ) {
					$left  = absint( $a['generated_ts'] ?? 0 );
					$right = absint( $b['generated_ts'] ?? 0 );
					return $left === $right
						? strcmp( (string) ( $a['filename'] ?? '' ), (string) ( $b['filename'] ?? '' ) )
						: $right <=> $left;
				}
			);
		}
		unset( $entries );
		return $groups;
	}

	private function archive_date_label( $date ) {
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
			$timestamp = strtotime( (string) $date );
			if ( $timestamp ) {
				return wp_date( 'd.m.Y.', $timestamp );
			}
		}
		return __( 'Bez datuma', 'sidrena' );
	}

	private function render_archive_groups( $groups ) {
		ob_start();
		$group_index = 0;
		foreach ( (array) $groups as $date => $entries ) {
			++$group_index;
			?>
			<details class="sidrena-downloads__day" <?php echo 1 === $group_index ? 'open' : ''; ?>>
				<summary>
					<strong><?php echo esc_html( $this->archive_date_label( $date ) ); ?></strong>
					<span><?php echo esc_html( sprintf( _n( '%d datoteka', '%d datoteka', count( $entries ), 'sidrena' ), count( $entries ) ) ); ?></span>
				</summary>
				<div class="sidrena-downloads__grid">
					<?php foreach ( $entries as $entry ) : ?>
						<?php echo wp_kses_post( $this->download_card( $entry ) ); ?>
					<?php endforeach; ?>
				</div>
			</details>
			<?php
		}
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

	private function pricelist_url( $page, $search, $location_id ) {
		$url = remove_query_arg( array( 'sidrena_q', 'sidrena_stranica' ) );
		$args = array();
		$page = max( 1, absint( $page ) );
		if ( $page > 1 ) {
			$args['sidrena_stranica'] = $page;
		}
		if ( '' !== $search ) {
			$args['sidrena_q'] = $search;
		}
		if ( isset( $_GET['lokacija'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['lokacija'] = Sidrena_Utils::sanitize_location_id( $location_id );
		}
		return empty( $args ) ? $url : add_query_arg( $args, $url );
	}

	private function normalize_snapshot_search( $value ) {
		$value = remove_accents( wp_strip_all_tags( (string) $value ) );
		return strtolower( trim( $value ) );
	}

	private function snapshot_row_matches( $row, $needle ) {
		if ( '' === $needle ) {
			return true;
		}
		$parts = array();
		foreach ( (array) $row as $value ) {
			if ( is_scalar( $value ) ) {
				$parts[] = (string) $value;
			}
		}
		return false !== strpos( $this->normalize_snapshot_search( implode( ' ', $parts ) ), $needle );
	}

	private function read_snapshot_page( $location_id, $search = '', $page = 1, $per_page = 50 ) {
		$path = Sidrena_Utils::public_snapshot_path( $location_id );
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return array();
		}

		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return array();
		}

		$header_line = fgets( $handle, 65536 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgets
		if ( false === $header_line ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return array();
		}
		$meta = json_decode( trim( $header_line ), true );
		$expected_id = Sidrena_Utils::sanitize_location_id( $location_id );
		$snapshot_id = is_array( $meta ) ? Sidrena_Utils::sanitize_location_id( $meta['location']['id'] ?? '' ) : '';
		if (
			! is_array( $meta )
			|| 2 !== absint( $meta['schema'] ?? 0 )
			|| 'jsonl' !== ( $meta['format'] ?? '' )
			|| ! isset( $meta['location'] )
			|| ! is_array( $meta['location'] )
			|| $expected_id !== $snapshot_id
			|| empty( $meta['generated_at'] )
		) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return array();
		}

		$page     = max( 1, absint( $page ) );
		$per_page = min( 100, max( 10, absint( $per_page ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$needle   = $this->normalize_snapshot_search( $search );
		$rows     = array();
		$total    = 0;

		while ( ! feof( $handle ) ) {
			$line = fgets( $handle, 1048577 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgets
			if ( false === $line ) {
				if ( feof( $handle ) ) {
					break;
				}
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				return array();
			}
			if ( 1048576 <= strlen( $line ) && "\n" !== substr( $line, -1 ) && ! feof( $handle ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				return array();
			}
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$row = json_decode( $line, true );
			if ( ! is_array( $row ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				return array();
			}
			if ( ! $this->snapshot_row_matches( $row, $needle ) ) {
				continue;
			}
			if ( $total >= $offset && count( $rows ) < $per_page ) {
				$rows[] = $row;
			}
			++$total;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$total_pages = $total ? (int) ceil( $total / $per_page ) : 0;
		if ( $total_pages && $page > $total_pages ) {
			return $this->read_snapshot_page( $location_id, $search, $total_pages, $per_page );
		}

		$meta['rows']        = $rows;
		$meta['total']       = $total;
		$meta['page']        = $page;
		$meta['per_page']    = $per_page;
		$meta['total_pages'] = $total_pages;
		return $meta;
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
