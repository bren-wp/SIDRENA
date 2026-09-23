=== Sidrena ===
Contributors: brendigo
Tags: woocommerce, cijene, cjenik, croatia, csv
Requires at least: 6.6
Tested up to: 7.1.2
Requires PHP: 7.4
Stable tag: 1.6.0
Donate link: https://sidrena-cijena.com.hr/#donirajte
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Besplatan WordPress/WooCommerce dodatak za sidrene cijene, povijest cijena, javne CSV/XML cjenike i arhivu 30+ dana.

== Description ==

**Sidrena** je potpuno besplatan open-source WordPress dodatak autora **Brendigo** za trgovce, webshopove i pružatelje usluga u Hrvatskoj.

Nema Pro izdanja, licencnog ključa, pretplate, telemetrije ni obaveznog vanjskog računa.

Plugin web: https://sidrena-cijena.com.hr/
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
* Shortcodeovi [sidrena_cjenici], [sidrena_usluge] i [sidrena_cijena].
* Bulk katalog, Site Health dijagnostika, lokalni audit dnevnik i WP-CLI.
* Samostalni Sidrena izbornik s odvojenim stranicama umjesto dvostrukih navigacija.
* Nema vanjskih runtime biblioteka, CDN-a, telemetrije, licence ili paywalla.

= Produkcijski panel =

Sidrena 1.6 donosi redizajn administracije prema novom Sidrena vizualnom identitetu. Pregled koristi stvarne podatke iz instalacije i prikazuje cijene, cjenike, povijest, arhivu i tehnička upozorenja bez lažnih rezultata ili izmišljenih postotaka usklađenosti.

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
2. Prenesite sidrena-1.6.0.zip, instalirajte i aktivirajte.
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

Da, za katalog i cjenike usluga. WooCommerce je potreban za proizvode.

= Čuva li svaku uspješnu objavu najmanje 30 dana? =

Postavka javne arhive ne može biti kraća od 30 dana; zadano je 45 dana. Svaka uspješna generacija sprema se kao zasebna CSV/XML datoteka.

= Je li javna arhiva isto što i najniža cijena u prethodnih 30 dana? =

Ne. Javna arhiva čuva objavljene cjenike. Zasebna interna povijest služi kao tehnička podloga za referencu prije sniženja.

= Može li plugin znati cijenu prije instalacije? =

Ne pouzdano. Ako nema provjerljivog povijesnog zapisa, Sidrena traži ručni unos ili uvoz iz vjerodostojne evidencije.

= Jamči li plugin zakonsku usklađenost? =

Ne. Automatizira tehničku evidenciju, prikaz i objavu. Primjenjivost propisa i točnost poslovnih podataka potrebno je provjeriti za konkretan poslovni subjekt.

== Screenshots ==

1. Novi Sidrena 1.6 pregled s cijenama, stvarnom poviješću, akcijama i tehničkim statusom.
2. Arhiva i tehnička spremnost s politikom čuvanja, integritetom i dobrovoljnom donacijom.
3. Aktualni javni CSV/XML cjenici po lokacijama i webshopu.
4. Bulk katalog za sidrene cijene, marke, datume i jediničnu cijenu.
5. Lokacije i webshop s lokacijskim cijenama i raspoloživošću.
6. Produkcijski alati za uvoz, izvoz, arhivu i provjerene poslovne evidencije.

== Changelog ==

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

= 1.6.0 =

Preporučena nadogradnja: produkcijski UI, poboljšana kontrola jedinične cijene, podaci usluga, bolja sljedivost i novi WordPress.org vizuali.
