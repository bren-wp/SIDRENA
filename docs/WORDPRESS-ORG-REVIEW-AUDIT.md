<!--
Sidrena source file.
Author: brendigo
Author URI: https://brendigo.com/
Plugin URI: https://brendigo.com/sidrene-cijene/
Support: sidrena@brendigo.com
-->

# WordPress.org review audit — SIDRENA 1.0.22

Review reference: `AUTOPREREVIEW ❗TRM-OWN sidrena-for-woocommerce/brendigo/28Sep26/T1 28Sep26/4.3 (P0TDX377029HGN)`

This file records the repository-side audit performed before the next WordPress.org submission. The exact final PR head SHA and final gate results are recorded in the PR review-audit comment after every required workflow completes on that same SHA.

## Public identity, slug and trademark handling

**Issue:** the previous WooCommerce-facing name/slug could be interpreted as insufficiently distinctive or as an official ecosystem product.

**Occurrences audited:** plugin headers, both readmes, text domains, POT packaging, Plugin Check slugs, release workflow, package build, graphics, screenshot-facing copy and branding sources.

**Resolution:**
- standalone display name: **brendigo Sidrene cijene i digitalni cjenici**
- WooCommerce display name: **brendigo Sidrene cijene i cjenici za WooCommerce**
- standalone public slug/text-domain: `brendigo-sidrene-cijene-digitalni-cjenici`
- WooCommerce requested public slug/text-domain: `brendigo-sidrene-cijene-cjenici`
- `brendigo` is the leading distinctive element.
- The public directory title deliberately uses the descriptive phrase `Sidrene cijene` rather than presenting `SIDRENA` as a standalone product-name token, because same-market products already use similar `Sidrena` branding. The in-plugin SIDRENA brand remains unchanged.
- WooCommerce is used only as a trailing compatibility/dependency descriptor.
- WooCommerce logos/third-party branding are not used in SIDRENA graphics.
- Woo readme states that the plugin is independently developed by brendigo, is not affiliated with Automattic, and is not an official WooCommerce product.

**Proof:** `.github/tests/wporg-review-smoke.php`, Plugin Check workflows, distribution guard, real WordPress.org asset workflow.

## Ownership metadata

**Issue:** reviewer ownership clarification.

**Occurrences audited:** public plugin headers, readmes, plugin URI, author URI and contributor identity.

**Resolution:**
- Author: `brendigo`
- Contributors: `brendigo`
- Author URI: `https://brendigo.com/`
- Plugin URI: `https://brendigo.com/sidrene-cijene/`
- submission remains associated with the brendigo WordPress.org identity and `info@brendigo.com` domain email.
- no DNS verification record is claimed by the plugin or repository.

**Proof:** metadata checks, source-metadata check and WordPress.org regression guard.

## Guideline 11 — admin notices

**Issue:** notices must not hijack the dashboard.

**Occurrences audited:** `admin_notices`, global notice hooks, dependency/conflict notices, support/donation/setup surfaces.

**Resolution:**
- only two dependency/conflict `admin_notices` remain.
- both are limited to the WordPress Plugins screen.
- both require the relevant plugin-management capability.
- both are dismissible.
- no `all_admin_notices` hook exists.
- donation and optional paid setup are confined to SIDRENA Support/documentation, not global notices or general dashboard marketing.

**Proof:** WordPress.org regression guard and admin-polish guard.

## Nonces and permissions

**Issue:** every request that changes or exposes sensitive administrative data must use appropriate request validation and authorization.

**Occurrences audited:** all `admin-post.php` actions, Woo product/variation saves, service saves, standalone catalogue save/import/sync, bulk editor, location/anchor imports, sensitive exports and REST routes.

**Resolution:**
- every SIDRENA `admin_post_*` handler uses an action-specific nonce plus SIDRENA management capability.
- Woo product/variation saves verify the WooCommerce nonce and object-specific `edit_post` permission.
- Woo bulk and SIDRENA Woo imports additionally verify `edit_post` for each affected product.
- service writes use a dedicated nonce plus object-specific `edit_post`.
- public REST routes are read-only and define explicit `permission_callback` values.
- no public REST write endpoint is registered.

**Proof:** CI request/security smoke tests, WordPress.org review regression guard and runtime tests.

## Input, import and file safety

**Issue:** sanitize/validate uploads and protect file operations.

**Occurrences audited:** CSV/XML imports, upload handling, paths, output escaping, CSV formula handling, XML parsing, SQL and filesystem publication.

**Resolution:**
- CSV/XML uploads are size-bounded and validate extension plus detected MIME type.
- binary data disguised as CSV is rejected by regression coverage.
- CSV imports enforce bounded row counts and streaming/batched processing.
- XML rejects DOCTYPE/ENTITY declarations, uses `LIBXML_NONET`, bounded row counts and XMLReader where available.
- CSV output neutralizes spreadsheet-formula prefixes.
- public file metadata strips unsafe path components.
- publication uses transactional/atomic write patterns and stored SHA-256 integrity metadata.
- dangerous execution/obfuscation primitives are blocked by CI.

**Proof:** utility/import/publication/security smoke tests and distribution guard.

## External services and privacy

**Issue:** undocumented third-party service use.

**Occurrences audited:** WordPress HTTP API calls, browser fetches, remote scripts/styles/fonts/images, tracking, telemetry, update services, remote documentation requests and external links.

**Resolution:**
- no telemetry, analytics or remote SaaS account is required.
- the only PHP automatic HTTP request is the administrator-triggered public-access check.
- that check is restricted to SIDRENA publication URLs under the current site's own public upload base, validates the URL, uses `wp_safe_remote_get()`, explicitly rejects unsafe URLs, limits redirects/response size and sends no catalogue/customer payload to brendigo.
- the frontend compatibility fetch is same-origin and targets the local SIDRENA read-only REST endpoint.
- official legal sources, brendigo, WhatsApp and Revolut are user-initiated links and are documented as such in both readmes.
- Terms/Privacy destinations for the external support/donation services are documented.

**Proof:** WordPress.org network regression guard and readme External services sections.

## Croatian legal scope

**Issue:** distinguish separate price concepts and avoid guarantees.

**Resolution:**
- sidrena/reference price, lowest price in the previous 30 days and public 30-day archive are separate concepts.
- the documentation states that machine-readable publication accepts XML **or** CSV; SIDRENA generates both as a technical interoperability choice without claiming both are simultaneously required.
- the service-price-list timing wording is aligned to NN 101/2026-1213: on each change, no later than 08:00 on the day the service price-list amendment is published; the older “day the change enters into force” paraphrase is prohibited by regression coverage.
- SIDRENA does not claim 100% legal compliance or guaranteed compliance.
- future/base-price rules are not silently equated with the current SIDRENA reference-price model.
- legal sources and effective dates are documented in `docs/legal-and-technical-notes.md`.

**Proof:** legal schema/automation smoke tests and legal-copy guard.

## Focused settings and automation

**Issue:** non-essential business identity and switches that can disable core publication should not burden the end user.

**Resolution:**
- legacy generic business identity settings are removed from active SIDRENA settings.
- simplified end-user settings focus on catalogue mode, safe daily generation time, retention above the minimum and alert email.
- publication-critical outputs and safeguards remain automated: CSV, XML, public HTML, JSON manifest, REST discovery, strict publication, history, watchdog/alerts, archive, integrity and atomic publication.
- unsafe generation times and too-short retention are normalized to safe values.

**Proof:** legal automation guard, settings/runtime tests and packaged documentation.

## Edition boundaries and production packages

**Issue:** standalone and WooCommerce editions must be independent production packages.

**Resolution:**
- exactly two installable ZIPs are built:
  - `sidrena-wordpress-1.0.22.zip`
  - `sidrena-woocommerce-1.0.22.zip`
- each ZIP has exactly one edition root.
- standalone ZIP excludes WooCommerce runtime catalogue/integration classes.
- WooCommerce ZIP excludes the standalone catalogue runtime.
- development workflows/tools/source-only branding/Windows/debug/map/PDB artefacts are excluded.
- both packages include their edition-specific `docs/UPUTE.md` and `docs/SIDRENA-UPUTE.pdf`.

**Proof:** distribution guard, ZIP integrity checks and direct artifact inspection.

## Documentation and PDF QA

**Issue:** end-user PDF must be a detailed Croatian manual, not a short support leaflet.

**Resolution:**
- WordPress and WooCommerce manuals are edition-specific.
- Markdown is the source used to build each PDF.
- manuals cover installation, first setup, settings, locations, products/services, SIDRENA price, 30-day reference, unit price, CSV/XML, archive, shortcodes, REST/manifest, automation/watchdog/cron, email alerts, audit, import/export, troubleshooting, update, security, production checklist and legal note.
- Woo manual additionally covers variations, bulk editing and location-specific Woo data.
- PDF QA renders every page, validates Croatian characters, detects blank/broken renders and uploads rendered pages for visual inspection.

**Proof:** distribution/release PDF visual QA and manual artifact review.

## WordPress.org assets and runtime QA

**Issue:** screenshots must come from a real active runtime and the workflow must not mutate the review branch.

**Resolution:**
- asset workflow runs real WordPress with each edition; Woo run installs/activates WooCommerce.
- browser screenshots are captured from the real wp-admin runtime.
- assets are uploaded as workflow artifacts.
- workflow uses read-only repository permission and metadata `--check`; it does not commit, push, force-rebase or rewrite the review branch.

**Proof:** `.github/workflows/wporg-real-assets.yml` and its workflow result on the final SHA.

## Final submission rule

The WooCommerce permalink requested in the reply to the existing WordPress.org review email thread is:

`brendigo-sidrene-cijene-cjenici`

No repository document claims approval is guaranteed. The submission target is zero known review blockers, zero relevant Plugin Check errors and green project QA on the same final SHA.
