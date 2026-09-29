=== SIDRENA ===
Contributors: brendigo
Tags: woocommerce, prices, price-list, croatia, csv
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.26
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

Users only configure the operating mode, a daily generation time before 08:00, archive retention of at least 30 days, and an alert email address.

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

= 1.0.26 =

* Unified the installed plugin name and WordPress.org readme brand to SIDRENA while keeping the established public slug/text domain unchanged.
* Added REST location-code resolution so the public location code returned by SIDRENA can also be used as a REST filter.
* Hardened CSV imports by validating the actual server-side temporary file size before reading content into memory.
* Added regression coverage for REST location-code filters and server-side upload-size enforcement across supported PHP versions.

= 1.0.25 =

* Aligned the WooCommerce edition public text domain and Plugin Check slug to brendigo-sidrena-cijena.
* Delayed all WooCommerce-specific runtime includes until WooCommerce is actually available on plugins_loaded.
* Added a defensive missing-WooCommerce safe state and a scoped Plugins-screen notice without registering WooCommerce hooks or running upgrade/publication scheduling.
* Improved the two-edition conflict guard so activation errors identify the active and attempted editions and explain the safe switch path without deleting business data.
* Added regression coverage for a missing/deactivated WooCommerce dependency and refreshed legal/source provenance documentation from official Croatian, WordPress and WooCommerce sources.
* Removed obsolete funding-related documentation; SIDRENA remains fully functional without paid activation or feature locks.

= 1.0.24 =

* Finalized the WordPress.org review-mail follow-up after the 1.0.23 release had already been published.
* Expanded external-link disclosure for brendigo, WhatsApp, Narodne novine, the Ministry of Economy, HOK, and the historical DIRH review reference.
* Added regression coverage preventing official Croatian legal/reference hosts from becoming automatic PHP HTTP or browser fetch endpoints.
* Preserved the distinctive public name/slug, WooCommerce dependency header, lowercase brendigo ownership metadata, nonce/capability protections, scoped admin notices, and zero-telemetry model.
* Includes the 1.0.23 code-audit fixes for canonical shortcode ownership, neutral legal marketing copy, stricter source metadata checks, and PDF QA compatibility.

= 1.0.23 =

* Removed duplicate legacy registration of the `[sidrena_cjenici]` shortcode from the REST class so the public renderer has a single owner.
* Added runtime regression coverage that verifies the public download shortcode cannot be silently overwritten by another SIDRENA component.
* Removed a repository-level compliance marketing claim and tightened release QA wording without changing the technical legal model.
* Updated PDF visual QA for current Pillow APIs so release validation runs without the deprecated `Image.getdata()` path.
* Strengthened source metadata detection and cleaned stale test metadata while preserving lowercase `brendigo` in public plugin headers.

= 1.0.22 =

* Simplified the Croatian end-user settings screen and locked publication-critical safeguards to automatic safe defaults.
* Added safe WooCommerce store-address onboarding for empty webshop locations without overwriting saved data.
* Improved variation reference-price hydration so the standard variation payload works without an additional request, with REST retained only as a compatibility fallback.
* Added public file integrity metadata, improved archive/download layouts, and edition-specific detailed Croatian PDF instructions.
* Added validated unit/alias extension hooks while preserving existing built-in conversions.
* Updated WordPress.org-facing naming, slugs, branding, admin notices, readme copy, and trademark handling based on reviewer feedback.
* Expanded PHP 7.4/8.3/8.4 CI, distribution/admin/legal, Plugin Check, and real WordPress/WooCommerce runtime regression coverage for the 1.0.22 release.

= 1.0.21 =

* Stabilized integration with existing store products and variations.
* Improved variation reference-price output without an extra request in the standard WooCommerce flow.
* Improved digital price lists, archives, locations, and integrity metadata.
* Added detailed Croatian end-user documentation.
* Strengthened CI, Plugin Check, and production regression guards.

= 1.0.20 =

* Improved price history, 30-day references, and unit-price handling.
* Improved CSV import/export, location data, and public publication.
