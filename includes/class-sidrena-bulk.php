<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compact bulk editor for the fields needed by Sidrena exports.
 */
final class Sidrena_Bulk {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		if ( Sidrena_Utils::is_woocommerce_active() ) {
			add_action( 'admin_post_sidrena_bulk_save', array( $this, 'save' ) );
		}
	}

	public function render() {
		if ( ! Sidrena_Utils::current_user_can_manage() ) {
			return;
		}

		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Katalog web trgovine nije dostupan. Provjerite je li potrebna integracija aktivna.', 'sidrena' ) . '</p></div>';
			return;
		}

		$page                  = max( 1, isset( $_GET['catalog_page'] ) ? absint( $_GET['catalog_page'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page              = 40;
		$query                 = new WC_Product_Query(
			array(
				'limit'    => $per_page,
				'page'     => $page,
				'status'   => array( 'publish', 'private', 'draft' ),
				'return'   => 'objects',
				'orderby'  => 'ID',
				'order'    => 'DESC',
				'paginate' => true,
			)
		);
		$result                = $query->get_products();
		$items                 = is_object( $result ) && isset( $result->products ) ? $result->products : array();
		$pages                 = is_object( $result ) && isset( $result->max_num_pages ) ? max( 1, absint( $result->max_num_pages ) ) : 1;
		$total                 = is_object( $result ) && isset( $result->total ) ? absint( $result->total ) : count( $items );
		$product_types         = function_exists( 'wc_get_product_types' ) ? wc_get_product_types() : array();
		$product_count_caption = sprintf(
			/* translators: %d: total number of WooCommerce products. */
			_n( '%d proizvod', '%d proizvoda', $total, 'sidrena' ),
			$total
		);
		$page_caption = sprintf(
			/* translators: 1: current catalog page, 2: total number of catalog pages. */
			__( 'Stranica %1$d od %2$d', 'sidrena' ),
			$page,
			$pages
		);
		?>
		<div class="sid-page-head sid-reference-page-head">
			<div>
				<span class="sid-kicker"><?php esc_html_e( 'Proizvodi web trgovine', 'sidrena' ); ?></span>
				<h2><?php esc_html_e( 'Proizvodi web trgovine', 'sidrena' ); ?></h2>
				<p><?php esc_html_e( 'SIDRENA koristi postojeći katalog web trgovine kao izvor podataka. Nema dupliciranja proizvoda; ovdje uređujete samo SIDRENA podatke potrebne za cjenik i prikaz cijena.', 'sidrena' ); ?></p>
			</div>
			<div class="sid-head-inline-actions"><span class="sid-status-pill <?php echo $total > 0 ? 'is-ok' : 'is-warn'; ?>"><?php echo esc_html( $product_count_caption ); ?></span><a class="button sid-secondary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>"><span class="dashicons dashicons-external"></span><?php esc_html_e( 'Otvori proizvode trgovine', 'sidrena' ); ?></a></div>
		</div>

		<section class="sid-card sid-reference-panel sid-woo-catalog-summary">
			<div class="sid-reference-mini-metrics">
				<div><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong><span><?php esc_html_e( 'proizvoda trgovine', 'sidrena' ); ?></span></div>
				<div><strong><?php echo esc_html( number_format_i18n( count( $items ) ) ); ?></strong><span><?php esc_html_e( 'na ovoj stranici', 'sidrena' ); ?></span></div>
				<div><strong><?php echo esc_html( $page . '/' . $pages ); ?></strong><span><?php esc_html_e( 'stranica kataloga', 'sidrena' ); ?></span></div>
			</div>
		</section>

		<form class="sid-card sid-bulk-card sid-reference-panel sid-woo-catalog-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sidrena_bulk_save">
			<input type="hidden" name="catalog_page" value="<?php echo esc_attr( $page ); ?>">
			<?php wp_nonce_field( 'sidrena_bulk_save' ); ?>
			<div class="sid-table-wrap sid-woo-table-wrap">
				<table class="widefat sid-bulk-table sid-woo-compact-table">
					<caption class="screen-reader-text"><?php esc_html_e( 'SIDRENA katalog web trgovine', 'sidrena' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Proizvod', 'sidrena' ); ?></th>
							<th scope="col"><?php esc_html_e( 'SKU', 'sidrena' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Trenutna cijena', 'sidrena' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Sidrena cijena', 'sidrena' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Datum', 'sidrena' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Javni cjenik', 'sidrena' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					if ( empty( $items ) ) :
						?>
						<tr><td class="sid-table-empty-cell" colspan="6"><strong><?php esc_html_e( 'Katalog web trgovine je prazan', 'sidrena' ); ?></strong><span><?php esc_html_e( 'Dodajte proizvod u web trgovinu; SIDRENA će ga koristiti bez stvaranja paralelnog kataloga.', 'sidrena' ); ?></span></td></tr><?php endif; ?>
					<?php foreach ( $items as $product ) : ?>
						<?php
						$id                 = $product->get_id();
						$anchor             = get_post_meta( $id, '_sidrena_anchor_price', true );
						$anchor_date        = Sidrena_Utils::current_reference_date( $id );
						$visibility_raw     = get_post_meta( $id, '_sidrena_cjenik_visibility', true );
						$visibility         = $visibility_raw ? $visibility_raw : 'auto';
						$catalog_visibility = is_callable( array( $product, 'get_catalog_visibility' ) ) ? $product->get_catalog_visibility() : 'visible';
						$public_included    = 'include' === $visibility || ( 'exclude' !== $visibility && 'hidden' !== $catalog_visibility );
						$sku                = $product->get_sku();
						$price              = $product->get_price();
						$product_type       = sanitize_key( $product->get_type() );
						$product_type_label = isset( $product_types[ $product_type ] ) ? $product_types[ $product_type ] : $product_type;
						$safe_suggestions   = $this->safe_suggestions( $product );
						$group              = get_post_meta( $id, '_sidrena_reference_group', true );
						$unit_status_raw    = get_post_meta( $id, '_sidrena_unit_price_status', true );
						$unit_status        = $unit_status_raw ? $unit_status_raw : 'review';
						?>
						<tr class="sid-woo-product-row">
							<td><strong><?php echo esc_html( $product->get_name() ); ?></strong><span class="sid-bulk-meta">#<?php echo esc_html( $id ); ?> · <?php echo esc_html( $product_type_label ); ?></span></td>
							<td><code><?php echo esc_html( $sku ? $sku : '—' ); ?></code></td>
							<td><strong class="sid-woo-current-price"><?php echo '' !== $price ? wp_kses_post( wc_price( (float) $price ) ) : '—'; ?></strong></td>
							<td><input aria-label="<?php esc_attr_e( 'Sidrena cijena', 'sidrena' ); ?>" type="number" min="0" step="0.01" name="items[<?php echo esc_attr( $id ); ?>][anchor]" value="<?php echo esc_attr( $anchor ); ?>"></td>
							<td><strong><?php echo esc_html( Sidrena_Utils::date_display( $anchor_date ) ); ?></strong><small class="sid-cell-sub"><?php esc_html_e( 'Zaključano SIDRENA pravilima', 'sidrena' ); ?></small></td>
							<td><span class="sid-status-pill <?php echo $public_included ? 'is-ok' : 'is-warn'; ?>"><?php echo $public_included ? esc_html__( 'Uključen', 'sidrena' ) : esc_html__( 'Isključen', 'sidrena' ); ?></span>
							<?php
							if ( ! $public_included && 'hidden' === $catalog_visibility && 'auto' === $visibility ) :
								?>
								<small class="sid-cell-sub"><?php esc_html_e( 'Trgovina: skriveno', 'sidrena' ); ?></small><?php endif; ?></td>
						</tr>
						<tr class="sid-woo-product-details-row">
							<td colspan="6">
								<details class="sid-row-details">
									<summary><span class="dashicons dashicons-admin-generic"></span><?php esc_html_e( 'Napredna SIDRENA polja', 'sidrena' ); ?><span class="sid-row-details__hint"><?php esc_html_e( 'šifra, marka, barkod, jedinice i pravilo javnog cjenika', 'sidrena' ); ?></span></summary>
									<div class="sid-row-details__grid">
										<label><span><?php esc_html_e( 'Šifra', 'sidrena' ); ?></span><input type="text" name="items[<?php echo esc_attr( $id ); ?>][code]" value="<?php echo esc_attr( get_post_meta( $id, '_sidrena_code', true ) ); ?>" placeholder="<?php echo esc_attr( $sku ); ?>" data-sidrena-safe-fill="code" data-sidrena-suggest="<?php echo esc_attr( $safe_suggestions['code'] ?? '' ); ?>"></label>
										<label><span><?php esc_html_e( 'Marka', 'sidrena' ); ?></span><input type="text" name="items[<?php echo esc_attr( $id ); ?>][brand]" value="<?php echo esc_attr( get_post_meta( $id, '_sidrena_brand', true ) ); ?>" data-sidrena-safe-fill="brand" data-sidrena-suggest="<?php echo esc_attr( $safe_suggestions['brand'] ?? '' ); ?>"></label>
										<label><span><?php esc_html_e( 'Barkod', 'sidrena' ); ?></span><input type="text" name="items[<?php echo esc_attr( $id ); ?>][barcode]" value="<?php echo esc_attr( get_post_meta( $id, '_sidrena_barcode', true ) ); ?>" placeholder="<?php echo esc_attr( Sidrena_Utils::get_barcode( $product ) ); ?>" data-sidrena-safe-fill="barcode" data-sidrena-suggest="<?php echo esc_attr( $safe_suggestions['barcode'] ?? '' ); ?>"></label>
										<label><span><?php esc_html_e( 'Pravni ruleset sidrene cijene', 'sidrena' ); ?></span><select name="items[<?php echo esc_attr( $id ); ?>][group]"><option value="standard" <?php selected( $group, 'standard' ); ?>><?php esc_html_e( 'Zaključano: 10.09.2026.', 'sidrena' ); ?></option><option value="fmcg" <?php selected( $group, 'fmcg' ); ?>><?php esc_html_e( 'Zaključano FMCG: 02.05.2025.', 'sidrena' ); ?></option><option value="custom" <?php selected( $group, 'custom' ); ?>><?php esc_html_e( 'Novouveden nakon 10.09.2026. — datum automatski', 'sidrena' ); ?></option></select></label>
										<label><span><?php esc_html_e( 'Status jedinične cijene', 'sidrena' ); ?></span><select name="items[<?php echo esc_attr( $id ); ?>][unit_status]"><option value="review" <?php selected( $unit_status, 'review' ); ?>><?php esc_html_e( 'Provjeriti', 'sidrena' ); ?></option><option value="required" <?php selected( $unit_status, 'required' ); ?>><?php esc_html_e( 'Obvezna', 'sidrena' ); ?></option><option value="not_required" <?php selected( $unit_status, 'not_required' ); ?>><?php esc_html_e( 'Nije primjenjiva', 'sidrena' ); ?></option><option value="exception" <?php selected( $unit_status, 'exception' ); ?>><?php esc_html_e( 'Iznimka', 'sidrena' ); ?></option></select></label>
										<label><span><?php esc_html_e( 'Količina', 'sidrena' ); ?></span><input type="number" min="0" step="0.0001" name="items[<?php echo esc_attr( $id ); ?>][quantity]" value="<?php echo esc_attr( get_post_meta( $id, '_sidrena_quantity', true ) ); ?>"></label>
										<label><span><?php esc_html_e( 'Pakiranje', 'sidrena' ); ?></span><input type="text" name="items[<?php echo esc_attr( $id ); ?>][quantity_unit]" value="<?php echo esc_attr( get_post_meta( $id, '_sidrena_quantity_unit', true ) ); ?>" placeholder="g / kg / ml / l"></label>
										<label><span><?php esc_html_e( 'Jedinica', 'sidrena' ); ?></span><input type="text" name="items[<?php echo esc_attr( $id ); ?>][unit]" value="<?php echo esc_attr( get_post_meta( $id, '_sidrena_unit', true ) ); ?>" placeholder="kg / l / m"></label>
										<label><span><?php esc_html_e( 'Iznos / jedinica', 'sidrena' ); ?></span><input type="number" min="0" step="0.0001" name="items[<?php echo esc_attr( $id ); ?>][unit_price]" value="<?php echo esc_attr( get_post_meta( $id, '_sidrena_unit_price', true ) ); ?>" placeholder="<?php esc_attr_e( 'automatski', 'sidrena' ); ?>"></label>
										<label><span><?php esc_html_e( 'Javni cjenik', 'sidrena' ); ?></span><select name="items[<?php echo esc_attr( $id ); ?>][cjenik_visibility]"><option value="auto" <?php selected( $visibility, 'auto' ); ?>><?php esc_html_e( 'Automatski', 'sidrena' ); ?></option><option value="include" <?php selected( $visibility, 'include' ); ?>><?php esc_html_e( 'Uvijek uključi', 'sidrena' ); ?></option><option value="exclude" <?php selected( $visibility, 'exclude' ); ?>><?php esc_html_e( 'Isključi', 'sidrena' ); ?></option></select></label>
									</div>
									<div class="sid-safe-fill">
										<button type="button" class="button sid-secondary sid-safe-fill-row"><span class="dashicons dashicons-database-import"></span><?php esc_html_e( 'Popuni dostupna prazna polja', 'sidrena' ); ?></button>
										<span><?php esc_html_e( 'Popunjava samo praznu šifru, marku i barkod kada postoje pouzdani izvori trgovine. Povijesne cijene, pravna izuzeća i druga polja bez sigurnog izvora ostaju nepromijenjena.', 'sidrena' ); ?></span>
									</div>
								</details>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<div class="sid-bulk-actions"><div class="sid-bulk-actions__primary"><button class="button button-primary sid-primary" type="submit"><span class="dashicons dashicons-saved"></span><?php esc_html_e( 'Spremi SIDRENA podatke', 'sidrena' ); ?></button><button class="button sid-secondary" type="button" id="sid-safe-fill-page"><span class="dashicons dashicons-database-import"></span><?php esc_html_e( 'Popuni prazna polja na stranici', 'sidrena' ); ?></button></div><span><?php echo esc_html( $page_caption ); ?></span></div>
		</form>
		<?php if ( $pages > 1 ) : ?>
		<nav class="sid-pagination" aria-label="<?php esc_attr_e( 'Navigacija kataloga', 'sidrena' ); ?>">
			<?php
			if ( $page > 1 ) :
				?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-catalog&catalog_page=' . ( $page - 1 ) ) ); ?>">← <?php esc_html_e( 'Prethodna', 'sidrena' ); ?></a><?php endif; ?>
			<?php
			if ( $page < $pages ) :
				?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-catalog&catalog_page=' . ( $page + 1 ) ) ); ?>"><?php esc_html_e( 'Sljedeća', 'sidrena' ); ?> →</a><?php endif; ?>
		</nav>
		<?php endif; ?>
		<?php
	}


	public function save() {
		if ( ! Sidrena_Utils::current_user_can_manage() ) {
			wp_die( esc_html__( 'Nemate dopuštenje za ovu radnju.', 'sidrena' ) );
		}
		check_admin_referer( 'sidrena_bulk_save' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() verified this form submission immediately above.
		$items   = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? map_deep( wp_unslash( $_POST['items'] ), 'sanitize_text_field' ) : array();
		$updated = 0;

		foreach ( $items as $id => $row ) {
			$id = absint( $id );
			if ( ! $id || ! is_array( $row ) || 'product' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}

			$this->set_text_meta( $id, '_sidrena_code', isset( $row['code'] ) ? $row['code'] : '' );
			$this->set_text_meta( $id, '_sidrena_brand', isset( $row['brand'] ) ? $row['brand'] : '' );
			$this->set_text_meta( $id, '_sidrena_barcode', isset( $row['barcode'] ) ? $row['barcode'] : '' );

			$anchor = Sidrena_Utils::decimal( isset( $row['anchor'] ) ? $row['anchor'] : '' );
			if ( '' === $anchor ) {
				delete_post_meta( $id, '_sidrena_anchor_price' );
			} else {
				update_post_meta( $id, '_sidrena_anchor_price', $anchor );
			}

			$group = sanitize_key( isset( $row['group'] ) ? $row['group'] : 'standard' );
			if ( ! in_array( $group, array( 'standard', 'fmcg', 'custom' ), true ) ) {
				$group = 'standard';
			}
			if ( 'custom' === $group ) {
				$custom_date = Sidrena_Utils::custom_reference_date( get_post_meta( $id, '_sidrena_anchor_date', true ) );
				if ( ! $custom_date ) {
					$custom_date = Sidrena_Utils::first_publication_reference_date( $id );
				}
				if ( $custom_date ) {
					update_post_meta( $id, '_sidrena_anchor_date', $custom_date );
				} else {
					$group = 'standard';
					delete_post_meta( $id, '_sidrena_anchor_date' );
				}
			} else {
				delete_post_meta( $id, '_sidrena_anchor_date' );
			}
			update_post_meta( $id, '_sidrena_reference_group', $group );

			$unit_status = sanitize_key( isset( $row['unit_status'] ) ? $row['unit_status'] : 'review' );
			if ( ! in_array( $unit_status, array( 'review', 'required', 'not_required', 'exception' ), true ) ) {
				$unit_status = 'review';
			}
			update_post_meta( $id, '_sidrena_unit_price_status', $unit_status );
			$quantity = Sidrena_Utils::decimal( isset( $row['quantity'] ) ? $row['quantity'] : '' );
			if ( '' === $quantity ) {
				delete_post_meta( $id, '_sidrena_quantity' );
			} else {
				update_post_meta( $id, '_sidrena_quantity', $quantity );
			}
			$quantity_unit = Sidrena_Utils::normalize_unit( isset( $row['quantity_unit'] ) ? $row['quantity_unit'] : '' );
			if ( '' === $quantity_unit ) {
				delete_post_meta( $id, '_sidrena_quantity_unit' );
			} else {
				update_post_meta( $id, '_sidrena_quantity_unit', $quantity_unit );
			}
			$this->set_text_meta( $id, '_sidrena_unit', isset( $row['unit'] ) ? $row['unit'] : '' );

			$unit_price = Sidrena_Utils::decimal( isset( $row['unit_price'] ) ? $row['unit_price'] : '' );
			if ( 'required' === $unit_status && '' === $unit_price && '' !== $quantity && '' !== $quantity_unit ) {
				$product = wc_get_product( $id );
				if ( $product ) {
					$raw_price = $product->get_price( 'edit' );
					if ( '' !== $raw_price ) {
						$retail     = function_exists( 'wc_get_price_including_tax' ) ? wc_get_price_including_tax( $product, array( 'price' => (float) $raw_price ) ) : (float) $raw_price;
						$calculated = Sidrena_Utils::calculate_unit_price( $retail, $quantity, $quantity_unit );
						if ( $calculated ) {
							$this->set_text_meta( $id, '_sidrena_unit', $calculated['unit'] );
							$unit_price = $calculated['unit_price'];
						}
					}
				}
			}
			if ( '' === $unit_price ) {
				delete_post_meta( $id, '_sidrena_unit_price' );
			} else {
				update_post_meta( $id, '_sidrena_unit_price', $unit_price );
			}
			$cjenik_visibility = sanitize_key( isset( $row['cjenik_visibility'] ) ? $row['cjenik_visibility'] : 'auto' );
			if ( ! in_array( $cjenik_visibility, array( 'auto', 'include', 'exclude' ), true ) ) {
				$cjenik_visibility = 'auto';
			}
			if ( 'auto' === $cjenik_visibility ) {
				delete_post_meta( $id, '_sidrena_cjenik_visibility' );
			} else {
				update_post_meta( $id, '_sidrena_cjenik_visibility', $cjenik_visibility );
			}
			++$updated;
		}

		Sidrena_Audit::log( 'bulk_catalog_save', 'success', sprintf( 'Masovno spremljeno %d proizvoda.', $updated ), array( 'count' => $updated ) );
		Sidrena_Pricelist::queue_regeneration();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Same verified sidrena_bulk_save form submission.
		$page = max( 1, isset( $_POST['catalog_page'] ) ? absint( $_POST['catalog_page'] ) : 1 );
		wp_safe_redirect( admin_url( 'admin.php?page=sidrena-catalog&catalog_page=' . $page . '&sid_notice=bulk_saved' ) );
		exit;
	}

	private function safe_suggestions( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return array();
		}

		$suggestions = array(
			'code'    => trim( (string) $product->get_sku() ),
			'brand'   => trim( (string) Sidrena_Utils::get_brand( $product ) ),
			'barcode' => trim( (string) Sidrena_Utils::get_barcode( $product ) ),
		);
		$suggestions = apply_filters( 'sidrena_safe_field_suggestions', $suggestions, $product );
		$clean       = array();
		foreach ( array( 'code', 'brand', 'barcode' ) as $key ) {
			$value = is_array( $suggestions ) && isset( $suggestions[ $key ] ) ? sanitize_text_field( (string) $suggestions[ $key ] ) : '';
			if ( '' !== $value ) {
				$clean[ $key ] = $value;
			}
		}
		return $clean;
	}

	private function set_text_meta( $id, $key, $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			delete_post_meta( $id, $key );
			return;
		}
		update_post_meta( $id, $key, $value );
	}
}
