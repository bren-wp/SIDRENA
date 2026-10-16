<?php
/**
 * Snapshot regular prices into sidrena meta in batches.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Activation snapshot runner.
 */
class Brendigo_Sidrena_Cijena_Activator {

	const PENDING_OPTION     = 'sidrena_cijena_snapshot_pending';
	const PAGE_OPTION        = 'sidrena_cijena_snapshot_page';
	const OFFSET_OPTION      = 'sidrena_cijena_snapshot_offset';
	const CHILD_OPTION       = 'sidrena_cijena_snapshot_child';
	const FILL_PROMPT_OPTION = 'sidrena_cijena_fill_prompt';
	const BATCH_SIZE         = 40;
	const WORK_BUDGET        = 25;

	/**
	 * Ask an admin to fill empty sidrena fields. Activation does not write prices.
	 */
	public static function queue_fill_prompt() {
		update_option( self::FILL_PROMPT_OPTION, 'yes', false );
	}


	/**
	 * Process one short slice of products, then reschedule if needed.
	 *
	 * A page is 40 parents, but each HTTP request stops after WORK_BUDGET
	 * product objects so a proxy timeout cannot drop the rest of the catalog.
	 * Offset and child resume inside the same page.
	 *
	 * @param int  $page        Optional explicit page. 0 reads the stored cursor.
	 * @param bool $stamp_today Also write today's date when the date field is empty.
	 * @param int  $offset      Parent index inside the current page.
	 * @param int  $child       Variation index inside the current parent.
	 * @return array{done:bool,processed:int,written:int,page:int,next_page:int,next_offset:int,next_child:int,total:int,seen:int}
	 */
	public static function process_batch( $page = 0, $stamp_today = false, $offset = 0, $child = 0 ) {
		ignore_user_abort( true );

		$page   = absint( $page );
		$offset = absint( $offset );
		$child  = absint( $child );
		if ( $page < 1 ) {
			$page   = max( 1, absint( get_option( self::PAGE_OPTION, 1 ) ) );
			$offset = absint( get_option( self::OFFSET_OPTION, 0 ) );
			$child  = absint( get_option( self::CHILD_OPTION, 0 ) );
		}

		$ids   = self::snapshot_ids( $page );
		$total = self::snapshot_total();

		$meta      = Brendigo_Sidrena_Cijena_Product_Meta::instance();
		$date      = $stamp_today ? Brendigo_Sidrena_Cijena_Product_Meta::today_date() : '';
		$processed = 0;
		$written   = 0;
		$budget    = self::WORK_BUDGET;
		$advanced  = 0;
		$next_child = 0;
		$slice     = array_slice( $ids, $offset );

		foreach ( $slice as $product_id ) {
			if ( $budget < 1 && $advanced > 0 ) {
				break;
			}

			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				++$advanced;
				$child = 0;
				continue;
			}

			++$processed;
			--$budget;

			if ( $product->is_type( 'variable' ) || $product->is_type( 'variable-subscription' ) ) {
				$children = array_values( $product->get_children() );
				$start    = $child;
				$child    = 0;
				$count    = count( $children );

				for ( $i = $start; $i < $count; $i++ ) {
					if ( $budget < 1 ) {
						$next_child = $i;
						break 2;
					}

					$variation = wc_get_product( $children[ $i ] );
					if ( $variation && $meta->snapshot_if_empty( $variation, $date ) ) {
						++$written;
					}
					--$budget;
				}

				++$advanced;
				continue;
			}

			if ( $meta->snapshot_if_empty( $product, $date ) ) {
				++$written;
			}
			$child = 0;
			++$advanced;
		}

		$next_offset   = $offset + $advanced;
		$page_complete = ( $next_child < 1 ) && ( $next_offset >= count( $ids ) );
		$done          = $page_complete && count( $ids ) < self::BATCH_SIZE;
		$next_page     = $page;
		$consumed      = $page_complete ? count( $ids ) : $next_offset;

		if ( $page_complete && ! $done ) {
			$next_page   = $page + 1;
			$next_offset = 0;
		}

		$seen = $done ? $total : ( ( $page - 1 ) * self::BATCH_SIZE ) + $consumed;
		if ( $total < $seen ) {
			$total = $seen;
		}

		if ( $done ) {
			delete_option( self::PENDING_OPTION );
			delete_option( self::PAGE_OPTION );
			delete_option( self::OFFSET_OPTION );
			delete_option( self::CHILD_OPTION );
			delete_option( self::FILL_PROMPT_OPTION );
			if ( class_exists( 'Brendigo_Sidrena_Cijena_Automation' ) ) {
				Brendigo_Sidrena_Cijena_Automation::instance()->queue_rebuild( 'snapshot-complete' );
			}
		} else {
			update_option( self::PAGE_OPTION, $next_page, false );
			update_option( self::OFFSET_OPTION, $next_offset, false );
			update_option( self::CHILD_OPTION, $next_child, false );
			if ( ! wp_next_scheduled( 'sidrena_cijena_snapshot_batch' ) ) {
				wp_schedule_single_event( time() + 2, 'sidrena_cijena_snapshot_batch' );
			}
		}

		return array(
			'done'        => $done,
			'processed'   => $processed,
			'written'     => $written,
			'page'        => $page,
			'next_page'   => $done ? $page : $next_page,
			'next_offset' => $done ? 0 : $next_offset,
			'next_child'  => $done ? 0 : $next_child,
			'total'       => $total,
			'seen'        => $seen,
		);
	}

	/**
	 * Parent IDs for one snapshot page, ordered by ID.
	 *
	 * @param int $page 1-based page.
	 * @return array<int,int>
	 */
	private static function snapshot_ids( $page ) {
		$ids = wc_get_products( self::snapshot_query_args( $page, self::BATCH_SIZE ) );

		return is_array( $ids ) ? array_map( 'absint', $ids ) : array();
	}

	/**
	 * How many parent products the snapshot walks.
	 *
	 * @return int
	 */
	private static function snapshot_total() {
		$query = wc_get_products( self::snapshot_query_args( 1, 1, true ) );
		if ( is_object( $query ) && isset( $query->total ) ) {
			return (int) $query->total;
		}

		return 0;
	}

	/**
	 * Shared product query for the admin/cron snapshot.
	 *
	 * @param int  $page     1-based page.
	 * @param int  $limit    Page size.
	 * @param bool $paginate Return a result object with a total.
	 * @return array<string,mixed>
	 */
	private static function snapshot_query_args( $page, $limit, $paginate = false ) {
		return array(
			'status'   => array( 'publish', 'private', 'draft', 'pending' ),
			'type'     => array( 'simple', 'variable', 'external', 'subscription', 'variable-subscription' ),
			'limit'    => $limit,
			'page'     => max( 1, absint( $page ) ),
			'paginate' => (bool) $paginate,
			'return'   => 'ids',
			'orderby'  => 'ID',
			'order'    => 'ASC',
		);
	}

	/**
	 * Whether a snapshot is still running.
	 *
	 * @return bool
	 */
	public static function is_pending() {
		return 'yes' === get_option( self::PENDING_OPTION );
	}

	/**
	 * Product types whose parent row must not receive a sidrena price.
	 *
	 * @return array<int,string>
	 */
	public static function skip_parent_types() {
		return array( 'variable', 'grouped', 'variable-subscription' );
	}

	/**
	 * Catalog counts for CLI/status: eligible rows, already filled, still empty.
	 *
	 * Eligible = product or variation with a non-empty regular price, excluding
	 * variable / grouped / variable-subscription parents.
	 *
	 * @return array{eligible:int,filled:int,missing:int}
	 */
	public static function catalog_stats() {
		global $wpdb;

		$sql = self::eligible_from_sql();
		// SQL is fully prepared by eligible_from_sql(); this read-only aggregate has no Core API equivalent.
		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

		return array(
			'eligible' => isset( $row['eligible'] ) ? (int) $row['eligible'] : 0,
			'filled'   => isset( $row['filled'] ) ? (int) $row['filled'] : 0,
			'missing'  => isset( $row['missing'] ) ? (int) $row['missing'] : 0,
		);
	}

	/**
	 * Fill every empty sidrena field from regular price, in-process (no AJAX).
	 *
	 * @param array<string,mixed> $args {
	 *     @type bool     $dry_run    Count only.
	 *     @type bool     $fast       SQL bulk (default true).
	 *     @type int      $batch_size PHP path page size.
	 *     @type string   $stamp_date Optional reference date for newly filled rows.
	 *     @type callable $on_page    Optional progress callback( array $page_result ).
	 * }
	 * @return array{written:int,processed:int,updated:int,inserted:int,missing_before:int,dry_run:bool,fast:bool}
	 */
	public static function fill_catalog( $args = array() ) {
		$dry_run    = ! empty( $args['dry_run'] );
		$fast       = ! array_key_exists( 'fast', $args ) || ! empty( $args['fast'] );
		$batch_size = isset( $args['batch_size'] ) ? max( 1, absint( $args['batch_size'] ) ) : 250;
		$stamp_date = isset( $args['stamp_date'] ) ? sanitize_text_field( (string) $args['stamp_date'] ) : '';
		$on_page    = isset( $args['on_page'] ) && is_callable( $args['on_page'] ) ? $args['on_page'] : null;

		$stats_before = self::catalog_stats();

		if ( $fast ) {
			$ids    = '' !== $stamp_date ? self::missing_product_ids() : array();
			$result = self::fast_fill( $dry_run );
			if ( ! $dry_run && '' !== $stamp_date ) {
				self::stamp_empty_dates( $ids, $stamp_date );
			}
			self::clear_pending_state();

			return array(
				'written'        => (int) $result['written'],
				'processed'      => (int) $stats_before['eligible'],
				'updated'        => (int) $result['updated'],
				'inserted'       => (int) $result['inserted'],
				'missing_before' => (int) $stats_before['missing'],
				'dry_run'        => $dry_run,
				'fast'           => true,
			);
		}

		$page      = 1;
		$processed = 0;
		$written   = 0;

		do {
			$batch = self::fill_page( $page, $batch_size, $dry_run, $stamp_date );
			$processed += (int) $batch['processed'];
			$written   += (int) $batch['written'];

			if ( $on_page ) {
				call_user_func( $on_page, $batch );
			}

			++$page;
		} while ( empty( $batch['done'] ) );

		self::clear_pending_state();

		return array(
			'written'        => $written,
			'processed'      => $processed,
			'updated'        => 0,
			'inserted'       => $written,
			'missing_before' => (int) $stats_before['missing'],
			'dry_run'        => $dry_run,
			'fast'           => false,
		);
	}

	/**
	 * One page of the PHP object-based fill (same rules as the admin button).
	 *
	 * @param int    $page       1-based page.
	 * @param int    $batch_size Products per page.
	 * @param bool   $dry_run    Do not write.
	 * @param string $stamp_date Optional reference date for newly filled rows.
	 * @return array{done:bool,processed:int,written:int,page:int}
	 */
	public static function fill_page( $page, $batch_size = 250, $dry_run = false, $stamp_date = '' ) {
		$page       = max( 1, absint( $page ) );
		$batch_size = max( 1, absint( $batch_size ) );

		$ids = wc_get_products(
			array(
				'status'  => array( 'publish', 'private', 'draft', 'pending' ),
				'type'    => array( 'simple', 'variable', 'external', 'subscription', 'variable-subscription' ),
				'limit'   => $batch_size,
				'page'    => $page,
				'return'  => 'ids',
				'orderby' => 'ID',
				'order'   => 'ASC',
			)
		);

		$meta       = Brendigo_Sidrena_Cijena_Product_Meta::instance();
		$stamp_date = is_string( $stamp_date ) ? trim( $stamp_date ) : '';
		$processed  = 0;
		$written    = 0;

		foreach ( $ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			++$processed;

			if ( $product->is_type( 'variable' ) || $product->is_type( 'variable-subscription' ) ) {
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					if ( ! $variation ) {
						continue;
					}
					if ( $dry_run ) {
						if ( $meta->can_snapshot( $variation ) ) {
							++$written;
						}
						continue;
					}
					if ( $meta->snapshot_if_empty( $variation, $stamp_date ) ) {
						++$written;
					}
				}
				continue;
			}

			if ( $dry_run ) {
				if ( $meta->can_snapshot( $product ) ) {
					++$written;
				}
				continue;
			}

			if ( $meta->snapshot_if_empty( $product, $stamp_date ) ) {
				++$written;
			}
		}

		return array(
			'done'      => count( $ids ) < $batch_size,
			'processed' => $processed,
			'written'   => $written,
			'page'      => $page,
		);
	}

	/**
	 * SQL bulk copy of `_regular_price` into empty `_sidrena_cijena`.
	 *
	 * @param bool $dry_run Count only.
	 * @return array{written:int,updated:int,inserted:int}
	 */
	public static function fast_fill( $dry_run = false ) {
		global $wpdb;

		$ids = self::missing_product_ids();

		if ( $dry_run ) {
			return array(
				'written'  => count( $ids ),
				'updated'  => 0,
				'inserted' => 0,
			);
		}

		if ( array() === $ids ) {
			return array(
				'written'  => 0,
				'updated'  => 0,
				'inserted' => 0,
			);
		}

		$price_key = BRENDIGO_SIDRENA_CIJENA_META_PRICE;
		$skip      = self::skip_parent_types();

		$updated_sql = $wpdb->prepare(
			"UPDATE {$wpdb->postmeta} sc
			INNER JOIN {$wpdb->posts} p ON p.ID = sc.post_id
			INNER JOIN {$wpdb->postmeta} rp
				ON rp.post_id = p.ID AND rp.meta_key = '_regular_price' AND rp.meta_value <> ''
			LEFT JOIN (
				SELECT tr.object_id, t.slug
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt
					ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_type'
				INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			) pt ON pt.object_id = p.ID
			SET sc.meta_value = rp.meta_value
			WHERE sc.meta_key = %s
			AND (sc.meta_value IS NULL OR sc.meta_value = '')
			AND p.post_type IN ('product', 'product_variation')
			AND p.post_status IN ('publish', 'private', 'draft', 'pending', 'inherit')
			AND (pt.slug IS NULL OR pt.slug NOT IN (%s, %s, %s))",
			$price_key,
			$skip[0],
			$skip[1],
			$skip[2]
		);
		// Bulk meta maintenance has no equivalent Core API and is intentionally performed in one query.
		$updated = $wpdb->query( $updated_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

		$inserted_sql = $wpdb->prepare(
			"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
			SELECT p.ID, %s, MAX(rp.meta_value)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} rp
				ON rp.post_id = p.ID AND rp.meta_key = '_regular_price' AND rp.meta_value <> ''
			LEFT JOIN {$wpdb->postmeta} sc
				ON sc.post_id = p.ID AND sc.meta_key = %s
			LEFT JOIN (
				SELECT tr.object_id, t.slug
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt
					ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_type'
				INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			) pt ON pt.object_id = p.ID
			WHERE p.post_type IN ('product', 'product_variation')
			AND p.post_status IN ('publish', 'private', 'draft', 'pending', 'inherit')
			AND (pt.slug IS NULL OR pt.slug NOT IN (%s, %s, %s))
			AND sc.meta_id IS NULL
			GROUP BY p.ID",
			$price_key,
			$price_key,
			$skip[0],
			$skip[1],
			$skip[2]
		);
		$inserted = $wpdb->query( $inserted_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

		$updated  = false === $updated ? 0 : (int) $updated;
		$inserted = false === $inserted ? 0 : (int) $inserted;

		self::invalidate_product_cache( $ids );

		return array(
			'written'  => $updated + $inserted,
			'updated'  => $updated,
			'inserted' => $inserted,
		);
	}

	/**
	 * Product IDs (and variation IDs) that still need a sidrena price.
	 *
	 * @return array<int,int>
	 */
	public static function missing_product_ids() {
		global $wpdb;

		$price_key = BRENDIGO_SIDRENA_CIJENA_META_PRICE;
		$skip      = self::skip_parent_types();
		$sql       = $wpdb->prepare(
			"SELECT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} rp
				ON rp.post_id = p.ID AND rp.meta_key = '_regular_price' AND rp.meta_value <> ''
			LEFT JOIN {$wpdb->postmeta} sc
				ON sc.post_id = p.ID AND sc.meta_key = %s
			LEFT JOIN (
				SELECT tr.object_id, t.slug
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt
					ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_type'
				INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			) pt ON pt.object_id = p.ID
			WHERE p.post_type IN ('product', 'product_variation')
			AND p.post_status IN ('publish', 'private', 'draft', 'pending', 'inherit')
			AND (pt.slug IS NULL OR pt.slug NOT IN (%s, %s, %s))
			GROUP BY p.ID
			HAVING MAX(CASE WHEN sc.meta_value IS NOT NULL AND sc.meta_value <> '' THEN 1 ELSE 0 END) = 0",
			$price_key,
			$skip[0],
			$skip[1],
			$skip[2]
		);

		$ids = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_map( 'intval', $ids );
	}

	/**
	 * Write a reference date only where sidrena date is still empty.
	 *
	 * @param array<int,int> $ids  Product or variation IDs.
	 * @param string         $date Date string (d.m.Y.).
	 */
	private static function stamp_empty_dates( $ids, $date ) {
		$date = sanitize_text_field( $date );
		if ( '' === $date || ! is_array( $ids ) || array() === $ids ) {
			return;
		}

		$meta = Brendigo_Sidrena_Cijena_Product_Meta::instance();

		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id < 1 ) {
				continue;
			}
			if ( '' !== $meta->get_date( $id ) ) {
				continue;
			}
			$meta->set_date( $id, $date );
		}

		self::invalidate_product_cache( $ids );
	}

	/**
	 * Drop activation/admin snapshot cursor so the admin notice does not linger.
	 */
	public static function clear_pending_state() {
		delete_option( self::PENDING_OPTION );
		delete_option( self::PAGE_OPTION );
		delete_option( self::OFFSET_OPTION );
		delete_option( self::CHILD_OPTION );
		delete_option( self::FILL_PROMPT_OPTION );
		wp_clear_scheduled_hook( 'sidrena_cijena_snapshot_batch' );
	}

	/**
	 * Drop stale post-meta cache for IDs written via SQL.
	 *
	 * @param array<int,int> $ids Product or variation IDs.
	 */
	private static function invalidate_product_cache( $ids ) {
		if ( class_exists( 'WC_Cache_Helper' ) && method_exists( 'WC_Cache_Helper', 'get_transient_version' ) ) {
			WC_Cache_Helper::get_transient_version( 'product', true );
		}

		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id < 1 ) {
				continue;
			}
			wp_cache_delete( $id, 'post_meta' );
			clean_post_cache( $id );
		}
	}

	/**
	 * Prepared aggregate SQL for catalog stats.
	 *
	 * @return string
	 */
	private static function eligible_from_sql() {
		global $wpdb;

		$price_key = BRENDIGO_SIDRENA_CIJENA_META_PRICE;
		$skip      = self::skip_parent_types();

		return $wpdb->prepare(
			"SELECT
				COUNT(*) AS eligible,
				SUM(has_sidrena) AS filled,
				SUM(1 - has_sidrena) AS missing
			FROM (
				SELECT p.ID,
					CASE WHEN MAX(CASE WHEN sc.meta_value IS NOT NULL AND sc.meta_value <> '' THEN 1 ELSE 0 END) = 1 THEN 1 ELSE 0 END AS has_sidrena
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} rp
					ON rp.post_id = p.ID AND rp.meta_key = '_regular_price' AND rp.meta_value <> ''
				LEFT JOIN {$wpdb->postmeta} sc
					ON sc.post_id = p.ID AND sc.meta_key = %s
				LEFT JOIN (
					SELECT tr.object_id, t.slug
					FROM {$wpdb->term_relationships} tr
					INNER JOIN {$wpdb->term_taxonomy} tt
						ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_type'
					INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				) pt ON pt.object_id = p.ID
				WHERE p.post_type IN ('product', 'product_variation')
				AND p.post_status IN ('publish', 'private', 'draft', 'pending', 'inherit')
				AND (pt.slug IS NULL OR pt.slug NOT IN (%s, %s, %s))
				GROUP BY p.ID
			) sidrena_rows",
			$price_key,
			$skip[0],
			$skip[1],
			$skip[2]
		);
	}

}
