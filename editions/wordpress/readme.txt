=== brendigo Sidrene cijene i digitalni cjenici ===
Contributors: brendigo
Tags: prices, price-list, croatia, csv, xml
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.25
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reference prices, 30-day sale references, products, services, CSV/XML price lists, locations and archives for Croatian WordPress sites.

== Description ==

**brendigo Sidrene cijene i digitalni cjenici** is the standalone SIDRENA edition for Croatian WordPress sites that need a dedicated product and service price catalogue without using an ecommerce catalogue as the source.

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

The Croatian machine-readable price-list rule accepts XML or CSV. SIDRENA generates both formats by design for interoperability; this does not state that the rule requires both formats at the same time.

The simplified settings screen only asks the user to choose the business mode, a daily generation time before 08:00, an archive retention period of at least 30 days, and an alert email address.

= Croatian end-user documentation =

The installable package includes detailed Croatian documentation:

* `docs/UPUTE.md` — detailed step-by-step text guide
* `docs/SIDRENA-UPUTE.pdf` — detailed PDF manual for non-technical end users

The guide covers installation, first-time setup, locations, products and services, reference prices, 30-day sale-price references, unit prices, price-list generation, public publication, archives, the audit log, cron, alerts, and troubleshooting.

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

= 1.0.25 =

* Added shared edition-conflict messaging that identifies the active and attempted SIDRENA editions and documents the safe switch path without deleting business data.
* Refreshed official Croatian legal sources and separated current 1 October 2026 duties from the future 17 November 2026 base-price regime.
* Added repository source/licensing provenance for official WordPress/WooCommerce documentation and open-source plugins studied only as references.
* Removed obsolete funding-related documentation; SIDRENA remains fully functional without paid activation or feature locks.
* Kept the established standalone WordPress identity and WooCommerce-independent runtime boundary.

= 1.0.24 =

* Finalized the WordPress.org review-mail follow-up after the 1.0.23 release had already been published.
* Expanded external-link disclosure for brendigo, WhatsApp, Narodne novine, the Ministry of Economy, HOK, and the historical DIRH review reference.
* Added regression coverage preventing official Croatian legal/reference hosts from becoming automatic PHP HTTP or browser fetch endpoints.
* Preserved the distinctive public name/slug, lowercase brendigo ownership metadata, nonce/capability protections, scoped admin notices, and zero-telemetry model.
* Includes the 1.0.23 code-audit fixes for canonical shortcode ownership, neutral legal marketing copy, stricter source metadata checks, and PDF QA compatibility.

= 1.0.23 =

* Removed duplicate legacy registration of the `[sidrena_cjenici]` shortcode from the REST class so the public renderer has a single owner.
* Added runtime regression coverage that verifies the public download shortcode cannot be silently overwritten by another SIDRENA component.
* Removed a repository-level compliance marketing claim and tightened release QA wording without changing the technical legal model.
* Updated PDF visual QA for current Pillow APIs so release validation runs without the deprecated `Image.getdata()` path.
* Strengthened source metadata detection and cleaned stale test metadata while preserving lowercase `brendigo` in public plugin headers.

= 1.0.22 =

* Simplified the Croatian end-user settings screen and locked publication-critical safeguards to automatic safe defaults.
* Focused the plugin on reference prices, 30-day sale-price references, digital price lists, locations, unit prices, archives, automated retrieval, and publication audit data.
* Added public file integrity metadata, improved archive/download layouts, and an edition-specific detailed Croatian PDF manual for non-technical users.
* Added validated unit/alias extension hooks while preserving existing built-in conversions.
* Updated WordPress.org-facing naming, slugs, branding, admin notices, and readme copy based on reviewer feedback.
* Expanded PHP 7.4/8.3/8.4 CI, distribution/admin/legal, Plugin Check, and real WordPress runtime regression coverage for the 1.0.22 release.

= 1.0.21 =

* Stabilized both production editions and their shared publication system.
* Improved digital price lists, archives, locations, integrity metadata, and the public price-list view.
* Added detailed Croatian end-user documentation.
* Strengthened CI, Plugin Check, and production regression guards.

= 1.0.20 =

* Improved reference-price rules, 30-day references, and unit-price handling.
* Improved import/export, public publication, and security checks.
