=== Sidrena WordPress ===
Contributors: brendigo
Donate link: https://sidrene-cijene.com.hr/#donirajte
Tags: cijene, cjenik, csv, xml, hrvatska, trgovina, usluge
Requires at least: 6.6
Tested up to: 7.1.2
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sidrene cijene za običan WordPress bez WooCommercea: vlastiti katalog proizvoda i usluga, CSV/XML cjenici i arhiva 30+ dana.

== Description ==

**Sidrena WordPress** je samostalni WordPress plugin za web stranice koje ne koriste WooCommerce.

Ovo izdanje koristi vlastiti Sidrena katalog proizvoda i katalog usluga. WooCommerce proizvodi, WooCommerce history hookovi i WooCommerce CSV integracija nisu dio ovog paketa.

Glavne mogućnosti:

* vlastiti katalog proizvoda
* katalog usluga
* sidrena/referentna cijena
* jedinična cijena
* CSV/XML javni cjenici
* javni HTML cjenik
* REST API
* više lokacija
* arhiva 30+ dana
* SHA-256 integritet
* WP-Cron i WP-CLI
* bez telemetrije i bez Pro paywalla

Ako web koristi WooCommerce, instalirajte zasebni **Sidrena WooCommerce** paket. Nemojte aktivirati oba Sidrena izdanja istodobno.

Službena stranica: https://sidrene-cijene.com.hr/

== Installation ==

1. U WordPressu otvorite Dodaci > Dodaj novi > Prenesi dodatak.
2. Prenesite `sidrena-wordpress-2.0.0.zip`.
3. Aktivirajte **Sidrena WordPress**.
4. Otvorite Sidrena > Katalog i unesite ili uvezite proizvode.
5. Po potrebi otvorite Usluge i Lokacije.
6. Pregledajte Postavke i generirajte prvi cjenik.

Detaljne upute nalaze se u `docs/UPUTE.md` unutar paketa.

== Changelog ==

= 2.0.0 =
* Sidrena je razdvojena na dva zasebna plugina.
* Ovo izdanje više ne učitava niti pakira WooCommerce product/history/import klase.
* Vlastiti WordPress katalog je jedini izvor proizvoda.
* Uklonjen je Static/PHP proizvod i sva njegova dokumentacija/testovi.
* Admin, REST, Site Health i cjenici rade isključivo u WordPress edition modu.
* WordPress paket ne sadrži Woo-specific klase.

== Upgrade Notice ==

= 2.0.0 =
Velika arhitekturna promjena. Za web bez WooCommercea instalirajte Sidrena WordPress 2.0.0. Za WooCommerce trgovinu koristite zasebni Sidrena WooCommerce paket.
