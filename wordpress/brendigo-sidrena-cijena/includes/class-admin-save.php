<?php
/**
 * Admin form saves and AJAX writes for Sidrena cijena.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings, catalog, outlet, snapshot, and purge writes.
 */
class Brendigo_Sidrena_Cijena_Admin_Save {

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
	 * Save the Poslovnice repeater.
	 */
	public function handle_poslovnice_save() {
		$action = isset( $_POST['sidrena_cijena_action'] ) ? sanitize_key( wp_unslash( $_POST['sidrena_cijena_action'] ) ) : '';
		if ( 'save_poslovnice' !== $action ) {
			return;
		}

		if ( ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() ) {
			return;
		}

		check_admin_referer( 'sidrena_cijena_save_poslovnice', 'sidrena_cijena_poslovnice_nonce' );

		$poslovnice  = Brendigo_Sidrena_Cijena_Poslovnice::instance();
		$before_rows = $poslovnice->all();
		$before_file = array(
			(string) Brendigo_Sidrena_Cijena_Settings::instance()->get( 'file_oblik', '' ),
			(string) Brendigo_Sidrena_Cijena_Settings::instance()->get( 'file_adresa', '' ),
			(string) Brendigo_Sidrena_Cijena_Settings::instance()->get( 'file_oznaka', '' ),
			(int) Brendigo_Sidrena_Cijena_Settings::instance()->get( 'file_pohrana', 1 ),
		);

		$before = array();
		foreach ( $before_rows as $outlet ) {
			if ( '' !== $outlet['id'] ) {
				$before[ $outlet['id'] ] = $outlet['oznaka'];
			}
		}

		// Never assume a missing, truncated or invalid form value means delete all
		// outlets: that would also cascade into deleting their catalog entries.
		if ( ! isset( $_POST['sidrena_cijena_poslovnice_json'] ) || ! is_string( $_POST['sidrena_cijena_poslovnice_json'] ) ) {
			wp_die( esc_html__( 'Podaci poslovnica nisu poslani. Ništa nije promijenjeno.', 'brendigo-sidrena-cijena' ) );
		}
		$json    = wp_unslash( $_POST['sidrena_cijena_poslovnice_json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated immediately below.
		$decoded = strlen( $json ) <= 5 * MB_IN_BYTES ? json_decode( $json, true ) : null;
		if ( ! preg_match( '/^\s*\[/', $json ) || ! is_array( $decoded ) || count( $decoded ) > 2000 ) {
			wp_die( esc_html__( 'Neispravni ili preveliki podaci poslovnica. Ništa nije promijenjeno.', 'brendigo-sidrena-cijena' ) );
		}
		foreach ( $decoded as $row ) {
			if ( ! is_array( $row ) ) {
				wp_die( esc_html__( 'Neispravan zapis poslovnice. Ništa nije promijenjeno.', 'brendigo-sidrena-cijena' ) );
			}
		}
		$saved = $poslovnice->save( $decoded );
		if ( ! $poslovnice->last_save_succeeded() ) {
			wp_die( esc_html__( 'Poslovnice nisu spremljene. Ništa dodatno nije obrisano.', 'brendigo-sidrena-cijena' ) );
		}

		$kept = array();
		foreach ( $saved as $outlet ) {
			if ( '' !== $outlet['id'] ) {
				$kept[ $outlet['id'] ] = true;
			}
		}
		foreach ( $before as $id => $oznaka ) {
			if ( ! isset( $kept[ $id ] ) && '' !== $oznaka ) {
				Brendigo_Sidrena_Cijena_Catalog::instance()->delete_for_poslovnica( $oznaka );
				Brendigo_Sidrena_Cijena_Upit::instance()->delete_for_poslovnica( $oznaka );
			}
		}

		if ( isset( $_POST['sidrena_cijena_file_oblik'] ) ) {
			Brendigo_Sidrena_Cijena_Settings::instance()->save(
				array(
					'file_oblik'   => sanitize_text_field( wp_unslash( $_POST['sidrena_cijena_file_oblik'] ) ),
					'file_adresa'  => isset( $_POST['sidrena_cijena_file_adresa'] ) ? sanitize_text_field( wp_unslash( $_POST['sidrena_cijena_file_adresa'] ) ) : '',
					'file_oznaka'  => isset( $_POST['sidrena_cijena_file_oznaka'] ) ? sanitize_text_field( wp_unslash( $_POST['sidrena_cijena_file_oznaka'] ) ) : '001',
					'file_pohrana' => isset( $_POST['sidrena_cijena_file_pohrana'] ) ? absint( wp_unslash( $_POST['sidrena_cijena_file_pohrana'] ) ) : 1,
				)
			);
		}

		$after_file = array(
			(string) Brendigo_Sidrena_Cijena_Settings::instance()->get( 'file_oblik', '' ),
			(string) Brendigo_Sidrena_Cijena_Settings::instance()->get( 'file_adresa', '' ),
			(string) Brendigo_Sidrena_Cijena_Settings::instance()->get( 'file_oznaka', '' ),
			(int) Brendigo_Sidrena_Cijena_Settings::instance()->get( 'file_pohrana', 1 ),
		);
		if ( class_exists( 'Brendigo_Sidrena_Cijena_Automation' ) && ( $before_rows !== $saved || $before_file !== $after_file ) ) {
			Brendigo_Sidrena_Cijena_Automation::instance()->queue_rebuild( 'outlet-settings' );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'brendigo-sidrena-cijena', 'tab' => 'poslovnice', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Save settings form.
	 */
	public function handle_settings_save() {
		$action = isset( $_POST['sidrena_cijena_action'] ) ? sanitize_key( wp_unslash( $_POST['sidrena_cijena_action'] ) ) : '';
		if ( 'save_settings' !== $action ) {
			return;
		}

		if ( ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() ) {
			return;
		}

		check_admin_referer( 'sidrena_cijena_save_settings', 'sidrena_cijena_nonce' );

		$settings     = Brendigo_Sidrena_Cijena_Settings::instance();
		$before_brand = array(
			(string) $settings->get( 'brand_taxonomy', '' ),
			(string) $settings->get( 'brand_manual', '' ),
		);

		$payload = array(
			'prefix'         => isset( $_POST['sidrena_cijena_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['sidrena_cijena_prefix'] ) ) : '',
			'tooltip'        => isset( $_POST['sidrena_cijena_tooltip'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sidrena_cijena_tooltip'] ) ) : '',
			'enable_tooltip' => isset( $_POST['sidrena_cijena_enable_tooltip'] ) ? 'yes' : 'no',
			'date_style'     => isset( $_POST['sidrena_cijena_date_style'] ) ? sanitize_text_field( wp_unslash( $_POST['sidrena_cijena_date_style'] ) ) : 'parens',
			'enable_cjm'     => isset( $_POST['sidrena_cijena_enable_cjm'] ) ? 'yes' : 'no',
			'brand_manual'   => isset( $_POST['sidrena_cijena_brand_manual'] ) ? sanitize_text_field( wp_unslash( $_POST['sidrena_cijena_brand_manual'] ) ) : '',
		);

		if ( Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ) {
			$payload['brand_taxonomy'] = isset( $_POST['sidrena_cijena_brand_taxonomy'] ) ? sanitize_text_field( wp_unslash( $_POST['sidrena_cijena_brand_taxonomy'] ) ) : '';
			$payload['auto_display'] = isset( $_POST['sidrena_cijena_auto_display'] ) ? 'yes' : 'no';
			$payload['auto_fill']    = isset( $_POST['sidrena_cijena_auto_fill'] ) ? 'yes' : 'no';
		}

		$settings->save( $payload );

		$after_brand = array(
			(string) $settings->get( 'brand_taxonomy', '' ),
			(string) $settings->get( 'brand_manual', '' ),
		);
		if ( $before_brand !== $after_brand ) {
			Brendigo_Sidrena_Cijena_Automation::instance()->queue_rebuild( 'brand-settings' );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'brendigo-sidrena-cijena', 'tab' => 'postavke', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Save public XML options from the Javni XML tab.
	 */
	public function handle_xml_settings_save() {
		if ( ! isset( $_POST['sidrena_cijena_action'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['sidrena_cijena_action'] ) );
		if ( ! in_array( $action, array( 'save_xml_settings', 'regenerate_public_xml' ), true ) ) {
			return;
		}

		if ( ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() ) {
			return;
		}

		check_admin_referer( 'sidrena_cijena_save_xml', 'sidrena_cijena_xml_nonce' );

		$settings            = Brendigo_Sidrena_Cijena_Settings::instance();
		$before_format       = $settings->export_format();
		$before_auto_rebuild = 'yes' === $settings->get( 'auto_rebuild', 'yes' );

		$payload = array(
			'daily_xml'        => isset( $_POST['sidrena_cijena_daily_xml'] ) ? 'yes' : 'no',
			'daily_time'       => isset( $_POST['sidrena_cijena_daily_time'] ) ? sanitize_text_field( wp_unslash( $_POST['sidrena_cijena_daily_time'] ) ) : '07:00',
			'auto_rebuild'     => isset( $_POST['sidrena_cijena_auto_rebuild'] ) ? 'yes' : 'no',
			'auto_maintenance' => isset( $_POST['sidrena_cijena_auto_maintenance'] ) ? 'yes' : 'no',
			'email_alerts'    => isset( $_POST['sidrena_cijena_email_alerts'] ) ? 'yes' : 'no',
			'alert_email'     => isset( $_POST['sidrena_cijena_alert_email'] ) ? sanitize_email( wp_unslash( $_POST['sidrena_cijena_alert_email'] ) ) : '',
			'export_format'    => isset( $_POST['sidrena_cijena_public_format'] ) ? sanitize_key( wp_unslash( $_POST['sidrena_cijena_public_format'] ) ) : 'xml',
		);

		$settings->save( $payload );

		Brendigo_Sidrena_Cijena_Export::instance()->sync_daily_schedule();
		Brendigo_Sidrena_Cijena_Automation::instance()->sync_health_schedule();

		if ( 'regenerate_public_xml' === $action ) {
			$result = Brendigo_Sidrena_Cijena_Export::instance()->publish_public_catalog( 'manual' );
			$flag = '1';
			if ( is_wp_error( $result ) ) {
				$flag = 'sidrena_poslovnice_empty' === $result->get_error_code() ? '2' : '0';
			}
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => 'brendigo-sidrena-cijena',
						'tab'         => 'javni-xml',
						'sidrena_xml' => $flag,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$after_format       = $settings->export_format();
		$after_auto_rebuild = 'yes' === $settings->get( 'auto_rebuild', 'yes' );
		$automation         = Brendigo_Sidrena_Cijena_Automation::instance();

		if ( $before_format !== $after_format ) {
			// The public URL changes with the format, so create the newly selected
			// output immediately instead of waiting for the next daily slot.
			$automation->mark_pending( 'feed-format' );
			Brendigo_Sidrena_Cijena_Export::instance()->queue_public_xml( 5, 'feed-format' );
		} elseif ( ! $before_auto_rebuild && $after_auto_rebuild && ! empty( $automation->get_pending_state() ) ) {
			// Turning instant refresh back on should flush changes that accumulated
			// while scheduled-only mode was active.
			Brendigo_Sidrena_Cijena_Export::instance()->queue_public_xml( 5, 'auto-rebuild-enabled' );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'brendigo-sidrena-cijena', 'tab' => 'javni-xml', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Save standalone catalog repeater.
	 */
	public function handle_catalog_save() {
		$action = isset( $_POST['sidrena_cijena_action'] ) ? sanitize_key( wp_unslash( $_POST['sidrena_cijena_action'] ) ) : '';
		if ( 'save_catalog' !== $action ) {
			return;
		}

		if ( ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() ) {
			return;
		}

		check_admin_referer( 'sidrena_cijena_save_catalog', 'sidrena_cijena_catalog_nonce' );

		// These two fields are required. Missing/truncated form posts must not wipe
		// the manual catalog or the quote-on-request collection.
		if ( ! isset( $_POST['sidrena_cijena_catalog_json'], $_POST['sidrena_cijena_upit_json'] )
			|| ! is_string( $_POST['sidrena_cijena_catalog_json'] )
			|| ! is_string( $_POST['sidrena_cijena_upit_json'] ) ) {
			wp_die( esc_html__( 'Podaci nisu potpuno poslani. Ništa nije promijenjeno; ponovno učitajte stranicu.', 'brendigo-sidrena-cijena' ) );
		}
		$json = wp_unslash( $_POST['sidrena_cijena_catalog_json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded and validated by Catalog.
		$upit = wp_unslash( $_POST['sidrena_cijena_upit_json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded and validated by Upit.
		$catalog_rows = json_decode( $json, true );
		$upit_rows = json_decode( $upit, true );
		if ( ! preg_match( '/^\s*\[/', $json ) || ! preg_match( '/^\s*\[/', $upit )
			|| ! is_array( $catalog_rows ) || ! is_array( $upit_rows ) ) {
			wp_die( esc_html__( 'Neispravan ili skraćen JSON. Ništa nije promijenjeno; ponovno učitajte stranicu.', 'brendigo-sidrena-cijena' ) );
		}
		foreach ( array_merge( $catalog_rows, $upit_rows ) as $row ) {
			if ( ! is_array( $row ) ) {
				wp_die( esc_html__( 'Neispravan format stavke. Ništa nije promijenjeno.', 'brendigo-sidrena-cijena' ) );
			}
		}
		Brendigo_Sidrena_Cijena_Catalog::instance()->save_from_json( $json );
		Brendigo_Sidrena_Cijena_Upit::instance()->save_from_json( $upit );

		wp_safe_redirect( add_query_arg( array( 'page' => 'brendigo-sidrena-cijena', 'tab' => 'proizvodi', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Save sidrena fields from the central WooCommerce catalog editor.
	 */
	public function handle_woo_bulk_save() {
		$action = isset( $_POST['sidrena_cijena_action'] ) ? sanitize_key( wp_unslash( $_POST['sidrena_cijena_action'] ) ) : '';
		if ( 'save_woo_catalog' !== $action || ! Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ) {
			return;
		}

		if ( ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() ) {
			return;
		}

		check_admin_referer( 'sidrena_cijena_save_woo_catalog', 'sidrena_cijena_woo_catalog_nonce' );

		$raw_rows = array();
		$json_used = false;
		if ( isset( $_POST['sidrena_wc_json'] ) && is_string( $_POST['sidrena_wc_json'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded, bounded, then every scalar is normalized below.
			$raw_json = wp_unslash( $_POST['sidrena_wc_json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- See validation directly below.
			if ( '' !== trim( $raw_json ) && strlen( $raw_json ) <= 2 * MB_IN_BYTES ) {
				$decoded = json_decode( $raw_json, true );
				if ( is_array( $decoded ) ) {
					$raw_rows = $decoded;
					$json_used = true;
				}
			}
		}

		if ( ! $json_used && isset( $_POST['sidrena_wc'] ) && is_array( $_POST['sidrena_wc'] ) ) {
			// No-JavaScript fallback. Every nested scalar is normalized by its domain setter.
			$raw_rows = wp_unslash( $_POST['sidrena_wc'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		$meta        = Brendigo_Sidrena_Cijena_Product_Meta::instance();
		$automation  = Brendigo_Sidrena_Cijena_Automation::instance();
		$was_paused  = $automation->suspend_rebuilds( true );
		$saved       = 0;

		try {
			foreach ( $raw_rows as $raw_id => $row ) {
				$product_id = absint( $raw_id );
				if ( $product_id < 1 || ! is_array( $row ) || ! current_user_can( 'edit_post', $product_id ) ) {
					continue;
				}

				$product = wc_get_product( $product_id );
				if ( ! $product instanceof WC_Product ) {
					continue;
				}

				$price      = isset( $row['price'] ) ? sanitize_text_field( $row['price'] ) : '';
				$date       = isset( $row['date'] ) ? sanitize_text_field( $row['date'] ) : '';
				$barcode    = isset( $row['barcode'] ) ? sanitize_text_field( $row['barcode'] ) : '';
				$visibility = isset( $row['visibility'] ) ? sanitize_key( $row['visibility'] ) : 'auto';

				$changes = array(
					$meta->set_price( $product_id, $price ),
					$meta->set_date( $product_id, $date ),
					$meta->set_barcode( $product_id, $barcode ),
					$meta->set_cjenik_visibility( $product_id, $visibility ),
				);

				if ( in_array( true, $changes, true ) ) {
					++$saved;
				}
			}
		} finally {
			$automation->suspend_rebuilds( $was_paused );
		}

		if ( $saved > 0 ) {
			Brendigo_Sidrena_Cijena_Automation::instance()->queue_rebuild( 'woo-bulk-editor' );
		}

		$page = isset( $_POST['sidrena_wc_page'] ) ? max( 1, absint( wp_unslash( $_POST['sidrena_wc_page'] ) ) ) : 1;
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'brendigo-sidrena-cijena',
					'tab'            => 'proizvodi',
					'sidrena_wc_page'=> $page,
					'wc_saved'       => $saved,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * AJAX batch snapshot from the settings page.
	 */
	public function ajax_snapshot() {
		check_ajax_referer( 'sidrena_cijena_snapshot', 'nonce' );

		if ( ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		$page        = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
		$offset      = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0;
		$child       = isset( $_POST['child'] ) ? absint( wp_unslash( $_POST['child'] ) ) : 0;
		$stamp_today = isset( $_POST['stamp_today'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['stamp_today'] ) );

		try {
			$result = Brendigo_Sidrena_Cijena_Activator::process_batch( $page, $stamp_today, $offset, $child );
			wp_send_json_success( $result );
		} catch ( Throwable $e ) {
			Brendigo_Sidrena_Cijena_Plugin::log_exception( $e );
			wp_send_json_error( array( 'message' => __( 'Obrada cijena nije uspjela. Pokušajte ponovno.', 'brendigo-sidrena-cijena' ) ) );
		}
	}

	/**
	 * Wipe plugin data and deactivate.
	 */
	public function ajax_purge() {
		check_ajax_referer( 'sidrena_cijena_purge', 'nonce' );

		if ( ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() || ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		try {
			Brendigo_Sidrena_Cijena_Plugin::purge_all_data();

			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			deactivate_plugins( plugin_basename( BRENDIGO_SIDRENA_CIJENA_FILE ) );

			wp_send_json_success(
				array(
					'redirect' => admin_url( 'plugins.php' ),
				)
			);
		} catch ( Throwable $e ) {
			Brendigo_Sidrena_Cijena_Plugin::log_exception( $e );
			wp_send_json_error( array( 'message' => __( 'Brisanje podataka nije uspjelo. Pokušajte ponovno.', 'brendigo-sidrena-cijena' ) ) );
		}
	}
}
