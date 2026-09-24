=== Sidrena WooCommerce ===
Contributors: brendigo
Donate link: https://sidrene-cijene.com.hr/#donirajte
Tags: woocommerce, cijene, cjenik, csv, xml, hrvatska, ecommerce
Requires at least: 6.6
Tested up to: 7.1.2
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sidrene cijene za WooCommerce proizvode i varijacije, usluge, povijest cijena, CSV/XML cjenici i arhiva 30+ dana.

== Description ==

**Sidrena WooCommerce** je izdanje namijenjeno isključivo WordPress stranicama s aktivnim WooCommerceom.

WooCommerce katalog je jedini izvor proizvoda. Samostalni Sidrena WordPress katalog nije dio ovog paketa.

Glavne mogućnosti:

* WooCommerce proizvodi i varijacije
* WooCommerce CSV Import/Export polja
* povijest cijena i 30-dnevne reference
* sidrena/referentna cijena
* jedinična cijena
* cijene i raspoloživost po lokaciji
* katalog usluga
* CSV/XML javni cjenici
* javni HTML cjenik
* REST API
* arhiva 30+ dana i SHA-256
* builder/price-output integracije
* WP-Cron i WP-CLI
* bez telemetrije i bez Pro paywalla

Ovo izdanje zahtijeva WooCommerce. Za web bez WooCommercea instalirajte zasebni **Sidrena WordPress** paket. Nemojte aktivirati oba Sidrena izdanja istodobno.

Službena stranica: https://sidrene-cijene.com.hr/

== Installation ==

1. Instalirajte i aktivirajte WooCommerce.
2. U WordPressu otvorite Dodaci > Dodaj novi > Prenesi dodatak.
3. Prenesite `sidrena-woocommerce-2.0.0.zip`.
4. Aktivirajte **Sidrena WooCommerce**.
5. Otvorite Sidrena > Katalog i pregledajte WooCommerce proizvode.
6. Pregledajte Postavke, Lokacije i Alate.
7. Generirajte prvi cjenik.

Detaljne upute nalaze se u `docs/UPUTE.md` unutar paketa.

== Changelog ==

= 2.0.0 =
* Sidrena je razdvojena na dva zasebna plugina.
* Ovo izdanje deklarira WooCommerce kao obaveznu ovisnost.
* WooCommerce katalog je jedini izvor proizvoda.
* Samostalni WordPress katalog nije dio niti ZIP-a niti runtimea.
* Woo history, location, bulk, CSV import/export i compatibility moduli učitavaju se samo u ovom izdanju.

== Upgrade Notice ==

= 2.0.0 =
Velika arhitekturna promjena. Za WooCommerce trgovine instalirajte Sidrena WooCommerce 2.0.0. Za web bez WooCommercea koristite zasebni Sidrena WordPress paket.
