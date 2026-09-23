<?php
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
		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			add_shortcode( 'sidrena_cijena', array( $this, 'price_shortcode' ) );
			add_shortcode( 'sidrena-cijena', array( $this, 'price_shortcode' ) );
		}
	}

	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
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
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
				'no_found_rows'  => true,
			)
		);

		$rows = array();
		foreach ( $query->posts as $post ) {
			$id           = $post->ID;
			$current      = get_post_meta( $id, '_sidrena_standalone_current_price', true );
			$anchor       = get_post_meta( $id, '_sidrena_standalone_anchor_price', true );
			$unit_status  = sanitize_key( (string) get_post_meta( $id, '_sidrena_standalone_unit_status', true ) );
			$sale_name    = trim( (string) get_post_meta( $id, '_sidrena_standalone_sale_name', true ) );
			$availability = sanitize_key( (string) get_post_meta( $id, '_sidrena_standalone_availability', true ) );
			if ( ! in_array( $availability, array( 'dostupno', 'nedostupno' ), true ) ) {
				$availability = 'dostupno';
			}

			$unit = get_post_meta( $id, '_sidrena_standalone_unit', true );
			$unit_price = get_post_meta( $id, '_sidrena_standalone_unit_price', true );
			if ( in_array( $unit_status, array( 'not_required', 'exception' ), true ) ) {
				$unit = '';
				$unit_price = '';
			}
			$rows[] = array(
				'_sidrena_lowest_30'        => Sidrena_Utils::decimal( get_post_meta( $id, '_sidrena_standalone_lowest_30', true ) ),
				'_sidrena_item_id'           => $id,
				'_sidrena_unit_status'       => $unit_status ? $unit_status : 'review',
				'_sidrena_location_explicit' => 'yes',
				'naziv'                      => get_the_title( $post ),
				'sifra'                      => get_post_meta( $id, '_sidrena_standalone_code', true ),
				'marka'                      => get_post_meta( $id, '_sidrena_standalone_brand', true ),
				'jedinica_mjere'             => $unit,
				'cijena_za_jedinicu_mjere'   => Sidrena_Utils::money( $unit_price, 4 ),
				'maloprodajna_cijena'        => Sidrena_Utils::money( $current ),
				'posebni_oblik_prodaje'      => $sale_name ? 'da' : 'ne',
				'naziv_posebnog_oblika_prodaje' => $sale_name,
				'sidrena_cijena'              => Sidrena_Utils::money( $anchor ),
				'datum_sidrene_cijene'        => '' === Sidrena_Utils::decimal( $anchor ) ? '' : Sidrena_Utils::date_display( get_post_meta( $id, '_sidrena_standalone_anchor_date', true ) ?: Sidrena_Utils::settings()['default_ref_date'] ),
				'barkod'                      => get_post_meta( $id, '_sidrena_standalone_barcode', true ),
				'dostupnost'                  => $availability,
			);
		}
		wp_reset_postdata();

		return apply_filters( 'sidrena_standalone_rows', $rows, $location );
	}

	public static function count() {
		$counts = wp_count_posts( self::POST_TYPE );
		return $counts && isset( $counts->publish ) ? absint( $counts->publish ) : 0;
	}

	public static function audit_stats() {
		$stats = array(
			'products'           => 0,
			'missing_anchor'     => 0,
			'missing_brand'      => 0,
			'missing_barcode'    => 0,
			'unit_price_review'  => 0,
			'unit_price_missing' => 0,
			'active_sales'       => 0,
			'sale_incomplete'     => 0,
		);

		foreach ( self::rows() as $row ) {
			++$stats['products'];
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
				if ( '' === Sidrena_Utils::decimal( $row['_sidrena_lowest_30'] ?? '' ) ) {
					++$stats['sale_incomplete'];
				}
			}
		}
		return $stats;
	}

	public function render() {
		if ( ! current_user_can( Sidrena_Utils::admin_capability() ) ) {
			return;
		}

		$page     = max( 1, isset( $_GET['standalone_page'] ) ? absint( $_GET['standalone_page'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = 60;
		$query    = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
		$items = is_array( $query->posts ) ? $query->posts : array();
		$pages = max( 1, absint( $query->max_num_pages ) );
		$total = absint( $query->found_posts );
		?>
		<div class="sid-page-head">
			<div>
				<span class="sid-kicker"><?php esc_html_e( 'Samostalni katalog', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Proizvodi bez WooCommercea', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Za običan WordPress unesite proizvode izravno u Sidreni. Isti podaci koriste se za javni HTML cjenik, CSV/XML, arhivu i tehničku provjeru.', 'sidrena' ); ?></p>
			</div>
			<span class="sid-status-pill"><?php echo esc_html( sprintf( __( '%d proizvoda', 'sidrena' ), $total ) ); ?></span>
		</div>
		<form class="sid-card sid-form sid-standalone-import" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sidrena_standalone_import">
			<?php wp_nonce_field( 'sidrena_standalone_import' ); ?>
			<div class="sid-section-head">
				<div><span class="sid-kicker"><?php esc_html_e( 'Masovni uvoz', 'sidrena' ); ?></span><h2><?php esc_html_e( 'CSV/XML katalog', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Podržani su hrvatski i tehnički nazivi stupaca/čvorova. Postojeće stavke povezuju se po šifri; nove zahtijevaju naziv, cijenu i sidrenu cijenu.', 'sidrena' ); ?></p></div>
			</div>
			<div class="sid-form-actions">
				<input class="sid-file-input" type="file" name="standalone_file" accept=".csv,.xml,text/csv,text/xml,application/xml" required>
				<button type="submit" class="button sid-secondary"><?php esc_html_e( 'Uvezi katalog', 'sidrena' ); ?></button>
			</div>
		</form>

		<form class="sid-card sid-form sid-standalone-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sidrena_standalone_save">
			<input type="hidden" name="standalone_page" value="<?php echo esc_attr( $page ); ?>">
			<?php wp_nonce_field( 'sidrena_standalone_save' ); ?>
			<div class="sid-table-wrap">
				<table class="widefat striped sid-bulk-table sid-standalone-table">
					<caption class="screen-reader-text"><?php esc_html_e( 'Samostalni Sidrena katalog proizvoda', 'sidrena' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Naziv', 'sidrena' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Šifra', 'sidrena' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Marka', 'sidrena' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Cijena', 'sidrena' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Sidrena', 'sidrena' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Barkod', 'sidrena' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Jedinična', 'sidrena' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Dostupnost', 'sidrena' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Posebna prodaja', 'sidrena' ); ?></th>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Radnje', 'sidrena' ); ?></span></th>
						</tr>
					</thead>
					<tbody id="sidrena-standalone-rows">
						<?php foreach ( $items as $post ) : ?>
							<?php $this->row( $post->ID ); ?>
						<?php endforeach; ?>
						<?php if ( 0 === $total ) : ?>
							<?php $this->row( 0, 'new-0' ); ?>
						<?php elseif ( empty( $items ) ) : ?>
							<tr><td colspan="10"><?php esc_html_e( 'Na ovoj stranici nema proizvoda. Vratite se na prethodnu stranicu kataloga.', 'sidrena' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
			<div class="sid-form-actions">
				<button type="button" class="button sid-secondary" id="sidrena-add-standalone"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e( 'Dodaj proizvod', 'sidrena' ); ?></button>
				<button type="submit" class="button button-primary sid-primary"><?php esc_html_e( 'Spremi ovu stranicu kataloga', 'sidrena' ); ?></button>
			</div>
			<template id="sidrena-standalone-template"><?php $this->row( 0, '__KEY__', true ); ?></template>
		</form>
		<?php if ( $pages > 1 ) : ?>
		<nav class="sid-pagination" aria-label="<?php esc_attr_e( 'Navigacija samostalnog kataloga', 'sidrena' ); ?>">
			<?php if ( $page > 1 ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-catalog&standalone_page=' . ( $page - 1 ) ) ); ?>">← <?php esc_html_e( 'Prethodna', 'sidrena' ); ?></a><?php endif; ?>
			<span><?php echo esc_html( sprintf( __( 'Stranica %1$d od %2$d', 'sidrena' ), min( $page, $pages ), $pages ) ); ?></span>
			<?php if ( $page < $pages ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-catalog&standalone_page=' . ( $page + 1 ) ) ); ?>"><?php esc_html_e( 'Sljedeća', 'sidrena' ); ?> →</a><?php endif; ?>
		</nav>
		<?php endif; ?>
		<?php
	}
	private function row( $id = 0, $key = '', $template = false ) {
		$id  = absint( $id );
		$key = $id ? (string) $id : ( $key ? $key : uniqid( 'new-', false ) );
		$meta = static function ( $name ) use ( $id ) {
			return $id ? get_post_meta( $id, $name, true ) : '';
		};
		$status = $meta( '_sidrena_standalone_unit_status' ) ?: 'review';
		$availability = $meta( '_sidrena_standalone_availability' ) ?: 'dostupno';
		?>
		<tr class="sidrena-standalone-row">
			<td><input type="hidden" name="items[<?php echo esc_attr( $key ); ?>][id]" value="<?php echo esc_attr( $id ); ?>"><input type="text" name="items[<?php echo esc_attr( $key ); ?>][name]" value="<?php echo esc_attr( $id ? get_the_title( $id ) : '' ); ?>" placeholder="<?php esc_attr_e( 'Naziv proizvoda', 'sidrena' ); ?>"><?php if ( $id ) : ?><small class="sid-bulk-meta"><code>[sidrena_cijena id="s<?php echo esc_attr( $id ); ?>"]</code></small><?php endif; ?></td>
			<td><input type="text" name="items[<?php echo esc_attr( $key ); ?>][code]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_code' ) ); ?>"></td>
			<td><input type="text" name="items[<?php echo esc_attr( $key ); ?>][brand]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_brand' ) ); ?>"></td>
			<td><input type="number" min="0" step="0.01" name="items[<?php echo esc_attr( $key ); ?>][current]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_current_price' ) ); ?>"></td>
			<td><input type="number" min="0" step="0.01" name="items[<?php echo esc_attr( $key ); ?>][anchor]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_anchor_price' ) ); ?>"><input type="date" name="items[<?php echo esc_attr( $key ); ?>][anchor_date]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_anchor_date' ) ); ?>"></td>
			<td><input type="text" name="items[<?php echo esc_attr( $key ); ?>][barcode]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_barcode' ) ); ?>"></td>
			<td><select name="items[<?php echo esc_attr( $key ); ?>][unit_status]"><option value="review" <?php selected( $status, 'review' ); ?>><?php esc_html_e( 'Provjeriti', 'sidrena' ); ?></option><option value="required" <?php selected( $status, 'required' ); ?>><?php esc_html_e( 'Obvezna', 'sidrena' ); ?></option><option value="not_required" <?php selected( $status, 'not_required' ); ?>><?php esc_html_e( 'Nije primjenjiva', 'sidrena' ); ?></option><option value="exception" <?php selected( $status, 'exception' ); ?>><?php esc_html_e( 'Iznimka', 'sidrena' ); ?></option></select><input type="number" min="0" step="0.0001" name="items[<?php echo esc_attr( $key ); ?>][quantity]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_quantity' ) ); ?>" placeholder="<?php esc_attr_e( 'Količina pakiranja', 'sidrena' ); ?>"><input type="text" name="items[<?php echo esc_attr( $key ); ?>][quantity_unit]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_quantity_unit' ) ); ?>" placeholder="<?php esc_attr_e( 'g / kg / ml / l / kom', 'sidrena' ); ?>"><input type="text" name="items[<?php echo esc_attr( $key ); ?>][unit]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_unit' ) ); ?>" placeholder="<?php esc_attr_e( 'Jedinica prikaza', 'sidrena' ); ?>"><input type="number" min="0" step="0.0001" name="items[<?php echo esc_attr( $key ); ?>][unit_price]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_unit_price' ) ); ?>" placeholder="<?php esc_attr_e( 'Automatski ako je prazno', 'sidrena' ); ?>"></td>
			<td><select name="items[<?php echo esc_attr( $key ); ?>][availability]"><option value="dostupno" <?php selected( $availability, 'dostupno' ); ?>><?php esc_html_e( 'Dostupno', 'sidrena' ); ?></option><option value="nedostupno" <?php selected( $availability, 'nedostupno' ); ?>><?php esc_html_e( 'Nedostupno', 'sidrena' ); ?></option></select></td>
			<td><input type="text" name="items[<?php echo esc_attr( $key ); ?>][sale_name]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_sale_name' ) ); ?>" placeholder="<?php esc_attr_e( 'npr. Akcija', 'sidrena' ); ?>"><input type="number" min="0" step="0.01" name="items[<?php echo esc_attr( $key ); ?>][lowest_30]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_lowest_30' ) ); ?>" placeholder="<?php esc_attr_e( 'Najniža cijena 30 dana', 'sidrena' ); ?>"></td>
			<td><?php if ( $id ) : ?><label class="sid-inline-delete"><input type="checkbox" name="items[<?php echo esc_attr( $key ); ?>][delete]" value="yes"> <?php esc_html_e( 'Obriši', 'sidrena' ); ?></label><?php else : ?><button type="button" class="button-link-delete sidrena-remove-standalone"><?php esc_html_e( 'Ukloni', 'sidrena' ); ?></button><?php endif; ?></td>
		</tr>
		<?php
	}

	public function save() {
		if ( ! current_user_can( Sidrena_Utils::admin_capability() ) || ! check_admin_referer( 'sidrena_standalone_save' ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}

		$items   = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$page    = max( 1, isset( $_POST['standalone_page'] ) ? absint( $_POST['standalone_page'] ) : 1 );
		$saved   = 0;
		$deleted = 0;
		$errors  = 0;

		foreach ( $items as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = absint( $row['id'] ?? 0 );
			if ( $id && self::POST_TYPE !== get_post_type( $id ) ) {
				++$errors;
				continue;
			}
			if ( $id && 'yes' === ( $row['delete'] ?? '' ) ) {
				if ( wp_delete_post( $id, true ) ) {
					++$deleted;
				} else {
					++$errors;
				}
				continue;
			}

			$name    = sanitize_text_field( $row['name'] ?? '' );
			$current = Sidrena_Utils::decimal( $row['current'] ?? '' );
			$anchor  = Sidrena_Utils::decimal( $row['anchor'] ?? '' );
			if ( ! $id && '' === $name && '' === $current && '' === $anchor ) {
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

			$this->set_meta( $saved_id, '_sidrena_standalone_code', sanitize_text_field( $row['code'] ?? '' ) );
			$this->set_meta( $saved_id, '_sidrena_standalone_brand', sanitize_text_field( $row['brand'] ?? '' ) );
			$this->set_meta( $saved_id, '_sidrena_standalone_current_price', $current );
			$this->set_meta( $saved_id, '_sidrena_standalone_anchor_price', $anchor );
			$this->set_meta( $saved_id, '_sidrena_standalone_anchor_date', Sidrena_Utils::sanitize_date( $row['anchor_date'] ?? '' ) );
			$this->set_meta( $saved_id, '_sidrena_standalone_barcode', sanitize_text_field( $row['barcode'] ?? '' ) );

			$status = sanitize_key( $row['unit_status'] ?? 'review' );
			if ( ! in_array( $status, array( 'review', 'required', 'not_required', 'exception' ), true ) ) {
				$status = 'review';
			}
			$this->set_meta( $saved_id, '_sidrena_standalone_unit_status', $status );
			$quantity      = Sidrena_Utils::decimal( $row['quantity'] ?? '' );
			$quantity_unit = Sidrena_Utils::normalize_unit( $row['quantity_unit'] ?? '' );
			$unit          = sanitize_text_field( $row['unit'] ?? '' );
			$unit_price    = Sidrena_Utils::decimal( $row['unit_price'] ?? '' );
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
			$this->set_meta( $saved_id, '_sidrena_standalone_lowest_30', Sidrena_Utils::decimal( $row['lowest_30'] ?? '' ) );
		}

		Sidrena_Audit::log(
			'standalone_catalog_save',
			$errors ? 'warning' : 'success',
			sprintf( __( 'Samostalni katalog spremljen: %1$d spremljenih, %2$d obrisanih, %3$d grešaka.', 'sidrena' ), $saved, $deleted, $errors ),
			array( 'saved' => $saved, 'deleted' => $deleted, 'errors' => $errors )
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
		if ( ! current_user_can( Sidrena_Utils::admin_capability() ) || ! check_admin_referer( 'sidrena_standalone_import' ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}
		if ( empty( $_FILES['standalone_file'] ) || ! is_array( $_FILES['standalone_file'] ) ) {
			$this->redirect_import( 'standalone_import_failed' );
		}

		$file = $_FILES['standalone_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated before use.
		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$this->redirect_import( 'standalone_import_failed' );
		}
		if ( (int) ( $file['size'] ?? 0 ) > 5 * MB_IN_BYTES ) {
			$this->redirect_import( 'standalone_import_failed' );
		}

		$filename = sanitize_file_name( wp_unslash( $file['name'] ?? '' ) );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'csv', 'xml' ), true ) ) {
			$this->redirect_import( 'standalone_import_failed' );
		}

		$contents = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents || '' === $contents ) {
			$this->redirect_import( 'standalone_import_failed' );
		}
		$contents = Sidrena_Utils::normalize_text_encoding( $contents );

		$rows = 'xml' === $ext ? $this->parse_xml_rows( $contents ) : $this->parse_csv_rows( $contents );
		if ( is_wp_error( $rows ) || empty( $rows ) ) {
			$this->redirect_import( 'standalone_import_failed' );
		}

		$created = 0;
		$updated = 0;
		$skipped = 0;
		foreach ( $rows as $raw ) {
			$row = $this->canonical_import_row( $raw );
			$code = sanitize_text_field( $row['code'] ?? '' );
			$id   = $code ? $this->find_by_code( $code ) : 0;

			if ( ! $id ) {
				$name    = sanitize_text_field( $row['name'] ?? '' );
				$current = Sidrena_Utils::decimal( $row['current'] ?? '' );
				$anchor  = Sidrena_Utils::decimal( $row['anchor'] ?? '' );
				if ( '' === $name || '' === $current || '' === $anchor ) {
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
					wp_update_post( array( 'ID' => $id, 'post_title' => sanitize_text_field( $row['name'] ) ) );
				}
			}

			$this->import_field( $id, '_sidrena_standalone_code', $row, 'code', 'text' );
			$this->import_field( $id, '_sidrena_standalone_brand', $row, 'brand', 'text' );
			$this->import_field( $id, '_sidrena_standalone_current_price', $row, 'current', 'decimal' );
			$this->import_field( $id, '_sidrena_standalone_anchor_price', $row, 'anchor', 'decimal' );
			$this->import_field( $id, '_sidrena_standalone_anchor_date', $row, 'anchor_date', 'date' );
			$this->import_field( $id, '_sidrena_standalone_barcode', $row, 'barcode', 'text' );
			$this->import_field( $id, '_sidrena_standalone_quantity', $row, 'quantity', 'decimal' );
			$this->import_field( $id, '_sidrena_standalone_quantity_unit', $row, 'quantity_unit', 'unit' );
			$this->import_field( $id, '_sidrena_standalone_unit', $row, 'unit', 'text' );
			$this->import_field( $id, '_sidrena_standalone_unit_price', $row, 'unit_price', 'decimal' );
			$this->import_field( $id, '_sidrena_standalone_sale_name', $row, 'sale_name', 'text' );
			$this->import_field( $id, '_sidrena_standalone_lowest_30', $row, 'lowest_30', 'decimal' );

			if ( array_key_exists( 'unit_status', $row ) && '' !== trim( (string) $row['unit_status'] ) ) {
				$status = sanitize_key( $row['unit_status'] );
				if ( in_array( $status, array( 'review', 'required', 'not_required', 'exception' ), true ) ) {
					update_post_meta( $id, '_sidrena_standalone_unit_status', $status );
				}
			}
			$unit_status = sanitize_key( get_post_meta( $id, '_sidrena_standalone_unit_status', true ) ?: 'review' );
			$unit_price  = Sidrena_Utils::decimal( get_post_meta( $id, '_sidrena_standalone_unit_price', true ) );
			$quantity    = Sidrena_Utils::decimal( get_post_meta( $id, '_sidrena_standalone_quantity', true ) );
			$quantity_unit = Sidrena_Utils::normalize_unit( get_post_meta( $id, '_sidrena_standalone_quantity_unit', true ) );
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

		Sidrena_Audit::log(
			'standalone_catalog_import',
			'success',
			sprintf( __( 'Uvoz samostalnog kataloga dovršen: %1$d novih, %2$d ažuriranih, %3$d preskočenih.', 'sidrena' ), $created, $updated, $skipped ),
			array( 'created' => $created, 'updated' => $updated, 'skipped' => $skipped )
		);
		Sidrena_Pricelist::queue_regeneration();
		wp_safe_redirect( admin_url( 'admin.php?page=sidrena-catalog&sid_notice=standalone_imported' ) );
		exit;
	}

	private function parse_csv_rows( $contents ) {
		$resource = fopen( 'php://temp', 'w+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $resource ) {
			return new WP_Error( 'csv_open' );
		}
		fwrite( $resource, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		rewind( $resource );
		$first = fgets( $resource );
		if ( false === $first ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'csv_empty' );
		}
		$delimiter = ';';
		$best = -1;
		foreach ( array( ';', ',', "\t" ) as $candidate ) {
			$count = substr_count( $first, $candidate );
			if ( $count > $best ) {
				$delimiter = $candidate;
				$best = $count;
			}
		}
		rewind( $resource );
		$head = fgetcsv( $resource, 0, $delimiter );
		if ( ! is_array( $head ) ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'csv_header' );
		}
		$head[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $head[0] );
		$head = array_map( array( 'Sidrena_Utils', 'import_header_key' ), $head );
		$nonempty_head = array_values( array_filter( $head ) );
		if ( count( $nonempty_head ) !== count( array_unique( $nonempty_head ) ) ) {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'csv_duplicate_headers', __( 'CSV sadrži duplicirana zaglavlja nakon normalizacije.', 'sidrena' ) );
		}
		$rows = array();
		$count = 0;
		while ( ( $values = fgetcsv( $resource, 0, $delimiter ) ) !== false ) {
			++$count;
			if ( $count > 50000 ) {
				fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				return new WP_Error( 'csv_row_limit', __( 'CSV ima više od dopuštenih 50.000 redaka.', 'sidrena' ) );
			}
			$row = array();
			foreach ( $head as $index => $key ) {
				if ( '' !== $key ) {
					$row[ $key ] = isset( $values[ $index ] ) ? $values[ $index ] : '';
				}
			}
			if ( ! empty( array_filter( $row, static function ( $value ) { return '' !== trim( (string) $value ); } ) ) ) {
				$rows[] = $row;
			}
		}
		fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $rows;
	}

	private function parse_xml_rows( $contents ) {
		if ( preg_match( '/<!\s*(DOCTYPE|ENTITY)\b/i', (string) $contents ) ) {
			return new WP_Error( 'xml_unsafe', __( 'XML s DOCTYPE ili ENTITY deklaracijama nije dopušten.', 'sidrena' ) );
		}
		if ( ! function_exists( 'simplexml_load_string' ) ) {
			return new WP_Error( 'xml_unavailable' );
		}
		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $contents, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
		libxml_clear_errors();
		if ( false === $xml ) {
			return new WP_Error( 'xml_invalid' );
		}

		$nodes = $xml->children();
		$rows = array();
		foreach ( $nodes as $node ) {
			if ( 0 === count( $node->children() ) ) {
				continue;
			}
			$row = array();
			foreach ( $node->children() as $key => $value ) {
				$row[ sanitize_key( (string) $key ) ] = (string) $value;
			}
			if ( ! empty( $row ) ) {
				$rows[] = $row;
			}
		}
		return $rows;
	}

	private function canonical_import_row( $row ) {
		$normalized = array();
		foreach ( $row as $key => $value ) {
			$normalized[ Sidrena_Utils::import_header_key( $key ) ] = $value;
		}

		$aliases = array(
			'name'          => array( 'name', 'naziv', 'naziv_proizvoda' ),
			'code'          => array( 'code', 'sku', 'sifra', 'sifra_proizvoda', 'sifra_artikla' ),
			'brand'         => array( 'brand', 'marka', 'marka_proizvoda' ),
			'current'       => array( 'current', 'price', 'cijena', 'maloprodajna_cijena', 'mpc' ),
			'anchor'        => array( 'anchor', 'anchor_price', 'sidrena_cijena', 'dodatna_cijena' ),
			'anchor_date'   => array( 'anchor_date', 'datum_sidrene_cijene', 'referentni_datum' ),
			'barcode'       => array( 'barcode', 'barkod', 'ean', 'gtin' ),
			'unit_status'   => array( 'unit_status', 'jedinicna_status', 'jedinicna_cijena_status' ),
			'quantity'      => array( 'quantity', 'kolicina', 'kolicina_pakiranja', 'neto_kolicina', 'neto_kolicina_proizvoda' ),
			'quantity_unit' => array( 'quantity_unit', 'jedinica_kolicine', 'jedinica_pakiranja' ),
			'unit'          => array( 'unit', 'jedinica', 'jedinica_mjere' ),
			'unit_price'    => array( 'unit_price', 'cijena_jedinice_mjere', 'cijena_za_jedinicu_mjere' ),
			'availability'  => array( 'availability', 'dostupnost', 'raspolozivost', 'status_dostupnosti' ),
			'sale_name'     => array( 'sale_name', 'naziv_posebnog_oblika', 'naziv_posebnog_oblika_prodaje' ),
			'lowest_30'     => array( 'lowest_30', 'naj_niza_30', 'najniza_cijena_30_dana', 'najniza_cijena_prethodnih_30_dana' ),
		);
		$out = array();
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
			$value = Sidrena_Utils::decimal( $value );
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

	public function price_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'sidrena_cijena' );
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
		$out = '<span class="sidrena-standalone-price">';
		if ( '' !== $current ) {
			$out .= '<span class="sidrena-standalone-price__current">' . esc_html( Sidrena_Utils::money( $current ) . ' ' . $currency ) . '</span>';
		}
		$sale_name = trim( (string) get_post_meta( $id, '_sidrena_standalone_sale_name', true ) );
		$lowest_30 = Sidrena_Utils::decimal( get_post_meta( $id, '_sidrena_standalone_lowest_30', true ) );
		if ( $sale_name && '' !== $lowest_30 ) {
			$out .= '<span class="sidrena-lowest"><span class="sidrena-lowest__label">' . esc_html__( 'Najniža cijena u prethodnih 30 dana', 'sidrena' ) . ':</span> <span class="sidrena-lowest__value">' . esc_html( Sidrena_Utils::money( $lowest_30 ) . ' ' . $currency ) . '</span></span>';
		}
		if ( '' !== $anchor ) {
			$date = get_post_meta( $id, '_sidrena_standalone_anchor_date', true ) ?: Sidrena_Utils::settings()['default_ref_date'];
			$out .= '<span class="sidrena-anchor"><span class="sidrena-anchor__label">' . esc_html( Sidrena_Utils::anchor_label( $date ) ) . ':</span> <span class="sidrena-anchor__value">' . esc_html( Sidrena_Utils::money( $anchor ) . ' ' . $currency ) . '</span></span>';
		}
		$out .= '</span>';
		return $out;
	}
}
