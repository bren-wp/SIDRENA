<?php
/**
 * CSV and XML readers for bulk import.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turn an uploaded file body into field names and rows.
 */
class Brendigo_Sidrena_Cijena_Import_Parse {

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
	 * Parse CSV contents.
	 *
	 * @param string $contents File body.
	 * @return array{fields: array<int, string>, rows: array<int, array<string, string>>}|WP_Error
	 */
	public function parse_csv( $contents ) {
		$contents = (string) preg_replace( '/^\xEF\xBB\xBF/', '', (string) $contents );
		$records  = $this->csv_records( $contents );
		if ( empty( $records ) ) {
			return new WP_Error( 'csv', __( 'CSV nije čitljiv.', 'brendigo-sidrena-cijena' ) );
		}

		$delimiter    = $this->detect_delimiter( $records[0] );
		$header_cells = str_getcsv( $records[0], $delimiter, '"', '' );
		if ( ! is_array( $header_cells ) || $this->csv_row_is_empty( $header_cells ) ) {
			return new WP_Error( 'csv', __( 'CSV mora imati zaglavlje i barem jedan red.', 'brendigo-sidrena-cijena' ) );
		}

		$headers = $this->unique_fields( $header_cells );
		if ( count( $headers ) > Brendigo_Sidrena_Cijena_Bulk_Import::MAX_FIELDS ) {
			/* translators: %d: maximum number of CSV columns */
			return new WP_Error( 'csv_fields', sprintf( __( 'CSV ima previše stupaca. Dopušteno je najviše %d.', 'brendigo-sidrena-cijena' ), Brendigo_Sidrena_Cijena_Bulk_Import::MAX_FIELDS ) );
		}

		$rows = array();
		foreach ( array_slice( $records, 1 ) as $record ) {
			$cells = str_getcsv( $record, $delimiter, '"', '' );
			if ( ! is_array( $cells ) || $this->csv_row_is_empty( $cells ) ) {
				continue;
			}

			if ( count( $rows ) >= Brendigo_Sidrena_Cijena_Bulk_Import::MAX_ROWS ) {
				/* translators: %d: maximum number of CSV data rows */
				return new WP_Error( 'csv_rows', sprintf( __( 'CSV ima više od %d redova. Podijelite datoteku u manje cjeline kako nijedan red ne bi bio preskočen.', 'brendigo-sidrena-cijena' ), Brendigo_Sidrena_Cijena_Bulk_Import::MAX_ROWS ) );
			}

			if ( count( $cells ) > count( $headers ) ) {
				$extra = array_slice( $cells, count( $headers ) );
				$extra = array_filter(
					$extra,
					static function ( $value ) {
						return '' !== trim( (string) $value );
					}
				);
				if ( ! empty( $extra ) ) {
					return new WP_Error( 'csv_columns', __( 'Jedan ili više CSV redova ima više vrijednosti nego zaglavlje. Provjerite navodnike i razdjelnik.', 'brendigo-sidrena-cijena' ) );
				}
			}

			$row = array();
			foreach ( $headers as $index => $field ) {
				$row[ $field ] = isset( $cells[ $index ] ) ? trim( (string) $cells[ $index ] ) : '';
			}
			$rows[] = $row;
		}

		if ( empty( $rows ) ) {
			return new WP_Error( 'csv', __( 'CSV nema redova za uvoz.', 'brendigo-sidrena-cijena' ) );
		}

		return array(
			'fields' => $headers,
			'rows'   => $rows,
		);
	}

	/**
	 * Split CSV text into logical records without breaking quoted line breaks.
	 *
	 * @param string $contents CSV body.
	 * @return array<int,string>
	 */
	private function csv_records( $contents ) {
		$records   = array();
		$record    = '';
		$in_quotes = false;
		$length    = strlen( (string) $contents );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $contents[ $i ];
			if ( '"' === $char ) {
				if ( $in_quotes && isset( $contents[ $i + 1 ] ) && '"' === $contents[ $i + 1 ] ) {
					$record .= '""';
					++$i;
					continue;
				}
				$in_quotes = ! $in_quotes;
				$record   .= $char;
				continue;
			}

			if ( ! $in_quotes && ( "\n" === $char || "\r" === $char ) ) {
				if ( '' !== trim( $record ) ) {
					$records[] = $record;
				}
				$record = '';
				if ( "\r" === $char && isset( $contents[ $i + 1 ] ) && "\n" === $contents[ $i + 1 ] ) {
					++$i;
				}
				continue;
			}

			$record .= $char;
		}

		if ( '' !== trim( $record ) ) {
			$records[] = $record;
		}

		return $records;
	}

	/**
	 * Parse XML contents into flat rows of first-level child nodes.
	 *
	 * @param string $contents File body.
	 * @return array{fields: array<int, string>, rows: array<int, array<string, string>>}|WP_Error
	 */
	public function parse_xml( $contents ) {
		if ( ! function_exists( 'simplexml_load_string' ) ) {
			return new WP_Error( 'xml', __( 'PHP SimpleXML nije dostupan na poslužitelju.', 'brendigo-sidrena-cijena' ) );
		}

		if ( preg_match( '/<!DOCTYPE|<!ENTITY/i', (string) $contents ) ) {
			return new WP_Error( 'xml', __( 'XML s DTD ili ENTITY deklaracijama nije dopušten.', 'brendigo-sidrena-cijena' ) );
		}

		$previous = libxml_use_internal_errors( true );
		$xml      = simplexml_load_string( $contents, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( false === $xml ) {
			return new WP_Error( 'xml', __( 'XML nije valjan.', 'brendigo-sidrena-cijena' ) );
		}

		$items = $this->find_xml_items( $xml );
		if ( empty( $items ) ) {
			return new WP_Error( 'xml', __( 'U XML-u nisu pronađeni ponavljajući zapisi proizvoda.', 'brendigo-sidrena-cijena' ) );
		}

		if ( count( $items ) > Brendigo_Sidrena_Cijena_Bulk_Import::MAX_ROWS ) {
			/* translators: %d: maximum number of XML records */
			return new WP_Error( 'xml_rows', sprintf( __( 'XML ima više od %d zapisa. Podijelite datoteku u manje cjeline kako nijedan zapis ne bi bio preskočen.', 'brendigo-sidrena-cijena' ), Brendigo_Sidrena_Cijena_Bulk_Import::MAX_ROWS ) );
		}

		$fields = array();
		$rows   = array();

		foreach ( $items as $item ) {
			$row = array();
			foreach ( $item->children() as $child ) {
				$name = $child->getName();
				if ( '' === $name ) {
					continue;
				}
				$fields[ $name ] = true;
				$row[ $name ]    = trim( (string) $child );
			}

			if ( ! empty( $row ) ) {
				$rows[] = $row;
			}
		}

		$fields = array_keys( $fields );
		if ( count( $fields ) > Brendigo_Sidrena_Cijena_Bulk_Import::MAX_FIELDS ) {
			/* translators: %d: maximum number of XML fields */
			return new WP_Error( 'xml_fields', sprintf( __( 'XML ima previše polja po zapisu. Dopušteno je najviše %d.', 'brendigo-sidrena-cijena' ), Brendigo_Sidrena_Cijena_Bulk_Import::MAX_FIELDS ) );
		}

		if ( empty( $fields ) || empty( $rows ) ) {
			return new WP_Error( 'xml', __( 'XML nema čvorova koje je moguće mapirati.', 'brendigo-sidrena-cijena' ) );
		}

		foreach ( $rows as &$row ) {
			foreach ( $fields as $field ) {
				if ( ! isset( $row[ $field ] ) ) {
					$row[ $field ] = '';
				}
			}
		}
		unset( $row );

		return array(
			'fields' => $fields,
			'rows'   => $rows,
		);
	}

	/**
	 * Find repeating product-like nodes.
	 *
	 * @param SimpleXMLElement $xml Root.
	 * @return array<int, SimpleXMLElement>
	 */
	public function find_xml_items( $xml ) {
		$children = array();
		foreach ( $xml->children() as $child ) {
			$children[] = $child;
		}

		if ( empty( $children ) ) {
			return array();
		}

		$names = array();
		foreach ( $children as $child ) {
			$name = $child->getName();
			$names[ $name ] = isset( $names[ $name ] ) ? $names[ $name ] + 1 : 1;
		}

		if ( 1 === count( $names ) && reset( $names ) > 1 ) {
			return $children;
		}

		if ( 1 === count( $children ) ) {
			return $this->find_xml_items( $children[0] );
		}

		arsort( $names );
		$top = array_key_first( $names );
		if ( $top && $names[ $top ] > 1 ) {
			$matched = array();
			foreach ( $children as $child ) {
				if ( $child->getName() === $top ) {
					$matched[] = $child;
				}
			}
			return $matched;
		}

		return $children;
	}

	/**
	 * Unique, non-empty CSV headers.
	 *
	 * @param array<int, string> $headers Raw headers.
	 * @return array<int, string>
	 */
	public function unique_fields( $headers ) {
		$used   = array();
		$fields = array();

		foreach ( $headers as $index => $header ) {
			$name = trim( (string) $header );
			if ( '' === $name ) {
				$name = 'stupac_' . ( $index + 1 );
			}

			$base = $name;
			$n    = 2;
			while ( isset( $used[ $name ] ) ) {
				$name = $base . '_' . $n;
				++$n;
			}

			$used[ $name ] = true;
			$fields[]      = $name;
		}

		return $fields;
	}

	/**
	 * Detect CSV delimiter from the header line.
	 *
	 * @param string $header First line.
	 * @return string
	 */
	public function detect_delimiter( $header ) {
		$best       = ';';
		$best_count = 1;

		foreach ( array( ';', ',', "\t" ) as $candidate ) {
			$cells = str_getcsv( (string) $header, $candidate, '"', '' );
			$count = is_array( $cells ) ? count( $cells ) : 1;
			if ( $count > $best_count ) {
				$best       = $candidate;
				$best_count = $count;
			}
		}

		return $best;
	}


	/**
	 * Whether a parsed CSV record contains no user data.
	 *
	 * @param array<int, mixed> $cells Parsed cells.
	 * @return bool
	 */
	private function csv_row_is_empty( $cells ) {
		foreach ( (array) $cells as $cell ) {
			if ( '' !== trim( (string) $cell ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Guess the SKU column name.
	 *
	 * @param array<int, string> $fields Field names.
	 * @return string
	 */
	public function guess_sku_field( $fields ) {
		$needles = array( 'sku', 'sifra', 'šifra', 'sifra_artikla', 'article', 'artikl', 'product_sku', 'productsku' );

		foreach ( $fields as $field ) {
			$norm = strtolower( remove_accents( (string) $field ) );
			$norm = str_replace( array( ' ', '-', '.' ), '_', $norm );
			if ( in_array( $norm, $needles, true ) ) {
				return $field;
			}
		}

		return '';
	}

	/**
	 * Guess a named column from common aliases.
	 *
	 * @param array<int, string>    $fields  Field names.
	 * @param array<int, string>    $needles Normalized aliases.
	 * @return string
	 */
	public function guess_named_field( $fields, $needles ) {
		foreach ( $fields as $field ) {
			$norm = strtolower( remove_accents( (string) $field ) );
			$norm = str_replace( array( ' ', '-', '.' ), '_', $norm );
			if ( in_array( $norm, $needles, true ) ) {
				return $field;
			}
		}

		return '';
	}

	/**
	 * Convert an imported file to UTF-8.
	 *
	 * Valid UTF-8 is kept. Anything else is tried as ISO-8859-2, then CP1250.
	 * mbstring has no CP1250, so conversion goes through strict iconv.
	 *
	 * @param string $contents Raw bytes.
	 * @return string
	 */
	public function normalize_encoding( $contents ) {
		if ( function_exists( 'wp_is_valid_utf8' ) ) {
			$is_utf8 = wp_is_valid_utf8( $contents );
		} elseif ( function_exists( 'mb_check_encoding' ) ) {
			$is_utf8 = mb_check_encoding( $contents, 'UTF-8' );
		} else {
			$is_utf8 = false !== preg_match( '//u', $contents );
		}

		if ( $is_utf8 ) {
			return $contents;
		}

		if ( ! function_exists( 'iconv' ) ) {
			return $contents;
		}

		// 0x80-0x9F is undefined in ISO-8859-2 and holds š/ž in CP1250.
		$encodings = preg_match( '/[\x80-\x9F]/', $contents )
			? array( 'CP1250' )
			: array( 'ISO-8859-2', 'CP1250' );

		foreach ( $encodings as $encoding ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- An iconv notice would corrupt the AJAX JSON body.
			$converted = @iconv( $encoding, 'UTF-8', $contents );
			if ( is_string( $converted ) ) {
				return $converted;
			}
		}

		return $contents;
	}
}
