=== brendigo SIDRENA – sidrene cijene i cjenici za WooCommerce ===
Contributors: brendigo
Donate link: https://revolut.me/catanyus?currency=EUR&amount=1000&note=SIDRENA%20plugin%20-%20donacija
Tags: woocommerce, prices, price-list, csv, xml
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.21
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reference-price records, price history, public CSV/XML price lists, locations and publication archives for Croatian stores using WooCommerce.

== Description ==

**brendigo SIDRENA – sidrene cijene i cjenici za WooCommerce** is an independently developed plugin for Croatian merchants that use WooCommerce products and variations.

The plugin keeps WooCommerce as the canonical product source and adds tools for:

* recording current and reference-price data
* maintaining price history used for 30-day sale-price references when applicable
* handling product data by physical location or webshop channel
* calculating and publishing unit-price data when applicable
* generating public CSV and XML price lists
* publishing a searchable HTML price-list page
* keeping previous successful publications available in an archive
* exposing a public JSON manifest and REST index for automated retrieval
* validating publication data before replacing the last successful public files
* monitoring daily publication and sending failure or delay notifications
* displaying stored file size, row count and SHA-256 integrity metadata
* editing SIDRENA fields in bulk without creating a duplicate product catalogue
* integrating SIDRENA fields with WooCommerce CSV import/export

The legal-publication profile is intentionally automated. CSV, XML, public HTML, manifest, REST index, price history, strict publication checks and publication monitoring cannot be accidentally disabled from the simplified settings screen.

Detailed end-user documentation in Croatian is included in:

* `docs/UPUTE.md`
* `docs/SIDRENA-UPUTE.pdf`

Optional paid initial setup and voluntary donations are clearly separated from plugin functionality. Paying for setup or donating is not required to use any feature.

SIDRENA provides technical tools for recording, checking and publishing price data. It is not legal certification and does not replace the merchant's source records or professional legal advice.

= Independence and trademarks =

This plugin is developed independently by **brendigo**. It is not affiliated with, endorsed by, sponsored by, or an official product of WooCommerce or Automattic.

WooCommerce is mentioned only to identify compatibility and the product-data source used by this edition.

= Privacy =

SIDRENA does not include telemetry or usage tracking.

Public-availability checks use the WordPress HTTP API only for public SIDRENA files hosted by the same WordPress site. The plugin does not require an external account or license server.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the SIDRENA ZIP through **Plugins > Add New Plugin > Upload Plugin**.
3. Activate **brendigo SIDRENA – sidrene cijene i cjenici za WooCommerce**.
4. Open **SIDRENA > Settings** and choose the operating mode, daily generation time, archive retention and notification email.
5. Open **SIDRENA > Locations** and review the webshop or physical-store locations.
6. Review SIDRENA fields on existing products and variations or use the SIDRENA bulk editor.
7. Open **SIDRENA > Check** and resolve any real data warnings.
8. Open **SIDRENA > Price Lists** and generate the first public price list.
9. Open the public price-list page and verify CSV/XML downloads and archive links.
10. Review **SIDRENA > Log** after the first production generation.

The detailed Croatian manual included in the plugin package explains every step for non-technical users.

== Frequently Asked Questions ==

= Does SIDRENA duplicate WooCommerce products? =

No. WooCommerce remains the canonical source for products and variations.

= Does the plugin automatically display reference-price information? =

When the relevant SIDRENA data exists, the plugin can render the configured reference-price information next to the existing product price. Variation-specific data is updated from the standard variation payload, with a compatibility fallback for integrations that remove it.

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
2. Product catalogue and SIDRENA bulk data editor.
3. Current public price-list files and archive controls.
4. Location management for webshop and physical stores.
5. Simplified automated-publication settings.
6. Help, documentation, support and optional setup information.

== Changelog ==

= 1.0.21 =

* Stable production release before the current 1.0.22 development cycle.
* Uses WooCommerce products and variations without a duplicate catalogue.
* Includes public CSV/XML generation, searchable HTML output, publication archives, locations, price history and unit-price support.
* Includes strict publication validation, public file integrity metadata, REST/manifest discovery and publication monitoring.
* Includes real WordPress/WooCommerce runtime QA and Plugin Check gates in the development workflow.

== Upgrade Notice ==

= 1.0.21 =

Stable production release. Back up the site before upgrading and verify the first public generation after installation.
