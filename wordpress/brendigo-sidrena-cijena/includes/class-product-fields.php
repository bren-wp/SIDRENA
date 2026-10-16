<?php
/**
 * WooCommerce product and variation editor fields.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product editor UI for sidrena fields.
 */
class Brendigo_Sidrena_Cijena_Product_Fields {

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

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
	 * Simple / external product fields under sale price.
	 */
	public function render_simple_fields() {
		$product_id = get_the_ID();
		$meta       = Brendigo_Sidrena_Cijena_Product_Meta::instance();
		$cjm_on     = ( 'yes' === Brendigo_Sidrena_Cijena_Settings::instance()->get( 'enable_cjm' ) );

		echo '<div class="options_group sidrena-cijena-admin-fields">';
		?>
		<div class="sidrena-cijena-admin-fields__head">
			<span class="sidrena-cijena-admin-fields__mark" aria-hidden="true">B</span>
			<div>
				<strong><?php echo esc_html__( 'Brendigo Sidrena Cijena', 'brendigo-sidrena-cijena' ); ?></strong>
				<small><?php echo esc_html__( 'Referentna cijena i podaci javnog cjenika', 'brendigo-sidrena-cijena' ); ?></small>
			</div>
		</div>
		<div class="sidrena-cijena-admin-fields__note">
			<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
			<span><?php echo esc_html__( 'Upišite referentnu cijenu i datum na koji je ta cijena vrijedila.', 'brendigo-sidrena-cijena' ); ?></span>
		</div>
		<?php

		echo '<div class="sidrena-cijena-price-row">';

		woocommerce_wp_text_input(
			array(
				'id'          => 'sidrena_cijena',
				'name'        => 'sidrena_cijena',
				'label'       => __( 'Sidrena cijena', 'brendigo-sidrena-cijena' ) . ' (' . get_woocommerce_currency_symbol() . ')',
				'desc_tip'    => true,
				'description' => __( 'Referentna maloprodajna cijena koja je vrijedila na datumu upisanom uz proizvod.', 'brendigo-sidrena-cijena' ),
				'value'       => $meta->get_price( $product_id ),
				'data_type'   => 'price',
				'wrapper_class' => 'sidrena-cijena-price-field',
			)
		);

		if ( $cjm_on ) {
			$this->render_cjm_controls(
				'sidrena_cijena_cjm',
				'sidrena_cijena_cjm_suffix',
				$meta->is_cjm_enabled( $product_id ),
				$meta->get_cjm_suffix( $product_id )
			);
		}

		echo '</div>';

		$sidrena_date_help = sprintf(
			/* translators: 1: general reference date, 2: FMCG reference date. */
			__( 'Ostavite prazno za zaključani opći referentni datum %1$s. Za obuhvaćene kategorije hrane, pića, kozmetike i kućanskih potrepština koristite %2$s. Vlastiti datum koristite samo kada je dopušten za novu stavku nakon referentnog datuma.', 'brendigo-sidrena-cijena' ),
			Brendigo_Sidrena_Cijena_Settings::REFERENCE_DATE_GENERAL,
			Brendigo_Sidrena_Cijena_Settings::REFERENCE_DATE_FMCG
		);

		woocommerce_wp_text_input(
			array(
				'id'          => 'sidrena_cijena_datum',
				'name'        => 'sidrena_cijena_datum',
				'label'       => __( 'Referentni datum', 'brendigo-sidrena-cijena' ),
				'placeholder' => Brendigo_Sidrena_Cijena_Settings::instance()->get( 'default_date' ),
				'desc_tip'    => true,
				'description' => $sidrena_date_help,
				'value'       => $meta->get_date( $product_id ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => 'sidrena_naziv_posebnog_oblika',
				'name'        => 'sidrena_naziv_posebnog_oblika',
				'label'       => __( 'Naziv posebnog oblika prodaje', 'brendigo-sidrena-cijena' ),
				'placeholder' => 'akcija',
				'desc_tip'    => true,
				'description' => __( 'Dok je akcijska cijena aktivna, javni cjenik upisuje akcija. Ovdje upišite drugi naziv (rasprodaja, sezonsko sniženje) ako treba. Prazno polje znači akcija. Bez aktivne akcijske cijene se ne objavljuje.', 'brendigo-sidrena-cijena' ),
				'value'       => $meta->get_oblik( $product_id ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => 'sidrena_jedinica_mjere',
				'name'        => 'sidrena_jedinica_mjere',
				'label'       => __( 'Jedinica mjere', 'brendigo-sidrena-cijena' ),
				'placeholder' => 'kg',
				'desc_tip'    => true,
				'description' => __( 'Jedinica u javnom cjeniku, npr. kg, l ili kom. Prazno ako proizvod nema cijenu za jedinicu mjere.', 'brendigo-sidrena-cijena' ),
				'value'       => $meta->get_jedinica( $product_id ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'            => 'sidrena_cijena_jedinice_mjere',
				'name'          => 'sidrena_cijena_jedinice_mjere',
				'label'         => __( 'Cijena za jedinicu mjere', 'brendigo-sidrena-cijena' ) . ' (' . get_woocommerce_currency_symbol() . ')',
				'desc_tip'      => true,
				'description'   => __( 'Isti oblik kao redovna cijena. Objavljuje se uz maloprodajnu cijenu. Prazno ako nije primjenjivo.', 'brendigo-sidrena-cijena' ),
				'value'         => $meta->get_jedinica_cijena( $product_id ),
				'data_type'     => 'price',
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => 'brendigo_sidrena_barcode',
				'name'        => 'brendigo_sidrena_barcode',
				'label'       => __( 'Barkod (EAN / GTIN)', 'brendigo-sidrena-cijena' ),
				'desc_tip'    => true,
				'description' => __( 'Opcionalni barkod za javni cjenik. Ako je prazan, plugin pokušava koristiti WooCommerce Global Unique ID i uobičajene GTIN/EAN meta ključeve.', 'brendigo-sidrena-cijena' ),
				'value'       => $meta->get_barcode( $product_id ),
			)
		);

		woocommerce_wp_select(
			array(
				'id'          => 'brendigo_sidrena_cjenik_visibility',
				'name'        => 'brendigo_sidrena_cjenik_visibility',
				'label'       => __( 'Prikaz u javnom cjeniku', 'brendigo-sidrena-cijena' ),
				'desc_tip'    => true,
				'description' => __( 'Automatski prati vidljivost proizvoda u katalogu. Po potrebi proizvod možete uvijek uključiti ili uvijek izostaviti iz javnog cjenika.', 'brendigo-sidrena-cijena' ),
				'value'       => $meta->get_cjenik_visibility( $product_id ),
				'options'     => array(
					'auto'    => __( 'Automatski', 'brendigo-sidrena-cijena' ),
					'include' => __( 'Uvijek uključi', 'brendigo-sidrena-cijena' ),
					// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Select option value; this array is not passed to WP_Query/get_posts.
					'exclude' => __( 'Uvijek izostavi', 'brendigo-sidrena-cijena' ),
				),
			)
		);

		echo '</div>';
	}

	/**
	 * CJM toggle + suffix field next to a sidrena price input.
	 *
	 * @param string $toggle_name Checkbox name.
	 * @param string $suffix_name Suffix input name.
	 * @param bool   $enabled     Current toggle.
	 * @param string $suffix      Current suffix.
	 */
	public function render_cjm_controls( $toggle_name, $suffix_name, $enabled, $suffix ) {
		?>
		<p class="form-field sidrena-cijena-cjm">
			<label for="<?php echo esc_attr( $toggle_name ); ?>"><?php echo esc_html__( 'CJM', 'brendigo-sidrena-cijena' ); ?></label>
			<?php
			Brendigo_Sidrena_Cijena_Admin::instance()->render_toggle(
				$toggle_name,
				$toggle_name,
				$enabled,
				__( 'Cijena za jedinicu mjere', 'brendigo-sidrena-cijena' ),
				'sidrena-cijena-cjm-toggle'
			);
			?>
			<span class="sidrena-cijena-cjm-suffix"<?php if ( ! $enabled ) : ?> hidden<?php endif; ?>>
				<input type="text" name="<?php echo esc_attr( $suffix_name ); ?>" value="<?php echo esc_attr( $suffix ); ?>" placeholder="<?php echo esc_attr__( 'za kg/l', 'brendigo-sidrena-cijena' ); ?>" />
				<span class="description"><?php echo esc_html__( 'Dodaje se u prefix: Cijena za kg/l', 'brendigo-sidrena-cijena' ); ?></span>
			</span>
		</p>
		<?php
	}

	/**
	 * Variation fields under variation sale price.
	 *
	 * @param int     $loop           Loop index.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation      Variation post.
	 */
	public function render_variation_fields( $loop, $variation_data, $variation ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$meta   = Brendigo_Sidrena_Cijena_Product_Meta::instance();
		$id     = $variation->ID;
		$cjm_on = ( 'yes' === Brendigo_Sidrena_Cijena_Settings::instance()->get( 'enable_cjm' ) );
		$cjm    = $meta->is_cjm_enabled( $id );
		?>
		<div class="sidrena-cijena-variation-fields">
			<div class="sidrena-cijena-variation-fields__head">
				<strong><?php echo esc_html__( 'Brendigo Sidrena Cijena', 'brendigo-sidrena-cijena' ); ?></strong>
				<small><?php echo esc_html__( 'Referentna cijena i datum', 'brendigo-sidrena-cijena' ); ?></small>
			</div>
			<p class="form-row form-row-first">
				<label><?php echo esc_html__( 'Sidrena cijena', 'brendigo-sidrena-cijena' ) . ' (' . esc_html( get_woocommerce_currency_symbol() ) . ')'; ?></label>
				<input type="text" class="short wc_input_price" name="sidrena_cijena[<?php echo esc_attr( (string) $loop ); ?>]" value="<?php echo esc_attr( wc_format_localized_price( $meta->get_price( $id ) ) ); ?>" />
			</p>
			<?php if ( $cjm_on ) : ?>
				<p class="form-row form-row-last sidrena-cijena-cjm">
					<label><?php echo esc_html__( 'CJM', 'brendigo-sidrena-cijena' ); ?></label>
					<?php
					Brendigo_Sidrena_Cijena_Admin::instance()->render_toggle(
						'sidrena_cijena_cjm[' . (string) $loop . ']',
						'sidrena_cijena_cjm_' . (string) $loop,
						$cjm,
						__( 'Jedinica mjere', 'brendigo-sidrena-cijena' ),
						'sidrena-cijena-cjm-toggle'
					);
					?>
				</p>
				<p class="form-row form-row-full sidrena-cijena-cjm-suffix"<?php if ( ! $cjm ) : ?> hidden<?php endif; ?>>
					<label><?php echo esc_html__( 'CJM dodatak prefixu', 'brendigo-sidrena-cijena' ); ?></label>
					<input type="text" class="short" name="sidrena_cijena_cjm_suffix[<?php echo esc_attr( (string) $loop ); ?>]" value="<?php echo esc_attr( $meta->get_cjm_suffix( $id ) ); ?>" placeholder="<?php echo esc_attr__( 'za kg/l', 'brendigo-sidrena-cijena' ); ?>" />
				</p>
			<?php endif; ?>
			<p class="form-row form-row-first">
				<label><?php echo esc_html__( 'Referentni datum', 'brendigo-sidrena-cijena' ); ?></label>
				<input type="text" class="short" name="sidrena_cijena_datum[<?php echo esc_attr( (string) $loop ); ?>]" value="<?php echo esc_attr( $meta->get_date( $id ) ); ?>" placeholder="<?php echo esc_attr( Brendigo_Sidrena_Cijena_Settings::instance()->get( 'default_date' ) ); ?>" />
			</p>
			<p class="form-row form-row-last">
				<label><?php echo esc_html__( 'Naziv posebnog oblika prodaje', 'brendigo-sidrena-cijena' ); ?></label>
				<input type="text" class="short" name="sidrena_naziv_posebnog_oblika[<?php echo esc_attr( (string) $loop ); ?>]" value="<?php echo esc_attr( $meta->get_oblik( $id ) ); ?>" placeholder="akcija" />
			</p>
			<p class="form-row form-row-first">
				<label><?php echo esc_html__( 'Jedinica mjere', 'brendigo-sidrena-cijena' ); ?></label>
				<input type="text" class="short" name="sidrena_jedinica_mjere[<?php echo esc_attr( (string) $loop ); ?>]" value="<?php echo esc_attr( $meta->get_jedinica( $id ) ); ?>" placeholder="kg" />
			</p>
			<p class="form-row form-row-last">
				<label><?php echo esc_html__( 'Cijena za jedinicu mjere', 'brendigo-sidrena-cijena' ) . ' (' . esc_html( get_woocommerce_currency_symbol() ) . ')'; ?></label>
				<input type="text" class="short wc_input_price" name="sidrena_cijena_jedinice_mjere[<?php echo esc_attr( (string) $loop ); ?>]" value="<?php echo esc_attr( wc_format_localized_price( $meta->get_jedinica_cijena( $id ) ) ); ?>" />
			</p>
			<p class="form-row form-row-first">
				<label><?php echo esc_html__( 'Barkod (EAN / GTIN)', 'brendigo-sidrena-cijena' ); ?></label>
				<input type="text" class="short" name="brendigo_sidrena_barcode[<?php echo esc_attr( (string) $loop ); ?>]" value="<?php echo esc_attr( $meta->get_barcode( $id ) ); ?>" />
			</p>
			<p class="form-row form-row-last">
				<label><?php echo esc_html__( 'Prikaz u javnom cjeniku', 'brendigo-sidrena-cijena' ); ?></label>
				<select name="brendigo_sidrena_cjenik_visibility[<?php echo esc_attr( (string) $loop ); ?>]">
					<option value="auto" <?php selected( $meta->get_cjenik_visibility( $id ), 'auto' ); ?>><?php echo esc_html__( 'Automatski', 'brendigo-sidrena-cijena' ); ?></option>
					<option value="include" <?php selected( $meta->get_cjenik_visibility( $id ), 'include' ); ?>><?php echo esc_html__( 'Uvijek uključi', 'brendigo-sidrena-cijena' ); ?></option>
					<option value="exclude" <?php selected( $meta->get_cjenik_visibility( $id ), 'exclude' ); ?>><?php echo esc_html__( 'Uvijek izostavi', 'brendigo-sidrena-cijena' ); ?></option>
				</select>
			</p>
		</div>
		<?php
	}
}
