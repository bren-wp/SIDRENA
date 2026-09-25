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
		add_action( 'transition_post_status', array( $this, 'snapshot_newly_published' ), 10, 3 );
		add_filter( 'manage_sidrena_service_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_sidrena_service_posts_custom_column', array( $this, 'column_values' ), 10, 2 );
		add_shortcode( 'sidrena_usluge', array( $this, 'shortcode' ) );
	}

	public function register_post_type() {
		$capability = Sidrena_Utils::admin_capability();

		register_post_type(
			'sidrena_service',
			array(
				'labels' => array(
					'name'          => __( 'Usluge', 'sidrena' ),
					'singular_name' => __( 'Usluga', 'sidrena' ),
					'add_new_item'  => __( 'Dodaj uslugu', 'sidrena' ),
					'edit_item'     => __( 'Uredi uslugu', 'sidrena' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'sidrena',
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'capabilities'        => array(
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
			'lowest_30'     => get_post_meta( $post->ID, '_sidrena_service_lowest_30_manual', true ),
			'exception'     => get_post_meta( $post->ID, '_sidrena_service_lowest_30_exception', true ),
			'service_type'  => get_post_meta( $post->ID, '_sidrena_service_type', true ),
			'service_scope' => get_post_meta( $post->ID, '_sidrena_service_scope', true ),
			'service_costs' => get_post_meta( $post->ID, '_sidrena_service_costs', true ),
			'service_goods' => get_post_meta( $post->ID, '_sidrena_service_goods', true ),
		);
		if ( ! $fields['anchor_date'] ) {
			$fields['anchor_date'] = Sidrena_Utils::settings()['default_ref_date'];
		}
		?>
		<div class="sidrena-service-grid">
			<p>
				<label for="sidrena_service_current_price"><strong><?php esc_html_e( 'Aktualna maloprodajna cijena (€)', 'sidrena' ); ?></strong></label><br>
				<input class="regular-text" type="number" min="0" step="0.01" id="sidrena_service_current_price" name="sidrena_service_current_price" value="<?php echo esc_attr( $fields['current_price'] ); ?>">
			</p>
			<p>
				<label for="sidrena_service_anchor_price"><strong><?php esc_html_e( 'Dodatna / sidrena cijena (€)', 'sidrena' ); ?></strong></label><br>
				<input class="regular-text" type="number" min="0" step="0.01" id="sidrena_service_anchor_price" name="sidrena_service_anchor_price" value="<?php echo esc_attr( $fields['anchor_price'] ); ?>">
			</p>
			<p>
				<label for="sidrena_service_anchor_date"><strong><?php esc_html_e( 'Referentni datum', 'sidrena' ); ?></strong></label><br>
				<input type="date" id="sidrena_service_anchor_date" name="sidrena_service_anchor_date" value="<?php echo esc_attr( $fields['anchor_date'] ); ?>">
			</p>
			<p>
				<label><input type="checkbox" name="sidrena_service_sale" value="yes" <?php checked( $fields['sale'], 'yes' ); ?>> <?php esc_html_e( 'Posebni oblik prodaje / sniženje je aktivno', 'sidrena' ); ?></label>
			</p>
			<p>
				<label for="sidrena_service_sale_name"><strong><?php esc_html_e( 'Naziv posebnog oblika prodaje', 'sidrena' ); ?></strong></label><br>
				<input class="regular-text" type="text" id="sidrena_service_sale_name" name="sidrena_service_sale_name" value="<?php echo esc_attr( $fields['sale_name'] ); ?>" placeholder="<?php esc_attr_e( 'npr. Akcija', 'sidrena' ); ?>">
			</p>
			<p>
				<label for="sidrena_service_lowest_30_manual"><strong><?php esc_html_e( 'Najniža cijena prije sniženja — ručna vrijednost (€)', 'sidrena' ); ?></strong></label><br>
				<input class="regular-text" type="number" min="0" step="0.01" id="sidrena_service_lowest_30_manual" name="sidrena_service_lowest_30_manual" value="<?php echo esc_attr( $fields['lowest_30'] ); ?>">
				<small><?php esc_html_e( 'Ostavite prazno za automatski izračun kada postoji potpuna 30-dnevna povijest.', 'sidrena' ); ?></small>
			</p>
			<p>
				<label for="sidrena_service_lowest_30_exception"><strong><?php esc_html_e( 'Iznimka za 30-dnevnu referencu usluge', 'sidrena' ); ?></strong></label><br>
				<select id="sidrena_service_lowest_30_exception" name="sidrena_service_lowest_30_exception">
					<option value="" <?php selected( $fields['exception'], '' ); ?>><?php esc_html_e( 'Nije označena iznimka', 'sidrena' ); ?></option>
					<option value="advertising" <?php selected( $fields['exception'], 'advertising' ); ?>><?php esc_html_e( 'Oglašavanje usluge', 'sidrena' ); ?></option>
					<option value="distance" <?php selected( $fields['exception'], 'distance' ); ?>><?php esc_html_e( 'Ugovor na daljinu', 'sidrena' ); ?></option>
					<option value="off_premises" <?php selected( $fields['exception'], 'off_premises' ); ?>><?php esc_html_e( 'Ugovor izvan poslovnih prostorija', 'sidrena' ); ?></option>
				</select>
				<small><?php esc_html_e( 'Koristite samo kada ste provjerili da se iznimka iz čl. 19. st. 8. Zakona o zaštiti potrošača stvarno primjenjuje na konkretan slučaj.', 'sidrena' ); ?></small>
			</p>
		</div>
		<div class="sid-service-details">
			<h3><?php esc_html_e( 'Podaci za javni cjenik usluga', 'sidrena' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Pravilnik NN 105/2026 traži da uz cijenu usluge budu navedeni naziv, vrsta i opseg usluge te da cijena obuhvati pripadajuće troškove. Ovdje evidentirajte te podatke za javni prikaz.', 'sidrena' ); ?></p>
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
		$locations       = Sidrena_Utils::locations();
		?>
		<div class="sid-service-locations">
			<h3><?php esc_html_e( 'Cijena po lokaciji', 'sidrena' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Ako ova usluga ima različitu cijenu po poslovnici ili webshopu, ovdje unesite lokalnu maloprodajnu cijenu. Prazno polje koristi osnovnu cijenu usluge.', 'sidrena' ); ?></p>
			<div class="sidrena-service-grid">
			<?php foreach ( $locations as $location ) : ?>
				<?php if ( 'yes' !== ( $location['enabled'] ?? '' ) ) { continue; } $location_id = Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' ); ?>
				<p>
					<label><strong><?php echo esc_html( ( $location['code'] ?? $location_id ) . ' · ' . ( $location['address'] ?? '' ) ); ?></strong></label><br>
					<input class="regular-text" type="number" min="0" step="0.01" name="sidrena_service_location_price[<?php echo esc_attr( $location_id ); ?>]" value="<?php echo esc_attr( $location_prices[ $location_id ] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Aktualna — koristi osnovnu', 'sidrena' ); ?>"><br>
					<input class="regular-text" type="number" min="0" step="0.01" name="sidrena_service_location_anchor_price[<?php echo esc_attr( $location_id ); ?>]" value="<?php echo esc_attr( $location_anchor_prices[ $location_id ] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Sidrena — koristi osnovnu', 'sidrena' ); ?>">
				</p>
			<?php endforeach; ?>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Za usluge prvi put uvedene nakon referentnog datuma koristite cijenu i datum prvog dana ponude. Povijest cijena i javna 30+ dnevna arhiva cjenika vode se odvojeno.', 'sidrena' ); ?></p>
		<?php
	}

	public function save( $post_id, $post ) {
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
			'sidrena_service_anchor_date'   => '_sidrena_service_anchor_date',
			'sidrena_service_sale_name'     => '_sidrena_service_sale_name',
			'sidrena_service_lowest_30_manual' => '_sidrena_service_lowest_30_manual',
			'sidrena_service_type'         => '_sidrena_service_type',
		);

		foreach ( $map as $field => $meta ) {
			$value = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : '';
			if ( false !== strpos( $field, 'price' ) ) {
				$value = Sidrena_Utils::decimal( $value );
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

		foreach ( array( 'sidrena_service_scope' => '_sidrena_service_scope', 'sidrena_service_costs' => '_sidrena_service_costs', 'sidrena_service_goods' => '_sidrena_service_goods' ) as $field => $meta ) {
			$value = isset( $_POST[ $field ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) : '';
			if ( '' === $value ) {
				delete_post_meta( $post_id, $meta );
			} else {
				update_post_meta( $post_id, $meta, $value );
			}
		}

		update_post_meta( $post_id, '_sidrena_service_sale', isset( $_POST['sidrena_service_sale'] ) ? 'yes' : 'no' );

		$exception = isset( $_POST['sidrena_service_lowest_30_exception'] ) ? sanitize_key( wp_unslash( $_POST['sidrena_service_lowest_30_exception'] ) ) : '';
		if ( ! in_array( $exception, array( '', 'advertising', 'distance', 'off_premises' ), true ) ) {
			$exception = '';
		}
		if ( $exception ) {
			update_post_meta( $post_id, '_sidrena_service_lowest_30_exception', $exception );
		} else {
			delete_post_meta( $post_id, '_sidrena_service_lowest_30_exception' );
		}

		$location_prices = array();
		$posted_prices   = isset( $_POST['sidrena_service_location_price'] ) && is_array( $_POST['sidrena_service_location_price'] ) ? wp_unslash( $_POST['sidrena_service_location_price'] ) : array();
		foreach ( $posted_prices as $location_id => $location_price ) {
			$location_id    = Sidrena_Utils::sanitize_location_id( $location_id );
			$location_price = Sidrena_Utils::decimal( $location_price );
			if ( '' !== $location_price ) {
				$location_prices[ $location_id ] = $location_price;
			}
		}
		if ( $location_prices ) {
			update_post_meta( $post_id, '_sidrena_service_location_prices', $location_prices );
		} else {
			delete_post_meta( $post_id, '_sidrena_service_location_prices' );
		}

		$location_anchor_prices = array();
		$posted_anchor_prices   = isset( $_POST['sidrena_service_location_anchor_price'] ) && is_array( $_POST['sidrena_service_location_anchor_price'] ) ? wp_unslash( $_POST['sidrena_service_location_anchor_price'] ) : array();
		foreach ( $posted_anchor_prices as $location_id => $location_price ) {
			$location_id    = Sidrena_Utils::sanitize_location_id( $location_id );
			$location_price = Sidrena_Utils::decimal( $location_price );
			if ( '' !== $location_price ) {
				$location_anchor_prices[ $location_id ] = $location_price;
			}
		}
		if ( $location_anchor_prices ) {
			update_post_meta( $post_id, '_sidrena_service_location_anchor_prices', $location_anchor_prices );
		} else {
			delete_post_meta( $post_id, '_sidrena_service_location_anchor_prices' );
		}

		Sidrena_Pricelist::queue_regeneration();
	}

	public function snapshot_newly_published( $new_status, $old_status, $post ) {
		if ( 'sidrena_service' !== $post->post_type || 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		if ( '' !== get_post_meta( $post->ID, '_sidrena_service_anchor_price', true ) ) {
			return;
		}

		$current = get_post_meta( $post->ID, '_sidrena_service_current_price', true );
		if ( '' === $current ) {
			return;
		}

		$settings  = Sidrena_Utils::settings();
		$published = get_post_datetime( $post );
		$cutoff    = new DateTimeImmutable( $settings['default_ref_date'] . ' 23:59:59', wp_timezone() );
		if ( ! $published || $published <= $cutoff ) {
			return;
		}

		update_post_meta( $post->ID, '_sidrena_service_anchor_price', Sidrena_Utils::decimal( $current ) );
		update_post_meta( $post->ID, '_sidrena_service_anchor_date', $published->format( 'Y-m-d' ) );
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
			$date  = get_post_meta( $post_id, '_sidrena_service_anchor_date', true );
			if ( ! $date ) {
				$date = Sidrena_Utils::settings()['default_ref_date'];
			}
			echo esc_html( Sidrena_Utils::money( $price ) . ' € · ' . Sidrena_Utils::date_display( $date ) );
		}
	}

	public function shortcode( $atts = array() ) {
		$atts = shortcode_atts(
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
		$out        .= '<p class="sidrena-services__summary">' . esc_html( sprintf( __( 'Prikazano %1$d–%2$d od %3$d usluga.', 'sidrena' ), $first, $last, $total ) ) . '</p>';
		$out        .= '<div class="sidrena-services__table-wrap"><table class="sidrena-services__table"><thead><tr>';
		$out        .= '<th scope="col">' . esc_html__( 'Usluga', 'sidrena' ) . '</th>';
		$out        .= '<th scope="col">' . esc_html__( 'Aktualna cijena', 'sidrena' ) . '</th>';
		$show_lowest = 'yes' === Sidrena_Utils::settings()['display_lowest_30'];
		if ( $show_lowest ) {
			$out .= '<th scope="col">' . esc_html__( 'Najniža cijena u prethodnih 30 dana', 'sidrena' ) . '</th>';
		}
		$out .= '<th scope="col">' . esc_html__( 'Dodatna cijena', 'sidrena' ) . '</th>';
		$out .= '</tr></thead><tbody>';

		foreach ( $query->posts as $service ) {
			$current = get_post_meta( $service->ID, '_sidrena_service_current_price', true );
			$anchor  = get_post_meta( $service->ID, '_sidrena_service_anchor_price', true );
			$date    = get_post_meta( $service->ID, '_sidrena_service_anchor_date', true );
			if ( ! $date ) {
				$date = Sidrena_Utils::settings()['default_ref_date'];
			}
			$out .= '<tr>';
			$type  = trim( (string) get_post_meta( $service->ID, '_sidrena_service_type', true ) );
			$scope = trim( (string) get_post_meta( $service->ID, '_sidrena_service_scope', true ) );
			$costs = trim( (string) get_post_meta( $service->ID, '_sidrena_service_costs', true ) );
			$goods = trim( (string) get_post_meta( $service->ID, '_sidrena_service_goods', true ) );
			$out .= '<th scope="row"><span class="sidrena-service-name">' . esc_html( get_the_title( $service ) ) . '</span>';
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
			$out .= '</th>';
			$current_text = '' === $current ? '—' : Sidrena_Utils::money( $current ) . ' €';
			$anchor_text  = '' === $anchor ? '—' : Sidrena_Utils::money( $anchor ) . ' €';
			$out .= '<td data-label="' . esc_attr__( 'Aktualna cijena', 'sidrena' ) . '">' . esc_html( $current_text ) . '</td>';
			if ( $show_lowest ) {
				$reference      = Sidrena_Service_History::sale_reference( $service->ID );
				$reference_text = 'ready' === $reference['status'] ? Sidrena_Utils::money( $reference['price'] ) . ' €' : '—';
				$out .= '<td data-label="' . esc_attr__( 'Najniža cijena u prethodnih 30 dana', 'sidrena' ) . '">' . esc_html( $reference_text ) . '</td>';
			}
			$out .= '<td data-label="' . esc_attr__( 'Dodatna cijena', 'sidrena' ) . '">' . esc_html( $anchor_text );
			if ( '' !== $anchor ) {
				$out .= '<small>' . esc_html( Sidrena_Utils::anchor_label( $date ) ) . '</small>';
			}
			$out .= '</td>';
			$out .= '</tr>';
		}
		$out .= '</tbody></table></div>';

		if ( $total_pages > 1 ) {
			$base_url = remove_query_arg( 'sidrena_usluge_stranica' );
			$out .= '<nav class="sidrena-services__pagination" aria-label="' . esc_attr__( 'Stranice cjenika usluga', 'sidrena' ) . '">';
			if ( $page > 1 ) {
				$out .= '<a rel="prev" href="' . esc_url( add_query_arg( 'sidrena_usluge_stranica', $page - 1, $base_url ) ) . '">' . esc_html__( 'Prethodna stranica', 'sidrena' ) . '</a>';
			}
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
