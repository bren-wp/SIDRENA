=== Sidrena WooCommerce ===
Contributors: brendigo
Donate link: https://revolut.me/catanyus?currency=EUR&amount=1000&note=Sidrena%20WooCommerce%20plugin%20-%20donacija
Tags: woocommerce, cijene, cjenik, csv, xml
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sidrene cijene za WooCommerce proizvode i usluge, javni CSV/XML cjenici, povijest cijena i arhiva objava.

== Description ==

Sidrena WooCommerce 1.0.1 koristi postojeće WooCommerce proizvode i varijacije kao izvor proizvoda.

* nema dupliciranja WooCommerce kataloga
* automatski prikaz sidrene/referentne cijene uz WooCommerce cijenu
* proizvodi, varijacije, usluge i lokacijski podaci
* povijest cijena i 30-dnevna referenca kada postoji dovoljna evidencija
* javni CSV/XML cjenici i pretraživi HTML prikaz
* najmanje 30 dana javne arhive prethodnih objava
* zasebna objava po aktivnoj lokaciji/webshopu
* dnevno automatsko generiranje, zadano u 06:30
* lokalni audit zapis s ograničenjem rasta i bez telemetrije
* kompaktan WordPress admin meni sa Sidrena sidro ikonicom

Službena stranica: https://brendigo.com/sidrene-cijene/
Podrška: sidrena@brendigo.com
Autor: Brendigo

= Privatnost i vanjske veze =

Sidrena ne šalje telemetriju i ne prati korištenje plugina. Provjera javne dostupnosti koristi WordPress HTTP API samo prema javnim Sidrena datotekama na istoj web stranici. E-mail, WhatsApp, službena stranica i donacija otvaraju se samo nakon korisničke akcije.

= Važna napomena =

Sidrena tehnički pomaže voditi i objaviti podatke o cijenama. Ne predstavlja automatsku pravnu potvrdu konkretnog poslovanja. Povijesni i referentni podaci moraju odgovarati stvarnoj poslovnoj evidenciji.

== Installation ==

1. Instalirajte i aktivirajte WooCommerce.
2. Prenesite `sidrena-woocommerce-1.0.1.zip` kroz Dodaci > Dodaj novi > Prenesi dodatak.
3. Aktivirajte Sidrena WooCommerce.
4. Otvorite Sidrena > Proizvodi ili postojeći WooCommerce proizvod/varijaciju i unesite Sidrena podatke.
5. Provjerite Sidrena > Usluge ako ih objavljujete.
6. Unesite Sidrena > Lokacije.
7. Provjerite Sidrena > Postavke.
8. U Sidrena > Cjenici generirajte prvu objavu i provjerite javni prikaz.

Detaljne upute nalaze se u `docs/UPUTE.md`, a PDF podrška u `docs/SIDRENA-PODRSKA.pdf`.

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

= 1.0.1 =
* Produkcijsko održavanje nakon 1.0.0: čišćenje duplog/mrtvog koda, dodatno UI/UX i branding poliranje te ažurirana tehnička provjera aktualnih pravila o objavi i isticanju cijena.
* Stvarni runtime screenshotovi i distribucijski paketi ponovno se generiraju nakon završnih izmjena.

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
