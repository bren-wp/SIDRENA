=== Sidrena WooCommerce ===
Contributors: brendigo
Donate link: https://revolut.me/catanyus?currency=EUR&amount=1000&note=Sidrena%20WooCommerce%20plugin%20-%20donacija
Tags: woocommerce, cijene, cjenik, csv, xml
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sidrene cijene za WooCommerce proizvode i usluge, javni CSV/XML cjenici, povijest cijena i arhiva objava.

== Description ==

Sidrena WooCommerce 0.8.1 koristi postojeće WooCommerce proizvode i varijacije kao izvor proizvoda.

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
2. Prenesite `sidrena-woocommerce-0.8.1.zip` kroz Dodaci > Dodaj novi > Prenesi dodatak.
3. Aktivirajte Sidrena WooCommerce.
4. Otvorite postojeći WooCommerce proizvod ili varijaciju i unesite Sidrena podatke.
5. Provjerite Usluge ako ih objavljujete.
6. Unesite Lokacije / webshop.
7. Provjerite Zakonske postavke.
8. U Objavi cjenika generirajte prvu objavu i provjerite javni prikaz.

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

1. Stvarni Sidrena WooCommerce početni ekran snimljen iz aktivnog WordPress + WooCommerce wp-admin sučelja.
2. Stvarni prikaz Sidrena rada s WooCommerce proizvodima.
3. Stvarni prikaz Objave cjenika i javnih datoteka.
4. Stvarni prikaz lokacija / webshopa u WooCommerce izdanju.

== Changelog ==

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
