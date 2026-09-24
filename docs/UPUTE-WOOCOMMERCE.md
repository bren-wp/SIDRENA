# Sidrena WooCommerce 2.0.0 — Upute za korištenje

## Namjena

Sidrena WooCommerce je izdanje isključivo za WordPress trgovine s aktivnim WooCommerceom. WooCommerce proizvodi i varijacije jedini su izvor proizvoda.

Za web bez WooCommercea koristite Sidrena WordPress.

## Instalacija

1. Instalirajte i aktivirajte WooCommerce.
2. Preuzmite `sidrena-woocommerce-2.0.0.zip`.
3. WordPress > Dodaci > Dodaj novi > Prenesi dodatak.
4. Aktivirajte **Sidrena WooCommerce**.
5. Otvorite **Sidrena** u lijevom admin meniju.

## WooCommerce proizvodi

Otvorite **Sidrena > Katalog** za masovno uređivanje Sidrena polja. Dodatna polja dostupna su i na WooCommerce proizvodu/varijaciji.

Podržani su:
- šifra i marka
- barkod
- sidrena cijena i datum
- referentna skupina
- jedinična cijena i pakiranje
- naziv posebnog oblika prodaje

## Povijest cijena

WooCommerce izdanje prati dostupnu lokalnu povijest cijena i promjene cijena proizvoda/varijacija. Povijest se ne rekonstruira iz nepostojećih podataka.

## WooCommerce CSV

Sidrena polja dostupna su u WooCommerce CSV Import/Export procesu.

## Lokacije

Za fizičke lokacije mogu se voditi cijena, sidrena cijena i raspoloživost po proizvodu/varijaciji.

## Usluge

Sidrena usluge rade i u WooCommerce izdanju kao zaseban katalog usluga.

## Cjenici i arhiva

CSV/XML, javni HTML, REST i arhiva koriste WooCommerce katalog kao izvor proizvoda. Standalone Sidrena proizvodi ne postoje u ovom izdanju.

## Automatizacija

Dostupni su WP-Cron i WP-CLI:

`wp sidrena generate`

`wp sidrena status`

`wp sidrena audit`

## Zamjena izdanja

Sidrena WooCommerce i Sidrena WordPress ne smiju biti aktivni istodobno. Prije promjene izdanja napravite sigurnosnu kopiju i provjerite javne cjenike.


## Deinstalacija i podaci

Brisanje plugin paketa po defaultu **ne briše Sidrena poslovne podatke**, postavke, tablice ni zajedničke capabilityje. To omogućuje siguran prijelaz između Sidrena WooCommerce i Sidrena WordPress izdanja.

Ako želite trajno ukloniti Sidrena bazne podatke nakon što nijedno izdanje više nije aktivno, prije brisanja u `wp-config.php` postavite:

`define( 'SIDRENA_DELETE_DATA_ON_UNINSTALL', true );`

Javna CSV/XML arhiva i sadržaj proizvoda/usluga namjerno se ne brišu automatski.


## Nadogradnja sa Sidrena 1.x

Sidrena 2.0 koristi zaseban WooCommerce plugin slug.

Preporučeni redoslijed:

1. Napravite backup baze i `wp-content/uploads/sidrena/`.
2. Provjerite da je WooCommerce aktivan.
3. Na stranici Dodaci **deaktivirajte** stari Sidrena 1.x.
4. Nemojte prvo kliknuti **Obriši** na starom 1.x paketu: starije verzije imaju staru uninstall logiku koja može obrisati zajedničke Sidrena opcije/tablice.
5. Instalirajte i aktivirajte `sidrena-woocommerce-2.0.0.zip`.
6. Provjerite WooCommerce proizvode, Sidrena polja, povijest cijena, Lokacije, Arhivu i javni cjenik.
7. Tek nakon provjere uklonite stari 1.x folder kroz hosting File Manager/SFTP ili ga ostavite deaktiviranog do sigurnog održavanja.

WooCommerce post meta, Sidrena postavke, povijesne tablice, usluge i arhiva koriste isti `sidrena_` podatkovni prostor i 2.0 ih može nastaviti koristiti.
