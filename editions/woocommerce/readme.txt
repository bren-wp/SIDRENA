=== SIDRENA ===
Contributors: brendigo
Tags: woocommerce, prices, price-list, croatia, csv
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SIDRENA anchor prices, unlimited price-change history, CSV/XML price lists, locations and archives for Croatian stores using WooCommerce.

== Description ==

**SIDRENA** is the WooCommerce edition for Croatian merchants who already use WooCommerce products and variations.

WooCommerce remains the canonical product and variation source. SIDRENA does not create a duplicate product catalogue; it adds the price-record and publication tools needed around the existing store catalogue.

The plugin provides:

* current and reference-price records for existing products and variations
* an unlimited audit history of actual WooCommerce price changes, kept separate from the SIDRENA anchor-price ruleset
* availability and price data by physical location
* unit-price calculation and publication when applicable
* public CSV and XML price lists
* a searchable public HTML price-list page
* an archive of previous successful publications
* a JSON manifest and REST index for automated retrieval
* strict validation before replacing the last valid publication
* scheduled daily generation, publication monitoring, and email alerts
* bulk editing of SIDRENA fields without duplicating products
* integration with WooCommerce CSV import/export
* stored file size, row count, and SHA-256 integrity metadata

= Automated publication profile =

CSV, XML, public HTML, the JSON manifest, REST index, price history, strict publication checks, and publication monitoring remain automatically enabled so end users cannot accidentally disable required technical output.

The Croatian machine-readable price-list rule accepts XML or CSV. SIDRENA generates both formats by design for interoperability; this does not state that the rule requires both formats at the same time.

Users configure the operating mode, the daily publication scheduler (internal WP-Cron or external server cron/WP-CLI), a generation time before 08:00, archive retention of at least 30 days, and an alert email address.

= Independence and trademarks =

This plugin is independently developed by **brendigo**. It is not affiliated with Automattic and is not an official WooCommerce product or release.

The WooCommerce name is used only to accurately describe compatibility, the required dependency, and integration behavior.

= Croatian end-user documentation =

The installable package includes detailed Croatian documentation:

* `docs/UPUTE.md` — detailed step-by-step text guide
* `docs/SIDRENA-UPUTE.pdf` — detailed PDF manual for non-technical end users

The guide covers installation, existing products and variations, bulk editing, locations, SIDRENA anchor prices, price-change history, unit prices, CSV import/export, generation, public publication, archives, cron, the audit log, and troubleshooting.

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

1. Install and activate WooCommerce.
2. In WordPress, open **Plugins > Add New Plugin > Upload Plugin**.
3. Upload the current SIDRENA web-store edition ZIP package.
4. Activate the plugin.
5. Open **SIDRENA > Settings**.
6. Configure **Locations** and review the optional store-address suggestion.
7. Open an existing product or the SIDRENA bulk catalogue and complete the real source data.
8. Open **Check** and resolve genuine data warnings.
9. Generate the first publication under **Price Lists**.
10. Verify the public CSV/XML files, archive, and audit log.

Detailed Croatian instructions are included in `docs/UPUTE.md` and `docs/SIDRENA-UPUTE.pdf`.

== Frequently Asked Questions ==

= Does SIDRENA duplicate WooCommerce products? =

No. Existing WooCommerce products and variations remain the source of truth.

= What happens if WooCommerce is deactivated? =

The WooCommerce-specific SIDRENA runtime stays inactive and shows a scoped notice on the Plugins screen. It does not register WooCommerce hooks or publish WooCommerce price lists until WooCommerce is active again.

= Does variation switching require an additional REST request? =

The standard variation payload contains SIDRENA reference-price markup without an additional request. REST remains a compatibility fallback for themes or builders that remove the standard payload.

= How can I use a real server cron instead of WP-Cron? =

Choose the external server cron / WP-CLI scheduler in **SIDRENA > Settings** and run `wp sidrena publish` from the server scheduler before the configured publication deadline. SIDRENA removes its internal daily generation event in this mode while keeping the watchdog active for delay detection and alerts.

= Can I disable CSV/XML output or publication monitoring? =

Not from the simplified end-user settings screen. These technical outputs and safeguards remain enabled for stable publication and interoperability.

= Is a SIDRENA reference price the same as the lowest price in the previous 30 days? =

No. They are stored and handled as separate concepts.

= Is the 80 EUR initial setup required? =

No. It is completely optional. The plugin can be installed and configured independently using the included Croatian documentation.

== Screenshots ==

1. SIDRENA dashboard and publication status.
2. Web-store catalogue and SIDRENA data.
3. Digital price lists and publication archive.
4. Locations and sales channels.
5. Simplified automated settings.
6. Support, documentation, and optional services.

== Changelog ==

= 1.0.0 =

* Consolidated production baseline focused on SIDRENA anchor/reference prices as a distinct concept from current, regular, sale and 30-day lowest-price data.
* Locks the statutory 10.09.2026 and 02.05.2025 reference dates in the backend legal ruleset and preserves retired administrator-entered dates only as audit migration data.
* Supports simple products, variable products and individual variations, including WooCommerce regular/sale/current lifecycle and scheduled-sale refresh handling.
* Provides item-level first-publication exceptions for genuinely new products or variations without turning custom dates into a global rule.
* Includes multiple locations, unit prices, WooCommerce CSV integration, public HTML price lists, CSV/XML machine-readable output, REST access and public archives.
* Keeps unlimited product, variation and location price-change history with bounded reads for large catalogues.
* Separates the stable current price list from controlled archive publication, with SHA-256 duplicate detection, atomic writes, file locking, rollback and last-valid-publication recovery.
* Supports internal WP-Cron or external server cron/WP-CLI publication, Site Health monitoring and publication alerts.
* Keeps dashboard and price-list scheduler status mode-aware so external WP-CLI automation is not reported as a missing internal cron error.
* Hardens administrator CSV downloads with a checked output stream, sanitized filenames and a nosniff response header.
* Hardens bulk input with server-side nonnegative numeric validation, bounded request payloads and valid-location checks.
* Requires WooCommerce at install/runtime and remains inactive safely while that dependency is unavailable.
* Ships without telemetry, license keys, feature paywalls or remote executable code.
