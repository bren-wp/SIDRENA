<?php
/**
 * Settings tab markup. Included from Brendigo_Sidrena_Cijena_Admin.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

		$sidrena_settings = Brendigo_Sidrena_Cijena_Settings::instance();
		$sidrena_export   = Brendigo_Sidrena_Cijena_Export::instance();
		$sidrena_rows     = Brendigo_Sidrena_Cijena_Poslovnice::instance()->all();
		$sidrena_json     = wp_json_encode( $sidrena_rows );
		if ( ! is_string( $sidrena_json ) ) {
			$sidrena_json = '[]';
		}

		$sidrena_adresa = (string) $sidrena_settings->get( 'file_adresa', '' );
		$sidrena_woo    = Brendigo_Sidrena_Cijena_Plugin::woocommerce_active();
		?>
		<div class="sidrena-cijena-callout">
			<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
			<p><?php echo esc_html( $sidrena_woo ? __( 'Webshop je zadana poslovnica: oblik, adresa, oznaka i broj pohrane ulaze u službeni naziv datoteke (NN 101/2026). Dodatne poslovnice dodajte ispod. Svaka dobiva vlastiti javni XML ili CSV i 30-dnevnu arhivu. Ručne stavke (usluge, dostava) dodijelite na prvom tabu odabirom poslovnice iznad tablice. Brisanje poslovnice briše i te stavke.', 'brendigo-sidrena-cijena' ) : __( 'Glavni cjenik sadrži proizvode i usluge koji nisu dodijeljeni zasebnoj poslovnici. Po potrebi dodajte poslovnice; svaka dobiva vlastiti XML/CSV i arhivu. Stavke poslovnice uređuju se na kartici Proizvodi i usluge. Brisanje poslovnice briše i njezine ručno unesene stavke.', 'brendigo-sidrena-cijena' ) ); ?></p>
		</div>

		<form method="post" action="<?php echo esc_url( $this->get_settings_tab_url( 'poslovnice' ) ); ?>" id="sidrena-poslovnice-form">
			<?php wp_nonce_field( 'sidrena_cijena_save_poslovnice', 'sidrena_cijena_poslovnice_nonce' ); ?>
			<input type="hidden" name="sidrena_cijena_action" value="save_poslovnice" />
			<input type="hidden" name="sidrena_cijena_poslovnice_json" id="sidrena-poslovnice-json" value="<?php echo esc_attr( $sidrena_json ); ?>" />

			<section class="sidrena-cijena-card">
				<div class="sidrena-cijena-card__head">
					<h2><?php echo esc_html( $sidrena_woo ? __( 'Webshop', 'brendigo-sidrena-cijena' ) : __( 'Glavni cjenik', 'brendigo-sidrena-cijena' ) ); ?></h2>
					<p><?php echo esc_html( $sidrena_woo ? __( 'Javni cjenik webshopa: /sidrena-cijena.xml (ili .csv). Svi objavljeni proizvodi i ručni retci bez oznake poslovnice.', 'brendigo-sidrena-cijena' ) : __( 'Glavni XML/CSV cjenik: /sidrena-cijena.xml (ili .csv). Uključuje ručno unesene proizvode i usluge bez dodijeljene poslovnice.', 'brendigo-sidrena-cijena' ) ); ?></p>
				</div>

				<article class="sidrena-poslovnica sidrena-poslovnica--webshop">
					<div class="sidrena-poslovnica__grid">
						<p class="sidrena-poslovnica__field">
							<label for="sidrena_cijena_file_oblik"><?php echo esc_html__( 'Oblik prodajnog objekta', 'brendigo-sidrena-cijena' ); ?></label>
							<input type="text" class="regular-text" id="sidrena_cijena_file_oblik" name="sidrena_cijena_file_oblik" value="<?php echo esc_attr( (string) $sidrena_settings->get( 'file_oblik', 'webshop' ) ); ?>" />
							<span class="description"><?php echo esc_html__( 'Kako je napisano, npr. webshop ili prodavaonica.', 'brendigo-sidrena-cijena' ); ?></span>
						</p>
						<p class="sidrena-poslovnica__field">
							<label for="sidrena_cijena_file_adresa"><?php echo esc_html__( 'Adresa prodajnog objekta', 'brendigo-sidrena-cijena' ); ?></label>
							<input type="text" class="regular-text" id="sidrena_cijena_file_adresa" name="sidrena_cijena_file_adresa" value="<?php echo esc_attr( $sidrena_adresa ); ?>" placeholder="<?php echo esc_attr( $sidrena_settings->default_file_address() ); ?>" />
							<span class="description"><?php echo esc_html__( 'Ulica i mjesto ostaju u nazivu, npr. Ilica 150 Zagreb.', 'brendigo-sidrena-cijena' ); ?></span>
						</p>
						<p class="sidrena-poslovnica__field">
							<label for="sidrena_cijena_file_oznaka"><?php echo esc_html__( 'Oznaka prodajnog objekta', 'brendigo-sidrena-cijena' ); ?></label>
							<input type="text" class="small-text" id="sidrena_cijena_file_oznaka" name="sidrena_cijena_file_oznaka" value="<?php echo esc_attr( (string) $sidrena_settings->get( 'file_oznaka', '001' ) ); ?>" />
							<span class="description"><?php echo esc_html__( 'Interna oznaka, npr. WEB ili 001. Crtica ostaje.', 'brendigo-sidrena-cijena' ); ?></span>
						</p>
						<p class="sidrena-poslovnica__field">
							<label for="sidrena_cijena_file_pohrana"><?php echo esc_html__( 'Sljedeći broj pohrane', 'brendigo-sidrena-cijena' ); ?></label>
							<input type="number" class="small-text" id="sidrena_cijena_file_pohrana" name="sidrena_cijena_file_pohrana" min="1" step="1" value="<?php echo esc_attr( (string) (int) $sidrena_settings->get( 'file_pohrana', 1 ) ); ?>" />
							<span class="description"><?php echo esc_html__( 'Raste jednom po danu. Ponovno generiranje istog dana ne povećava broj. U naziv ide kako je upisan, npr. 104.', 'brendigo-sidrena-cijena' ); ?></span>
						</p>
					</div>
					<p class="description">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: example filename */
								__( 'Sljedeći službeni naziv: %s (primjer Ministarstva: prodavaonica_Ilica 150 Zagreb_P-01_104_01.10.2026_07:45). Na Windows poslužitelju vrijeme ide s crtom, 07-45.', 'brendigo-sidrena-cijena' ),
								$sidrena_export->legal_filename()
							)
						);
						?>
					</p>

					<div class="sidrena-poslovnice">
						<div class="sidrena-poslovnice__head">
							<h3><?php echo esc_html__( 'Dodatne poslovnice', 'brendigo-sidrena-cijena' ); ?></h3>
							<button type="button" class="button button-secondary" id="sidrena-poslovnica-add">
								<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
								<?php echo esc_html__( 'Dodaj poslovnicu', 'brendigo-sidrena-cijena' ); ?>
							</button>
						</div>
						<p class="description"><?php echo esc_html__( 'Svaka poslovnica dobiva adresu /sidrena-cijena-OZNAKA.xml (ili .csv) i vlastitu arhivu od 30 datoteka.', 'brendigo-sidrena-cijena' ); ?></p>
						<div id="sidrena-poslovnice"></div>
					</div>
				</article>
			</section>

			<?php $this->render_save_button( __( 'Spremi poslovnice', 'brendigo-sidrena-cijena' ) ); ?>
		</form>
		<?php
