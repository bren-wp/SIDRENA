=== brendigo SIDRENA – sidrene cijene i cjenici za WooCommerce ===
Contributors: brendigo
Donate link: https://revolut.me/catanyus?currency=EUR&amount=1000&note=SIDRENA%20plugin%20-%20donacija
Tags: woocommerce, cijene, cjenik, csv, xml
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.21
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sidrene cijene, povijest cijena, javni CSV/XML cjenici, lokacije i arhiva objava za postojeće WooCommerce proizvode i varijacije.

== Description ==

SIDRENA za WooCommerce koristi postojeće WooCommerce proizvode i varijacije kao izvor podataka bez stvaranja dvostrukog kataloga.

* keep WooCommerce as the canonical product and variation source
* display reference-price information alongside existing WooCommerce prices when configured
* record price history and location-specific data
* generate public CSV/XML price lists and searchable HTML output
* keep at least 30 days of public publication archives
* publish separate outputs for active locations and webshop channels
* run scheduled daily generation, with 06:30 as the default time
* keep a bounded local audit log without telemetry
* use a compact, responsive WordPress admin interface with WooCommerce-specific accents

Official website: https://brendigo.com/sidrene-cijene/
Support: sidrena@brendigo.com
Author: brendigo

= Privacy and external links =

SIDRENA does not include telemetry or usage tracking. Public-availability checks use the WordPress HTTP API only for public SIDRENA files hosted by the same site.

Ovaj dodatak razvija brendigo neovisno. Dodatak nije povezan, odobren niti službeni proizvod WooCommercea ili Automattica.

This plugin is developed independently by brendigo. It is not affiliated with, endorsed by, or an official product of WooCommerce or Automattic.

= Important note =

SIDRENA provides technical tools for recording, checking and publishing price data. It is not an automatic legal certification of a specific business.

== Installation ==

1. Instalirajte i aktivirajte WooCommerce.
2. Prenesite `sidrena-woocommerce-1.0.21.zip` kroz Dodaci > Dodaj novi > Prenesi dodatak.
3. Aktivirajte brendigo SIDRENA – cjenici za WooCommerce.
4. Otvorite Sidrena > Proizvodi ili postojeći WooCommerce proizvod/varijaciju i unesite Sidrena podatke.
5. Provjerite Sidrena > Usluge ako ih objavljujete.
6. Unesite Sidrena > Lokacije.
7. Provjerite Sidrena > Postavke.
8. U Sidrena > Cjenici generirajte prvu objavu i provjerite javni prikaz.

Detaljne upute nalaze se u `docs/UPUTE.md`, a izdanje-specifični detaljni PDF priručnik i podrška u `docs/SIDRENA-PODRSKA.pdf`.

== Frequently Asked Questions ==

= Duplira li Sidrena WooCommerce proizvode? =
Ne. WooCommerce ostaje izvor proizvoda i varijacija.

= Moram li ručno dodavati shortcode uz svaki proizvod? =
Ne. Kada je prikaz uključen i podaci postoje, Sidrena automatski dodaje potrebni prikaz uz WooCommerce cijenu.

= Prati li Sidrena korisnike ili šalje telemetriju? =
Ne. Plugin nema ugrađenu analitiku ni telemetriju.

= Koja je licenca? =
Sidrena je GPLv2 ili novija, u skladu sa zahtjevima WordPress.org direktorija.

== Screenshots ==

1. Stvarni Sidrena WooCommerce ekran Pregled snimljen iz aktivnog WordPress + WooCommerce wp-admin sučelja.
2. Stvarni prikaz Sidrena rada s WooCommerce proizvodima.
3. Stvarni prikaz Cjenika i javnih datoteka.
4. Stvarni prikaz Lokacija / webshopa.
5. Stvarni prikaz Postavki.
6. Stvarni prikaz Pomoći i podrške.

== Changelog ==

= 1.0.21 =
* Fixed the optional REST location flow so an omitted location resolves to the first enabled public channel instead of becoming a synthetic location-not-found value.
* Physical-location REST requests now use the indexed SIDRENA location table as the candidate set instead of scanning the complete WooCommerce catalogue.
* Physical-location pagination preserves the configured WooCommerce variation child order, including manually reordered variations.
* Public REST and manifest output now use a canonical public metadata projection and no longer expose internal ordering/runtime timestamp fields.
* Real Chromium REST runtime QA now runs against the active WordPress and WooCommerce environments, while regression tests cover location-candidate pagination and configured variation order.
* CI now permanently guards against generated/debug content markers and encoded PHP execution primitives in production source.
* Both production packages passed PHP 7.4/8.3/8.4 CI, distribution/admin/legal guards, strict Plugin Check and real WordPress/WooCommerce browser QA before the 1.0.21 release bump.

= 1.0.20 =
* Public REST display hydration now honors the SIDRENA REST toggle and does not enqueue the compatibility REST layer when public REST output is disabled.
* Physical-location REST output no longer falls back to global WooCommerce stock when an explicit per-location availability value is missing; webshop output may still use the global stock fallback.
* Woo REST pagination totals now count the actual public rows available for the requested channel instead of counting variable parents or unavailable physical-location rows incorrectly.
* Storefront hydration skips redundant base-product REST requests when server-rendered SIDRENA markup is already present, while selected variations still hydrate dynamically.
* HTTP 408/409/425/429, 5xx, network and JSON failures remain retryable and keep the last valid markup visible; explicitly permanent client statuses are negative-cached to prevent repeated REST traffic for removed/non-public variations.
* Browser-like Node regression coverage verifies transient 503 recovery, permanent 404 negative caching and HTTP 408 timeout recovery.
* The functional 1.0.20 code passed PHP 7.4/8.3/8.4 CI, admin/legal/distribution guards, strict Plugin Check for both editions and real WordPress/WooCommerce browser QA before this version bump.

= 1.0.19 =
* Separated the current additional/sidrena price, the lowest price in the previous 30 days and the future statutory base-price layer so they are not treated as the same legal concept.
* Strict publication now blocks incomplete special-sale product/service rows when the 30-day reference is missing; perishable/fast-expiry exemptions require an expiry date and service exceptions remain channel-aware.
* WooCommerce variations now honor perishable/fast-expiry exemptions inherited from the parent product while physical-location availability remains explicit per location.
* Public HTML price lists now show available 30-day references, expiry details and service type/scope/cost/replacement-goods information without changing the fixed machine-readable CSV/XML header set.
* The safe legal profile keeps anchor display, 30-day display and price-history tracking enabled for auditable publication.
* Added NN 59/2026 future base-price readiness notes for the 17.11.2026 application date without inventing an implementing reference day or product scope that is not yet configured by the implementing rule.
* Expanded regression coverage for Croatian sale-reference rules, inherited Woo exemptions, stable machine headers and public legal details.
* The functional code passed PHP 7.4/8.3/8.4 CI, admin/legal/distribution guards, strict Plugin Check for both editions and real WordPress/WooCommerce browser capture before this version bump.
* The versioned 1.0.19 WordPress/WooCommerce wp-admin capture was rerun successfully and refreshed the real WordPress.org screenshots.

= 1.0.18 =
* Corrected the public WooCommerce text domain to `sidrena-for-woocommerce` across the source entrypoint and production package while retaining the `sidrena-woocommerce` install folder for compatible upgrades.
* Updated the package builder so WooCommerce ships `languages/sidrena-for-woocommerce.pot` with matching X-Domain metadata.
* Strict Plugin Check now validates the WooCommerce package against the public `sidrena-for-woocommerce` slug instead of the install-folder alias.
* Distribution, package-entrypoint and CI regression guards now fail if the old install-folder alias returns as the public text domain.
* The corrected production-shaped package passed PHP 7.4/8.3/8.4 CI, distribution guards, strict WooCommerce Plugin Check and real wp-admin browser capture before this release bump.

= 1.0.17 =
* Hardened stable release-source verification so every release branch must match the current main commit before publication.
* Preserved valid UTF-8 and JSON structure when local audit messages or context exceed storage limits.
* Reused the durable atomic writer for the public JSON manifest, including short-write handling and fsync before commit.
* WooCommerce CSV imports now clear stale package-unit metadata when the package-unit column is explicitly blank.
* Added permanent regression coverage for Woo import state, release integrity, audit data integrity, manifest publication, README real assets and Plugin Check workflow resilience.
* The main README now displays real SIDRENA production logos, icons, WordPress.org banners, brand covers and both runtime screenshot galleries.

= 1.0.16 =
* Consolidated the production hero and page-head layout into the canonical admin component layer and removed obsolete 1.0.14 override duplication.
* Preserved explicit edition-badge icon sizing after CSS consolidation so the WooCommerce edition identity remains stable.
* Hardened storefront variation hydration so stale asynchronous responses cannot overwrite the currently selected variation price information.
* Removed an obsolete release-specific version marker from production admin CSS.
* Expanded permanent regression coverage for the canonical hero layer, edition-badge icon sizing and stale WooCommerce variation responses.
* Real WordPress and WooCommerce admin captures passed the functional 1.0.16 runtime checks before the version bump.

= 1.0.15 =
* Added screen-reader captions and scoped column headers to SIDRENA admin data tables.
* Generated price-list file actions now expose contextual accessible labels instead of repeating an ambiguous “Open” action.
* Dynamic location cards update their title and address while editing, announce add/remove changes and restore focus to the nearest remaining location.
* Public price lists now use scoped column and row headers plus a local theme-independent visually-hidden utility.
* The public services table now includes its own accessible caption.
* Added all new accessibility and dynamic-location strings to the shipped translation catalog.
* Added permanent regression coverage for admin/public table semantics, dynamic location UX and frontend accessibility utilities.
* Real WordPress and WooCommerce admin captures passed the functional 1.0.15 UI/runtime checks before the version bump.

= 1.0.14 =
* Rebuilt the WordPress and WooCommerce dashboards as edition-specific SIDRENA interfaces based on the approved visual references, while keeping all metrics tied to real runtime data.
* Rebuilt the Digitalni cjenici screen with publication metrics, distribution/integration summary, real public-output preview, archive access and responsive card hierarchy.
* Replaced the WooCommerce 13-column primary editor with a compact product table and expandable advanced SIDRENA fields, without duplicating WooCommerce products or dropping any saved metadata.
* Added a local reference UI design layer for the navy/cyan WordPress and navy/purple WooCommerce editions; no external CSS or Tailwind CDN is required.
* Tightened responsive behavior for dashboards, tables, location forms, upload controls and action groups across desktop, tablet and mobile widths.
* Added client-side 5 MB/type validation for CSV/XML uploads while retaining existing server-side checks.
* Added permanent reference-admin-UI regression coverage to the main CI and admin-polish guard.
* Real wp-admin screenshots must be regenerated and pass JavaScript, overlap, overflow and form-viewport checks before release.

= 1.0.12 =
* Corrected the public REST index plugin URL so source and packaged runtime both expose the canonical Brendigo SIDRENA page.
* Removed the build-time legacy-domain rewrite that could hide stale production source during packaging.
* Added source-integrity regression coverage for retired SIDRENA domain variants across runtime code, documentation and every input copied into release packages.
* Strengthened CI so retired production URLs fail before packaging instead of being silently normalized.
* Explicitly unknown or disabled REST location IDs now return a 404 error instead of silently falling back to another active location.
* Unified import and sync forms under the same double-submit protection, browser-back recovery and accessible form status lifecycle.
* Added visible invalid/focus states, keyboard focus for custom toggles and labelled CSV/XML upload controls with file-size guidance.
* Added permanent admin-form UX regression coverage to the main CI and dedicated admin-polish guard.
* Service per-location current/reference price inputs now have explicit labels and a responsive editor layout.
* Business identity settings now validate Croatian OIB checksums client-side and server-side, and reject invalid business e-mail values with clear feedback.

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
* WooCommerce izdanje koristi ljubičasti edition akcent uz osnovni Sidrena plavi identitet.
* WordPress admin meni koristi kompaktno lokalno sidro bez vanjskih asseta.
* WordPress.org banneri generiraju se iz službenog Sidrena brandinga, a screenshotovi iz stvarnog WordPress + WooCommerce wp-admin sučelja.
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
