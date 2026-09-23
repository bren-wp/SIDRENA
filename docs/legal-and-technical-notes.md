# Sidrena — legal and technical notes

Sidrena is a technical WordPress/WooCommerce tool. It does not replace legal advice and it does not infer historical prices that are not present in a verifiable source.

## Core official references

- NN 101/2026, no. 1212 — additional price / reference dates; effective 1 October 2026.
- NN 101/2026, no. 1213 — public CSV/XML price lists, update timing, 30-day public availability, required columns, file naming and machine retrieval; effective 1 October 2026.
- Ministry of Economy clarification dated 22 September 2026 — implementation examples, multiple locations, webshop, newly listed items and machine-readable publication.
- NN 59/2026 — Consumer Protection Act amendments relevant to the lowest price in the preceding 30 days during reductions.
- NN 105/2026 — rules relevant to service price lists and service information.

Official links are also shown in the Sidrena > Propisi screen.

## Separate records

Sidrena intentionally keeps two different records:

1. **Public CSV/XML archive** — every successful publication is a separate file, publicly available for at least 30 days. Default retention is 45 days. A current file is protected even when older than the retention window until it is superseded.
2. **Internal price history** — WooCommerce products/variations, Sidrena services and per-location product values are stored as changed states and daily snapshots. The internal history is retained for 400 days so future 30-day look-back windows can have a known baseline.

The plugin does not fabricate the period before installation. Historical anchor/reference values should be imported or entered only from reliable business records.

## Products

Generated product rows include the product name, stable code, brand, unit and unit price when applicable, retail price, sale indicator/name, anchor price/date, barcode and per-location availability. Product code resolution is WooCommerce SKU -> Sidrena code -> stable WP-ID fallback.

For physical locations, Sidrena supports location-specific current price, anchor price and availability. Imported location prices are treated as final retail amounts. Base WooCommerce prices are converted to tax-inclusive retail values when WooCommerce provides the conversion helper.

## Services

Sidrena services support current price, anchor price/date, sale state/name, service type, scope, related costs/price note and per-location current/anchor prices. The service public list and machine endpoint expose these values.

## Multiple locations and webshop

Each enabled location generates its own file. A webshop is modeled as its own location/object. File names contain object kind, address, object code, storage sequence and generation timestamp. The same column structure is used across locations for a given catalog type.

## Automation

The default daily schedule is 06:30 in the WordPress timezone. WP-Cron depends on site traffic, therefore a real server cron that calls WordPress cron is recommended where execution before 08:00 is operationally critical.

Product/service edits and scheduled WooCommerce sale transitions can queue a new generation. If one new file fails, the last successful current file for that location/catalog/format remains published.

## Integrity and exports

Archive records contain file size, row count, SHA-256, publication time and retain-until time. The admin panel verifies indexed files and hashes. Archive metadata and internal price history can be exported as CSV.

## Privacy

No telemetry, analytics, remote activation, license server, account or paid feature gate is required. Runtime assets are local to the plugin.


## Operativna pouzdanost

Sidrena 1.5.0 dodaje Site Health provjeru rasporeda i zapisivosti arhive te WP-CLI naredbe za produkcijske servere. WordPress WP-Cron ovisi o prometu i sam po sebi ne jamči izvršavanje u točno određenoj minuti. Za poslovne procese koji zahtijevaju objavu prije određenog roka preporučuje se server cron koji redovito pokreće WordPress cron ili izravno koristi `wp sidrena generate` u odgovarajuće vrijeme.

Lokalni audit dnevnik služi tehničkoj sljedivosti generiranja i administrativnih promjena. Ne predstavlja pravno jamstvo usklađenosti i ne šalje podatke izvan WordPress instalacije.
