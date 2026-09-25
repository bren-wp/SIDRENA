<!--
Sidrena source file.
Author: Brendigo
Author URI: https://brendigo.com/
Plugin URI: https://brendigo.com/sidrene-cijene/
Support: sidrena@brendigo.com
-->

# Sidrena brand system 1.0.1

Ovaj direktorij je izvorni branding paket za Sidrena WordPress i Sidrena WooCommerce. Vizualni sustav prati produkcijske reference: tamno plava pomorska baza, svjetionik kao signal sigurnosti, Sidrena znak (S + sidro), plavi WordPress akcent i ljubičasti WooCommerce akcent.

## Primarne boje

- Primarna navy: `#082D5B`
- Duboka navy: `#061A33`
- Kobalt plava: `#2563EB`
- Cijan: `#0EA5E9`
- Svijetla plava: `#E0F2FE`
- Pozadina: `#F8FAFC`
- Tekst: `#0F172A`
- Sekundarni tekst: `#64748B`
- Uspjeh: `#10B981`
- Upozorenje: `#F59E0B`
- Greška: `#EF4444`
- WooCommerce akcent: `#7C3AED`

## Tipografija

Primarni UI font je Inter. Plugin ne učitava font s vanjske mreže; koristi lokalni/system stack `Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`.

## Logotipi i ikone

- `assets/images/logo-mark.svg` — transparentni Sidrena znak.
- `assets/images/app-icon.svg` — kvadratna ikona aplikacije.
- `assets/images/logo-horizontal.svg` — logo za svijetlu podlogu.
- `assets/images/logo-horizontal-light.svg` — logo za tamnu podlogu.
- `assets/images/logo-wordpress.svg` — WordPress izdanje za svijetlu podlogu.
- `assets/images/logo-wordpress-light.svg` — WordPress izdanje za tamni admin header.
- `assets/images/logo-woocommerce.svg` — WooCommerce izdanje za svijetlu podlogu.
- `assets/images/logo-woocommerce-light.svg` — WooCommerce izdanje za tamni admin header.
- `assets/images/menu-anchor.svg` — kompaktna WP admin menu ikona.
- `assets/images/brand-hero.svg` — lokalni morski/svjetionik vizual za admin i dokumentaciju.

## Marketinški asseti

- `branding/website-hero-wordpress.svg`
- `branding/website-hero-woocommerce.svg`
- `branding/plugin-cover-wordpress.svg`
- `branding/plugin-cover-woocommerce.svg`
- `branding/email-header.svg`
- `branding/docs-cover-wordpress.svg`
- `branding/docs-cover-woocommerce.svg`
- `branding/cta-wordpress.svg`
- `branding/cta-woocommerce.svg`
- `branding/app-card-wordpress.svg`
- `branding/app-card-woocommerce.svg`
- `branding/compact-wordpress.svg`
- `branding/compact-woocommerce.svg`
- `branding/support-cover.svg`

- `branding/wporg-banner-wordpress.svg`
- `branding/wporg-banner-woocommerce.svg`
- `branding/social-wordpress.svg`
- `branding/social-woocommerce.svg`

Renderirane PNG varijante marketinških asseta nalaze se u `branding/rendered/`, uključujući edition-specific `social-*.png` i `wporg-banner-*.png`. WordPress.org banneri renderiraju se iz namjenskih `wporg-banner-*.svg` izvora, a ne iz runtime screenshotova.

WordPress.org screenshotovi se ne crtaju: CI podiže stvarni WordPress/WooCommerce, aktivira odgovarajuće Sidrena izdanje i snima stvarne wp-admin ekrane.

## Pravila upotrebe

Ne rastezati logo, ne mijenjati odnos boja znaka i wordmarka, ne koristiti Woo ljubičasti akcent za WordPress izdanje i ne zamijeniti lokalne assete vanjskim CDN ikonama ili fontovima.

## Runtime screenshot pravilo

Glavni `README.md`, upute i instalacijski ZIP-ovi za dokaz stvarnog sučelja smiju koristiti samo slike koje proizvodi `tools/capture-wporg-assets.mjs` iz aktivnog WordPress okruženja. Marketinški SVG/PNG hero, cover i social asseti ne smiju se predstavljati kao screenshot plugina. Za izdanje 1.0.1 obvezno je šest stvarnih ekrana po izdanju: Pregled, Katalog/Proizvodi, Cjenici, Lokacije, Postavke i Pomoć.
