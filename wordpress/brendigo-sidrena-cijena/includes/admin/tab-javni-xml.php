<?php
/**
 * Public pricelist settings.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

$sidrena_export->maybe_ensure_public_xml();

$sidrena_settings      = Brendigo_Sidrena_Cijena_Settings::instance();
$sidrena_format        = $sidrena_settings->export_format();
$sidrena_open_label    = 'csv' === $sidrena_format ? __( 'Otvori CSV', 'brendigo-sidrena-cijena' ) : __( 'Otvori XML', 'brendigo-sidrena-cijena' );
$sidrena_xml_url       = $sidrena_export->get_public_url();
$sidrena_cjenik_url    = $sidrena_export->get_cjenik_url();
$sidrena_archive_url   = $sidrena_export->get_archive_url();
$sidrena_xml_meta      = $sidrena_export->get_public_meta();
$sidrena_next_daily    = $sidrena_export->get_next_daily_timestamp();
$sidrena_daily_time    = $sidrena_settings->daily_time();
$sidrena_site_timezone = wp_timezone_string();
$sidrena_generated_at  = ! empty( $sidrena_xml_meta['generated'] ) ? (int) $sidrena_xml_meta['generated'] : 0;
$sidrena_daily_enabled = 'yes' === $sidrena_settings->get( 'daily_xml' );
$sidrena_last_slot     = $sidrena_daily_enabled ? $sidrena_export->get_previous_daily_timestamp() : 0;
$sidrena_missed_slot   = $sidrena_daily_enabled && $sidrena_last_slot > 0 && time() >= ( $sidrena_last_slot + Brendigo_Sidrena_Cijena_Automation::DAILY_GRACE ) && $sidrena_generated_at < $sidrena_last_slot;
$sidrena_pending        = Brendigo_Sidrena_Cijena_Automation::instance()->get_pending_state();
$sidrena_pending_since  = isset( $sidrena_pending['since'] ) ? (int) $sidrena_pending['since'] : 0;
$sidrena_pending_count  = isset( $sidrena_pending['reasons'] ) && is_array( $sidrena_pending['reasons'] ) ? count( $sidrena_pending['reasons'] ) : 0;
$sidrena_auto_rebuild   = 'yes' === $sidrena_settings->get( 'auto_rebuild', 'yes' );
$sidrena_history        = $sidrena_export->get_publication_history();
?>
<section class="sidrena-cijena-card sidrena-cijena-feeds">
	<div class="sidrena-cijena-card__head">
		<h2><?php echo esc_html__( 'Javni cjenik', 'brendigo-sidrena-cijena' ); ?></h2>
		<p><?php echo esc_html__( 'U zakazano vrijeme zajedno se objavljuju javna HTML stranica, XML/CSV datoteka i nova verzija arhive. Vrijeme objave možete postaviti za svaki dan.', 'brendigo-sidrena-cijena' ); ?></p>
	</div>

	<div class="sidrena-cijena-feeds__grid">
		<?php
		$this->render_url_box(
			$sidrena_settings->is_multi_poslovnica() ? ( Brendigo_Sidrena_Cijena_Plugin::woocommerce_active() ? __( 'Webshop', 'brendigo-sidrena-cijena' ) : __( 'Glavni cjenik', 'brendigo-sidrena-cijena' ) ) : __( 'XML/CSV cjenik', 'brendigo-sidrena-cijena' ),
			$sidrena_xml_url,
			$sidrena_xml_url,
			$sidrena_open_label,
			'button button-primary',
			'dashicons-external',
			'sidrena-cijena-public-url'
		);

		$this->render_url_box(
			__( 'Javna stranica cjenika', 'brendigo-sidrena-cijena' ),
			$sidrena_cjenik_url,
			$sidrena_cjenik_url,
			__( 'Otvori javni cjenik', 'brendigo-sidrena-cijena' ),
			'button button-secondary',
			'dashicons-external',
			'sidrena-cijena-cjenik-url'
		);

		$this->render_url_box(
			__( 'Arhiva cjenika', 'brendigo-sidrena-cijena' ),
			$sidrena_archive_url,
			$sidrena_archive_url,
			__( 'Otvori arhivu', 'brendigo-sidrena-cijena' ),
			'button button-secondary',
			'dashicons-archive',
			'sidrena-cijena-archive-url'
		);

		$sidrena_feeds = Brendigo_Sidrena_Cijena_Feed_Files::instance();
		foreach ( array( 'proizvodi' => __( 'Proizvodi', 'brendigo-sidrena-cijena' ), 'usluge' => __( 'Usluge', 'brendigo-sidrena-cijena' ) ) as $sidrena_kind => $sidrena_kind_label ) {
			foreach ( array( 'xml', 'csv' ) as $sidrena_extension ) {
				$sidrena_url = $sidrena_feeds->get_type_public_url( $sidrena_kind, $sidrena_extension );
				$this->render_url_box( $sidrena_kind_label . ' – ' . strtoupper( $sidrena_extension ), $sidrena_url, $sidrena_url, __( 'Otvori', 'brendigo-sidrena-cijena' ) );
			}
		}
		$this->render_url_box( __( 'Javni REST API', 'brendigo-sidrena-cijena' ), rest_url( 'brendigo-sidrena/v1/cjenici' ), rest_url( 'brendigo-sidrena/v1/cjenici' ), __( 'Otvori API', 'brendigo-sidrena-cijena' ) );

		if ( $sidrena_settings->is_multi_poslovnica() ) {
			foreach ( Brendigo_Sidrena_Cijena_Poslovnice::instance()->all() as $sidrena_outlet ) {
				$sidrena_outlet_url  = $sidrena_export->get_public_url( '', $sidrena_outlet['oznaka'] );
				$sidrena_outlet_name = '' !== $sidrena_outlet['naziv'] ? $sidrena_outlet['naziv'] : $sidrena_outlet['oznaka'];
				$this->render_url_box( $sidrena_outlet_name, $sidrena_outlet_url, $sidrena_outlet_url, $sidrena_open_label );
			}
		}
		?>
	</div>

	<?php if ( $sidrena_generated_at > 0 ) : ?>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: datetime, 2: item count */
					__( 'Zadnja objava: %1$s · %2$d stavki.', 'brendigo-sidrena-cijena' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sidrena_generated_at ),
					isset( $sidrena_xml_meta['count'] ) ? (int) $sidrena_xml_meta['count'] : 0
				)
			);
			?>
		</p>
	<?php else : ?>
		<p class="description"><?php echo esc_html__( 'Cjenik još nije objavljen. Možete ga objaviti odmah ili pričekati prvi zakazani termin.', 'brendigo-sidrena-cijena' ); ?></p>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( $this->get_settings_tab_url( 'javni-xml' ) ); ?>">
		<?php wp_nonce_field( 'sidrena_cijena_save_xml', 'sidrena_cijena_xml_nonce' ); ?>

		<section class="sidrena-cijena-automation sidrena-cijena-publication-card">
			<h3><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><?php echo esc_html__( 'Dnevna objava', 'brendigo-sidrena-cijena' ); ?></h3>
			<p>
				<?php
				$this->render_toggle(
					'sidrena_cijena_daily_xml',
					'sidrena_cijena_daily_xml',
					$sidrena_daily_enabled,
					__( 'Objavi cjenik automatski svaki dan', 'brendigo-sidrena-cijena' )
				);
				?>
			</p>

			<div class="sidrena-cijena-publication-schedule">
				<label for="sidrena_cijena_daily_time">
					<span class="sidrena-cijena-publication-schedule__label"><?php echo esc_html__( 'Vrijeme objave', 'brendigo-sidrena-cijena' ); ?></span>
					<input type="time" id="sidrena_cijena_daily_time" name="sidrena_cijena_daily_time" value="<?php echo esc_attr( $sidrena_daily_time ); ?>" step="60" />
				</label>
				<div class="sidrena-cijena-publication-schedule__meta">
					<strong><?php echo esc_html( $sidrena_site_timezone ); ?></strong>
					<span><?php echo esc_html__( 'Nove instalacije koriste 07:00. Vrijeme možete promijeniti, a raspored ostaje vezan uz vremensku zonu stranice.', 'brendigo-sidrena-cijena' ); ?></span>
				</div>
			</div>

			<?php if ( $sidrena_next_daily && $sidrena_daily_enabled ) : ?>
				<div class="brendigo-info-box">
					<span class="dashicons dashicons-clock" aria-hidden="true"></span>
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: datetime */
								__( 'Sljedeća automatska objava: %s.', 'brendigo-sidrena-cijena' ),
								wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sidrena_next_daily )
							)
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( $sidrena_pending_since > 0 ) : ?>
				<?php
				$sidrena_pending_mode = $sidrena_auto_rebuild ? 'queued' : ( $sidrena_daily_enabled ? 'scheduled' : 'manual' );
				$sidrena_pending_title = $sidrena_auto_rebuild
					? __( 'Objava je u redu čekanja', 'brendigo-sidrena-cijena' )
					: ( $sidrena_daily_enabled ? __( 'Promjene čekaju sljedeću objavu', 'brendigo-sidrena-cijena' ) : __( 'Promjene još nisu objavljene', 'brendigo-sidrena-cijena' ) );
				$sidrena_pending_icon = 'queued' === $sidrena_pending_mode ? 'dashicons-update' : ( 'scheduled' === $sidrena_pending_mode ? 'dashicons-clock' : 'dashicons-warning' );
				?>
				<div class="sidrena-cijena-publication-state is-<?php echo esc_attr( $sidrena_pending_mode ); ?>" role="status">
					<span class="dashicons <?php echo esc_attr( $sidrena_pending_icon ); ?>" aria-hidden="true"></span>
					<div>
						<strong><?php echo esc_html( $sidrena_pending_title ); ?></strong>
						<p>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: number of change groups, 2: datetime when changes first became pending */
									__( '%1$d skupina promjena čeka objavu od %2$s.', 'brendigo-sidrena-cijena' ),
									max( 1, $sidrena_pending_count ),
									wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sidrena_pending_since )
								)
							);
							?>
						</p>
						<?php if ( 'manual' === $sidrena_pending_mode ) : ?>
							<p><?php echo esc_html__( 'Uključite dnevnu objavu ili kliknite „Generiraj javni cjenik” kada želite objaviti spremljene promjene.', 'brendigo-sidrena-cijena' ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $sidrena_missed_slot ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html__( 'Planirana objava nije završena u očekivanom terminu. Automatska provjera će ponovno pokušati objaviti cjenik.', 'brendigo-sidrena-cijena' ); ?></p></div>
			<?php endif; ?>

			<p class="description"><?php echo esc_html__( 'Za objavu i bez pokretanja WP-Crona pri posjetima, postavite hosting cron svake minute: wp cron event run --due-now --path=/putanja/do/wordpressa. Alternativno pokrenite wp sidrena-cijena regenerate prema rasporedu. Bez posjeta ili vanjskog raspoređivača nema zajamčenog vremena objave.', 'brendigo-sidrena-cijena' ); ?></p>
			<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON && $sidrena_daily_enabled ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html__( 'Automatsko izvršavanje WordPress zadataka isključeno je na ovoj instalaciji. Na hostingu mora biti uključeno pokretanje zakazanih zadataka kako bi se dnevna objava izvršila.', 'brendigo-sidrena-cijena' ); ?></p></div>
			<?php endif; ?>
		</section>

		<section class="sidrena-cijena-automation">
			<h3><span class="dashicons dashicons-update" aria-hidden="true"></span><?php echo esc_html__( 'Automatsko osvježavanje', 'brendigo-sidrena-cijena' ); ?></h3>
			<p>
				<?php
				$this->render_toggle(
					'sidrena_cijena_auto_rebuild',
					'sidrena_cijena_auto_rebuild',
					$sidrena_auto_rebuild,
					__( 'Osvježi javni cjenik i nakon promjene proizvoda', 'brendigo-sidrena-cijena' )
				);
				?>
			</p>
			<p class="description"><?php echo esc_html__( 'Kada je uključeno, promjene proizvoda, cijena, zalihe, barkoda i marke automatski osvježavaju javni cjenik. Ako želite objavu samo jednom dnevno, primjerice u 07:00, isključite ovu opciju i ostavite uključenu Dnevnu objavu.', 'brendigo-sidrena-cijena' ); ?></p>

			<p>
				<?php
				$this->render_toggle(
					'sidrena_cijena_auto_maintenance',
					'sidrena_cijena_auto_maintenance',
					'yes' === $sidrena_settings->get( 'auto_maintenance', 'yes' ),
					__( 'Automatski provjeri raspored i ponovi neuspjelu objavu', 'brendigo-sidrena-cijena' )
				);
				?>
			</p>
			<p class="description"><?php echo esc_html__( 'Preporučeno je ostaviti uključeno kako bi se propuštena ili neuspjela objava automatski pokušala dovršiti.', 'brendigo-sidrena-cijena' ); ?></p>
		</section>

		<section class="sidrena-cijena-automation">
			<h3><?php echo esc_html__( 'E-mail upozorenja', 'brendigo-sidrena-cijena' ); ?></h3>
			<p><?php $this->render_toggle( 'sidrena_cijena_email_alerts', 'sidrena_cijena_email_alerts', 'yes' === $sidrena_settings->get( 'email_alerts', 'yes' ), __( 'Pošalji upozorenje ako objava zakaže ili nije izvršena', 'brendigo-sidrena-cijena' ) ); ?></p>
			<p><label for="sidrena_cijena_alert_email"><?php echo esc_html__( 'Adresa primatelja (prazno = administrator stranice)', 'brendigo-sidrena-cijena' ); ?></label><br />
			<input class="regular-text" type="email" id="sidrena_cijena_alert_email" name="sidrena_cijena_alert_email" value="<?php echo esc_attr( $sidrena_settings->get( 'alert_email', '' ) ); ?>" autocomplete="email" /></p>
			<p class="description"><?php echo esc_html__( 'Ponavljajuća upozorenja istog uzroka ograničena su na najviše jedno u 12 sati. Dostava zahtijeva ispravan WordPress sustav slanja pošte.', 'brendigo-sidrena-cijena' ); ?></p>
		</section>

		<section class="sidrena-cijena-automation">
			<h3><span class="dashicons dashicons-media-spreadsheet aria-hidden="true"></span><?php echo esc_html__( 'Format datoteke', 'brendigo-sidrena-cijena' ); ?></h3>
			<fieldset class="sidrena-cijena-choices">
				<label class="sidrena-cijena-choice"><input type="radio" name="sidrena_cijena_public_format" value="xml" <?php checked( $sidrena_format, 'xml' ); ?> /><span>XML</span></label>
				<label class="sidrena-cijena-choice"><input type="radio" name="sidrena_cijena_public_format" value="csv" <?php checked( $sidrena_format, 'csv' ); ?> /><span>CSV</span></label>
			</fieldset>
			<p class="description"><?php echo esc_html__( 'Promjena formata vrijedi za nova objavljivanja. Postojeće datoteke u arhivi ostaju nepromijenjene.', 'brendigo-sidrena-cijena' ); ?></p>
		</section>

		<p class="sidrena-cijena-actions">
			<button type="submit" class="button button-secondary" name="sidrena_cijena_action" value="save_xml_settings">
				<?php echo $this->save_icon_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
				<?php echo esc_html__( 'Spremi', 'brendigo-sidrena-cijena' ); ?>
			</button>
			<button type="submit" class="button button-primary" name="sidrena_cijena_action" value="regenerate_public_xml">
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php echo esc_html__( 'Objavi cjenik sada', 'brendigo-sidrena-cijena' ); ?>
			</button>
		</p>
	</form>


	<?php if ( ! empty( $sidrena_history ) ) : ?>
		<section class="sidrena-cijena-publication-history" aria-labelledby="sidrena-publication-history-title">
			<div class="sidrena-cijena-publication-history__head">
				<div>
					<h3 id="sidrena-publication-history-title"><?php echo esc_html__( 'Nedavne objave', 'brendigo-sidrena-cijena' ); ?></h3>
					<p><?php echo esc_html__( 'Kratka evidencija zadnjih pokušaja objave pomaže brzo uočiti propuštenu ili neuspjelu automatizaciju.', 'brendigo-sidrena-cijena' ); ?></p>
				</div>
				<span class="brendigo-admin-chip"><?php echo esc_html( number_format_i18n( count( $sidrena_history ) ) ); ?></span>
			</div>
			<div class="sidrena-cijena-publication-history__list">
				<?php foreach ( array_slice( $sidrena_history, 0, 6 ) as $sidrena_entry ) : ?>
					<?php
					$sidrena_entry_ok = isset( $sidrena_entry['status'] ) && 'success' === $sidrena_entry['status'];
					$sidrena_source   = isset( $sidrena_entry['source'] ) ? (string) $sidrena_entry['source'] : '';
					$sidrena_source_labels = array(
						'daily'  => __( 'Dnevna', 'brendigo-sidrena-cijena' ),
						'queued' => __( 'Automatska', 'brendigo-sidrena-cijena' ),
						'manual' => __( 'Ručna', 'brendigo-sidrena-cijena' ),
						'cli'    => __( 'WP-CLI', 'brendigo-sidrena-cijena' ),
					);
					$sidrena_source_label = isset( $sidrena_source_labels[ $sidrena_source ] ) ? $sidrena_source_labels[ $sidrena_source ] : __( 'Objava', 'brendigo-sidrena-cijena' );
					$sidrena_entry_time = isset( $sidrena_entry['time'] ) ? (int) $sidrena_entry['time'] : 0;
					$sidrena_entry_count    = isset( $sidrena_entry['count'] ) ? (int) $sidrena_entry['count'] : 0;
					$sidrena_entry_format   = isset( $sidrena_entry['format'] ) ? strtoupper( (string) $sidrena_entry['format'] ) : '';
					$sidrena_entry_duration = isset( $sidrena_entry['duration_ms'] ) ? max( 0, (int) $sidrena_entry['duration_ms'] ) : 0;
					?>
					<div class="sidrena-cijena-publication-history__item <?php echo esc_attr( $sidrena_entry_ok ? 'is-success' : 'is-failed' ); ?>">
						<span class="dashicons <?php echo esc_attr( $sidrena_entry_ok ? 'dashicons-yes-alt' : 'dashicons-warning' ); ?>" aria-hidden="true"></span>
						<div>
							<strong><?php echo esc_html( $sidrena_source_label ); ?> · <?php echo esc_html( $sidrena_entry_ok ? __( 'uspješno', 'brendigo-sidrena-cijena' ) : __( 'neuspješno', 'brendigo-sidrena-cijena' ) ); ?></strong>
							<span>
								<?php echo esc_html( $sidrena_entry_time > 0 ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sidrena_entry_time ) : '—' ); ?>
								<?php if ( $sidrena_entry_ok ) : ?>
									· <?php echo esc_html( number_format_i18n( $sidrena_entry_count ) ); ?> <?php echo esc_html__( 'stavki', 'brendigo-sidrena-cijena' ); ?><?php echo '' !== $sidrena_entry_format ? ' · ' . esc_html( $sidrena_entry_format ) : ''; ?><?php echo $sidrena_entry_duration > 0 ? ' · ' . esc_html( number_format_i18n( $sidrena_entry_duration ) ) . ' ms' : ''; ?>
								<?php elseif ( ! empty( $sidrena_entry['error'] ) ) : ?>
									· <code><?php echo esc_html( (string) $sidrena_entry['error'] ); ?></code>
								<?php endif; ?>
							</span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<div class="sidrena-cijena-feeds__grid sidrena-cijena-shortcodes">
		<?php
		$this->render_archive_shortcode_docs();
		$this->render_cjenik_shortcode_docs( $sidrena_settings );
		?>
	</div>
</section>
