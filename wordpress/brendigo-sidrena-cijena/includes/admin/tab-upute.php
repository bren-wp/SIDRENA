<?php
/**
 * Beginner-friendly administrator guide for standalone WordPress and WooCommerce.
 *
 * The guide lives inside the admin UI, so no bundled markdown file is needed.
 * It only links to existing screens and never changes site data.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

$sidrena_help_woo = Brendigo_Sidrena_Cijena_Plugin::woocommerce_active();
$sidrena_help_urls = array(
	'pregled'    => $this->get_settings_tab_url( 'pregled' ),
	'proizvodi'  => $this->get_settings_tab_url( 'proizvodi' ),
	'postavke'   => $this->get_settings_tab_url( 'postavke' ),
	'uvoz-izvoz' => $this->get_settings_tab_url( 'uvoz-izvoz' ),
	'javni-xml'  => $this->get_settings_tab_url( 'javni-xml' ),
	'poslovnice' => $this->get_settings_tab_url( 'poslovnice' ),
	'wp-settings' => admin_url( 'options-general.php' ),
	'wp-pages'   => admin_url( 'edit.php?post_type=page' ),
	'wp-plugins' => admin_url( 'plugins.php' ),
	'woo-products' => admin_url( 'edit.php?post_type=product' ),
);

$sidrena_help_common = array(
	array(
		'title' => __( 'Prije prvog korištenja', 'brendigo-sidrena-cijena' ),
		'steps' => array(
			array( __( 'Za novu instalaciju otvorite Dodaci → Dodaj novi → Prenesi dodatak, odaberite SIDRENA ZIP i aktivirajte ga. Pri nadogradnji postojećeg dodatka prvo napravite sigurnosnu kopiju.', 'brendigo-sidrena-cijena' ), 'wp-plugins', __( 'Otvori dodatke', 'brendigo-sidrena-cijena' ) ),
			array( __( 'Napravite sigurnosnu kopiju stranice i baze podataka, posebno ako prelazite sa starije verzije dodatka.', 'brendigo-sidrena-cijena' ) ),
			array( __( 'U WordPressu otvorite Postavke → Općenito i provjerite vremensku zonu stranice. Ona određuje vrijeme dnevne objave.', 'brendigo-sidrena-cijena' ), 'wp-settings', __( 'Otvori postavke WordPressa', 'brendigo-sidrena-cijena' ) ),
			array( __( 'U kartici Poslovnice provjerite naziv vrste objekta, adresu i oznaku. Ako imate više lokacija, unesite svaku zasebno i spremite.', 'brendigo-sidrena-cijena' ), 'poslovnice', __( 'Otvori Poslovnice', 'brendigo-sidrena-cijena' ) ),
			array( __( 'Nemojte prepisivati sidrenu cijenu današnjom cijenom. Unesite samo iznos i datum za koje imate stvarnu evidenciju.', 'brendigo-sidrena-cijena' ) ),
		),
	),
);

$sidrena_help_standalone = array(
	'title' => __( 'Upute: obični WordPress bez WooCommercea', 'brendigo-sidrena-cijena' ),
	'intro'  => __( 'Za cjenik proizvoda i usluga ne trebate internetsku trgovinu. Ovaj način rada ne uključuje košaricu ni naplatu.', 'brendigo-sidrena-cijena' ),
	'steps' => array(
		array( __( 'Nakon aktivacije u lijevom izborniku otvorite Brendigo Sidrena Cijena → Proizvodi i usluge.', 'brendigo-sidrena-cijena' ), 'proizvodi', __( 'Otvori proizvode i usluge', 'brendigo-sidrena-cijena' ) ),
		array( __( 'Kliknite „Dodaj proizvod ili uslugu” i odaberite vrstu stavke. Unesite naziv i redovnu cijenu; ostala polja, poput SKU-a, barkoda, marke i mjerne jedinice, ispunite kada su potrebna.', 'brendigo-sidrena-cijena' ) ),
		array( __( 'U polje „Sidrena cijena” upišite stvarni iznos iz evidencije, a u „Datum sidrene” odgovarajući datum. Za novu stavku ne izmišljajte cijenu iz ranijeg razdoblja.', 'brendigo-sidrena-cijena' ) ),
		array( __( 'Ako imate više poslovnica, odaberite onu kojoj stavka pripada. Zatim kliknite „Spremi cjenik”.', 'brendigo-sidrena-cijena' ) ),
		array( __( 'Otvorite „Javni cjenik”, kliknite „Objavi cjenik sada” i potom „Otvori javni cjenik”. Provjerite naziv, iznose, datume i dostupnost.', 'brendigo-sidrena-cijena' ), 'javni-xml', __( 'Otvori Javni cjenik', 'brendigo-sidrena-cijena' ) ),
		array( __( 'Ako želite cjenik na svojoj WordPress stranici, dodajte stranicu, umetnite blok „Kratki kod” i zalijepite [sidrena-cjenik]. Za javnu arhivu koristite [sidrena-arhiva].', 'brendigo-sidrena-cijena' ), 'wp-pages', __( 'Otvori stranice', 'brendigo-sidrena-cijena' ) ),
	),
);

$sidrena_help_woocommerce = array(
	'title' => __( 'Upute: WordPress s WooCommerceom', 'brendigo-sidrena-cijena' ),
	'intro'  => __( 'WooCommerce upravlja nazivima proizvoda, zalihama i aktualnim cijenama. SIDRENA dodaje sidrene podatke i javne cjenike; proizvode nije potrebno ponovno unositi.', 'brendigo-sidrena-cijena' ),
	'steps' => array(
		array( __( 'Provjerite jesu li WooCommerce i vaši proizvodi ispravno postavljeni. Otvorite Proizvodi → Svi proizvodi i pregledajte redovne, akcijske cijene te varijacije.', 'brendigo-sidrena-cijena' ), 'woo-products', __( 'Otvori WooCommerce proizvode', 'brendigo-sidrena-cijena' ) ),
		array( __( 'Otvorite WooCommerce → Brendigo Sidrena Cijena → Dodatne stavke. U gornjem dijelu prikazani su WooCommerce proizvodi povezani s trgovinom.', 'brendigo-sidrena-cijena' ), 'proizvodi', __( 'Otvori Dodatne stavke', 'brendigo-sidrena-cijena' ) ),
		array( __( 'Za svaki proizvod ili varijaciju unesite točnu „Sidrenu cijenu” i „Cijena je vrijedila na dan”. Po potrebi upišite barkod te odaberite prikaz u javnom cjeniku.', 'brendigo-sidrena-cijena' ) ),
		array( __( 'Kliknite „Spremi sidrene cijene”. Ne koristite „Preuzmi važeću cijenu u prazna polja” kao zamjenu za povijesnu evidenciju.', 'brendigo-sidrena-cijena' ) ),
		array( __( 'U Postavkama po potrebi uključite „Automatski prikaz sidrene cijene”, spremite, a zatim otvorite javnu stranicu proizvoda i provjerite rezultat.', 'brendigo-sidrena-cijena' ), 'postavke', __( 'Otvori Postavke', 'brendigo-sidrena-cijena' ) ),
		array( __( 'U Javnom cjeniku objavite podatke. Pregledajte jednostavne proizvode, svaku varijaciju, datume, poreze, košaricu i naplatu. Prikaz može ovisiti o temi i blokovima.', 'brendigo-sidrena-cijena' ), 'javni-xml', __( 'Otvori Javni cjenik', 'brendigo-sidrena-cijena' ) ),
		array( __( 'Stavke koje nisu WooCommerce proizvodi (npr. usluge) možete dodatno unijeti u donji dio kartice „Dodatne stavke”. Ne unosite iste proizvode dvaput.', 'brendigo-sidrena-cijena' ) ),
		),
);

$sidrena_help_shared = array(
	array(
		'title' => __( 'Dnevna objava i arhiva', 'brendigo-sidrena-cijena' ),
		'steps' => array(
			array( __( 'Otvorite Javni cjenik, uključite „Objavi cjenik automatski svaki dan” i odaberite vrijeme (za nove instalacije početna je vrijednost 07:00).', 'brendigo-sidrena-cijena' ), 'javni-xml', __( 'Otvori postavke objave', 'brendigo-sidrena-cijena' ) ),
			array( __( 'Odaberite XML ili CSV kao glavni format, spremite postavke i prvi put kliknite „Objavi cjenik sada”. Usporedite javnu stranicu i datoteku.', 'brendigo-sidrena-cijena' ) ),
			array( __( 'Otvorite poveznicu „Arhiva cjenika” i provjerite je li objava dostupna. Arhiva treba biti dostupna javno, bez prijave u administraciju.', 'brendigo-sidrena-cijena' ) ),
			array( __( 'Na stranicama bez redovitih posjeta WP-Cron može kasniti. Ako objava mora biti u točno određeno vrijeme, zatražite od hostinga postavljanje poslužiteljskog rasporeda (cron).', 'brendigo-sidrena-cijena' ) ),
		),
	),
	array(
		'title' => __( 'Uvoz i izvoz većeg broja stavki', 'brendigo-sidrena-cijena' ),
		'steps' => array(
			array( __( 'Prije uvoza obavezno napravite sigurnosnu kopiju. U kartici Uvoz/Izvoz preuzmite CSV ili XLSX predložak i popunite stupce. SKU i barkod čuvajte kao tekst kada mogu imati vodeće nule.', 'brendigo-sidrena-cijena' ), 'uvoz-izvoz', __( 'Otvori Uvoz/Izvoz', 'brendigo-sidrena-cijena' ) ),
			array( __( 'Odaberite datoteku CSV, XML ili XLSX i kliknite „Učitaj i mapiraj polja”. Provjerite odgovara li svaki stupac ispravnom podatku.', 'brendigo-sidrena-cijena' ) ),
			array( __( 'Pregledajte probnu provjeru i moguće pogreške, odaberite poslovnicu, pa tek onda potvrdite „Pokreni uvoz”. To nije automatsko poništavanje već izvršenih promjena.', 'brendigo-sidrena-cijena' ) ),
			array( __( 'Na WooCommerce trgovini uvoz može promijeniti prodajnu cijenu ili zalihu postojećeg proizvoda. Nakon uvoza provjerite nekoliko proizvoda prije objave cjenika.', 'brendigo-sidrena-cijena' ) ),
		),
	),
);

// Primary guide is shown first. The other configuration remains available as an optional reference.
$sidrena_help_primary   = $sidrena_help_woo ? $sidrena_help_woocommerce : $sidrena_help_standalone;
$sidrena_help_secondary = $sidrena_help_woo ? $sidrena_help_standalone : $sidrena_help_woocommerce;
?>
<section class="sidrena-cijena-card sidrena-help" aria-labelledby="sidrena-help-title">
	<div class="sidrena-cijena-card__head">
		<h2 id="sidrena-help-title"><?php echo esc_html__( 'Korisničke upute – od prvog koraka do objave', 'brendigo-sidrena-cijena' ); ?></h2>
		<p><?php echo esc_html__( 'Vodič za početnike. Koraci odgovaraju stvarnim izbornicima i gumbima u ovom dodatku. Kliknite poveznicu uz korak kada želite otvoriti tu postavku.', 'brendigo-sidrena-cijena' ); ?></p>
	</div>
	<div class="sidrena-help__mode" role="status">
		<span class="dashicons <?php echo esc_attr( $sidrena_help_woo ? 'dashicons-cart' : 'dashicons-admin-site-alt3' ); ?>" aria-hidden="true"></span>
		<div><strong><?php echo esc_html( $sidrena_help_woo ? __( 'Otkriven je WooCommerce', 'brendigo-sidrena-cijena' ) : __( 'WordPress bez WooCommercea', 'brendigo-sidrena-cijena' ) ); ?></strong>
		<p><?php echo esc_html( $sidrena_help_woo ? __( 'Najprije pročitajte upute za webshop. Ostale upute dostupne su niže.', 'brendigo-sidrena-cijena' ) : __( 'Najprije pročitajte upute za samostalni cjenik. Upute za webshop dostupne su niže.', 'brendigo-sidrena-cijena' ) ); ?></p></div>
	</div>
	<nav class="sidrena-help__toc" aria-label="<?php echo esc_attr__( 'Sadržaj uputa', 'brendigo-sidrena-cijena' ); ?>">
		<a href="#sidrena-help-start"><?php echo esc_html__( 'Priprema', 'brendigo-sidrena-cijena' ); ?></a>
		<a href="#sidrena-help-workflow"><?php echo esc_html__( 'Prvi cjenik', 'brendigo-sidrena-cijena' ); ?></a>
		<a href="#sidrena-help-publish"><?php echo esc_html__( 'Objava', 'brendigo-sidrena-cijena' ); ?></a>
		<a href="#sidrena-help-import"><?php echo esc_html__( 'Uvoz i izvoz', 'brendigo-sidrena-cijena' ); ?></a>
		<a href="#sidrena-help-display"><?php echo esc_html__( 'Prikaz na stranici', 'brendigo-sidrena-cijena' ); ?></a>
		<a href="#sidrena-help-faq"><?php echo esc_html__( 'Česta pitanja', 'brendigo-sidrena-cijena' ); ?></a>
	</nav>
</section>
<?php
$sidrena_help_sections = array_merge( $sidrena_help_common, array( $sidrena_help_primary ), $sidrena_help_shared );
foreach ( $sidrena_help_sections as $sidrena_help_index => $sidrena_help_section ) :
	$sidrena_help_section_id = array( 'sidrena-help-start', 'sidrena-help-workflow', 'sidrena-help-publish', 'sidrena-help-import' )[ $sidrena_help_index ];
	?>
	<section id="<?php echo esc_attr( $sidrena_help_section_id ); ?>" class="sidrena-cijena-card sidrena-help__section" aria-labelledby="<?php echo esc_attr( $sidrena_help_section_id . '-title' ); ?>">
		<h2 id="<?php echo esc_attr( $sidrena_help_section_id . '-title' ); ?>"><?php echo esc_html( $sidrena_help_section['title'] ); ?></h2>
		<?php if ( isset( $sidrena_help_section['intro'] ) ) : ?><p><?php echo esc_html( $sidrena_help_section['intro'] ); ?></p><?php endif; ?>
		<ol class="sidrena-help__steps">
			<?php foreach ( $sidrena_help_section['steps'] as $sidrena_help_step ) : ?>
				<li>
					<p><?php echo esc_html( $sidrena_help_step[0] ); ?></p>
					<?php if ( isset( $sidrena_help_step[1], $sidrena_help_step[2], $sidrena_help_urls[ $sidrena_help_step[1] ] ) ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( $sidrena_help_urls[ $sidrena_help_step[1] ] ); ?>"><?php echo esc_html( $sidrena_help_step[2] ); ?><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</section>
<?php endforeach; ?>
<section id="sidrena-help-display" class="sidrena-cijena-card sidrena-help__section" aria-labelledby="sidrena-help-display-title">
	<h2 id="sidrena-help-display-title"><?php echo esc_html__( 'Kako prikazati cjenik i cijene na web-stranici', 'brendigo-sidrena-cijena' ); ?></h2>
	<p><?php echo esc_html__( 'U WordPress uređivaču otvorite ili izradite stranicu, umetnite blok „Kratki kod”, zalijepite primjer i kliknite „Objavi” ili „Ažuriraj”. Zamijenite 123 stvarnim ID-jem stavke.', 'brendigo-sidrena-cijena' ); ?></p>
	<div class="sidrena-help__shortcodes">
		<dl>
			<dt><?php echo esc_html__( 'Cijeli cjenik', 'brendigo-sidrena-cijena' ); ?></dt><dd><code>[sidrena-cjenik]</code></dd>
			<dt><?php echo esc_html__( 'Arhiva cjenika', 'brendigo-sidrena-cijena' ); ?></dt><dd><code>[sidrena-arhiva]</code></dd>
			<dt><?php echo esc_html__( 'Kartica ručno unesene stavke', 'brendigo-sidrena-cijena' ); ?></dt><dd><code>[sidrena_stavka id="123"]</code></dd>
			<dt><?php echo esc_html__( 'Zabilježena povijest cijene', 'brendigo-sidrena-cijena' ); ?></dt><dd><code>[sidrena_povijest_cijena id="123"]</code></dd>
			<dt><?php echo esc_html__( 'Grafikon cijena', 'brendigo-sidrena-cijena' ); ?></dt><dd><code>[sidrena_graf_cijena id="123"]</code></dd>
		</dl>
	</div>
	<p class="description"><?php echo esc_html__( 'Kada je WooCommerce uključen, za ručno unesene stavke u povijesti i grafikonu koristite ID s početnim slovom d, primjerice d123. Za WooCommerce proizvod koristite njegov brojčani ID.', 'brendigo-sidrena-cijena' ); ?></p>
	<p><a class="button button-secondary" href="<?php echo esc_url( $sidrena_help_urls['wp-pages'] ); ?>"><?php echo esc_html__( 'Otvori WordPress stranice', 'brendigo-sidrena-cijena' ); ?></a></p>
</section>
<section id="sidrena-help-faq" class="sidrena-cijena-card sidrena-help__section" aria-labelledby="sidrena-help-faq-title">
	<h2 id="sidrena-help-faq-title"><?php echo esc_html__( 'Česta pitanja i rješavanje problema', 'brendigo-sidrena-cijena' ); ?></h2>
	<details><summary><?php echo esc_html__( 'Ne vidim cijenu ili se promjena ne prikazuje javno.', 'brendigo-sidrena-cijena' ); ?></summary><p><?php echo esc_html__( 'Provjerite je li stavka spremljena, ima li ispravnu sidrenu cijenu i datum te je li uključena u javni cjenik. Zatim u Javnom cjeniku ponovno pokrenite objavu. Ako i dalje nema promjene, očistite predmemoriju stranice i preglednika.', 'brendigo-sidrena-cijena' ); ?></p></details>
	<details><summary><?php echo esc_html__( 'Svakodnevna objava nije pokrenuta na vrijeme.', 'brendigo-sidrena-cijena' ); ?></summary><p><?php echo esc_html__( 'Provjerite vremensku zonu WordPressa, uključenu dnevnu objavu i povijest objava. WordPressovi zakazani zadaci ovise o prometu ili poslužiteljskom rasporedu. Kontaktirajte hosting za pouzdan cron i provjerite je li e-mail za upozorenja ispravan.', 'brendigo-sidrena-cijena' ); ?></p></details>
	<details><summary><?php echo esc_html__( 'Kako izbjegavam pogreške s povijesnom cijenom?', 'brendigo-sidrena-cijena' ); ?></summary><p><?php echo esc_html__( 'Sidrenu cijenu unosite samo prema svojoj stvarnoj evidenciji za propisani datum. Povijest koju plugin prikuplja počinje tek od uključivanja praćenja; ne dokazuje ranije cijene. Najniža cijena prije sniženja zaseban je podatak i ne zamjenjuje sidrenu cijenu.', 'brendigo-sidrena-cijena' ); ?></p></details>
	<details><summary><?php echo esc_html__( 'Mogu li koristiti plugin bez webshopa?', 'brendigo-sidrena-cijena' ); ?></summary><p><?php echo esc_html__( 'Da. Ručno unesite proizvode i usluge pa objavite javni HTML/XML/CSV cjenik. WooCommerce je potreban samo za funkcije trgovine poput narudžbi i naplate.', 'brendigo-sidrena-cijena' ); ?></p></details>
	<details><summary><?php echo esc_html__( 'Kako postupiti ako uvoz ne uspije?', 'brendigo-sidrena-cijena' ); ?></summary><p><?php echo esc_html__( 'Provjerite format datoteke, mapiranje stupaca, prazne cijene i duple SKU vrijednosti. Ne pokrećite opet isti uvoz naslijepo. Ako ste već promijenili cijene, upotrijebite sigurnosnu kopiju ili ručno provjerite podatke.', 'brendigo-sidrena-cijena' ); ?></p></details>
	<details><summary><?php echo esc_html__( 'Koje informacije o korisnicima plugin pohranjuje?', 'brendigo-sidrena-cijena' ); ?></summary><p><?php echo esc_html__( 'Revizijski zapis administrativnih promjena može sadržavati identifikator prijavljenog korisnika, vrijeme i vrijednosti prije/poslije promjene. Javni cjenik sadrži podatke o proizvodima i cijenama, ne revizijski dnevnik. Pristup administratorskom dijelu treba dati samo ovlaštenim osobama.', 'brendigo-sidrena-cijena' ); ?></p></details>
</section>
<details class="sidrena-cijena-card sidrena-help__section sidrena-help__alternative">
	<summary><?php echo esc_html( $sidrena_help_secondary['title'] ); ?></summary>
	<p><?php echo esc_html( $sidrena_help_secondary['intro'] ); ?></p>
	<ol class="sidrena-help__steps">
		<?php foreach ( $sidrena_help_secondary['steps'] as $sidrena_help_step ) : ?>
			<li><p><?php echo esc_html( $sidrena_help_step[0] ); ?></p></li>
		<?php endforeach; ?>
	</ol>
</details>
<section class="sidrena-cijena-card sidrena-help__section sidrena-help__closing">
	<h2><?php echo esc_html__( 'Prije nego što završite', 'brendigo-sidrena-cijena' ); ?></h2>
	<p><?php echo esc_html__( 'Otvorite javni cjenik u privatnom prozoru preglednika, usporedite ga s vlastitom evidencijom cijena i provjerite arhivu. Ako imate WooCommerce, testirajte jednostavan i varijabilan proizvod te košaricu i naplatu.', 'brendigo-sidrena-cijena' ); ?></p>
	<p><?php echo esc_html__( 'Ove upute opisuju korištenje dodatka, ali ne zamjenjuju provjeru zakonskih obveza za vašu djelatnost.', 'brendigo-sidrena-cijena' ); ?></p>
</section>
