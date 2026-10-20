# SIDRENA for Android

Native Android companion application for SIDRENA WordPress and WooCommerce editions.

## Product scope

- Kotlin, Jetpack Compose, Material 3, single-activity architecture.
- Croatian-first interface, with accessible layouts for phones and tablets.
- Connect to an existing SIDRENA installation over HTTPS using an authenticated, least-privilege API.
- View and manage permitted catalog items, services, price history, locations, price lists, and publication archives.
- Support offline read-only caching where safe; queue edits only after conflict resolution and explicit confirmation.
- Preserve the existing WordPress/WooCommerce catalog as the source of truth; do not duplicate products.
- Respect reference-price and publication rules implemented by the server; never infer legal compliance from the app alone.

## Integration prerequisites

The WordPress plugin must expose versioned authenticated endpoints for the mobile app, with server-side capability checks, input validation, rate limits, audit logging, and narrowly scoped tokens. The mobile client must not request or store WordPress administrator passwords. Do not expose publication or destructive actions before corresponding endpoints and permission tests exist.

## Security and release requirements

No embedded credentials or secrets; Android Keystore-backed credential storage; HTTPS-only connections; safe token revocation; no analytics SDK by default; minimum Android permissions; secure backups policy; explicit error and conflict handling; unit/integration/UI tests; accessibility checks; release signing outside the repository; Play policy and Data safety review before publishing.

## Development status

Planning scaffold only. No functional Android application or integration endpoints have been implemented in this directory yet.
