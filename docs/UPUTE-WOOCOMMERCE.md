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
