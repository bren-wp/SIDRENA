=== Sidrena WordPress ===
Contributors: brendigo
Donate link: https://revolut.me/catanyus?currency=EUR&amount=1000&note=Sidrena%20WordPress%20plugin%20-%20donacija
Tags: cijene, cjenik, csv, xml, trgovina
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sidrene cijene, proizvodi i usluge, javni CSV/XML cjenici i arhiva objava za WordPress bez WooCommercea.

== Description ==

Sidrena WordPress 0.8.1 namijenjena je web stranicama koje ne koriste WooCommerce kao izvor proizvoda.

* vlastiti katalog proizvoda i usluga ili povezivanje postojećeg javnog WordPress sadržaja
* sidrena/referentna cijena, aktualna cijena i jedinična cijena kada je primjenjiva
* javni CSV/XML cjenici i pretraživi HTML prikaz
* najmanje 30 dana javne arhive prethodnih objava
* zasebne lokacije i webshop
* dnevno automatsko generiranje, zadano u 06:30
* sigurnosna provjera propuštene objave
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

1. Prenesite `sidrena-wordpress-0.8.1.zip` kroz Dodaci > Dodaj novi > Prenesi dodatak.
2. Aktivirajte Sidrena WordPress.
3. Otvorite Sidrena > Proizvodi i povežite postojeći sadržaj ili unesite/uvezite katalog.
4. Provjerite Usluge ako ih objavljujete.
5. Unesite Lokacije / webshop.
6. Provjerite Zakonske postavke.
7. U Objavi cjenika generirajte prvu objavu i provjerite javni prikaz.
8. Na Početku provjerite tehničku spremnost i raspored.

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

1. Stvarni Sidrena WordPress administracijski ekran s kompaktnim zaglavljem, pregledom statusa i sidro ikonicom u WordPress meniju.

== Changelog ==

= 0.8.1 =
* Dodana lokalna sidro ikonica u WordPress bočni meni.
* Uklonjen mrtvi legacy admin menu i popravljeni linkovi prema sekundarnim prikazima.
* Admin zaglavlje pojednostavljeno je na mali Sidrena logo i jasan edition badge.
* Službeni Plugin URI promijenjen je na https://brendigo.com/sidrene-cijene/.
* Dokumentacija, WP.org readme i stvarni screenshotovi usklađeni su s trenutačnim sučeljem.
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
