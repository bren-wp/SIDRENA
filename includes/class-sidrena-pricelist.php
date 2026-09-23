<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sidrena_Pricelist {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'sidrena_daily_generation', array( $this, 'generate_all' ) );
		add_action( 'sidrena_queued_generation', array( $this, 'generate_all' ) );
	}

	public static function queue_regeneration() {
		if ( ! wp_next_scheduled( 'sidrena_queued_generation' ) ) {
			wp_schedule_single_event( time() + 60, 'sidrena_queued_generation' );
		}
	}

	public function generate_all() {
		$settings  = Sidrena_Utils::settings();
		$locations = Sidrena_Utils::locations();
		$paths     = Sidrena_Utils::upload_paths();
		$index     = array();
		$expected  = array();
		$errors    = array();
		$generated = 0;

		wp_mkdir_p( $paths['archive_dir'] );
		$timestamp = time();
		$stamp     = wp_date( 'd.m.Y_H-i-s', $timestamp );
		$formats   = $this->formats( $settings );

		if ( empty( $formats ) ) {
			$errors[] = __( 'CSV i XML izlaz su isključeni. Uključite barem jedan format.', 'sidrena' );
		}

		foreach ( $locations as $location_index => &$location ) {
			if ( empty( $location['enabled'] ) || 'yes' !== $location['enabled'] ) {
				continue;
			}

			$address = trim( isset( $location['address'] ) ? $location['address'] : '' );
			if ( '' === $address ) {
				$errors[] = sprintf(
					/* translators: %s is a location code. */
					__( 'Lokacija "%s" nema upisanu adresu potrebnu za naziv datoteke cjenika.', 'sidrena' ),
					isset( $location['code'] ) ? $location['code'] : ( $location_index + 1 )
				);
				continue;
			}

			$sequence = max( 1, isset( $location['sequence'] ) ? absint( $location['sequence'] ) : 1 );
			foreach ( $this->catalog_types( $settings['business_mode'] ) as $catalog_type ) {
				foreach ( $formats as $format ) {
					$key              = $this->index_key( $location, $catalog_type, $format );
					$expected[ $key ] = true;
					$filename         = $this->build_filename( $location, $sequence, $stamp, $format );
					$filepath         = $paths['archive_dir'] . $filename;
					$result           = 'products' === $catalog_type
						? $this->write_products( $filepath, $format, $location )
						: $this->write_services( $filepath, $format, $location );

					if ( is_wp_error( $result ) ) {
						$errors[] = $result->get_error_message();
						++$sequence;
						continue;
					}

					$hash  = is_file( $filepath ) ? hash_file( 'sha256', $filepath ) : '';
					$bytes = is_file( $filepath ) ? filesize( $filepath ) : 0;
					++$generated;
					$index[] = array(
						'location_id'   => sanitize_key( isset( $location['id'] ) ? $location['id'] : '' ),
						'location_code' => sanitize_text_field( isset( $location['code'] ) ? $location['code'] : '' ),
						'kind'          => sanitize_key( isset( $location['kind'] ) ? $location['kind'] : 'objekt' ),
						'catalog'       => $catalog_type,
						'format'        => $format,
						'url'           => $paths['archive_url'] . rawurlencode( $filename ),
						'filename'      => $filename,
						'generated_at'  => wp_date( DATE_ATOM, $timestamp ),
						'generated_ts'  => $timestamp,
						'retain_until'  => wp_date( DATE_ATOM, $timestamp + ( max( 30, absint( $settings['retention_days'] ) ) * DAY_IN_SECONDS ) ),
						'retain_until_ts' => $timestamp + ( max( 30, absint( $settings['retention_days'] ) ) * DAY_IN_SECONDS ),
						'sequence'      => $sequence,
						'rows'          => (int) $result,
						'bytes'         => (int) $bytes,
						'sha256'        => $hash ? sanitize_text_field( $hash ) : '',
					);
					++$sequence;
				}
			}
			$location['sequence'] = $sequence;
		}
		unset( $location );

		update_option( 'sidrena_locations', $locations, false );
		if ( ! empty( $index ) ) {
			$this->merge_archive_index( $index );
		}
		$this->merge_current_index( $index, $expected );

		update_option(
			'sidrena_last_run',
			array(
				'generated_at' => wp_date( DATE_ATOM, $timestamp ),
				'files'        => $generated,
				'errors'       => $errors,
			),
			false
		);

		$this->cleanup_archives();
		$this->write_manifest();
		Sidrena_Audit::log(
			'pricelist_generation',
			empty( $errors ) ? 'success' : 'warning',
			empty( $errors ) ? __( 'Generiranje cjenika dovršeno.', 'sidrena' ) : __( 'Generiranje cjenika dovršeno s upozorenjima.', 'sidrena' ),
			array(
				'files'  => $generated,
				'errors' => $errors,
			)
		);
		return empty( $errors );
	}

	private function formats( $settings ) {
		$formats = array();
		if ( 'yes' === $settings['generate_csv'] ) {
			$formats[] = 'csv';
		}
		if ( 'yes' === $settings['generate_xml'] ) {
			$formats[] = 'xml';
		}
		return $formats;
	}

	private function catalog_types( $mode ) {
		if ( 'products' === $mode ) {
			return array( 'products' );
		}
		if ( 'services' === $mode ) {
			return array( 'services' );
		}
		return array( 'products', 'services' );
	}

	private function index_key( $location, $catalog, $format ) {
		return implode(
			'|',
			array(
				Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' ),
				sanitize_key( $catalog ),
				sanitize_key( $format ),
			)
		);
	}

	private function build_filename( $location, $sequence, $stamp, $format ) {
		$kind    = Sidrena_Utils::filename_part( isset( $location['kind'] ) ? $location['kind'] : 'objekt' );
		$address = Sidrena_Utils::filename_part( isset( $location['address'] ) ? $location['address'] : '' );
		$code    = Sidrena_Utils::filename_part( isset( $location['code'] ) ? $location['code'] : '01' );

		// Required components: object type, address, object code, storage sequence,
		// and transmission/generation date+time. File extension indicates CSV/XML.
		return sprintf( '%s_%s_%s_%06d_%s.%s', $kind, $address, $code, $sequence, $stamp, $format );
	}

	private function write_products( $filepath, $format, $location ) {
		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			return new WP_Error( 'no_woo', __( 'WooCommerce nije aktivan; cjenik proizvoda nije generiran.', 'sidrena' ) );
		}

		$headers = array(
			'naziv',
			'sifra',
			'marka',
			'jedinica_mjere',
			'cijena_za_jedinicu_mjere',
			'maloprodajna_cijena',
			'posebni_oblik_prodaje',
			'naziv_posebnog_oblika_prodaje',
			'sidrena_cijena',
			'datum_sidrene_cijene',
			'barkod',
			'dostupnost',
		);

		if ( 'csv' === $format ) {
			return $this->write_csv( $filepath, $headers, $this->product_rows( $location ) );
		}
		return $this->write_xml( $filepath, 'proizvodi', 'proizvod', $headers, $this->product_rows( $location ) );
	}

	private function write_services( $filepath, $format, $location ) {
		$headers = array(
			'naziv_usluge',
			'vrsta_usluge',
			'opseg_usluge',
			'pripadajuci_troskovi',
			'ugradbena_zamjenska_roba',
			'maloprodajna_cijena',
			'posebni_oblik_prodaje',
			'naziv_posebnog_oblika_prodaje',
			'sidrena_cijena',
			'datum_sidrene_cijene',
		);

		if ( 'csv' === $format ) {
			return $this->write_csv( $filepath, $headers, $this->service_rows( $location ) );
		}
		return $this->write_xml( $filepath, 'usluge', 'usluga', $headers, $this->service_rows( $location ) );
	}

	private function product_rows( $location ) {
		$page = 1;
		do {
			$query = new WC_Product_Query(
				array(
					'limit'   => 100,
					'page'    => $page,
					'status'  => array( 'publish' ),
					'return'  => 'objects',
					'orderby' => 'ID',
					'order'   => 'ASC',
				)
			);
			$products = $query->get_products();

			foreach ( $products as $product ) {
				if ( $product->is_type( 'variable' ) ) {
					foreach ( $product->get_children() as $variation_id ) {
						$variation = wc_get_product( $variation_id );
						if ( $variation && 'publish' === get_post_status( $variation->get_parent_id() ) ) {
							yield $this->product_row( $variation, $location );
						}
					}
					continue;
				}
				yield $this->product_row( $product, $location );
			}
			++$page;
		} while ( count( $products ) === 100 );
	}

	private function product_row( $product, $location ) {
		$location_id = Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' );
		$override    = Sidrena_Location_Data::get_for_product( $location_id, $product );
		$raw_price   = $product->get_price( 'edit' );
		$price       = $raw_price;

		if ( isset( $override['price'] ) && '' !== $override['price'] ) {
			// Imported per-location price is treated as the final retail amount.
			$price = (float) $override['price'];
		} elseif ( function_exists( 'wc_get_price_including_tax' ) && '' !== $raw_price ) {
			$price = wc_get_price_including_tax( $product, array( 'price' => (float) $raw_price ) );
		}

		$has_location_anchor = isset( $override['anchor_price'] ) && '' !== $override['anchor_price'];
		$anchor              = $has_location_anchor
			? Sidrena_Utils::decimal( $override['anchor_price'] )
			: Sidrena_Utils::product_anchor_price( $product->get_id() );

		// Per-location imports are entered as final retail amounts. The base
		// WooCommerce anchor follows the store's tax-entry setting and therefore
		// needs the same retail conversion as the live WooCommerce price.
		if ( ! $has_location_anchor && '' !== $anchor && function_exists( 'wc_get_price_including_tax' ) ) {
			$anchor = wc_get_price_including_tax( $product, array( 'price' => (float) $anchor ) );
		}

		$available = isset( $override['availability'] ) && in_array( $override['availability'], array( 'dostupno', 'nedostupno' ), true )
			? $override['availability']
			: ( $product->is_in_stock() ? 'dostupno' : 'nedostupno' );
		$available = apply_filters( 'sidrena_product_availability', $available, $product, $location );
		$current   = apply_filters( 'sidrena_product_retail_price', $price, $product, $location );
		$sale_name = Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_sale_name' );
		if ( ! $sale_name && $product->is_on_sale() ) {
			$sale_name = __( 'Akcija', 'sidrena' );
		}

		$name = $product->get_name();
		if ( $product->is_type( 'variation' ) ) {
			$attributes = wc_get_formatted_variation( $product, true, false, false );
			if ( $attributes ) {
				$name .= ' — ' . wp_strip_all_tags( $attributes );
			}
		}

		$brand_product = $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : $product;
		return array(
			'naziv'                         => $name,
			'sifra'                         => Sidrena_Utils::get_product_code( $product ),
			'marka'                         => Sidrena_Utils::get_brand( $brand_product ),
			'jedinica_mjere'                => Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_unit' ),
			'cijena_za_jedinicu_mjere'      => Sidrena_Utils::money( Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_unit_price' ), 4 ),
			'maloprodajna_cijena'           => Sidrena_Utils::money( $current ),
			'posebni_oblik_prodaje'         => $product->is_on_sale() ? 'da' : 'ne',
			'naziv_posebnog_oblika_prodaje' => $product->is_on_sale() ? $sale_name : '',
			'sidrena_cijena'                 => Sidrena_Utils::money( $anchor ),
			'datum_sidrene_cijene'           => '' === $anchor ? '' : Sidrena_Utils::date_display( Sidrena_Utils::current_reference_date( $product->get_id() ) ),
			'barkod'                         => Sidrena_Utils::get_barcode( $product ),
			'dostupnost'                     => sanitize_text_field( $available ),
		);
	}

	private function service_rows( $location ) {
		$page = 1;
		do {
			$query = new WP_Query(
				array(
					'post_type'      => 'sidrena_service',
					'post_status'    => 'publish',
					'posts_per_page' => 250,
					'paged'          => $page,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);

			foreach ( $query->posts as $service ) {
				$current         = get_post_meta( $service->ID, '_sidrena_service_current_price', true );
				$location_prices = get_post_meta( $service->ID, '_sidrena_service_location_prices', true );
				$location_prices = is_array( $location_prices ) ? $location_prices : array();
				$location_id     = Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' );
				if ( isset( $location_prices[ $location_id ] ) && '' !== $location_prices[ $location_id ] ) {
					$current = Sidrena_Utils::decimal( $location_prices[ $location_id ] );
				}
				$anchor_prices = get_post_meta( $service->ID, '_sidrena_service_location_anchor_prices', true );
				$anchor_prices = is_array( $anchor_prices ) ? $anchor_prices : array();
				$anchor        = isset( $anchor_prices[ $location_id ] ) && '' !== $anchor_prices[ $location_id ]
					? Sidrena_Utils::decimal( $anchor_prices[ $location_id ] )
					: get_post_meta( $service->ID, '_sidrena_service_anchor_price', true );
				$date    = get_post_meta( $service->ID, '_sidrena_service_anchor_date', true );
				if ( ! $date ) {
					$date = Sidrena_Utils::settings()['default_ref_date'];
				}

				$current = apply_filters( 'sidrena_service_retail_price', $current, $service, $location );
				$sale    = 'yes' === get_post_meta( $service->ID, '_sidrena_service_sale', true );
				yield array(
					'naziv_usluge'                  => get_the_title( $service ),
					'vrsta_usluge'                  => get_post_meta( $service->ID, '_sidrena_service_type', true ),
					'opseg_usluge'                  => get_post_meta( $service->ID, '_sidrena_service_scope', true ),
					'pripadajuci_troskovi'           => get_post_meta( $service->ID, '_sidrena_service_costs', true ),
					'ugradbena_zamjenska_roba'       => get_post_meta( $service->ID, '_sidrena_service_goods', true ),
					'maloprodajna_cijena'           => Sidrena_Utils::money( $current ),
					'posebni_oblik_prodaje'         => $sale ? 'da' : 'ne',
					'naziv_posebnog_oblika_prodaje' => $sale ? get_post_meta( $service->ID, '_sidrena_service_sale_name', true ) : '',
					'sidrena_cijena'                 => Sidrena_Utils::money( $anchor ),
					'datum_sidrene_cijene'           => '' === $anchor ? '' : Sidrena_Utils::date_display( $date ),
				);
			}
			$count = count( $query->posts );
			++$page;
		} while ( 250 === $count );
		wp_reset_postdata();
	}

	private function write_csv( $filepath, $headers, $rows ) {
		$settings  = Sidrena_Utils::settings();
		$delimiter = isset( $settings['csv_delimiter'] ) && in_array( $settings['csv_delimiter'], array( ';', ',', '\t' ), true ) ? $settings['csv_delimiter'] : ';';
		if ( '\t' === $delimiter ) {
			$delimiter = "\t";
		}

		$temp = $filepath . '.tmp';
		$handle = fopen( $temp, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new WP_Error( 'file_open', sprintf( __( 'Nije moguće otvoriti datoteku za pisanje: %s', 'sidrena' ), basename( $filepath ) ) );
		}

		fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $handle, $headers, $delimiter );
		$count = 0;
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $headers as $header ) {
				$line[] = isset( $row[ $header ] ) ? $row[ $header ] : '';
			}
			fputcsv( $handle, $line, $delimiter );
			++$count;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( ! rename( $temp, $filepath ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			@unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error( 'file_commit', sprintf( __( 'Nije moguće dovršiti zapis datoteke: %s', 'sidrena' ), basename( $filepath ) ) );
		}
		return $count;
	}

	private function write_xml( $filepath, $root, $item, $headers, $rows ) {
		$temp   = $filepath . '.tmp';
		$handle = fopen( $temp, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new WP_Error( 'file_open', sprintf( __( 'Nije moguće otvoriti datoteku za pisanje: %s', 'sidrena' ), basename( $filepath ) ) );
		}

		fwrite( $handle, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<{$root}>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		$count = 0;
		foreach ( $rows as $row ) {
			fwrite( $handle, "  <{$item}>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			foreach ( $headers as $header ) {
				$value = isset( $row[ $header ] ) ? (string) $row[ $header ] : '';
				fwrite( $handle, '    <' . $header . '>' . esc_xml( $value ) . '</' . $header . ">\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			}
			fwrite( $handle, "  </{$item}>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			++$count;
		}
		fwrite( $handle, "</{$root}>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( ! rename( $temp, $filepath ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			@unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error( 'file_commit', sprintf( __( 'Nije moguće dovršiti zapis datoteke: %s', 'sidrena' ), basename( $filepath ) ) );
		}
		return $count;
	}

	private function merge_archive_index( $new_files ) {
		$archive = Sidrena_Utils::archive_index();
		$by_name = array();
		foreach ( array_merge( $archive, $new_files ) as $file ) {
			if ( empty( $file['filename'] ) ) {
				continue;
			}
			$by_name[ $file['filename'] ] = $file;
		}
		$archive = array_values( $by_name );
		usort(
			$archive,
			static function ( $a, $b ) {
				return (int) ( $b['generated_ts'] ?? 0 ) <=> (int) ( $a['generated_ts'] ?? 0 );
			}
		);
		update_option( 'sidrena_archive_index', $archive, false );
	}

	private function merge_current_index( $new_files, $expected ) {
		$existing = Sidrena_Utils::public_index();
		$by_key   = array();
		foreach ( $existing as $file ) {
			$key = implode( '|', array( sanitize_key( $file['location_id'] ?? '' ), sanitize_key( $file['catalog'] ?? '' ), sanitize_key( $file['format'] ?? '' ) ) );
			if ( isset( $expected[ $key ] ) ) {
				$by_key[ $key ] = $file;
			}
		}
		foreach ( $new_files as $file ) {
			$key = implode( '|', array( sanitize_key( $file['location_id'] ?? '' ), sanitize_key( $file['catalog'] ?? '' ), sanitize_key( $file['format'] ?? '' ) ) );
			$by_key[ $key ] = $file;
		}
		update_option( 'sidrena_public_index', array_values( $by_key ), false );
	}

	public function cleanup_archives() {
		$settings      = Sidrena_Utils::settings();
		$days          = max( 30, absint( $settings['retention_days'] ) );
		$cutoff        = time() - ( DAY_IN_SECONDS * $days );
		$paths         = Sidrena_Utils::upload_paths();
		$current_names = array();

		// A currently published cjenik must never disappear merely because it is
		// older than the archive retention window (especially relevant to
		// service-only sites where a cjenik may stay valid until the next change).
		foreach ( Sidrena_Utils::public_index() as $entry ) {
			$filename = isset( $entry['filename'] ) ? basename( (string) $entry['filename'] ) : '';
			if ( $filename ) {
				$current_names[ $filename ] = true;
			}
		}

		$archive = array();
		foreach ( Sidrena_Utils::archive_index() as $entry ) {
			$generated = isset( $entry['generated_ts'] ) ? absint( $entry['generated_ts'] ) : 0;
			$filename  = isset( $entry['filename'] ) ? basename( (string) $entry['filename'] ) : '';
			if ( ! $filename ) {
				continue;
			}

			$path       = $paths['archive_dir'] . $filename;
			$is_current = isset( $current_names[ $filename ] );

			// Never shorten a retention promise already stored in the archive index.
			// A later increase of the configured retention extends older entries too.
			$minimum_until    = $generated ? $generated + ( 30 * DAY_IN_SECONDS ) : 0;
			$configured_until = $generated ? $generated + ( $days * DAY_IN_SECONDS ) : 0;
			$stored_until     = isset( $entry['retain_until_ts'] ) ? absint( $entry['retain_until_ts'] ) : 0;
			$retain_until     = max( $minimum_until, $configured_until, $stored_until );
			$is_expired       = $retain_until
				? time() >= $retain_until
				: ( is_file( $path ) && filemtime( $path ) < $cutoff );

			if ( $generated && $retain_until > $stored_until ) {
				$entry['retain_until_ts'] = $retain_until;
				$entry['retain_until']    = wp_date( DATE_ATOM, $retain_until );
			}

			if ( $is_expired && ! $is_current ) {
				if ( is_file( $path ) ) {
					unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				}
				continue;
			}

			if ( is_file( $path ) ) {
				$archive[] = $entry;
			}
		}
		update_option( 'sidrena_archive_index', $archive, false );

		// Remove only orphaned files that are beyond retention and are not the
		// currently published file. This also cleans interrupted/legacy writes.
		$known = array();
		foreach ( $archive as $entry ) {
			if ( ! empty( $entry['filename'] ) ) {
				$known[ basename( (string) $entry['filename'] ) ] = true;
			}
		}
		$files = glob( $paths['archive_dir'] . '*.{csv,xml}', GLOB_BRACE );
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				$filename = basename( $file );
				if ( isset( $known[ $filename ] ) || isset( $current_names[ $filename ] ) ) {
					continue;
				}
				if ( is_file( $file ) && filemtime( $file ) < $cutoff ) {
					unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				}
			}
		}
	}

	private function write_manifest() {
		$settings = Sidrena_Utils::settings();
		$paths    = Sidrena_Utils::upload_paths();
		if ( 'yes' !== $settings['publish_manifest'] ) {
			if ( is_file( $paths['manifest'] ) ) {
				unlink( $paths['manifest'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
			return;
		}
		$data  = array(
			'schema'       => 3,
			'generator'    => 'Sidrena ' . SIDRENA_VERSION,
			'plugin_url'   => 'https://sidrena-cijena.com.hr/',
			'ruleset'      => SIDRENA_RULESET,
			'realtime_url'  => rest_url( 'sidrena/v1/cijene' ),
			'generated_at' => current_time( DATE_ATOM ),
			'retention_days' => max( 30, absint( $settings['retention_days'] ) ),
			'current'      => Sidrena_Utils::public_index(),
			'archive'      => Sidrena_Utils::archive_index(),
		);
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false !== $json ) {
			file_put_contents( $paths['manifest'], $json . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}
}
