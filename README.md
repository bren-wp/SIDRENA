# Sidrena 2.0.0

Sidrena je besplatan open-source projekt za sidrene/referentne cijene, strojno čitljive cjenike i javnu arhivu.

Od verzije **2.0.0** projekt se izdaje kao dva potpuno odvojena WordPress plugina.

## Sidrena WordPress

Za običan WordPress bez WooCommercea.

- vlastiti katalog proizvoda
- katalog usluga
- sidrena/referentna cijena
- jedinična cijena
- CSV/XML
- javni HTML cjenik
- REST API
- više lokacija
- arhiva 30+ dana
- WP-Cron i WP-CLI

Instalacijski paket:

`sidrena-wordpress-x.y.z.zip`

Glavni plugin file:

`sidrena-wordpress.php`

## Sidrena WooCommerce

Za WordPress + WooCommerce.

- WooCommerce proizvodi i varijacije
- WooCommerce CSV Import/Export
- povijest cijena
- 30-dnevne reference
- sidrena/referentna cijena
- jedinična cijena
- cijene i raspoloživost po lokaciji
- katalog usluga
- CSV/XML
- javni HTML cjenik
- REST API
- arhiva 30+ dana
- builder/price-output integracije
- WP-Cron i WP-CLI

Instalacijski paket:

`sidrena-woocommerce-x.y.z.zip`

Glavni plugin file:

`sidrena-woocommerce.php`

WooCommerce je obavezna ovisnost ovog izdanja.

## Važno

**Ne aktivirajte oba Sidrena izdanja istodobno.**

Oba paketa koriste isti Sidrena podatkovni prostor, opcije i javnu arhivu kako bi prijelaz između izdanja bio moguć bez dupliranja poslovnih podataka, ali runtime je namjerno međusobno isključiv.

## Razdvajanje koda

Release build fizički uklanja edition-specifične klase koje drugom pluginu nisu potrebne.

Sidrena WordPress ZIP ne sadrži:

- Woo bulk editor
- Woo product fields
- Woo price history
- Woo location data/history
- Woo CSV import/export integraciju
- Woo builder compatibility klasu

Sidrena WooCommerce ZIP ne sadrži:

- standalone WordPress katalog klasu

Zajednički ostaju samo stvarno zajednički dijelovi: usluge, cjenici, REST, javni prikaz, arhiva, audit, admin shell, Site Health i utility layer.

## Build

Lokalno ili u CI-ju:

```bash
./tools/build-editions.sh 2.0.0 /tmp/sidrena-build
```

Dobivaju se:

- `sidrena-wordpress-2.0.0.zip`
- `sidrena-wordpress-2.0.0.zip.sha256`
- `sidrena-woocommerce-2.0.0.zip`
- `sidrena-woocommerce-2.0.0.zip.sha256`

## Dokumentacija

- WordPress izdanje: `docs/UPUTE-WORDPRESS.md`
- WooCommerce izdanje: `docs/UPUTE-WOOCOMMERCE.md`
- pravne/tehničke bilješke: `docs/legal-and-technical-notes.md`

## Projekt

Autor: **Brendigo**

Službena stranica: https://sidrene-cijene.com.hr/

Licenca: GPLv2 or later
