<?php
/**
 * Services whose price is agreed on request. HTML cjenik only.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Repeater rows that print at the bottom of the HTML cjenik.
 */
class Brendigo_Sidrena_Cijena_Upit {

	use Brendigo_Sidrena_Cijena_Row_Helpers;

	const OPTION_KEY = 'sidrena_cijena_upit';
	const NEXT_ID_OPTION = 'sidrena_cijena_upit_next_id';

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Request-cached rows.
	 *
	 * @var array<int, array<string, string>>|null
	 */
	private $cached_all = null;

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

			$clean = $this->sanitize_row( $row );
			if ( null !== $clean ) {
				$out[] = $clean;
			}
		}

		$this->cached_all = $out;
		return $this->cached_all;
	}

	/**
	 * Rows for one HTML cjenik file.
	 *
	 * An empty code is the webshop. A single shop shows every row.
	 *
	 * @param string $oznaka Outlet code.
	 * @return array<int, array<string, string>>
	 */
	public function for_oznaka( $oznaka ) {
		$oznaka = $this->row_poslovnica( array( 'poslovnica' => $oznaka ) );
		if ( '' === $oznaka && ! Brendigo_Sidrena_Cijena_Settings::instance()->is_multi_poslovnica() ) {
			return $this->all();
		}

		$out = array();
		foreach ( $this->all() as $row ) {
			if ( $this->row_poslovnica( $row ) === $oznaka ) {
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * Persist rows and queue a public HTML rebuild.
	 *
	 * @param mixed $rows  Raw rows.
	 * @param bool  $queue Queue the public feed rebuild.
	 * @return array<int, array<string, string>>
	 */
	public function save( $rows, $queue = true ) {
		$clean    = array();
		$used_ids = array();

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$item = $this->sanitize_row( $row );
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
		$next = max( $this->next_id( $clean ), $this->next_id( $current ), max( 1, (int) get_option( self::NEXT_ID_OPTION, 1 ) ) );
		foreach ( $clean as $index => $item ) {
			if ( '' === $item['id'] ) {
				$clean[ $index ]['id'] = (string) $next;
				++$next;
			}
		}

		if ( $clean === $current ) {
			return $current;
		}
		// Do not report a successful save or update the request cache on DB failure.
		if ( ! update_option( self::OPTION_KEY, $clean, false ) ) {
			return $current;
		}
		$next_id = max( $next, $this->next_id( $clean ) );
		if ( $next_id > (int) get_option( self::NEXT_ID_OPTION, 1 ) ) {
			update_option( self::NEXT_ID_OPTION, $next_id, false );
		}
		$this->cached_all = $clean;

		if ( $queue && class_exists( 'Brendigo_Sidrena_Cijena_Automation' ) ) {
			Brendigo_Sidrena_Cijena_Automation::instance()->queue_rebuild( 'upit-change' );
		}

		return $clean;
	}


	/**
	 * Drop rows that belong to one outlet.
	 *
	 * @param string $oznaka Outlet code.
	 * @return array<int, array<string, string>>
	 */
	public function delete_for_poslovnica( $oznaka ) {
		$oznaka = $this->row_poslovnica( array( 'poslovnica' => $oznaka ) );
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
	 * Keep a row only when it has a name and criteria.
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array<string, string>|null
	 */
	private function sanitize_row( $row ) {
		$id = isset( $row['id'] ) ? preg_replace( '/\D/', '', (string) $row['id'] ) : '';
		$id = is_string( $id ) ? $id : '';

		$naziv = isset( $row['naziv'] ) ? sanitize_text_field( wp_unslash( (string) $row['naziv'] ) ) : '';
		$naziv = is_string( $naziv ) ? $naziv : '';

		$kriteriji = isset( $row['kriteriji'] ) ? sanitize_text_field( wp_unslash( (string) $row['kriteriji'] ) ) : '';
		$kriteriji = is_string( $kriteriji ) ? $kriteriji : '';

		if ( '' === $naziv || '' === $kriteriji ) {
			return null;
		}

		return array(
			'id'         => $id,
			'naziv'      => $naziv,
			'kriteriji'  => $kriteriji,
			'poslovnica' => $this->row_poslovnica( $row ),
		);
	}

}
