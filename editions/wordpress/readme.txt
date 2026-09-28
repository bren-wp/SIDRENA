=== brendigo Cjenikomat – sidrene cijene i digitalni cjenici ===
Contributors: brendigo
Donate link: https://revolut.me/catanyus?currency=EUR&amount=1000&note=SIDRENA%20plugin%20-%20donacija
Tags: prices, price-list, croatia, csv, xml
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.22
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reference prices, 30-day sale-price references, products and services, public CSV/XML price lists, locations, and publication archives for the Croatian market.

== Description ==

**brendigo Cjenikomat – sidrene cijene i digitalni cjenici** is the standalone SIDRENA edition for Croatian WordPress sites that need a dedicated product and service price catalogue without using an ecommerce catalogue as the source.

The plugin provides:

* a dedicated product and service catalogue
* current and reference-price records
* a separate 30-day reference for special sale-price situations when applicable
* multiple physical locations and webshop channels
* unit-price calculation and publication when applicable
* CSV/XML catalogue import and export
* automatic public CSV and XML price-list generation
* a searchable public HTML price-list page
* an archive of previous successful publications
* a JSON manifest and REST index for automated retrieval
* validation before replacing the last valid public publication
* scheduled daily generation, publication monitoring, and email alerts
* stored file size, row count, and SHA-256 integrity metadata

= Automated publication profile =

Important publication and technical outputs are automated so an end user cannot accidentally disable them. CSV, XML, public HTML, the JSON manifest, REST index, price history, strict publication checks, and publication monitoring remain enabled.

The simplified settings screen only asks the user to choose the business mode, a daily generation time before 08:00, an archive retention period of at least 30 days, and an alert email address.

= Croatian end-user documentation =

The installable package includes detailed Croatian documentation:

* `docs/UPUTE.md` — detailed step-by-step text guide
* `docs/SIDRENA-UPUTE.pdf` — detailed PDF manual for non-technical end users

The guide covers installation, first-time setup, locations, products and services, reference prices, 30-day sale-price references, unit prices, price-list generation, public publication, archives, the audit log, cron, alerts, and troubleshooting.

= Support, donation, and optional setup =

Support email: sidrena@brendigo.com

WhatsApp: +385 91 901 0092

Optional one-time initial setup: **80 EUR**.

Paid setup is not required to use the plugin. A voluntary donation supports continued development and does not unlock features, support rights, or legal certification.

= Legal note =

SIDRENA provides technical tools for recording, checking, automating, and publishing price data. It does not provide legal certification or replace the merchant's source records or professional legal advice.

== Installation ==

1. In WordPress, open **Plugins > Add New Plugin > Upload Plugin**.
2. Upload the current standalone SIDRENA ZIP package.
3. Activate the plugin.
4. Open **SIDRENA > Settings** and choose the operating mode.
5. Configure **Locations**.
6. Add the first product or service.
7. Open **Check** and resolve genuine data warnings.
8. Generate the first publication under **Price Lists**.
9. Verify the public CSV/XML files, archive, and audit log.

Detailed Croatian instructions are included in `docs/UPUTE.md` and `docs/SIDRENA-UPUTE.pdf`.

== Frequently Asked Questions ==

= Does this edition require WooCommerce? =

No. This edition uses its own SIDRENA product and service catalogue.

= Can I disable CSV/XML output or publication monitoring? =

Not from the simplified end-user settings screen. Those technical outputs and safeguards stay enabled to prevent accidental misconfiguration.

= Is a SIDRENA reference price the same as the lowest price in the previous 30 days? =

No. SIDRENA stores them as separate concepts and displays them only when the relevant rule is applicable.

= Is the 80 EUR initial setup required? =

No. It is a completely optional service. The plugin can be installed and configured independently using the included Croatian documentation.

== Screenshots ==

1. SIDRENA dashboard and publication status.
2. Standalone product catalogue.
3. Digital price lists and publication archive.
4. Locations and sales channels.
5. Simplified automated settings.
6. Support, documentation, and optional services.

== Changelog ==

= 1.0.22 =

* Simplified the Croatian end-user settings screen and locked publication-critical safeguards to automatic safe defaults.
* Focused the plugin on reference prices, 30-day sale-price references, digital price lists, locations, unit prices, archives, automated retrieval, and publication audit data.
* Added public file integrity metadata, improved archive/download layouts, and an edition-specific detailed Croatian PDF manual for non-technical users.
* Added validated unit/alias extension hooks while preserving existing built-in conversions.
* Updated WordPress.org-facing naming, slugs, branding, admin notices, and readme copy based on reviewer feedback.
* Passed PHP 7.4/8.3/8.4 CI, distribution/admin/legal guards, strict Plugin Check for both production editions, and real WordPress browser/runtime QA before the version bump.

= 1.0.21 =

* Stabilized both production editions and their shared publication system.
* Improved digital price lists, archives, locations, integrity metadata, and the public price-list view.
* Added detailed Croatian end-user documentation.
* Strengthened CI, Plugin Check, and production regression guards.

= 1.0.20 =

* Improved reference-price rules, 30-day references, and unit-price handling.
* Improved import/export, public publication, and security checks.
