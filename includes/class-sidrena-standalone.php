<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sidrena_Standalone {
	const POST_TYPE = 'sidrena_item';

	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'admin_post_sidrena_standalone_save', array( $this, 'save' ) );
		add_action( 'admin_post_sidrena_standalone_import', array( $this, 'import' ) );
		add_action( 'admin_post_sidrena_standalone_sync_source', array( $this, 'start_source_sync' ) );
		add_action( 'sidrena_standalone_sync_batch', array( $this, 'sync_source_batch' ), 10, 3 );
		add_action( 'save_post', array( $this, 'sync_linked_source_on_save' ), 30, 3 );
		add_filter( 'the_content', array( $this, 'append_reference_to_linked_content' ), 25 );
		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			add_shortcode( 'sidrena_cijena', array( $this, 'price_shortcode' ) );
			add_shortcode( 'sidrena-cijena', array( $this, 'price_shortcode' ) );
			add_action( 'sidrena_cijena', array( $this, 'action_output' ), 10, 1 );
		}
	}

	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Sidrena proizvodi', 'sidrena' ),
					'singular_name' => __( 'Sidrena proizvod', 'sidrena' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title' ),
				'map_meta_cap'        => true,
			)
		);
	}

	public static function rows( $location = array() ) {
		$rows = array();
		foreach ( self::iterate_rows( $location ) as $row ) {
			$rows[] = $row;
		}
		return $rows;
	}

	public static function iterate_rows( $location = array(), $batch_size = 200 ) {
		$batch_size = min( 500, max( 20, absint( $batch_size ) ) );
		$page       = 1;

		do {
			$query = new WP_Query(
				array(
					'post_type'              => self::POST_TYPE,
					'post_status'            => 'publish',
					'posts_per_page'         => $batch_size,
					'paged'                  => $page,
					'orderby'                => array(
						'menu_order' => 'ASC',
						'ID'         => 'ASC',
					),
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
				)
			);

			$batch = array();
			foreach ( $query->posts as $post ) {
				$row = self::export_row( $post, $location );
				if ( $row ) {
					$batch[] = $row;
				}
			}
			$batch = apply_filters( 'sidrena_standalone_rows', $batch, $location );
			foreach ( $batch as $row ) {
				if ( is_array( $row ) ) {
					yield $row;
				}
			}

			$count = count( $query->posts );
			wp_reset_postdata();
			++$page;
		} while ( $count === $batch_size );
	}

	public static function paged_rows( $page = 1, $per_page = 100, $location = array() ) {
		$page     = max( 1, absint( $page ) );
		$per_page = min( 100, max( 1, absint( $per_page ) ) );
		$query    = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
			)
		);

		$rows = array();
		foreach ( $query->posts as $post ) {
			$row = self::export_row( $post, $location );
			if ( $row ) {
				$rows[] = $row;
			}
		}
		wp_reset_postdata();

		return array(
			'items'       => apply_filters( 'sidrena_standalone_rows', $rows, $location ),
			'total'       => absint( $query->found_posts ),
			'total_pages' => absint( $query->max_num_pages ),
		);
	}

	private static function export_row( $post, $location = array() ) {
		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return array();
		}
		$id              = $post->ID;
		$current         = get_post_meta( $id, '_sidrena_standalone_current_price', true );
		$anchor          = get_post_meta( $id, '_sidrena_standalone_anchor_price', true );
		$reference_group = Sidrena_Utils::sanitize_reference_group( get_post_meta( $id, '_sidrena_standalone_reference_group', true ) );
		$anchor_date     = 'custom' === $reference_group ? Sidrena_Utils::verified_custom_reference_date_for_post( $id, get_post_meta( $id, '_sidrena_standalone_anchor_date', true ) ) : '';
		$unit_status     = sanitize_key( (string) get_post_meta( $id, '_sidrena_standalone_unit_status', true ) );
		$sale_name       = trim( (string) get_post_meta( $id, '_sidrena_standalone_sale_name', true ) );
		$availability    = sanitize_key( (string) get_post_meta( $id, '_sidrena_standalone_availability', true ) );
		if ( ! in_array( $availability, array( 'dostupno', 'nedostupno' ), true ) ) {
			$availability = 'dostupno';
		}

		$location_id           = Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' );
		$location_kind         = sanitize_key( (string) ( $location['kind'] ?? 'objekt' ) );
		$location_availability = get_post_meta( $id, '_sidrena_standalone_location_availability', true );
		$location_availability = is_array( $location_availability ) ? $location_availability : array();
		$has_location_status   = $location_id && isset( $location_availability[ $location_id ] ) && in_array( $location_availability[ $location_id ], array( 'dostupno', 'nedostupno' ), true );
		if ( $has_location_status ) {
			$availability = $location_availability[ $location_id ];
		}

		$unit       = get_post_meta( $id, '_sidrena_standalone_unit', true );
		$unit_price = get_post_meta( $id, '_sidrena_standalone_unit_price', true );
		if ( in_array( $unit_status, array( 'not_required', 'exception' ), true ) ) {
			$unit       = '';
			$unit_price = '';
		}

		return array(
			'_sidrena_item_id'              => $id,
			'_sidrena_unit_status'          => $unit_status ? $unit_status : 'review',
			'_sidrena_location_explicit'    => ( $has_location_status || 'webshop' === $location_kind ) ? 'yes' : 'no',
			'naziv'                         => get_the_title( $post ),
			'sifra'                         => get_post_meta( $id, '_sidrena_standalone_code', true ),
			'marka'                         => get_post_meta( $id, '_sidrena_standalone_brand', true ),
			'jedinica_mjere'                => $unit,
			'cijena_za_jedinicu_mjere'      => Sidrena_Utils::money( $unit_price, 4 ),
			'maloprodajna_cijena'           => Sidrena_Utils::money( $current ),
			'posebni_oblik_prodaje'         => $sale_name ? 'da' : 'ne',
			'naziv_posebnog_oblika_prodaje' => $sale_name,
			'sidrena_cijena'                => Sidrena_Utils::money( $anchor ),
			'datum_sidrene_cijene'          => '' === Sidrena_Utils::decimal( $anchor ) ? '' : Sidrena_Utils::date_display( Sidrena_Utils::resolved_reference_date( $reference_group, $anchor_date ) ),
			'barkod'                        => get_post_meta( $id, '_sidrena_standalone_barcode', true ),
			'dostupnost'                    => $availability,
		);
	}

	public static function count() {
		$counts = wp_count_posts( self::POST_TYPE );
		return $counts && isset( $counts->publish ) ? absint( $counts->publish ) : 0;
	}

	public static function audit_stats() {
		$stats = array(
			'products'           => 0,
			'missing_current'    => 0,
			'missing_anchor'     => 0,
			'missing_brand'      => 0,
			'missing_barcode'    => 0,
			'unit_price_review'  => 0,
			'unit_price_missing' => 0,
			'active_sales'       => 0,
		);

		foreach ( self::iterate_rows() as $row ) {
			++$stats['products'];
			if ( '' === Sidrena_Utils::decimal( $row['maloprodajna_cijena'] ?? '' ) ) {
				++$stats['missing_current'];
			}
			if ( '' === trim( (string) $row['sidrena_cijena'] ) ) {
				++$stats['missing_anchor'];
			}
			if ( '' === trim( (string) $row['marka'] ) ) {
				++$stats['missing_brand'];
			}
			if ( '' === trim( (string) $row['barkod'] ) ) {
				++$stats['missing_barcode'];
			}
			$status = sanitize_key( (string) $row['_sidrena_unit_status'] );
			if ( ! $status || 'review' === $status ) {
				++$stats['unit_price_review'];
			} elseif ( 'required' === $status && ( '' === trim( (string) $row['jedinica_mjere'] ) || '' === trim( (string) $row['cijena_za_jedinicu_mjere'] ) ) ) {
				++$stats['unit_price_missing'];
			}
			if ( 'da' === $row['posebni_oblik_prodaje'] ) {
				++$stats['active_sales'];
			}
		}
		return $stats;
	}

	private function source_post_types() {
		$objects = get_post_types( array( 'public' => true ), 'objects' );
		$out     = array();
		foreach ( is_array( $objects ) ? $objects : array() as $name => $object ) {
			$name = sanitize_key( $name );
			if ( ! $name || in_array( $name, array( 'attachment', self::POST_TYPE, 'sidrena_service' ), true ) ) {
				continue;
			}
			if ( 'product' === $name && class_exists( 'WooCommerce' ) ) {
				continue;
			}
			$label        = is_object( $object ) && ! empty( $object->labels->name ) ? $object->labels->name : $name;
			$out[ $name ] = sanitize_text_field( $label );
		}
		asort( $out, SORT_NATURAL | SORT_FLAG_CASE );
		return $out;
	}

	private function linked_item_id( $source_post_id ) {
		$source_post_id = absint( $source_post_id );
		if ( ! $source_post_id ) {
			return 0;
		}
		// This is a bounded one-row reverse lookup for a unique source/content link.
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_sidrena_standalone_source_post_id',
				'meta_value'     => $source_post_id,
				'no_found_rows'  => true,
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		return ! empty( $ids ) ? absint( $ids[0] ) : 0;
	}

	private function source_price( $source_post_id, $preferred_key = '' ) {
		$source_post_id = absint( $source_post_id );
		$preferred_key  = sanitize_key( (string) $preferred_key );
		$keys           = array();
		if ( $preferred_key ) {
			$keys[] = $preferred_key;
		}
		$keys = array_merge( $keys, array( '_price', 'price', 'cijena', 'product_price', '_regular_price', 'regular_price' ) );
		$keys = array_values( array_unique( $keys ) );
		foreach ( $keys as $key ) {
			$value = Sidrena_Utils::decimal( get_post_meta( $source_post_id, $key, true ) );
			if ( '' !== $value ) {
				return array(
					'price' => $value,
					'key'   => $key,
				);
			}
		}
		return array(
			'price' => '',
			'key'   => $preferred_key,
		);
	}

	public function start_source_sync() {
		if ( ! Sidrena_Utils::current_user_can_manage() || ! check_admin_referer( 'sidrena_standalone_sync_source' ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}
		$post_type = isset( $_POST['source_post_type'] ) ? sanitize_key( wp_unslash( $_POST['source_post_type'] ) ) : '';
		$price_key = isset( $_POST['source_price_key'] ) ? sanitize_key( wp_unslash( $_POST['source_price_key'] ) ) : '';
		$types     = $this->source_post_types();
		if ( ! $post_type || ! isset( $types[ $post_type ] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=sidrena-catalog&sid_notice=standalone_sync_failed' ) );
			exit;
		}

		update_option(
			'sidrena_standalone_sync_state',
			array(
				'post_type'  => $post_type,
				'price_key'  => $price_key,
				'status'     => 'queued',
				'page'       => 1,
				'created'    => 0,
				'updated'    => 0,
				'skipped'    => 0,
				'started_at' => current_time( 'mysql' ),
			),
			false
		);
		$scheduled = wp_schedule_single_event( time() + 2, 'sidrena_standalone_sync_batch', array( $post_type, 1, $price_key ) );
		if ( false === $scheduled || is_wp_error( $scheduled ) ) {
			update_option(
				'sidrena_standalone_sync_state',
				array(
					'post_type' => $post_type,
					'status'    => 'error',
				),
				false
			);
			wp_safe_redirect( admin_url( 'admin.php?page=sidrena-catalog&sid_notice=standalone_sync_failed' ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=sidrena-catalog&sid_notice=standalone_sync_started' ) );
		exit;
	}

	public function sync_source_batch( $post_type, $page = 1, $price_key = '' ) {
		$post_type = sanitize_key( (string) $post_type );
		$page      = max( 1, absint( $page ) );
		$price_key = sanitize_key( (string) $price_key );
		if ( ! post_type_exists( $post_type ) || in_array( $post_type, array( 'attachment', self::POST_TYPE, 'sidrena_service' ), true ) ) {
			return;
		}

		$source_ids = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => 100,
				'paged'                  => $page,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$state   = get_option( 'sidrena_standalone_sync_state', array() );
		$created = absint( $state['created'] ?? 0 );
		$updated = absint( $state['updated'] ?? 0 );
		$skipped = absint( $state['skipped'] ?? 0 );

		foreach ( is_array( $source_ids ) ? $source_ids : array() as $source_id ) {
			$source_id = absint( $source_id );
			if ( ! $source_id ) {
				++$skipped;
				continue;
			}
			$item_id    = $this->linked_item_id( $source_id );
			$title      = sanitize_text_field( get_the_title( $source_id ) );
			$stored_key = $item_id ? sanitize_key( get_post_meta( $item_id, '_sidrena_standalone_source_price_key', true ) ) : '';
			$lookup_key = $price_key ? $price_key : $stored_key;
			$price      = $this->source_price( $source_id, $lookup_key );
			$current    = $item_id ? Sidrena_Utils::decimal( get_post_meta( $item_id, '_sidrena_standalone_current_price', true ) ) : '';
			if ( '' !== $price['price'] ) {
				$current = $price['price'];
			} elseif ( $lookup_key ) {
				$current = '';
			}
			$post_status = $title && '' !== $current ? 'publish' : 'draft';

			if ( $item_id ) {
				$result = wp_update_post(
					array(
						'ID'          => $item_id,
						'post_title'  => $title ? $title : __( 'Proizvod bez naziva', 'sidrena' ),
						'post_status' => $post_status,
					),
					true
				);
				if ( is_wp_error( $result ) ) {
					++$skipped;
					continue;
				}
				++$updated;
			} else {
				$item_id = wp_insert_post(
					array(
						'post_type'   => self::POST_TYPE,
						'post_status' => $post_status,
						'post_title'  => $title ? $title : __( 'Proizvod bez naziva', 'sidrena' ),
					),
					true
				);
				if ( is_wp_error( $item_id ) || ! $item_id ) {
					++$skipped;
					continue;
				}
				++$created;
				update_post_meta( $item_id, '_sidrena_standalone_code', 'WP-' . $source_id );
			}
			update_post_meta( $item_id, '_sidrena_standalone_source_post_id', $source_id );
			update_post_meta( $item_id, '_sidrena_standalone_source_post_type', $post_type );
			if ( $price['key'] ) {
				update_post_meta( $item_id, '_sidrena_standalone_source_price_key', $price['key'] );
			}
			if ( '' !== $current ) {
				update_post_meta( $item_id, '_sidrena_standalone_current_price', $current );
			} elseif ( $lookup_key ) {
				delete_post_meta( $item_id, '_sidrena_standalone_current_price' );
			}
			if ( '' === get_post_meta( $item_id, '_sidrena_standalone_availability', true ) ) {
				update_post_meta( $item_id, '_sidrena_standalone_availability', 'dostupno' );
			}
		}

		$state = array(
			'post_type'  => $post_type,
			'price_key'  => $price_key,
			'status'     => count( $source_ids ) === 100 ? 'running' : 'complete',
			'page'       => $page,
			'created'    => $created,
			'updated'    => $updated,
			'skipped'    => $skipped,
			'updated_at' => current_time( 'mysql' ),
		);
		update_option( 'sidrena_standalone_sync_state', $state, false );

		if ( count( $source_ids ) === 100 ) {
			$scheduled = wp_schedule_single_event( time() + 3, 'sidrena_standalone_sync_batch', array( $post_type, $page + 1, $price_key ) );
			if ( false === $scheduled || is_wp_error( $scheduled ) ) {
				$state['status']     = 'error';
				$state['updated_at'] = current_time( 'mysql' );
				update_option( 'sidrena_standalone_sync_state', $state, false );
				Sidrena_Audit::log(
					'wordpress_catalog_sync',
					'error',
					__( 'Sinkronizacija WordPress sadržaja zaustavljena je jer sljedeći batch nije bilo moguće zakazati.', 'sidrena' ),
					$state
				);
			}
			return;
		}

		Sidrena_Audit::log(
			'wordpress_catalog_sync',
			'success',
			/* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */
			sprintf( __( 'Sinkronizacija WordPress sadržaja dovršena: %1$d novih, %2$d ažuriranih, %3$d preskočenih.', 'sidrena' ), $created, $updated, $skipped ),
			$state
		);
		Sidrena_Pricelist::queue_regeneration();
	}

	public function sync_linked_source_on_save( $post_id, $post, $update ) {
		unset( $update );
		$post_id = absint( $post_id );
		if ( ! $post_id || ! $post instanceof WP_Post || self::POST_TYPE === $post->post_type || 'sidrena_service' === $post->post_type || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$item_id = $this->linked_item_id( $post_id );
		if ( ! $item_id ) {
			return;
		}
		$price_key = sanitize_key( get_post_meta( $item_id, '_sidrena_standalone_source_price_key', true ) );
		$price     = $this->source_price( $post_id, $price_key );
		$changed   = false;
		$old       = Sidrena_Utils::decimal( get_post_meta( $item_id, '_sidrena_standalone_current_price', true ) );
		if ( '' !== $price['price'] ) {
			if ( $price['price'] !== $old ) {
				update_post_meta( $item_id, '_sidrena_standalone_current_price', $price['price'] );
				$changed = true;
			}
		} elseif ( $price_key && '' !== $old ) {
			delete_post_meta( $item_id, '_sidrena_standalone_current_price' );
			$changed = true;
		}
		$title = sanitize_text_field( get_the_title( $post_id ) );
		if ( $title && get_the_title( $item_id ) !== $title ) {
			wp_update_post(
				array(
					'ID'         => $item_id,
					'post_title' => $title,
				)
			);
			$changed = true;
		}
		$current = Sidrena_Utils::decimal( get_post_meta( $item_id, '_sidrena_standalone_current_price', true ) );
		$desired = 'publish' === $post->post_status && '' !== $current ? 'publish' : 'draft';
		if ( get_post_status( $item_id ) !== $desired ) {
			wp_update_post(
				array(
					'ID'          => $item_id,
					'post_status' => $desired,
				)
			);
			$changed = true;
		}
		if ( $changed ) {
			Sidrena_Pricelist::queue_regeneration();
		}
	}

	public function append_reference_to_linked_content( $content ) {
		if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$source_id = get_the_ID();
		$item_id   = $this->linked_item_id( $source_id );
		if ( ! $item_id || 'publish' !== get_post_status( $item_id ) ) {
			return $content;
		}
		if ( function_exists( 'has_shortcode' ) && ( has_shortcode( $content, 'sidrena_cijena' ) || has_shortcode( $content, 'sidrena-cijena' ) ) ) {
			return $content;
		}
		$reference = $this->price_shortcode(
			array(
				'id'           => 's' . $item_id,
				'show_current' => 'no',
			)
		);
		if ( ! $reference ) {
			return $content;
		}
		return $content . '<div class="sidrena-auto-reference">' . $reference . '</div>';
	}

	public function render() {
		if ( ! Sidrena_Utils::current_user_can_manage() ) {
			return;
		}

		$page                  = max( 1, isset( $_GET['standalone_page'] ) ? absint( $_GET['standalone_page'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page              = 60;
		$query                 = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
		$items                 = is_array( $query->posts ) ? $query->posts : array();
		$pages                 = max( 1, absint( $query->max_num_pages ) );
		$total                 = absint( $query->found_posts );
		$product_count_caption = sprintf(
			/* translators: %d: number of products in the standalone WordPress catalog. */
			_n( '%d proizvod', '%d proizvoda', $total, 'sidrena' ),
			$total
		);
		?>
		<div class="sid-page-head">
			<div>
				<span class="sid-kicker"><?php esc_html_e( 'WordPress katalog', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Postojeći proizvodi i WordPress katalog', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Možete voditi proizvode izravno u Sidreni ili povući postojeći WordPress tip sadržaja. Povezanim zapisima Sidrena automatski prikazuje sidrenu cijenu na njihovoj javnoj stranici.', 'sidrena' ); ?></p>
			</div>
			<span class="sid-status-pill"><?php echo esc_html( $product_count_caption ); ?></span>
		</div>
		<?php
		$source_types = $this->source_post_types();
		$sync_state   = get_option( 'sidrena_standalone_sync_state', array() );
		$sync_status  = sanitize_key( (string) ( $sync_state['status'] ?? '' ) );
		$sync_labels  = array(
			'queued'   => __( 'Na čekanju', 'sidrena' ),
			'running'  => __( 'Sinkronizacija u tijeku', 'sidrena' ),
			'complete' => __( 'Dovršeno', 'sidrena' ),
			'error'    => __( 'Greška', 'sidrena' ),
		);
		$sync_label   = $sync_labels[ $sync_status ] ?? '';
		$sync_class   = 'complete' === $sync_status ? 'is-ok' : ( 'error' === $sync_status ? 'is-error' : 'is-warn' );
		$sync_summary = '';
		if ( ! empty( $sync_state['created'] ) || ! empty( $sync_state['updated'] ) || ! empty( $sync_state['skipped'] ) ) {
			$sync_summary = sprintf(
				/* translators: 1: created products, 2: updated products, 3: skipped products. */
				__( 'Zadnja sinkronizacija: %1$d novih, %2$d ažuriranih, %3$d preskočenih.', 'sidrena' ),
				absint( $sync_state['created'] ?? 0 ),
				absint( $sync_state['updated'] ?? 0 ),
				absint( $sync_state['skipped'] ?? 0 )
			);
		}
		$page_caption = sprintf(
			/* translators: 1: current catalog page, 2: total number of catalog pages. */
			__( 'Stranica %1$d od %2$d', 'sidrena' ),
			min( $page, $pages ),
			$pages
		);
		?>
		<section class="sid-card sid-source-sync">
			<div class="sid-section-head">
				<div><span class="sid-kicker"><?php esc_html_e( 'Automatsko povezivanje', 'sidrena' ); ?></span><h2><?php esc_html_e( 'Povuci postojeće proizvode / sadržaj', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Odaberite postojeći tip sadržaja. Sidrena će povući nazive, povezati zapise i pokušati prepoznati postojeće polje cijene. Nakon toga u pravilu trebate dopuniti samo sidrenu cijenu i ostale obvezne podatke koji nedostaju.', 'sidrena' ); ?></p></div>
				<?php
				if ( $sync_label ) :
					?>
					<span class="sid-status-pill <?php echo esc_attr( $sync_class ); ?>"><?php echo esc_html( $sync_label ); ?></span><?php endif; ?>
			</div>
			<form class="sid-form sid-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sidrena_standalone_sync_source">
				<?php wp_nonce_field( 'sidrena_standalone_sync_source' ); ?>
				<label><span><?php esc_html_e( 'Tip sadržaja', 'sidrena' ); ?></span><select name="source_post_type" required><option value=""><?php esc_html_e( 'Odaberite…', 'sidrena' ); ?></option>
				<?php
				foreach ( $source_types as $source_name => $source_label ) :
					?>
					<option value="<?php echo esc_attr( $source_name ); ?>"><?php echo esc_html( $source_label . ' (' . $source_name . ')' ); ?></option><?php endforeach; ?></select></label>
				<label><span><?php esc_html_e( 'Meta ključ postojeće cijene', 'sidrena' ); ?></span><input type="text" name="source_price_key" placeholder="_price / price / cijena"><small><?php esc_html_e( 'Ostavite prazno za automatsko prepoznavanje.', 'sidrena' ); ?></small></label>
				<button type="submit" class="button sid-secondary"><span class="dashicons dashicons-update"></span><?php esc_html_e( 'Pokreni sinkronizaciju', 'sidrena' ); ?></button>
			</form>
			<?php
			if ( $sync_summary ) :
				?>
				<p class="description"><?php echo esc_html( $sync_summary ); ?></p><?php endif; ?>
		</section>
		<form class="sid-card sid-form sid-standalone-import" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sidrena_standalone_import">
			<?php wp_nonce_field( 'sidrena_standalone_import' ); ?>
			<div class="sid-section-head">
				<div><span class="sid-kicker"><?php esc_html_e( 'Masovni uvoz', 'sidrena' ); ?></span><h2><?php esc_html_e( 'CSV/XML katalog', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Podržani su hrvatski i tehnički nazivi stupaca/čvorova. Postojeće stavke povezuju se po šifri; nove zahtijevaju naziv, cijenu i sidrenu cijenu.', 'sidrena' ); ?></p></div>
			</div>
			<div class="sid-form-actions">
				<label class="sid-file-control"><span><?php esc_html_e( 'CSV ili XML datoteka', 'sidrena' ); ?></span><input class="sid-file-input" type="file" name="standalone_file" accept=".csv,.xml,text/csv,text/xml,application/xml" aria-describedby="sid-standalone-file-help" required><small id="sid-standalone-file-help"><?php esc_html_e( 'Najviše 5 MB. Podržani su CSV i XML formati bez izvršnog sadržaja.', 'sidrena' ); ?></small></label>
				<button type="submit" class="button sid-secondary"><span class="dashicons dashicons-upload"></span><?php esc_html_e( 'Uvezi katalog', 'sidrena' ); ?></button>
			</div>
		</form>

		<form class="sid-card sid-form sid-standalone-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sidrena_standalone_save">
			<input type="hidden" name="standalone_page" value="<?php echo esc_attr( $page ); ?>">
			<?php wp_nonce_field( 'sidrena_standalone_save' ); ?>
			<div class="sid-table-wrap">
				<table class="widefat striped sid-bulk-table sid-standalone-table">
					<caption class="screen-reader-text"><?php esc_html_e( 'Sidrena WordPress katalog proizvoda', 'sidrena' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Proizvod / usluga', 'sidrena' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Šifra', 'sidrena' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Trenutna cijena', 'sidrena' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Cijena na datum', 'sidrena' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Dostupnost', 'sidrena' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'sidrena' ); ?></th>
						</tr>
					</thead>
					<tbody id="sidrena-standalone-rows">
						<?php foreach ( $items as $post ) : ?>
							<?php $this->row( $post->ID ); ?>
						<?php endforeach; ?>
						<?php if ( 0 === $total ) : ?>
							<tr class="sid-catalog-empty-row"><td colspan="6" class="sid-table-empty-cell"><strong><?php esc_html_e( 'Katalog je spreman za prvi stvarni proizvod.', 'sidrena' ); ?></strong><span><?php esc_html_e( 'Povežite postojeći WordPress sadržaj, uvezite CSV/XML ili kliknite “Dodaj proizvod”. Sidrena ne umeće demo ni izmišljene podatke.', 'sidrena' ); ?></span></td></tr>
						<?php elseif ( empty( $items ) ) : ?>
							<tr><td colspan="6" class="sid-table-empty-cell"><strong><?php esc_html_e( 'Na ovoj stranici nema proizvoda.', 'sidrena' ); ?></strong><span><?php esc_html_e( 'Vratite se na prethodnu stranicu kataloga ili dodajte novi stvarni proizvod.', 'sidrena' ); ?></span></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
			<div class="sid-form-actions">
				<button type="button" class="button sid-secondary" id="sidrena-add-standalone"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e( 'Dodaj proizvod', 'sidrena' ); ?></button>
				<button type="submit" class="button button-primary sid-primary"><span class="dashicons dashicons-saved"></span><?php esc_html_e( 'Spremi ovu stranicu kataloga', 'sidrena' ); ?></button>
			</div>
			<template id="sidrena-standalone-template"><?php $this->row( 0, '__KEY__', true ); ?></template>
		</form>
		<?php if ( $pages > 1 ) : ?>
		<nav class="sid-pagination" aria-label="<?php esc_attr_e( 'Navigacija WordPress kataloga', 'sidrena' ); ?>">
			<?php
			if ( $page > 1 ) :
				?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-catalog&standalone_page=' . ( $page - 1 ) ) ); ?>">← <?php esc_html_e( 'Prethodna', 'sidrena' ); ?></a><?php endif; ?>
			<span><?php echo esc_html( $page_caption ); ?></span>
			<?php
			if ( $page < $pages ) :
				?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-catalog&standalone_page=' . ( $page + 1 ) ) ); ?>"><?php esc_html_e( 'Sljedeća', 'sidrena' ); ?> →</a><?php endif; ?>
		</nav>
		<?php endif; ?>
		<?php
	}
	private function row( $id = 0, $key = '', $template = false ) {
		unset( $template );
		$id                       = absint( $id );
		$key                      = $id ? (string) $id : ( $key ? $key : uniqid( 'new-', false ) );
		$meta                     = static function ( $name ) use ( $id ) {
			return $id ? get_post_meta( $id, $name, true ) : '';
		};
		$status_raw               = $meta( '_sidrena_standalone_unit_status' );
		$status                   = $status_raw ? $status_raw : 'review';
		$availability_raw         = $meta( '_sidrena_standalone_availability' );
		$availability             = $availability_raw ? $availability_raw : 'dostupno';
		$current                  = $meta( '_sidrena_standalone_current_price' );
		$anchor                   = $meta( '_sidrena_standalone_anchor_price' );
		$reference_group          = Sidrena_Utils::sanitize_reference_group( $meta( '_sidrena_standalone_reference_group' ) );
		$anchor_date              = 'custom' === $reference_group ? Sidrena_Utils::verified_custom_reference_date_for_post( $id, $meta( '_sidrena_standalone_anchor_date' ) ) : '';
		$location_availability    = $meta( '_sidrena_standalone_location_availability' );
		$location_availability    = is_array( $location_availability ) ? $location_availability : array();
		$locations                = Sidrena_Utils::locations();
		$sale_name                = trim( (string) $meta( '_sidrena_standalone_sale_name' ) );
		$physical_locations_ready = true;
		foreach ( $locations as $location ) {
			if ( 'yes' !== ( $location['enabled'] ?? '' ) || 'webshop' === sanitize_key( (string) ( $location['kind'] ?? 'objekt' ) ) ) {
				continue;
			}
			$location_id = Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' );
			if ( ! $location_id || ! isset( $location_availability[ $location_id ] ) || ! in_array( $location_availability[ $location_id ], array( 'dostupno', 'nedostupno' ), true ) ) {
				$physical_locations_ready = false;
				break;
			}
		}
		$row_ready = $id
			&& '' !== Sidrena_Utils::decimal( $current )
			&& '' !== Sidrena_Utils::decimal( $anchor )
			&& '' !== trim( (string) $meta( '_sidrena_standalone_code' ) )
			&& '' !== trim( (string) $meta( '_sidrena_standalone_brand' ) )
			&& 'review' !== $status
			&& $physical_locations_ready
			&& ( 'custom' !== $reference_group || '' !== $anchor_date );
		?>
		<tr class="sidrena-standalone-row" data-sidrena-row-key="<?php echo esc_attr( $key ); ?>">
			<td>
				<input type="hidden" name="items[<?php echo esc_attr( $key ); ?>][id]" value="<?php echo esc_attr( $id ); ?>">
				<input type="text" name="items[<?php echo esc_attr( $key ); ?>][name]" value="<?php echo esc_attr( $id ? get_the_title( $id ) : '' ); ?>" placeholder="<?php esc_attr_e( 'Naziv proizvoda', 'sidrena' ); ?>">
				<?php if ( $id ) : ?>
					<small class="sid-bulk-meta"><code>[sidrena_cijena id="s<?php echo esc_attr( $id ); ?>"]</code>
					<?php
					$source_id = absint( $meta( '_sidrena_standalone_source_post_id' ) ); if ( $source_id ) :
						?>
						· <a href="<?php echo esc_url( get_permalink( $source_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Povezana stranica', 'sidrena' ); ?> #<?php echo esc_html( $source_id ); ?></a><?php endif; ?></small>
				<?php endif; ?>
			</td>
			<td><input aria-label="<?php esc_attr_e( 'Šifra', 'sidrena' ); ?>" type="text" name="items[<?php echo esc_attr( $key ); ?>][code]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_code' ) ); ?>"></td>
			<td><input aria-label="<?php esc_attr_e( 'Trenutna cijena', 'sidrena' ); ?>" type="number" min="0" step="0.01" name="items[<?php echo esc_attr( $key ); ?>][current]" value="<?php echo esc_attr( $current ); ?>"></td>
			<td class="sid-standalone-anchor-cell">
				<input aria-label="<?php esc_attr_e( 'Sidrena cijena', 'sidrena' ); ?>" type="number" min="0" step="0.01" name="items[<?php echo esc_attr( $key ); ?>][anchor]" value="<?php echo esc_attr( $anchor ); ?>">
				<?php if ( 'custom' === $reference_group ) : ?>
					<input type="hidden" name="items[<?php echo esc_attr( $key ); ?>][reference_group]" value="custom">
					<strong><?php echo esc_html( Sidrena_Utils::date_display( $anchor_date ) ); ?></strong>
					<small class="sid-cell-sub"><?php esc_html_e( 'Automatski zaključano iz dokazivog prvog objavljivanja stavke.', 'sidrena' ); ?></small>
				<?php else : ?>
					<select aria-label="<?php esc_attr_e( 'Pravni datum sidrene cijene', 'sidrena' ); ?>" name="items[<?php echo esc_attr( $key ); ?>][reference_group]">
						<option value="standard" <?php selected( $reference_group, 'standard' ); ?>><?php esc_html_e( '10.09.2026.', 'sidrena' ); ?></option>
						<option value="fmcg" <?php selected( $reference_group, 'fmcg' ); ?>><?php esc_html_e( 'FMCG 02.05.2025.', 'sidrena' ); ?></option>
					</select>
					<small class="sid-cell-sub"><?php esc_html_e( 'Novouvedenu stavku SIDRENA prepoznaje automatski pri prvom objavljivanju; datum nije ručno promjenjiv.', 'sidrena' ); ?></small>
				<?php endif; ?>
			</td>
			<td><select aria-label="<?php esc_attr_e( 'Zadana dostupnost / webshop', 'sidrena' ); ?>" name="items[<?php echo esc_attr( $key ); ?>][availability]"><option value="dostupno" <?php selected( $availability, 'dostupno' ); ?>><?php esc_html_e( 'Dostupno', 'sidrena' ); ?></option><option value="nedostupno" <?php selected( $availability, 'nedostupno' ); ?>><?php esc_html_e( 'Nedostupno', 'sidrena' ); ?></option></select></td>
			<td><span class="sid-status-pill <?php echo $row_ready ? 'is-ok' : 'is-warn'; ?>"><?php echo $row_ready ? esc_html__( 'Spremno', 'sidrena' ) : esc_html__( 'Provjeriti', 'sidrena' ); ?></span></td>
		</tr>
		<tr class="sid-standalone-details-row" data-sidrena-details-for="<?php echo esc_attr( $key ); ?>">
			<td colspan="6">
				<details class="sid-row-details">
					<summary><span class="dashicons dashicons-admin-generic"></span><?php esc_html_e( 'Napredna SIDRENA polja', 'sidrena' ); ?><span class="sid-row-details__hint"><?php esc_html_e( 'marka, barkod, jedinična cijena, posebna prodaja i dostupnost po lokaciji', 'sidrena' ); ?></span></summary>
					<div class="sid-row-details__grid sid-row-details__grid--wordpress">
						<label><span><?php esc_html_e( 'Marka', 'sidrena' ); ?></span><input type="text" name="items[<?php echo esc_attr( $key ); ?>][brand]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_brand' ) ); ?>"></label>
						<label><span><?php esc_html_e( 'Barkod', 'sidrena' ); ?></span><input type="text" name="items[<?php echo esc_attr( $key ); ?>][barcode]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_barcode' ) ); ?>"></label>
						<label><span><?php esc_html_e( 'Status jedinične cijene', 'sidrena' ); ?></span><select name="items[<?php echo esc_attr( $key ); ?>][unit_status]"><option value="review" <?php selected( $status, 'review' ); ?>><?php esc_html_e( 'Provjeriti', 'sidrena' ); ?></option><option value="required" <?php selected( $status, 'required' ); ?>><?php esc_html_e( 'Obvezna', 'sidrena' ); ?></option><option value="not_required" <?php selected( $status, 'not_required' ); ?>><?php esc_html_e( 'Nije primjenjiva', 'sidrena' ); ?></option><option value="exception" <?php selected( $status, 'exception' ); ?>><?php esc_html_e( 'Iznimka', 'sidrena' ); ?></option></select></label>
						<label><span><?php esc_html_e( 'Količina pakiranja', 'sidrena' ); ?></span><input type="number" min="0" step="0.0001" name="items[<?php echo esc_attr( $key ); ?>][quantity]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_quantity' ) ); ?>"></label>
						<label><span><?php esc_html_e( 'Jedinica pakiranja', 'sidrena' ); ?></span><input type="text" name="items[<?php echo esc_attr( $key ); ?>][quantity_unit]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_quantity_unit' ) ); ?>" placeholder="<?php esc_attr_e( 'g / kg / ml / l / kom', 'sidrena' ); ?>"></label>
						<label><span><?php esc_html_e( 'Jedinica prikaza', 'sidrena' ); ?></span><input type="text" name="items[<?php echo esc_attr( $key ); ?>][unit]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_unit' ) ); ?>"></label>
						<label><span><?php esc_html_e( 'Cijena po jedinici', 'sidrena' ); ?></span><input type="number" min="0" step="0.0001" name="items[<?php echo esc_attr( $key ); ?>][unit_price]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_unit_price' ) ); ?>" placeholder="<?php esc_attr_e( 'Automatski ako je prazno', 'sidrena' ); ?>"></label>
						<label><span><?php esc_html_e( 'Naziv posebne prodaje', 'sidrena' ); ?></span><input type="text" name="items[<?php echo esc_attr( $key ); ?>][sale_name]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_sale_name' ) ); ?>" placeholder="<?php esc_attr_e( 'npr. Akcija', 'sidrena' ); ?>"></label>
						<?php foreach ( $locations as $location ) : ?>
							<?php
							if ( 'yes' !== ( $location['enabled'] ?? '' ) || 'webshop' === sanitize_key( (string) ( $location['kind'] ?? 'objekt' ) ) ) {
								continue;
							}
							$location_id    = Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' );
							$location_label = trim( (string) ( $location['code'] ?? $location_id ) . ' · ' . (string) ( $location['address'] ?? '' ) );
							$location_value = isset( $location_availability[ $location_id ] ) ? sanitize_key( (string) $location_availability[ $location_id ] ) : '';
							/* translators: %s: physical store/location code and address. */
							$availability_label = sprintf( __( 'Dostupnost · %s', 'sidrena' ), $location_label );
							?>
							<label><span><?php echo esc_html( $availability_label ); ?></span><select name="items[<?php echo esc_attr( $key ); ?>][location_availability][<?php echo esc_attr( $location_id ); ?>]"><option value="" <?php selected( $location_value, '' ); ?>><?php esc_html_e( 'Nije uneseno', 'sidrena' ); ?></option><option value="dostupno" <?php selected( $location_value, 'dostupno' ); ?>><?php esc_html_e( 'Dostupno', 'sidrena' ); ?></option><option value="nedostupno" <?php selected( $location_value, 'nedostupno' ); ?>><?php esc_html_e( 'Nedostupno', 'sidrena' ); ?></option></select><small><?php esc_html_e( 'Stvarna raspoloživost za ovu fizičku lokaciju.', 'sidrena' ); ?></small></label>
						<?php endforeach; ?>
						<div class="sid-row-details__action">
						<?php
						if ( $id ) :
							?>
							<label class="sid-inline-delete"><input type="checkbox" name="items[<?php echo esc_attr( $key ); ?>][delete]" value="yes"> <?php esc_html_e( 'Obriši ovaj proizvod', 'sidrena' ); ?></label>
							<?php
else :
	?>
							<button type="button" class="button-link-delete sidrena-remove-standalone"><?php esc_html_e( 'Ukloni nespremljeni proizvod', 'sidrena' ); ?></button><?php endif; ?></div>
					</div>
				</details>
			</td>
		</tr>
		<?php
	}

	public function save() {
		if ( ! Sidrena_Utils::current_user_can_manage() || ! check_admin_referer( 'sidrena_standalone_save' ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}

		$items           = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$items           = array_slice( $items, 0, 100, true );
		$page            = max( 1, isset( $_POST['standalone_page'] ) ? absint( $_POST['standalone_page'] ) : 1 );
		$saved           = 0;
		$deleted         = 0;
		$errors          = 0;
		$code_index      = $this->code_index();
		$valid_locations = array();
		foreach ( Sidrena_Utils::locations() as $location ) {
			if ( 'yes' !== ( $location['enabled'] ?? '' ) ) {
				continue;
			}
			$location_id = Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' );
			if ( $location_id ) {
				$valid_locations[ $location_id ] = true;
			}
		}

		foreach ( $items as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id                = absint( $row['id'] ?? 0 );
			$original_code_key = $id ? $this->code_key( get_post_meta( $id, '_sidrena_standalone_code', true ) ) : '';
			if ( $id && self::POST_TYPE !== get_post_type( $id ) ) {
				++$errors;
				continue;
			}
			if ( $id && 'yes' === ( $row['delete'] ?? '' ) ) {
				if ( wp_delete_post( $id, true ) ) {
					if ( $original_code_key && isset( $code_index[ $original_code_key ] ) && absint( $code_index[ $original_code_key ] ) === $id ) {
						unset( $code_index[ $original_code_key ] );
					}
					++$deleted;
				} else {
					++$errors;
				}
				continue;
			}

			$name       = sanitize_text_field( $row['name'] ?? '' );
			$code       = sanitize_text_field( $row['code'] ?? '' );
			$code_key   = $this->code_key( $code );
			$current    = Sidrena_Utils::validated_nonnegative_decimal( $row['current'] ?? '' );
			$anchor     = Sidrena_Utils::validated_nonnegative_decimal( $row['anchor'] ?? '' );
			$quantity   = Sidrena_Utils::validated_nonnegative_decimal( $row['quantity'] ?? '' );
			$unit_price = Sidrena_Utils::validated_nonnegative_decimal( $row['unit_price'] ?? '' );
			if ( null === $current || null === $anchor || null === $quantity || null === $unit_price ) {
				++$errors;
				continue;
			}
			if ( ! $id && '' === $name && '' === $current && '' === $anchor && '' === $code ) {
				continue;
			}
			if ( $code_key && isset( $code_index[ $code_key ] ) && absint( $code_index[ $code_key ] ) !== $id ) {
				++$errors;
				continue;
			}

			$saved_id = wp_insert_post(
				array(
					'ID'          => $id,
					'post_type'   => self::POST_TYPE,
					'post_title'  => $name ? $name : __( 'Proizvod bez naziva', 'sidrena' ),
					'post_status' => $name && '' !== $current ? 'publish' : 'draft',
				),
				true
			);
			if ( is_wp_error( $saved_id ) || ! $saved_id ) {
				++$errors;
				continue;
			}
			++$saved;

			$this->set_meta( $saved_id, '_sidrena_standalone_code', $code );
			if ( $original_code_key && $original_code_key !== $code_key && isset( $code_index[ $original_code_key ] ) && absint( $code_index[ $original_code_key ] ) === $saved_id ) {
				unset( $code_index[ $original_code_key ] );
			}
			if ( $code_key ) {
				$code_index[ $code_key ] = $saved_id;
			}
			$this->set_meta( $saved_id, '_sidrena_standalone_brand', sanitize_text_field( $row['brand'] ?? '' ) );
			$this->set_meta( $saved_id, '_sidrena_standalone_current_price', $current );
			$this->set_meta( $saved_id, '_sidrena_standalone_anchor_price', $anchor );
			$reference_group = Sidrena_Utils::sanitize_reference_group( $row['reference_group'] ?? 'standard' );
			$custom_date     = '';
			if ( 'custom' === $reference_group ) {
				$custom_date = Sidrena_Utils::verified_custom_reference_date_for_post( $saved_id, get_post_meta( $saved_id, '_sidrena_standalone_anchor_date', true ) );
				if ( ! $custom_date ) {
					$reference_group = 'standard';
				}
			}
			$this->set_meta( $saved_id, '_sidrena_standalone_reference_group', $reference_group );
			$this->set_meta( $saved_id, '_sidrena_standalone_anchor_date', $custom_date );
			$this->set_meta( $saved_id, '_sidrena_standalone_barcode', sanitize_text_field( $row['barcode'] ?? '' ) );

			$status = sanitize_key( $row['unit_status'] ?? 'review' );
			if ( ! in_array( $status, array( 'review', 'required', 'not_required', 'exception' ), true ) ) {
				$status = 'review';
			}
			$this->set_meta( $saved_id, '_sidrena_standalone_unit_status', $status );
			$quantity_unit = Sidrena_Utils::normalize_unit( $row['quantity_unit'] ?? '' );
			$unit          = sanitize_text_field( $row['unit'] ?? '' );
			if ( 'required' === $status && '' === $unit_price && '' !== $quantity && '' !== $quantity_unit ) {
				$calculated = Sidrena_Utils::calculate_unit_price( $current, $quantity, $quantity_unit );
				if ( $calculated ) {
					$unit       = $calculated['unit'];
					$unit_price = $calculated['unit_price'];
				}
			}
			$this->set_meta( $saved_id, '_sidrena_standalone_quantity', $quantity );
			$this->set_meta( $saved_id, '_sidrena_standalone_quantity_unit', $quantity_unit );
			$this->set_meta( $saved_id, '_sidrena_standalone_unit', $unit );
			$this->set_meta( $saved_id, '_sidrena_standalone_unit_price', $unit_price );

			$availability = sanitize_key( $row['availability'] ?? 'dostupno' );
			if ( ! in_array( $availability, array( 'dostupno', 'nedostupno' ), true ) ) {
				$availability = 'dostupno';
			}
			$this->set_meta( $saved_id, '_sidrena_standalone_availability', $availability );
			$this->set_meta( $saved_id, '_sidrena_standalone_sale_name', sanitize_text_field( $row['sale_name'] ?? '' ) );

			$location_availability        = array();
			$posted_location_availability = isset( $row['location_availability'] ) && is_array( $row['location_availability'] ) ? $row['location_availability'] : array();
			foreach ( $posted_location_availability as $location_id => $location_status ) {
				$location_id     = Sidrena_Utils::sanitize_location_id( $location_id );
				$location_status = sanitize_key( (string) $location_status );
				if ( isset( $valid_locations[ $location_id ] ) && in_array( $location_status, array( 'dostupno', 'nedostupno' ), true ) ) {
					$location_availability[ $location_id ] = $location_status;
				}
			}
			$this->set_meta( $saved_id, '_sidrena_standalone_location_availability', $location_availability );
		}

		Sidrena_Audit::log(
			'standalone_catalog_save',
			$errors ? 'warning' : 'success',
			/* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */
			sprintf( __( 'WordPress katalog spremljen: %1$d spremljenih, %2$d obrisanih, %3$d grešaka.', 'sidrena' ), $saved, $deleted, $errors ),
			array(
				'saved'   => $saved,
				'deleted' => $deleted,
				'errors'  => $errors,
			)
		);
		Sidrena_Pricelist::queue_regeneration();
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'sidrena-catalog',
					'standalone_page' => $page,
					'sid_notice'      => $errors ? 'standalone_saved_with_errors' : 'bulk_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
	public function import() {
		if ( ! Sidrena_Utils::current_user_can_manage() || ! check_admin_referer( 'sidrena_standalone_import' ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}
		if ( empty( $_FILES['standalone_file'] ) || ! is_array( $_FILES['standalone_file'] ) ) {
			$this->redirect_import( 'standalone_import_failed' );
		}

		$file = $_FILES['standalone_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated before use.
		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$this->redirect_import( 'standalone_import_failed' );
		}
		$upload_size = $this->validate_uploaded_catalog_size( $file['tmp_name'], $file['size'] ?? 0 );
		if ( is_wp_error( $upload_size ) ) {
			$this->redirect_import( 'standalone_import_failed' );
		}

		$filename = sanitize_file_name( wp_unslash( $file['name'] ?? '' ) );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'csv', 'xml' ), true ) ) {
			$this->redirect_import( 'standalone_import_failed' );
		}

		if ( ! Sidrena_Utils::uploaded_text_type_allowed( $file['tmp_name'], $filename, array( 'csv', 'xml' ) ) ) {
			$this->redirect_import( 'standalone_import_failed' );
		}

		$contents = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents || '' === $contents || false !== strpos( $contents, "\0" ) ) {
			$this->redirect_import( 'standalone_import_failed' );
		}
		$contents = Sidrena_Utils::normalize_text_encoding( $contents );
		if ( '' === $contents ) {
			$this->redirect_import( 'standalone_import_failed' );
		}

		$csv_resource = null;
		if ( 'xml' === $ext ) {
			$xml_count = $this->validate_xml_import( $contents );
			if ( is_wp_error( $xml_count ) || 0 === $xml_count ) {
				$this->redirect_import( 'standalone_import_failed' );
			}
			$rows = $this->iterate_xml_import_rows( $contents );
		} else {
			$prepared = $this->prepare_csv_import_stream( $contents );
			if ( is_wp_error( $prepared ) ) {
				$this->redirect_import( 'standalone_import_failed' );
			}
			list( $csv_resource, $delimiter, $head, $csv_count ) = $prepared;
			if ( 0 === $csv_count ) {
				fclose( $csv_resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				$this->redirect_import( 'standalone_import_failed' );
			}
			$rows = $this->iterate_csv_import_rows( $csv_resource, $delimiter, $head );
		}

		$created    = 0;
		$updated    = 0;
		$skipped    = 0;
		$code_index = $this->code_index();
		foreach ( $rows as $raw ) {
			$row      = $this->canonical_import_row( $raw );
			$code     = sanitize_text_field( $row['code'] ?? '' );
			$code_key = $this->code_key( $code );
			$id       = $code_key && isset( $code_index[ $code_key ] ) ? absint( $code_index[ $code_key ] ) : 0;

			if ( ! $id ) {
				$name    = sanitize_text_field( $row['name'] ?? '' );
				$current = Sidrena_Utils::validated_nonnegative_decimal( $row['current'] ?? '' );
				$anchor  = Sidrena_Utils::validated_nonnegative_decimal( $row['anchor'] ?? '' );
				if ( null === $current || null === $anchor || '' === $name || '' === $current || '' === $anchor ) {
					++$skipped;
					continue;
				}
				$id = wp_insert_post(
					array(
						'post_type'   => self::POST_TYPE,
						'post_status' => 'publish',
						'post_title'  => $name,
					),
					true
				);
				if ( is_wp_error( $id ) || ! $id ) {
					++$skipped;
					continue;
				}
				++$created;
			} else {
				++$updated;
				if ( ! empty( $row['name'] ) ) {
					wp_update_post(
						array(
							'ID'         => $id,
							'post_title' => sanitize_text_field( $row['name'] ),
						)
					);
				}
			}

			$this->import_field( $id, '_sidrena_standalone_code', $row, 'code', 'text' );
			if ( $code_key ) {
				$code_index[ $code_key ] = $id;
			}
			$this->import_field( $id, '_sidrena_standalone_brand', $row, 'brand', 'text' );
			$this->import_field( $id, '_sidrena_standalone_current_price', $row, 'current', 'decimal' );
			$this->import_field( $id, '_sidrena_standalone_anchor_price', $row, 'anchor', 'decimal' );
			$reference_group = array_key_exists( 'reference_group', $row ) ? Sidrena_Utils::sanitize_reference_group( $row['reference_group'] ) : 'standard';
			$custom_date     = 'custom' === $reference_group
				? Sidrena_Utils::verified_custom_reference_date_for_post( $id, $row['anchor_date'] ?? '' )
				: '';
			if ( 'custom' === $reference_group && ! $custom_date ) {
				$reference_group = 'standard';
			}
			update_post_meta( $id, '_sidrena_standalone_reference_group', $reference_group );
			if ( $custom_date ) {
				update_post_meta( $id, '_sidrena_standalone_anchor_date', $custom_date );
			} else {
				delete_post_meta( $id, '_sidrena_standalone_anchor_date' );
			}
			$this->import_field( $id, '_sidrena_standalone_barcode', $row, 'barcode', 'text' );
			$this->import_field( $id, '_sidrena_standalone_quantity', $row, 'quantity', 'decimal' );
			$this->import_field( $id, '_sidrena_standalone_quantity_unit', $row, 'quantity_unit', 'unit' );
			$this->import_field( $id, '_sidrena_standalone_unit', $row, 'unit', 'text' );
			$this->import_field( $id, '_sidrena_standalone_unit_price', $row, 'unit_price', 'decimal' );
			$this->import_field( $id, '_sidrena_standalone_sale_name', $row, 'sale_name', 'text' );

			if ( array_key_exists( 'unit_status', $row ) && '' !== trim( (string) $row['unit_status'] ) ) {
				$status = sanitize_key( $row['unit_status'] );
				if ( in_array( $status, array( 'review', 'required', 'not_required', 'exception' ), true ) ) {
					update_post_meta( $id, '_sidrena_standalone_unit_status', $status );
				}
			}
			$unit_status_raw = get_post_meta( $id, '_sidrena_standalone_unit_status', true );
			$unit_status     = sanitize_key( $unit_status_raw ? $unit_status_raw : 'review' );
			$unit_price      = Sidrena_Utils::decimal( get_post_meta( $id, '_sidrena_standalone_unit_price', true ) );
			$quantity        = Sidrena_Utils::decimal( get_post_meta( $id, '_sidrena_standalone_quantity', true ) );
			$quantity_unit   = Sidrena_Utils::normalize_unit( get_post_meta( $id, '_sidrena_standalone_quantity_unit', true ) );
			if ( 'required' === $unit_status && '' === $unit_price && '' !== $quantity && '' !== $quantity_unit ) {
				$calculated = Sidrena_Utils::calculate_unit_price( get_post_meta( $id, '_sidrena_standalone_current_price', true ), $quantity, $quantity_unit );
				if ( $calculated ) {
					update_post_meta( $id, '_sidrena_standalone_unit', $calculated['unit'] );
					update_post_meta( $id, '_sidrena_standalone_unit_price', $calculated['unit_price'] );
				}
			}

			if ( array_key_exists( 'availability', $row ) && '' !== trim( (string) $row['availability'] ) ) {
				$availability = sanitize_key( remove_accents( (string) $row['availability'] ) );
				if ( in_array( $availability, array( 'dostupno', 'nedostupno' ), true ) ) {
					update_post_meta( $id, '_sidrena_standalone_availability', $availability );
				}
			}

			$final_name    = trim( (string) get_the_title( $id ) );
			$final_current = Sidrena_Utils::decimal( get_post_meta( $id, '_sidrena_standalone_current_price', true ) );
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => $final_name && '' !== $final_current ? 'publish' : 'draft',
				)
			);
		}

		if ( is_resource( $csv_resource ) ) {
			fclose( $csv_resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}

		Sidrena_Audit::log(
			'standalone_catalog_import',
			'success',
			/* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */
			sprintf( __( 'Uvoz WordPress kataloga dovršen: %1$d novih, %2$d ažuriranih, %3$d preskočenih.', 'sidrena' ), $created, $updated, $skipped ),
			array(
				'created' => $created,
				'updated' => $updated,
				'skipped' => $skipped,
			)
		);
		Sidrena_Pricelist::queue_regeneration();
		wp_safe_redirect( admin_url( 'admin.php?page=sidrena-catalog&sid_notice=standalone_imported' ) );
		exit;
	}

	private function validate_uploaded_catalog_size( $tmp_name, $reported_size ) {
		$max_bytes = 5 * MB_IN_BYTES;
		$tmp_name  = (string) $tmp_name;
		$reported  = max( 0, (int) $reported_size );

		if ( '' === $tmp_name || ! is_file( $tmp_name ) ) {
			return new WP_Error( 'upload_missing', __( 'Privremena datoteka uvoza nije dostupna.', 'sidrena' ) );
		}
		if ( $reported > $max_bytes ) {
			return new WP_Error( 'upload_too_large', __( 'Datoteka za uvoz prelazi dopuštenih 5 MB.', 'sidrena' ) );
		}

		clearstatcache( true, $tmp_name );
		$actual = wp_filesize( $tmp_name );
		if ( false === $actual || $actual <= 0 ) {
			return new WP_Error( 'upload_empty', __( 'Datoteka za uvoz je prazna ili joj nije moguće utvrditi veličinu.', 'sidrena' ) );
		}
		if ( $actual > $max_bytes ) {
			return new WP_Error( 'upload_too_large', __( 'Stvarna datoteka na poslužitelju prelazi dopuštenih 5 MB.', 'sidrena' ) );
		}

		return (int) $actual;
	}

	private function prepare_csv_import_stream( $contents, $row_limit = 50000 ) {
		$row_limit = min( 50000, max( 1, absint( $row_limit ) ) );
		$resource  = fopen( 'php://temp/maxmemory:1048576', 'w+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $resource ) {
			return new WP_Error( 'csv_open' );
		}
		$contents = (string) $contents;
		$length   = strlen( $contents );
		$offset   = 0;
		while ( $offset < $length ) {
			$written = fwrite( $resource, substr( $contents, $offset ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			if ( false === $written || 0 === $written ) {
				fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				return new WP_Error( 'csv_write' );
			}
			$offset += $written;
		}

		rewind( $resource );
		$first = fgets( $resource );
		if ( false === $first ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'csv_empty' );
		}
		$delimiter = ';';
		$best      = -1;
		foreach ( array( ';', ',', "\t" ) as $candidate ) {
			$count = substr_count( $first, $candidate );
			if ( $count > $best ) {
				$delimiter = $candidate;
				$best      = $count;
			}
		}

		rewind( $resource );
		$head = fgetcsv( $resource, 0, $delimiter );
		if ( ! is_array( $head ) ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'csv_header' );
		}
		$head[0]       = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $head[0] );
		$head          = array_map( array( 'Sidrena_Utils', 'import_header_key' ), $head );
		$nonempty_head = array_values( array_filter( $head ) );
		if ( count( $nonempty_head ) !== count( array_unique( $nonempty_head ) ) ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'csv_duplicate_headers', __( 'CSV sadrži duplicirana zaglavlja nakon normalizacije.', 'sidrena' ) );
		}

		$count = 0;
		while ( true ) {
			$values = fgetcsv( $resource, 0, $delimiter );
			if ( false === $values ) {
				break;
			}
			unset( $values );
			++$count;
			if ( $count > $row_limit ) {
				fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				return new WP_Error( 'csv_row_limit', __( 'CSV ima više od dopuštenih 50.000 redaka.', 'sidrena' ) );
			}
		}

		rewind( $resource );
		fgetcsv( $resource, 0, $delimiter );
		return array( $resource, $delimiter, $head, $count );
	}

	private function iterate_csv_import_rows( $stream, $delimiter, $head ) {
		while ( is_resource( $stream ) ) {
			$values = fgetcsv( $stream, 0, $delimiter );
			if ( false === $values ) {
				break;
			}
			$row = array();
			foreach ( $head as $index => $key ) {
				if ( '' !== $key ) {
					$row[ $key ] = isset( $values[ $index ] ) ? $values[ $index ] : '';
				}
			}
			if ( ! empty(
				array_filter(
					$row,
					static function ( $value ) {
						return '' !== trim( (string) $value ); }
				)
			) ) {
				yield $row;
			}
		}
	}

	private function validate_xml_import( $contents, $row_limit = 50000 ) {
		$row_limit = min( 50000, max( 1, absint( $row_limit ) ) );
		if ( preg_match( '/<!\s*(DOCTYPE|ENTITY)\b/i', (string) $contents ) ) {
			return new WP_Error( 'xml_unsafe', __( 'XML s DOCTYPE ili ENTITY deklaracijama nije dopušten.', 'sidrena' ) );
		}
		if ( ! function_exists( 'simplexml_load_string' ) ) {
			return new WP_Error( 'xml_unavailable' );
		}

		if ( class_exists( 'XMLReader' ) ) {
			$previous = libxml_use_internal_errors( true );
			libxml_clear_errors();
			$reader = new XMLReader();
			if ( ! $reader->XML( (string) $contents, null, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT ) ) {
				libxml_clear_errors();
				libxml_use_internal_errors( $previous );
				return new WP_Error( 'xml_invalid' );
			}
			$root_depth = null;
			$count      = 0;
			while ( $reader->read() ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- nodeType is a native XMLReader property.
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- nodeType is a native XMLReader property.
				if ( XMLReader::ELEMENT !== $reader->nodeType ) {
					continue;
				}
				if ( null === $root_depth ) {
					$root_depth = $reader->depth;
					continue;
				}
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- isEmptyElement is a native XMLReader property.
				if ( $reader->depth === $root_depth + 1 && ! $reader->isEmptyElement ) {
					++$count;
					if ( $count > $row_limit ) {
						$reader->close();
						libxml_clear_errors();
						libxml_use_internal_errors( $previous );
						return new WP_Error( 'xml_row_limit', __( 'XML ima više od dopuštenih 50.000 zapisa.', 'sidrena' ) );
					}
				}
			}
			$errors = libxml_get_errors();
			$reader->close();
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
			return empty( $errors ) ? $count : new WP_Error( 'xml_invalid' );
		}

		$previous = libxml_use_internal_errors( true );
		$xml      = simplexml_load_string( (string) $contents, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
		$errors   = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( false === $xml || ! empty( $errors ) ) {
			return new WP_Error( 'xml_invalid' );
		}
		$count = 0;
		foreach ( $xml->children() as $node ) {
			if ( 0 === count( $node->children() ) ) {
				continue;
			}
			++$count;
			if ( $count > $row_limit ) {
				return new WP_Error( 'xml_row_limit', __( 'XML ima više od dopuštenih 50.000 zapisa.', 'sidrena' ) );
			}
		}
		return $count;
	}

	private function iterate_xml_import_rows( $contents ) {
		if ( class_exists( 'XMLReader' ) ) {
			$reader = new XMLReader();
			if ( ! $reader->XML( (string) $contents, null, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT ) ) {
				return;
			}
			$root_depth = null;
			while ( $reader->read() ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- nodeType is a native XMLReader property.
				if ( XMLReader::ELEMENT !== $reader->nodeType ) {
					continue;
				}
				if ( null === $root_depth ) {
					$root_depth = $reader->depth;
					continue;
				}
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- isEmptyElement is a native XMLReader property.
				if ( $reader->depth !== $root_depth + 1 || $reader->isEmptyElement ) {
					continue;
				}
				$outer = $reader->readOuterXML();
				if ( ! $outer ) {
					continue;
				}
				$node = simplexml_load_string( $outer, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
				if ( false === $node ) {
					continue;
				}
				$row = array();
				foreach ( $node->children() as $key => $value ) {
					$normalized = Sidrena_Utils::import_header_key( (string) $key );
					if ( '' !== $normalized ) {
						$row[ $normalized ] = trim( (string) $value );
					}
				}
				if ( ! empty( $row ) ) {
					yield $row;
				}
			}
			$reader->close();
			return;
		}

		$xml = simplexml_load_string( (string) $contents, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
		if ( false === $xml ) {
			return;
		}
		foreach ( $xml->children() as $node ) {
			if ( 0 === count( $node->children() ) ) {
				continue;
			}
			$row = array();
			foreach ( $node->children() as $key => $value ) {
				$normalized = Sidrena_Utils::import_header_key( (string) $key );
				if ( '' !== $normalized ) {
					$row[ $normalized ] = trim( (string) $value );
				}
			}
			if ( ! empty( $row ) ) {
				yield $row;
			}
		}
	}

	private function code_key( $code ) {
		$code = trim( wp_strip_all_tags( (string) $code ) );
		if ( '' === $code ) {
			return '';
		}
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $code, 'UTF-8' ) : strtolower( $code );
	}

	private function code_index() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded read across WordPress posts/postmeta for the standalone code index.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, pm.meta_value
				FROM %i p
				INNER JOIN %i pm ON pm.post_id = p.ID
				WHERE p.post_type = %s
					AND p.post_status IN ('publish','draft','pending','private')
					AND pm.meta_key = %s
					AND pm.meta_value <> ''
				ORDER BY p.ID ASC",
				$wpdb->posts,
				$wpdb->postmeta,
				self::POST_TYPE,
				'_sidrena_standalone_code'
			),
			ARRAY_A
		);

		$index = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$key = $this->code_key( $row['meta_value'] ?? '' );
			if ( $key && ! isset( $index[ $key ] ) ) {
				$index[ $key ] = absint( $row['ID'] ?? 0 );
			}
		}
		return $index;
	}

	private function canonical_import_row( $row ) {
		$normalized = array();
		foreach ( $row as $key => $value ) {
			$normalized[ Sidrena_Utils::import_header_key( $key ) ] = $value;
		}

		$aliases = array(
			'name'            => array( 'name', 'naziv', 'naziv_proizvoda' ),
			'code'            => array( 'code', 'sku', 'sifra', 'sifra_proizvoda', 'sifra_artikla' ),
			'brand'           => array( 'brand', 'marka', 'marka_proizvoda' ),
			'current'         => array( 'current', 'price', 'cijena', 'maloprodajna_cijena', 'mpc' ),
			'anchor'          => array( 'anchor', 'anchor_price', 'sidrena_cijena', 'dodatna_cijena' ),
			'reference_group' => array( 'reference_group', 'referentna_skupina', 'grupa_sidrene_cijene' ),
			'anchor_date'     => array( 'anchor_date', 'datum_sidrene_cijene', 'referentni_datum', 'datum_prvog_uvrstenja' ),
			'barcode'         => array( 'barcode', 'barkod', 'ean', 'gtin' ),
			'unit_status'     => array( 'unit_status', 'jedinicna_status', 'jedinicna_cijena_status' ),
			'quantity'        => array( 'quantity', 'kolicina', 'kolicina_pakiranja', 'neto_kolicina', 'neto_kolicina_proizvoda' ),
			'quantity_unit'   => array( 'quantity_unit', 'jedinica_kolicine', 'jedinica_pakiranja' ),
			'unit'            => array( 'unit', 'jedinica', 'jedinica_mjere' ),
			'unit_price'      => array( 'unit_price', 'cijena_jedinice_mjere', 'cijena_za_jedinicu_mjere' ),
			'availability'    => array( 'availability', 'dostupnost', 'raspolozivost', 'status_dostupnosti' ),
			'sale_name'       => array( 'sale_name', 'naziv_posebnog_oblika', 'naziv_posebnog_oblika_prodaje' ),
		);
		$out     = array();
		foreach ( $aliases as $canonical => $names ) {
			foreach ( $names as $name ) {
				$key = Sidrena_Utils::import_header_key( $name );
				if ( array_key_exists( $key, $normalized ) ) {
					$out[ $canonical ] = $normalized[ $key ];
					break;
				}
			}
		}

		if ( ! isset( $out['anchor'] ) ) {
			foreach ( $normalized as $key => $value ) {
				if ( 0 === strpos( $key, 'sidrena_cijena_na_' ) ) {
					$out['anchor'] = $value;
					break;
				}
			}
		}

		if ( isset( $out['quantity'] ) ) {
			$parsed = Sidrena_Utils::parse_quantity_with_unit( $out['quantity'] );
			if ( $parsed ) {
				$out['quantity'] = $parsed['quantity'];
				if ( ! empty( $parsed['unit'] ) ) {
					$out['quantity_unit'] = $parsed['unit'];
				}
			}
		}
		return $out;
	}

	private function import_field( $id, $meta_key, $row, $column, $type ) {
		if ( ! array_key_exists( $column, $row ) || '' === trim( (string) $row[ $column ] ) ) {
			return;
		}
		$value = $row[ $column ];
		if ( 'decimal' === $type ) {
			$value = Sidrena_Utils::validated_nonnegative_decimal( $value );
			if ( null === $value ) {
				return;
			}
		} elseif ( 'date' === $type ) {
			$value = Sidrena_Utils::sanitize_date( $value );
		} elseif ( 'unit' === $type ) {
			$value = Sidrena_Utils::normalize_unit( $value );
		} else {
			$value = sanitize_text_field( $value );
		}
		if ( '' !== $value ) {
			update_post_meta( $id, $meta_key, $value );
		}
	}

	private function redirect_import( $notice ) {
		wp_safe_redirect( admin_url( 'admin.php?page=sidrena-catalog&sid_notice=' . sanitize_key( $notice ) ) );
		exit;
	}

	private function set_meta( $id, $key, $value ) {
		if ( '' === $value || null === $value ) {
			delete_post_meta( $id, $key );
			return;
		}
		update_post_meta( $id, $key, $value );
	}

	public function action_output( $item = null ) {
		$raw = is_scalar( $item ) ? trim( (string) $item ) : '';
		$id  = absint( preg_replace( '/\D+/', '', $raw ) );
		if ( ! $id && is_object( $item ) && isset( $item->ID ) ) {
			$id = absint( $item->ID );
		}
		if ( ! $id ) {
			return;
		}
		echo wp_kses_post( $this->price_shortcode( array( 'id' => 's' . $id ) ) );
	}

	public function price_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'           => 0,
				'show_current' => 'yes',
			),
			$atts,
			'sidrena_cijena'
		);
		$raw  = (string) $atts['id'];
		$id   = absint( preg_replace( '/\D+/', '', $raw ) );
		if ( ! $id || self::POST_TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return '';
		}

		$current = Sidrena_Utils::decimal( get_post_meta( $id, '_sidrena_standalone_current_price', true ) );
		$anchor  = Sidrena_Utils::decimal( get_post_meta( $id, '_sidrena_standalone_anchor_price', true ) );
		if ( '' === $current && '' === $anchor ) {
			return '';
		}

		wp_enqueue_style( 'sidrena-frontend', SIDRENA_URL . 'public/css/frontend.css', array(), SIDRENA_VERSION );
		$currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '€';
		$out      = '<span class="sidrena-standalone-price">';
		if ( 'no' !== sanitize_key( (string) $atts['show_current'] ) && '' !== $current ) {
			$out .= '<span class="sidrena-standalone-price__current">' . esc_html( Sidrena_Utils::money( $current ) . ' ' . $currency ) . '</span>';
		}
		$sale_name = trim( (string) get_post_meta( $id, '_sidrena_standalone_sale_name', true ) );
		if ( '' !== $anchor ) {
			$reference_group   = Sidrena_Utils::sanitize_reference_group( get_post_meta( $id, '_sidrena_standalone_reference_group', true ) );
			$saved_anchor_date = 'custom' === $reference_group ? Sidrena_Utils::verified_custom_reference_date_for_post( $id, get_post_meta( $id, '_sidrena_standalone_anchor_date', true ) ) : '';
			$date              = Sidrena_Utils::resolved_reference_date( $reference_group, $saved_anchor_date );
			$tooltip           = Sidrena_Utils::anchor_tooltip();
			$tooltip_id        = 'sidrena-anchor-tip-s' . absint( $id );
			$tip_html          = $tooltip ? '<span class="sidrena-anchor__info" aria-hidden="true">i</span><span id="' . esc_attr( $tooltip_id ) . '" class="sidrena-anchor__tooltip" role="tooltip">' . esc_html( $tooltip ) . '</span>' : '';
			$out              .= '<span class="sidrena-anchor' . ( $tooltip ? ' sidrena-anchor--has-tooltip' : '' ) . '"' . ( $tooltip ? ' tabindex="0" aria-describedby="' . esc_attr( $tooltip_id ) . '"' : '' ) . '><span class="sidrena-anchor__label">' . esc_html( Sidrena_Utils::anchor_label( $date ) ) . ':</span> <span class="sidrena-anchor__value">' . esc_html( Sidrena_Utils::money( $anchor ) . ' ' . $currency ) . '</span>' . $tip_html . '</span>';
		}
		$out .= '</span>';
		return $out;
	}
}
