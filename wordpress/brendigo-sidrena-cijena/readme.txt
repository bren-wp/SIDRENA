=== SIDRENA – Sidrene cijene i cjenici ===
Contributors: brendigo
Tags: reference-prices, price-list, csv, croatia, woocommerce
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage reference prices and dates, publish a public HTML pricelist, and generate XML/CSV archives for WooCommerce or standalone catalogs.

== Description ==

**SIDRENA brings reference prices, transparent public pricelists and dated archives together in one WordPress dashboard.** Built for WooCommerce stores and independent WordPress catalogs, without adding an external account or mandatory hosted service.

= Publish with clarity =

* **Reference prices with dates** - keep reference values and their applicable dates visible alongside current prices.
* **Public, readable pricelists** - publish HTML pages and share XML/CSV exports with customers and search engines.
* **A lasting archive** - preserve published pricelists for future consultation and downloads.
* **WordPress or WooCommerce** - manage products, variations, services and multiple locations from familiar controls.
* **Control and visibility** - use role-protected settings, an administrator audit trail and scheduled publication.

The plugin does not invent historical prices or certify a shop's legal compliance. Store owners are responsible for entering accurate source data, testing published outputs and checking applicable legal requirements.

= Designed for your workflow =


Brendigo Sidrena Cijena provides one interface for managing reference-price data and publishing a public pricelist.

Key features:

* Reads products and variations directly from WooCommerce without duplicating the catalog.
* Bulk editing of reference price, reference date, barcode and public-pricelist visibility.
* Standalone manual products and services when WooCommerce is not used, with individual price cards and observed sales-price history.
* Administrator-only local audit trail for reference-price edits (time, operator, old/new value); no external analytics.
* Separate [sidrena_najniza_prije_snizenja id="d7" datum="2026-10-09"] for the recorded 30-day window before a specific sale. Incomplete history is labeled as such.
* Public HTML pricelist and XML/CSV output generated from the same saved data.
* Public archive of previously generated pricelists.
* Multiple shop locations with separate public outputs.
* CSV/XML import and export, unit-of-measure price fields and barcode support for standalone items as well as WooCommerce products.
* WPML, Polylang and common WooCommerce theme/builder compatibility.
* Automatic publication after relevant changes plus a configurable daily publication time.
* Recovery checks for missed or failed background publications.
* WP-CLI generation and health commands on supported hosting environments.
* Observed selling-price history for WooCommerce products/variations and standalone products/services, with an incomplete-30-day-history indication.
* Email alerts when scheduled publication fails or is missed, with a 12-hour per-error throttle.
* Separate XML, CSV and HTML feeds for goods and services, including multiple outlet feeds and 30-day dated archives.
* Read-only REST API for discovering current pricelists and their archives.
* Cart/Checkout Blocks reference-price display via WooCommerce Store API extension data.
* Best-effort missed-publication recovery on public visits when built-in WP-Cron is disabled; external host scheduling remains recommended.

New installations default to a daily publication time of 07:00 in the WordPress site timezone. The time is configurable. A scheduled publication updates the public HTML page and configured XML/CSV file together and then stores the published version in the archive.

Reference prices and dates are entered or imported by the site administrator. The plugin does not infer historical prices.

Documentation: https://brendigo.com/sidrene-cijene

== Installation ==

After activation, open the **Upute** (Guide) tab inside Brendigo Sidrena Cijena for a step-by-step onboarding guide in Croatian: separate instructions for standalone WordPress and WooCommerce, publishing, imports, public shortcodes, and troubleshooting.


1. Install the ZIP from WordPress Plugins > Add New, or upload the `brendigo-sidrena-cijena` folder to `/wp-content/plugins/`.
2. Activate **SIDRENA**.
3. Open WooCommerce > SIDRENA. Without WooCommerce, use the standalone SIDRENA menu.
4. Open **Products** to enter reference prices and dates.
5. Open **Public pricelist** to choose the daily publication time and publish the first version.

== WooCommerce catalog workflow ==

The Products tab reads WooCommerce products directly. Product name, SKU and current price remain linked to the original product; the plugin stores only its own reference-price fields and related public-pricelist settings.

Large catalogs are paginated, and variations are grouped with their parent products.

== Standalone WordPress without WooCommerce ==

WooCommerce is optional. Activate the plugin in a standard WordPress installation and use the **Brendigo Sidrena Cijena** menu. Add manual products and services in the Products tab, provide the current price, reference price and date, then publish the HTML/XML/CSV pricelist. No checkout or online payment module is added to WordPress by this plugin.

Use `[sidrena_stavka id="123"]` for a named card showing the current and reference price, `[sidrena-cijena id="123"]` for the reference price, `[sidrena_povijest_cijena id="123"]` for observed price history, and `[sidrena-cjenik]` for the entire pricelist. When WooCommerce is also active, prefix the manual row ID with `d` for shortcodes that may otherwise resolve to a WooCommerce product: `id="d123"`. Individual manual item cards always use the manual catalog.

Price history starts when tracking is installed or a product is first seen; it does not backdate or establish the legally required historical reference price. Daily publishing uses the WordPress event scheduler and an on-visit recovery fallback; for predictable timing even on low-traffic sites configure a server-side cron.

== Scheduled publication ==

Daily publication follows the WordPress site timezone and is recalculated by local calendar date so daylight-saving changes do not shift the configured local time. A successful run updates the public HTML pricelist, the selected XML/CSV live file, and the archive entry.

WordPress WP-Cron is traffic-driven. If publication must run very close to an exact minute, configure the hosting scheduler to trigger WordPress cron regularly. Automatic maintenance also checks for missed publication slots and can queue recovery.

== Shortcodes ==

`[brendigo-sidrena-cijena]` - reference price for the current product.
`[brendigo-sidrena-cijena id="123"]` - reference price for a selected product or manual catalog row.
`[brendigo-sidrena-cjenik]` - public pricelist table.
`[brendigo-sidrena-arhiva]` - public pricelist archive.
`[sidrena_stavka id="123"]` - standalone product or service card, no WooCommerce needed.
`[sidrena_povijest_cijena id="123"]` - observed price history in standalone mode; with WooCommerce use `d123` for a manual row.

Legacy shortcode aliases remain available for backward compatibility.

== WP-CLI ==

`wp sidrena-cijena regenerate` regenerates and archives the public pricelist.

`wp sidrena-cijena health` reports publication schedule and automation state.

`wp sidrena-cijena fill` can fill empty reference-price fields from regular prices when an administrator intentionally chooses that workflow.

== Frequently Asked Questions ==

= Does the plugin copy my WooCommerce products into another catalog? =

No. The WooCommerce catalog editor reads existing products and variations directly and stores only plugin-specific fields on those records.

= What is published at the selected daily time? =

The public HTML pricelist and selected XML/CSV live file are generated together, followed by the archive entry.

= Can I change 07:00? =

Yes. The daily publication time is configurable in the Public pricelist tab.

= Does the plugin send catalog data to an external service? =

No. The plugin does not make remote API requests for catalog processing. The administrator guide is built into the plugin and does not require an external connection.

== Upgrade Notice ==

= 1.5.0 =
More translated interface texts for 20 languages. Untranslated texts still fall back to Croatian. No migration required.


= 1.4.7 =
More translations for every previously incomplete locale, particularly error handling and public pricelists. No database migration.


= 1.4.6 =
Adds 1,400 locale translations for the core interface and import/export error messages. No database migration.


= 1.4.5 =
Completes English translations and expands Bosnian and Serbian; other locales remain partial. No data migration.

= 1.4.4 =
Adds multilingual catalogs and fixes default prefix and locale handling. No database migration.

= 1.4.3 =
Hardens outlet and service saves, protects imported price data with server-side preflight verification, and optimizes multi-outlet processing. Back up your database before upgrading.

= 1.4.2 =
Adds in-plugin beginner instructions for standalone WordPress and WooCommerce, with direct navigation to existing features. No catalog or database changes.


= 1.4.1 =
Improves Plugin Check compatibility, SQL safety, and history performance. Keeps existing prices, feeds and both operating modes.

= 1.3.2 =
Improves barcode handling, CSV/XML imports and standalone administration. Preserves existing pricing data and URLs.

= 1.3.1 =
Adds standalone WordPress price-history tracking and an individual manual-item shortcode, hardens malformed JSON handling, makes catalog IDs monotonic, improves cleanup and privacy controls. WooCommerce stays optional. Back up your database and generated pricelists before updating.

= 1.3.0 =
Adds observed price history, publication alerts, separate product/service feeds, and REST discovery. Back up data before updating.

= 1.2.16 =
Fixes WordPress.org i18n translator-comment errors and malformed admin sprintf placeholders that could append internal text to publication, pending-change, and pagination labels.

== Changelog ==

= 1.5.0 =
* Expanded incomplete translations across 20 languages; these 20 remain partial.
* Fixed Serbian Latin translation filenames to `sr_RS_latin` and regenerated all compiled locale catalogs.
* Removed manual translation loading discouraged for WordPress.org-hosted plugins.
* Added SIDRENA brand symbol to the WordPress admin and prepared separate WordPress.org icon/banner assets.
* Kept Croatian as the source language and reference-price logic unchanged.


= 1.4.4 =
* Added 27 partial language packs, POT template, and corrected locale selection and default label.
* Preserved Croatian as source and unchanged reference-price business logic.

= 1.4.3 =
* Hardened outlet JSON validation and prevented destructive changes on failed writes.
* Kept outlet and manual service identifiers stable across deletion.
* Required matching, successful server-side import preflight before applying CSV/XML/XLSX import mappings.
* Reduced unnecessary repeated catalog scans for multi-outlet generation.
* Removed a duplicate import-column alias; retained existing public APIs and WordPress/WooCommerce modes.

= 1.4.2 =
* Added a fully local, access-controlled Upute admin tab with beginner onboarding guides for two operating modes and common setup tasks.
* Linked help from the admin header, plugin action links and overview dashboard.
* Kept current public price outputs and WordPress.org distribution clean of extra markdown files.


= 1.4.1 =
* Fixed WordPress.org Plugin Check errors for options, temporary XLSX files and product query filters.
* Improved plugin-owned SQL safety and reduced repeat database reads while retaining up-to-date administrator audit information.
* Removed internal development documentation from the distributable package.


= 1.3.3 =
* Added an administrator-only local change log for reference price edits in manual catalogs and plugin-managed WooCommerce writes, including WooCommerce CSV imports after successful saves.
* Added `[sidrena_najniza_prije_snizenja id="d7" datum="2026-10-09"]` for a dated, separate pre-discount 30-day observation. Data are marked incomplete when no baseline proves all 30 days.
* Preserved recent 31-day archival data even when archives contain more than 30 files.
* Improved protection against incorrectly logging unsuccessful saves, with regression tests for standalone and WooCommerce CSV integration.

= 1.3.1 =
* Fully support observing manual product/service price changes without WooCommerce; protect history from WooCommerce ID collisions.
* Add `[sidrena_stavka id="123"]` for a lightweight named manual product/service card.
* Prevent malformed or truncated catalog JSON from wiping stored data, strictly validate item IDs, and avoid reusing automatic row IDs after deletion.
* Improve manual-row lookup with an in-request index and prevent accidental repeated unslashing of stored text.
* Disable automatic historical reference-price autofill on new installs only; keep existing saved preferences unchanged.
* Maintain standalone history independently of WooCommerce; explicitly purge history only on administrator-confirmed full data wipe.
* Keep original prices, dates, WooCommerce functionality, exports, archives, public URLs and existing shortcodes compatible.

= 1.3.0 =
* Record observed WooCommerce product and variation price changes; calculate the lowest observed price within the 30-day window, marking incomplete history.
* Capture current baseline prices for existing products incrementally and keep 400 days of price observations.
* Send rate-limited failure and missed-publication email alerts to the designated address or site administrator.
* Publish separate goods/services XML, CSV and HTML alongside unchanged existing combined live feeds, with per-outlet exports and dated archives.
* Provide a read-only REST API for published feeds and separate archives.
* Integrate reference-price display into the WooCommerce Store API and Cart/Checkout Blocks.
* Add best-effort overdue publication repair when WP-Cron is disabled, plus server-side cron configuration guidance.
* Preserve reference-price dates, existing option keys, product metadata, shortcodes, archive schema and slug.

= 1.2.16 =
* Moved translator comments directly above all newly reported placeholder translation calls so WordPress i18n checks can associate them correctly.
* Fixed malformed numbered sprintf placeholders in publication status, pending-change status, and WooCommerce pagination labels.
* Re-audited translatable placeholder strings and release metadata.

= 1.2.15 =
* Added a deterministic class-map autoloader so plugin classes load only when a request actually needs them.
* Removed redundant manual class bootstrap branches and more unused localized JavaScript configuration.
* Centralized locked reference-date values so product fields, admin guidance and import templates cannot drift from the configured constants.
* Reduced unnecessary frontend stylesheet work when automatic display is disabled or standalone pages do not render reference prices.
* Deferred the storefront compatibility script and removed a repeated full-document legacy-tooltip cleanup from every injected price node.
* Removed the current tooltip info icon markup/CSS while preserving keyboard-accessible tooltip behavior and legacy cached-markup cleanup.
* Refined admin switch states and corrected reduced-motion loading-indicator behavior.
* Sanitized and bounded exception messages before they are passed to logging integrations.

= 1.2.14 =
* Centralized plugin-owned filesystem access and removed duplicated filesystem initialization.
* Lazy-loaded dashboard/import/editor code outside normal storefront requests.
* Removed verified duplicate forwarding methods, shared manual-row helper implementations, obsolete CLI fallback code, unused CSS variables and empty package directories.
* Consolidated repeated standalone public-page HTML shell/back-link output and uses the WordPress site language/direction.
* Moved older release history to `changelog.txt` and reduced readme size for the WordPress.org parser.

For older release history, see `changelog.txt`.
