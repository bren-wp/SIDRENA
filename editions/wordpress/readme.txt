=== brendigo SIDRENA – sidrene cijene i digitalni cjenici ===
Contributors: brendigo
Donate link: https://revolut.me/catanyus?currency=EUR&amount=1000&note=SIDRENA%20plugin%20-%20donacija
Tags: cijene, cjenik, hrvatska, csv, xml
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.21
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sidrene cijene, 30-dnevna referenca, proizvodi i usluge, javni CSV/XML cjenici, lokacije i arhiva objava za hrvatsko tržište.

== Description ==

**brendigo SIDRENA – sidrene cijene i digitalni cjenici** je samostalno izdanje za hrvatske WordPress stranice koje trebaju vlastiti katalog proizvoda i usluga bez vanjskog kataloga trgovine kao izvora podataka.

Dodatak omogućuje:

* vlastiti katalog proizvoda i usluga
* evidenciju aktualnih i sidrenih cijena
* odvojenu 30-dnevnu referencu kod posebnih oblika prodaje kada je primjenjiva
* više fizičkih lokacija i webshop kanala
* izračun i objavu cijene za jedinicu mjere kada je primjenjiva
* CSV/XML uvoz i izvoz kataloga
* automatsko generiranje javnih CSV i XML cjenika
* javnu pretraživu HTML stranicu cjenika
* čuvanje prethodnih uspješnih objava u arhivi
* JSON manifest i REST indeks za automatizirani dohvat
* provjeru podataka prije zamjene zadnje valjane javne objave
* dnevno generiranje, nadzor objave i e-mail upozorenja
* prikaz veličine datoteke, broja redaka i SHA-256 podatka o integritetu

= Automatizirani profil objave =

Zakonski i tehnički važni izlazi namjerno su automatizirani kako ih krajnji korisnik ne bi slučajno isključio. CSV, XML, javni HTML cjenik, manifest, REST indeks, povijest cijena, stroga provjera objave i nadzor objave ostaju uključeni.

Korisnik u postavkama bira samo način rada, vrijeme dnevnog generiranja prije 08:00, duljinu arhive od najmanje 30 dana i e-mail za upozorenja.

= Upute za krajnjeg korisnika =

U instalacijskom ZIP-u nalaze se:

* `docs/UPUTE.md` — detaljne tekstualne upute
* `docs/SIDRENA-UPUTE.pdf` — detaljni PDF priručnik za krajnjeg korisnika

Upute obuhvaćaju instalaciju, prvo postavljanje, lokacije, unos proizvoda i usluga, sidrenu cijenu, 30-dnevnu referencu, jediničnu cijenu, generiranje cjenika, javnu objavu, arhivu, Dnevnik, cron, upozorenja i rješavanje najčešćih problema.

= Podrška, donacija i opcionalno postavljanje =

Podrška: sidrena@brendigo.com

WhatsApp: +385 91 901 0092

Opcionalno jednokratno početno postavljanje: **80 EUR**.

Plaćeno postavljanje nije uvjet za korištenje dodatka. Dobrovoljna donacija podržava razvoj i nije naknada za funkcije, podršku ili pravno jamstvo.

= Pravna napomena =

SIDRENA tehnički pomaže pri unosu, provjeri, evidenciji, automatizaciji i objavi podataka. Ne predstavlja pravno jamstvo, pravnu certifikaciju niti zamjenu za stvarnu poslovnu evidenciju i stručni pravni savjet.

== Installation ==

1. U WordPressu otvorite **Dodaci > Dodaj novi dodatak > Prenesi dodatak**.
2. Prenesite aktualni ZIP paket za samostalno SIDRENA izdanje.
3. Aktivirajte dodatak.
4. Otvorite **SIDRENA > Postavke** i odaberite način rada.
5. Uredite **Lokacije**.
6. Unesite prvi proizvod ili uslugu.
7. Otvorite **Provjera** i riješite stvarna upozorenja.
8. U **Cjenici** generirajte prvu objavu.
9. Provjerite javne CSV/XML datoteke, arhivu i Dnevnik.

Detaljni postupak nalazi se u `docs/UPUTE.md` i `docs/SIDRENA-UPUTE.pdf`.

== Frequently Asked Questions ==

= Treba li WooCommerce? =

Ne. Ovo izdanje koristi vlastiti SIDRENA katalog proizvoda i usluga.

= Mogu li isključiti CSV/XML ili nadzor objave? =

Ne kroz pojednostavljeni korisnički ekran. Ti izlazi i sigurnosni mehanizmi ostaju uključeni kako ih krajnji korisnik ne bi slučajno onemogućio.

= Je li sidrena cijena isto što i najniža cijena u prethodnih 30 dana? =

Ne. SIDRENA ih vodi kao odvojene podatke i prikazuje ih samo kada su za konkretnu situaciju stvarno primjenjivi.

= Moram li platiti početno postavljanje? =

Ne. Početno postavljanje od 80 EUR je potpuno opcionalna usluga. Dodatak se može samostalno instalirati i koristiti prema priloženim uputama.

== Screenshots ==

1. SIDRENA pregled i status objave.
2. Samostalni katalog proizvoda.
3. Digitalni cjenici i arhiva.
4. Lokacije i prodajni objekti.
5. Pojednostavljene automatizirane postavke.
6. Podrška, dokumentacija i opcionalne usluge.

== Changelog ==

= 1.0.21 =

* Stabilizirana oba produkcijska izdanja i njihov zajednički sustav objave.
* Poboljšani digitalni cjenici, arhiva, lokacije, provjera integriteta i javni prikaz.
* Dodana detaljna dokumentacija za krajnjeg korisnika.
* Pojačani CI, Plugin Check i produkcijski regression guardovi.

= 1.0.20 =

* Poboljšana pravila sidrenih cijena, 30-dnevne reference i cijene za jedinicu mjere.
* Poboljšani import/export, javna objava i sigurnosne provjere.
