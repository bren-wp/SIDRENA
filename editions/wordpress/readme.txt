=== SIDRENA ===
Contributors: brendigo
Donate link: https://revolut.me/catanyus?currency=EUR&amount=1000&note=Sidrena%20WordPress%20plugin%20-%20donacija
Tags: cijene, cjenik, csv, xml, trgovina
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.12
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Price history, products and services, public CSV/XML price lists, locations and publication archives for standard WordPress sites.

== Description ==

SIDRENA 1.0.12 is built for WordPress sites that need a structured price catalogue without using WooCommerce as the product source.

* manage products and services in a dedicated catalogue or connect existing public WordPress content
* record current, reference and unit prices when applicable
* generate public CSV/XML price lists and searchable HTML output
* keep at least 30 days of public publication archives
* maintain separate physical locations and webshop channels
* run scheduled daily generation, with 06:30 as the default time
* keep a bounded local audit log without telemetry
* use a compact, responsive WordPress admin interface with the SIDRENA visual system

Official website: https://brendigo.com/sidrene-cijene/
Support: sidrena@brendigo.com
Author: Brendigo

= Privacy and external links =

SIDRENA does not include telemetry or usage tracking. Public-availability checks use the WordPress HTTP API only for public SIDRENA files hosted by the same site.

= Important note =

SIDRENA provides technical tools for recording, checking and publishing price data. It is not an automatic legal certification of a specific business.

== Installation ==

1. Prenesite `sidrena-wordpress-1.0.12.zip` kroz Dodaci > Dodaj novi > Prenesi dodatak.
2. Aktivirajte Sidrena WordPress.
3. Otvorite Sidrena > Katalog i povežite postojeći sadržaj ili unesite/uvezite katalog.
4. Provjerite Sidrena > Usluge ako ih objavljujete.
5. Unesite Sidrena > Lokacije.
6. Provjerite Sidrena > Postavke.
7. U Sidrena > Cjenici generirajte prvu objavu i provjerite javni prikaz.
8. Na Sidrena > Pregled provjerite tehničku spremnost i raspored.

Detaljne upute nalaze se u `docs/UPUTE.md`, a PDF podrška u `docs/SIDRENA-PODRSKA.pdf`.

== Frequently Asked Questions ==

= Moram li ručno dodavati shortcode uz svaki povezani proizvod? =
Ne. Kada je postojeći WordPress sadržaj povezan sa Sidrena katalogom, plugin može automatski prikazati sidrenu cijenu na povezanoj javnoj stranici.

= Objavljuje li cjenik automatski? =
Da. WordPress raspored generira cjenik u konfigurirano vrijeme, zadano 06:30. Za poslovno kritičan termin preporučuje se server cron.

= Prati li Sidrena korisnike ili šalje telemetriju? =
Ne. Plugin nema ugrađenu analitiku ni telemetriju.

= Koja je licenca? =
Sidrena je GPLv2 ili novija, u skladu sa zahtjevima WordPress.org direktorija.

== Screenshots ==

1. Stvarni Sidrena WordPress ekran Pregled snimljen iz aktivnog wp-admin sučelja.
2. Stvarni prikaz Kataloga u Sidrena WordPress izdanju.
3. Stvarni prikaz Cjenika i javnih datoteka.
4. Stvarni prikaz Lokacija.
5. Stvarni prikaz Postavki i tehničke konfiguracije.
6. Stvarni prikaz Pomoći i podrške.

== Changelog ==

= 1.0.12 =
* Corrected the public REST index plugin URL so source and packaged runtime both expose the canonical Brendigo SIDRENA page.
* Removed the build-time legacy-domain rewrite that could hide stale production source during packaging.
* Added source-integrity regression coverage for retired SIDRENA domain variants across runtime code and distribution inputs.
* Strengthened CI so retired production URLs fail before packaging instead of being silently normalized.
* Runtime UI is unchanged from 1.0.11; the existing real wp-admin screenshots remain representative.

= 1.0.11 =
* Release publication now runs strict official Plugin Check against both production-shaped packages before publishing.
* New version tags are created only after package integrity verification and both release Plugin Check gates succeed.
* Added a permanent regression test that protects the release ordering from build through Plugin Check, tag creation and publication.
* Prepared remaining service-history and standalone table identifiers with WordPress %i placeholders instead of SQL string interpolation.
* Removed obsolete PreparedSQL.InterpolatedNotPrepared suppression comments from already-prepared custom-table queries.
* Added regression coverage for service-history and standalone SQL identifier handling.

= 1.0.10 =
* Fixed Plugin Check findings for translator comments, output escaping, request sanitisation and custom-table SQL identifiers.
* Removed the discouraged manual translation loader and documented intentional external WPML hooks.
* Refined the SIDRENA logo geometry, admin hero, menu icon, spacing, cards, tables and edition badges to follow the supplied brand references more closely.
* WordPress.org description copy is now in standard English while the Croatian runtime interface remains unchanged.
* README and WordPress.org runtime screenshots are regenerated from the real 1.0.10 plugin UI.
* Fixed the early upgrade/bootstrap order that could trigger a WP-CLI fatal before rewrite globals were ready.
* Official WordPress Plugin Check passes against the production-shaped 1.0.10 package.

= 1.0.9 =
* Ispravljeno je učitavanje kompletnog SIDRENA admin CSS/JS sloja i kada drugi plugin ili admin router promijeni WordPress hook suffix; dodan je sigurni admin_print_styles fallback bez inline CSS-a.
* Runtime asset URL-ovi koriste plugins_url() vezan uz stvarnu SIDRENA ulaznu datoteku, a filemtime cache-busting sprječava prikaz zastarjelog CSS-a nakon nadogradnje.
* Potpuno je obnovljen SIDRENA logo sustav: glavni znak, WordPress/WooCommerce lockupi, svijetle varijante, app ikona i posebna mala admin-menu ikona.
* Admin hero, kartice, metrike, razmaci i responzivne dimenzije dodatno su usklađeni s referentnim SIDRENA prikazima.
* CI i distribution guard sada izričito provjeravaju da instalacijski ZIP sadrži brand.css, admin.js i edition-specific runtime logotipe.
* Dodan je izvršni admin asset-routing regresijski test.

= 1.0.8 =
* Instalacijski ZIP je radikalno smanjen uklanjanjem WordPress.org screenshotova i marketinških PNG/SVG asseta koji nisu potrebni za runtime.
* Build i release guard odbijaju instalacijski ZIP veći od 1,5 MiB, čime se izbjegava čest shared-hosting upload_max_filesize problem od 2 MiB.
* Dodan je cross-version upgrade marker i idempotentni repair put koji omogućuje 1.0.8 nadogradnju preko bilo kojeg ranijeg SIDRENA izdanja iste edicije bez brisanja poslovnih podataka.
* Upgrade automatski obnavlja shemu, postavke, direktorije za objavu, rasporede i javnu stranicu kada nedostaju.
* Uklonjeni su preostali duplicirani/stari source headeri iz runtime testova.

= 1.0.7 =
* Ispravljena je prva objava nove usluge: sidrena vrijednost finalizira se nakon spremanja stvarno unesene aktualne cijene.
* Uklonjena je dvostruka registracija stavke Usluge u WordPress admin meniju.
* Admin obrasci sprječavaju slučajni dvostruki submit i imaju jasniji keyboard focus / busy state.
* Pojačani su regresijski testovi za servisni lifecycle, strukturu cjenika i admin navigaciju.

= 1.0.6 =
* Službeni datum MINGO pojašnjenja usklađen je s datumom objave 22.09.2026.
* Uklonjena je duplicirana normalizacija pravno relevantnih postavki iz compliance watchdog sloja.
* Dodatno su ispolirani SIDRENA brand header, responzivni prikaz i zaštite od overflowa.

= 1.0.5 =
* Ispravljena je službena oznaka MINGO pojašnjenja na dokument objavljen 22.09.2026. i usklađen je ruleset prikaz.
* Uklonjeni su preostali duplicirani source headeri i zastarjeli URL-ovi iz produkcijskog PHP/CSS koda.
* Dodatno je poliran Sidrena brand sustav prema službenim vizualnim referencama, uz zadržavanje stvarnih runtime podataka.
* WordPress i WooCommerce izdanje ostaju strogo odvojeni, bez telemetrije i bez paralelnog kataloga u WooCommerce izdanju.

= 1.0.4 =
* WordPress katalog dobio je jasno produkcijsko prazno stanje bez lažnog ili nedovršenog retka proizvoda.
* Admin JavaScript sinkronizira prazno stanje pri dodavanju i uklanjanju prvog nespremljenog proizvoda.
* Propisi sada sadrže praktičan NN 105/2026 vodič za kategorije jedinične cijene i propisane iznimke, bez automatskog pravnog klasificiranja proizvoda.
* Vizual svjetionika u brand headeru bolje je kadriran prema službenim SIDRENA referencama.
* Legal smoke test čuva datum primjene 26.09.2026., vodič kategorija/iznimaka i pravilo ljudske provjere primjenjivosti.

= 1.0.3 =
* Ispravljeni su CI guardovi nakon legitimnog refaktora admin body klase i površine podrške.
* Mobilni CSS breakpointi premješteni su iza produkcijskih komponenti kako kasnija desktop pravila više ne bi poništavala 782/390 px raspored.
* WordPress katalog na uskim ekranima koristi lokalno skrolanje tablice bez horizontalnog overflowa cijele wp-admin stranice.
* Uklonjen je preostali zastarjeli URL iz javnog manifesta i dokumentacija je usklađena sa službenim Brendigo SIDRENA URL-om.
* Pravna formulacija dodatno je pooštrena: tehničke provjere ne zamjenjuju individualnu pravnu procjenu.

= 1.0.2 =
* Uklonjen legacy/mrtvi administratorski CSS koji se više ne koristi na glavnim Sidrena ekranima.
* Smanjeno je dupliciranje PHP navigacije i edition-conflict zaštite.
* Optimiziran je WooCommerce kompatibilni frontend DOM/AJAX sloj kako bi izbjegao redundantne zahtjeve i vlastite mutation cikluse.
* WordPress i WooCommerce zadržavaju jasno odvojene edition akcente uz zajednički SIDRENA brand sustav.
* Produkcijske pravne provjere ostaju tehničke provjere podataka, objave, arhive i raspoloživosti te ne zamjenjuju individualnu pravnu procjenu.

= 1.0.1 =
* Usklađena je tehnička provjera s NN 101/2026 i službenim MINGO pojašnjenjima: dovoljan je CSV ili XML, dok javni HTML i manifest ostaju opcionalni.
* Barkod proizvoda više ne blokira objavu kada nije primjenjiv, a dodatni opisni podaci usluge ostaju korisni ali nisu obvezni za strogi cjenik.
* Zadana oznaka dodatne cijene prikazuje se kao "Cijena na datum", uz zadržavanje podrške za prilagođeni naziv.
* Očišćeni su duplicirani source headeri, zastarjeli URL-ovi i razvojni version komentari u produkcijskom CSS/JS kodu.
* Zadržane su zaštite za referentne datume 10.09.2026. i 02.05.2025., arhivu najmanje 30 dana i generiranje prije 08:00.

= 1.0.0 =
* Stabilno 1.0 izdanje objedinjuje završeni Sidrena produkcijski UI/UX, stvarne runtime screenshotove i kompletan branding paket.
* Dashboard tehnička spremnost sada koristi isti stvarni checklist kao detaljna kontrola, bez kontradiktornih statusa.
* Dodatno su ispolirani responzivni obrasci, file inputi, prazna stanja, sticky akcije i prikaz kataloga.
* Runtime i distribucijski paketi strogo odvajaju WordPress i WooCommerce edition-specific logotipe i marketinške assete.
* Release ZIP-ovi provjeravaju SHA-256, integritet arhive, stvarne screenshotove i sadržaj paketa prije objave.
* Uninstall zaštite i smoke testovi usklađeni su sa sigurnim čišćenjem runtime rasporeda i očuvanjem zajedničkih poslovnih podataka.
* Stvarni wp-admin capture prolazi desktop, tablet i mobilne provjere bez ključnih preklapanja i horizontalnog overflowa.

= 0.9.0 =
* Potpuno novo Sidrena administracijsko sučelje izrađeno od nule prema službenom pomorskom brand sustavu.
* Novi tamno-plavi brand header sa svjetionikom, responzivne statusne kartice, tablice, obrasci i jasne akcije.
* WordPress izdanje koristi plavi edition akcent, lokalne logotipe i novu Sidrena app ikonu.
* WordPress admin meni koristi kompaktno lokalno sidro bez vanjskih asseta.
* WordPress.org banneri generiraju se iz službenog Sidrena brandinga, a screenshotovi iz stvarnog aktivnog wp-admin sučelja.
* Dodan je kompletan brand vodič i produkcijski branding paket koji ulazi i u instalacijski ZIP.
* Dokumentacija u ZIP-u sadrži šest stvarnih runtime screenshotova novog 0.9.0 sučelja.
* Službena stranica: https://brendigo.com/sidrene-cijene/.

= 0.8.1 =
* Dodana lokalna sidro ikonica u WordPress bočni meni.
* Uklonjen mrtvi legacy admin menu i popravljeni linkovi prema sekundarnim prikazima.
* Admin zaglavlje pojednostavljeno je na mali Sidrena logo i jasan edition badge.
* Službeni Plugin URI promijenjen je na https://brendigo.com/sidrene-cijene/.
* Dokumentacija, WP.org readme i stvarni runtime screenshotovi usklađeni su s trenutačnim sučeljem.
* Licenca je usklađena na GPLv2 ili noviju za WordPress.org distribuciju.
* WP.org tagovi ograničeni su na pet i uklonjen je GitHub Update URI iz plugin headera.

= 0.8.0 =
* Izdvojena su dva službena plugin paketa: WordPress i WooCommerce.
* Ojačane su provjere izdanja, sigurnosti i stabilnosti.

= 0.7.0 =
* Distribucija je zadržana isključivo kroz dva WordPress plugin ZIP paketa.
* Dokumentacija i release pravila usklađeni su s plugin-only isporukom.

= 0.6.0 =
* Dodani su distribution, legal automation i produkcijski guardovi.
* Poboljšani su streaming uvoz, arhiva i ograničavanje audit zapisa.

= 0.1.0 =
* Prvo javno izdanje.
