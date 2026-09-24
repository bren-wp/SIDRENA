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
		add_action( 'sidrena_publication_watch', array( $this, 'publication_watch' ) );
	}

	public static function queue_regeneration() {
		if ( wp_next_scheduled( 'sidrena_queued_generation' ) ) {
			return true;
		}
		return false !== wp_schedule_single_event( time() + 60, 'sidrena_queued_generation' );
	}


	public function publication_watch() {
		$settings = Sidrena_Utils::settings();
		if ( 'yes' !== $settings['generate_csv'] && 'yes' !== $settings['generate_xml'] ) {
			return;
		}
		$now_time = wp_date( 'H:i' );
		$target   = isset( $settings['generation_time'] ) ? (string) $settings['generation_time'] : '06:30';
		if ( $now_time < $target ) {
			return;
		}
		$last    = get_option( 'sidrena_last_run', array() );
		$last_ts = ! empty( $last['generated_at'] ) ? strtotime( (string) $last['generated_at'] ) : 0;
		if ( $last_ts && wp_date( 'Y-m-d', $last_ts ) === wp_date( 'Y-m-d' ) ) {
			return;
		}
		if ( self::queue_regeneration() ) {
			Sidrena_Audit::log(
				'publication_watch',
				'info',
				__( 'Sigurnosna provjera je uočila da današnji cjenik još nije objavljen nakon planiranog vremena te je pokrenula ponovno generiranje.', 'sidrena' ),
				array( 'target_time' => $target )
			);
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
		wp_mkdir_p( $paths['snapshot_dir'] );
		$lock = $this->acquire_generation_lock( $paths );
		if ( is_wp_error( $lock ) ) {
			Sidrena_Audit::log(
				'pricelist_generation',
				'warning',
				$lock->get_error_message(),
				array( 'code' => $lock->get_error_code() )
			);
			return false;
		}

		try {
			$timestamp = time();
			$stamp     = wp_date( 'd.m.Y_H-i', $timestamp );
			$formats   = $this->formats( $settings );

			if ( empty( $formats ) ) {
				$errors[] = __( 'CSV i XML izlaz su isključeni. Uključite barem jedan format.', 'sidrena' );
				Sidrena_Audit::log(
					'pricelist_generation',
					'warning',
					__( 'Generiranje cjenika nije pokrenuto jer su CSV i XML izlaz isključeni.', 'sidrena' ),
					array( 'errors' => $errors )
				);
				return false;
			}

			foreach ( $locations as $location_index => &$location ) {
				if ( empty( $location['enabled'] ) || 'yes' !== $location['enabled'] ) {
					continue;
				}

				$address = trim( isset( $location['address'] ) ? $location['address'] : '' );
				if ( '' === $address ) {
					$errors[] = sprintf(
						__( 'Lokacija "%s" nema upisanu adresu potrebnu za naziv datoteke cjenika.', 'sidrena' ),
						isset( $location['code'] ) ? $location['code'] : ( $location_index + 1 )
					);
					continue;
				}

				$sequence          = max( 1, isset( $location['sequence'] ) ? absint( $location['sequence'] ) : 1 );
				$snapshot_catalogs = array();

				foreach ( $this->catalog_types( $settings['business_mode'] ) as $catalog_type ) {
					foreach ( $formats as $format ) {
						$expected[ $this->index_key( $location, $catalog_type, $format ) ] = true;
					}

					if ( 'yes' === $settings['strict_publication'] ) {
						$preflight = $this->preflight_catalog( $catalog_type, $location );
						if ( is_wp_error( $preflight ) ) {
							$errors[] = $preflight->get_error_message();
							continue;
						}
					}

					$catalog_generated = false;
					foreach ( $formats as $format ) {
						$filename = $this->build_filename( $location, $sequence, $stamp, $format );
						$filepath = $paths['archive_dir'] . $filename;
						$result   = 'products' === $catalog_type
							? $this->write_products( $filepath, $format, $location )
							: $this->write_services( $filepath, $format, $location );

						if ( is_wp_error( $result ) ) {
							$errors[] = $result->get_error_message();
							++$sequence;
							continue;
						}

						$catalog_generated = true;
						$hash  = is_file( $filepath ) ? hash_file( 'sha256', $filepath ) : '';
						$bytes = is_file( $filepath ) ? filesize( $filepath ) : 0;
						++$generated;
						$index[] = array(
							'location_id'     => sanitize_key( isset( $location['id'] ) ? $location['id'] : '' ),
							'location_code'   => sanitize_text_field( isset( $location['code'] ) ? $location['code'] : '' ),
							'kind'            => sanitize_key( isset( $location['kind'] ) ? $location['kind'] : 'objekt' ),
							'catalog'         => $catalog_type,
							'format'          => $format,
							'url'             => $paths['archive_url'] . rawurlencode( $filename ),
							'filename'        => $filename,
							'generated_at'    => wp_date( DATE_ATOM, $timestamp ),
							'generated_ts'    => $timestamp,
							'retain_until'    => wp_date( DATE_ATOM, $timestamp + ( max( 30, absint( $settings['retention_days'] ) ) * DAY_IN_SECONDS ) ),
							'retain_until_ts' => $timestamp + ( max( 30, absint( $settings['retention_days'] ) ) * DAY_IN_SECONDS ),
							'sequence'        => $sequence,
							'rows'            => (int) $result,
							'bytes'           => (int) $bytes,
							'sha256'          => $hash ? sanitize_text_field( $hash ) : '',
						);
						++$sequence;
					}

					if ( $catalog_generated ) {
						$snapshot_catalogs[] = $catalog_type;
					}
				}

				if ( ! empty( $snapshot_catalogs ) ) {
					$snapshot = $this->write_public_snapshot( $location, $snapshot_catalogs, $timestamp );
					if ( is_wp_error( $snapshot ) ) {
						$errors[] = $snapshot->get_error_message();
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
			$this->cleanup_public_snapshots( $locations );

			$this->cleanup_archives();
			$manifest = $this->write_manifest();
			if ( is_wp_error( $manifest ) ) {
				$errors[] = $manifest->get_error_message();
			}
			update_option(
				'sidrena_last_run',
				array(
					'generated_at' => wp_date( DATE_ATOM, $timestamp ),
					'files'        => $generated,
					'errors'       => $errors,
				),
				false
			);

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
		} finally {
			$this->release_generation_lock( $lock );
		}
	}


	private function acquire_generation_lock( $paths ) {
		$lock_path = trailingslashit( $paths['base_dir'] ) . 'generation.lock';
		$handle    = @fopen( $lock_path, 'c+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $handle ) {
			return new WP_Error( 'generation_lock_unavailable', __( 'Nije moguće otvoriti sigurnosni lock za generiranje cjenika.', 'sidrena' ) );
		}
		if ( ! flock( $handle, LOCK_EX | LOCK_NB ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_flock
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'generation_locked', __( 'Generiranje cjenika je već u tijeku. Novi paralelni proces nije pokrenut.', 'sidrena' ) );
		}

		ftruncate( $handle, 0 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_ftruncate
		rewind( $handle );
		fwrite( $handle, wp_json_encode( array( 'started_at' => time(), 'version' => SIDRENA_VERSION ) ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fflush( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fflush
		return $handle;
	}

	private function release_generation_lock( $handle ) {
		if ( ! is_resource( $handle ) ) {
			return;
		}
		flock( $handle, LOCK_UN ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_flock
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}


	private function preflight_catalog( $catalog_type, $location ) {
		$issues = array();
		$rows   = 'products' === $catalog_type ? $this->product_rows( $location ) : $this->service_rows( $location );
		$kind   = sanitize_key( $location['kind'] ?? 'objekt' );
		$code   = sanitize_text_field( $location['code'] ?? $location['id'] ?? 'lokacija' );

		foreach ( $rows as $row ) {
			$row_issues = 'products' === $catalog_type
				? $this->validate_product_row( $row, $kind )
				: $this->validate_service_row( $row );

			foreach ( $row_issues as $issue ) {
				$issues[] = $issue;
				if ( count( $issues ) >= 12 ) {
					break 2;
				}
			}
		}

		if ( empty( $issues ) ) {
			return true;
		}

		return new WP_Error(
			'sidrena_preflight_failed',
			sprintf(
				__( 'Cjenik za lokaciju %1$s (%2$s) nije objavljen jer stroga provjera nije prošla: %3$s', 'sidrena' ),
				$code,
				'products' === $catalog_type ? __( 'proizvodi', 'sidrena' ) : __( 'usluge', 'sidrena' ),
				implode( '; ', $issues )
			)
		);
	}

	private function validate_product_row( $row, $location_kind ) {
		$issues = array();
		$id     = absint( $row['_sidrena_item_id'] ?? 0 );
		$name   = trim( (string) ( $row['naziv'] ?? '' ) );
		$label  = $name ? $name : sprintf( __( 'proizvod #%d', 'sidrena' ), $id );

		$required = array(
			'sifra'               => __( 'šifra', 'sidrena' ),
			'marka'               => __( 'marka', 'sidrena' ),
			'maloprodajna_cijena' => __( 'maloprodajna cijena', 'sidrena' ),
			'sidrena_cijena'      => __( 'sidrena cijena', 'sidrena' ),
			'barkod'              => __( 'barkod', 'sidrena' ),
			'dostupnost'          => __( 'dostupnost', 'sidrena' ),
		);
		if ( '' === $name ) {
			$issues[] = sprintf( __( '%s: nedostaje naziv', 'sidrena' ), $label );
		}
		foreach ( $required as $key => $field_label ) {
			if ( '' === trim( (string) ( $row[ $key ] ?? '' ) ) ) {
				$issues[] = sprintf( __( '%1$s: nedostaje %2$s', 'sidrena' ), $label, $field_label );
			}
		}

		if ( 'webshop' !== $location_kind && 'yes' !== ( $row['_sidrena_location_explicit'] ?? 'no' ) ) {
			$issues[] = sprintf( __( '%s: fizička lokacija nema unesenu stvarnu raspoloživost', 'sidrena' ), $label );
		}

		$unit_status = sanitize_key( (string) ( $row['_sidrena_unit_status'] ?? 'review' ) );
		if ( ! $unit_status || 'review' === $unit_status ) {
			$issues[] = sprintf( __( '%s: primjenjivost jedinične cijene nije pregledana', 'sidrena' ), $label );
		} elseif ( 'required' === $unit_status ) {
			if ( '' === trim( (string) ( $row['jedinica_mjere'] ?? '' ) ) || '' === trim( (string) ( $row['cijena_za_jedinicu_mjere'] ?? '' ) ) ) {
				$issues[] = sprintf( __( '%s: obvezna jedinična cijena nije potpuno unesena', 'sidrena' ), $label );
			}
		}

		if ( 'da' === ( $row['posebni_oblik_prodaje'] ?? '' ) && '' === trim( (string) ( $row['naziv_posebnog_oblika_prodaje'] ?? '' ) ) ) {
			$issues[] = sprintf( __( '%s: aktivni posebni oblik prodaje nema naziv', 'sidrena' ), $label );
		}
		return $issues;
	}

	private function validate_service_row( $row ) {
		$issues = array();
		$id     = absint( $row['_sidrena_item_id'] ?? 0 );
		$name   = trim( (string) ( $row['naziv_usluge'] ?? '' ) );
		$label  = $name ? $name : sprintf( __( 'usluga #%d', 'sidrena' ), $id );

		if ( '' === $name ) {
			$issues[] = sprintf( __( '%s: nedostaje naziv usluge', 'sidrena' ), $label );
		}
		if ( '' === trim( (string) ( $row['maloprodajna_cijena'] ?? '' ) ) ) {
			$issues[] = sprintf( __( '%s: nedostaje maloprodajna cijena', 'sidrena' ), $label );
		}
		if ( '' === trim( (string) ( $row['sidrena_cijena'] ?? '' ) ) ) {
			$issues[] = sprintf( __( '%s: nedostaje sidrena cijena', 'sidrena' ), $label );
		}
		if ( 'da' === ( $row['posebni_oblik_prodaje'] ?? '' ) && '' === trim( (string) ( $row['naziv_posebnog_oblika_prodaje'] ?? '' ) ) ) {
			$issues[] = sprintf( __( '%s: aktivni posebni oblik prodaje nema naziv', 'sidrena' ), $label );
		}
		return $issues;
	}

	private function write_public_snapshot( $location, $catalogs, $timestamp ) {
		$path = Sidrena_Utils::public_snapshot_path( $location['id'] ?? '' );
		$temp = $path . '.tmp';
		$meta = array(
			'schema'       => 1,
			'generator'    => 'Sidrena ' . SIDRENA_VERSION,
			'generated_at' => wp_date( DATE_ATOM, $timestamp ),
			'location'     => array(
				'id'      => Sidrena_Utils::sanitize_location_id( $location['id'] ?? '' ),
				'code'    => sanitize_text_field( $location['code'] ?? '' ),
				'kind'    => sanitize_text_field( $location['kind'] ?? '' ),
				'address' => sanitize_text_field( $location['address'] ?? '' ),
			),
		);
		$header = wp_json_encode( $meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $header ) {
			return new WP_Error( 'snapshot_encode', __( 'Nije moguće pripremiti javni HTML snapshot cjenika.', 'sidrena' ) );
		}

		$handle = fopen( $temp, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new WP_Error( 'snapshot_write', __( 'Nije moguće otvoriti javni HTML snapshot za zapis.', 'sidrena' ) );
		}

		$prefix = substr( $header, 0, -1 ) . ',"rows":[';
		if ( false === fwrite( $handle, $prefix ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			@unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error( 'snapshot_write', __( 'Nije moguće zapisati javni HTML snapshot cjenika.', 'sidrena' ) );
		}

		$count = 0;
		$first = true;
		foreach ( array_unique( $catalogs ) as $catalog ) {
			$source = 'products' === $catalog ? $this->product_rows( $location ) : $this->service_rows( $location );
			foreach ( $source as $row ) {
				foreach ( array_keys( $row ) as $key ) {
					if ( 0 === strpos( $key, '_sidrena_' ) ) {
						unset( $row[ $key ] );
					}
				}
				$row['type'] = 'products' === $catalog ? 'product' : 'service';
				$encoded = wp_json_encode( $row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				if ( false === $encoded ) {
					fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
					@unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged
					return new WP_Error( 'snapshot_row_encode', __( 'Jedan redak javnog HTML snapshota nije moguće JSON kodirati.', 'sidrena' ) );
				}
				$chunk = ( $first ? '' : ',' ) . $encoded;
				if ( false === fwrite( $handle, $chunk ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
					fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
					@unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged
					return new WP_Error( 'snapshot_write', __( 'Nije moguće dovršiti zapis javnog HTML snapshota cjenika.', 'sidrena' ) );
				}
				$first = false;
				++$count;
			}
		}

		if ( false === fwrite( $handle, "]}\n" ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			@unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error( 'snapshot_write', __( 'Nije moguće dovršiti zapis javnog HTML snapshota cjenika.', 'sidrena' ) );
		}
		fflush( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fflush
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( ! rename( $temp, $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			@unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error( 'snapshot_commit', __( 'Nije moguće dovršiti javni HTML snapshot cjenika.', 'sidrena' ) );
		}
		return $count;
	}

	private function cleanup_public_snapshots( $locations ) {
		$paths = Sidrena_Utils::upload_paths();
		if ( ! is_dir( $paths['snapshot_dir'] ) ) {
			return;
		}
		$keep = array();
		foreach ( $locations as $location ) {
			if ( 'yes' === ( $location['enabled'] ?? '' ) ) {
				$keep[ basename( Sidrena_Utils::public_snapshot_path( $location['id'] ?? '' ) ) ] = true;
			}
		}
		$files = glob( $paths['snapshot_dir'] . 'cjenik-*.json' );
		foreach ( is_array( $files ) ? $files : array() as $file ) {
			if ( ! isset( $keep[ basename( $file ) ] ) && is_file( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
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
		if ( Sidrena_Utils::is_wordpress_edition() ) {
			foreach ( Sidrena_Standalone::iterate_rows( $location ) as $row ) {
				yield $row;
			}
			return;
		}

		if ( ! Sidrena_Utils::is_woocommerce_active() ) {
			return;
		}

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
				if ( ! Sidrena_Utils::is_public_wc_product( $product ) ) {
					continue;
				}
				if ( is_callable( array( $product, 'get_catalog_visibility' ) ) && 'hidden' === $product->get_catalog_visibility() ) {
					continue;
				}
				if ( $product->is_type( 'variable' ) ) {
					foreach ( $product->get_children() as $variation_id ) {
						$variation = wc_get_product( $variation_id );
						if ( $variation && Sidrena_Utils::is_public_wc_product( $variation ) ) {
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

		$has_location_availability = isset( $override['availability'] ) && in_array( $override['availability'], array( 'dostupno', 'nedostupno' ), true );
		$available = $has_location_availability
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
		$unit_status   = sanitize_key( (string) Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_unit_price_status', 'review' ) );
		$unit          = Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_unit' );
		$unit_price    = Sidrena_Utils::product_meta_with_parent( $product, '_sidrena_unit_price' );
		if ( in_array( $unit_status, array( 'not_required', 'exception' ), true ) ) {
			$unit       = '';
			$unit_price = '';
		}
		return array(
			'_sidrena_item_id'          => $product->get_id(),
			'_sidrena_unit_status'      => $unit_status,
			'_sidrena_location_explicit' => $has_location_availability ? 'yes' : 'no',
			'naziv'                         => $name,
			'sifra'                         => Sidrena_Utils::get_product_code( $product ),
			'marka'                         => Sidrena_Utils::get_brand( $brand_product ),
			'jedinica_mjere'                => $unit,
			'cijena_za_jedinicu_mjere'      => Sidrena_Utils::money( $unit_price, 4 ),
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
					'_sidrena_item_id'                   => $service->ID,
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

		$opened = $this->open_atomic_writer( $filepath );
		if ( is_wp_error( $opened ) ) {
			return $opened;
		}
		list( $handle, $temp ) = $opened;

		if ( ! $this->write_stream_all( $handle, "\xEF\xBB\xBF" ) || false === fputcsv( $handle, $headers, $delimiter ) ) {
			$this->discard_atomic_writer( $handle, $temp );
			return new WP_Error( 'file_write', sprintf( __( 'Nije moguće zapisati zaglavlje datoteke: %s', 'sidrena' ), basename( $filepath ) ) );
		}

		$count = 0;
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $headers as $header ) {
				$line[] = Sidrena_Utils::csv_safe_cell( isset( $row[ $header ] ) ? $row[ $header ] : '' );
			}
			if ( false === fputcsv( $handle, $line, $delimiter ) ) {
				$this->discard_atomic_writer( $handle, $temp );
				return new WP_Error( 'file_write', sprintf( __( 'Nije moguće zapisati redak datoteke: %s', 'sidrena' ), basename( $filepath ) ) );
			}
			++$count;
		}

		$result = $this->commit_atomic_writer( $handle, $temp, $filepath );
		return is_wp_error( $result ) ? $result : $count;
	}
	private function write_xml( $filepath, $root, $item, $headers, $rows ) {
		$opened = $this->open_atomic_writer( $filepath );
		if ( is_wp_error( $opened ) ) {
			return $opened;
		}
		list( $handle, $temp ) = $opened;

		if ( ! $this->write_stream_all( $handle, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<{$root}>\n" ) ) {
			$this->discard_atomic_writer( $handle, $temp );
			return new WP_Error( 'file_write', sprintf( __( 'Nije moguće započeti XML datoteku: %s', 'sidrena' ), basename( $filepath ) ) );
		}

		$count = 0;
		foreach ( $rows as $row ) {
			if ( ! $this->write_stream_all( $handle, "  <{$item}>\n" ) ) {
				$this->discard_atomic_writer( $handle, $temp );
				return new WP_Error( 'file_write', sprintf( __( 'Nije moguće zapisati XML datoteku: %s', 'sidrena' ), basename( $filepath ) ) );
			}
			foreach ( $headers as $header ) {
				$value = isset( $row[ $header ] ) ? (string) $row[ $header ] : '';
				$chunk = '    <' . $header . '>' . esc_xml( $value ) . '</' . $header . ">\n";
				if ( ! $this->write_stream_all( $handle, $chunk ) ) {
					$this->discard_atomic_writer( $handle, $temp );
					return new WP_Error( 'file_write', sprintf( __( 'Nije moguće zapisati XML datoteku: %s', 'sidrena' ), basename( $filepath ) ) );
				}
			}
			if ( ! $this->write_stream_all( $handle, "  </{$item}>\n" ) ) {
				$this->discard_atomic_writer( $handle, $temp );
				return new WP_Error( 'file_write', sprintf( __( 'Nije moguće zapisati XML datoteku: %s', 'sidrena' ), basename( $filepath ) ) );
			}
			++$count;
		}
		if ( ! $this->write_stream_all( $handle, "</{$root}>\n" ) ) {
			$this->discard_atomic_writer( $handle, $temp );
			return new WP_Error( 'file_write', sprintf( __( 'Nije moguće dovršiti XML datoteku: %s', 'sidrena' ), basename( $filepath ) ) );
		}

		$result = $this->commit_atomic_writer( $handle, $temp, $filepath );
		return is_wp_error( $result ) ? $result : $count;
	}
	private function open_atomic_writer( $filepath ) {
		$directory = dirname( $filepath );
		$suffix    = wp_generate_password( 12, false, false );
		$temp      = trailingslashit( $directory ) . '.' . basename( $filepath ) . '.' . $suffix . '.tmp';
		$handle    = fopen( $temp, 'xb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new WP_Error( 'file_open', sprintf( __( 'Nije moguće otvoriti privremenu datoteku za zapis: %s', 'sidrena' ), basename( $filepath ) ) );
		}
		return array( $handle, $temp );
	}

	private function write_stream_all( $handle, $data ) {
		$data   = (string) $data;
		$length = strlen( $data );
		$offset = 0;
		while ( $offset < $length ) {
			$written = fwrite( $handle, substr( $data, $offset ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			if ( false === $written || 0 === $written ) {
				return false;
			}
			$offset += $written;
		}
		return true;
	}

	private function discard_atomic_writer( $handle, $temp ) {
		if ( is_resource( $handle ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
		if ( $temp && is_file( $temp ) ) {
			unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	private function commit_atomic_writer( $handle, $temp, $filepath ) {
		if ( ! fflush( $handle ) ) {
			$this->discard_atomic_writer( $handle, $temp );
			return new WP_Error( 'file_flush', sprintf( __( 'Nije moguće dovršiti zapis datoteke: %s', 'sidrena' ), basename( $filepath ) ) );
		}
		if ( function_exists( 'fsync' ) && ! fsync( $handle ) ) {
			$this->discard_atomic_writer( $handle, $temp );
			return new WP_Error( 'file_sync', sprintf( __( 'Nije moguće sinkronizirati datoteku na disk: %s', 'sidrena' ), basename( $filepath ) ) );
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( ! rename( $temp, $filepath ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			if ( is_file( $temp ) ) {
				unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
			return new WP_Error( 'file_commit', sprintf( __( 'Nije moguće atomski objaviti datoteku: %s', 'sidrena' ), basename( $filepath ) ) );
		}
		return true;
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
			if ( is_file( $paths['manifest'] ) && ! unlink( $paths['manifest'] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				return new WP_Error( 'manifest_remove', __( 'Nije moguće ukloniti isključeni JSON manifest.', 'sidrena' ) );
			}
			return true;
		}

		$data = array(
			'schema'         => 3,
			'generator'      => 'Sidrena ' . SIDRENA_VERSION,
			'plugin_url'     => 'https://sidrene-cijene.com.hr/',
			'ruleset'        => SIDRENA_RULESET,
			'realtime_url'   => rest_url( 'sidrena/v1/cijene' ),
			'generated_at'   => current_time( DATE_ATOM ),
			'retention_days' => max( 30, absint( $settings['retention_days'] ) ),
			'current'        => Sidrena_Utils::public_index(),
			'archive'        => Sidrena_Utils::archive_index(),
		);
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return new WP_Error( 'manifest_encode', __( 'Nije moguće pripremiti JSON manifest cjenika.', 'sidrena' ) );
		}

		$payload = $json . "\n";
		$temp    = $paths['manifest'] . '.tmp';
		$written = file_put_contents( $temp, $payload ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $written || strlen( $payload ) !== $written ) {
			@unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error( 'manifest_write', __( 'Nije moguće zapisati JSON manifest cjenika.', 'sidrena' ) );
		}
		if ( ! rename( $temp, $paths['manifest'] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			@unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error( 'manifest_commit', __( 'Nije moguće atomski objaviti JSON manifest cjenika.', 'sidrena' ) );
		}
		return true;
	}
}
