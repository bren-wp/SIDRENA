<?php
/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo
 * @link https://sidrene-cijene.com.hr/
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
			echo '<div class="notice notice-error"><p>' . esc_html__( 'WooCommerce nije dostupan. Sidrena WooCommerce katalog nije moguće otvoriti.', 'sidrena' ) . '</p></div>';
			return;
		}

		$page    = max( 1, isset( $_GET['catalog_page'] ) ? absint( $_GET['catalog_page'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = 40;
		$query = new WC_Product_Query(
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
		$result = $query->get_products();
		$items  = is_object( $result ) && isset( $result->products ) ? $result->products : array();
		$pages  = is_object( $result ) && isset( $result->max_num_pages ) ? max( 1, absint( $result->max_num_pages ) ) : 1;
		?>
		<div class="sid-page-head"><div><span class="sid-kicker"><?php esc_html_e( 'Masovno uređivanje', 'sidrena' ); ?></span><h2><?php esc_html_e( 'WooCommerce katalog', 'sidrena' ); ?></h2><p><?php esc_html_e( 'Uredite šifru, marku, sidrenu cijenu, referentni datum, referentnu skupinu i jediničnu cijenu bez otvaranja svakog proizvoda zasebno. Varijacije se mogu dodatno prilagoditi na WooCommerce stranici proizvoda.', 'sidrena' ); ?></p></div></div>
		<form class="sid-card sid-bulk-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sidrena_bulk_save">
			<input type="hidden" name="catalog_page" value="<?php echo esc_attr( $page ); ?>">
			<?php wp_nonce_field( 'sidrena_bulk_save' ); ?>
			<div class="sid-table-wrap">
			<table class="widefat striped sid-bulk-table">
				<caption class="screen-reader-text"><?php esc_html_e( 'WooCommerce Sidrena katalog', 'sidrena' ); ?></caption>
				<thead><tr><th scope="col"><?php esc_html_e( 'Proizvod', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Šifra', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Marka', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Barkod', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Sidrena cijena', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Datum', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Grupa', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Jedinična cijena', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Količina', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Pakiranje', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Jedinica', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Iznos / jedinica', 'sidrena' ); ?></th><th scope="col"><?php esc_html_e( 'Javni cjenik', 'sidrena' ); ?></th></tr></thead>
				<tbody>
				<?php if ( empty( $items ) ) : ?><tr><td colspan="13"><?php esc_html_e( 'Na ovoj stranici nema WooCommerce proizvoda.', 'sidrena' ); ?></td></tr><?php endif; ?>
				<?php foreach ( $items as $product ) : $id = $product->get_id(); ?>
				<tr>
					<td><strong><?php echo esc_html( $product->get_name() ); ?></strong><span class="sid-bulk-meta">#<?php echo esc_html( $id ); ?> · <?php echo esc_html( $product->get_type() ); ?></span></td>
					<td><input type="text" name="items[<?php echo esc_attr( $id ); ?>][code]" value="<?php echo esc_attr( get_post_meta( $id, '_sidrena_code', true ) ); ?>" placeholder="<?php echo esc_attr( $product->get_sku() ); ?>"></td>
					<td><input type="text" name="items[<?php echo esc_attr( $id ); ?>][brand]" value="<?php echo esc_attr( get_post_meta( $id, '_sidrena_brand', true ) ); ?>"></td><td><input type="text" name="items[<?php echo esc_attr( $id ); ?>][barcode]" value="<?php echo esc_attr( get_post_meta( $id, '_sidrena_barcode', true ) ); ?>" placeholder="<?php echo esc_attr( Sidrena_Utils::get_barcode( $product ) ); ?>"></td>
					<td><input type="number" min="0" step="0.01" name="items[<?php echo esc_attr( $id ); ?>][anchor]" value="<?php echo esc_attr( get_post_meta( $id, '_sidrena_anchor_price', true ) ); ?>"></td>
					<td><input type="date" name="items[<?php echo esc_attr( $id ); ?>][date]" value="<?php echo esc_attr( get_post_meta( $id, '_sidrena_anchor_date', true ) ); ?>"></td>
					<td><select name="items[<?php echo esc_attr( $id ); ?>][group]"><option value="standard" <?php selected( get_post_meta( $id, '_sidrena_reference_group', true ), 'standard' ); ?>><?php esc_html_e( 'Standard', 'sidrena' ); ?></option><option value="fmcg" <?php selected( get_post_meta( $id, '_sidrena_reference_group', true ), 'fmcg' ); ?>>FMCG</option><option value="custom" <?php selected( get_post_meta( $id, '_sidrena_reference_group', true ), 'custom' ); ?>><?php esc_html_e( 'Prilagođeno', 'sidrena' ); ?></option></select></td><td><select name="items[<?php echo esc_attr( $id ); ?>][unit_status]"><?php $unit_status = get_post_meta( $id, '_sidrena_unit_price_status', true ) ?: 'review'; ?><option value="review" <?php selected( $unit_status, 'review' ); ?>><?php esc_html_e( 'Provjeriti', 'sidrena' ); ?></option><option value="required" <?php selected( $unit_status, 'required' ); ?>><?php esc_html_e( 'Obvezna', 'sidrena' ); ?></option><option value="not_required" <?php selected( $unit_status, 'not_required' ); ?>><?php esc_html_e( 'Nije primjenjiva', 'sidrena' ); ?></option><option value="exception" <?php selected( $unit_status, 'exception' ); ?>><?php esc_html_e( 'Iznimka', 'sidrena' ); ?></option></select></td><td><input type="number" min="0" step="0.0001" name="items[<?php echo esc_attr( $id ); ?>][quantity]" value="<?php echo esc_attr( get_post_meta( $id, '_sidrena_quantity', true ) ); ?>" placeholder="750"></td><td><input type="text" name="items[<?php echo esc_attr( $id ); ?>][quantity_unit]" value="<?php echo esc_attr( get_post_meta( $id, '_sidrena_quantity_unit', true ) ); ?>" placeholder="g / kg / ml / l"></td><td><input type="text" name="items[<?php echo esc_attr( $id ); ?>][unit]" value="<?php echo esc_attr( get_post_meta( $id, '_sidrena_unit', true ) ); ?>" placeholder="kg / l / m"></td><td><input type="number" min="0" step="0.0001" name="items[<?php echo esc_attr( $id ); ?>][unit_price]" value="<?php echo esc_attr( get_post_meta( $id, '_sidrena_unit_price', true ) ); ?>" placeholder="<?php esc_attr_e( 'auto', 'sidrena' ); ?>"></td>
					<td><?php $cjenik_visibility = get_post_meta( $id, '_sidrena_cjenik_visibility', true ) ?: 'auto'; ?><select name="items[<?php echo esc_attr( $id ); ?>][cjenik_visibility]"><option value="auto" <?php selected( $cjenik_visibility, 'auto' ); ?>><?php esc_html_e( 'Automatski', 'sidrena' ); ?></option><option value="include" <?php selected( $cjenik_visibility, 'include' ); ?>><?php esc_html_e( 'Uvijek uključi', 'sidrena' ); ?></option><option value="exclude" <?php selected( $cjenik_visibility, 'exclude' ); ?>><?php esc_html_e( 'Isključi', 'sidrena' ); ?></option></select></td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			<div class="sid-bulk-actions"><button class="button button-primary sid-primary" type="submit"><span class="dashicons dashicons-saved"></span><?php esc_html_e( 'Spremi ovu stranicu kataloga', 'sidrena' ); ?></button><span><?php echo esc_html( sprintf( __( 'Stranica %1$d od %2$d', 'sidrena' ), $page, $pages ) ); ?></span></div>
		</form>
		<?php if ( $pages > 1 ) : ?>
		<nav class="sid-pagination" aria-label="<?php esc_attr_e( 'Navigacija kataloga', 'sidrena' ); ?>">
			<?php if ( $page > 1 ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-catalog&catalog_page=' . ( $page - 1 ) ) ); ?>">← <?php esc_html_e( 'Prethodna', 'sidrena' ); ?></a><?php endif; ?>
			<?php if ( $page < $pages ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sidrena-catalog&catalog_page=' . ( $page + 1 ) ) ); ?>"><?php esc_html_e( 'Sljedeća', 'sidrena' ); ?> →</a><?php endif; ?>
		</nav>
		<?php endif; ?>
		<?php
	}

	public function save() {
		if ( ! Sidrena_Utils::current_user_can_manage() ) {
			wp_die( esc_html__( 'Nemate dopuštenje za ovu radnju.', 'sidrena' ) );
		}
		check_admin_referer( 'sidrena_bulk_save' );
		$items = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
		$updated = 0;

		foreach ( $items as $id => $row ) {
			$id = absint( $id );
			if ( ! $id || ! is_array( $row ) || 'product' !== get_post_type( $id ) ) {
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

			$date = Sidrena_Utils::sanitize_date( isset( $row['date'] ) ? $row['date'] : '' );
			if ( $date ) {
				update_post_meta( $id, '_sidrena_anchor_date', $date );
			} else {
				delete_post_meta( $id, '_sidrena_anchor_date' );
			}

			$group = sanitize_key( isset( $row['group'] ) ? $row['group'] : 'standard' );
			if ( ! in_array( $group, array( 'standard', 'fmcg', 'custom' ), true ) ) {
				$group = 'standard';
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
						$retail = function_exists( 'wc_get_price_including_tax' ) ? wc_get_price_including_tax( $product, array( 'price' => (float) $raw_price ) ) : (float) $raw_price;
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
		$page = max( 1, isset( $_POST['catalog_page'] ) ? absint( $_POST['catalog_page'] ) : 1 );
		wp_safe_redirect( admin_url( 'admin.php?page=sidrena-catalog&catalog_page=' . $page . '&sid_notice=bulk_saved' ) );
		exit;
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
