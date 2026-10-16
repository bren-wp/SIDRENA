<?php
/**
 * Shared helpers for option-backed manual row collections.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reuse the small pieces that are identical in the manual catalog and
 * price-on-request collections. The consuming class provides save().
 */
trait Brendigo_Sidrena_Cijena_Row_Helpers {

	/**
	 * Decode a repeater JSON payload and persist the normalized rows.
	 *
	 * @param string $json Raw JSON.
	 * @return array<int, array<string, string>>
	 */
	public function save_from_json( $json ) {
		$decoded = json_decode( (string) $json, true );
		if ( ! is_array( $decoded ) || ! preg_match( '/^\s*\[/', (string) $json ) ) {
			// Invalid/truncated JSON and JSON objects must not erase the catalog.
			// A valid [] intentionally still removes every row.
			return $this->all();
		}
		foreach ( $decoded as $row ) {
			if ( ! is_array( $row ) ) {
				return $this->all();
			}
		}

		return $this->save( $decoded, true );
	}

	/**
	 * Normalize the outlet code stored on a manual row. Empty is the webshop.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return string
	 */
	public function row_poslovnica( $row ) {
		$value = Brendigo_Sidrena_Cijena_Settings::instance()->normalize_oznaka( isset( $row['poslovnica'] ) ? $row['poslovnica'] : '' );
		if ( 'webshop' === strtolower( $value ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Next unused numeric row id.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return int
	 */
	private function next_id( $rows ) {
		$max = 0;
		foreach ( $rows as $row ) {
			$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
			if ( $id > $max ) {
				$max = $id;
			}
		}

		return $max + 1;
	}
}
