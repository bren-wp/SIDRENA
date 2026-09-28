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

final class Sidrena_Services {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );
		add_action( 'save_post_sidrena_service', array( $this, 'save' ), 10, 2 );
		add_action( 'wp_after_insert_post', array( $this, 'after_insert_post' ), 10, 4 );
		add_filter( 'manage_sidrena_service_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_sidrena_service_posts_custom_column', array( $this, 'column_values' ), 10, 2 );
		add_shortcode( 'sidrena_usluge', array( $this, 'shortcode' ) );
	}

	public function register_post_type() {
		$capability = Sidrena_Utils::admin_capability();

		register_post_type(
			'sidrena_service',
			array(
				'labels'              => array(
					'name'          => __( 'Usluge', 'sidrena' ),
					'singular_name' => __( 'Usluga', 'sidrena' ),
					'add_new_item'  => __( 'Dodaj uslugu', 'sidrena' ),
					'edit_item'     => __( 'Uredi uslugu', 'sidrena' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'sidrena',
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array(
					'edit_post'              => $capability,
					'read_post'              => $capability,
					'delete_post'            => $capability,
					'edit_posts'             => $capability,
					'edit_others_posts'      => $capability,
					'publish_posts'          => $capability,
					'read_private_posts'     => $capability,
					'delete_posts'           => $capability,
					'delete_private_posts'   => $capability,
					'delete_published_posts' => $capability,
					'delete_others_posts'    => $capability,
					'edit_private_posts'     => $capability,
					'edit_published_posts'   => $capability,
					'create_posts'           => $capability,
				),
				'map_meta_cap'        => true,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
			)
		);
	}

	public function meta_boxes() {
		add_meta_box(
			'sidrena_service_price',
			__( 'Sidrena — cijene usluge', 'sidrena' ),
			array( $this, 'render_meta_box' ),
			'sidrena_service',
			'normal',
			'high'
		);
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'sidrena_service_save', 'sidrena_service_nonce' );
		$fields = array(
			'current_price' => get_post_meta( $post->ID, '_sidrena_service_current_price', true ),
			'anchor_price'  => get_post_meta( $post->ID, '_sidrena_service_anchor_price', true ),
			'anchor_date'   => get_post_meta( $post->ID, '_sidrena_service_anchor_date', true ),
			'sale'          => get_post_meta( $post->ID, '_sidrena_service_sale', true ),
			'sale_name'     => get_post_meta( $post->ID, '_sidrena_service_sale_name', true ),
			'service_type'  => get_post_meta( $post->ID, '_sidrena_service_type', true ),
			'service_scope' => get_post_meta( $post->ID, '_sidrena_service_scope', true ),
			'service_costs' => get_post_meta( $post->ID, '_sidrena_service_costs', true ),
			'service_goods' => get_post_meta( $post->ID, '_sidrena_service_goods', true ),
		);
		$fields['reference_group'] = Sidrena_Utils::sanitize_reference_group( get_post_meta( $post->ID, '_sidrena_service_reference_group', true ), false );
		if ( 'custom' !== $fields['reference_group'] ) {
			$fields['anchor_date'] = '';
		}
		?>
		<div class="sidrena-service-grid">
			<p>
				<label for="sidrena_service_current_price"><strong><?php esc_html_e( 'Aktualna maloprodajna cijena (€)', 'sidrena' ); ?></strong></label><br>
				<input class="regular-text" type="number" min="0" step="0.01" id="sidrena_service_current_price" name="sidrena_service_current_price" value="<?php echo esc_attr( $fields['current_price'] ); ?>">
			</p>
			<p>
				<label for="sidrena_service_anchor_price"><strong><?php esc_html_e( 'Sidrena cijena (€)', 'sidrena' ); ?></strong></label><br>
				<input class="regular-text" type="number" min="0" step="0.01" id="sidrena_service_anchor_price" name="sidrena_service_anchor_price" value="<?php echo esc_attr( $fields['anchor_price'] ); ?>">
			</p>
			<p>
				<label for="sidrena_service_reference_group"><strong><?php esc_html_e( 'Pravni datum sidrene cijene', 'sidrena' ); ?></strong></label><br>
				<select id="sidrena_service_reference_group" name="sidrena_service_reference_group">
					<option value="standard" <?php selected( $fields['reference_group'], 'standard' ); ?>><?php esc_html_e( 'Zaključano: 10.09.2026.', 'sidrena' ); ?></option>
					<option value="custom" <?php selected( $fields['reference_group'], 'custom' ); ?>><?php esc_html_e( 'Novouvedena usluga nakon 10.09.2026.', 'sidrena' ); ?></option>
				</select>
			</p>
			<p>
				<label for="sidrena_service_anchor_date"><strong><?php esc_html_e( 'Datum prvog uvrštenja nove usluge', 'sidrena' ); ?></strong></label><br>
				<input type="date" min="2026-09-11" id="sidrena_service_anchor_date" name="sidrena_service_anchor_date" value="<?php echo esc_attr( $fields['anchor_date'] ); ?>">
				<small><?php esc_html_e( 'Koristi se samo za stvarno novouvedenu uslugu nakon 10.09.2026.; inače se datum zaključava na 10.09.2026.', 'sidrena' ); ?></small>
			</p>
			<p>
				<label><input type="checkbox" name="sidrena_service_sale" value="yes" <?php checked( $fields['sale'], 'yes' ); ?>> <?php esc_html_e( 'Aktualna cijena primjenjuje se tijekom posebnog oblika prodaje', 'sidrena' ); ?></label>
			</p>
			<p>
				<label for="sidrena_service_sale_name"><strong><?php esc_html_e( 'Naziv posebnog oblika prodaje', 'sidrena' ); ?></strong></label><br>
				<input class="regular-text" type="text" id="sidrena_service_sale_name" name="sidrena_service_sale_name" value="<?php echo esc_attr( $fields['sale_name'] ); ?>" placeholder="<?php esc_attr_e( 'npr. Akcija', 'sidrena' ); ?>">
			</p>
		</div>
		<div class="sid-service-details">
			<h3><?php esc_html_e( 'Podaci za javni cjenik usluga', 'sidrena' ); ?></h3>
			<p class="description"><?php esc_html_e( 'NN 105/2026 čl. 9.–11. uređuje prikaz cijena usluga: uz cijenu se navode naziv, vrsta i opseg usluge, cijena obuhvaća pripadajuće troškove, a cijena ugradbene ili zamjenske robe ističe se kada je roba sastavni dio usluge. Ova opisna polja dopunjuju javni prikaz; strojno čitljivi CSV/XML cjenik slijedi zasebna polja iz NN 101/2026.', 'sidrena' ); ?></p>
			<div class="sidrena-service-grid">
				<p><label for="sidrena_service_type"><strong><?php esc_html_e( 'Vrsta usluge', 'sidrena' ); ?></strong></label><br><input class="regular-text" type="text" id="sidrena_service_type" name="sidrena_service_type" value="<?php echo esc_attr( $fields['service_type'] ); ?>" placeholder="<?php esc_attr_e( 'npr. servisna usluga', 'sidrena' ); ?>"></p>
				<p><label for="sidrena_service_scope"><strong><?php esc_html_e( 'Opseg usluge', 'sidrena' ); ?></strong></label><br><textarea class="large-text" rows="3" id="sidrena_service_scope" name="sidrena_service_scope" placeholder="<?php esc_attr_e( 'Što točno usluga uključuje', 'sidrena' ); ?>"><?php echo esc_textarea( $fields['service_scope'] ); ?></textarea></p>
				<p><label for="sidrena_service_costs"><strong><?php esc_html_e( 'Pripadajući troškovi / napomena o cijeni', 'sidrena' ); ?></strong></label><br><textarea class="large-text" rows="3" id="sidrena_service_costs" name="sidrena_service_costs" placeholder="<?php esc_attr_e( 'Navedite što je uključeno u cijenu i relevantne troškove', 'sidrena' ); ?>"><?php echo esc_textarea( $fields['service_costs'] ); ?></textarea></p>
				<p><label for="sidrena_service_goods"><strong><?php esc_html_e( 'Ugradbena / zamjenska roba i cijena', 'sidrena' ); ?></strong></label><br><textarea class="large-text" rows="3" id="sidrena_service_goods" name="sidrena_service_goods" placeholder="<?php esc_attr_e( 'Ako je roba sastavni dio usluge, navedite robu i njezinu cijenu uz uslugu', 'sidrena' ); ?>"><?php echo esc_textarea( $fields['service_goods'] ); ?></textarea><small><?php esc_html_e( 'NN 105/2026 čl. 11. traži isticanje cijene ugradbene ili zamjenske robe uz pripadajuću uslugu kada je roba sastavni dio usluge.', 'sidrena' ); ?></small></p>
			</div>
		</div>
		<?php
		$location_prices        = get_post_meta( $post->ID, '_sidrena_service_location_prices', true );
		$location_anchor_prices = get_post_meta( $post->ID, '_sidrena_service_location_anchor_prices', true );
		$location_prices        = is_array( $location_prices ) ? $location_prices : array();
		$location_anchor_prices = is_array( $location_anchor_prices ) ? $location_anchor_prices : array();
		$locations              = Sidrena_Utils::locations();
		?>
		<div class="sid-service-locations">
			<h3><?php esc_html_e( 'Cijena po lokaciji', 'sidrena' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Ako ova usluga ima različitu cijenu po poslovnici ili webshopu, ovdje unesite lokalnu maloprodajnu cijenu. Prazno polje koristi osnovnu cijenu usluge.', 'sidrena' ); ?></p>
			<div class="sidrena-service-grid">
			<?php foreach ( $locations as $location ) : ?>
				<?php
				if ( 'yes' !== ( $location['enabled'] ?? '' ) ) {
					continue;
				} $location_id = Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' );
				?>
				<p class="sid-service-location-card">
					<strong class="sid-service-location-title"><?php echo esc_html( ( $location['code'] ?? $location_id ) . ' · ' . ( $location['address'] ?? '' ) ); ?></strong>
					<span class="sid-service-location-fields">
						<label for="sidrena_service_location_price_<?php echo esc_attr( $location_id ); ?>"><?php esc_html_e( 'Aktualna cijena (€)', 'sidrena' ); ?></label>
						<input class="regular-text" type="number" min="0" step="0.01" inputmode="decimal" id="sidrena_service_location_price_<?php echo esc_attr( $location_id ); ?>" name="sidrena_service_location_price[<?php echo esc_attr( $location_id ); ?>]" value="<?php echo esc_attr( $location_prices[ $location_id ] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Koristi osnovnu cijenu', 'sidrena' ); ?>">
						<label for="sidrena_service_location_anchor_price_<?php echo esc_attr( $location_id ); ?>"><?php esc_html_e( 'Sidrena cijena (€)', 'sidrena' ); ?></label>
						<input class="regular-text" type="number" min="0" step="0.01" inputmode="decimal" id="sidrena_service_location_anchor_price_<?php echo esc_attr( $location_id ); ?>" name="sidrena_service_location_anchor_price[<?php echo esc_attr( $location_id ); ?>]" value="<?php echo esc_attr( $location_anchor_prices[ $location_id ] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Koristi osnovnu sidrenu cijenu', 'sidrena' ); ?>">
					</span>
				</p>
			<?php endforeach; ?>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Za uslugu prvi put uvedenu nakon 10.09.2026. koristi se cijena i datum prvog dana ponude. Objavljeni CSV/XML cjenici čuvaju se javno 30 dana.', 'sidrena' ); ?></p>
		<?php
	}

	public function save( $post_id, $post ) {
		unset( $post );
		if ( ! isset( $_POST['sidrena_service_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sidrena_service_nonce'] ) ), 'sidrena_service_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$map = array(
			'sidrena_service_current_price' => '_sidrena_service_current_price',
			'sidrena_service_anchor_price'  => '_sidrena_service_anchor_price',
			'sidrena_service_sale_name'     => '_sidrena_service_sale_name',
			'sidrena_service_type'          => '_sidrena_service_type',
		);

		foreach ( $map as $field => $meta ) {
			$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			if ( false !== strpos( $field, 'price' ) ) {
				$value = Sidrena_Utils::validated_nonnegative_decimal( $value );
				if ( null === $value ) {
					continue;
				}
			} elseif ( false !== strpos( $field, 'date' ) ) {
				$value = Sidrena_Utils::sanitize_date( $value );
			} else {
				$value = sanitize_text_field( $value );
			}

			if ( '' === $value ) {
				delete_post_meta( $post_id, $meta );
			} else {
				update_post_meta( $post_id, $meta, $value );
			}
		}

		foreach ( array(
			'sidrena_service_scope' => '_sidrena_service_scope',
			'sidrena_service_costs' => '_sidrena_service_costs',
			'sidrena_service_goods' => '_sidrena_service_goods',
		) as $field => $meta ) {
			$value = isset( $_POST[ $field ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) : '';
			if ( '' === $value ) {
				delete_post_meta( $post_id, $meta );
			} else {
				update_post_meta( $post_id, $meta, $value );
			}
		}

		update_post_meta( $post_id, '_sidrena_service_sale', isset( $_POST['sidrena_service_sale'] ) ? 'yes' : 'no' );

		$reference_group = isset( $_POST['sidrena_service_reference_group'] )
			? Sidrena_Utils::sanitize_reference_group( wp_unslash( $_POST['sidrena_service_reference_group'] ), false )
			: 'standard';
		update_post_meta( $post_id, '_sidrena_service_reference_group', $reference_group );
		if ( 'custom' === $reference_group ) {
			$custom_date = isset( $_POST['sidrena_service_anchor_date'] )
				? Sidrena_Utils::custom_reference_date( wp_unslash( $_POST['sidrena_service_anchor_date'] ) )
				: '';
			if ( $custom_date ) {
				update_post_meta( $post_id, '_sidrena_service_anchor_date', $custom_date );
			} else {
				delete_post_meta( $post_id, '_sidrena_service_anchor_date' );
			}
		} else {
			delete_post_meta( $post_id, '_sidrena_service_anchor_date' );
		}
		// Legacy 30-day sale-reference metadata is intentionally not part of the active SIDRENA workflow.
		delete_post_meta( $post_id, '_sidrena_service_lowest_30_manual' );
		delete_post_meta( $post_id, '_sidrena_service_lowest_30_exception' );

		$existing_location_prices = get_post_meta( $post_id, '_sidrena_service_location_prices', true );
		$existing_location_prices = is_array( $existing_location_prices ) ? $existing_location_prices : array();
		$location_prices          = array();
		$posted_prices            = isset( $_POST['sidrena_service_location_price'] ) && is_array( $_POST['sidrena_service_location_price'] ) ? map_deep( wp_unslash( $_POST['sidrena_service_location_price'] ), 'sanitize_text_field' ) : array();
		foreach ( $posted_prices as $location_id => $location_price ) {
			$location_id    = Sidrena_Utils::sanitize_location_id( $location_id );
			$location_price = Sidrena_Utils::validated_nonnegative_decimal( $location_price );
			if ( null === $location_price ) {
				if ( isset( $existing_location_prices[ $location_id ] ) ) {
					$location_prices[ $location_id ] = $existing_location_prices[ $location_id ];
				}
				continue;
			}
			if ( '' !== $location_price ) {
				$location_prices[ $location_id ] = $location_price;
			}
		}
		if ( $location_prices ) {
			update_post_meta( $post_id, '_sidrena_service_location_prices', $location_prices );
		} else {
			delete_post_meta( $post_id, '_sidrena_service_location_prices' );
		}

		$existing_location_anchor_prices = get_post_meta( $post_id, '_sidrena_service_location_anchor_prices', true );
		$existing_location_anchor_prices = is_array( $existing_location_anchor_prices ) ? $existing_location_anchor_prices : array();
		$location_anchor_prices          = array();
		$posted_anchor_prices            = isset( $_POST['sidrena_service_location_anchor_price'] ) && is_array( $_POST['sidrena_service_location_anchor_price'] ) ? map_deep( wp_unslash( $_POST['sidrena_service_location_anchor_price'] ), 'sanitize_text_field' ) : array();
		foreach ( $posted_anchor_prices as $location_id => $location_price ) {
			$location_id    = Sidrena_Utils::sanitize_location_id( $location_id );
			$location_price = Sidrena_Utils::validated_nonnegative_decimal( $location_price );
			if ( null === $location_price ) {
				if ( isset( $existing_location_anchor_prices[ $location_id ] ) ) {
					$location_anchor_prices[ $location_id ] = $existing_location_anchor_prices[ $location_id ];
				}
				continue;
			}
			if ( '' !== $location_price ) {
				$location_anchor_prices[ $location_id ] = $location_price;
			}
		}
		if ( $location_anchor_prices ) {
			update_post_meta( $post_id, '_sidrena_service_location_anchor_prices', $location_anchor_prices );
		} else {
			delete_post_meta( $post_id, '_sidrena_service_location_anchor_prices' );
		}
	}

	public function after_insert_post( $post_id, $post, $update, $post_before ) {
		unset( $update );
		if ( ! $post instanceof WP_Post || 'sidrena_service' !== $post->post_type || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$was_published = $post_before instanceof WP_Post && 'publish' === $post_before->post_status;
		if ( 'publish' === $post->post_status && ! $was_published ) {
			$this->snapshot_newly_published( $post_id, $post );
		}

		Sidrena_Pricelist::queue_regeneration();
	}

	private function snapshot_newly_published( $post_id, $post ) {
		if ( '' !== get_post_meta( $post_id, '_sidrena_service_anchor_price', true ) ) {
			return;
		}

		$current = get_post_meta( $post_id, '_sidrena_service_current_price', true );
		if ( '' === $current ) {
			return;
		}

		$published = get_post_datetime( $post );
		if ( ! $published ) {
			return;
		}

		try {
			$cutoff = new DateTimeImmutable( Sidrena_Utils::standard_reference_date() . ' 23:59:59', wp_timezone() );
		} catch ( Exception $exception ) {
			return;
		}
		if ( $published <= $cutoff ) {
			return;
		}

		update_post_meta( $post_id, '_sidrena_service_anchor_price', Sidrena_Utils::decimal( $current ) );
		update_post_meta( $post_id, '_sidrena_service_reference_group', 'custom' );
		update_post_meta( $post_id, '_sidrena_service_anchor_date', $published->format( 'Y-m-d' ) );
	}

	public function columns( $columns ) {
		$columns['sidrena_current'] = __( 'Aktualna cijena', 'sidrena' );
		$columns['sidrena_anchor']  = __( 'Sidrena cijena', 'sidrena' );
		return $columns;
	}

	public function column_values( $column, $post_id ) {
		if ( 'sidrena_current' === $column ) {
			echo esc_html( Sidrena_Utils::money( get_post_meta( $post_id, '_sidrena_service_current_price', true ) ) . ' €' );
		}
		if ( 'sidrena_anchor' === $column ) {
			$price = get_post_meta( $post_id, '_sidrena_service_anchor_price', true );
			$date  = Sidrena_Utils::service_reference_date( $post_id );
			echo esc_html( Sidrena_Utils::money( $price ) . ' € · ' . Sidrena_Utils::date_display( $date ) );
		}
	}

	public function shortcode( $atts = array() ) {
		$atts     = shortcode_atts(
			array(
				'po_stranici' => 50,
			),
			$atts,
			'sidrena_usluge'
		);
		$per_page = min( 100, max( 10, absint( $atts['po_stranici'] ) ) );
		$page     = isset( $_GET['sidrena_usluge_stranica'] ) ? max( 1, absint( wp_unslash( $_GET['sidrena_usluge_stranica'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query    = $this->service_page_query( $page, $per_page );

		if ( $query->max_num_pages && $page > (int) $query->max_num_pages ) {
			$page  = (int) $query->max_num_pages;
			$query = $this->service_page_query( $page, $per_page );
		}

		if ( empty( $query->posts ) ) {
			wp_reset_postdata();
			return '<p class="sidrena-public-message">' . esc_html__( 'Cjenik usluga još nema objavljenih stavki.', 'sidrena' ) . '</p>';
		}

		wp_enqueue_style( 'sidrena-frontend', SIDRENA_URL . 'public/css/frontend.css', array(), SIDRENA_VERSION );
		$total       = absint( $query->found_posts );
		$total_pages = max( 1, absint( $query->max_num_pages ) );
		$first       = ( ( $page - 1 ) * $per_page ) + 1;
		$last        = min( $total, $first + count( $query->posts ) - 1 );
		$out         = '<div class="sidrena-services" role="region" aria-label="' . esc_attr__( 'Cjenik usluga', 'sidrena' ) . '">';
		/* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */
		$out        .= '<p class="sidrena-services__summary">' . esc_html( sprintf( __( 'Prikazano %1$d–%2$d od %3$d usluga.', 'sidrena' ), $first, $last, $total ) ) . '</p>';
		$out        .= '<div class="sidrena-services__table-wrap"><table class="sidrena-services__table">';
		$out        .= '<caption class="sidrena-visually-hidden">' . esc_html__( 'Aktualni cjenik usluga', 'sidrena' ) . '</caption><thead><tr>';
		$out        .= '<th scope="col">' . esc_html__( 'Usluga', 'sidrena' ) . '</th>';
		$out        .= '<th scope="col">' . esc_html__( 'Aktualna cijena', 'sidrena' ) . '</th>';
		$out .= '<th scope="col">' . esc_html__( 'Sidrena cijena', 'sidrena' ) . '</th>';
		$out .= '</tr></thead><tbody>';

		foreach ( $query->posts as $service ) {
			$current = get_post_meta( $service->ID, '_sidrena_service_current_price', true );
			$anchor  = get_post_meta( $service->ID, '_sidrena_service_anchor_price', true );
			$date    = Sidrena_Utils::service_reference_date( $service->ID );
			$out  .= '<tr>';
			$type  = trim( (string) get_post_meta( $service->ID, '_sidrena_service_type', true ) );
			$scope = trim( (string) get_post_meta( $service->ID, '_sidrena_service_scope', true ) );
			$costs = trim( (string) get_post_meta( $service->ID, '_sidrena_service_costs', true ) );
			$goods = trim( (string) get_post_meta( $service->ID, '_sidrena_service_goods', true ) );
			$out  .= '<th scope="row"><span class="sidrena-service-name">' . esc_html( get_the_title( $service ) ) . '</span>';
			if ( $type ) {
				$out .= '<small class="sidrena-service-meta"><strong>' . esc_html__( 'Vrsta:', 'sidrena' ) . '</strong> ' . esc_html( $type ) . '</small>';
			}
			if ( $scope ) {
				$out .= '<small class="sidrena-service-meta"><strong>' . esc_html__( 'Opseg:', 'sidrena' ) . '</strong> ' . nl2br( esc_html( $scope ) ) . '</small>';
			}
			if ( $costs ) {
				$out .= '<small class="sidrena-service-meta"><strong>' . esc_html__( 'Troškovi:', 'sidrena' ) . '</strong> ' . nl2br( esc_html( $costs ) ) . '</small>';
			}
			if ( $goods ) {
				$out .= '<small class="sidrena-service-meta"><strong>' . esc_html__( 'Ugradbena/zamjenska roba:', 'sidrena' ) . '</strong> ' . nl2br( esc_html( $goods ) ) . '</small>';
			}
			$out         .= '</th>';
			$current_text = '' === $current ? '—' : Sidrena_Utils::money( $current ) . ' €';
			$anchor_text  = '' === $anchor ? '—' : Sidrena_Utils::money( $anchor ) . ' €';
			$out         .= '<td data-label="' . esc_attr__( 'Aktualna cijena', 'sidrena' ) . '">' . esc_html( $current_text ) . '</td>';
			$out .= '<td data-label="' . esc_attr__( 'Sidrena cijena', 'sidrena' ) . '">' . esc_html( $anchor_text );
			if ( '' !== $anchor ) {
				$out .= '<small>' . esc_html( Sidrena_Utils::anchor_label( $date ) ) . '</small>';
			}
			$out .= '</td>';
			$out .= '</tr>';
		}
		$out .= '</tbody></table></div>';

		if ( $total_pages > 1 ) {
			$base_url = remove_query_arg( 'sidrena_usluge_stranica' );
			$out     .= '<nav class="sidrena-services__pagination" aria-label="' . esc_attr__( 'Stranice cjenika usluga', 'sidrena' ) . '">';
			if ( $page > 1 ) {
				$out .= '<a rel="prev" href="' . esc_url( add_query_arg( 'sidrena_usluge_stranica', $page - 1, $base_url ) ) . '">' . esc_html__( 'Prethodna stranica', 'sidrena' ) . '</a>';
			}
			/* translators: printf placeholders are replaced with runtime values shown to the administrator or visitor. */
			$out .= '<span>' . esc_html( sprintf( __( 'Stranica %1$d od %2$d', 'sidrena' ), $page, $total_pages ) ) . '</span>';
			if ( $page < $total_pages ) {
				$out .= '<a rel="next" href="' . esc_url( add_query_arg( 'sidrena_usluge_stranica', $page + 1, $base_url ) ) . '">' . esc_html__( 'Sljedeća stranica', 'sidrena' ) . '</a>';
			}
			$out .= '</nav>';
		}

		$out .= '</div>';
		wp_reset_postdata();
		return $out;
	}

	private function service_page_query( $page, $per_page ) {
		return new WP_Query(
			array(
				'post_type'      => 'sidrena_service',
				'post_status'    => 'publish',
				'posts_per_page' => min( 100, max( 10, absint( $per_page ) ) ),
				'paged'          => max( 1, absint( $page ) ),
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'no_found_rows'  => false,
			)
		);
	}
}
