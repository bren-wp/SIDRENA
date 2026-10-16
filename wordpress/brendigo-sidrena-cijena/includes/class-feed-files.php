<?php
/**
 * Write and stream XML, CSV, and HTML pricelist files.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Write and stream XML, CSV, and HTML pricelist files.
 */
class Brendigo_Sidrena_Cijena_Feed_Files {

	/**
	 * Products shown on one public cjenik page.
	 */
	const CJENIK_PAGE_SIZE = 200;

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
	 * CSV with one product example and one service example for Uvoz.
	 */
	public function send_import_template() {
		$date = Brendigo_Sidrena_Cijena_Catalog::instance()->normalize_date( (string) Brendigo_Sidrena_Cijena_Settings::instance()->get( 'default_date' ) );
		if ( '' === $date ) {
			$date = Brendigo_Sidrena_Cijena_Settings::REFERENCE_DATE_GENERAL;
		}

		$keys = array( 'naziv', 'sku', 'vrsta', 'marka', 'url', 'cijena_redovna', 'cijena_akcijska', 'naziv_posebnog_oblika', 'jedinica_mjere', 'cijena_jedinice_mjere', 'sidrena_cijena', 'sidrena_datum', 'barkod', 'dostupnost', 'poslovnica' );
		$lines   = array();
		$lines[] = $this->csv_line( $keys );
		$lines[] = $this->csv_line(
			array(
				'Primjer proizvoda',
				'SKU-001',
				'proizvod',
				'Marka',
				'',
				'10,00',
				'',
				'',
				'kg',
				'10,00',
				'10,00',
				$date,
				'3850000000000',
				'dostupno',
				'',
			)
		);
		$lines[] = $this->csv_line(
			array(
				'Primjer usluge (dostava HP do 2 kg)',
				'USL-HP-2KG',
				'usluga',
				'',
				'',
				'4,50',
				'',
				'',
				'',
				'',
				'4,50',
				$date,
				'',
				'dostupno',
				'',
			)
		);

		$this->headers( 'text/csv; charset=utf-8', 'sidrena-cijena-predlozak.csv' );
		echo chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) . implode( '', $lines ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Send CSV download.
	 *
	 * @param string                             $filename File name.
	 * @param array<int, array<string, string>>  $rows     Rows.
	 * @param bool                               $public   Client cjenik columns, same as the public feed.
	 */
	public function send_csv( $filename, $rows, $public = false ) {
		$this->headers( 'text/csv; charset=utf-8', $filename );

		if ( $public ) {
			echo $this->build_csv_string( $rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download; fields formula-neutralized and quoted in csv_line().
			exit;
		}

		$lines   = array();
		$lines[] = $this->csv_line( array( 'naziv', 'sku', 'cijena_redovna', 'cijena_akcijska', 'sidrena_cijena' ) );

		foreach ( $rows as $row ) {
			$lines[] = $this->csv_line(
				array(
					$row['naziv'],
					$row['sku'],
					$this->csv_amount( $row['cijena_redovna'] ),
					$this->csv_amount( $row['cijena_akcijska'] ),
					$this->csv_amount( $row['sidrena_cijena'] ),
				)
			);
		}

		echo chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) . implode( '', $lines ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download; fields formula-neutralized and quoted in csv_line().
		exit;
	}

	/**
	 * One CSV row with a semicolon delimiter.
	 *
	 * @param array<int, string> $fields Fields.
	 * @return string
	 */
	public function csv_line( $fields ) {
		$escaped = array();

		foreach ( $fields as $field ) {
			$escaped[] = $this->csv_field( $field );
		}

		return implode( ';', $escaped ) . "\r\n";
	}

	/**
	 * Neutralize spreadsheet formulas, then quote for CSV syntax.
	 *
	 * @param mixed $field Raw field.
	 * @return string
	 */
	public function csv_field( $field ) {
		$field = $this->csv_neutralize_formula( $field );

		if ( false !== strpbrk( $field, ";\"\n\r" ) ) {
			$field = '"' . str_replace( '"', '""', $field ) . '"';
		}

		return $field;
	}

	/**
	 * Prefix spreadsheet formula triggers so Excel/LibreOffice treat the cell as text.
	 *
	 * Number values are left unchanged: a pure numeric value cannot form a valid formula.
	 *
	 * @see https://owasp.org/www-community/attacks/CSV_Injection
	 *
	 * @param mixed $field Raw field.
	 * @return string
	 */
	public function csv_neutralize_formula( $field ) {
		if ( is_int( $field ) || is_float( $field ) ) {
			return (string) $field;
		}

		$field = (string) $field;
		if ( '' === $field ) {
			return $field;
		}

		// 0x09: Tab (\t), 0x0D: Carriage Return (\r).
		$triggers = array( '=', '+', '-', '@', chr( 0x09 ), chr( 0x0D ) );
		$first    = function_exists( 'mb_substr' ) ? mb_substr( $field, 0, 1, 'UTF-8' ) : substr( $field, 0, 1 );

		if ( in_array( $first, $triggers, true )
			|| preg_match( '/^[\x00-\x1F\x7F]/', $field )
			|| preg_match( '/^\s*[=+\-@]/u', $field ) ) {
			return "'" . $field;
		}

		return $field;
	}

	/**
	 * Price columns written with a Croatian decimal comma in CSV only.
	 *
	 * @return array<int, string>
	 */
	public function csv_price_keys() {
		return array( 'cijena_redovna', 'cijena_akcijska', 'cijena_jedinice_mjere', 'maloprodajna_cijena', 'sidrena_cijena' );
	}

	/**
	 * Pure dot-decimal amounts become commas so Excel does not read them as dates.
	 *
	 * Empty values, integers, and dates such as 10.09.2026. stay unchanged.
	 *
	 * @param mixed $value Raw amount.
	 * @return string
	 */
	public function csv_amount( $value ) {
		$value = (string) $value;
		if ( preg_match( '/^-?\d+\.\d+$/', $value ) ) {
			return str_replace( '.', ',', $value );
		}

		return $value;
	}

	/**
	 * Public CSV (semicolon, UTF-8 BOM), same columns as the public XML feed.
	 *
	 * @param array<int, array<string, string>> $rows Rows.
	 * @return string
	 */
	public function build_csv_string( $rows, $type = '' ) {
		$keys  = $this->public_column_keys( $rows, $type );
		$lines = array();
		$lines[] = $this->csv_line( $keys );

		$price_keys = $this->csv_price_keys();

		foreach ( $rows as $row ) {
			$cells = array();
			foreach ( $keys as $key ) {
				$cell = isset( $row[ $key ] ) ? $row[ $key ] : '';
				if ( in_array( $key, $price_keys, true ) ) {
					$cell = $this->csv_amount( $cell );
				}
				$cells[] = $cell;
			}
			$lines[] = $this->csv_line( $cells );
		}

		return chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) . implode( '', $lines );
	}

	/**
	 * Public CSV/XML columns. Service-only feeds drop goods fields.
	 *
	 * @param array<int, array<string, string>> $rows Rows.
	 * @return array<int, string>
	 */
	public function public_column_keys( $rows, $type = '' ) {
		if ( 'usluge' === $type || ( '' === $type && $this->feed_is_services_only( $rows ) ) ) {
			return array( 'id', 'vrsta', 'naziv', 'url', 'maloprodajna_cijena', 'naziv_posebnog_oblika', 'sidrena_cijena', 'sidrena_datum' );
		}

		return array( 'id', 'vrsta', 'naziv', 'sku', 'marka', 'url', 'jedinica_mjere', 'cijena_jedinice_mjere', 'maloprodajna_cijena', 'naziv_posebnog_oblika', 'sidrena_cijena', 'sidrena_datum', 'barkod', 'dostupnost' );
	}

	/**
	 * True when every row is an usluga.
	 *
	 * @param array<int, array<string, string>> $rows Rows.
	 * @return bool
	 */
	public function feed_is_services_only( $rows ) {
		if ( array() === $rows ) {
			return false;
		}

		foreach ( $rows as $row ) {
			$vrsta = isset( $row['vrsta'] ) ? $row['vrsta'] : 'proizvod';
			if ( 'usluga' !== $vrsta ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * xml or csv from settings.
	 *
	 * @return string
	 */
	public function export_format() {
		if ( class_exists( 'Brendigo_Sidrena_Cijena_Settings' ) ) {
			return Brendigo_Sidrena_Cijena_Settings::instance()->export_format();
		}

		return 'xml';
	}

	/**
	 * xml or csv.
	 *
	 * @param mixed $ext Raw.
	 * @return string
	 */
	public function sanitize_ext( $ext ) {
		$ext = is_string( $ext ) ? strtolower( $ext ) : '';

		return 'csv' === $ext ? 'csv' : 'xml';
	}

	/**
	 * Live public filename for a format.
	 *
	 * @param string $format xml|csv.
	 * @return string
	 */
	public function live_filename( $format = '', $oznaka = '' ) {
		$format = '' === $format ? $this->export_format() : $this->sanitize_ext( $format );
		$oznaka = Brendigo_Sidrena_Cijena_Settings::instance()->normalize_oznaka( $oznaka );
		if ( '' !== $oznaka ) {
			return Brendigo_Sidrena_Cijena_Export::PUBLIC_STEM . '-' . $oznaka . '.' . $format;
		}

		return Brendigo_Sidrena_Cijena_Export::PUBLIC_STEM . '.' . $format;
	}

	/**
	 * Uploads basedir from wp_upload_dir() only (no WP_CONTENT_DIR fallback).
	 *
	 * @return string
	 */
	public function get_uploads_basedir() {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}

		return (string) $uploads['basedir'];
	}

	/**
	 * Absolute directory for the public XML file.
	 *
	 * @return string
	 */
	public function get_public_dir() {
		$base = $this->get_uploads_basedir();
		if ( '' === $base ) {
			return '';
		}

		return trailingslashit( $base ) . 'sidrena-cijena';
	}

	/**
	 * Absolute path of the live public feed.
	 *
	 * @param string $format xml|csv, empty = current setting.
	 * @return string
	 */
	public function get_public_path( $format = '', $oznaka = '' ) {
		$dir = $this->get_public_dir();
		if ( '' === $dir ) {
			return '';
		}

		return trailingslashit( $dir ) . $this->live_filename( $format, $oznaka );
	}

	/**
	 * Absolute path of the generated HTML table for the public page and shortcode.
	 *
	 * @param string $oznaka Outlet code, empty for the full catalog.
	 * @return string
	 */
	/** Separate product and service public filenames; their links remain stable across regenerations. */
	public function get_type_public_path( $kind, $format = 'xml', $oznaka = '' ) {
		$dir = $this->get_public_dir();
		if ( '' === $dir || ! in_array( $kind, array( 'proizvodi', 'usluge' ), true ) ) {
			return '';
		}
		$code = Brendigo_Sidrena_Cijena_Settings::instance()->normalize_oznaka( $oznaka );
		$filename = 'sidrena-cijena-' . $kind . ( $code ? '-' . $code : '' ) . '.' . $this->sanitize_ext( $format );
		return trailingslashit( $dir ) . $filename;
	}

	public function get_type_html_path( $kind, $oznaka = '' ) {
		$dir = $this->get_public_dir();
		$code = Brendigo_Sidrena_Cijena_Settings::instance()->normalize_oznaka( $oznaka );
		if ( '' === $dir || ! in_array( $kind, array( 'proizvodi', 'usluge' ), true ) ) {
			return '';
		}
		return trailingslashit( $dir ) . 'sidrena-cijena-' . $kind . ( $code ? '-' . $code : '' ) . '.html';
	}

	public function get_type_public_url( $kind, $format = 'xml', $oznaka = '' ) {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['baseurl'] ) ) {
			return '';
		}
		$path = $this->get_type_public_path( $kind, $format, $oznaka );
		if ( '' === $path ) {
			return '';
		}
		return trailingslashit( $uploads['baseurl'] ) . 'sidrena-cijena/' . rawurlencode( basename( $path ) );
	}

	public function get_cjenik_html_path( $oznaka = '' ) {
		$dir = $this->get_public_dir();
		if ( '' === $dir ) {
			return '';
		}

		$oznaka = Brendigo_Sidrena_Cijena_Settings::instance()->normalize_oznaka( $oznaka );
		$name   = '' === $oznaka ? 'sidrena-cijena-cjenik.html' : 'sidrena-cijena-cjenik-' . $oznaka . '.html';

		return trailingslashit( $dir ) . $name;
	}

	/**
	 * Public URL (pretty permalink, or query fallback).
	 *
	 * @param string $format xml|csv, empty = current setting.
	 * @return string
	 */
	public function get_public_url( $format = '', $oznaka = '' ) {
		$format     = '' === $format ? $this->export_format() : $this->sanitize_ext( $format );
		$oznaka     = Brendigo_Sidrena_Cijena_Settings::instance()->normalize_oznaka( $oznaka );
		$permalinks = get_option( 'permalink_structure' );

		if ( is_string( $permalinks ) && '' !== $permalinks ) {
			return home_url( '/' . $this->live_filename( $format, $oznaka ) );
		}

		$args = array(
			'sidrena_cijena_xml' => '1',
			'sidrena_cijena_ext' => $format,
		);
		if ( '' !== $oznaka ) {
			$args['sidrena_cijena_oznaka'] = $oznaka;
		}

		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Public archive index URL.
	 *
	 * @return string
	 */
	public function get_archive_url() {
		$permalinks = get_option( 'permalink_structure' );

		if ( is_string( $permalinks ) && '' !== $permalinks ) {
			return home_url( '/' . Brendigo_Sidrena_Cijena_Export::ARCHIVE_SLUG . '/' );
		}

		return add_query_arg( 'sidrena_cijena_arhiva', '1', home_url( '/' ) );
	}

	/**
	 * Public HTML pricelist URL. Empty oznaka is the webshop page.
	 *
	 * @param string $oznaka Outlet code, empty for the webshop page.
	 * @return string
	 */
	public function get_cjenik_url( $oznaka = '' ) {
		$oznaka     = Brendigo_Sidrena_Cijena_Settings::instance()->normalize_oznaka( $oznaka );
		$permalinks = get_option( 'permalink_structure' );

		if ( is_string( $permalinks ) && '' !== $permalinks ) {
			$path = '/' . Brendigo_Sidrena_Cijena_Export::CJENIK_SLUG . '/';
			if ( '' !== $oznaka ) {
				$path .= $oznaka . '/';
			}

			return home_url( $path );
		}

		$args = array(
			'sidrena_cijena_cjenik' => '1',
		);
		if ( '' !== $oznaka ) {
			$args['sidrena_cijena_oznaka'] = $oznaka;
		}

		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Public URL for one archived feed file.
	 *
	 * @param string $name_or_date Legal filename or legacy d-m-Y.
	 * @param string $ext          xml|csv (legacy date URLs).
	 * @return string
	 */
	public function get_archive_file_url( $name_or_date, $ext = 'xml' ) {
		$name = Brendigo_Sidrena_Cijena_Archive::instance()->sanitize_archive_name( $name_or_date );
		if ( '' === $name ) {
			$date = Brendigo_Sidrena_Cijena_Archive::instance()->sanitize_archive_date( $name_or_date );
			$ext  = $this->sanitize_ext( $ext );
			if ( '' === $date ) {
				return $this->get_archive_url();
			}
			$name = Brendigo_Sidrena_Cijena_Export::PUBLIC_STEM . '_' . $date . '.' . $ext;
		}

		$permalinks = get_option( 'permalink_structure' );
		$pretty     = is_string( $permalinks ) && '' !== $permalinks
			&& Brendigo_Sidrena_Cijena_Archive::instance()->archive_name_allows_pretty_path( $name );

		if ( $pretty ) {
			return home_url( '/' . Brendigo_Sidrena_Cijena_Export::ARCHIVE_SLUG . '/' . rawurlencode( $name ) );
		}

		return add_query_arg(
			array(
				'sidrena_cijena_arhiva_name' => $name,
			),
			home_url( '/' )
		);
	}

	/**
	 * Send XML download.
	 *
	 * @param string                             $filename     File name.
	 * @param array<int, array<string, string>>  $rows         Rows.
	 * @param bool                               $missing_only Audit export.
	 * @param bool                               $public       Client cjenik columns, same as the public feed.
	 */
	public function send_xml( $filename, $rows, $missing_only, $public = false ) {
		$this->headers( 'application/xml; charset=utf-8', $filename );
		echo $this->build_xml_string( $rows, $missing_only ? 'nedostaje' : 'cjenik', $public ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML download; values escaped with esc_xml() in xml_value().
		exit;
	}

	/**
	 * Build an XML document string.
	 *
	 * @param array<int, array<string, string>> $rows           Rows.
	 * @param string                            $root           Root element name.
	 * @param bool                              $public_fields  Include id and url.
	 * @return string
	 */
	public function build_xml_string( $rows, $root, $public_fields = false, $type = '' ) {
		$root = preg_replace( '/[^a-z0-9_\-]/i', '', $root );
		if ( '' === $root ) {
			$root = 'cjenik';
		}

		$keys = $this->public_column_keys( $rows, $type );
		$tag  = 'usluge' === $type || ( '' === $type && $this->feed_is_services_only( $rows ) ) ? 'usluga' : 'proizvod';

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<' . $root . ' generated="' . $this->xml_value( gmdate( 'c' ) ) . '" count="' . $this->xml_value( (string) count( $rows ) ) . '" shop="' . $this->xml_value( home_url( '/' ) ) . '">' . "\n";

		foreach ( $rows as $row ) {
			$row_tag = ( isset( $row['vrsta'] ) && 'usluga' === $row['vrsta'] ) ? 'usluga' : $tag;
			if ( ! $public_fields ) {
				$row_tag = 'proizvod';
			}
			$xml .= "\t<" . $row_tag . ">\n";
			if ( $public_fields ) {
				foreach ( $keys as $key ) {
					$xml .= "\t\t<" . $key . ">" . $this->xml_value( isset( $row[ $key ] ) ? $row[ $key ] : '' ) . "</" . $key . ">\n";
				}
			} else {
				$xml .= "\t\t<naziv>" . $this->xml_value( $row['naziv'] ) . "</naziv>\n";
				$xml .= "\t\t<sku>" . $this->xml_value( $row['sku'] ) . "</sku>\n";
				$xml .= "\t\t<cijena_redovna>" . $this->xml_value( $row['cijena_redovna'] ) . "</cijena_redovna>\n";
				$xml .= "\t\t<cijena_akcijska>" . $this->xml_value( $row['cijena_akcijska'] ) . "</cijena_akcijska>\n";
				$xml .= "\t\t<sidrena_cijena>" . $this->xml_value( $row['sidrena_cijena'] ) . "</sidrena_cijena>\n";
			}
			$xml .= "\t</" . $row_tag . ">\n";
		}

		$xml .= '</' . $root . '>';

		return $xml;
	}

	/**
	 * Write one live feed file.
	 *
	 * @param array<int, array<string, string>> $rows   Rows.
	 * @param string                            $oznaka Outlet code, empty for the single shop feed.
	 * @return array<string, mixed>|WP_Error
	 */
	public function write_feed_file( $rows, $oznaka = '' ) {
		$format    = $this->export_format();
		$feed_body = 'csv' === $format ? $this->build_csv_string( $rows ) : $this->build_xml_string( $rows, 'cjenik', true );
		$html_body = '<!-- sidrena-count:' . count( $rows ) . " -->\n" . $this->build_cjenik_embed_html( $rows, Brendigo_Sidrena_Cijena_Export::CJENIK_DOM_TOKEN, $oznaka );
		$dir       = $this->get_public_dir();

		if ( '' === $dir ) {
			return new WP_Error( 'sidrena_xml_uploads', __( 'Uploads mapa nije dostupna.', 'brendigo-sidrena-cijena' ) );
		}

		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'sidrena_xml_mkdir', __( 'Mapa za javni cjenik nije mogla biti stvorena.', 'brendigo-sidrena-cijena' ) );
		}

		$bundle = array(
			$this->get_public_path( $format, $oznaka ) => $feed_body,
			$this->get_cjenik_html_path( $oznaka )     => $html_body,
		);
		$types = array( 'proizvodi' => array(), 'usluge' => array() );
		foreach ( $rows as $row ) {
			$kind = isset( $row['vrsta'] ) && 'usluga' === $row['vrsta'] ? 'usluge' : 'proizvodi';
			$types[ $kind ][] = $row;
		}
		foreach ( $types as $kind => $type_rows ) {
			foreach ( array( 'xml', 'csv' ) as $extension ) {
				$path = $this->get_type_public_path( $kind, $extension, $oznaka );
				$bundle[ $path ] = 'csv' === $extension
					? $this->build_csv_string( $type_rows, $kind )
					: $this->build_xml_string( $type_rows, 'cjenik', true, $kind );
			}
			$bundle[ $this->get_type_html_path( $kind, $oznaka ) ] = '<!-- sidrena-count:' . count( $type_rows ) . " -->\n" . $this->build_cjenik_embed_html( $type_rows, Brendigo_Sidrena_Cijena_Export::CJENIK_DOM_TOKEN, $oznaka );
		}
		$published = $this->atomic_write_bundle(
			$bundle,
			'sidrena_public_write',
			__( 'Javni cjenik nije mogao biti spremljen.', 'brendigo-sidrena-cijena' )
		);

		if ( is_wp_error( $published ) ) {
			return $published;
		}

		$feed_path = $this->get_public_path( $format, $oznaka );
		$html_path = $this->get_cjenik_html_path( $oznaka );

		return array(
			'generated'   => time(),
			'count'       => count( $rows ),
			'bytes'       => isset( $published[ $feed_path ] ) ? (int) $published[ $feed_path ] : strlen( $feed_body ),
			'html_bytes'  => isset( $published[ $html_path ] ) ? (int) $published[ $html_path ] : strlen( $html_body ),
			'sha256'      => hash( 'sha256', $feed_body ),
			'html_sha256' => hash( 'sha256', $html_body ),
			'format'      => $format,
		);
	}

	/**
	 * Publish multiple generated files as one coordinated bundle.
	 *
	 * Every body is staged before any live file is replaced. Existing live files
	 * are copied to temporary backups and restored if a later replacement fails,
	 * so the HTML page and downloadable XML/CSV cannot remain on different
	 * publication versions after an I/O error.
	 *
	 * @param array<string,string> $files         Destination path => body.
	 * @param string               $error_code    WP_Error code.
	 * @param string               $error_message Safe error message.
	 * @return array<string,int>|WP_Error Bytes written by destination path.
	 */
	private function atomic_write_bundle( $files, $error_code, $error_message ) {
		if ( empty( $files ) ) {
			return array();
		}

		$wp_filesystem = Brendigo_Sidrena_Cijena_Filesystem::get();
		if ( ! $wp_filesystem ) {
			return new WP_Error( $error_code, $error_message );
		}

		$token   = wp_generate_password( 14, false, false );
		$mode    = defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : false;
		$staged  = array();
		$backups = array();
		$bytes   = array();

		foreach ( $files as $path => $body ) {
			$path = (string) $path;
			$body = (string) $body;
			$dir  = dirname( $path );
			if ( ! $wp_filesystem->is_dir( $dir ) || ! $wp_filesystem->is_writable( $dir ) ) {
				$this->cleanup_bundle_files( $wp_filesystem, $staged, $backups );
				return new WP_Error( $error_code, $error_message );
			}

			$tmp = trailingslashit( $dir ) . '.' . basename( $path ) . '.' . $token . '.tmp';
			if ( ! $wp_filesystem->put_contents( $tmp, $body, $mode ) ) {
				$this->cleanup_bundle_files( $wp_filesystem, $staged, $backups );
				return new WP_Error( $error_code, $error_message );
			}
			$staged[ $path ] = $tmp;
			$bytes[ $path ]  = strlen( $body );
		}

		foreach ( array_keys( $files ) as $path ) {
			$path = (string) $path;
			if ( ! $wp_filesystem->exists( $path ) ) {
				$backups[ $path ] = '';
				continue;
			}

			$backup = dirname( $path ) . '/.' . basename( $path ) . '.' . $token . '.bak';
			if ( ! $wp_filesystem->copy( $path, $backup, true, $mode ) ) {
				$this->cleanup_bundle_files( $wp_filesystem, $staged, $backups );
				return new WP_Error( $error_code, $error_message );
			}
			$backups[ $path ] = $backup;
		}

		$committed = array();
		foreach ( $staged as $path => $tmp ) {
			if ( ! $wp_filesystem->move( $tmp, $path, true ) ) {
				$restore = array_values( array_unique( array_merge( $committed, array( $path ) ) ) );
				foreach ( array_reverse( $restore ) as $restore_path ) {
					$backup = isset( $backups[ $restore_path ] ) ? $backups[ $restore_path ] : '';
					if ( '' !== $backup && $wp_filesystem->exists( $backup ) ) {
						$wp_filesystem->copy( $backup, $restore_path, true, $mode );
					} elseif ( in_array( $restore_path, $committed, true ) ) {
						$wp_filesystem->delete( $restore_path, false, 'f' );
					}
				}
				$this->cleanup_bundle_files( $wp_filesystem, $staged, $backups );
				return new WP_Error( $error_code, $error_message );
			}
			$committed[] = $path;
		}

		$this->cleanup_bundle_files( $wp_filesystem, array(), $backups );
		return $bytes;
	}

	/**
	 * Remove staged/backup files left by a coordinated publication attempt.
	 *
	 * @param WP_Filesystem_Base      $filesystem WordPress filesystem instance.
	 * @param array<string,string>    $staged     Destination => staged path.
	 * @param array<string,string>    $backups    Destination => backup path.
	 */
	private function cleanup_bundle_files( $filesystem, $staged, $backups ) {
		foreach ( array_merge( array_values( $staged ), array_values( $backups ) ) as $path ) {
			if ( '' !== (string) $path && $filesystem->exists( $path ) ) {
				$filesystem->delete( $path, false, 'f' );
			}
		}
	}

	/**
	 * Remove orphaned publication stage/backup files left after a terminated PHP request.
	 * Only plugin-owned hidden files in the public-feed directory are considered.
	 *
	 * @param int $older_than Age threshold in seconds.
	 * @return int Number of files removed.
	 */
	public function cleanup_stale_publish_files( $older_than = HOUR_IN_SECONDS ) {
		$dir = $this->get_public_dir();
		if ( '' === $dir ) {
			return 0;
		}

		$wp_filesystem = Brendigo_Sidrena_Cijena_Filesystem::get();
		if ( ! $wp_filesystem || ! $wp_filesystem->is_dir( $dir ) ) {
			return 0;
		}

		$list = $wp_filesystem->dirlist( $dir, false, false );
		if ( ! is_array( $list ) ) {
			return 0;
		}

		$cutoff  = time() - max( 300, absint( $older_than ) );
		$removed = 0;
		foreach ( $list as $name => $info ) {
			$name = (string) $name;
			if ( ! preg_match( '/^\.(sidrena-cijena|sidrena-cjenik)[A-Za-z0-9._-]*\.(?:tmp|bak)$/', $name ) ) {
				continue;
			}

			$modified = isset( $info['lastmodunix'] ) ? (int) $info['lastmodunix'] : 0;
			if ( $modified < 1 || $modified > $cutoff ) {
				continue;
			}

			$path = trailingslashit( $dir ) . $name;
			if ( $wp_filesystem->delete( $path, false, 'f' ) ) {
				++$removed;
			}
		}

		return $removed;
	}

	/**
	 * HTML table markup. The DOM id is a token in the stored file and a real id when printed.
	 *
	 * @param array<int, array<string, string>> $rows   Catalog rows.
	 * @param string                            $dom_id Element id.
	 * @param string                            $oznaka Outlet code, empty for the webshop.
	 * @return string
	 */
	public function build_cjenik_embed_html( $rows, $dom_id, $oznaka = '' ) {
		$catalog = Brendigo_Sidrena_Cijena_Catalog::instance();
		$upit    = Brendigo_Sidrena_Cijena_Upit::instance()->for_oznaka( $oznaka );
		$html    = '<div class="sidrena-cjenik" id="' . esc_attr( $dom_id ) . '">';

		if ( array() === $rows && array() === $upit ) {
			$html .= '<p class="empty">' . esc_html__( 'Cjenik je prazan.', 'brendigo-sidrena-cijena' ) . '</p>';
			$html .= '</div>';
			return $html;
		}

		$html .= '<div class="cjenik-toolbar">';
		$html .= '<label class="cjenik-search"><span class="screen-reader-text">' . esc_html__( 'Pretraži cjenik', 'brendigo-sidrena-cijena' ) . '</span>';
		$html .= '<input type="search" class="cjenik-filter" placeholder="' . esc_attr__( 'Pretraži naziv, SKU, marku ili barkod…', 'brendigo-sidrena-cijena' ) . '" /></label>';
		$html .= '</div>';

		if ( array() === $rows ) {
			$html .= $this->build_upit_section( $upit );
			$html .= '<p class="cjenik-empty-filter" hidden>' . esc_html__( 'Nema proizvoda koji odgovaraju pretrazi.', 'brendigo-sidrena-cijena' ) . '</p>';
			$html .= '</div>';
			return $html;
		}

		$columns = $this->cjenik_visible_columns( $rows );

		$html .= '<div class="cjenik-table-wrap"><table class="cjenik-table">';
		$html .= '<thead><tr>';
		foreach ( $columns as $column ) {
			$html .= '<th>' . esc_html( $column['label'] ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';

		$row_index = 0;
		foreach ( $rows as $row ) {
			$naziv       = isset( $row['naziv'] ) ? (string) $row['naziv'] : '';
			$sku         = isset( $row['sku'] ) ? (string) $row['sku'] : '';
			$marka       = isset( $row['marka'] ) ? trim( (string) $row['marka'] ) : '';
			$url         = isset( $row['url'] ) ? (string) $row['url'] : '';
			$barkod      = isset( $row['barkod'] ) ? trim( (string) $row['barkod'] ) : '';
			$jedinica    = isset( $row['jedinica_mjere'] ) ? trim( (string) $row['jedinica_mjere'] ) : '';
			$oblik       = isset( $row['naziv_posebnog_oblika'] ) ? trim( (string) $row['naziv_posebnog_oblika'] ) : '';
			$vrsta       = ( isset( $row['vrsta'] ) && 'usluga' === $row['vrsta'] ) ? 'usluga' : 'proizvod';
			$vrsta_label = 'usluga' === $vrsta ? __( 'Usluga', 'brendigo-sidrena-cijena' ) : __( 'Proizvod', 'brendigo-sidrena-cijena' );
			$datum       = isset( $row['sidrena_datum'] ) ? trim( (string) $row['sidrena_datum'] ) : '';
			$dostupnost  = ( isset( $row['dostupnost'] ) && 'nedostupno' === $row['dostupnost'] ) ? 'nedostupno' : 'dostupno';
			$stock_label = 'nedostupno' === $dostupnost
				? __( 'Nedostupno', 'brendigo-sidrena-cijena' )
				: __( 'Dostupno', 'brendigo-sidrena-cijena' );
			$search_src  = $naziv . ' ' . $sku . ' ' . $marka . ' ' . $barkod . ' ' . $jedinica . ' ' . $oblik . ' ' . $vrsta_label . ' ' . $stock_label;
			$search      = function_exists( 'mb_strtolower' ) ? mb_strtolower( $search_src, 'UTF-8' ) : strtolower( $search_src );

			$naziv_html = '' !== $url
				? '<a href="' . esc_url( $url ) . '">' . esc_html( $naziv ) . '</a>'
				: esc_html( $naziv );

			$cells = array(
				'naziv'                 => $naziv_html,
				'vrsta'                 => esc_html( $vrsta_label ),
				'sku'                   => esc_html( $sku ),
				'marka'                 => esc_html( '' !== $marka ? $marka : '-' ),
				'barkod'                => esc_html( '' !== $barkod ? $barkod : '-' ),
				'jedinica_mjere'        => esc_html( '' !== $jedinica ? $jedinica : '-' ),
				'cijena_jedinice_mjere' => esc_html( $catalog->format_amount( isset( $row['cijena_jedinice_mjere'] ) ? $row['cijena_jedinice_mjere'] : '' ) ),
				'maloprodajna_cijena'   => esc_html( $catalog->format_amount( isset( $row['maloprodajna_cijena'] ) ? $row['maloprodajna_cijena'] : '' ) ),
				'naziv_posebnog_oblika' => esc_html( '' !== $oblik ? $oblik : '-' ),
				'sidrena_cijena'        => esc_html( $catalog->format_amount( isset( $row['sidrena_cijena'] ) ? $row['sidrena_cijena'] : '' ) ),
				'sidrena_datum'         => esc_html( '' !== $datum ? $datum : '-' ),
				'dostupnost'            => '<span class="cjenik-stock is-' . esc_attr( $dostupnost ) . '">' . esc_html( $stock_label ) . '</span>',
			);

			$hidden = $row_index >= self::CJENIK_PAGE_SIZE ? ' hidden' : '';
			++$row_index;

			$html .= '<tr data-search="' . esc_attr( $search ) . '"' . $hidden . '>';
			foreach ( $columns as $key => $column ) {
				$html .= '<td data-label="' . esc_attr( $column['label'] ) . '">' . $cells[ $key ] . '</td>';
			}
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		$html .= '<nav class="cjenik-pager" data-page-size="' . (int) self::CJENIK_PAGE_SIZE . '" hidden aria-label="' . esc_attr__( 'Stranice cjenika', 'brendigo-sidrena-cijena' ) . '"></nav>';
		$html .= $this->build_upit_section( $upit );
		$html .= '<p class="cjenik-empty-filter" hidden>' . esc_html__( 'Nema proizvoda koji odgovaraju pretrazi.', 'brendigo-sidrena-cijena' ) . '</p>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * "Cijena na upit" table for one outlet. Empty when that outlet has no such rows.
	 *
	 * @param array<int, array<string, string>> $rows Upit rows.
	 * @return string
	 */
	private function build_upit_section( $rows ) {
		if ( array() === $rows ) {
			return '';
		}

		$label_naziv     = __( 'Naziv', 'brendigo-sidrena-cijena' );
		$label_kriteriji = __( 'Kriteriji', 'brendigo-sidrena-cijena' );
		$label_cijena    = __( 'Cijena', 'brendigo-sidrena-cijena' );
		$cijena          = __( 'Cijena na upit', 'brendigo-sidrena-cijena' );

		$html  = '<h2 class="cjenik-upit-title">' . esc_html__( 'Cijena na upit', 'brendigo-sidrena-cijena' ) . '</h2>';
		$html .= '<div class="cjenik-table-wrap"><table class="cjenik-upit">';
		$html .= '<thead><tr>';
		$html .= '<th>' . esc_html( $label_naziv ) . '</th>';
		$html .= '<th>' . esc_html( $label_kriteriji ) . '</th>';
		$html .= '<th>' . esc_html( $label_cijena ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$naziv     = isset( $row['naziv'] ) ? (string) $row['naziv'] : '';
			$kriteriji = isset( $row['kriteriji'] ) ? (string) $row['kriteriji'] : '';
			$search    = $naziv . ' ' . $kriteriji . ' ' . $cijena;
			$search    = function_exists( 'mb_strtolower' ) ? mb_strtolower( $search, 'UTF-8' ) : strtolower( $search );

			$html .= '<tr data-search="' . esc_attr( $search ) . '">';
			$html .= '<td data-label="' . esc_attr( $label_naziv ) . '">' . esc_html( $naziv ) . '</td>';
			$html .= '<td data-label="' . esc_attr( $label_kriteriji ) . '">' . esc_html( $kriteriji ) . '</td>';
			$html .= '<td data-label="' . esc_attr( $label_cijena ) . '">' . esc_html( $cijena ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';

		return $html;
	}

	/**
	 * HTML table columns that have at least one value. Name and prices stay.
	 *
	 * @param array<int, array<string, string>> $rows Rows.
	 * @return array<string, array{label:string}>
	 */
	public function cjenik_visible_columns( $rows ) {
		$all = array(
			'naziv'                 => array( 'label' => __( 'Naziv', 'brendigo-sidrena-cijena' ), 'always' => true ),
			'vrsta'                 => array( 'label' => __( 'Vrsta', 'brendigo-sidrena-cijena' ), 'always' => false ),
			'sku'                   => array( 'label' => __( 'SKU', 'brendigo-sidrena-cijena' ), 'always' => false ),
			'marka'                 => array( 'label' => __( 'Marka', 'brendigo-sidrena-cijena' ), 'always' => false ),
			'barkod'                => array( 'label' => __( 'Barkod', 'brendigo-sidrena-cijena' ), 'always' => false ),
			'jedinica_mjere'        => array( 'label' => __( 'Jedinica mjere', 'brendigo-sidrena-cijena' ), 'always' => false ),
			'cijena_jedinice_mjere' => array( 'label' => __( 'Cijena za jedinicu mjere', 'brendigo-sidrena-cijena' ), 'always' => false ),
			'maloprodajna_cijena'   => array( 'label' => __( 'Maloprodajna cijena', 'brendigo-sidrena-cijena' ), 'always' => true ),
			'naziv_posebnog_oblika' => array( 'label' => __( 'Poseban oblik prodaje', 'brendigo-sidrena-cijena' ), 'always' => false ),
			'sidrena_cijena'        => array( 'label' => __( 'Sidrena cijena', 'brendigo-sidrena-cijena' ), 'always' => true ),
			'sidrena_datum'         => array( 'label' => __( 'Datum sidrene', 'brendigo-sidrena-cijena' ), 'always' => false ),
			'dostupnost'            => array( 'label' => __( 'Dostupnost', 'brendigo-sidrena-cijena' ), 'always' => false ),
		);

		$filled = array();
		foreach ( $rows as $row ) {
			foreach ( $all as $key => $column ) {
				if ( isset( $filled[ $key ] ) ) {
					continue;
				}
				$value = isset( $row[ $key ] ) ? trim( (string) $row[ $key ] ) : '';
				if ( '' !== $value && '-' !== $value ) {
					$filled[ $key ] = true;
				}
			}
		}

		$out = array();
		foreach ( $all as $key => $column ) {
			if ( ! empty( $column['always'] ) || isset( $filled[ $key ] ) ) {
				$out[ $key ] = array( 'label' => $column['label'] );
			}
		}

		return $out;
	}

	/**
	 * Generated HTML table, or null when the file is not there yet.
	 *
	 * @param string $oznaka Outlet code, empty for the full catalog.
	 * @return array{html:string,count:int}|null
	 */
	public function read_cjenik_snapshot( $oznaka ) {
		$path = $this->get_cjenik_html_path( $oznaka );
		if ( '' === $path ) {
			return null;
		}

		$wp_filesystem = Brendigo_Sidrena_Cijena_Filesystem::get();
		if ( ! $wp_filesystem || ! $wp_filesystem->exists( $path ) || ! $wp_filesystem->is_readable( $path ) ) {
			return null;
		}

		$html = $wp_filesystem->get_contents( $path );
		if ( ! is_string( $html ) || '' === $html ) {
			return null;
		}

		// Versions before 1.2 stored the table filter as inline JavaScript.
		// Remove that legacy block; the external cjenik.js handles all instances now.
		$html = preg_replace( '#<script>.*?</script>#is', '', $html );
		if ( ! is_string( $html ) ) {
			return null;
		}

		$count = 0;
		if ( preg_match( '/<!-- sidrena-count:(\d+) -->/', $html, $match ) ) {
			$count = (int) $match[1];
		}

		return array(
			'html'  => $html,
			'count' => $count,
		);
	}

	/**
	 * Table HTML from the generated file. Unknown or empty outlets stay empty and do not queue a rebuild.
	 *
	 * @param string $oznaka Outlet code.
	 * @param string $dom_id Element id for this print.
	 * @return string
	 */
	public function cjenik_snapshot_embed( $oznaka, $dom_id ) {
		$oznaka = Brendigo_Sidrena_Cijena_Settings::instance()->normalize_oznaka( $oznaka );
		if ( '' !== $oznaka ) {
			$outlet = Brendigo_Sidrena_Cijena_Poslovnice::instance()->get_by_oznaka( $oznaka );
			if ( ! is_array( $outlet ) ) {
				return '<div class="sidrena-cjenik"><p class="empty">' . esc_html__( 'Cjenik je prazan.', 'brendigo-sidrena-cijena' ) . '</p></div>';
			}
		}

		$snapshot = $this->read_cjenik_snapshot( $oznaka );
		if ( null === $snapshot ) {
			Brendigo_Sidrena_Cijena_Feed_Routes::instance()->schedule_public_rebuild();
			return '<p class="sidrena-cjenik-pending">' . esc_html__( 'Cjenik se priprema. Osvježite stranicu za nekoliko minuta.', 'brendigo-sidrena-cijena' ) . '</p>';
		}

		return str_replace( Brendigo_Sidrena_Cijena_Export::CJENIK_DOM_TOKEN, $dom_id, $snapshot['html'] );
	}

	/**
	 * Searchable pricelist table for the public page and the [sidrena-cjenik] shortcode.
	 *
	 * Reads the file written with the public feed. A missing file queues one rebuild.
	 *
	 * @param string                                  $oznaka Outlet code, empty for the full list.
	 * @param array<int, array<string, string>>|null $rows   Already built rows. Public calls leave this null.
	 * @return string
	 */
	public function get_cjenik_embed_html( $oznaka = '', $rows = null ) {
		static $instance = 0;

		++$instance;
		$dom_id = 'sidrena-cjenik-' . $instance;

		if ( is_array( $rows ) ) {
			return $this->build_cjenik_embed_html( $rows, $dom_id, $oznaka );
		}

		return $this->cjenik_snapshot_embed( $oznaka, $dom_id );
	}

	/**
	 * Escape an XML text node.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public function xml_value( $value ) {
		return esc_xml( (string) $value );
	}

	/**
	 * Download headers.
	 *
	 * @param string $content_type MIME type.
	 * @param string $filename     File name.
	 */
	public function headers( $content_type, $filename ) {
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		$download = str_replace( array( '"', "\r", "\n" ), '', (string) $filename );
		if ( '' === $download ) {
			$download = 'sidrena-cjenik';
		}

		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename="' . $download . '"; filename*=UTF-8\'\'' . rawurlencode( $download ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
	}

	/**
	 * Stream a public file with type-specific headers.
	 *
	 * @param string $path     Absolute path.
	 * @param string $filename Download name.
	 * @param string $ext      xml|csv.
	 */
	public function send_public_file( $path, $filename, $ext ) {
		$ext = $this->sanitize_ext( $ext );
		if ( 'csv' === $ext ) {
			header( 'Content-Type: text/csv; charset=utf-8' );
		} else {
			header( 'Content-Type: application/xml; charset=utf-8' );
		}

		$download = str_replace( array( '"', "\r", "\n" ), '', $filename );
		header( 'Content-Disposition: inline; filename="' . $download . '"; filename*=UTF-8\'\'' . rawurlencode( $filename ) );
		header( 'X-Robots-Tag: noindex, follow' );
		header( 'Cache-Control: public, max-age=300, stale-while-revalidate=60' );
		header( 'X-Content-Type-Options: nosniff' );

		$mtime = Brendigo_Sidrena_Cijena_Filesystem::mtime( $path );
		$bytes = Brendigo_Sidrena_Cijena_Filesystem::size( $path );
		if ( $mtime > 0 ) {
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $mtime ) . ' GMT' );
		}
		if ( $bytes > 0 ) {
			header( 'Content-Length: ' . $bytes );
		}

		$etag = '"' . md5( basename( $path ) . ':' . (string) $mtime . ':' . (string) $bytes ) . '"';
		header( 'ETag: ' . $etag );

		$if_none_match = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';
		$if_modified    = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) : '';
		$not_modified   = '' !== $if_none_match && trim( $if_none_match ) === $etag;
		if ( ! $not_modified && $mtime > 0 && '' !== $if_modified ) {
			$client_time = strtotime( $if_modified );
			$not_modified = false !== $client_time && $client_time >= (int) $mtime;
		}

		if ( $not_modified ) {
			status_header( 304 );
			exit;
		}

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'HEAD' === $request_method ) {
			exit;
		}

		$contents = Brendigo_Sidrena_Cijena_Filesystem::get_contents( $path );
		if ( false === $contents ) {
			status_header( 500 );
			exit;
		}

		echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated XML/CSV is served verbatim with its declared content type.
		exit;
	}
}
