=== Sidrena ===
Contributors: brendigo
Tags: woocommerce, cijene, cjenik, croatia, csv
Requires at least: 6.6
Tested up to: 7.1.2
Requires PHP: 7.4
Stable tag: 1.6.3
Donate link: https://sidrene-cijene.com.hr/#donirajte
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Besplatan WordPress/WooCommerce dodatak za sidrene cijene, povijest cijena, javne CSV/XML cjenike i arhivu 30+ dana.

== Description ==

**Sidrena** je potpuno besplatan open-source WordPress dodatak autora **Brendigo** za trgovce, webshopove i pružatelje usluga u Hrvatskoj.

Nema Pro izdanja, licencnog ključa, pretplate, telemetrije ni obaveznog vanjskog računa.

Plugin web: https://sidrene-cijene.com.hr/
Autor: https://brendigo.com/

Sidrena tehnički podržava evidenciju, prikaz i objavu podataka prema službenim pravilima i pojašnjenjima koja projekt prati. Dodatak je tehnički alat i ne predstavlja pravno mišljenje niti automatsko jamstvo usklađenosti konkretnog poslovnog subjekta.

= Glavne mogućnosti =

* Sidrena/dodatna cijena i referentni datum za WooCommerce proizvode i varijacije.
* Standardni referentni datum 10.09.2026. i zaseban datum 02.05.2025. za ranije obuhvaćene FMCG kategorije.
* Katalog usluga koji može raditi bez WooCommercea.
* Javni CSV/XML cjenik zasebno za svaku aktivnu fizičku lokaciju i webshop.
* Svaka uspješna objava ostaje zasebna javna datoteka.
* Minimalno čuvanje javne arhive 30 dana; zadano 45 dana.
* Lokalna povijest proizvoda, varijacija, usluga i lokacijskih vrijednosti.
* Odvojena evidencija javne arhive i 30-dnevne reference prije sniženja.
* SHA-256 provjera integriteta arhive.
* Lokacijske cijene, sidrene cijene i raspoloživost dostupno / nedostupno.
* Automatsko dnevno generiranje; zadano 06:30 prema WordPress vremenskoj zoni.
* Javni REST /wp-json/sidrena/v1/cjenici i /wp-json/sidrena/v1/cijene.
* Shortcodeovi [sidrena_cjenici], [sidrena_usluge], [sidrena_cijena] i [sidrena-cijena].
* Bulk katalog, Site Health dijagnostika, lokalni audit dnevnik i WP-CLI (wp sidrena generate, status, audit, fill).
* Samostalni Sidrena izbornik s odvojenim stranicama umjesto dvostrukih navigacija.
* Kompatibilnost s WooCommerce Blocks te popularnim builderima i dinamičkim price widgetima (Elementor, Divi, Bricks, Beaver Builder, Oxygen, WPBakery, Avada/Fusion, Woodmart/Flatsome, Breakdance/Brizy i srodni elementi).
* Nema vanjskih runtime biblioteka, CDN-a, telemetrije, licence ili paywalla.

= Novo u 1.6.1 =

* Radi s WooCommerceom ili kao samostalni WordPress katalog proizvoda.
* Uz WooCommerce postoji i zaseban ekran Dodatne stavke za ručne artikle koji ulaze u javni cjenik te imaju vlastiti [sidrena_cijena id="s123"] shortcode.
* Samostalni katalog podržava CSV/XML uvoz po šifri.
* WooCommerce standardni CSV Import/Export dobiva Sidrena polja.
* Javni pretraživi HTML cjenik: /sidrena-cjenik/ i [sidrena_cjenik] / [sidrena-cjenik].
* Javna arhiva: /arhiva-sidrene-cijene/ i [sidrena_arhiva] / [sidrena-arhiva].
* HTML cjenik koristi spremljeni snapshot, bez prolaska kroz cijeli katalog pri svakom posjetu.
* Ako snapshot nedostaje, vraća se pripremna poruka/503 i zakazuje jedna pozadinska obnova.
* Stroga provjera može spriječiti da nepotpuna nova objava zamijeni zadnji valjani cjenik.
* Admin upozorava na zastarjelo generiranje i objašnjava WP-Cron / server-cron režim.
* CSV import normalizira UTF-8, Windows-1250 i ISO-8859-2 gdje je moguće.
* Javni CSV neutralizira spreadsheet formula-prefikse u tekstualnim ćelijama.
* Dodan je fallback za Woo Blocks, Elementor, WPBakery, Oxygen, Divi, Bricks, Beaver Builder i druge česte price wrappere.
* Varijacije zadržavaju svoju Sidrena vrijednost nakon dinamičkih promjena WooCommerce forme.
* Oznaka i tooltip mogu se prilagoditi i registriraju se za WPML/Polylang prijevod.
* Catalog-hidden WooCommerce proizvodi ne ulaze u javni CSV/XML, snapshot ni realtime API.

= Produkcijski panel =

Sidrena 1.6.1 donosi produkcijsko sučelje i dodatne javne/uvozne mogućnosti prema novom Sidrena vizualnom identitetu. Pregled koristi stvarne podatke iz instalacije i prikazuje cijene, cjenike, povijest, arhivu i tehnička upozorenja bez lažnih rezultata ili izmišljenih postotaka usklađenosti.

= Jedinična cijena =

NN 105/2026 propisuje jediničnu cijenu za određene skupine robe uz propisane iznimke. Sidrena omogućuje označavanje statusa proizvoda kao potrebna provjera, obvezna, nije primjenjiva ili propisana iznimka.

Kada je označena kao obvezna, tehnička provjera upozorava ako nedostaje jedinica mjere ili cijena za jedinicu mjere. Sidrena ne donosi pravni zaključak samo iz WooCommerce kategorije.

= Usluge =

Za javni cjenik usluge moguće je voditi naziv, vrstu, opseg, aktualnu i sidrenu cijenu, posebni oblik prodaje, pripadajuće troškove te podatak o ugradbenoj/zamjenskoj robi i njezinoj cijeni kada je takva roba sastavni dio usluge.

= Digitalni cjenici =

Za proizvode Sidrena objavljuje naziv, šifru, marku, jedinicu mjere i cijenu za jedinicu mjere kada je primjenjivo, maloprodajnu cijenu, posebni oblik prodaje i naziv, sidrenu cijenu/datum, barkod te raspoloživost.

Za usluge objavljuje podatke propisane Odlukom, uz dodatna polja koja pomažu javnom prikazu vrste, opsega, troškova i sastavne robe.

Naziv datoteke uključuje vrstu objekta, adresu, oznaku objekta, redni broj pohrane te datum i vrijeme generiranja. Sve lokacije koriste jednaku tehničku strukturu za isti katalog.

= Arhiva najmanje 30 dana =

Svaka uspješna CSV/XML generacija sprema novu datoteku umjesto prepisivanja prethodne. Arhivski zapis sadrži vrijeme objave, rok čuvanja, broj redaka, veličinu i SHA-256. Aktualna datoteka dodatno se ne uklanja samo zato što je starija od arhivskog prozora.

Sidrena ne rekonstruira razdoblje prije instalacije. Povijesne vrijednosti unosite samo iz vjerodostojne poslovne evidencije.

= Privatnost =

Nema telemetrije ni automatskog kontakta s vanjskim servisom. Administracijske radnje koriste WordPress capability i nonce provjere. CSV/XML se prvo zapisuje u privremenu datoteku.

= Službeni izvori =

* https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html
* https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html
* https://mingo.gov.hr/vijesti/pojasnjenja-za-primjenu-dodatne-cijene-i-objavu-cjenika-od-1-listopada/10440
* https://narodne-novine.nn.hr/clanci/sluzbeni/2026_06_59_728.html
* https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_105_1270.html

== Installation ==

1. Otvorite Dodaci > Dodaj novi > Prenesi dodatak.
2. Prenesite aktualni sidrena-x.y.z.zip, instalirajte i aktivirajte.
3. Otvorite glavni izbornik **Sidrena**.
4. Odaberite proizvode, usluge ili mješoviti način rada.
5. Dodajte svaku fizičku lokaciju i zaseban webshop ako postoji.
6. Provjerite sidrene cijene, datume, marke i primjenjivost jedinične cijene.
7. Za fizičke lokacije uvezite stvarnu raspoloživost i eventualne lokalne cijene.
8. Generirajte prvi cjenik i provjerite Cjenici, Arhiva 30+ dana i Usklađenost.
9. Za pouzdano izvršavanje prije 08:00 konfigurirajte server cron koji pokreće WordPress cron ili koristite WP-CLI.

== Frequently Asked Questions ==

= Je li Sidrena potpuno besplatna? =

Da. Nema Pro izdanja, licencnog ključa, pretplate, triala niti udaljene aktivacije.

= Mogu li podržati razvoj? =

Da. U Sidrena panelu postoji diskretna poveznica za dobrovoljnu donaciju. Donacija nije uvjet za korištenje i ne otključava nikakve funkcije.

= Radi li bez WooCommercea? =

Da. Sidrena ima vlastiti katalog proizvoda i usluga; WooCommerce je potreban samo kada želite povezati Sidrena podatke s WooCommerce proizvodima i varijacijama.

= Čuva li svaku uspješnu objavu najmanje 30 dana? =

Postavka javne arhive ne može biti kraća od 30 dana; zadano je 45 dana. Svaka uspješna generacija sprema se kao zasebna CSV/XML datoteka.

= Je li javna arhiva isto što i najniža cijena u prethodnih 30 dana? =

Ne. Javna arhiva čuva objavljene cjenike. Zasebna interna povijest služi kao tehnička podloga za referencu prije sniženja.

= Može li plugin znati cijenu prije instalacije? =

Ne pouzdano. Ako nema provjerljivog povijesnog zapisa, Sidrena traži ručni unos ili uvoz iz vjerodostojne evidencije.

= Jamči li plugin zakonsku usklađenost? =

Ne. Automatizira tehničku evidenciju, prikaz i objavu. Primjenjivost propisa i točnost poslovnih podataka potrebno je provjeriti za konkretan poslovni subjekt.

= Kako masovno popuniti prazne Sidrena cijene? =

Za velike kataloge koristite wp sidrena fill --dry-run za pregled, wp sidrena fill za sigurno popunjavanje samo praznih Sidrena cijena iz WooCommerce redovne cijene i wp sidrena fill --today kada za nove artikle želite upisati i današnji prilagođeni referentni datum. Postojeće Sidrena vrijednosti se ne prepisuju.

= Kako ručno prikazati Sidrena cijenu u custom predlošku? =

Koristite [sidrena-cijena id="123"], [sidrena_cijena id="123"], do_action( 'sidrena_cijena' ) ili PHP helper sidrena_cijena( 123 ).

= Radi li s page builderima? =

Da. Sidrena koristi WooCommerce price filter, block/builder filtre i lokalni JS fallback za dinamički promijenjene cijene. Pokriva tipične price elemente u Elementor/JetWooBuilder/ShopEngine, Divi, Oxygen, Bricks, Beaver Builder, Breakdance, Brizy, Avada/Fusion, Woodmart/Flatsome, WPBakery i WooCommerce Blocks.

== Screenshots ==

1. Novi Sidrena 1.6 pregled s cijenama, stvarnom poviješću, akcijama i tehničkim statusom.
2. Arhiva i tehnička spremnost s politikom čuvanja, integritetom i dobrovoljnom donacijom.
3. Aktualni javni CSV/XML cjenici po lokacijama i webshopu.
4. Bulk katalog za sidrene cijene, marke, datume i jediničnu cijenu.
5. Lokacije i webshop s lokacijskim cijenama i raspoloživošću.
6. Produkcijski alati za uvoz, izvoz, arhivu i provjerene poslovne evidencije.

== Changelog ==

= 1.6.3 =
* Dodan non-blocking lock koji sprječava paralelna generiranja i race conditione nad javnim datotekama.
* JSON manifest i javni HTML snapshot sada se objavljuju atomskim privremeni-zapis → rename postupkom.
* Snapshot se generira streaming zapisom, bez držanja cijelog kataloga u jednom dodatnom PHP polju.
* Oštećen ili nevaljan javni snapshot više se ne tretira kao valjan: ruta vraća pripremni odgovor/503 i zakazuje obnovu.
* Dodatno je zatvoren REST/public izlaz za nejavne, password-protected i nejavne varijacijske proizvode.
* Poboljšana je Windows-1250 i ISO-8859-2 normalizacija na PHP 7.4–8.4 te CSV formula-injection zaštita.
* CI sada provjerava PHP 7.4, 8.3 i 8.4, hrvatske znakove u nazivima datoteka, legacy encoding, CSV injection i debug ostatke.
* Ispravljena je dokumentacija samostalnog kataloga i instalacijskog paketa.

= 1.6.2 =
* Proširena kompatibilnost s builderima i dinamičkim WooCommerce price widgetima.
* Dodan sigurni WP-CLI wp sidrena fill s --dry-run i --today; postojeće Sidrena cijene se nikada ne prepisuju.
* Dodan shortcode alias [sidrena-cijena] i univerzalni PHP helper sidrena_cijena().
* Standardiziran redoslijed prikaza: aktualna cijena → sidrena cijena → 30-dnevna referenca → rok uporabe kada je primjenjivo.
* Poboljšan WP-CLI status s poviješću, javnim datotekama, arhivom i statusom stroge objave.

= 1.6.1 =
* Samostalni WordPress katalog proizvoda bez obveznog WooCommercea.
* CSV/XML uvoz za samostalni katalog.
* Native WooCommerce CSV Import/Export Sidrena polja.
* Cacheirani javni HTML cjenik i arhiva s novim shortcodeovima.
* Stroga validacija objave i zadržavanje zadnje valjane datoteke kod greške.
* Cron-health upozorenja, UTF-8 normalizacija i CSV formula-injection zaštita.
* Proširena kompatibilnost s WooCommerce Blocks i popularnim page builderima.
* WPML/Polylang registracija prilagodljivih frontend stringova.
* Catalog-hidden proizvodi isključeni iz javnih izlaza.
* Dostupan pristupačan tooltip za sidrenu cijenu.

= 1.6.0 =
* Novi produkcijski Sidrena admin dizajn prema službenom vizualnom identitetu.
* Odvojene top-level Sidrena podstranice bez dvostruke navigacije.
* Stvarni graf i tablica najnovijih promjena cijena iz lokalne povijesti.
* Dobrovoljna donacija unutar Sidrena stranica bez paywalla ili funkcionalnih ograničenja.
* Eksplicitna provjera primjenjivosti jedinične cijene prema NN 105/2026.
* Podaci o ugradbenoj/zamjenskoj robi kod usluga.
* Novi WordPress.org banner, ikona i šest screenshot izvora.
* Poboljšana dokumentacija, pravne napomene i produkcijsko pakiranje.

Starije promjene: changelog.txt.

== Upgrade Notice ==

= 1.6.3 =

Preporučena nadogradnja: sigurnije generiranje i javna objava, bolja zaštita REST izlaza, streaming snapshoti te PHP 8.4/legacy-encoding provjere.

= 1.6.2 =

Preporučena nadogradnja: builder kompatibilnost, WP-CLI fill, standalone katalog, javni HTML cjenik i stroža produkcijska automatizacija.

= 1.6.1 =

Preporučena nadogradnja: javni HTML cjenik, standalone katalog, stroga objava, Woo CSV integracija i dodatna kompatibilnost.

= 1.6.0 =

Preporučena nadogradnja: produkcijski UI, poboljšana kontrola jedinične cijene, podaci usluga, bolja sljedivost i novi WordPress.org vizuali.
