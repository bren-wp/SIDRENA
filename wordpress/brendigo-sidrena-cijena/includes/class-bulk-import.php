<?php
/**
 * Bulk CSV / XML price import matched by SKU, then by the existing id column.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin AJAX importer.
 */
class Brendigo_Sidrena_Cijena_Bulk_Import {

	const BATCH_SIZE   = 40;
	const MAX_ROWS     = 25000;
	const MAX_FIELDS   = 128;
	const MAX_BYTES    = 20971520;
	const TOKEN_LENGTH = 32;
	const TRANSIENT    = 'sidrena_cijena_import_';
	const LOCK_PREFIX  = 'sidrena_cijena_import_lock_';

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
	 * Register AJAX.
	 */
	private function __construct() {
		add_action( 'wp_ajax_sidrena_cijena_import_parse', array( $this, 'ajax_parse' ) );
		add_action( 'wp_ajax_sidrena_cijena_import_run', array( $this, 'ajax_run' ) );
		add_action( 'wp_ajax_sidrena_cijena_import_preflight', array( $this, 'ajax_preflight' ) );
		add_action( 'admin_post_sidrena_cijena_xlsx_template', array( $this, 'xlsx_template' ) );

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'purge_legacy_upload_imports' ) );
		}
	}

	/** Download an authenticated XLSX template without creating a public file. */
	public function xlsx_template() {
		if ( ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() ) {
			wp_die( esc_html__( 'Nemate dozvolu za izvoz.', 'brendigo-sidrena-cijena' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'sidrena_xlsx_template' );
		$bytes = Brendigo_Sidrena_Cijena_XLSX::template_bytes();
		if ( ! is_string( $bytes ) || '' === $bytes ) {
			wp_die( esc_html__( 'Na poslužitelju nije dostupno PHP ZIP proširenje.', 'brendigo-sidrena-cijena' ) );
		}
		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="sidrena-predlozak.xlsx"' );
		header( 'Content-Length: ' . strlen( $bytes ) );
		echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XLSX binary.
		exit;
	}

	/**
	 * Upload + parse a CSV/XML file into a staged JSON job.
	 */
	public function ajax_parse() {
		check_ajax_referer( 'sidrena_cijena_import', 'nonce' );

		if ( ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Nemate dozvolu za uvoz.', 'brendigo-sidrena-cijena' ) ), 403 );
		}

		if ( empty( $_FILES['file'] ) || ! isset( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Odaberite CSV ili XML datoteku.', 'brendigo-sidrena-cijena' ) ) );
		}

		$file = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $file['error'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Prijenos datoteke nije uspio.', 'brendigo-sidrena-cijena' ) ) );
		}

		$name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, array( 'csv', 'xml', 'xlsx' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Dopušteni su CSV, XML i XLSX.', 'brendigo-sidrena-cijena' ) ) );
		}

		$tmp = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			wp_send_json_error( array( 'message' => __( 'Datoteka nije valjana.', 'brendigo-sidrena-cijena' ) ) );
		}

		$bytes = Brendigo_Sidrena_Cijena_Filesystem::size( $tmp );
		if ( $bytes < 1 || $bytes > self::MAX_BYTES ) {
			wp_send_json_error( array( 'message' => __( 'Datoteka je veća od 20 MB.', 'brendigo-sidrena-cijena' ) ) );
		}

		// XLSX is a ZIP container; never inspect it as plaintext or normalize its encoding.
		if ( 'xlsx' === $ext ) {
			$parsed = Brendigo_Sidrena_Cijena_XLSX::parse( $tmp );
			if ( is_wp_error( $parsed ) ) {
				wp_send_json_error( array( 'message' => $parsed->get_error_message() ) );
			}
		} else {
		$contents = Brendigo_Sidrena_Cijena_Filesystem::get_contents( $tmp );
		if ( false === $contents || '' === trim( $contents ) ) {
			wp_send_json_error( array( 'message' => __( 'Datoteka je prazna.', 'brendigo-sidrena-cijena' ) ) );
		}

		if ( false !== strpos( $contents, "\0" ) ) {
			wp_send_json_error( array( 'message' => __( 'Datoteka sadrži binarne podatke i nije valjani CSV/XML.', 'brendigo-sidrena-cijena' ) ) );
		}

		if ( 'xml' === $ext ) {
			$xml_probe = ltrim( preg_replace( '/^\xEF\xBB\xBF/', '', $contents ) );
			if ( '' === $xml_probe || '<' !== $xml_probe[0] || preg_match( '/<!DOCTYPE|<!ENTITY/i', $xml_probe ) ) {
				wp_send_json_error( array( 'message' => __( 'XML datoteka nije dopuštenog ili sigurnog formata.', 'brendigo-sidrena-cijena' ) ) );
			}
		}

		$parser   = Brendigo_Sidrena_Cijena_Import_Parse::instance();
		$contents = $parser->normalize_encoding( $contents );
		$parsed   = ( 'xml' === $ext ) ? $parser->parse_xml( $contents ) : $parser->parse_csv( $contents );
		} // End plaintext (CSV/XML) reader.

		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error( array( 'message' => $parsed->get_error_message() ) );
		}

		$dir = $this->get_import_dir();
		if ( '' === $dir || ! wp_mkdir_p( $dir ) ) {
			wp_send_json_error( array( 'message' => __( 'Mapa za uvoz nije mogla biti stvorena.', 'brendigo-sidrena-cijena' ) ) );
		}

		$this->ensure_import_dir_protected( $dir );
		$this->purge_old_jobs();

		$token = strtolower( wp_generate_password( 32, false, false ) );
		$path  = $this->import_job_path( $token, false );
		if ( '' === $path ) {
			wp_send_json_error( array( 'message' => __( 'Privremena datoteka uvoza nije mogla biti spremljena.', 'brendigo-sidrena-cijena' ) ) );
		}
		$json = wp_json_encode(
			array(
				'fields' => $parsed['fields'],
				'rows'   => $parsed['rows'],
			)
		);

		if ( ! is_string( $json ) || '' === $json ) {
			wp_send_json_error( array( 'message' => __( 'Privremeni podaci uvoza nisu mogli biti pripremljeni.', 'brendigo-sidrena-cijena' ) ) );
		}

		if ( ! Brendigo_Sidrena_Cijena_Filesystem::put_contents( $path, $json ) ) {
			wp_send_json_error( array( 'message' => __( 'Privremena datoteka uvoza nije mogla biti spremljena.', 'brendigo-sidrena-cijena' ) ) );
		}

		// Keep staged jobs private to the administrator who created them.
		if ( function_exists( 'wp_chmod_file' ) ) {
			wp_chmod_file( $path );
		}

		set_transient(
			self::TRANSIENT . $token,
			array(
				'total'        => count( $parsed['rows'] ),
				'filename'     => $name,
				'created'      => time(),
				'user_id'      => get_current_user_id(),
				'offset'       => 0,
				'updated'      => 0,
				'skipped'      => 0,
				'missing'      => 0,
				'created_rows' => 0,
				'config_hash'  => '',
			),
			HOUR_IN_SECONDS
		);

		wp_send_json_success(
			array(
				'token'    => $token,
				'fields'   => $parsed['fields'],
				'total'    => count( $parsed['rows'] ),
				'preview'  => array_slice( $parsed['rows'], 0, 4 ),
				'filename' => $name,
				'guessed'  => array(
					'sku'             => $parser->guess_sku_field( $parsed['fields'] ),
					'barkod'          => $parser->guess_named_field( $parsed['fields'], array( 'barkod', 'barcode', 'ean', 'gtin' ) ),
					'name'            => $parser->guess_named_field( $parsed['fields'], array( 'naziv', 'name', 'title', 'proizvod', 'product', 'product_name', 'usluga' ) ),
					'url'             => $parser->guess_named_field( $parsed['fields'], array( 'url', 'link', 'permalink', 'href' ) ),
					'regular'         => $parser->guess_named_field( $parsed['fields'], array( 'cijena_redovna', 'maloprodajna_cijena', 'maloprodajna', 'regular_price', 'regular', 'price', 'cijena', 'cijena_s_pdvom', 'cijena_s_pdv_om' ) ),
					'sale'            => $parser->guess_named_field( $parsed['fields'], array( 'cijena_akcijska', 'sale_price', 'sale', 'akcijska' ) ),
					'sidrena'         => $parser->guess_named_field( $parsed['fields'], array( 'sidrena_cijena', 'sidrena', 'bazna_cijena', 'anchor_price' ) ),
					'datum'           => $parser->guess_named_field( $parsed['fields'], array( 'sidrena_datum', 'sidrena_cijena_datum', 'datum', 'datum_sidrene' ) ),
					'vrsta'           => $parser->guess_named_field( $parsed['fields'], array( 'vrsta', 'tip', 'type', 'usluga_proizvod' ) ),
					'poslovnica'      => $parser->guess_named_field( $parsed['fields'], array( 'poslovnica', 'oznaka', 'outlet' ) ),
					'jedinica'        => $parser->guess_named_field( $parsed['fields'], array( 'jedinica_mjere', 'jedinica', 'unit', 'unit_of_measure', 'mjerna_jedinica' ) ),
					'jedinica_cijena' => $parser->guess_named_field( $parsed['fields'], array( 'cijena_jedinice_mjere', 'cijena_jedinice', 'cijena_za_jedinicu', 'cijena_za_jedinicu_mjere', 'unit_price' ) ),
					'marka'           => $parser->guess_named_field( $parsed['fields'], array( 'marka', 'brand', 'proizvodjac', 'manufacturer' ) ),
					'oblik'           => $parser->guess_named_field( $parsed['fields'], array( 'naziv_posebnog_oblika', 'poseban_oblik', 'oblik', 'naziv_posebnog_oblika_prodaje' ) ),
					'cjm'             => $parser->guess_named_field( $parsed['fields'], array( 'cjm', 'sidrena_cijena_cjm' ) ),
					'cjm_suffix'      => $parser->guess_named_field( $parsed['fields'], array( 'cjm_suffix', 'cjm_dodatak', 'sidrena_cijena_cjm_suffix' ) ),
					'dostupnost'      => $parser->guess_named_field( $parsed['fields'], array( 'dostupnost', 'availability', 'stock_status', 'zaliha' ) ),
					'id'              => $parser->guess_named_field( $parsed['fields'], array( 'id' ) ),
				),
			)
		);
	}

	/**
	 * Read-only import preview: validate a bounded sample using the actual mapped columns.
	 * This does not attempt a rollback and makes no database changes.
	 */
	public function ajax_preflight() {
		check_ajax_referer( 'sidrena_cijena_import', 'nonce' );
		if ( ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Nemate dozvolu.', 'brendigo-sidrena-cijena' ) ), 403 );
		}
		$token = isset( $_POST['token'] ) ? $this->normalize_job_token( sanitize_text_field( wp_unslash( $_POST['token'] ) ) ) : '';
		$map = array();
		$raw_map = isset( $_POST['map'] ) && is_array( $_POST['map'] ) ? wp_unslash( $_POST['map'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		foreach ( $raw_map as $key => $value ) {
			if ( is_string( $key ) && is_scalar( $value ) ) { $map[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value ); }
		}
		$job = '' !== $token ? get_transient( self::TRANSIENT . $token ) : false;
		$path = '' !== $token ? $this->import_job_path( $token, true ) : '';
		if ( ! is_array( $job ) || '' === $path || ! isset( $job['user_id'] ) || (int) $job['user_id'] !== get_current_user_id() || ! empty( $job['offset'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Uvoz nije dostupan ili je već započet. Ponovno učitajte datoteku.', 'brendigo-sidrena-cijena' ) ), 409 );
		}
		$payload = json_decode( (string) Brendigo_Sidrena_Cijena_Filesystem::get_contents( $path ), true );
		if ( ! is_array( $payload ) || empty( $payload['rows'] ) || empty( $payload['fields'] ) || count( $payload['rows'] ) !== (int) $job['total'] ) {
			wp_send_json_error( array( 'message' => __( 'Sadržaj uvoza nije valjan.', 'brendigo-sidrena-cijena' ) ), 409 );
		}
		$fields = $payload['fields'];
		foreach ( $map as $field ) {
			if ( '' !== $field && ! in_array( $field, $fields, true ) ) {
				wp_send_json_error( array( 'message' => __( 'Jedno mapirano polje ne postoji u datoteci.', 'brendigo-sidrena-cijena' ) ), 400 );
			}
		}
		$woo = Brendigo_Sidrena_Cijena_Plugin::woocommerce_active();
		$sku = isset( $map['sku'] ) ? $map['sku'] : '';
		$id = isset( $map['id'] ) ? $map['id'] : '';
		if ( $woo && '' === $sku && '' === $id ) {
			wp_send_json_error( array( 'message' => __( 'Odaberite SKU ili ID.', 'brendigo-sidrena-cijena' ) ), 400 );
		}
		$outlet = isset( $_POST['import_poslovnica'] ) ? sanitize_text_field( wp_unslash( $_POST['import_poslovnica'] ) ) : '';
		if ( '' === $outlet || ( 'webshop' !== $outlet && ! Brendigo_Sidrena_Cijena_Poslovnice::instance()->get_by_oznaka( $outlet ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Odaberite valjanu poslovnicu.', 'brendigo-sidrena-cijena' ) ), 400 );
		}
		$sample = array_slice( $payload['rows'], 0, 200 );
		$found = 0; $new = 0; $invalid = 0; $repeated = 0; $seen = array();
		foreach ( $sample as $row ) {
			if ( ! is_array( $row ) ) { ++$invalid; continue; }
			$key = '' !== $sku && isset( $row[ $sku ] ) ? trim( (string) $row[ $sku ] ) : '';
			$id_value = '' !== $id && isset( $row[ $id ] ) ? trim( (string) $row[ $id ] ) : '';
			$identity = '' !== $key ? 'sku:' . $key : ( '' !== $id_value ? 'id:' . $id_value : '' );
			if ( '' !== $identity ) {
				if ( isset( $seen[ $identity ] ) ) { ++$repeated; }
				$seen[ $identity ] = true;
			}
			$bad = false;
			foreach ( array( 'regular', 'sale', 'sidrena', 'jedinica_cijena' ) as $field ) {
				$column = isset( $map[ $field ] ) ? $map[ $field ] : '';
				$raw = '' !== $column && isset( $row[ $column ] ) ? trim( (string) $row[ $column ] ) : '';
				if ( '' !== $raw && '' === $this->import_price( $raw ) ) { $bad = true; }
			}
			if ( $bad ) { ++$invalid; }
			$match_id = $woo && 'webshop' === $outlet && '' !== $key && function_exists( 'wc_get_product_id_by_sku' ) ? (int) wc_get_product_id_by_sku( $key ) : 0;
			if ( $woo && ! $match_id && '' !== $id_value && ctype_digit( $id_value ) && function_exists( 'wc_get_product' ) && wc_get_product( (int) $id_value ) ) { $match_id = (int) $id_value; }
			if ( $match_id ) { ++$found; } else { ++$new; }
		}
		if ( $invalid || $repeated ) {
			// The browser also displays this information, but server-side validation
			// must prevent a direct AJAX call from bypassing the preflight.
			wp_send_json_error( array( 'message' => __( 'Uzorak uvoza ima neispravne iznose ili ponovljene identifikatore. Ispravite datoteku.', 'brendigo-sidrena-cijena' ) ), 400 );
		}
		$preflight_hash = $this->import_config_hash( $map, $outlet, $woo, $fields );
		if ( '' === $preflight_hash ) {
			wp_send_json_error( array( 'message' => __( 'Mapiranje uvoza nije moguće provjeriti.', 'brendigo-sidrena-cijena' ) ), 400 );
		}
		$job['preflight_hash'] = $preflight_hash;
		$job['preflight_at'] = time();
		set_transient( self::TRANSIENT . $token, $job, HOUR_IN_SECONDS );
		wp_send_json_success( array( 'checked' => count( $sample ), 'total' => count( $payload['rows'] ), 'matched_woo' => $found, 'manual_or_new' => $new, 'invalid_prices' => $invalid, 'duplicate_keys' => $repeated, 'sample_only' => count( $sample ) < count( $payload['rows'] ) ) );
	}

	/**
	 * Process one batch of mapped rows.
	 */
	public function ajax_run() {
		check_ajax_referer( 'sidrena_cijena_import', 'nonce' );

		if ( ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Nemate dozvolu za uvoz.', 'brendigo-sidrena-cijena' ) ), 403 );
		}

		$token = isset( $_POST['token'] ) ? $this->normalize_job_token( sanitize_text_field( wp_unslash( $_POST['token'] ) ) ) : '';
		$map   = isset( $_POST['map'] ) && is_array( $_POST['map'] ) ? wp_unslash( $_POST['map'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( '' === $token ) {
			wp_send_json_error( array( 'message' => __( 'Privremeni identifikator uvoza nije valjan. Ponovno učitajte datoteku.', 'brendigo-sidrena-cijena' ) ), 400 );
		}

		$job  = get_transient( self::TRANSIENT . $token );
		$path = $this->import_job_path( $token, true );
		if ( ! is_array( $job ) || '' === $path ) {
			wp_send_json_error( array( 'message' => __( 'Uvoz je istekao. Ponovno učitajte datoteku.', 'brendigo-sidrena-cijena' ) ) );
		}

		if ( empty( $job['user_id'] ) || (int) $job['user_id'] !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Ovaj privremeni uvoz pripada drugom korisniku. Ponovno učitajte datoteku.', 'brendigo-sidrena-cijena' ) ), 403 );
		}

		$sku_field = isset( $map['sku'] ) ? sanitize_text_field( $map['sku'] ) : '';
		$id_field  = isset( $map['id'] ) ? sanitize_text_field( $map['id'] ) : '';
		$woo       = Brendigo_Sidrena_Cijena_Plugin::woocommerce_active();

		if ( $woo && '' === $sku_field && '' === $id_field ) {
			wp_send_json_error( array( 'message' => __( 'Odaberite stupac za SKU ili ID.', 'brendigo-sidrena-cijena' ) ) );
		}

		$import_outlet = isset( $_POST['import_poslovnica'] ) ? sanitize_text_field( wp_unslash( $_POST['import_poslovnica'] ) ) : '';
		if ( '' === $import_outlet ) {
			wp_send_json_error( array( 'message' => __( 'Odaberite poslovnicu u koju se uvozi.', 'brendigo-sidrena-cijena' ) ) );
		}
		if ( 'webshop' !== $import_outlet && null === Brendigo_Sidrena_Cijena_Poslovnice::instance()->get_by_oznaka( $import_outlet ) ) {
			wp_send_json_error( array( 'message' => __( 'Odabrana poslovnica ne postoji.', 'brendigo-sidrena-cijena' ) ) );
		}

		$payload_json = Brendigo_Sidrena_Cijena_Filesystem::get_contents( $path );
		$payload      = false !== $payload_json ? json_decode( $payload_json, true ) : null;
		if ( ! is_array( $payload ) || empty( $payload['rows'] ) || ! is_array( $payload['rows'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Privremeni uvoz nije čitljiv.', 'brendigo-sidrena-cijena' ) ) );
		}

		$fields = isset( $payload['fields'] ) && is_array( $payload['fields'] ) ? $payload['fields'] : array();
		if ( '' !== $sku_field && ! in_array( $sku_field, $fields, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Odabrani SKU stupac ne postoji u datoteci.', 'brendigo-sidrena-cijena' ) ) );
		}
		if ( '' !== $id_field && ! in_array( $id_field, $fields, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Odabrani ID stupac ne postoji u datoteci.', 'brendigo-sidrena-cijena' ) ) );
		}

		if ( isset( $job['total'] ) && absint( $job['total'] ) !== count( $payload['rows'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Privremeni uvoz nije cjelovit. Ponovno učitajte datoteku.', 'brendigo-sidrena-cijena' ) ), 409 );
		}

		$regular            = $this->mapped_field( $map, 'regular', $fields );
		$sale               = $this->mapped_field( $map, 'sale', $fields );
		$sidrena            = $this->mapped_field( $map, 'sidrena', $fields );
		$name               = $this->mapped_field( $map, 'name', $fields );
		$url                = $this->mapped_field( $map, 'url', $fields );
		$dostupnost         = $this->mapped_field( $map, 'dostupnost', $fields );
		$datum              = $this->mapped_field( $map, 'datum', $fields );
		$jedinica           = $this->mapped_field( $map, 'jedinica', $fields );
		$jedinica_cijena    = $this->mapped_field( $map, 'jedinica_cijena', $fields );
		$marka              = $this->mapped_field( $map, 'marka', $fields );
		$oblik              = $this->mapped_field( $map, 'oblik', $fields );
		$cjm                = $this->mapped_field( $map, 'cjm', $fields );
		$cjm_suffix         = $this->mapped_field( $map, 'cjm_suffix', $fields );
		$vrsta              = $this->mapped_field( $map, 'vrsta', $fields );
		$poslovnica         = $this->mapped_field( $map, 'poslovnica', $fields );
		$retail_is_display  = $this->is_display_retail_column( $regular );
		$sidrena_is_display = $this->file_has_display_retail( $fields );

		$sku_field = $this->mapped_field( $map, 'sku', $fields );
		$barcode   = $this->mapped_field( $map, 'barkod', $fields );
		$id_field  = $this->mapped_field( $map, 'id', $fields );

		$normalized_map = array(
			'sku'             => $sku_field,
			'barkod'          => $barcode,
			'id'              => $id_field,
			'name'            => $name,
			'url'             => $url,
			'regular'         => $regular,
			'sale'            => $sale,
			'sidrena'         => $sidrena,
			'dostupnost'      => $dostupnost,
			'datum'           => $datum,
			'jedinica'        => $jedinica,
			'jedinica_cijena' => $jedinica_cijena,
			'marka'           => $marka,
			'oblik'           => $oblik,
			'cjm'             => $cjm,
			'cjm_suffix'      => $cjm_suffix,
			'vrsta'           => $vrsta,
			'poslovnica'      => $poslovnica,
		);
		$config_hash = $this->import_config_hash( $normalized_map, $import_outlet, $woo, $fields );
		if ( '' === $config_hash ) {
			wp_send_json_error( array( 'message' => __( 'Postavke mapiranja uvoza nisu valjane.', 'brendigo-sidrena-cijena' ) ), 400 );
		}
		if ( empty( $job['preflight_hash'] ) || ! is_string( $job['preflight_hash'] )
			|| ! hash_equals( $job['preflight_hash'], $config_hash )
			|| empty( $job['preflight_at'] ) || ( time() - (int) $job['preflight_at'] ) > HOUR_IN_SECONDS ) {
			wp_send_json_error( array( 'message' => __( 'Prije uvoza obavezna je uspješna provjera istog mapiranja i poslovnice. Pokrenite uvoz ponovno.', 'brendigo-sidrena-cijena' ) ), 409 );
		}

		if ( ! empty( $job['config_hash'] ) && ! hash_equals( (string) $job['config_hash'], $config_hash ) ) {
			wp_send_json_error( array( 'message' => __( 'Mapiranje ili poslovnica promijenjeni su nakon početka uvoza. Ponovno učitajte datoteku i pokrenite novi uvoz.', 'brendigo-sidrena-cijena' ) ), 409 );
		}

		if ( ! $this->acquire_job_lock( $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Prethodni dio ovog uvoza još se obrađuje. Pričekajte nekoliko sekundi i pokušajte ponovno.', 'brendigo-sidrena-cijena' ) ), 409 );
		}

		try {
			$rows      = $payload['rows'];
			$total     = count( $rows );
			$offset    = isset( $job['offset'] ) ? min( absint( $job['offset'] ), $total ) : 0;
			$slice     = array_slice( $rows, $offset, self::BATCH_SIZE );
			$updated   = isset( $job['updated'] ) ? absint( $job['updated'] ) : 0;
			$skipped   = isset( $job['skipped'] ) ? absint( $job['skipped'] ) : 0;
			$missing   = isset( $job['missing'] ) ? absint( $job['missing'] ) : 0;
			$created   = isset( $job['created_rows'] ) ? absint( $job['created_rows'] ) : 0;
			$processed = $offset;

			if ( $woo ) {
				$meta            = Brendigo_Sidrena_Cijena_Product_Meta::instance();
				$catalog         = Brendigo_Sidrena_Cijena_Catalog::instance();
				$catalog_started = false;
				$outlet_import   = ( 'webshop' !== strtolower( $import_outlet ) );
				$outlets         = $outlet_import ? Brendigo_Sidrena_Cijena_Poslovnice::instance() : null;
				if ( $outlets ) {
					$outlets->begin_batch();
				}

				foreach ( $slice as $row ) {
					++$processed;
					if ( ! is_array( $row ) ) {
						++$skipped;
						continue;
					}

					$sku        = ( '' !== $sku_field && isset( $row[ $sku_field ] ) ) ? trim( (string) $row[ $sku_field ] ) : '';
					$id_raw     = ( '' !== $id_field && isset( $row[ $id_field ] ) ) ? trim( (string) $row[ $id_field ] ) : '';
					$id_target  = $this->import_id_target( $id_raw, true );
					$product_id = ( '' !== $sku ) ? (int) wc_get_product_id_by_sku( $sku ) : 0;
					$vrsta_raw  = $this->mapped_text( $row, $vrsta );
					$is_service = null !== $vrsta_raw && 'usluga' === $catalog->normalize_vrsta( $vrsta_raw );
					if ( ( ! $product_id || $is_service ) && ! $is_service && $id_target['woo'] > 0 && function_exists( 'wc_get_product' ) && wc_get_product( $id_target['woo'] ) ) {
						$product_id = $id_target['woo'];
					}
					if ( ! $product_id || $is_service ) {
						$catalog_id = $id_target['catalog'];
						$name_value = trim( (string) ( $name && isset( $row[ $name ] ) ? $row[ $name ] : '' ) );
						if ( '' === $sku && '' === $name_value && '' === $catalog_id ) {
							++$skipped;
							continue;
						}
						if ( ! $catalog_started ) {
							$catalog->begin_batch();
							$catalog_started = true;
						}
						$item = $this->catalog_item_from_map( $row, $sku_field, $name, $url, $regular, $sale, $sidrena, $dostupnost, $jedinica, $jedinica_cijena, $marka, $oblik, $cjm, $cjm_suffix, $vrsta, $datum, $poslovnica, $import_outlet, $barcode );
						if ( '' !== $catalog_id ) {
							$item['id'] = $catalog_id;
						}
						$result = $catalog->upsert_working_row( $item );
						if ( 'updated' === $result ) {
							++$updated;
						} elseif ( 'created' === $result ) {
							++$created;
						} else {
							++$skipped;
						}
						continue;
					}

					$product = wc_get_product( $product_id );
					if ( ! $product ) {
						++$missing;
						continue;
					}

					$changed       = false;
					$product_dirty = false;
					$oblik_value   = $this->mapped_text( $row, $oblik );

					if ( $retail_is_display ) {
						$price = $this->import_price( isset( $row[ $regular ] ) ? $row[ $regular ] : '' );
						if ( '' !== $price ) {
							$target_sale = null !== $oblik_value;
							$current     = $target_sale ? (string) $product->get_sale_price( 'edit' ) : (string) $product->get_regular_price( 'edit' );
							$stored      = $meta->display_price_to_stored( $product, $price, $current );
							if ( ! $this->prices_equal( $current, $stored ) ) {
								if ( $target_sale ) {
									$product->set_sale_price( $stored );
								} else {
									$product->set_regular_price( $stored );
								}
								$changed       = true;
								$product_dirty = true;
							}
						}
					} else {
						if ( $regular ) {
							$price = $this->import_price( isset( $row[ $regular ] ) ? $row[ $regular ] : '' );
							if ( '' !== $price && ! $this->prices_equal( $product->get_regular_price( 'edit' ), $price ) ) {
								$product->set_regular_price( $price );
								$changed       = true;
								$product_dirty = true;
							}
						}

						if ( $sale ) {
							$price = $this->import_price( isset( $row[ $sale ] ) ? $row[ $sale ] : '' );
							if ( '' !== $price && ! $this->prices_equal( $product->get_sale_price( 'edit' ), $price ) ) {
								$product->set_sale_price( $price );
								$changed       = true;
								$product_dirty = true;
							}
						}
					}

					if ( $sidrena ) {
						$price = $this->import_price( isset( $row[ $sidrena ] ) ? $row[ $sidrena ] : '' );
						if ( '' !== $price ) {
							$current = (string) $meta->get_price( $product_id );
							if ( $sidrena_is_display ) {
								$price = $meta->display_price_to_stored( $product, $price, $current );
							}
							if ( ! $this->prices_equal( $current, $price ) ) {
								$meta->set_price( $product_id, $price );
								$changed = true;
							}
						}
					}

					$date_value = $this->mapped_text( $row, $datum );
					if ( null !== $date_value ) {
						$normalized_date = Brendigo_Sidrena_Cijena_Money::normalize_date( $date_value );
						if ( '' !== $normalized_date && $normalized_date !== $meta->get_date( $product_id ) ) {
							$meta->set_date( $product_id, $normalized_date );
							$changed = true;
						}
					}

					$unit = $this->mapped_text( $row, $jedinica );
					if ( null !== $unit ) {
						$unit = sanitize_text_field( $unit );
						if ( $unit !== $meta->get_jedinica( $product_id ) ) {
							$meta->set_jedinica( $product_id, $unit );
							$changed = true;
						}
					}

					if ( $jedinica_cijena ) {
						$unit_price = $this->import_price( isset( $row[ $jedinica_cijena ] ) ? $row[ $jedinica_cijena ] : '' );
						if ( '' !== $unit_price && ! $this->prices_equal( $meta->get_jedinica_cijena( $product_id ), $unit_price ) ) {
							$meta->set_jedinica_cijena( $product_id, $unit_price );
							$changed = true;
						}
					}

					if ( null !== $oblik_value ) {
						$oblik_value = sanitize_text_field( $oblik_value );
						if ( $oblik_value !== $meta->get_oblik( $product_id ) ) {
							$meta->set_oblik( $product_id, $oblik_value );
							$changed = true;
						}
					}

					$barcode_value = $this->mapped_text( $row, $barcode );
					if ( null !== $barcode_value ) {
						$barcode_value = substr( preg_replace( '/[^0-9A-Za-z._-]/', '', $barcode_value ), 0, 64 );
						if ( $barcode_value !== (string) get_post_meta( $product_id, BRENDIGO_SIDRENA_CIJENA_META_BARCODE, true ) ) {
							if ( $meta->set_barcode( $product_id, $barcode_value ) ) {
								$changed = true;
							}
						}
					}

					$marka_value = $this->mapped_text( $row, $marka );
					if ( null !== $marka_value && Brendigo_Sidrena_Cijena_Settings::instance()->assign_product_marka( $product, $marka_value ) ) {
						$changed = true;
					}

					$cjm_value = $this->mapped_text( $row, $cjm );
					if ( null !== $cjm_value ) {
						$cjm_enabled = $this->is_cjm_on( $cjm_value );
						if ( $cjm_enabled !== $meta->is_cjm_enabled( $product_id ) ) {
							$meta->set_cjm_enabled( $product_id, $cjm_enabled );
							$changed = true;
						}
					}

					$suffix_value = $this->mapped_text( $row, $cjm_suffix );
					if ( null !== $suffix_value ) {
						$suffix_value = sanitize_text_field( $suffix_value );
						if ( $suffix_value !== $meta->get_cjm_suffix( $product_id ) ) {
							$meta->set_cjm_suffix( $product_id, $suffix_value );
							$changed = true;
						}
					}

					$stock_value = $this->mapped_text( $row, $dostupnost );
					if ( $outlet_import && $outlets ) {
						if ( $outlets->set_product_dostupnost( $import_outlet, $product_id, null === $stock_value ? '' : $stock_value, true ) ) {
							$changed = true;
						}
					} elseif ( null !== $stock_value ) {
						$flag         = Brendigo_Sidrena_Cijena_Catalog::instance()->normalize_dostupnost( $stock_value );
						$stock_status = 'nedostupno' === $flag ? 'outofstock' : 'instock';
						if ( $stock_status !== (string) $product->get_stock_status( 'edit' ) ) {
							$product->set_stock_status( $stock_status );
							$changed       = true;
							$product_dirty = true;
						}
					}

					if ( $product_dirty ) {
						$product->save();
					}

					if ( $changed ) {
						++$updated;
					} else {
						++$skipped;
					}
				}

				if ( $catalog_started ) {
					// The public feed is queued once when the complete import finishes.
					$catalog->commit_batch( false );
				}
				if ( $outlets ) {
					$outlets->commit_batch();
				}
			} else {
				$catalog = Brendigo_Sidrena_Cijena_Catalog::instance();
				$catalog->begin_batch();

				foreach ( $slice as $row ) {
					++$processed;
					if ( ! is_array( $row ) ) {
						++$skipped;
						continue;
					}

					$item = $this->catalog_item_from_map( $row, $sku_field, $name, $url, $regular, $sale, $sidrena, $dostupnost, $jedinica, $jedinica_cijena, $marka, $oblik, $cjm, $cjm_suffix, $vrsta, $datum, $poslovnica, $import_outlet, $barcode );
					$id_raw = ( '' !== $id_field && isset( $row[ $id_field ] ) ) ? trim( (string) $row[ $id_field ] ) : '';
					$catalog_id = $this->import_id_target( $id_raw, false )['catalog'];
					if ( '' !== $catalog_id ) {
						$item['id'] = $catalog_id;
					}

					$result = $catalog->upsert_working_row( $item );

					if ( 'updated' === $result ) {
						++$updated;
					} elseif ( 'created' === $result ) {
						++$created;
					} else {
						++$skipped;
					}
				}

				// Queue the public feed only after the entire import has finished.
				$catalog->commit_batch( false );
			}

			$done = $processed >= $total;
			if ( $done ) {
				Brendigo_Sidrena_Cijena_Filesystem::delete( $path );
				delete_transient( self::TRANSIENT . $token );
				if ( class_exists( 'Brendigo_Sidrena_Cijena_Automation' ) ) {
					Brendigo_Sidrena_Cijena_Automation::instance()->queue_rebuild( 'bulk-import', true );
				}
			} else {
				$job['offset']       = $processed;
				$job['updated']      = $updated;
				$job['skipped']      = $skipped;
				$job['missing']      = $missing;
				$job['created_rows'] = $created;
				$job['config_hash']  = $config_hash;
				set_transient( self::TRANSIENT . $token, $job, HOUR_IN_SECONDS );
			}

		} catch ( Throwable $e ) {
			$this->release_job_lock( $token );
			Brendigo_Sidrena_Cijena_Plugin::log_exception( $e );
			wp_send_json_error( array( 'message' => __( 'Uvoz nije uspio. Pojedinosti su zapisane u zapisnik poslužitelja.', 'brendigo-sidrena-cijena' ) ), 500 );
		}

		$this->release_job_lock( $token );

		wp_send_json_success(
			array(
				'offset'    => $processed,
				'total'     => $total,
				'updated'   => $updated,
				'skipped'   => $skipped,
				'missing'   => $missing,
				'created'   => $created,
				'done'      => $done,
				'percent'   => $total ? (int) round( ( $processed / $total ) * 100 ) : 100,
			)
		);
	}

	/**
	 * Split an export id into a Woo product id or a manual catalog id.
	 *
	 * A leading d marks a manual row. That number is never looked up as a Woo product.
	 * With WooCommerce, a plain number is a product id. Without it, a plain number is a catalog id.
	 *
	 * @param string $raw Cell value.
	 * @param bool   $woo WooCommerce is active.
	 * @return array{woo: int, catalog: string}
	 */
	private function import_id_target( $raw, $woo ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return array(
				'woo'     => 0,
				'catalog' => '',
			);
		}

		if ( preg_match( '/^d(\d+)$/i', $raw, $matches ) ) {
			return array(
				'woo'     => 0,
				'catalog' => $matches[1],
			);
		}

		if ( preg_match( '/^\d+$/', $raw ) ) {
			if ( $woo ) {
				return array(
					'woo'     => (int) $raw,
					'catalog' => '',
				);
			}

			return array(
				'woo'     => 0,
				'catalog' => $raw,
			);
		}

		return array(
			'woo'     => 0,
			'catalog' => '',
		);
	}

	/**
	 * Canonical binding of preflight to the exact mapping, outlet and runtime mode.
	 * Both AJAX steps must agree even when posted map keys arrive reordered.
	 */
	private function import_config_hash( $map, $outlet, $woo, $fields ) {
		$keys = array( 'sku', 'barkod', 'id', 'name', 'url', 'regular', 'sale', 'sidrena', 'dostupnost', 'datum', 'jedinica', 'jedinica_cijena', 'marka', 'oblik', 'cjm', 'cjm_suffix', 'vrsta', 'poslovnica' );
		$normalized = array();
		foreach ( $keys as $key ) {
			$normalized[ $key ] = $this->mapped_field( $map, $key, $fields );
		}
		$json = wp_json_encode( array( 'outlet' => $outlet, 'woo' => (bool) $woo, 'map' => $normalized ) );
		return is_string( $json ) ? hash( 'sha256', $json ) : '';
	}

	/**
	 * Sanitize a mapped field name against available columns.
	 *
	 * @param array<string, mixed> $map    Posted map.
	 * @param string               $key    Map key.
	 * @param array<int, string>   $fields Allowed fields.
	 * @return string
	 */
	private function mapped_field( $map, $key, $fields ) {
		$value = isset( $map[ $key ] ) ? sanitize_text_field( $map[ $key ] ) : '';
		if ( '' === $value || ! in_array( $value, $fields, true ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Non-empty mapped cell, or null when the column is unmapped or blank.
	 *
	 * @param array<string, string> $row   Parsed row.
	 * @param string                $field Column name.
	 * @return string|null
	 */
	private function mapped_text( $row, $field ) {
		if ( '' === $field || ! is_array( $row ) || ! isset( $row[ $field ] ) ) {
			return null;
		}

		$value = trim( (string) $row[ $field ] );
		if ( '' === $value ) {
			return null;
		}

		return $value;
	}

	/**
	 * Catalog payload from one mapped import row.
	 *
	 * @param array<string, string> $row             Parsed row.
	 * @param string                $sku_field       SKU column.
	 * @param string                $name            Name column.
	 * @param string                $url             URL column.
	 * @param string                $regular         Regular price column.
	 * @param string                $sale            Sale price column.
	 * @param string                $sidrena         Sidrena column.
	 * @param string                $dostupnost      Stock column.
	 * @param string                $jedinica        Unit column.
	 * @param string                $jedinica_cijena Unit price column.
	 * @param string                $marka           Brand column.
	 * @param string                $oblik           Sale form column.
	 * @param string                $cjm             CJM column.
	 * @param string                $cjm_suffix      CJM suffix column.
	 * @param string                $vrsta           Type column.
	 * @param string                $datum           Date column.
	 * @param string                $poslovnica      Outlet column.
	 * @param string                $import_outlet    Explicit target outlet.
	 * @return array<string, string>
	 */
	private function catalog_item_from_map( $row, $sku_field, $name, $url, $regular, $sale, $sidrena, $dostupnost, $jedinica, $jedinica_cijena, $marka, $oblik, $cjm, $cjm_suffix, $vrsta, $datum, $poslovnica, $import_outlet, $barcode ) {
		$retail      = $regular && isset( $row[ $regular ] ) ? $this->import_price( $row[ $regular ] ) : '';
		$sale_amount = $sale && isset( $row[ $sale ] ) ? $this->import_price( $row[ $sale ] ) : '';
		if ( $this->is_display_retail_column( $regular ) && null !== $this->mapped_text( $row, $oblik ) ) {
			$regular_amount = '';
			if ( '' === $sale_amount && '' !== $retail ) {
				$sale_amount = $retail;
			}
		} else {
			$regular_amount = $retail;
		}

		$item = array(
			'naziv'           => $name && isset( $row[ $name ] ) ? $row[ $name ] : '',
			'sku'             => $sku_field && isset( $row[ $sku_field ] ) ? $row[ $sku_field ] : '',
			'url'             => $url && isset( $row[ $url ] ) ? $row[ $url ] : '',
			'cijena_redovna'  => $regular_amount,
			'cijena_akcijska' => $sale_amount,
			'sidrena_cijena'  => $sidrena && isset( $row[ $sidrena ] ) ? $this->import_price( $row[ $sidrena ] ) : '',
		);
		$this->assign_mapped_text( $item, 'barkod', $row, $barcode );
		$this->assign_mapped_text( $item, 'marka', $row, $marka );
		$this->assign_mapped_text( $item, 'dostupnost', $row, $dostupnost );
		$this->assign_mapped_text( $item, 'jedinica_mjere', $row, $jedinica );
		$this->assign_mapped_text( $item, 'naziv_posebnog_oblika', $row, $oblik );
		$this->assign_mapped_text( $item, 'cjm', $row, $cjm );
		$this->assign_mapped_text( $item, 'cjm_suffix', $row, $cjm_suffix );
		$this->assign_mapped_text( $item, 'vrsta', $row, $vrsta );
		$this->assign_mapped_text( $item, 'sidrena_datum', $row, $datum );
		$this->assign_mapped_text( $item, 'poslovnica', $row, $poslovnica );
		$target = sanitize_text_field( (string) $import_outlet );
		$item['poslovnica'] = ( '' === $target || 'webshop' === strtolower( $target ) ) ? '' : $target;
		if ( $jedinica_cijena ) {
			$unit_price = $this->import_price( isset( $row[ $jedinica_cijena ] ) ? $row[ $jedinica_cijena ] : '' );
			if ( '' !== $unit_price ) {
				$item['cijena_jedinice_mjere'] = $unit_price;
			}
		}

		return $item;
	}

	/**
	 * Copy a non-empty mapped cell onto a catalog payload.
	 *
	 * @param array<string, string> $item  Payload.
	 * @param string                $key   Catalog key.
	 * @param array<string, string> $row   Parsed row.
	 * @param string                $field Column name.
	 */
	private function assign_mapped_text( &$item, $key, $row, $field ) {
		$value = $this->mapped_text( $row, $field );
		if ( null === $value ) {
			return;
		}

		$item[ $key ] = $value;
	}

	/**
	 * Public price-list column mapped onto Redovna cijena.
	 *
	 * @param string $field Column name.
	 * @return bool
	 */
	private function is_display_retail_column( $field ) {
		$field = strtolower( remove_accents( (string) $field ) );
		$field = str_replace( array( ' ', '-', '.' ), '_', $field );

		return in_array( $field, array( 'maloprodajna_cijena', 'maloprodajna' ), true );
	}

	/**
	 * Whether this file is a public price list (display amounts, not stored ones).
	 *
	 * @param array<int, string> $fields Header fields.
	 * @return bool
	 */
	private function file_has_display_retail( $fields ) {
		foreach ( (array) $fields as $field ) {
			if ( $this->is_display_retail_column( $field ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether an imported CJM cell turns the suffix on.
	 *
	 * @param string $value Raw cell.
	 * @return bool
	 */
	private function is_cjm_on( $value ) {
		$value = strtolower( trim( (string) $value ) );

		return in_array( $value, array( 'yes', '1', 'true' ), true );
	}

	/**
	 * Normalize an imported price string.
	 *
	 * @param mixed $value Raw cell.
	 * @return string
	 */
	private function import_price( $value ) {
		// Do not pre-strip arbitrary input: that can silently convert an invalid
		// amount such as "12e3" into "123" before the strict normalizer sees it.
		$value = trim( (string) $value );

		if ( class_exists( 'Brendigo_Sidrena_Cijena_Product_Meta' ) ) {
			return Brendigo_Sidrena_Cijena_Product_Meta::instance()->normalize_price( $value );
		}

		return Brendigo_Sidrena_Cijena_Catalog::instance()->normalize_price( $value );
	}

	/**
	 * Compare two imported price values after applying the plugin's normalizer.
	 *
	 * @param mixed $left  Existing value.
	 * @param mixed $right Imported value.
	 * @return bool
	 */
	private function prices_equal( $left, $right ) {
		return $this->import_price( $left ) === $this->import_price( $right );
	}

	/**
	 * Validate an internal staged-import token without mutating user input.
	 *
	 * @param mixed $token Candidate token.
	 * @return string
	 */
	private function normalize_job_token( $token ) {
		$token = strtolower( trim( (string) $token ) );

		return preg_match( '/^[a-z0-9]{' . self::TOKEN_LENGTH . '}$/', $token ) ? $token : '';
	}

	/**
	 * Import staging directory, outside the web root.
	 *
	 * @return string
	 */
	private function get_import_dir() {
		$base = get_temp_dir();
		if ( ! is_string( $base ) || '' === $base ) {
			return '';
		}

		return trailingslashit( $base ) . 'sidrena-cijena-imports';
	}

	/**
	 * JSON path for a token. The path is derived here, never read from the transient.
	 *
	 * @param string $token      Job token.
	 * @param bool   $must_exist False while the file is about to be created.
	 * @return string
	 */
	private function import_job_path( $token, $must_exist ) {
		$token = $this->normalize_job_token( $token );
		if ( '' === $token ) {
			return '';
		}

		$dir = $this->get_import_dir();
		if ( '' === $dir || ! wp_mkdir_p( $dir ) ) {
			return '';
		}

		$real_dir = realpath( $dir );
		if ( false === $real_dir ) {
			return '';
		}

		$real_dir = wp_normalize_path( $real_dir );
		$path     = $real_dir . '/' . $token . '.json';

		if ( ! $must_exist ) {
			return $path;
		}

		$real = realpath( $path );
		if ( false === $real ) {
			return '';
		}

		$real = wp_normalize_path( $real );
		if ( 0 !== strpos( $real, trailingslashit( $real_dir ) ) ) {
			return '';
		}

		return $real;
	}

	/**
	 * Hide the imports directory if the temp path is ever web-accessible.
	 *
	 * @param string $dir Directory.
	 */
	private function ensure_import_dir_protected( $dir ) {
		$index = trailingslashit( $dir ) . 'index.php';
		if ( ! Brendigo_Sidrena_Cijena_Filesystem::exists( $index ) ) {
			Brendigo_Sidrena_Cijena_Filesystem::put_contents( $index, "<?php\ndefined( 'ABSPATH' ) || exit;\n" );
		}

		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! Brendigo_Sidrena_Cijena_Filesystem::exists( $htaccess ) ) {
			$rules = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
			Brendigo_Sidrena_Cijena_Filesystem::put_contents( $htaccess, $rules );
		}

		$web_config = trailingslashit( $dir ) . 'web.config';
		if ( ! Brendigo_Sidrena_Cijena_Filesystem::exists( $web_config ) ) {
			$config = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n    <authorization>\n      <deny users=\"*\" />\n    </authorization>\n  </system.webServer>\n</configuration>\n";
			Brendigo_Sidrena_Cijena_Filesystem::put_contents( $web_config, $config );
		}
	}


	/**
	 * Staged JSON jobs inside one plugin-owned import directory.
	 *
	 * @param string $dir Import directory.
	 * @return array<int,string>
	 */
	private function staged_job_files( $dir ) {
		if ( ! Brendigo_Sidrena_Cijena_Filesystem::is_dir( $dir ) ) {
			return array();
		}

		$files = array();
		foreach ( Brendigo_Sidrena_Cijena_Filesystem::dirlist( $dir ) as $name => $details ) {
			$name = (string) $name;
			if ( 'json' !== strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
				continue;
			}
			if ( is_array( $details ) && isset( $details['type'] ) && 'f' !== $details['type'] ) {
				continue;
			}
			$files[] = trailingslashit( $dir ) . $name;
		}

		return $files;
	}

	/**
	 * Delete staged JSON left in the old public uploads folder.
	 */
	public function purge_legacy_upload_imports() {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return;
		}

		$dir = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . 'sidrena-cijena/imports' );
		foreach ( $this->staged_job_files( $dir ) as $file ) {
			Brendigo_Sidrena_Cijena_Filesystem::delete( $file );
		}
	}

	/**
	 * Acquire an atomic short-lived lock for one batch job.
	 *
	 * @param string $token Import job token.
	 * @return bool
	 */
	private function acquire_job_lock( $token ) {
		$token = $this->normalize_job_token( $token );
		if ( '' === $token ) {
			return false;
		}

		$key   = self::LOCK_PREFIX . $token;
		$stamp = (int) get_option( $key, 0 );

		if ( $stamp > 0 && ( time() - $stamp ) >= 2 * MINUTE_IN_SECONDS ) {
			delete_option( $key );
		}

		return add_option( $key, time(), '', 'no' );
	}

	/**
	 * Release a batch-job lock.
	 *
	 * @param string $token Import job token.
	 */
	private function release_job_lock( $token ) {
		$token = $this->normalize_job_token( $token );
		if ( '' !== $token ) {
			delete_option( self::LOCK_PREFIX . $token );
		}
	}

	/**
	 * Remove every staged import job and its matching transient.
	 *
	 * Used during plugin deactivation and full data purge so temporary
	 * import payloads are never left behind in the system temp folder.
	 */
	public function purge_all_jobs() {
		$dir = $this->get_import_dir();
		if ( ! Brendigo_Sidrena_Cijena_Filesystem::is_dir( $dir ) ) {
			return;
		}

		foreach ( $this->staged_job_files( $dir ) as $file ) {
				$token = $this->normalize_job_token( basename( $file, '.json' ) );
				if ( '' !== $token ) {
					delete_transient( self::TRANSIENT . $token );
					delete_option( self::LOCK_PREFIX . $token );
				}

				Brendigo_Sidrena_Cijena_Filesystem::delete( $file );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Maintenance scans plugin-owned lock options by prefix; Core has no wildcard option API.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::LOCK_PREFIX ) . '%'
			)
		);

		foreach ( array( 'index.php', '.htaccess', 'web.config' ) as $protection_file ) {
			$path = trailingslashit( $dir ) . $protection_file;
			if ( Brendigo_Sidrena_Cijena_Filesystem::is_file( $path ) ) {
				Brendigo_Sidrena_Cijena_Filesystem::delete( $path );
			}
		}

		// Remove the empty plugin-owned staging directory when no files remain.
		if ( array() === Brendigo_Sidrena_Cijena_Filesystem::dirlist( $dir ) ) {
			Brendigo_Sidrena_Cijena_Filesystem::delete( $dir );
		}
	}

	/**
	 * Remove staged import files older than an hour.
	 */
	public function purge_old_jobs() {
		$dir    = $this->get_import_dir();
		$cutoff = time() - HOUR_IN_SECONDS;

		foreach ( $this->staged_job_files( $dir ) as $file ) {
			$mtime = Brendigo_Sidrena_Cijena_Filesystem::mtime( $file );
			if ( $mtime < 1 || $mtime >= $cutoff ) {
				continue;
			}

			$token = $this->normalize_job_token( basename( $file, '.json' ) );
			if ( '' !== $token ) {
				delete_transient( self::TRANSIENT . $token );
				delete_option( self::LOCK_PREFIX . $token );
			}
			Brendigo_Sidrena_Cijena_Filesystem::delete( $file );
		}

		// A request can die after taking a lock but before releasing it. Clear
		// orphan/stale lock options during the hourly maintenance pass as well.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Maintenance scans plugin-owned lock options by prefix; Core has no wildcard option API.
		$locks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::LOCK_PREFIX ) . '%'
			),
			ARRAY_A
		);
		if ( is_array( $locks ) ) {
			foreach ( $locks as $lock ) {
				$stamp = isset( $lock['option_value'] ) ? (int) $lock['option_value'] : 0;
				if ( $stamp > 0 && ( time() - $stamp ) < 10 * MINUTE_IN_SECONDS ) {
					continue;
				}
				if ( ! empty( $lock['option_name'] ) ) {
					delete_option( (string) $lock['option_name'] );
				}
			}
		}
	}
}
