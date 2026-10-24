<?php
/**
 * Overview dashboard.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;


$sidrena_woo_active = Brendigo_Sidrena_Cijena_Plugin::woocommerce_active();
$sidrena_outlets    = Brendigo_Sidrena_Cijena_Poslovnice::instance()->all();
$sidrena_xml_meta   = $sidrena_export->get_public_meta();
$sidrena_generated  = ! empty( $sidrena_xml_meta['generated'] ) ? (int) $sidrena_xml_meta['generated'] : 0;
$sidrena_item_count = isset( $sidrena_xml_meta['count'] ) ? (int) $sidrena_xml_meta['count'] : 0;
$sidrena_automation_state = Brendigo_Sidrena_Cijena_Automation::instance()->get_pending_state();
$sidrena_pending_since    = isset( $sidrena_automation_state['since'] ) ? (int) $sidrena_automation_state['since'] : 0;
$sidrena_daily_enabled    = 'yes' === $sidrena_settings->get( 'daily_xml' );

$sidrena_overview = array(
	'count'      => 0,
	'preview_id' => 0,
);

if ( $sidrena_woo_active ) {
	$sidrena_overview = Brendigo_Sidrena_Cijena_Product_Meta::instance()->overview_metrics();
	$sidrena_count    = (int) $sidrena_overview['count'];
} else {
	$sidrena_count = count( Brendigo_Sidrena_Cijena_Catalog::instance()->all() );
}

if ( $sidrena_item_count < 1 ) {
	$sidrena_item_count = $sidrena_count;
}

$sidrena_preview_name    = '';
$sidrena_preview_image   = '';
$sidrena_preview_current = '';
$sidrena_preview_anchor  = '';
$sidrena_preview_date    = Brendigo_Sidrena_Cijena_Settings::REFERENCE_DATE_GENERAL;

if ( $sidrena_woo_active ) {
	$sidrena_preview_id = isset( $sidrena_overview['preview_id'] ) ? absint( $sidrena_overview['preview_id'] ) : 0;

	if ( $sidrena_preview_id && function_exists( 'wc_get_product' ) ) {
		$sidrena_preview_product = wc_get_product( $sidrena_preview_id );
		if ( $sidrena_preview_product instanceof WC_Product ) {
			$sidrena_meta            = Brendigo_Sidrena_Cijena_Product_Meta::instance();
			$sidrena_preview_name    = $sidrena_preview_product->get_name();
			$sidrena_preview_image   = wp_get_attachment_image_url( $sidrena_preview_product->get_image_id(), 'woocommerce_thumbnail' );
			$sidrena_preview_current = wc_price( (float) $sidrena_preview_product->get_price() );
			$sidrena_anchor_display  = $sidrena_meta->get_price_to_display( $sidrena_preview_product );
			$sidrena_preview_anchor  = '' !== (string) $sidrena_anchor_display ? wc_price( (float) $sidrena_anchor_display ) : '';
			$sidrena_preview_date    = $sidrena_meta->get_display_date( $sidrena_preview_id );
		}
	}
} else {
	$sidrena_catalog_rows = Brendigo_Sidrena_Cijena_Catalog::instance()->all();
	if ( ! empty( $sidrena_catalog_rows[0] ) && is_array( $sidrena_catalog_rows[0] ) ) {
		$sidrena_row             = $sidrena_catalog_rows[0];
		$sidrena_preview_name    = isset( $sidrena_row['naziv'] ) ? (string) $sidrena_row['naziv'] : '';
		$sidrena_preview_current = isset( $sidrena_row['cijena_redovna'] ) ? esc_html( $sidrena_row['cijena_redovna'] ) . ' €' : '';
		$sidrena_preview_anchor  = isset( $sidrena_row['sidrena_cijena'] ) ? esc_html( $sidrena_row['sidrena_cijena'] ) . ' €' : '';
		$sidrena_preview_date    = Brendigo_Sidrena_Cijena_Catalog::instance()->display_date( $sidrena_row );
	}
}

$sidrena_last_generated = $sidrena_generated
	? wp_date( get_option( 'date_format' ) . ' · ' . get_option( 'time_format' ), $sidrena_generated )
	: __( 'Još nije generirano', 'brendigo-sidrena-cijena' );

if ( $sidrena_daily_enabled ) {
	/* translators: %s: configured daily publication time. */
	$sidrena_daily_status_text = sprintf( __( 'Uključeno · %s', 'brendigo-sidrena-cijena' ), $sidrena_settings->daily_time() );
} else {
	$sidrena_daily_status_text = __( 'Isključeno', 'brendigo-sidrena-cijena' );
}

if ( $sidrena_pending_since > 0 ) {
	$sidrena_pending_status_title = __( 'Neobjavljene promjene', 'brendigo-sidrena-cijena' );
	/* translators: %s: datetime when the first unpublished change was saved. */
	$sidrena_pending_status_text = sprintf( __( 'Čekaju od %s', 'brendigo-sidrena-cijena' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sidrena_pending_since ) );
} else {
	$sidrena_pending_status_title = __( 'Objava promjena', 'brendigo-sidrena-cijena' );
	$sidrena_pending_status_text  = __( 'Sve spremljene promjene su objavljene.', 'brendigo-sidrena-cijena' );
}
?>
<section class="brendigo-overview-legal">
	<div class="brendigo-overview-legal__copy">
		<span class="brendigo-overview-icon brendigo-overview-icon--shield"><span class="dashicons dashicons-shield" aria-hidden="true"></span></span>
		<div>
			<p class="brendigo-section-kicker"><?php echo esc_html__( 'Pregled podataka', 'brendigo-sidrena-cijena' ); ?></p>
			<h2><?php echo esc_html__( 'Sidrene cijene i datumi na jednom mjestu', 'brendigo-sidrena-cijena' ); ?></h2>
			<p><?php echo esc_html__( 'Uredite cijenu koja je vrijedila na odgovarajući datum, a javni cjenik i arhiva generiraju se iz spremljenih podataka.', 'brendigo-sidrena-cijena' ); ?></p>
		</div>
	</div>
	<div class="brendigo-reference-dates">
		<p class="brendigo-reference-dates__title"><?php echo esc_html__( 'Referentni datumi', 'brendigo-sidrena-cijena' ); ?></p>
		<div class="brendigo-reference-date"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><div><strong><?php echo esc_html( Brendigo_Sidrena_Cijena_Settings::REFERENCE_DATE_GENERAL ); ?></strong><small><?php echo esc_html__( 'ostali proizvodi i usluge', 'brendigo-sidrena-cijena' ); ?></small></div></div>
		<div class="brendigo-reference-date"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><div><strong><?php echo esc_html( Brendigo_Sidrena_Cijena_Settings::REFERENCE_DATE_FMCG ); ?></strong><small><?php echo esc_html__( 'hrana, piće, kozmetika i kućanske potrepštine', 'brendigo-sidrena-cijena' ); ?></small></div></div>
	</div>
</section>

<section class="sidrena-cijena-card brendigo-dashboard-card" aria-labelledby="sidrena-legal-2026-title">
    <div class="sidrena-cijena-card__head brendigo-card-title">
        <span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
        <div>
            <h2 id="sidrena-legal-2026-title"><?php echo esc_html__( 'Propisi o sidrenim cijenama — 2026.', 'brendigo-sidrena-cijena' ); ?></h2>
            <p><?php echo esc_html__( 'Narodne novine 101/2026, izmjene 110/2026. Proširene obveze primjenjuju se od 17. studenoga 2026.', 'brendigo-sidrena-cijena' ); ?></p>
        </div>
    </div>
    <p><?php echo esc_html__( 'Za ranije obuhvaćenu robu provjerite stvarnu cijenu na dan 2. svibnja 2025., a za ostalu robu i usluge na dan 10. rujna 2026. Sidrene cijene ne smiju se zaključivati iz sadašnje cijene.', 'brendigo-sidrena-cijena' ); ?></p>
    <p><?php echo esc_html__( 'Provjerite dnevnu objavu proizvoda najkasnije do 8:00, primjenjivi rok za objavu promjene cijene usluge, potpunost CSV/XML podataka po poslovnici i javnu arhivu najmanje 30 dana.', 'brendigo-sidrena-cijena' ); ?></p>
    <p><?php echo esc_html__( 'Prijedlozi novih pravilnika iz listopada 2026. još nisu konačan propis. Za svaku stavku i djelatnost provjerite obveze te izvorne dokaze.', 'brendigo-sidrena-cijena' ); ?></p>
    <p><a href="<?php echo esc_url( 'https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Odluka NN 101/2026', 'brendigo-sidrena-cijena' ); ?></a>
    &nbsp;·&nbsp; <a href="<?php echo esc_url( 'https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_110_1309.html' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Izmjena NN 110/2026', 'brendigo-sidrena-cijena' ); ?></a></p>
</section>

<section class="brendigo-kpi-grid" aria-label="<?php echo esc_attr__( 'Sažetak', 'brendigo-sidrena-cijena' ); ?>">
	<article class="brendigo-kpi-card">
		<span class="dashicons dashicons-products" aria-hidden="true"></span>
		<div><strong><?php echo esc_html( number_format_i18n( $sidrena_count ) ); ?></strong><span><?php echo esc_html__( 'stavki sa sidrenom cijenom', 'brendigo-sidrena-cijena' ); ?></span></div>
	</article>
	<article class="brendigo-kpi-card">
		<span class="dashicons dashicons-media-code" aria-hidden="true"></span>
		<div><strong><?php echo $sidrena_settings->is_public_cjenik_enabled() ? esc_html__( 'Aktivan', 'brendigo-sidrena-cijena' ) : esc_html__( 'Isključen', 'brendigo-sidrena-cijena' ); ?></strong><span><?php echo esc_html__( 'javni cjenik', 'brendigo-sidrena-cijena' ); ?></span></div>
	</article>
	<article class="brendigo-kpi-card">
		<span class="dashicons dashicons-store" aria-hidden="true"></span>
		<div><strong><?php echo esc_html( number_format_i18n( count( $sidrena_outlets ) ) ); ?></strong><span><?php echo esc_html__( 'dodatnih poslovnica', 'brendigo-sidrena-cijena' ); ?></span></div>
	</article>
	<article class="brendigo-kpi-card">
		<span class="dashicons dashicons-clock" aria-hidden="true"></span>
		<div><strong class="brendigo-kpi-card__date"><?php echo esc_html( $sidrena_last_generated ); ?></strong><span><?php echo esc_html__( 'zadnje generiranje', 'brendigo-sidrena-cijena' ); ?></span></div>
	</article>
</section>

<section class="brendigo-dashboard-grid">
	<article class="sidrena-cijena-card brendigo-dashboard-card">
		<div class="sidrena-cijena-card__head brendigo-card-title"><span class="dashicons dashicons-controls-forward" aria-hidden="true"></span><div><h2><?php echo esc_html__( 'Brze radnje', 'brendigo-sidrena-cijena' ); ?></h2><p><?php echo esc_html__( 'Najčešće administrativne radnje na jednom mjestu.', 'brendigo-sidrena-cijena' ); ?></p></div></div>
		<div class="brendigo-quick-actions">
			<a class="brendigo-quick-action brendigo-quick-action--primary" href="<?php echo esc_url( $this->get_settings_tab_url( 'proizvodi' ) ); ?>"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><span><?php echo esc_html__( 'Dodaj ili uredi stavku', 'brendigo-sidrena-cijena' ); ?></span><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a>
			<a class="brendigo-quick-action" href="<?php echo esc_url( $sidrena_export->get_cjenik_url() ); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-external" aria-hidden="true"></span><span><?php echo esc_html__( 'Otvori javni cjenik', 'brendigo-sidrena-cijena' ); ?></span><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a>
			<a class="brendigo-quick-action" href="<?php echo esc_url( $this->get_settings_tab_url( 'javni-xml' ) ); ?>"><span class="dashicons dashicons-update" aria-hidden="true"></span><span><?php echo esc_html__( 'Objava javnog cjenika', 'brendigo-sidrena-cijena' ); ?></span><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a>
			<a class="brendigo-quick-action" href="<?php echo esc_url( $this->get_settings_tab_url( 'upute' ) ); ?>"><span class="dashicons dashicons-book-alt" aria-hidden="true"></span><span><?php echo esc_html__( 'Upute korak po korak', 'brendigo-sidrena-cijena' ); ?></span><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a>
			<a class="brendigo-quick-action" href="<?php echo esc_url( $this->get_settings_tab_url( 'postavke' ) ); ?>"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><span><?php echo esc_html__( 'Uredi postavke', 'brendigo-sidrena-cijena' ); ?></span><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a>
		</div>
	</article>

	<article class="sidrena-cijena-card brendigo-dashboard-card">
		<div class="sidrena-cijena-card__head brendigo-card-title"><span class="dashicons dashicons-database" aria-hidden="true"></span><div><h2><?php echo esc_html__( 'Status sustava', 'brendigo-sidrena-cijena' ); ?></h2><p><?php echo esc_html__( 'Stanje cjenika, arhive i automatizacije.', 'brendigo-sidrena-cijena' ); ?></p></div></div>
		<ul class="brendigo-status-list">
			<li><span class="brendigo-status-dot is-ok"></span><div><strong><?php echo esc_html( strtoupper( $sidrena_settings->export_format() ) . ' ' . __( 'javni cjenik', 'brendigo-sidrena-cijena' ) ); ?></strong><small><?php echo esc_html( $sidrena_export->get_public_url() ); ?></small></div></li>
			<li><span class="brendigo-status-dot is-ok"></span><div><strong><?php echo esc_html__( 'HTML cjenik', 'brendigo-sidrena-cijena' ); ?></strong><small><?php echo esc_html( $sidrena_export->get_cjenik_url() ); ?></small></div></li>
			<li><span class="brendigo-status-dot <?php echo $sidrena_daily_enabled ? 'is-ok' : 'is-muted'; ?>"></span><div><strong><?php echo esc_html__( 'Dnevna objava', 'brendigo-sidrena-cijena' ); ?></strong><small><?php echo esc_html( $sidrena_daily_status_text ); ?></small></div></li>
			<li><span class="brendigo-status-dot <?php echo $sidrena_pending_since > 0 ? 'is-warn' : 'is-ok'; ?>"></span><div><strong><?php echo esc_html( $sidrena_pending_status_title ); ?></strong><small><?php echo esc_html( $sidrena_pending_status_text ); ?></small></div></li>
			<li><span class="brendigo-status-dot is-ok"></span><div><strong><?php echo esc_html__( 'Arhiva', 'brendigo-sidrena-cijena' ); ?></strong><small><?php echo esc_html__( 'Dostupna kroz javni pregled arhive.', 'brendigo-sidrena-cijena' ); ?></small></div></li>
		</ul>
	</article>

	<article class="sidrena-cijena-card brendigo-dashboard-card brendigo-product-preview">
		<div class="sidrena-cijena-card__head brendigo-card-title"><span class="dashicons dashicons-visibility" aria-hidden="true"></span><div><h2><?php echo esc_html__( 'Prikaz na proizvodu', 'brendigo-sidrena-cijena' ); ?></h2><p><?php echo esc_html__( 'Pregled kako će spremljena sidrena cijena izgledati uz proizvod.', 'brendigo-sidrena-cijena' ); ?></p></div></div>
		<?php if ( '' !== $sidrena_preview_name ) : ?>
			<div class="brendigo-product-preview__body">
				<div class="brendigo-product-preview__media">
					<?php if ( $sidrena_preview_image ) : ?><img src="<?php echo esc_url( $sidrena_preview_image ); ?>" alt="" loading="lazy" /><?php else : ?><span class="dashicons dashicons-products" aria-hidden="true"></span><?php endif; ?>
				</div>
				<div class="brendigo-product-preview__content">
					<h3><?php echo esc_html( $sidrena_preview_name ); ?></h3>
					<div class="brendigo-preview-price-row"><span><?php echo esc_html__( 'Važeća cijena', 'brendigo-sidrena-cijena' ); ?></span><strong><?php echo wp_kses_post( $sidrena_preview_current ); ?></strong></div>
					<?php
					/* translators: %s: reference date. */
					$sidrena_preview_anchor_label = sprintf( __( 'Sidrena cijena (%s)', 'brendigo-sidrena-cijena' ), $sidrena_preview_date );
					?>
					<div class="brendigo-preview-price-row"><span><?php echo esc_html( $sidrena_preview_anchor_label ); ?></span><strong><?php echo wp_kses_post( $sidrena_preview_anchor ); ?></strong></div>
					<div class="brendigo-preview-note"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><span><?php echo esc_html__( 'Prikaz koristi stvarne podatke proizvoda i spremljeni datum sidrene cijene.', 'brendigo-sidrena-cijena' ); ?></span></div>
				</div>
			</div>
		<?php else : ?>
			<div class="brendigo-empty-state"><span class="dashicons dashicons-products" aria-hidden="true"></span><p><?php echo esc_html__( 'Dodajte sidrenu cijenu proizvodu kako bi se ovdje prikazao stvarni pregled.', 'brendigo-sidrena-cijena' ); ?></p></div>
		<?php endif; ?>
	</article>
</section>

<section class="sidrena-cijena-card brendigo-overview-footer">
	<div><span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span><strong><?php echo esc_html( number_format_i18n( $sidrena_item_count ) ); ?></strong><span><?php echo esc_html__( 'stavki u zadnjem javnom cjeniku', 'brendigo-sidrena-cijena' ); ?></span></div>
	<div><span class="dashicons dashicons-lock" aria-hidden="true"></span><strong><?php echo esc_html__( 'Zaključano', 'brendigo-sidrena-cijena' ); ?></strong><span><?php echo esc_html__( 'globalni referentni datumi', 'brendigo-sidrena-cijena' ); ?></span></div>
	<div><span class="dashicons dashicons-shield-alt" aria-hidden="true"></span><strong><?php echo esc_html__( 'Automatski', 'brendigo-sidrena-cijena' ); ?></strong><span><?php echo esc_html__( 'javni cjenik i arhiva iz spremljenih podataka', 'brendigo-sidrena-cijena' ); ?></span></div>
</section>
