# SIDRENA – localizations 1.5.0

The original gettext catalog is in **Croatian (`hr_HR`)**. All untranslated messages fall back to the original Croatian and are **NOT** counted as translated. The WordPress text domain is `brendigo-sidrena-cijena`.

* Four English catalogs: 576 / 576 populated.
* Bosnian and two Serbian catalogs: 576 / 576 populated, **native-language proofreading pending**.
* German, Spanish, French, Italian and Slovenian: 269 / 576 actually translated.
* Fifteen other languages: 261 / 576 actually translated.

Version 1.5.0 added **75 manually authored translations to each of the 20 partially translated locale catalogs**. The remaining incomplete translations are intentionally empty. No duplicated Croatian strings, unchecked machine-generated content or external translation services were used to fake translation coverage.

Review locale translations with native-speaking subject-matter specialists, especially regulatory and price-related wording. Retain all placeholders, the approved locked reference dates, technical field names and shortcodes. Test every locale in the actual WordPress/WooCommerce interface before production deployment.

WordPress.org plugin review, Polyglots translation approval and WordPress.org language packs require separate publication procedures; local `.po` / `.mo` files do not publish themselves to WordPress.org. See `coverage.json` for per-locale counts.

## WordPress.org corrections included in 1.5.0

* Serbian Latin is now named `sr_RS_latin`, per WordPress.org locale registry; old `sr_RS@latin` files removed.
* WordPress.org loads approved language packs on demand, without an explicit `load_plugin_textdomain()` call. WordPress 6.8+ also discovers bundled translations using the plugin header `Text Domain` and `Domain Path`. On older supported WordPress versions, install approved language packs or use a compatible translation manager for bundled catalogs.
* Actual translation coverage is unchanged; do not state that all locale texts have been translated or reviewed.
