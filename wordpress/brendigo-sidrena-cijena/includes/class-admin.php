<?php
/**
 * Admin: settings, standalone catalog, and WooCommerce product fields.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI.
 */
class Brendigo_Sidrena_Cijena_Admin {

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Mark the settings screen so its footer can be styled apart from other admin pages.
	 *
	 * @param string $classes Body classes.
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->id, array( 'woocommerce_page_brendigo-sidrena-cijena', 'toplevel_page_brendigo-sidrena-cijena' ), true ) ) {
			$classes .= ' sidrena-cijena-screen';
		} elseif ( isset( $_GET['page'] ) && 'brendigo-sidrena-cijena' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$classes .= ' sidrena-cijena-screen';
		}

		return $classes;
	}


	/**
	 * Drop the WordPress footer credit. Late, so other admin CSS cannot show it again.
	 *
	 * @param string $text Footer HTML.
	 * @return string
	 */
	public function filter_admin_footer_text( $text ) {
		if ( $this->is_settings_screen() ) {
			return '';
		}

		return $text;
	}

	/**
	 * Whether the current request is the Sidrena cijena settings screen.
	 *
	 * @return bool
	 */
	private function is_settings_screen() {
		if ( isset( $_GET['page'] ) && 'brendigo-sidrena-cijena' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && in_array( $screen->id, array( 'woocommerce_page_brendigo-sidrena-cijena', 'toplevel_page_brendigo-sidrena-cijena' ), true );
	}

	/**
	 * Register admin hooks.
	 */
	private function __construct() {
		$admin_save = Brendigo_Sidrena_Cijena_Admin_Save::instance();

		add_action( 'admin_menu', array( $this, 'register_menu' ), 60 );
		add_action( 'admin_init', array( $admin_save, 'handle_settings_save' ) );
		add_action( 'admin_init', array( $admin_save, 'handle_xml_settings_save' ) );
		add_action( 'admin_init', array( $admin_save, 'handle_poslovnice_save' ) );
		add_action( 'admin_init', array( $admin_save, 'handle_catalog_save' ) );
		add_action( 'admin_init', array( $admin_save, 'handle_woo_bulk_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
		add_filter( 'admin_footer_text', array( $this, 'filter_admin_footer_text' ), 999 );
		add_action( 'wp_ajax_sidrena_cijena_purge', array( $admin_save, 'ajax_purge' ) );

		if ( ! Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ) {
			return;
		}

		$product_fields = Brendigo_Sidrena_Cijena_Product_Fields::instance();

		add_action( 'admin_init', array( $this, 'maybe_dismiss_fill_prompt' ) );
		add_action( 'admin_notices', array( $this, 'snapshot_notice' ) );
		add_action( 'wp_ajax_sidrena_cijena_snapshot', array( $admin_save, 'ajax_snapshot' ) );

		add_action( 'woocommerce_product_options_pricing', array( $product_fields, 'render_simple_fields' ) );
		add_action( 'woocommerce_variation_options_pricing', array( $product_fields, 'render_variation_fields' ), 10, 3 );
		add_action( 'woocommerce_admin_process_product_object', array( 'Brendigo_Sidrena_Cijena_Fields', 'save_simple' ) );
		add_action( 'woocommerce_save_product_variation', array( 'Brendigo_Sidrena_Cijena_Fields', 'save_variation' ), 10, 2 );

		add_filter( 'manage_edit-product_columns', array( $this, 'add_product_column' ) );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_product_column' ), 10, 2 );
	}

	/**
	 * WooCommerce submenu, or a top-level menu without Woo.
	 */
	public function register_menu() {
		$cap   = Brendigo_Sidrena_Cijena_Plugin::manage_capability();
		$title = __( 'Brendigo Sidrena Cijena', 'brendigo-sidrena-cijena' );

		if ( Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ) {
			add_submenu_page(
				'woocommerce',
				$title,
				$title,
				$cap,
				'brendigo-sidrena-cijena',
				array( $this, 'render_settings_page' )
			);
			return;
		}

		add_menu_page(
			$title,
			$title,
			$cap,
			'brendigo-sidrena-cijena',
			array( $this, 'render_settings_page' ),
			'dashicons-tag',
			56
		);
	}

	/**
	 * Settings screen.
	 */
	public function render_settings_page() {
		if ( ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() ) {
			return;
		}

		$tab       = $this->get_settings_tab();
		$tabs      = $this->get_tabs();
		$settings  = Brendigo_Sidrena_Cijena_Settings::instance();
		$export    = Brendigo_Sidrena_Cijena_Export::instance();
		$saved     = isset( $_GET['updated'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['updated'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$xml_flag  = isset( $_GET['sidrena_xml'] ) ? sanitize_text_field( wp_unslash( $_GET['sidrena_xml'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$xml_ok    = '1' === $xml_flag;
		$xml_fail  = '0' === $xml_flag;
		$xml_empty = '2' === $xml_flag;
		$multi     = $settings->is_multi_poslovnica();
		$xml_url   = $export->get_public_url();
		$page_title = 'pregled' === $tab ? __( 'Brendigo Sidrena Cijena', 'brendigo-sidrena-cijena' ) : ( isset( $tabs[ $tab ]['label'] ) ? $tabs[ $tab ]['label'] : __( 'Brendigo Sidrena Cijena', 'brendigo-sidrena-cijena' ) );
		$page_subtitles = array(
			'pregled'      => __( 'Sidrene cijene, digitalni cjenik, arhiva, poslovnice i razmjena podataka u jednom sučelju.', 'brendigo-sidrena-cijena' ),
			'proizvodi'    => __( 'Ručno upravljanje proizvodima i uslugama koje ulaze u javni cjenik.', 'brendigo-sidrena-cijena' ),
			'postavke'     => __( 'Prikaz, tekstovi, pravila i alati za sidrene cijene.', 'brendigo-sidrena-cijena' ),
			'uvoz-izvoz'   => __( 'CSV i XML razmjena podataka za sidrene cijene i javni cjenik.', 'brendigo-sidrena-cijena' ),
			'javni-xml'    => __( 'HTML prikaz, XML/CSV datoteke i arhiva javnog cjenika.', 'brendigo-sidrena-cijena' ),
			'poslovnice'   => __( 'Više lokacija, zasebni javni cjenici i datoteke po poslovnici.', 'brendigo-sidrena-cijena' ),
			'upute'        => __( 'Korisničke upute za WordPress i WooCommerce – korak po korak.', 'brendigo-sidrena-cijena' ),
		);
		$page_subtitle = isset( $page_subtitles[ $tab ] ) ? $page_subtitles[ $tab ] : '';
		?>
		<div class="wrap sidrena-cijena-settings brendigo-sidrena-admin">
			<div class="brendigo-admin-shell">
				<aside class="brendigo-admin-sidebar" aria-label="<?php echo esc_attr__( 'Brendigo Sidrena Cijena', 'brendigo-sidrena-cijena' ); ?>">
					<a class="brendigo-admin-brand" href="<?php echo esc_url( $this->get_settings_tab_url( 'pregled' ) ); ?>">
						<span class="brendigo-admin-brand__mark brendigo-admin-brand__mark--sidrena" aria-hidden="true"><img src="<?php echo esc_url( BRENDIGO_SIDRENA_CIJENA_URL . 'assets/images/sidrena-brand-mark.png' ); ?>" alt="" width="48" height="48" loading="eager" decoding="async" /></span>
						<span class="brendigo-admin-brand__text"><strong>SIDRENA</strong><small>Brendigo</small></span>
					</a>
					<nav class="brendigo-admin-nav">
						<?php foreach ( $tabs as $key => $item ) : ?>
							<a class="brendigo-admin-nav__item<?php echo $tab === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( $this->get_settings_tab_url( $key ) ); ?>"<?php if ( $tab === $key ) : ?> aria-current="page"<?php endif; ?>>
								<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
								<span><?php echo esc_html( $item['label'] ); ?></span>
							</a>
						<?php endforeach; ?>
					</nav>
					<div class="brendigo-admin-sidebar__legal">
						<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
						<strong><?php echo esc_html__( 'Sidrena cijena', 'brendigo-sidrena-cijena' ); ?></strong>
						<small><?php echo esc_html__( 'Referentna cijena s datumom na koji je vrijedila.', 'brendigo-sidrena-cijena' ); ?></small>
					</div>
				</aside>

				<main class="brendigo-admin-main">
					<header class="brendigo-admin-topbar">
						<div>
							<p class="brendigo-admin-eyebrow"><?php echo esc_html__( 'Upravljanje sidrenim cijenama', 'brendigo-sidrena-cijena' ); ?></p>
							<h1><?php echo esc_html( $page_title ); ?></h1>
							<p><?php echo esc_html( $page_subtitle ); ?></p>
						</div>
						<div class="brendigo-admin-topbar__meta">
							<span class="brendigo-admin-chip">v<?php echo esc_html( BRENDIGO_SIDRENA_CIJENA_VERSION ); ?></span>
							<span class="brendigo-admin-chip brendigo-admin-chip--success"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php echo Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ? esc_html__( 'WooCommerce povezano', 'brendigo-sidrena-cijena' ) : esc_html__( 'Samostalni način rada', 'brendigo-sidrena-cijena' ); ?></span>
							<a class="button brendigo-admin-docs" href="<?php echo esc_url( $this->get_settings_tab_url( 'upute' ) ); ?>"><span class="dashicons dashicons-book-alt" aria-hidden="true"></span><?php echo esc_html__( 'Upute korak po korak', 'brendigo-sidrena-cijena' ); ?></a>
						</div>
					</header>

					<div class="brendigo-admin-notices">
						<?php $this->render_settings_snapshot_notice(); ?>
						<?php if ( $saved ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Postavke su spremljene.', 'brendigo-sidrena-cijena' ); ?></p></div><?php endif; ?>
						<?php if ( $xml_ok ) : ?>
							<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $multi ? __( 'Javni cjenici poslovnica su generirani.', 'brendigo-sidrena-cijena' ) : __( 'Javni cjenik je generiran.', 'brendigo-sidrena-cijena' ) ); ?><?php if ( ! $multi ) : ?> <a href="<?php echo esc_url( $xml_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $xml_url ); ?></a><?php endif; ?></p></div>
						<?php endif; ?>
						<?php if ( $xml_empty ) : ?><div class="notice notice-warning is-dismissible"><p><?php echo esc_html__( 'Nijedna poslovnica nema dodane proizvode. Dodajte proizvode na kartici Poslovnice.', 'brendigo-sidrena-cijena' ); ?></p></div><?php endif; ?>
						<?php if ( $xml_fail ) : ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html__( 'Javni cjenik nije mogao biti potpuno objavljen. Provjerite dozvole mape uploads i pokušajte ponovno.', 'brendigo-sidrena-cijena' ); ?></p></div><?php endif; ?>
						<?php do_action( 'sidrena_cijena_settings_notices', $tab ); ?>
					</div>

					<div class="sidrena-cijena-panels">
						<?php
						if ( 'pregled' === $tab ) {
							$this->render_tab_pregled( $settings, $export );
						} elseif ( 'proizvodi' === $tab ) {
							$this->render_tab_proizvodi();
						} elseif ( 'uvoz-izvoz' === $tab ) {
							$this->render_tab_uvoz_izvoz();
						} elseif ( 'javni-xml' === $tab ) {
							$this->render_tab_javni_xml( $export );
						} elseif ( 'poslovnice' === $tab ) {
							$this->render_tab_poslovnice();
						} elseif ( 'postavke' === $tab ) {
							$this->render_tab_postavke( $settings );
						} elseif ( 'upute' === $tab ) {
							$this->render_tab_upute();
						} else {
							do_action( 'sidrena_cijena_render_settings_tab', $tab );
						}
						?>

						<footer class="sidrena-cijena-card sidrena-cijena-credit">
							<div><strong><?php echo esc_html__( 'Brendigo Sidrena Cijena', 'brendigo-sidrena-cijena' ); ?></strong><p><?php echo esc_html__( 'Sidrene cijene i javni cjenik na jednom mjestu.', 'brendigo-sidrena-cijena' ); ?></p></div>
							<a class="sidrena-cijena-credit__brand" href="https://brendigo.com" target="_blank" rel="noopener noreferrer" aria-label="Brendigo.com"><span class="brendigo-admin-brand__mark brendigo-admin-brand__mark--small" aria-hidden="true">B</span><span>Brendigo</span></a>
						</footer>
					</div>
				</main>
			</div>
		</div>
		<?php
	}

	/**
	 * Settings tabs (slug => label/icon).
	 *
	 * @return array<string, array{label:string, icon:string}>
	 */
	private function get_tabs() {
		$tabs = array();

		$tabs['pregled'] = array(
			'label' => __( 'Pregled', 'brendigo-sidrena-cijena' ),
			'icon'  => 'dashicons-dashboard',
		);

		$tabs['proizvodi'] = array(
			'label' => Brendigo_Sidrena_Cijena_Plugin::woocommerce_active()
				? __( 'Dodatne stavke', 'brendigo-sidrena-cijena' )
				: __( 'Proizvodi i usluge', 'brendigo-sidrena-cijena' ),
			'icon'  => 'dashicons-products',
		);

		$tabs['postavke'] = array(
			'label' => __( 'Postavke', 'brendigo-sidrena-cijena' ),
			'icon'  => 'dashicons-admin-generic',
		);
		$tabs['uvoz-izvoz'] = array(
			'label' => __( 'Uvoz/Izvoz', 'brendigo-sidrena-cijena' ),
			'icon'  => 'dashicons-migrate',
		);
		$tabs['javni-xml'] = array(
			'label' => __( 'Javni cjenik', 'brendigo-sidrena-cijena' ),
			'icon'  => 'dashicons-media-code',
		);
		$tabs['poslovnice'] = array(
			'label' => __( 'Poslovnice', 'brendigo-sidrena-cijena' ),
			'icon'  => 'dashicons-store',
		);

		$tabs['upute'] = array(
			'label' => __( 'Upute', 'brendigo-sidrena-cijena' ),
			'icon'  => 'dashicons-book-alt',
		);

		/**
		 * Extra Sidrena cijena settings tabs.
		 *
		 * @param array<string, array{label:string, icon:string}> $tabs Tabs.
		 */
		$filtered = apply_filters( 'sidrena_cijena_settings_tabs', $tabs );

		return is_array( $filtered ) ? $filtered : $tabs;
	}

	/**
	 * Current settings tab.
	 *
	 * @return string
	 */
	private function get_settings_tab() {
		$tabs = $this->get_tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( isset( $tabs[ $tab ] ) ) {
			return $tab;
		}

		if ( isset( $_GET['sidrena_xml'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 'javni-xml';
		}

		$keys = array_keys( $tabs );
		return isset( $keys[0] ) ? $keys[0] : 'postavke';
	}

	/**
	 * Settings tab URL.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	private function get_settings_tab_url( $tab ) {
		return add_query_arg(
			array(
				'page' => 'brendigo-sidrena-cijena',
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Outlet codes for the catalog row select.
	 *
	 * @return array<int, array{oznaka:string,naziv:string}>
	 */
	private function catalog_outlet_choices() {
		$out = array();
		foreach ( Brendigo_Sidrena_Cijena_Poslovnice::instance()->all() as $outlet ) {
			$label = '' !== $outlet['naziv'] ? $outlet['naziv'] : $outlet['oznaka'];
			$out[] = array(
				'oznaka' => $outlet['oznaka'],
				'naziv'  => $label,
			);
		}

		return $out;
	}

	/**
	 * Overview dashboard.
	 *
	 * @param Brendigo_Sidrena_Cijena_Settings $sidrena_settings Settings helper.
	 * @param Brendigo_Sidrena_Cijena_Export   $sidrena_export   Export helper.
	 */
	private function render_tab_pregled( $sidrena_settings, $sidrena_export ) {
		include BRENDIGO_SIDRENA_CIJENA_PATH . 'includes/admin/tab-pregled.php';
	}

	/**
	 * Postavke tab: display options, then snapshot fill.
	 *
	 * @param Brendigo_Sidrena_Cijena_Settings $sidrena_settings Settings helper.
	 */
	private function render_tab_postavke( $sidrena_settings ) {
		include BRENDIGO_SIDRENA_CIJENA_PATH . 'includes/admin/tab-postavke.php';
	}

	/**
	 * Step-by-step user guide for site owners (both operating modes).
	 */
	private function render_tab_upute() {
		include BRENDIGO_SIDRENA_CIJENA_PATH . 'includes/admin/tab-upute.php';
	}

	/**
	 * Poslovnice tab: legal filename fields and assigned products.
	 */
	private function render_tab_poslovnice() {
		include BRENDIGO_SIDRENA_CIJENA_PATH . 'includes/admin/tab-poslovnice.php';
	}


	/**
	 * Accessible on/off toggle.
	 *
	 * @param string $name    Input name.
	 * @param string $id      Input id.
	 * @param bool   $checked On/off.
	 * @param string $label   Visible label.
	 * @param string $class   Extra class on the checkbox.
	 */
	public function render_toggle( $name, $id, $checked, $label, $class = '' ) {
		?>
		<label class="sidrena-cijena-toggle" for="<?php echo esc_attr( $id ); ?>">
			<input
				type="checkbox"
				role="switch"
				name="<?php echo esc_attr( $name ); ?>"
				id="<?php echo esc_attr( $id ); ?>"
				value="yes"
				class="<?php echo esc_attr( trim( $class ) ); ?>"
				<?php checked( $checked ); ?>
			/>
			<span class="sidrena-cijena-toggle__track" aria-hidden="true"></span>
			<span class="sidrena-cijena-toggle__label"><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	/**
	 * Floppy-disk glyph used on primary save buttons.
	 *
	 * @return string
	 */
	public function save_icon_html() {
		return '<span class="sidrena-cijena-save-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M20.5 5.8 18.2 3.5c-.4-.4-.9-.6-1.4-.6H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V7.2c0-.5-.2-1-.5-1.4zM12 19c-1.7 0-3-1.3-3-3s1.3-3 3-3 3 1.3 3 3-1.3 3-3 3zm3-10H6V5h9v4z"/></svg></span>';
	}

	/**
	 * Primary submit button with the save icon.
	 *
	 * @param string $label Button label.
	 */
	public function render_save_button( $label ) {
		echo '<p class="submit"><button type="submit" class="button button-primary">';
		echo $this->save_icon_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		echo esc_html( $label );
		echo '</button></p>';
	}

	/**
	 * Copyable URL or shortcode row.
	 *
	 * @param string $label      Box label.
	 * @param string $value      Code / URL text.
	 * @param string $open_url   Optional open-link href.
	 * @param string $open_label Optional open-link label.
	 * @param string $open_class Open-link button class.
	 * @param string $open_icon  Dashicon class.
	 * @param string $code_id    Optional id on the code element.
	 */
	public function render_url_box( $label, $value, $open_url = '', $open_label = '', $open_class = 'button button-primary', $open_icon = 'dashicons-external', $code_id = '' ) {
		?>
		<div class="sidrena-cijena-urlbox">
			<span class="sidrena-cijena-urlbox__label"><?php echo esc_html( $label ); ?></span>
			<code<?php echo '' !== $code_id ? ' id="' . esc_attr( $code_id ) . '"' : ''; ?>><?php echo esc_html( $value ); ?></code>
			<div class="sidrena-cijena-urlbox__actions">
				<button type="button" class="button button-secondary sidrena-cijena-copy" data-copy="<?php echo esc_attr( $value ); ?>">
					<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
					<?php echo esc_html__( 'Kopiraj', 'brendigo-sidrena-cijena' ); ?>
				</button>
				<?php if ( '' !== $open_url && '' !== $open_label ) : ?>
					<a class="<?php echo esc_attr( $open_class ); ?>" href="<?php echo esc_url( $open_url ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="dashicons <?php echo esc_attr( $open_icon ); ?>" aria-hidden="true"></span>
						<?php echo esc_html( $open_label ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Catalog repeater. Without Woo this is the whole pricelist. With Woo it is extra rows appended to the shop feed.
	 */
	private function render_tab_proizvodi() {
		include BRENDIGO_SIDRENA_CIJENA_PATH . 'includes/admin/tab-proizvodi.php';
	}

	/**
	 * Uvoz / izvoz tab.
	 */
	private function render_tab_uvoz_izvoz() {
		include BRENDIGO_SIDRENA_CIJENA_PATH . 'includes/admin/tab-uvoz-izvoz.php';
	}

	/**
	 * Javni cjenik tab: shortcode that prints the archive file list.
	 */
	private function render_archive_shortcode_docs() {
		$settings = Brendigo_Sidrena_Cijena_Settings::instance();
		$code     = '[sidrena-arhiva]';
		$this->render_url_box( __( 'Shortcode arhive', 'brendigo-sidrena-cijena' ), $code );
		if ( ! $settings->is_multi_poslovnica() ) {
			return;
		}

		$web = Brendigo_Sidrena_Cijena_Feed_Routes::instance()->webshop_oznaka();
		$this->render_url_box( $web, '[sidrena-arhiva oznaka="' . $web . '"]' );
		foreach ( Brendigo_Sidrena_Cijena_Poslovnice::instance()->all() as $outlet ) {
			$outlet_code = '[sidrena-arhiva oznaka="' . $outlet['oznaka'] . '"]';
			$outlet_name = '' !== $outlet['naziv'] ? $outlet['naziv'] : $outlet['oznaka'];
			$this->render_url_box( $outlet_name, $outlet_code );
		}
	}

	/**
	 * Javni cjenik tab: shortcode that prints the table.
	 *
	 * @param Brendigo_Sidrena_Cijena_Settings $settings Settings helper.
	 */
	private function render_cjenik_shortcode_docs( $settings ) {
		$code = '[sidrena-cjenik]';
		$this->render_url_box( __( 'Shortcode tablice', 'brendigo-sidrena-cijena' ), $code );
		if ( $settings->is_multi_poslovnica() ) {
			foreach ( Brendigo_Sidrena_Cijena_Poslovnice::instance()->all() as $outlet ) {
				$outlet_code = '[sidrena-cjenik oznaka="' . $outlet['oznaka'] . '"]';
				$outlet_name = '' !== $outlet['naziv'] ? $outlet['naziv'] : $outlet['oznaka'];
				$this->render_url_box( $outlet_name, $outlet_code );
			}
		}
	}

	/**
	 * Javni XML tab.
	 *
	 * @param Brendigo_Sidrena_Cijena_Export $sidrena_export Export helper.
	 */
	private function render_tab_javni_xml( $sidrena_export ) {
		include BRENDIGO_SIDRENA_CIJENA_PATH . 'includes/admin/tab-javni-xml.php';
	}





	/**
	 * Hide the post-activation fill prompt.
	 */
	public function maybe_dismiss_fill_prompt() {
		if ( ! isset( $_GET['sidrena_cijena_dismiss_fill'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		check_admin_referer( 'sidrena_cijena_dismiss_fill' );

		if ( ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() ) {
			return;
		}

		delete_option( Brendigo_Sidrena_Cijena_Activator::FILL_PROMPT_OPTION );
		wp_safe_redirect( remove_query_arg( array( 'sidrena_cijena_dismiss_fill', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * After activation, point at the two fill buttons. Shown on every admin screen until dismissed or a fill finishes.
	 */
	private function render_fill_prompt_notice() {
		if ( 'yes' !== get_option( Brendigo_Sidrena_Cijena_Activator::FILL_PROMPT_OPTION ) ) {
			return;
		}

		$settings_url = $this->get_settings_tab_url( 'postavke' );
		$dismiss_url  = wp_nonce_url(
			add_query_arg( 'sidrena_cijena_dismiss_fill', '1' ),
			'sidrena_cijena_dismiss_fill'
		);

		echo '<div class="notice notice-info"><p>';
		echo esc_html__( 'Sidrena cijena ne upisuje cijene pri aktivaciji. Prazna polja možete popuniti na kartici Postavke: iz redovne cijene (datum ostaje zadani) ili s današnjim datumom.', 'brendigo-sidrena-cijena' );
		echo ' <a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Otvori postavke', 'brendigo-sidrena-cijena' ) . '</a>';
		echo ' · <a href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Sakrij', 'brendigo-sidrena-cijena' ) . '</a>';
		echo '</p></div>';
	}

	/**
	 * Render the snapshot/fill notice inside the branded settings shell.
	 *
	 * Keeping this notice inside the plugin layout prevents the default WordPress
	 * light notice from breaking the dark header and keeps the message in the
	 * same visual hierarchy as the rest of the settings screen.
	 */
	private function render_settings_snapshot_notice() {
		if ( ! Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() || ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() ) {
			return;
		}

		if ( ! Brendigo_Sidrena_Cijena_Activator::is_pending() ) {
			$this->render_fill_prompt_notice();
			return;
		}

		echo '<div class="notice notice-info"><p>';
		echo esc_html__( 'Sidrena cijena: u tijeku je spremanje redovnih cijena proizvoda i varijacija. Već unesene sidrene cijene neće se prepisati.', 'brendigo-sidrena-cijena' );
		echo '</p></div>';
	}

	/**
	 * Notice while a snapshot is running, or after activation until prices are filled or the prompt is dismissed.
	 */
	public function snapshot_notice() {
		if ( ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() || $this->is_settings_screen() ) {
			return;
		}

		if ( ! Brendigo_Sidrena_Cijena_Activator::is_pending() ) {
			$this->render_fill_prompt_notice();
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}

		$allowed = (
			'woocommerce_page_brendigo-sidrena-cijena' === $screen->id
			|| 'toplevel_page_brendigo-sidrena-cijena' === $screen->id
			|| 'product' === $screen->id
			|| 'edit-product' === $screen->id
			|| ( isset( $screen->post_type ) && 'product' === $screen->post_type )
			|| ( is_string( $screen->id ) && false !== strpos( $screen->id, 'woocommerce' ) )
		);

		if ( ! $allowed ) {
			return;
		}

		echo '<div class="notice notice-info"><p>';
		echo esc_html__( 'Sidrena cijena: u tijeku je spremanje redovnih cijena proizvoda i varijacija. Već unesene sidrene cijene neće se prepisati.', 'brendigo-sidrena-cijena' );
		echo '</p></div>';
	}

	/**
	 * Admin assets on product and settings screens.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_product  = $screen && 'product' === $screen->id;
		$is_settings = in_array( $hook, array( 'woocommerce_page_brendigo-sidrena-cijena', 'toplevel_page_brendigo-sidrena-cijena' ), true );

		if ( ! $is_product && ! $is_settings ) {
			return;
		}

		wp_enqueue_style(
			'sidrena-cijena-admin',
			BRENDIGO_SIDRENA_CIJENA_URL . 'assets/css/admin.css',
			array(),
			BRENDIGO_SIDRENA_CIJENA_VERSION
		);

		$script_deps = array( 'jquery' );
		if ( $is_settings && Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ) {
			if ( wp_style_is( 'woocommerce_admin_styles', 'registered' ) ) {
				wp_enqueue_style( 'woocommerce_admin_styles' );
			}
			if ( wp_script_is( 'selectWoo', 'registered' ) ) {
				wp_enqueue_script( 'selectWoo' );
				$script_deps[] = 'selectWoo';
			}
		}

		wp_enqueue_script(
			'sidrena-cijena-admin',
			BRENDIGO_SIDRENA_CIJENA_URL . 'assets/js/admin.js',
			$script_deps,
			BRENDIGO_SIDRENA_CIJENA_VERSION,
			true
		);

		if ( $is_settings ) {
			$current_tab     = $this->get_settings_tab();
			$catalog_choices = array();
			$product_labels  = array();
			$outlet_choices  = array();

			if ( 'proizvodi' === $current_tab ) {
				$outlet_choices = $this->catalog_outlet_choices();
			}

			if ( 'poslovnice' === $current_tab ) {
				$product_labels = Brendigo_Sidrena_Cijena_Poslovnice::instance()->product_labels();
				if ( ! Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ) {
					foreach ( Brendigo_Sidrena_Cijena_Catalog::instance()->all() as $row ) {
						$label = $row['naziv'];
						if ( '' !== $row['sku'] ) {
							$label .= ' (' . $row['sku'] . ')';
						}
						$catalog_choices[] = array(
							'id'    => $row['id'],
							'label' => $label,
						);
					}
				}
			}

			/* translators: %d: number of manual catalog rows. */
			$sidrena_row_count_manual = __( '%d stavki', 'brendigo-sidrena-cijena' );
			/* translators: %d: number of products. */
			$sidrena_row_count_products = __( '%d proizvoda i usluga', 'brendigo-sidrena-cijena' );

			wp_localize_script(
				'sidrena-cijena-admin',
				'sidrenaCijenaAdmin',
				array(
					'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
					'nonce'              => wp_create_nonce( 'sidrena_cijena_snapshot' ),
					'purgeNonce'         => wp_create_nonce( 'sidrena_cijena_purge' ),
					'importNonce'        => wp_create_nonce( 'sidrena_cijena_import' ),
					'productSearchNonce' => wp_create_nonce( 'search-products' ),
					'standalone'         => ! Brendigo_Sidrena_Cijena_Plugin::woocommerce_active(),
					'catalogIdPrefix'    => Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ? 'd' : '',
					'cjmEnabled'         => Brendigo_Sidrena_Cijena_Settings::instance()->is_cjm_enabled(),
					'outlets'            => $outlet_choices,
					'catalogChoices'     => $catalog_choices,
					'productLabels'      => $product_labels,
					'i18n'               => array(
						'running'      => __( 'Obrada…', 'brendigo-sidrena-cijena' ),
						/* translators: 1: processed rows, 2: total rows. */
						'progress'     => __( 'Obrada… %1$s / %2$s', 'brendigo-sidrena-cijena' ),
						'retry'        => __( 'Veza je prekinuta. Nastavljam automatski…', 'brendigo-sidrena-cijena' ),
						'stalled'      => __( 'Obrada je stala. Pritisnite gumb ponovno za nastavak od istog mjesta. Već upisane cijene se ne prepisuju.', 'brendigo-sidrena-cijena' ),
						'done'         => __( 'Gotovo. Prazna polja su popunjena.', 'brendigo-sidrena-cijena' ),
						'doneToday'    => __( 'Gotovo. Prazna polja su popunjena današnjim datumom.', 'brendigo-sidrena-cijena' ),
						'todayConfirm' => sprintf(
							/* translators: %s: locked general reference date. */
							__( 'Ovo popunjava prazne sidrene cijene trenutnom redovnom cijenom i upisuje današnji datum kao referentni (samo ako datum još nije unesen). Proizvodi koji su postojali na %s trebaju ostati na zadanom datumu. Nastaviti?', 'brendigo-sidrena-cijena' ),
							Brendigo_Sidrena_Cijena_Settings::REFERENCE_DATE_GENERAL
						),
						'error'        => __( 'Došlo je do pogreške. Pokušajte ponovno.', 'brendigo-sidrena-cijena' ),
						'purgeConfirm' => __( 'Ovo će trajno obrisati sve sidrene cijene, ručni cjenik, evidenciju povijesti cijena, postavke, javni XML i arhivu, zatim deaktivirati dodatak. Radnja se ne može poništiti. Nastaviti?', 'brendigo-sidrena-cijena' ),
						'purging'      => __( 'Brisanje podataka…', 'brendigo-sidrena-cijena' ),
						'copied'       => __( 'Kopirano', 'brendigo-sidrena-cijena' ),
						'copyFailed'   => __( 'Kopiranje nije uspjelo', 'brendigo-sidrena-cijena' ),
						'catalogRequired' => __( 'Naziv, redovna cijena i sidrena cijena obavezni su za svaki popunjeni redak. Ispravite označena polja prije spremanja.', 'brendigo-sidrena-cijena' ),
						'noFile'       => __( 'Odaberite CSV, XML ili XLSX datoteku.', 'brendigo-sidrena-cijena' ),
						'invalidFile'  => __( 'Dopušteni su CSV, XML i XLSX.', 'brendigo-sidrena-cijena' ),
						'fileTooLarge' => __( 'Datoteka je veća od 20 MB.', 'brendigo-sidrena-cijena' ),
						'importStalled' => __( 'Uvoz nije napredovao. Ponovno učitajte datoteku i pokušajte ponovno.', 'brendigo-sidrena-cijena' ),
						'preflightRunning' => __( 'Provjera mapiranja i cijena prije uvoza…', 'brendigo-sidrena-cijena' ),
						/* translators: {checked}, {total}, {sample}, {matched_woo}, {manual_or_new}, {invalid_prices}, {duplicate_keys}: read-only import preflight counts and optional sample note. Keep the brace placeholders unchanged. */
						'preflightReport' => __( 'Provjera bez promjena podataka: {checked} od {total} redaka{sample}. Postojeći Woo proizvodi: {matched_woo}, novi/ručni redci: {manual_or_new}, nevaljani iznosi: {invalid_prices}, ponovljeni SKU/ID: {duplicate_keys}.', 'brendigo-sidrena-cijena' ),
						'preflightSample' => __( ' (UZORAK, ne cijela datoteka)', 'brendigo-sidrena-cijena' ),
						'preflightInvalid' => __( 'Ispravite ulazne podatke prije uvoza.', 'brendigo-sidrena-cijena' ),
						'applyImportConfirm' => __( 'Primijeniti uvoz? Prije masovnih promjena preporučuje se sigurnosna kopija.', 'brendigo-sidrena-cijena' ),

						'analyzing'    => __( 'Čitanje datoteke…', 'brendigo-sidrena-cijena' ),
						'skuRequired'     => __( 'Odaberite stupac/čvor koji sadrži SKU.', 'brendigo-sidrena-cijena' ),
						'skuOrIdRequired' => __( 'Odaberite stupac za SKU ili ID.', 'brendigo-sidrena-cijena' ),
						/* translators: 1: current page number, 2: total pages. */
						'pageStatus'      => __( 'Stranica %1$d od %2$d', 'brendigo-sidrena-cijena' ),
						'noImport'     => __( 'Ne uvozi', 'brendigo-sidrena-cijena' ),
						'chooseSku'    => __( 'Odaberite SKU polje', 'brendigo-sidrena-cijena' ),
						'importing'    => __( 'Uvoz u tijeku…', 'brendigo-sidrena-cijena' ),
						'importDone'   => __( 'Uvoz je gotov.', 'brendigo-sidrena-cijena' ),
						'rowsLabel'    => __( 'redova', 'brendigo-sidrena-cijena' ),
						'updated'      => __( 'ažurirano', 'brendigo-sidrena-cijena' ),
						'skipped'      => __( 'preskočeno', 'brendigo-sidrena-cijena' ),
						'missing'      => __( 'SKU nije pronađen', 'brendigo-sidrena-cijena' ),
						'created'      => __( 'dodano', 'brendigo-sidrena-cijena' ),
						'duplicate'     => __( 'Dupliciraj', 'brendigo-sidrena-cijena' ),
						'remove'        => __( 'Ukloni', 'brendigo-sidrena-cijena' ),
						'addRow'        => __( 'Dodaj redak', 'brendigo-sidrena-cijena' ),
						'copyShortcode' => __( 'Kopiraj shortcode', 'brendigo-sidrena-cijena' ),
						'saveForCode'   => __( 'Spremi cjenik za shortcode', 'brendigo-sidrena-cijena' ),
						'dostupno'      => __( 'Dostupno', 'brendigo-sidrena-cijena' ),
						'nedostupno'    => __( 'Nedostupno', 'brendigo-sidrena-cijena' ),
						'cjm'           => __( 'CJM', 'brendigo-sidrena-cijena' ),
						'cjmPlaceholder' => __( 'za kg/l', 'brendigo-sidrena-cijena' ),
						'oblikPlaceholder' => __( 'akcija', 'brendigo-sidrena-cijena' ),
						'jedinicaPlaceholder' => 'kg',
						'datePlaceholder'  => (string) Brendigo_Sidrena_Cijena_Settings::instance()->get( 'default_date' ),
						'vrstaProizvod'    => __( 'Proizvod', 'brendigo-sidrena-cijena' ),
						'vrstaUsluga'      => __( 'Usluga', 'brendigo-sidrena-cijena' ),
						'webshop'          => Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ? __( 'Webshop', 'brendigo-sidrena-cijena' ) : __( 'Glavni cjenik', 'brendigo-sidrena-cijena' ),
						/* translators: %d: number of catalog rows. */
						'rowCount'       => Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ? $sidrena_row_count_manual : $sidrena_row_count_products,
						'outletName'     => __( 'Naziv', 'brendigo-sidrena-cijena' ),
						'outletNameHelp' => __( 'Samo za vas u administraciji. Ne ulazi u naziv datoteke.', 'brendigo-sidrena-cijena' ),
						'outletOblik'    => __( 'Oblik prodajnog objekta', 'brendigo-sidrena-cijena' ),
						'outletOblikHelp' => __( 'Kako je napisano, npr. prodavaonica, webshop ili servis.', 'brendigo-sidrena-cijena' ),
						'outletAddress'  => __( 'Adresa prodajnog objekta', 'brendigo-sidrena-cijena' ),
						'outletAddressHelp' => __( 'Ulica i mjesto ostaju u nazivu, npr. Ilica 150 Zagreb.', 'brendigo-sidrena-cijena' ),
						'outletCode'     => __( 'Oznaka prodajnog objekta', 'brendigo-sidrena-cijena' ),
						'outletCodeHelp' => __( 'Interna oznaka, npr. P-01 ili 001. Crtica ostaje. Ulazi u adresu cjenika: /sidrena-cijena-P-01.xml.', 'brendigo-sidrena-cijena' ),
						'outletSeq'      => __( 'Sljedeći broj pohrane', 'brendigo-sidrena-cijena' ),
						'outletSeqHelp'  => __( 'Raste jednom po danu. Ponovno generiranje istog dana ne povećava broj. U naziv ide kako je upisan, npr. 104.', 'brendigo-sidrena-cijena' ),
						'outletProducts' => __( 'Proizvodi', 'brendigo-sidrena-cijena' ),
						'outletProductsHelp' => __( 'U cjenik ove poslovnice ulaze samo odabrani proizvodi.', 'brendigo-sidrena-cijena' ),
						'searchProducts' => __( 'Traži proizvod…', 'brendigo-sidrena-cijena' ),
						'noCatalog'      => __( 'Prvo dodajte proizvode u kartici Proizvodi.', 'brendigo-sidrena-cijena' ),
						'chooseOutlet'   => __( 'Odaberite poslovnicu u koju se uvozi.', 'brendigo-sidrena-cijena' ),
						'removeOutlet'   => __( 'Ukloni poslovnicu', 'brendigo-sidrena-cijena' ),
						'removeOutletConfirm' => __( 'Ukloniti ovu poslovnicu? Ručne stavke vezane uz njezinu oznaku mogu biti uklonjene pri spremanju.', 'brendigo-sidrena-cijena' ),
						'unsavedChanges' => __( 'Imate nespremljene promjene.', 'brendigo-sidrena-cijena' ),
						'unsavedLabel'   => __( 'Nespremljene promjene', 'brendigo-sidrena-cijena' ),
						'saving'         => __( 'Spremanje…', 'brendigo-sidrena-cijena' ),
						'fillCurrentConfirm' => __( 'Popuniti prazna polja sidrene cijene važećom cijenom prikazanih proizvoda? Koristite samo ako je ta cijena vrijedila na referentni datum koji unosite.', 'brendigo-sidrena-cijena' ),
						'wooNoChanges'      => __( 'Nema nespremljenih promjena.', 'brendigo-sidrena-cijena' ),
						'wooLeaveWarning'   => __( 'Imate nespremljene promjene sidrenih cijena. Ako napustite stranicu, promjene će se izgubiti.', 'brendigo-sidrena-cijena' ),
						'wooResetConfirm'   => __( 'Poništiti sve nespremljene promjene na ovoj stranici?', 'brendigo-sidrena-cijena' ),
						'wooVisibleRows'     => __( 'Prikazano {visible} od {total} stavki na ovoj stranici.', 'brendigo-sidrena-cijena' ),
						/* translators: %d: number of changed WooCommerce rows. */
						'wooChangedRows'    => __( '%d proizvoda ima nespremljene promjene.', 'brendigo-sidrena-cijena' ),
					),
				)
			);

			if ( 'proizvodi' === $current_tab ) {
				wp_enqueue_script(
					'sidrena-cijena-admin-catalog',
					BRENDIGO_SIDRENA_CIJENA_URL . 'assets/js/admin-catalog.js',
					array( 'sidrena-cijena-admin' ),
					BRENDIGO_SIDRENA_CIJENA_VERSION,
					true
				);
			}

			if ( 'uvoz-izvoz' === $current_tab ) {
				wp_enqueue_script(
					'sidrena-cijena-admin-import',
					BRENDIGO_SIDRENA_CIJENA_URL . 'assets/js/admin-import.js',
					array( 'sidrena-cijena-admin' ),
					BRENDIGO_SIDRENA_CIJENA_VERSION,
					true
				);
			}

			if ( 'poslovnice' === $current_tab ) {
				wp_enqueue_script(
					'sidrena-cijena-admin-outlets',
					BRENDIGO_SIDRENA_CIJENA_URL . 'assets/js/admin-outlets.js',
					array( 'sidrena-cijena-admin' ),
					BRENDIGO_SIDRENA_CIJENA_VERSION,
					true
				);
			}
		}
	}








	/**
	 * Product list column.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function add_product_column( $columns ) {
		$columns['sidrena_cijena'] = __( 'Sidrena cijena', 'brendigo-sidrena-cijena' );
		return $columns;
	}

	/**
	 * Render product list column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Product ID.
	 */
	public function render_product_column( $column, $post_id ) {
		if ( 'sidrena_cijena' !== $column ) {
			return;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			echo '-';
			return;
		}

		$meta = Brendigo_Sidrena_Cijena_Product_Meta::instance();

		if ( $product->is_type( 'variable' ) ) {
			$prices = array();
			foreach ( $product->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( ! $variation ) {
					continue;
				}

				$display = $meta->get_price_to_display( $variation );
				if ( null !== $display ) {
					$prices[] = $display;
				}
			}

			if ( empty( $prices ) ) {
				echo '-';
				return;
			}

			$min = min( $prices );
			$max = max( $prices );
			if ( $min === $max ) {
				echo wp_kses_post( wc_price( $min ) );
				return;
			}

			echo wp_kses_post( wc_price( $min ) . ' - ' . wc_price( $max ) );
			return;
		}

		$html = $meta->format_price_html( $product );
		echo '' === $html ? '-' : wp_kses_post( $html );
	}
}
