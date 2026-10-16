<?php
/** Minimal, bounded XLSX reader/writer without third-party dependencies. */
defined( 'ABSPATH' ) || exit;

final class Brendigo_Sidrena_Cijena_XLSX {
	private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

	/** Securely read the first worksheet in the standard XLSX location. */
	public static function parse( $path ) {
		if ( ! class_exists( 'ZipArchive' ) || ! function_exists( 'simplexml_load_string' ) ) {
			return new WP_Error( 'xlsx_extension', __( 'XLSX zahtijeva PHP proširenja ZIP i SimpleXML. Možete koristiti CSV.', 'brendigo-sidrena-cijena' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::RDONLY ) ) {
			return new WP_Error( 'xlsx_invalid', __( 'XLSX nije valjana arhiva.', 'brendigo-sidrena-cijena' ) );
		}
		try {
			if ( $zip->numFiles > 1500 || $zip->numFiles < 3 ) {
				return new WP_Error( 'xlsx_entries', __( 'XLSX ima neočekivan broj datoteka.', 'brendigo-sidrena-cijena' ) );
			}
			$total = 0;
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$stat = $zip->statIndex( $i );
				if ( ! $stat ) { return new WP_Error( 'xlsx_entries', __( 'XLSX nije čitljiv.', 'brendigo-sidrena-cijena' ) ); }
				$entry = (string) $stat['name'];
				if ( strpos( $entry, '../' ) !== false || 0 === strpos( $entry, '/' ) || preg_match( '/\\.\\.(?:\\\\|$)/', $entry ) ) {
					return new WP_Error( 'xlsx_path', __( 'XLSX sadrži nedopuštenu putanju.', 'brendigo-sidrena-cijena' ) );
				}
				if ( preg_match( '/(?:vbaProject\.bin|externalLinks\/)/i', $entry ) ) {
					return new WP_Error( 'xlsx_external', __( 'XLSX ima makronaredbe ili vanjske veze.', 'brendigo-sidrena-cijena' ) );
				}
				$total += (int) $stat['size'];
				if ( $total > 67108864 ) {
					return new WP_Error( 'xlsx_large', __( 'XLSX sadrži previše raspakiranih podataka (najviše 64 MB).', 'brendigo-sidrena-cijena' ) );
				}
			}
			// Macros and external connections do not belong in a price-list import.
			foreach ( array( 'xl/vbaProject.bin', 'xl/externalLinks/externalLink1.xml' ) as $unsafe ) {
				if ( false !== $zip->locateName( $unsafe ) ) {
					return new WP_Error( 'xlsx_external', __( 'Datoteke s makronaredbama ili vanjskim vezama nisu dopuštene.', 'brendigo-sidrena-cijena' ) );
				}
			}
			// Locate the FIRST workbook sheet by its internal relationship, not by
			// assuming that Excel always calls it sheet1.xml.
			$workbook = self::xml_part( $zip, 'xl/workbook.xml', 1048576 );
			$relationships = self::xml_part( $zip, 'xl/_rels/workbook.xml.rels', 1048576 );
			if ( is_wp_error( $workbook ) ) { return $workbook; }
			if ( is_wp_error( $relationships ) ) { return $relationships; }
			$sheet_nodes = $workbook->xpath( '//s:sheets/s:sheet' );
			if ( ! $sheet_nodes ) { return new WP_Error( 'xlsx_sheet', __( 'XLSX nema radnih listova.', 'brendigo-sidrena-cijena' ) ); }
			$rel_attrs = $sheet_nodes[0]->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );
			$rel_id = isset( $rel_attrs['id'] ) ? (string) $rel_attrs['id'] : '';
			$sheet_path = '';
			foreach ( $relationships->children( 'http://schemas.openxmlformats.org/package/2006/relationships' )->Relationship as $rel ) {
				if ( (string) $rel['Id'] !== $rel_id || (string) $rel['TargetMode'] === 'External' ) { continue; }
				$target = ltrim( (string) $rel['Target'], '/' );
				$target = 0 === strpos( $target, 'xl/' ) ? $target : 'xl/' . $target;
				if ( preg_match( '#^xl/worksheets/[a-zA-Z0-9_-]+\.xml$#D', $target ) ) { $sheet_path = $target; }
				break;
			}
			if ( '' === $sheet_path ) { return new WP_Error( 'xlsx_sheet', __( 'Prvi XLSX list nije podržan ili pokazuje na vanjski sadržaj.', 'brendigo-sidrena-cijena' ) ); }
			$sheet = self::xml_part( $zip, $sheet_path, 33554432 );
			if ( is_wp_error( $sheet ) ) { return $sheet; }
			$strings = array();
			if ( false !== $zip->locateName( 'xl/sharedStrings.xml' ) ) {
				$xml = self::xml_part( $zip, 'xl/sharedStrings.xml', 16777216 );
				if ( is_wp_error( $xml ) ) { return $xml; }
				$nodes = $xml->xpath( '//s:si' );
				foreach ( (array) $nodes as $node ) {
					$text = '';
					foreach ( (array) $node->xpath( './/*[local-name()="t"]' ) as $part ) { $text .= (string) $part; }
					$strings[] = $text;
				}
			}
			$records = array();
			foreach ( (array) $sheet->xpath( '//s:sheetData/s:row' ) as $row ) {
				$columns = array();
				foreach ( $row->children( self::MAIN_NS )->c as $cell ) {
					$address = (string) $cell['r'];
					if ( ! preg_match( '/^([A-Z]{1,3})[1-9][0-9]*$/D', $address, $match ) ) { continue; }
					$index = 0;
					foreach ( str_split( $match[1] ) as $letter ) { $index = $index * 26 + ( ord( $letter ) - 64 ); }
					--$index;
					if ( $index >= Brendigo_Sidrena_Cijena_Bulk_Import::MAX_FIELDS ) {
						return new WP_Error( 'xlsx_fields', __( 'XLSX ima previše stupaca.', 'brendigo-sidrena-cijena' ) );
					}
					$kind = (string) $cell['t'];
					// Formula cells are not evaluated; reject rather than silently import stale cache.
					if ( count( $cell->children( self::MAIN_NS )->f ) > 0 ) {
						return new WP_Error( 'xlsx_formula', __( 'Uvoz formule nije dopušten. Prije uvoza pretvorite formule u vrijednosti.', 'brendigo-sidrena-cijena' ) );
					}
					if ( 's' === $kind ) {
						$key = (int) $cell->v;
						$val = isset( $strings[ $key ] ) ? $strings[ $key ] : '';
					} elseif ( 'inlineStr' === $kind ) {
						$val = '';
						foreach ( (array) $cell->xpath( './/*[local-name()="t"]' ) as $part ) { $val .= (string) $part; }
					} else {
						$val = (string) $cell->v;
					}
					$columns[ $index ] = trim( $val );
				}
				if ( $columns ) {
					$records[] = $columns;
					if ( count( $records ) > Brendigo_Sidrena_Cijena_Bulk_Import::MAX_ROWS + 1 ) {
						return new WP_Error( 'xlsx_rows', __( 'XLSX ima previše redaka.', 'brendigo-sidrena-cijena' ) );
					}
				}
			}
			if ( count( $records ) < 2 ) { return new WP_Error( 'xlsx_empty', __( 'XLSX mora sadržavati zaglavlje i najmanje jedan redak.', 'brendigo-sidrena-cijena' ) ); }
			$headers = array();
			$used = array();
			foreach ( $records[0] as $i => $header ) {
				$label = trim( $header );
				if ( '' === $label ) { $label = 'Stupac_' . ( $i + 1 ); }
				$base = $label;
				$n = 2;
				while ( isset( $used[ $label ] ) ) { $label = $base . '_' . $n++; }
				$used[ $label ] = true;
				$headers[ $i ] = $label;
			}
			$rows = array();
			foreach ( array_slice( $records, 1 ) as $record ) {
				$row = array();
				foreach ( $headers as $index => $label ) { $row[ $label ] = isset( $record[ $index ] ) ? $record[ $index ] : ''; }
				if ( array_filter( $row, static function ( $v ) { return '' !== $v; } ) ) { $rows[] = $row; }
			}
			return $rows ? array( 'fields' => array_values( $headers ), 'rows' => $rows ) : new WP_Error( 'xlsx_empty', __( 'XLSX nema zapisa za uvoz.', 'brendigo-sidrena-cijena' ) );
		} finally {
			$zip->close();
		}
	}

	private static function xml_part( $zip, $name, $max_bytes ) {
		$info = $zip->statName( $name );
		if ( ! $info || (int) $info['size'] > $max_bytes ) {
			return new WP_Error( 'xlsx_part', __( 'XLSX ne sadrži podržani prvi list ili je prevelik.', 'brendigo-sidrena-cijena' ) );
		}
		$text = $zip->getFromName( $name );
		if ( false === $text || preg_match( '/<!DOCTYPE|<!ENTITY/i', $text ) ) {
			return new WP_Error( 'xlsx_xml', __( 'XLSX sadrži nedopušten XML.', 'brendigo-sidrena-cijena' ) );
		}
		$previous = libxml_use_internal_errors( true );
		try { $xml = simplexml_load_string( $text, 'SimpleXMLElement', LIBXML_NONET ); }
		finally { libxml_clear_errors(); libxml_use_internal_errors( $previous ); }
		if ( false === $xml ) { return new WP_Error( 'xlsx_xml', __( 'XLSX XML nije čitljiv.', 'brendigo-sidrena-cijena' ) ); }
		$xml->registerXPathNamespace( 's', self::MAIN_NS );
		return $xml;
	}

	/** Produce an XLSX template using inline strings (no executable formulas). */
	public static function template_bytes() {
		if ( ! class_exists( 'ZipArchive' ) ) { return false; }
		$tmp = wp_tempnam( 'sidrena-predlozak.xlsx' );
		if ( ! $tmp ) { return false; }
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) { wp_delete_file( $tmp ); return false; }
		$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="' . self::MAIN_NS . '"><sheetData>';
		$rows = array(
			array( 'sku','barkod','naziv','vrsta','cijena_redovna','cijena_akcijska','sidrena_cijena','sidrena_datum','poslovnica','dostupnost' ),
			array( 'PROIZVOD-001','0001234567890','Primjer proizvoda','proizvod','19.90','','18.90','10.09.2026.','webshop','dostupno' ),
			array( 'USLUGA-001','','Primjer usluge','usluga','30.00','','29.00','10.09.2026.','webshop','dostupno' ),
		);
		foreach ( $rows as $index => $values ) {
			$sheet .= '<row r="' . ( $index + 1 ) . '">';
			foreach ( $values as $col => $value ) {
				$letter = chr( 65 + $col );
				$sheet .= '<c r="' . $letter . ( $index + 1 ) . '" t="inlineStr"><is><t>' . htmlspecialchars( $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</t></is></c>';
			}
			$sheet .= '</row>';
		}
		$sheet .= '</sheetData></worksheet>';
		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="' . self::MAIN_NS . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Cjenik" sheetId="1" r:id="rId1"/></sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>' );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet );
		$zip->close();
		$bytes = file_get_contents( $tmp ); // Temporary file is created by WordPress itself.
		wp_delete_file( $tmp );
		return $bytes;
	}
}
