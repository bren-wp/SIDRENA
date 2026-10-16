<?php
/**
 * Settings tab markup. Included from Brendigo_Sidrena_Cijena_Admin.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

		$sidrena_rows = Brendigo_Sidrena_Cijena_Catalog::instance()->all();
		$sidrena_json = wp_json_encode( $sidrena_rows );
		if ( ! is_string( $sidrena_json ) ) {
			$sidrena_json = '[]';
		}

		$sidrena_upit_rows = Brendigo_Sidrena_Cijena_Upit::instance()->all();
		$sidrena_upit_json = wp_json_encode( $sidrena_upit_rows );
		if ( ! is_string( $sidrena_upit_json ) ) {
			$sidrena_upit_json = '[]';
		}

		$sidrena_woo        = Brendigo_Sidrena_Cijena_Plugin::woocommerce_active();
		$sidrena_cjenik_url = Brendigo_Sidrena_Cijena_Export::instance()->get_cjenik_url();
		$sidrena_woo_catalog = null;
		if ( $sidrena_woo && class_exists( 'Brendigo_Sidrena_Cijena_Woo_Bulk_Editor' ) ) {
			$sidrena_woo_editor  = Brendigo_Sidrena_Cijena_Woo_Bulk_Editor::instance();
			$sidrena_woo_catalog = $sidrena_woo_editor->page( $sidrena_woo_editor->requested_page() );
		}
		$sidrena_wc_saved = isset( $_GET['wc_saved'] ) ? absint( wp_unslash( $_GET['wc_saved'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice.
		?>
		<div class="sidrena-cijena-callout">
			<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
			<p><?php echo esc_html( $sidrena_woo ? __( 'Svi WooCommerce proizvodi povlače se automatski iz postojećeg kataloga i prikazuju po stranicama. Upišite samo sidrenu cijenu i, kada je potrebno, datum na koji je ta cijena vrijedila. Barkod i vidljivost možete doraditi na istom mjestu. Dodatne stavke koristite samo za ono što nije WooCommerce proizvod.', 'brendigo-sidrena-cijena' ) : __( 'Dodajte proizvode i usluge koji trebaju biti u javnom cjeniku. Za svaku stavku upišite važeću i sidrenu cijenu te datum na koji je sidrena cijena vrijedila.', 'brendigo-sidrena-cijena' ) ); ?></p>
		</div>

		<?php if ( $sidrena_woo && is_array( $sidrena_woo_catalog ) ) : ?>
		<section class="sidrena-cijena-card brendigo-woo-catalog">
			<div class="sidrena-cijena-card__head">
				<div>
					<h2><?php echo esc_html__( 'WooCommerce proizvodi', 'brendigo-sidrena-cijena' ); ?></h2>
					<p><?php echo esc_html__( 'Katalog je stalno povezan s WooCommerceom, bez zasebnog kopiranja proizvoda. Naziv, SKU i važeća cijena ostaju na izvornom proizvodu; ovdje unosite podatke potrebne za sidrenu cijenu.', 'brendigo-sidrena-cijena' ); ?></p>
				</div>
				<span class="brendigo-admin-chip"><?php echo esc_html( number_format_i18n( (int) $sidrena_woo_catalog['total'] ) ); ?> <?php echo esc_html__( 'proizvoda', 'brendigo-sidrena-cijena' ); ?></span>
			</div>

			<?php if ( isset( $_GET['wc_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<?php
				if ( $sidrena_wc_saved > 0 ) {
					/* translators: %d: number of updated products. */
					$sidrena_wc_saved_message = sprintf( __( 'Spremljeno je %d izmijenjenih proizvoda.', 'brendigo-sidrena-cijena' ), $sidrena_wc_saved );
				} else {
					$sidrena_wc_saved_message = __( 'Nema promjena za spremanje.', 'brendigo-sidrena-cijena' );
				}
				?>
				<div class="notice notice-success inline"><p><?php echo esc_html( $sidrena_wc_saved_message ); ?></p></div>
			<?php endif; ?>

			<div class="brendigo-woo-catalog-tools">
				<label>
					<span><?php echo esc_html__( 'Pretraži na ovoj stranici', 'brendigo-sidrena-cijena' ); ?></span>
					<input type="search" id="sidrena-wc-filter" placeholder="<?php echo esc_attr__( 'Naziv ili SKU…', 'brendigo-sidrena-cijena' ); ?>" />
				</label>
				<label>
					<span><?php echo esc_html__( 'Prikaz', 'brendigo-sidrena-cijena' ); ?></span>
					<select id="sidrena-wc-price-filter">
						<option value="all"><?php echo esc_html__( 'Svi proizvodi', 'brendigo-sidrena-cijena' ); ?></option>
						<option value="missing"><?php echo esc_html__( 'Bez sidrene cijene', 'brendigo-sidrena-cijena' ); ?></option>
						<option value="complete"><?php echo esc_html__( 'Sa sidrenom cijenom', 'brendigo-sidrena-cijena' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php echo esc_html__( 'Datum za prazna polja', 'brendigo-sidrena-cijena' ); ?></span>
					<input type="text" id="sidrena-wc-bulk-date" value="<?php echo esc_attr( Brendigo_Sidrena_Cijena_Settings::instance()->get( 'default_date' ) ); ?>" inputmode="numeric" />
				</label>
				<button type="button" class="button button-secondary js-sidrena-wc-fill-date"><?php echo esc_html__( 'Popuni prazne datume', 'brendigo-sidrena-cijena' ); ?></button>
				<button type="button" class="button button-secondary js-sidrena-wc-fill-price"><?php echo esc_html__( 'Preuzmi važeću cijenu u prazna polja', 'brendigo-sidrena-cijena' ); ?></button>
			</div>
			<div class="brendigo-woo-catalog-summary">
				<p class="description brendigo-woo-change-status" id="sidrena-wc-change-status" aria-live="polite"><?php echo esc_html__( 'Nema nespremljenih promjena.', 'brendigo-sidrena-cijena' ); ?></p>
				<p class="description" id="sidrena-wc-visible-status" aria-live="polite"></p>
			</div>

			<form method="post" action="<?php echo esc_url( $this->get_settings_tab_url( 'proizvodi' ) ); ?>" id="sidrena-wc-catalog-form">
				<?php wp_nonce_field( 'sidrena_cijena_save_woo_catalog', 'sidrena_cijena_woo_catalog_nonce' ); ?>
				<input type="hidden" name="sidrena_cijena_action" value="save_woo_catalog" />
				<input type="hidden" name="sidrena_wc_page" value="<?php echo esc_attr( (string) $sidrena_woo_catalog['page'] ); ?>" />
				<input type="hidden" name="sidrena_wc_json" id="sidrena-wc-json" value="" />

				<div class="sidrena-cijena-catalog-wrap brendigo-woo-catalog-wrap">
					<table class="sidrena-cijena-catalog brendigo-woo-catalog-table">
						<thead><tr>
							<th><?php echo esc_html__( 'Proizvod', 'brendigo-sidrena-cijena' ); ?></th>
							<th><?php echo esc_html__( 'SKU', 'brendigo-sidrena-cijena' ); ?></th>
							<th><?php echo esc_html__( 'Važeća cijena', 'brendigo-sidrena-cijena' ); ?></th>
							<th><?php echo esc_html__( 'Sidrena cijena', 'brendigo-sidrena-cijena' ); ?></th>
							<th><?php echo esc_html__( 'Cijena je vrijedila na dan', 'brendigo-sidrena-cijena' ); ?></th>
							<th><?php echo esc_html__( 'Barkod', 'brendigo-sidrena-cijena' ); ?></th>
							<th><?php echo esc_html__( 'Javni cjenik', 'brendigo-sidrena-cijena' ); ?></th>
						</tr></thead>
						<tbody>
						<?php if ( empty( $sidrena_woo_catalog['rows'] ) ) : ?>
							<tr><td colspan="7" class="brendigo-woo-empty"><?php echo esc_html__( 'WooCommerce katalog trenutno nema proizvoda za prikaz.', 'brendigo-sidrena-cijena' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $sidrena_woo_catalog['rows'] as $sidrena_wc_row ) : ?>
							<?php if ( ! empty( $sidrena_wc_row['group'] ) ) : ?>
							<tr class="brendigo-woo-group" data-search="<?php echo esc_attr( strtolower( $sidrena_wc_row['name'] . ' ' . $sidrena_wc_row['sku'] ) ); ?>"><td colspan="7"><strong><?php echo esc_html( $sidrena_wc_row['name'] ); ?></strong><?php if ( '' !== $sidrena_wc_row['sku'] ) : ?> <code><?php echo esc_html( $sidrena_wc_row['sku'] ); ?></code><?php endif; ?></td></tr>
							<?php else : ?>
							<tr class="brendigo-woo-row<?php echo ! empty( $sidrena_wc_row['variation'] ) ? ' is-variation' : ''; ?><?php echo '' === (string) $sidrena_wc_row['sidrena'] ? ' is-missing-anchor' : ''; ?>" data-product-id="<?php echo esc_attr( (string) $sidrena_wc_row['id'] ); ?>" data-search="<?php echo esc_attr( strtolower( $sidrena_wc_row['name'] . ' ' . $sidrena_wc_row['parent'] . ' ' . $sidrena_wc_row['sku'] ) ); ?>">
								<td><a href="<?php echo esc_url( $sidrena_wc_row['edit_url'] ); ?>"><strong><?php echo esc_html( $sidrena_wc_row['name'] ); ?></strong></a><?php if ( ! empty( $sidrena_wc_row['variation'] ) && '' !== $sidrena_wc_row['parent'] ) : ?><small><?php echo esc_html( $sidrena_wc_row['parent'] ); ?></small><?php endif; ?></td>
								<td><code><?php echo esc_html( '' !== $sidrena_wc_row['sku'] ? $sidrena_wc_row['sku'] : '—' ); ?></code></td>
								<td class="brendigo-woo-current" data-current="<?php echo esc_attr( '' !== $sidrena_wc_row['current'] ? $sidrena_wc_row['current'] : $sidrena_wc_row['regular'] ); ?>"><?php echo '' !== $sidrena_wc_row['display'] ? wp_kses_post( $sidrena_wc_row['display'] ) : '—'; ?></td>
								<td><input type="text" class="wc_input_price js-sidrena-wc-price" inputmode="decimal" name="sidrena_wc[<?php echo esc_attr( (string) $sidrena_wc_row['id'] ); ?>][price]" value="<?php echo esc_attr( $sidrena_wc_row['sidrena'] ); ?>" /></td>
								<td><input type="text" class="js-sidrena-wc-date" inputmode="numeric" name="sidrena_wc[<?php echo esc_attr( (string) $sidrena_wc_row['id'] ); ?>][date]" value="<?php echo esc_attr( $sidrena_wc_row['date'] ); ?>" placeholder="<?php echo esc_attr( Brendigo_Sidrena_Cijena_Settings::instance()->get( 'default_date' ) ); ?>" /></td>
								<td><input type="text" name="sidrena_wc[<?php echo esc_attr( (string) $sidrena_wc_row['id'] ); ?>][barcode]" value="<?php echo esc_attr( $sidrena_wc_row['barcode'] ); ?>" /></td>
								<td><select name="sidrena_wc[<?php echo esc_attr( (string) $sidrena_wc_row['id'] ); ?>][visibility]"><option value="auto"<?php selected( $sidrena_wc_row['visibility'], 'auto' ); ?>><?php echo esc_html__( 'Automatski', 'brendigo-sidrena-cijena' ); ?></option><option value="include"<?php selected( $sidrena_wc_row['visibility'], 'include' ); ?>><?php echo esc_html__( 'Uključi', 'brendigo-sidrena-cijena' ); ?></option><option value="exclude"<?php selected( $sidrena_wc_row['visibility'], 'exclude' ); ?>><?php echo esc_html__( 'Isključi', 'brendigo-sidrena-cijena' ); ?></option></select></td>
							</tr>
							<?php endif; ?>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="brendigo-woo-catalog-footer">
					<div class="brendigo-woo-catalog-footer__actions">
						<button type="submit" class="button button-primary button-hero js-sidrena-wc-save"><?php echo esc_html__( 'Spremi sidrene cijene', 'brendigo-sidrena-cijena' ); ?></button>
						<button type="button" class="button button-secondary js-sidrena-wc-reset"><?php echo esc_html__( 'Poništi nespremljene promjene', 'brendigo-sidrena-cijena' ); ?></button>
					</div>
					<?php if ( (int) $sidrena_woo_catalog['pages'] > 1 ) : ?>
					<nav class="sidrena-cijena-catalog-pager" aria-label="<?php echo esc_attr__( 'Stranice WooCommerce proizvoda', 'brendigo-sidrena-cijena' ); ?>">
						<?php if ( (int) $sidrena_woo_catalog['page'] > 1 ) : ?><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'brendigo-sidrena-cijena', 'tab' => 'proizvodi', 'sidrena_wc_page' => (int) $sidrena_woo_catalog['page'] - 1 ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'Prethodna', 'brendigo-sidrena-cijena' ); ?></a><?php endif; ?>
						<?php
						/* translators: 1: current page, 2: total pages. */
						$sidrena_wc_page_label = sprintf( __( 'Stranica %1$d od %2$d', 'brendigo-sidrena-cijena' ), (int) $sidrena_woo_catalog['page'], (int) $sidrena_woo_catalog['pages'] );
						?>
						<span><?php echo esc_html( $sidrena_wc_page_label ); ?></span>
						<?php if ( (int) $sidrena_woo_catalog['page'] < (int) $sidrena_woo_catalog['pages'] ) : ?><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'brendigo-sidrena-cijena', 'tab' => 'proizvodi', 'sidrena_wc_page' => (int) $sidrena_woo_catalog['page'] + 1 ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'Sljedeća', 'brendigo-sidrena-cijena' ); ?></a><?php endif; ?>
					</nav>
					<?php endif; ?>
				</div>
			</form>
		</section>
		<?php endif; ?>

		<section class="sidrena-cijena-card">
			<?php if ( ! $sidrena_woo ) : ?>
			<p class="description"><?php echo esc_html__( 'Samostalni WordPress način rada: ovdje se uređuju proizvodi i usluge, bez obaveznog WooCommercea. Stavku možete prikazati pomoću [sidrena_stavka id="123"], a cijeli cjenik pomoću [sidrena-cjenik]. Za povijest cijena koristite [sidrena_povijest_cijena id="123"].', 'brendigo-sidrena-cijena' ); ?></p>
			<?php endif; ?>
			<div class="sidrena-cijena-card__head">
				<h2><?php echo esc_html( $sidrena_woo ? __( 'Dodatne stavke', 'brendigo-sidrena-cijena' ) : __( 'Proizvodi i usluge', 'brendigo-sidrena-cijena' ) ); ?></h2>
				<p>
					<?php
					if ( $sidrena_woo ) {
						echo esc_html(
							sprintf(
								/* translators: %d row count */
								__( 'Trenutno %d stavki. Dodaju se na kraj javnog cjenika.', 'brendigo-sidrena-cijena' ),
								count( $sidrena_rows )
							)
						);
					} else {
						echo esc_html(
							sprintf(
								/* translators: %d product count */
								__( 'Trenutno %d proizvoda i usluga. Javna stranica:', 'brendigo-sidrena-cijena' ),
								count( $sidrena_rows )
							)
						);
					}
					echo ' ';
					if ( $sidrena_woo ) {
						echo esc_html__( 'HTML:', 'brendigo-sidrena-cijena' );
						echo ' ';
					}
					?>
					<a href="<?php echo esc_url( $sidrena_cjenik_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $sidrena_cjenik_url ); ?></a>
				</p>
			</div>

			<form method="post" action="<?php echo esc_url( $this->get_settings_tab_url( 'proizvodi' ) ); ?>" id="sidrena-cijena-catalog-form">
				<?php wp_nonce_field( 'sidrena_cijena_save_catalog', 'sidrena_cijena_catalog_nonce' ); ?>
				<input type="hidden" name="sidrena_cijena_action" value="save_catalog" />
				<input type="hidden" name="sidrena_cijena_catalog_json" id="sidrena-cijena-catalog-json" value="<?php echo esc_attr( $sidrena_json ); ?>" />
				<input type="hidden" name="sidrena_cijena_upit_json" id="sidrena-cijena-upit-json" value="<?php echo esc_attr( $sidrena_upit_json ); ?>" />

				<div class="sidrena-cijena-catalog-toolbar">
					<div class="sidrena-cijena-catalog-toolbar__start">
						<?php if ( Brendigo_Sidrena_Cijena_Settings::instance()->is_multi_poslovnica() ) : ?>
						<label class="sidrena-cijena-catalog-scope" for="sidrena-cijena-catalog-scope">
							<span><?php echo esc_html__( 'Poslovnica', 'brendigo-sidrena-cijena' ); ?></span>
							<select id="sidrena-cijena-catalog-scope">
								<option value=""><?php echo esc_html( $sidrena_woo ? __( 'Webshop', 'brendigo-sidrena-cijena' ) : __( 'Glavni cjenik', 'brendigo-sidrena-cijena' ) ); ?></option>
								<?php foreach ( Brendigo_Sidrena_Cijena_Poslovnice::instance()->all() as $sidrena_outlet ) : ?>
									<?php $sidrena_outlet_name = '' !== $sidrena_outlet['naziv'] ? $sidrena_outlet['naziv'] : $sidrena_outlet['oznaka']; ?>
									<option value="<?php echo esc_attr( $sidrena_outlet['oznaka'] ); ?>"><?php echo esc_html( $sidrena_outlet_name ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<?php endif; ?>
						<button type="button" class="button button-secondary" id="sidrena-cijena-catalog-add">
							<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
							<?php echo esc_html( $sidrena_woo ? __( 'Dodaj stavku', 'brendigo-sidrena-cijena' ) : __( 'Dodaj proizvod ili uslugu', 'brendigo-sidrena-cijena' ) ); ?>
						</button>
					</div>
					<p class="description sidrena-cijena-catalog-count" id="sidrena-cijena-catalog-count"></p>
				</div>

				<div class="sidrena-cijena-catalog-wrap">
					<table class="sidrena-cijena-catalog" id="sidrena-cijena-catalog">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Naziv', 'brendigo-sidrena-cijena' ); ?></th>
								<th><?php echo esc_html__( 'Vrsta', 'brendigo-sidrena-cijena' ); ?></th>
								<th><?php echo esc_html__( 'SKU', 'brendigo-sidrena-cijena' ); ?></th>
								<th><?php echo esc_html__( 'Barkod', 'brendigo-sidrena-cijena' ); ?></th>
								<th><?php echo esc_html__( 'Marka', 'brendigo-sidrena-cijena' ); ?></th>
								<th><?php echo esc_html__( 'URL', 'brendigo-sidrena-cijena' ); ?></th>
								<th><?php echo esc_html__( 'Redovna cijena', 'brendigo-sidrena-cijena' ); ?></th>
								<th><?php echo esc_html__( 'Akcijska cijena', 'brendigo-sidrena-cijena' ); ?></th>
								<th><?php echo esc_html__( 'Poseban oblik', 'brendigo-sidrena-cijena' ); ?></th>
								<th><?php echo esc_html__( 'Jedinica mjere', 'brendigo-sidrena-cijena' ); ?></th>
								<th><?php echo esc_html__( 'Cijena za jedinicu', 'brendigo-sidrena-cijena' ); ?></th>
								<th><?php echo esc_html__( 'Sidrena cijena', 'brendigo-sidrena-cijena' ); ?></th>
								<th><?php echo esc_html__( 'Datum sidrene', 'brendigo-sidrena-cijena' ); ?></th>
								<?php if ( Brendigo_Sidrena_Cijena_Settings::instance()->is_cjm_enabled() ) : ?>
								<th><?php echo esc_html__( 'Mjerna jedinica', 'brendigo-sidrena-cijena' ); ?></th>
								<?php endif; ?>
								<th><?php echo esc_html__( 'Dostupnost', 'brendigo-sidrena-cijena' ); ?></th>
								<th><?php echo esc_html__( 'Shortcode', 'brendigo-sidrena-cijena' ); ?></th>
								<th><span class="screen-reader-text"><?php echo esc_html__( 'Radnje', 'brendigo-sidrena-cijena' ); ?></span></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>

				<nav class="sidrena-cijena-catalog-pager" id="sidrena-cijena-catalog-pager" hidden>
					<button type="button" class="button" id="sidrena-cijena-catalog-prev"><?php echo esc_html__( 'Prethodna', 'brendigo-sidrena-cijena' ); ?></button>
					<span id="sidrena-cijena-catalog-page"></span>
					<button type="button" class="button" id="sidrena-cijena-catalog-next"><?php echo esc_html__( 'Sljedeća', 'brendigo-sidrena-cijena' ); ?></button>
				</nav>

				<div class="sidrena-cijena-upit-block">
					<h3 class="sidrena-cijena-upit-head"><?php echo esc_html__( 'Cijena na upit', 'brendigo-sidrena-cijena' ); ?></h3>
					<p class="description"><?php echo esc_html__( 'Ovi retci ispisuju se na dnu HTML cjenika.', 'brendigo-sidrena-cijena' ); ?></p>
					<p class="sidrena-cijena-actions">
						<button type="button" class="button button-secondary" id="sidrena-cijena-upit-add">
							<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
							<?php echo esc_html__( 'Dodaj stavku', 'brendigo-sidrena-cijena' ); ?>
						</button>
					</p>
					<div class="sidrena-cijena-catalog-wrap">
						<table class="sidrena-cijena-catalog sidrena-cijena-upit" id="sidrena-cijena-upit">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Naziv', 'brendigo-sidrena-cijena' ); ?></th>
									<th><?php echo esc_html__( 'Kriteriji', 'brendigo-sidrena-cijena' ); ?></th>
									<th><span class="screen-reader-text"><?php echo esc_html__( 'Radnje', 'brendigo-sidrena-cijena' ); ?></span></th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>

				<?php $this->render_save_button( $sidrena_woo ? __( 'Spremi stavke', 'brendigo-sidrena-cijena' ) : __( 'Spremi cjenik', 'brendigo-sidrena-cijena' ) ); ?>
			</form>
		</section>
		<?php
