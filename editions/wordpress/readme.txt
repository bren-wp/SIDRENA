=== Sidrena WordPress ===
Contributors: brendigo
Donate link: https://revolut.me/catanyus?currency=EUR&amount=1000&note=Sidrena%20WordPress%20plugin%20-%20donacija
Tags: cijene, cjenik, csv, xml, trgovina
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sidrene cijene, proizvodi i usluge, javni CSV/XML cjenici i arhiva objava za WordPress bez WooCommercea.

== Description ==

Sidrena WordPress 1.0.2 namijenjena je web stranicama koje ne koriste WooCommerce kao izvor proizvoda.

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

1. Prenesite `sidrena-wordpress-1.0.2.zip` kroz Dodaci > Dodaj novi > Prenesi dodatak.
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

= 1.0.2 =
* Uklonjen legacy/mrtvi administratorski CSS koji se više ne koristi na glavnim Sidrena ekranima.
* Smanjeno je dupliciranje PHP navigacije i edition-conflict zaštite.
* Optimiziran je WooCommerce kompatibilni frontend DOM/AJAX sloj kako bi izbjegao redundantne zahtjeve i vlastite mutation cikluse.
* WordPress i WooCommerce zadržavaju jasno odvojene edition akcente uz zajednički SIDRENA brand sustav.
* Produkcijske pravne provjere ostaju tehničke provjere podataka, objave, arhive i raspoloživosti, a ne pravna garancija.

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
