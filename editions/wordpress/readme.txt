=== brendigo SIDRENA – sidrene cijene i digitalni cjenici ===
Contributors: brendigo
Donate link: https://revolut.me/catanyus?currency=EUR&amount=1000&note=SIDRENA%20plugin%20-%20donacija
Tags: prices, price-list, csv, xml, catalog
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.21
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reference-price records, products and services, public CSV/XML price lists, locations and publication archives for Croatian WordPress sites.

== Description ==

**brendigo SIDRENA – sidrene cijene i digitalni cjenici** is the standalone SIDRENA edition for Croatian WordPress sites that need a structured product/service price catalogue without using an ecommerce catalogue as the source.

The plugin provides tools for:

* managing a dedicated product and service catalogue
* recording current and reference-price data
* maintaining price history used for 30-day sale-price references when applicable
* handling multiple physical locations and webshop channels
* calculating and publishing unit-price data when applicable
* importing and exporting catalogue data through CSV/XML workflows
* generating public CSV and XML price lists
* publishing a searchable HTML price-list page
* keeping previous successful publications available in an archive
* exposing a public JSON manifest and REST index for automated retrieval
* validating publication data before replacing the last successful public files
* monitoring daily publication and sending failure or delay notifications
* displaying stored file size, row count and SHA-256 integrity metadata

The legal-publication profile is intentionally automated. CSV, XML, public HTML, manifest, REST index, price history, strict publication checks and publication monitoring cannot be accidentally disabled from the simplified settings screen.

Detailed end-user documentation in Croatian is included in:

* `docs/UPUTE.md`
* `docs/SIDRENA-UPUTE.pdf`

Optional paid initial setup and voluntary donations are clearly separated from plugin functionality. Paying for setup or donating is not required to use any feature.

SIDRENA provides technical tools for recording, checking and publishing price data. It is not legal certification and does not replace the merchant's source records or professional legal advice.

= Privacy =

SIDRENA does not include telemetry or usage tracking.

Public-availability checks use the WordPress HTTP API only for public SIDRENA files hosted by the same WordPress site. The plugin does not require an external account or license server.

== Installation ==

1. Upload the SIDRENA ZIP through **Plugins > Add New Plugin > Upload Plugin**.
2. Activate **brendigo SIDRENA – sidrene cijene i digitalni cjenici**.
3. Open **SIDRENA > Settings** and choose the operating mode, daily generation time, archive retention and notification email.
4. Open **SIDRENA > Locations** and create the real business locations that should publish price-list files.
5. Open **SIDRENA > Catalog** and add the first product, or import a prepared catalogue.
6. Open **SIDRENA > Services** if services are part of the published catalogue.
7. Open **SIDRENA > Check** and resolve any real data warnings.
8. Open **SIDRENA > Price Lists** and generate the first public price list.
9. Open the public price-list page and verify CSV/XML downloads and archive links.
10. Review **SIDRENA > Log** after the first production generation.

The detailed Croatian manual included in the plugin package explains every step for non-technical users.

== Frequently Asked Questions ==

= Does this edition require WooCommerce? =

No. This is the standalone edition and uses its own SIDRENA product/service catalogue.

= Does SIDRENA guarantee legal compliance? =

No. SIDRENA automates technical recording, validation and publication workflows. The merchant remains responsible for accurate source data and for determining which legal rules apply to each product, service, sale, location and business model.

= Does the plugin track visitors or administrators? =

No. There is no telemetry or usage tracking.

= Is paid setup required? =

No. All plugin functionality is available without paid setup. The optional one-time setup service is separate from the plugin.

= Where are the Croatian instructions? =

The install package contains `docs/UPUTE.md` and `docs/SIDRENA-UPUTE.pdf`.

== Screenshots ==

1. SIDRENA overview with publication status and operational checks.
2. Standalone product catalogue.
3. Current public price-list files and archive controls.
4. Location management.
5. Simplified automated-publication settings.
6. Help, documentation, support and optional setup information.

== Changelog ==

= 1.0.21 =

* Stable production release before the current 1.0.22 development cycle.
* Includes standalone products and services, public CSV/XML generation, searchable HTML output, publication archives and locations.
* Includes price history, unit-price support, strict publication validation and public file integrity metadata.
* Includes REST/manifest discovery, publication monitoring and automated legal-publication defaults.
* Includes real WordPress runtime QA and Plugin Check gates in the development workflow.

== Upgrade Notice ==

= 1.0.21 =

Stable production release. Back up the site before upgrading and verify the first public generation after installation.
