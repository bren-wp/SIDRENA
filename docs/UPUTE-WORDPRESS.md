# Sidrena WordPress 2.0.0 — Upute za korištenje

## Namjena

Sidrena WordPress je izdanje za WordPress web stranice **bez WooCommercea**. Plugin ima vlastiti katalog proizvoda i katalog usluga.

Ako stranica koristi WooCommerce kao stvarni izvor proizvoda, nemojte koristiti ovo izdanje — instalirajte Sidrena WooCommerce.

## Instalacija

1. Preuzmite `sidrena-wordpress-2.0.0.zip`.
2. WordPress > Dodaci > Dodaj novi > Prenesi dodatak.
3. Aktivirajte **Sidrena WordPress**.
4. U lijevom admin meniju otvorite **Sidrena**.

## Katalog proizvoda

Otvorite **Sidrena > Katalog**.

Proizvode možete:
- unositi ručno
- uređivati u Sidrena katalogu
- uvoziti iz CSV/XML datoteke
- označiti dostupnost
- unijeti šifru, marku, barkod, cijenu, sidrenu cijenu i jediničnu cijenu

WooCommerce proizvodi se u ovom izdanju namjerno ne čitaju.

## Usluge

Usluge su zaseban WordPress post type i mogu se koristiti uz proizvode ili samostalno.

## Cjenici

Sidrena može generirati CSV i XML, javni HTML prikaz i REST podatke. Prethodne objave čuvaju se prema postavljenoj politici arhive, najmanje 30 dana.

## Lokacije

Lokacije određuju zasebne izlaze i naziv datoteka. WordPress izdanje ne koristi WooCommerce tablicu cijena/raspoloživosti po lokaciji.

## Automatizacija

Dostupni su WP-Cron i WP-CLI:

`wp sidrena generate`

`wp sidrena status`

`wp sidrena audit`

## Zamjena izdanja

Sidrena WordPress i Sidrena WooCommerce ne smiju biti aktivni istodobno.

Ako prelazite na WooCommerce:
1. napravite sigurnosnu kopiju
2. deaktivirajte Sidrena WordPress
3. instalirajte i aktivirajte WooCommerce
4. instalirajte Sidrena WooCommerce
5. provjerite podatke prije generiranja novih javnih cjenika


## Deinstalacija i podaci

Brisanje plugin paketa po defaultu **ne briše Sidrena poslovne podatke**, postavke, tablice ni zajedničke capabilityje. To omogućuje siguran prijelaz između Sidrena WordPress i Sidrena WooCommerce izdanja.

Ako želite trajno ukloniti Sidrena bazne podatke nakon što nijedno izdanje više nije aktivno, prije brisanja u `wp-config.php` postavite:

`define( 'SIDRENA_DELETE_DATA_ON_UNINSTALL', true );`

Javna CSV/XML arhiva i sadržaj proizvoda/usluga namjerno se ne brišu automatski.


## Nadogradnja sa Sidrena 1.x

Sidrena 2.0 koristi dva nova plugin sluga, zato prijelaz nije klasična zamjena ZIP-a preko starog plugina.

Preporučeni redoslijed:

1. Napravite backup baze i `wp-content/uploads/sidrena/`.
2. Na stranici Dodaci **deaktivirajte** stari Sidrena 1.x.
3. Nemojte prvo kliknuti **Obriši** na starom 1.x paketu: starije verzije imaju staru uninstall logiku koja može obrisati zajedničke Sidrena opcije/tablice.
4. Instalirajte i aktivirajte `sidrena-wordpress-2.0.0.zip`.
5. Provjerite Katalog, Usluge, Lokacije, Postavke, Arhivu i javni cjenik.
6. Tek nakon provjere uklonite stari 1.x folder kroz hosting File Manager/SFTP ili ga ostavite deaktiviranog do sigurnog održavanja.

Postojeći Sidrena proizvodi, usluge, post meta, postavke i arhiva koriste isti `sidrena_` podatkovni prostor i 2.0 ih može nastaviti koristiti.
