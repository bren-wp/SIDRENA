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

			$rows[] = array(
				'_sidrena_lowest_30'        => Sidrena_Utils::decimal( get_post_meta( $id, '_sidrena_standalone_lowest_30', true ) ),
				'_sidrena_item_id'           => $id,
				'_sidrena_unit_status'       => $unit_status ? $unit_status : 'review',
				'_sidrena_location_explicit' => 'yes',
				'naziv'                      => get_the_title( $post ),
				'sifra'                      => get_post_meta( $id, '_sidrena_standalone_code', true ),
				'marka'                      => get_post_meta( $id, '_sidrena_standalone_brand', true ),
				'jedinica_mjere'             => get_post_meta( $id, '_sidrena_standalone_unit', true ),
				'cijena_za_jedinicu_mjere'   => Sidrena_Utils::money( get_post_meta( $id, '_sidrena_standalone_unit_price', true ), 4 ),
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
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$items = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 200,
				'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
			)
		);
		?>
		<div class="sid-page-head">
			<div>
				<span class="sid-kicker"><?php esc_html_e( 'Samostalni katalog', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Proizvodi bez WooCommercea', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'Za običan WordPress unesite proizvode izravno u Sidreni. Isti podaci koriste se za javni HTML cjenik, CSV/XML, arhivu i tehničku provjeru.', 'sidrena' ); ?></p>
			</div>
		</div>
		<form class="sid-card sid-form sid-standalone-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sidrena_standalone_save">
			<?php wp_nonce_field( 'sidrena_standalone_save' ); ?>
			<div class="sid-table-wrap">
				<table class="widefat striped sid-bulk-table sid-standalone-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Naziv', 'sidrena' ); ?></th>
							<th><?php esc_html_e( 'Šifra', 'sidrena' ); ?></th>
							<th><?php esc_html_e( 'Marka', 'sidrena' ); ?></th>
							<th><?php esc_html_e( 'Cijena', 'sidrena' ); ?></th>
							<th><?php esc_html_e( 'Sidrena', 'sidrena' ); ?></th>
							<th><?php esc_html_e( 'Barkod', 'sidrena' ); ?></th>
							<th><?php esc_html_e( 'Jedinična', 'sidrena' ); ?></th>
							<th><?php esc_html_e( 'Dostupnost', 'sidrena' ); ?></th>
							<th><?php esc_html_e( 'Posebna prodaja', 'sidrena' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody id="sidrena-standalone-rows">
						<?php foreach ( $items as $post ) : ?>
							<?php $this->row( $post->ID ); ?>
						<?php endforeach; ?>
						<?php if ( empty( $items ) ) : ?>
							<?php $this->row( 0, 'new-0' ); ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
			<div class="sid-form-actions">
				<button type="button" class="button sid-secondary" id="sidrena-add-standalone"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e( 'Dodaj proizvod', 'sidrena' ); ?></button>
				<button type="submit" class="button button-primary sid-primary"><?php esc_html_e( 'Spremi katalog', 'sidrena' ); ?></button>
			</div>
			<template id="sidrena-standalone-template"><?php $this->row( 0, '__KEY__', true ); ?></template>
		</form>
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
			<td><input type="hidden" name="items[<?php echo esc_attr( $key ); ?>][id]" value="<?php echo esc_attr( $id ); ?>"><input type="text" name="items[<?php echo esc_attr( $key ); ?>][name]" value="<?php echo esc_attr( $id ? get_the_title( $id ) : '' ); ?>" placeholder="<?php esc_attr_e( 'Naziv proizvoda', 'sidrena' ); ?>"></td>
			<td><input type="text" name="items[<?php echo esc_attr( $key ); ?>][code]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_code' ) ); ?>"></td>
			<td><input type="text" name="items[<?php echo esc_attr( $key ); ?>][brand]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_brand' ) ); ?>"></td>
			<td><input type="number" min="0" step="0.01" name="items[<?php echo esc_attr( $key ); ?>][current]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_current_price' ) ); ?>"></td>
			<td><input type="number" min="0" step="0.01" name="items[<?php echo esc_attr( $key ); ?>][anchor]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_anchor_price' ) ); ?>"><input type="date" name="items[<?php echo esc_attr( $key ); ?>][anchor_date]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_anchor_date' ) ); ?>"></td>
			<td><input type="text" name="items[<?php echo esc_attr( $key ); ?>][barcode]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_barcode' ) ); ?>"></td>
			<td><select name="items[<?php echo esc_attr( $key ); ?>][unit_status]"><option value="review" <?php selected( $status, 'review' ); ?>><?php esc_html_e( 'Provjeriti', 'sidrena' ); ?></option><option value="required" <?php selected( $status, 'required' ); ?>><?php esc_html_e( 'Obvezna', 'sidrena' ); ?></option><option value="not_required" <?php selected( $status, 'not_required' ); ?>><?php esc_html_e( 'Nije primjenjiva', 'sidrena' ); ?></option><option value="exception" <?php selected( $status, 'exception' ); ?>><?php esc_html_e( 'Iznimka', 'sidrena' ); ?></option></select><input type="text" name="items[<?php echo esc_attr( $key ); ?>][unit]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_unit' ) ); ?>" placeholder="kg / l / m"><input type="number" min="0" step="0.0001" name="items[<?php echo esc_attr( $key ); ?>][unit_price]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_unit_price' ) ); ?>"></td>
			<td><select name="items[<?php echo esc_attr( $key ); ?>][availability]"><option value="dostupno" <?php selected( $availability, 'dostupno' ); ?>><?php esc_html_e( 'Dostupno', 'sidrena' ); ?></option><option value="nedostupno" <?php selected( $availability, 'nedostupno' ); ?>><?php esc_html_e( 'Nedostupno', 'sidrena' ); ?></option></select></td>
			<td><input type="text" name="items[<?php echo esc_attr( $key ); ?>][sale_name]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_sale_name' ) ); ?>" placeholder="<?php esc_attr_e( 'npr. Akcija', 'sidrena' ); ?>"><input type="number" min="0" step="0.01" name="items[<?php echo esc_attr( $key ); ?>][lowest_30]" value="<?php echo esc_attr( $meta( '_sidrena_standalone_lowest_30' ) ); ?>" placeholder="<?php esc_attr_e( 'Najniža cijena 30 dana', 'sidrena' ); ?>"></td>
			<td><?php if ( $id ) : ?><label class="sid-inline-delete"><input type="checkbox" name="items[<?php echo esc_attr( $key ); ?>][delete]" value="yes"> <?php esc_html_e( 'Obriši', 'sidrena' ); ?></label><?php else : ?><button type="button" class="button-link-delete sidrena-remove-standalone"><?php esc_html_e( 'Ukloni', 'sidrena' ); ?></button><?php endif; ?></td>
		</tr>
		<?php
	}

	public function save() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'sidrena_standalone_save' ) ) {
			wp_die( esc_html__( 'Nedopušten zahtjev.', 'sidrena' ) );
		}
		$items = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$position = 0;
		foreach ( $items as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = absint( $row['id'] ?? 0 );
			if ( $id && self::POST_TYPE !== get_post_type( $id ) ) {
				continue;
			}
			if ( $id && 'yes' === ( $row['delete'] ?? '' ) ) {
				wp_delete_post( $id, true );
				continue;
			}

			$name    = sanitize_text_field( $row['name'] ?? '' );
			$current = Sidrena_Utils::decimal( $row['current'] ?? '' );
			$anchor  = Sidrena_Utils::decimal( $row['anchor'] ?? '' );
			if ( ! $id && '' === $name && '' === $current && '' === $anchor ) {
				continue;
			}

			$postarr = array(
				'ID'         => $id,
				'post_type'  => self::POST_TYPE,
				'post_title' => $name ? $name : __( 'Proizvod bez naziva', 'sidrena' ),
				'post_status'=> $name && '' !== $current ? 'publish' : 'draft',
				'menu_order' => $position,
			);
			$saved_id = wp_insert_post( $postarr, true );
			if ( is_wp_error( $saved_id ) || ! $saved_id ) {
				continue;
			}
			++$position;

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
			$this->set_meta( $saved_id, '_sidrena_standalone_unit', sanitize_text_field( $row['unit'] ?? '' ) );
			$this->set_meta( $saved_id, '_sidrena_standalone_unit_price', Sidrena_Utils::decimal( $row['unit_price'] ?? '' ) );

			$availability = sanitize_key( $row['availability'] ?? 'dostupno' );
			if ( ! in_array( $availability, array( 'dostupno', 'nedostupno' ), true ) ) {
				$availability = 'dostupno';
			}
			$this->set_meta( $saved_id, '_sidrena_standalone_availability', $availability );
			$this->set_meta( $saved_id, '_sidrena_standalone_sale_name', sanitize_text_field( $row['sale_name'] ?? '' ) );
			$this->set_meta( $saved_id, '_sidrena_standalone_lowest_30', Sidrena_Utils::decimal( $row['lowest_30'] ?? '' ) );
		}

		Sidrena_Audit::log( 'standalone_catalog_save', 'success', __( 'Samostalni Sidrena katalog je spremljen.', 'sidrena' ) );
		Sidrena_Pricelist::queue_regeneration();
		wp_safe_redirect( admin_url( 'admin.php?page=sidrena-catalog&sid_notice=bulk_saved' ) );
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
