=== SIDRENA ===
Contributors: brendigo
Tags: prices, price-list, croatia, csv, xml
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SIDRENA anchor prices, unlimited price-change history, products, services, CSV/XML price lists, locations and archives for Croatian WordPress sites.

== Description ==

**SIDRENA** is the standalone WordPress edition for Croatian WordPress sites that need a dedicated product and service price catalogue without using an ecommerce catalogue as the source.

The plugin provides:

* a dedicated product and service catalogue
* current and reference-price records
* an unlimited audit history of actual price changes, kept separate from the SIDRENA anchor-price ruleset
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

The Croatian machine-readable price-list rule accepts XML or CSV. SIDRENA generates both formats by design for interoperability; this does not state that the rule requires both formats at the same time.

The simplified settings screen asks the user to choose the business mode, the daily publication scheduler (internal WP-Cron or external server cron/WP-CLI), a generation time before 08:00, archive retention of at least 30 days, and an alert email address.

= Croatian end-user documentation =

The installable package includes detailed Croatian documentation:

* `docs/UPUTE.md` — detailed step-by-step text guide
* `docs/SIDRENA-UPUTE.pdf` — detailed PDF manual for non-technical end users

The guide covers installation, first-time setup, locations, products and services, SIDRENA anchor prices, price-change history, unit prices, price-list generation, public publication, archives, the audit log, cron, alerts, and troubleshooting.

= Support and optional setup =

Support email: sidrena@brendigo.com

WhatsApp: +385 91 901 0092

Optional one-time initial setup: **80 EUR**.

Paid setup is not required to use the plugin and does not unlock features, support rights, or legal certification.

== External services and user-initiated links ==

SIDRENA does not require a remote API or SaaS account for its core price-list functions, and it does not send telemetry or usage analytics.

When an administrator explicitly runs the public-access check, the plugin uses the WordPress HTTP API only to request public SIDRENA price-list URLs generated on the same WordPress site. No product/customer personal data is sent to brendigo or to a third-party API by that check; the request contains only normal HTTP request metadata and a SIDRENA user-agent string.

The administration and bundled documentation also contain optional, user-initiated external links. SIDRENA does not fetch these destinations in the background. When an administrator clicks one of these links, their browser or mail application opens the destination directly. The destination may then receive normal connection/request data such as the visitor IP address, browser user-agent, referrer information allowed by the browser, and any cookies already associated with that destination. SIDRENA does not append catalogue, product, customer, order, or price-list data to those links.

* **brendigo** — plugin website, documentation/support, and optional setup information. Terms: https://brendigo.com/uvjeti-koristenja/ Privacy: https://brendigo.com/politika-privatnosti/
* **WhatsApp** — optional support link to `wa.me` with only the static pre-filled text "Pozdrav, trebam podršku za Sidrena plugin." The administrator decides whether to continue/send in WhatsApp. Terms: https://www.whatsapp.com/legal/terms-of-service?lang=hr Privacy: https://www.whatsapp.com/legal/privacy-policy?lang=hr
* **Official Croatian legal/information references** — links to Narodne novine (`narodne-novine.nn.hr`), the Ministry of Economy (`mingo.gov.hr`), and the Croatian Chamber of Trades and Crafts / HOK (`www.hok.hr`) are ordinary reference links opened only after an administrator clicks them. Older review builds also contained a Državni inspektorat (`dirh.gov.hr`) reference link; the current production runtime no longer contains that DIRH link. SIDRENA never uses these government/reference sites as an API or automatic data service. Narodne novine Terms: https://www.nn.hr/hr/o-nama/opci-uvjeti-koristenja/ Privacy: https://www.nn.hr/hr/o-nama/zastita-privatnosti/ Government portal Terms/Privacy information: https://gov.hr/hr/uvjeti-koristenja-i-politika-privatnosti/1808 HOK Privacy: https://www.hok.hr/o-hok-u/zastita-osobnih-podataka

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

= How can I use a real server cron instead of WP-Cron? =

Choose the external server cron / WP-CLI scheduler in **SIDRENA > Settings** and run `wp sidrena publish` from the server scheduler before the configured publication deadline. SIDRENA removes its internal daily generation event in this mode while keeping the watchdog active for delay detection and alerts.

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

= 1.0.4 =

* Aligns the installable WordPress package main file with the public WordPress.org slug: `brendigo-sidrene-cijene-digitalni-cjenici.php`.
* Keeps the public plugin name `SIDRENA` and the slug-matching text domain unchanged.
* Updates release, distribution, Plugin Check and WordPress.org asset workflows so the production ZIP, release gate and real wp-admin screenshots validate the same slug-named package entrypoint.
* Keeps the existing SIDRENA schema marker and runtime data backward compatible with 1.0.3.

= 1.0.3 =

* Declares verified WooCommerce High-Performance Order Storage (HPOS) compatibility for the WooCommerce edition.
* Adds CI coverage ensuring only the WooCommerce edition declares the `custom_order_tables` feature.
* Real wp-admin QA now enables HPOS with SIDRENA active and exercises WooCommerce order CRUD before capturing the release candidate.
* Keeps the standalone WordPress edition free of WooCommerce-specific compatibility declarations.
* No database schema migration is introduced; the existing SIDRENA schema marker remains unchanged.

= 1.0.2 =

* Streams standalone CSV imports directly from the uploaded temporary file with bounded row handling instead of loading the full import into memory.
* Hardens CSV delimiter/dialect handling for PHP 8.4 and adds regression coverage for malformed and edge-case input.
* Batches WooCommerce product-code lookups during import to reduce repeated catalogue lookup overhead on large files.
* Replaces page/OFFSET iteration in daily product and service price-history snapshots with bounded ID keyset iteration.
* Centralizes the published-post keyset iterator and reuses it for WooCommerce product export and service export, keeping daily CSV/XML generation efficient as catalogues grow.
* Preserves publish, product visibility, variable-product child ordering, filters, public output formats and the existing database schema marker.
* Keeps the WordPress admin sidebar, active menu state and SIDRENA top-level icon on the native WordPress admin color scheme; SIDRENA branding remains inside plugin content screens.
* Revalidates the production packages across PHP 7.4/8.3/8.4, WPCS/PHPStan, distribution guards, WordPress Plugin Check and real wp-admin QA.

= 1.0.1 =

* Aligns the standalone WordPress edition with the paired SIDRENA 1.0.1 release version without introducing a WooCommerce dependency.
* Refreshes version metadata, documentation, POT metadata and release-validation guards for the 1.0.1 production package.
* Revalidates the standalone production ZIP across PHP 7.4/8.3/8.4, WPCS/PHPStan and WordPress Plugin Check.
* Keeps the standalone products/services catalog, public price lists, archive, REST contract and stored data backward compatible with 1.0.0.
* Hardens the administrator public-file health check to exact same-origin URLs under the SIDRENA uploads path, with redirects disabled and bounded responses.
* Verifies the idempotent 1.0.0 → 1.0.1 upgrade path and current schema marker without repeatedly running the expensive upgrade on current installations.
* Adds a release-level reproducible-build gate so the WordPress ZIP and checksum must match a second build from the same verified release target before publication.

= 1.0.0 =

* Consolidated production baseline focused on SIDRENA anchor/reference prices as a distinct concept from current prices and the lowest price in the previous 30 days.
* Locks the statutory 10.09.2026 and 02.05.2025 reference dates in the backend legal ruleset and preserves retired administrator-entered dates only as audit migration data.
* Provides item-level first-publication exceptions for genuinely new products or services without turning custom dates into a global rule.
* Includes products, services, multiple locations, unit prices, safe CSV/XML import and export, public HTML price lists, machine-readable output, REST access and public archives.
* Keeps unlimited product, service and location price-change history with bounded reads for large catalogues.
* Separates the stable current price list from controlled archive publication, with SHA-256 duplicate detection, atomic writes, file locking, rollback and last-valid-publication recovery.
* Supports internal WP-Cron or external server cron/WP-CLI publication, Site Health monitoring and publication alerts.
* Keeps dashboard and price-list scheduler status mode-aware so external WP-CLI automation is not reported as a missing internal cron error.
* Hardens administrator CSV downloads with a checked output stream, sanitized filenames and a nosniff response header.
* Optimizes WooCommerce public REST pagination with a bounded invalidated product-index cache so repeated pages do not rescan the complete catalog.
* Hardens catalogue input with server-side nonnegative numeric validation, bounded save payloads, valid-location checks and chunked/bounded standalone code lookups.
* Ships without telemetry, license keys, feature paywalls or remote executable code.
