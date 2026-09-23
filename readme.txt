=== Sidrena ===
Contributors: brendigo
Tags: woocommerce, cijene, cjenik, croatia, csv
Requires at least: 6.6
Tested up to: 7.1.2
Requires PHP: 7.4
Stable tag: 1.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Besplatan WordPress/WooCommerce dodatak za sidrene cijene, povijest cijena te javne CSV/XML cjenike i arhivu 30+ dana.

== Description ==

**Sidrena** je potpuno besplatan open-source WordPress dodatak autora **Brendigo** za trgovce i pružatelje usluga u Hrvatskoj. Nema Pro izdanja, licencnog ključa, pretplate, telemetrije ni obaveznog vanjskog računa.

Plugin web: https://sidrena-cijena.com.hr/
Autor: https://brendigo.com/

Sidrena tehnički podržava evidenciju i objavu podataka prema pravilima iz NN 101/2026 i službenom pojašnjenju Ministarstva gospodarstva od 22.09.2026., koja se primjenjuju od 1.10.2026. Dodatak je tehnički alat, ne pravno mišljenje niti jamstvo usklađenosti konkretnog poslovnog subjekta.

= Glavne mogućnosti =

* Dodatna/sidrena cijena za WooCommerce proizvode i varijacije.
* Standardni referentni datum 10.09.2026. i zaseban FMCG datum 02.05.2025.
* Katalog usluga bez obaveznog WooCommercea.
* Javni CSV/XML cjenik zasebno za svaku fizičku lokaciju i webshop.
* Svaka uspješna objava ostaje zasebna javna datoteka; minimalno čuvanje je 30 dana, zadano 45 dana.
* Interna povijest proizvoda, usluga i lokacijskih vrijednosti čuva promjene i dnevne snapshotove 400 dana.
* 30-dnevna javna arhiva cjenika i povijest za najnižu cijenu prije sniženja vode se odvojeno.
* SHA-256 provjera integriteta arhive, kalendar objava i CSV izvoz evidencije.
* Lokacijske cijene, sidrene cijene i raspoloživost `dostupno` / `nedostupno`.
* Automatsko dnevno generiranje; zadano 06:30 prema WordPress vremenskoj zoni.
* Javni indeks `/wp-json/sidrena/v1/cjenici` i aktualne cijene `/wp-json/sidrena/v1/cijene`.
* Shortcodeovi `[sidrena_cjenici]`, `[sidrena_usluge]` i `[sidrena_cijena]`.
* Jedan klik za izradu javne WordPress stranice **Cjenici**.
* Premium top-level **Sidrena** izbornik pri dnu WordPress administracije.
* Kartice Pregled, Usklađenost, Cjenici, Arhiva 30+ dana, Lokacije, Postavke, Alati i Propisi.
* Nema vanjskih runtime biblioteka, CDN-a, telemetrije, licence, računa ili paywalla.

= Digitalni cjenici =

Za proizvode Sidrena objavljuje naziv, šifru, marku, jedinicu mjere i cijenu za jedinicu mjere kada je primjenjivo, maloprodajnu cijenu, posebni oblik prodaje i naziv, sidrenu cijenu/datum, barkod te raspoloživost. Šifra koristi WooCommerce SKU, zatim opcionalnu Sidrena šifru, a zatim stabilni `WP-ID` fallback.

Za usluge objavljuje naziv, maloprodajnu cijenu, posebni oblik prodaje i naziv te sidrenu cijenu; dodatno može voditi vrstu, opseg i pripadajuće troškove/napomenu o cijeni.

Naziv datoteke uključuje vrstu objekta, adresu, oznaku objekta, redni broj pohrane te datum i vrijeme generiranja. Sve lokacije koriste jednaku tehničku strukturu za isti katalog.

= Arhiva najmanje 30 dana =

Svaka uspješna CSV/XML generacija sprema novu datoteku umjesto prepisivanja prethodne. Svaki arhivski zapis ima vlastiti rok čuvanja, vrijeme objave, broj redaka, veličinu i SHA-256. Povećanje razdoblja čuvanja može produljiti stare zapise, ali ne skraćuje već zapisani rok. Aktualna datoteka dodatno se ne briše samo zato što je starija od arhivskog prozora.

Sidrena ne može pouzdano rekonstruirati razdoblje prije instalacije. Povijesne vrijednosti treba uvesti ili upisati samo iz vjerodostojne poslovne evidencije.

= Najniža cijena prije sniženja =

Sidrena zasebno bilježi promjene cijena WooCommerce proizvoda/varijacija i Sidrena usluga. Kod novog neprekinutog sniženja pokušava zamrznuti provjerljivu referencu iz prethodnih 30 dana. Ako nema poznato stanje na početku prozora, rezultat označava kao nepotpun umjesto nagađanja. Moguće je unijeti provjerenu ručnu vrijednost.

= Privatnost i sigurnost =

Administracijske radnje koriste capability i nonce provjere. CSV/XML se pišu preko privremene datoteke i tek potom zamjenjuju konačnom datotekom. Nema telemetrije ni automatskog kontakta s vanjskim servisom. Javni REST odgovori aktualnih cijena šalju no-cache/no-store zaglavlja.

= Službeni izvori =

* https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html
* https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html
* https://mingo.gov.hr/vijesti/pojasnjenja-za-primjenu-dodatne-cijene-i-objavu-cjenika-od-1-listopada/10440
* https://narodne-novine.nn.hr/clanci/sluzbeni/2026_06_59_728.html

Dodatne tehničke bilješke nalaze se u `docs/legal-and-technical-notes.md`, a starija povijest izdanja u `changelog.txt`.

== Installation ==

1. Otvorite Dodaci > Dodaj novi > Prenesi dodatak.
2. Prenesite `sidrena-1.5.1.zip`, instalirajte i aktivirajte.
3. Otvorite glavni izbornik **Sidrena**.
4. Odaberite proizvode, usluge ili mješoviti način rada.
5. Dodajte svaku fizičku lokaciju i zaseban webshop ako postoji.
6. Provjerite sidrene cijene i datume; povijesne vrijednosti unosite samo iz provjerene evidencije.
7. Za fizičke lokacije uvezite raspoloživost i eventualne lokacijske cijene.
8. Generirajte prvi cjenik i provjerite Cjenici, Arhiva 30+ dana i Usklađenost.
9. Za poslovno kritično izvršavanje prije 08:00 konfigurirajte pouzdan server cron koji pokreće WordPress cron.

== Frequently Asked Questions ==

= Je li Sidrena potpuno besplatna? =

Da. Nema Pro izdanja, licencnog ključa, pretplate, vremenskog ograničenja ni udaljene aktivacije.

= Radi li bez WooCommercea? =

Da, za katalog i cjenike usluga. Za proizvode je potreban WooCommerce.

= Čuva li svaki objavljeni cjenik 30 dana? =

Svaka uspješna generacija sprema se kao zasebna javna CSV/XML datoteka, a postavka čuvanja ne može biti kraća od 30 dana. Zadano je 45 dana.

= Je li javna arhiva isto što i najniža cijena u prethodnih 30 dana? =

Ne. Javna arhiva čuva objavljene cjenike, a zasebna interna povijest služi kao tehnička podloga za izračun reference prije sniženja.

= Može li plugin znati cijenu prije instalacije? =

Ne pouzdano. Ako nema vjerodostojnog povijesnog zapisa, Sidrena traži ručni unos ili CSV uvoz umjesto izmišljanja podatka.

= Mora li svaka poslovnica imati svoj cjenik? =

Službeno pojašnjenje Ministarstva od 22.09.2026. navodi zasebnu datoteku za svaku fizičku lokaciju, a webshop kao zaseban objekt. Sidrena je zato strukturirana po lokacijama.

= Jamči li plugin zakonsku usklađenost? =

Ne. Automatizira tehničku evidenciju, prikaz i objavu; primjenjivost propisa i točnost poslovnih podataka treba provjeriti za konkretan poslovni subjekt.

== Screenshots ==

1. Premium Sidrena pregled s tehničkim statusom i glavnim akcijama.
2. Centar usklađenosti s tehničkim provjerama podataka, povijesti i integriteta.
3. Upravljanje aktualnim javnim CSV/XML cjenicima, javnom stranicom i strojnim dohvatom.
4. Arhiva 30+ dana s kalendarom, SHA-256 provjerom i rokovima čuvanja.
5. Upravljanje fizičkim lokacijama, webshopom i pokrivenošću raspoloživosti.
6. Alati za uvoz i izvoz sidrenih cijena, lokacijskih podataka, povijesti i arhive.

== Changelog ==

= 1.5.0 =
* Bulk catalog editor, local audit trail, Site Health checks and WP-CLI production commands.

= 1.4.0 =
* Dodan Centar usklađenosti s tehničkim provjerama podataka, lokacija, cjenika, arhive, cron rasporeda i integriteta.
* Dodana 400-dnevna povijest lokacijskih cijena, sidrenih cijena i raspoloživosti.
* Dodan objedinjeni CSV izvoz interne povijesti proizvoda, usluga i lokacija.
* Dodana izrada javne stranice Cjenici jednim klikom.
* Dodana stabilna šifra proizvoda SKU -> Sidrena šifra -> WP-ID.
* Proširen REST prikaz i usklađen porezni tretman WooCommerce maloprodajnih cijena s CSV/XML cjenikom.
* Dodani hookovi za početak i završetak zakazanih WooCommerce sniženja.
* Poboljšani admin UI, provjera marke proizvoda i upravljanje manifestom.

Starije promjene: `changelog.txt`.

== Upgrade Notice ==

= 1.4.0 =

Preporučena nadogradnja: dodaje centar tehničke spremnosti, lokacijsku povijest, objedinjeni izvoz, javnu stranicu Cjenici i potpuniji strojni dohvat.
