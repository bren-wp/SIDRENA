=== brendigo SIDRENA – sidrene cijene i cjenici za WooCommerce ===
Contributors: brendigo
Donate link: https://revolut.me/catanyus?currency=EUR&amount=1000&note=SIDRENA%20plugin%20-%20donacija
Tags: woocommerce, cijene, cjenik, hrvatska, csv
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.21
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sidrene cijene, 30-dnevna referenca, javni CSV/XML cjenici, lokacije i arhiva objava za hrvatske trgovine koje koriste WooCommerce.

== Description ==

**brendigo SIDRENA – sidrene cijene i cjenici za WooCommerce** je neovisno razvijeno izdanje za hrvatske trgovce koji već koriste WooCommerce proizvode i varijacije.

WooCommerce ostaje izvor proizvoda i varijacija. SIDRENA ne stvara drugi paralelni katalog, nego postojećim proizvodima dodaje podatke i alate potrebne za evidenciju i objavu cijena.

Dodatak omogućuje:

* evidenciju aktualnih i sidrenih cijena uz postojeće proizvode i varijacije
* odvojenu 30-dnevnu referencu kod posebnih oblika prodaje kada je primjenjiva
* podatke o raspoloživosti i cijeni po fizičkoj lokaciji
* izračun i objavu cijene za jedinicu mjere kada je primjenjiva
* javne CSV i XML cjenike
* javnu pretraživu HTML stranicu cjenika
* arhivu prethodnih uspješnih objava
* JSON manifest i REST indeks za automatizirani dohvat
* strogu provjeru prije zamjene zadnje valjane objave
* dnevno generiranje, nadzor objave i e-mail upozorenja
* bulk uređivanje SIDRENA podataka bez dupliciranja proizvoda
* integraciju SIDRENA polja s WooCommerce CSV uvozom i izvozom
* prikaz veličine datoteke, broja redaka i SHA-256 podatka o integritetu

= Automatizirani profil objave =

CSV, XML, javni HTML cjenik, manifest, REST indeks, povijest cijena, stroga provjera objave i nadzor objave ostaju automatski uključeni kako ih krajnji korisnik ne bi slučajno onemogućio.

Korisnik podešava samo način rada, vrijeme dnevnog generiranja prije 08:00, arhivu od najmanje 30 dana i e-mail za upozorenja.

= Neovisnost i zaštitni znakovi =

Ovaj dodatak razvija **brendigo** neovisno. Nije povezan s tvrtkom Automattic niti predstavlja službeni proizvod ili službeno izdanje WooCommercea.

Naziv WooCommerce koristi se samo radi točnog opisa kompatibilnosti i integracije.

= Upute za krajnjeg korisnika =

U instalacijskom ZIP-u nalaze se:

* `docs/UPUTE.md` — detaljne tekstualne upute
* `docs/SIDRENA-UPUTE.pdf` — detaljni PDF priručnik za krajnjeg korisnika

Upute obuhvaćaju instalaciju, postojeće proizvode i varijacije, bulk uređivanje, lokacije, sidrenu cijenu, 30-dnevnu referencu, jediničnu cijenu, CSV uvoz/izvoz, generiranje, javnu objavu, arhivu, cron, Dnevnik i rješavanje najčešćih problema.

= Podrška, donacija i opcionalno postavljanje =

Podrška: sidrena@brendigo.com

WhatsApp: +385 91 901 0092

Opcionalno jednokratno početno postavljanje: **80 EUR**.

Plaćeno postavljanje nije uvjet za korištenje dodatka. Dobrovoljna donacija podržava razvoj i nije naknada za funkcije, podršku ili pravno jamstvo.

= Pravna napomena =

SIDRENA tehnički pomaže pri unosu, provjeri, evidenciji, automatizaciji i objavi podataka. Ne predstavlja pravno jamstvo, pravnu certifikaciju niti zamjenu za stvarnu poslovnu evidenciju i stručni pravni savjet.

== Installation ==

1. Instalirajte i aktivirajte WooCommerce.
2. U WordPressu otvorite **Dodaci > Dodaj novi dodatak > Prenesi dodatak**.
3. Prenesite aktualni ZIP paket SIDRENA izdanja za WooCommerce.
4. Aktivirajte dodatak.
5. Otvorite **SIDRENA > Postavke**.
6. Uredite **Lokacije** i po potrebi popunite prijedlog adrese web trgovine.
7. Otvorite postojeći proizvod ili SIDRENA bulk katalog i dopunite stvarne podatke.
8. Otvorite **Provjera** i riješite stvarna upozorenja.
9. U **Cjenici** generirajte prvu objavu.
10. Provjerite javne CSV/XML datoteke, arhivu i Dnevnik.

Detaljni postupak nalazi se u `docs/UPUTE.md` i `docs/SIDRENA-UPUTE.pdf`.

== Frequently Asked Questions ==

= Duplira li SIDRENA proizvode? =

Ne. Postojeći WooCommerce proizvodi i varijacije ostaju izvor podataka.

= Mora li REST biti uključen da bi promjena varijacije prikazala ispravnu referentnu cijenu? =

Standardni variation payload sadrži SIDRENA podatke bez dodatnog zahtjeva. REST ostaje kompatibilni fallback za teme i buildere koji uklone standardni payload.

= Mogu li isključiti CSV/XML ili nadzor objave? =

Ne kroz pojednostavljeni korisnički ekran. Ti izlazi i sigurnosni mehanizmi ostaju uključeni radi stabilne objave i interoperabilnosti.

= Je li sidrena cijena isto što i najniža cijena u prethodnih 30 dana? =

Ne. To su odvojeni podaci.

= Moram li platiti početno postavljanje? =

Ne. Početno postavljanje od 80 EUR je potpuno opcionalno. Dodatak se može samostalno instalirati i koristiti prema priloženim uputama.

== Screenshots ==

1. SIDRENA pregled i status objave.
2. Katalog web trgovine i SIDRENA podaci.
3. Digitalni cjenici i arhiva.
4. Lokacije i prodajni objekti.
5. Pojednostavljene automatizirane postavke.
6. Podrška, dokumentacija i opcionalne usluge.

== Changelog ==

= 1.0.21 =

* Stabilizirano izdanje za postojeće proizvode i varijacije trgovine.
* Poboljšani prikaz varijacija bez dodatnog zahtjeva u standardnom WooCommerce toku.
* Poboljšani digitalni cjenici, arhiva, lokacije i provjera integriteta.
* Dodana detaljna dokumentacija za krajnjeg korisnika.
* Pojačani CI, Plugin Check i produkcijski regression guardovi.

= 1.0.20 =

* Poboljšana povijest cijena, 30-dnevna referenca i cijena za jedinicu mjere.
* Poboljšani CSV uvoz/izvoz, lokacijski podaci i javna objava.
