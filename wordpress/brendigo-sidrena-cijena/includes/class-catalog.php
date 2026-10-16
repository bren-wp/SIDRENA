<?php
/**
 * Standalone product catalog when WooCommerce is not active.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Repeater-backed product list used for XML/CSV and the public HTML cjenik.
 */
class Brendigo_Sidrena_Cijena_Catalog {

	use Brendigo_Sidrena_Cijena_Row_Helpers;

	const OPTION_KEY = 'sidrena_cijena_catalog';
	const NEXT_ID_OPTION = 'sidrena_cijena_catalog_next_id';

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * In-memory rows during a batch upsert.
	 *
	 * @var array<int, array<string, string>>|null
	 */
	private $working = null;

	/**
	 * Fast lookup indexes used only while an import batch is open.
	 *
	 * @var array<string, int>
	 */
	private $working_sku_index = array();

	/**
	 * @var array<string, int>
	 */
	private $working_id_index = array();

	/**
	 * Whether the current batch changed any catalog row.
	 *
	 * @var bool
	 */
	private $working_dirty = false;

	/**
	 * Request-cached rows.
	 *
	 * @var array<int, array<string, string>>|null
	 */
	private $cached_all = null;

	/** @var array<string,array<string,string>>|null Request-scoped ID index. */
	private $cached_by_id = null;

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
	 * One stored row by numeric id.
	 *
	 * @param mixed $id Row id.
	 * @return array<string, string>|null
	 */
	public function get( $id ) {
		$id = (string) $id;
		if ( ! preg_match( '/^[0-9]+$/D', $id ) ) {
			return null;
		}
		if ( null === $this->cached_by_id ) {
			$this->cached_by_id = array();
			foreach ( $this->all() as $row ) {
				if ( isset( $row['id'] ) ) {
					$this->cached_by_id[ (string) $row['id'] ] = $row;
				}
			}
		}
		return isset( $this->cached_by_id[ $id ] ) ? $this->cached_by_id[ $id ] : null;
	}

	/**
	 * All stored rows.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function all() {
		if ( null !== $this->cached_all ) {
			return $this->cached_all;
		}

		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$this->cached_all = array();
			return $this->cached_all;
		}

		$out = array();
		foreach ( $stored as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$clean = $this->sanitize_row( $row, false );
			if ( null !== $clean ) {
				$out[] = $clean;
			}
		}

		$this->cached_all = $out;
		return $this->cached_all;
	}

	/**
	 * Persist rows and optionally queue a public feed rebuild.
	 *
	 * @param mixed $rows  Raw rows.
	 * @param bool  $queue Queue XML/CSV regenerate.
	 * @return array<int, array<string, string>>
	 */
	public function save( $rows, $queue = true ) {
		$clean   = array();
		$used_ids = array();

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$item = $this->sanitize_row( $row, true );
				if ( null === $item ) {
					continue;
				}

				if ( isset( $used_ids[ $item['id'] ] ) ) {
					$item['id'] = '';
				}

				$clean[] = $item;
				if ( '' !== $item['id'] ) {
					$used_ids[ $item['id'] ] = true;
				}
			}
		}

		$current = $this->all();
		$next = max(
			$this->next_id( $clean ),
			$this->next_id( $current ),
			max( 1, (int) get_option( self::NEXT_ID_OPTION, 1 ) )
		);
		foreach ( $clean as $index => $item ) {
			if ( '' === $item['id'] ) {
				$clean[ $index ]['id'] = (string) $next;
				++$next;
			}
		}

		if ( $clean === $current ) {
			return $current;
		}

		// Never update in-request caches, price history or audit if storage failed.
		if ( ! update_option( self::OPTION_KEY, $clean, false ) ) {
			return $current;
		}
		$next_id = max( $next, $this->next_id( $clean ) );
		if ( $next_id > (int) get_option( self::NEXT_ID_OPTION, 1 ) ) {
			update_option( self::NEXT_ID_OPTION, $next_id, false );
		}
		$this->cached_all = $clean;
		$this->cached_by_id = null;

		// Only actual observed selling-price changes enter history; sidrena values
		// and historical reference dates are never generated from this log.
		Brendigo_Sidrena_Cijena_Price_History::instance()->capture_catalog_changes( $current, $clean );
		Brendigo_Sidrena_Cijena_Audit_Log::instance()->capture_catalog_changes( $current, $clean );

		if ( $queue && class_exists( 'Brendigo_Sidrena_Cijena_Automation' ) ) {
			Brendigo_Sidrena_Cijena_Automation::instance()->queue_rebuild( 'catalog-change' );
		}

		return $clean;
	}


	/**
	 * Drop manual rows that belong to one outlet.
	 *
	 * @param string $oznaka Outlet code.
	 * @return array<int, array<string, string>>
	 */
	public function delete_for_poslovnica( $oznaka ) {
		$oznaka = Brendigo_Sidrena_Cijena_Settings::instance()->normalize_oznaka( $oznaka );
		if ( '' === $oznaka ) {
			return $this->all();
		}

		$keep = array();
		foreach ( $this->all() as $row ) {
			if ( $this->row_poslovnica( $row ) !== $oznaka ) {
				$keep[] = $row;
			}
		}

		return $this->save( $keep, true );
	}

	/**
	 * Rows for XML/CSV export.
	 *
	 * @param bool $missing_only Only rows without sidrena price.
	 * @param bool $public       Include id and url.
	 * @return array<int, array<string, string>>
	 */
	public function to_export_rows( $missing_only = false, $public = false ) {
		$out = array();

		foreach ( $this->all() as $row ) {
			if ( $missing_only && '' !== $row['sidrena_cijena'] ) {
				continue;
			}

			$item = array(
				'id'              => $row['id'],
				'naziv'           => $row['naziv'],
				'sku'             => $row['sku'],
				'barkod'          => isset( $row['barkod'] ) ? $row['barkod'] : '',
				'cijena_redovna'  => $row['cijena_redovna'],
				'cijena_akcijska' => $row['cijena_akcijska'],
				'sidrena_cijena'  => $row['sidrena_cijena'],
				'vrsta'           => $this->row_vrsta( $row ),
				'sidrena_datum'   => $this->display_date( $row ),
				'poslovnica'      => $this->row_poslovnica( $row ),
			);

			if ( $public ) {
				$legal                    = $this->public_price_fields( $row );
				$item['id']               = $row['id'];
				$item['url']              = $row['url'];
				$item['marka']            = Brendigo_Sidrena_Cijena_Settings::instance()->row_marka( isset( $row['marka'] ) ? $row['marka'] : '' );
				$item['barkod']           = isset( $row['barkod'] ) ? $row['barkod'] : '';
				$item['dostupnost']       = isset( $row['dostupnost'] ) ? $row['dostupnost'] : 'dostupno';
				$item['jedinica_mjere']   = $legal['jedinica_mjere'];
				$item['cijena_jedinice_mjere'] = $legal['cijena_jedinice_mjere'];
				$item['maloprodajna_cijena']   = $legal['maloprodajna_cijena'];
				$item['naziv_posebnog_oblika'] = $legal['naziv_posebnog_oblika'];
			}

			$out[] = $item;
		}

		return $out;
	}

	/**
	 * Start a batch upsert (one option write at commit).
	 */
	public function begin_batch() {
		$this->working           = $this->all();
		$this->working_sku_index = array();
		$this->working_id_index  = array();
		$this->working_dirty     = false;

		foreach ( $this->working as $index => $row ) {
			$this->index_working_row( $index, $row );
		}
	}

	/**
	 * Insert or update one imported row by SKU, then by catalog id, otherwise append.
	 *
	 * @param array<string, string> $data Sanitized-ish fields.
	 * @return string updated|created|skipped
	 */
	public function upsert_working_row( $data ) {
		if ( ! is_array( $this->working ) ) {
			$this->begin_batch();
		}

		$row = $this->sanitize_row(
			array(
				'id'              => isset( $data['id'] ) ? $data['id'] : '',
				'naziv'           => isset( $data['naziv'] ) ? $data['naziv'] : '',
				'sku'             => isset( $data['sku'] ) ? $data['sku'] : '',
				'barkod'          => isset( $data['barkod'] ) ? $data['barkod'] : '',
				'marka'           => isset( $data['marka'] ) ? $data['marka'] : '',
				'url'             => isset( $data['url'] ) ? $data['url'] : '',
				'cijena_redovna'        => isset( $data['cijena_redovna'] ) ? $data['cijena_redovna'] : '',
				'cijena_akcijska'       => isset( $data['cijena_akcijska'] ) ? $data['cijena_akcijska'] : '',
				'sidrena_cijena'        => isset( $data['sidrena_cijena'] ) ? $data['sidrena_cijena'] : '',
				'dostupnost'            => ( isset( $data['dostupnost'] ) && '' !== trim( (string) $data['dostupnost'] ) ) ? $data['dostupnost'] : '',
				'cjm'                   => isset( $data['cjm'] ) ? $data['cjm'] : '',
				'cjm_suffix'            => isset( $data['cjm_suffix'] ) ? $data['cjm_suffix'] : '',
				'naziv_posebnog_oblika' => isset( $data['naziv_posebnog_oblika'] ) ? $data['naziv_posebnog_oblika'] : '',
				'jedinica_mjere'        => isset( $data['jedinica_mjere'] ) ? $data['jedinica_mjere'] : '',
				'cijena_jedinice_mjere' => isset( $data['cijena_jedinice_mjere'] ) ? $data['cijena_jedinice_mjere'] : '',
				'vrsta'                 => isset( $data['vrsta'] ) ? $data['vrsta'] : '',
				'sidrena_datum'         => isset( $data['sidrena_datum'] ) ? $data['sidrena_datum'] : '',
				'poslovnica'            => isset( $data['poslovnica'] ) ? $data['poslovnica'] : '',
			),
			false
		);

		if ( null === $row ) {
			return 'skipped';
		}

		$sku    = $row['sku'];
		$target = $row['poslovnica'];
		if ( '' !== $sku ) {
			$key = $this->working_sku_key( $sku, $target );
			if ( isset( $this->working_sku_index[ $key ] ) ) {
				$index    = $this->working_sku_index[ $key ];
				$existing = $this->working[ $index ];
				$row      = $this->fill_blanks( $row, $existing, $data );
				$row['id'] = $existing['id'];

				if ( $row === $existing ) {
					return 'skipped';
				}

				$this->unindex_working_row( $index, $existing );
				$this->working[ $index ] = $row;
				$this->index_working_row( $index, $row );
				$this->working_dirty = true;
				return 'updated';
			}
		}

		$match_id = $row['id'];
		if ( '' !== $match_id && isset( $this->working_id_index[ $match_id ] ) ) {
			$index    = $this->working_id_index[ $match_id ];
			$existing = $this->working[ $index ];
			$row      = $this->fill_blanks( $row, $existing, $data );
			$row['id'] = $existing['id'];

			if ( $row === $existing ) {
				return 'skipped';
			}

			$this->unindex_working_row( $index, $existing );
			$this->working[ $index ] = $row;
			$this->index_working_row( $index, $row );
			$this->working_dirty = true;
			return 'updated';
		}

		if ( '' === $row['naziv'] || '' === $row['cijena_redovna'] || '' === $row['sidrena_cijena'] ) {
			return 'skipped';
		}

		$index = count( $this->working );
		$this->working[] = $row;
		$this->index_working_row( $index, $row );
		$this->working_dirty = true;
		return 'created';
	}

	/**
	 * Keep stored values when the import left a field empty.
	 *
	 * @param array<string, string> $row      Sanitized incoming row.
	 * @param array<string, string> $existing Stored row.
	 * @param array<string, string> $data     Raw mapped import fields.
	 * @return array<string, string>
	 */
	private function fill_blanks( $row, $existing, $data ) {
		$empty_keep = array( 'naziv', 'url', 'cijena_redovna', 'cijena_akcijska', 'sidrena_cijena' );
		foreach ( $empty_keep as $key ) {
			if ( '' === $row[ $key ] && isset( $existing[ $key ] ) ) {
				$row[ $key ] = $existing[ $key ];
			}
		}

		if ( ! array_key_exists( 'barkod', $data ) || '' === $row['barkod'] ) {
			$row['barkod'] = isset( $existing['barkod'] ) ? $existing['barkod'] : '';
		}

		if ( ! array_key_exists( 'marka', $data ) ) {
			$row['marka'] = isset( $existing['marka'] ) ? $existing['marka'] : '';
		} elseif ( '' === $row['marka'] && isset( $existing['marka'] ) ) {
			$row['marka'] = $existing['marka'];
		}

		$incoming_stock = ( isset( $data['dostupnost'] ) && '' !== trim( (string) $data['dostupnost'] ) );
		if ( ! $incoming_stock ) {
			$row['dostupnost'] = isset( $existing['dostupnost'] ) ? $existing['dostupnost'] : 'dostupno';
		}

		$key_if_absent = array( 'cjm', 'cjm_suffix', 'naziv_posebnog_oblika', 'jedinica_mjere', 'cijena_jedinice_mjere', 'poslovnica' );
		foreach ( $key_if_absent as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				$row[ $key ] = isset( $existing[ $key ] ) ? $existing[ $key ] : '';
			}
		}

		if ( ! array_key_exists( 'vrsta', $data ) || '' === $row['vrsta'] ) {
			$row['vrsta'] = isset( $existing['vrsta'] ) ? $existing['vrsta'] : 'proizvod';
		}

		if ( ! array_key_exists( 'sidrena_datum', $data ) ) {
			$row['sidrena_datum'] = isset( $existing['sidrena_datum'] ) ? $existing['sidrena_datum'] : '';
		} elseif ( '' === $row['sidrena_datum'] && isset( $existing['sidrena_datum'] ) ) {
			$row['sidrena_datum'] = $existing['sidrena_datum'];
		}

		return $row;
	}

	/**
	 * Write the batch and optionally queue the public feed.
	 *
	 * @param bool $queue Queue regenerate.
	 */
	public function commit_batch( $queue = true ) {
		if ( ! is_array( $this->working ) ) {
			return;
		}

		$rows  = $this->working;
		$dirty = $this->working_dirty;

		$this->working           = null;
		$this->working_sku_index = array();
		$this->working_id_index  = array();
		$this->working_dirty     = false;

		if ( $dirty ) {
			$this->save( $rows, $queue );
		}
	}

	/**
	 * Composite SKU/outlet key for batch lookup.
	 *
	 * @param string $sku        SKU.
	 * @param string $poslovnica Outlet code.
	 * @return string
	 */
	private function working_sku_key( $sku, $poslovnica ) {
		return (string) $sku . chr( 31 ) . (string) $poslovnica;
	}

	/**
	 * Add one working row to the transient batch indexes. First match wins,
	 * matching the behavior of the former linear scan.
	 *
	 * @param int                   $index Row index.
	 * @param array<string, string> $row   Row.
	 */
	private function index_working_row( $index, $row ) {
		$id = isset( $row['id'] ) ? (string) $row['id'] : '';
		if ( '' !== $id && ! isset( $this->working_id_index[ $id ] ) ) {
			$this->working_id_index[ $id ] = (int) $index;
		}

		$sku = isset( $row['sku'] ) ? (string) $row['sku'] : '';
		if ( '' !== $sku ) {
			$key = $this->working_sku_key( $sku, $this->row_poslovnica( $row ) );
			if ( ! isset( $this->working_sku_index[ $key ] ) ) {
				$this->working_sku_index[ $key ] = (int) $index;
			}
		}
	}

	/**
	 * Remove index entries that currently point at one working row.
	 *
	 * @param int                   $index Row index.
	 * @param array<string, string> $row   Previous row.
	 */
	private function unindex_working_row( $index, $row ) {
		$id = isset( $row['id'] ) ? (string) $row['id'] : '';
		if ( '' !== $id && isset( $this->working_id_index[ $id ] ) && (int) $this->working_id_index[ $id ] === (int) $index ) {
			unset( $this->working_id_index[ $id ] );
		}

		$sku = isset( $row['sku'] ) ? (string) $row['sku'] : '';
		if ( '' !== $sku ) {
			$key = $this->working_sku_key( $sku, $this->row_poslovnica( $row ) );
			if ( isset( $this->working_sku_index[ $key ] ) && (int) $this->working_sku_index[ $key ] === (int) $index ) {
				unset( $this->working_sku_index[ $key ] );
			}
		}
	}

	/**
	 * Format a stored amount for the public HTML table.
	 *
	 * @param string $raw Stored decimal.
	 * @return string
	 */
	public function format_amount( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '-';
		}

		$normalized = $this->normalize_price( $raw );
		if ( '' === $normalized || ! is_numeric( $normalized ) ) {
			return $raw;
		}

		return number_format( (float) $normalized, 2, ',', '.' ) . ' €';
	}

	/**
	 * Normalize a price string to a Woo-like decimal (dot separator).
	 *
	 * @param mixed $price Raw value.
	 * @return string
	 */
	public function normalize_price( $price ) {
		return Brendigo_Sidrena_Cijena_Money::normalize_price_catalog( $price );
	}

	/**
	 * Sanitize one catalog row.
	 *
	 * @param array<string, mixed> $row   Raw row.
	 * @param bool                 $strict Drop incomplete rows.
	 * @return array<string, string>|null
	 */
	private function sanitize_row( $row, $strict ) {
		$id = isset( $row['id'] ) ? trim( (string) $row['id'] ) : '';
		$id = preg_match( '/^[1-9][0-9]*$/D', $id ) ? $id : '';
		$naziv  = isset( $row['naziv'] ) ? sanitize_text_field( (string) $row['naziv'] ) : '';
		$sku    = isset( $row['sku'] ) ? sanitize_text_field( (string) $row['sku'] ) : '';
		$marka  = isset( $row['marka'] ) ? sanitize_text_field( (string) $row['marka'] ) : '';
		// Never interpret barcode as a number: leading zeroes are significant.
		$barkod = isset( $row['barkod'] ) ? substr( preg_replace( '/[^0-9A-Za-z._-]/', '', (string) $row['barkod'] ), 0, 64 ) : '';
		$url    = isset( $row['url'] ) ? esc_url_raw( trim( (string) $row['url'] ), array( 'http', 'https' ) ) : '';
		$reg         = isset( $row['cijena_redovna'] ) ? $this->normalize_price( $row['cijena_redovna'] ) : '';
		$sale        = isset( $row['cijena_akcijska'] ) ? $this->normalize_price( $row['cijena_akcijska'] ) : '';
		$sidrena     = isset( $row['sidrena_cijena'] ) ? $this->normalize_price( $row['sidrena_cijena'] ) : '';
		$dostupnost  = ( isset( $row['dostupnost'] ) && '' !== trim( (string) $row['dostupnost'] ) ) ? $this->normalize_dostupnost( $row['dostupnost'] ) : '';
		$cjm_raw     = isset( $row['cjm'] ) ? strtolower( trim( sanitize_text_field( (string) $row['cjm'] ) ) ) : '';
		$cjm         = in_array( $cjm_raw, array( 'yes', '1', 'true' ), true ) ? 'yes' : '';
		$cjm_suffix  = isset( $row['cjm_suffix'] ) ? sanitize_text_field( (string) $row['cjm_suffix'] ) : '';
		$cjm_suffix  = is_string( $cjm_suffix ) ? $cjm_suffix : '';
		$oblik       = isset( $row['naziv_posebnog_oblika'] ) ? sanitize_text_field( (string) $row['naziv_posebnog_oblika'] ) : '';
		$jedinica    = isset( $row['jedinica_mjere'] ) ? sanitize_text_field( (string) $row['jedinica_mjere'] ) : '';
		$jed_cijena  = isset( $row['cijena_jedinice_mjere'] ) ? $this->normalize_price( $row['cijena_jedinice_mjere'] ) : '';
		$vrsta       = $this->normalize_vrsta( isset( $row['vrsta'] ) ? $row['vrsta'] : '' );
		$datum       = $this->normalize_date( isset( $row['sidrena_datum'] ) ? $row['sidrena_datum'] : '' );
		$poslovnica  = $this->row_poslovnica( $row );
		$oblik       = is_string( $oblik ) ? $oblik : '';
		$jedinica    = is_string( $jedinica ) ? $jedinica : '';

		$naziv = is_string( $naziv ) ? $naziv : '';
		$sku   = is_string( $sku ) ? $sku : '';
		$marka = is_string( $marka ) ? $marka : '';
		$url   = is_string( $url ) ? $url : '';
		$id    = is_string( $id ) ? $id : '';

		if ( $strict && ( '' === $naziv || '' === $reg || '' === $sidrena ) ) {
			return null;
		}

		if ( ! $strict && '' === $naziv && '' === $sku && '' === $url && '' === $reg && '' === $sale && '' === $sidrena ) {
			return null;
		}

		if ( '' === $dostupnost ) {
			$dostupnost = 'dostupno';
		}

		return array(
			'id'              => $id,
			'naziv'           => $naziv,
			'sku'             => $sku,
			'barkod'          => $barkod,
			'marka'           => $marka,
			'url'             => $url,
			'cijena_redovna'  => $reg,
			'cijena_akcijska' => $sale,
			'sidrena_cijena'  => $sidrena,
			'dostupnost'            => $dostupnost,
			'cjm'                   => $cjm,
			'cjm_suffix'            => $cjm_suffix,
			'naziv_posebnog_oblika' => $oblik,
			'jedinica_mjere'        => $jedinica,
			'cijena_jedinice_mjere' => $jed_cijena,
			'vrsta'                 => $vrsta,
			'sidrena_datum'         => $datum,
			'poslovnica'            => $poslovnica,
		);
	}

	/**
	 * proizvod or usluga. Anything else becomes proizvod.
	 *
	 * @param mixed $value Raw.
	 * @return string
	 */
	public function normalize_vrsta( $value ) {
		return Brendigo_Sidrena_Cijena_Money::normalize_vrsta( $value );
	}

	/**
	 * Stored row type.
	 *
	 * @param array<string, string> $row Row.
	 * @return string
	 */
	public function row_vrsta( $row ) {
		return $this->normalize_vrsta( isset( $row['vrsta'] ) ? $row['vrsta'] : '' );
	}


	/**
	 * Date shown for a catalog row: override, otherwise shop default.
	 *
	 * @param array<string, string> $row Row.
	 * @return string
	 */
	public function display_date( $row ) {
		$custom = $this->normalize_date( isset( $row['sidrena_datum'] ) ? $row['sidrena_datum'] : '' );
		if ( '' !== $custom ) {
			return $custom;
		}

		return $this->normalize_date( (string) Brendigo_Sidrena_Cijena_Settings::instance()->get( 'default_date' ) );
	}

	/**
	 * Trailing period on a Croatian date, or empty.
	 *
	 * @param mixed $date Raw.
	 * @return string
	 */
	public function normalize_date( $date ) {
		return Brendigo_Sidrena_Cijena_Money::normalize_date( $date );
	}

	/**
	 * Whether a catalog row belongs on the webshop feed.
	 *
	 * Empty poslovnica, unless the row id is assigned to a physical outlet.
	 *
	 * @param array<string, string> $row           Row.
	 * @param array<string, bool>   $assigned_ids  Catalog ids picked on outlet cards.
	 * @return bool
	 */
	public function belongs_to_webshop( $row, $assigned_ids = array() ) {
		if ( '' !== $this->row_poslovnica( $row ) ) {
			return false;
		}

		$id = isset( $row['id'] ) ? (string) $row['id'] : '';
		if ( '' !== $id && isset( $assigned_ids[ $id ] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether a catalog row belongs on one outlet feed.
	 *
	 * @param array<string, string> $row          Row.
	 * @param string                $oznaka       Outlet code.
	 * @param array<string, bool>   $assigned_ids Ids picked on that outlet card.
	 * @return bool
	 */
	public function belongs_to_outlet( $row, $oznaka, $assigned_ids = array() ) {
		$oznaka = Brendigo_Sidrena_Cijena_Settings::instance()->normalize_oznaka( $oznaka );
		if ( '' === $oznaka ) {
			return $this->belongs_to_webshop( $row, $assigned_ids );
		}

		if ( $this->row_poslovnica( $row ) === $oznaka ) {
			return true;
		}

		$id = isset( $row['id'] ) ? (string) $row['id'] : '';

		return '' !== $id && isset( $assigned_ids[ $id ] ) && '' === $this->row_poslovnica( $row );
	}

	/**
	 * Catalog rows for one public feed.
	 *
	 * @param bool               $missing_only Empty sidrena only.
	 * @param bool               $public       Public columns.
	 * @param string|null        $oznaka       null = all, '' = webshop, code = outlet.
	 * @param array<int, string> $assigned_ids Outlet picker ids.
	 * @return array<int, array<string, string>>
	 */
	public function to_export_rows_for( $missing_only, $public, $oznaka, $assigned_ids = array() ) {
		$lookup = array();
		foreach ( $assigned_ids as $id ) {
			$id = preg_replace( '/\D/', '', (string) $id );
			if ( is_string( $id ) && '' !== $id ) {
				$lookup[ $id ] = true;
			}
		}

		$out = array();
		foreach ( $this->to_export_rows( $missing_only, $public ) as $row ) {
			if ( null === $oznaka ) {
				$out[] = $row;
				continue;
			}

			$match = ( '' === $oznaka )
				? $this->belongs_to_webshop( $row, $lookup )
				: $this->belongs_to_outlet( $row, $oznaka, $lookup );
			if ( $match ) {
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * Legal price columns for one manual row. Sale price wins; empty override becomes "akcija".
	 *
	 * @param array<string, string> $row Stored row.
	 * @return array<string, string>
	 */
	public function public_price_fields( $row ) {
		$sale     = isset( $row['cijena_akcijska'] ) ? trim( (string) $row['cijena_akcijska'] ) : '';
		$regular  = isset( $row['cijena_redovna'] ) ? (string) $row['cijena_redovna'] : '';
		$on_sale  = '' !== $sale;
		$override = isset( $row['naziv_posebnog_oblika'] ) ? trim( (string) $row['naziv_posebnog_oblika'] ) : '';

		return array(
			'maloprodajna_cijena'    => $on_sale ? $sale : $regular,
			'naziv_posebnog_oblika'  => $on_sale ? ( '' !== $override ? $override : 'akcija' ) : '',
			'jedinica_mjere'         => isset( $row['jedinica_mjere'] ) ? (string) $row['jedinica_mjere'] : '',
			'cijena_jedinice_mjere'  => isset( $row['cijena_jedinice_mjere'] ) ? (string) $row['cijena_jedinice_mjere'] : '',
		);
	}

	/**
	 * Legal availability flag stored on a catalog row.
	 *
	 * @param mixed $value Raw value.
	 * @return string dostupno|nedostupno
	 */
	public function normalize_dostupnost( $value ) {
		return Brendigo_Sidrena_Cijena_Money::normalize_dostupnost( $value );
	}

}
