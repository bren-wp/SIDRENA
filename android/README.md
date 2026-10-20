# SIDRENA za Android

**SIDRENA by Brendigo** · **0.2.0** · `com.brendigo.sidrena`

![SIDRENA](../desktop/branding/sidrena-banner.svg)

Pregledajte objavljene cjenike, proizvode, usluge i poslovnice sa svoje WordPress stranice, uz prepoznatljiv tamnoplavi izgled i narančaste kontrole.

## Zasloni i mogućnosti

| Zaslon | Mogućnosti |
|---|---|
| Povezivanje | HTTPS adresa WordPress stranice |
| Početna | Povezana stranica, pregled podataka, zadnja objava i brzi pristup |
| Proizvodi | Popis, pretraga, filtri sidrenih cijena i dostupnost |
| Usluge | Objavljene usluge i pripadajuće cijene |
| Detalj stavke | SKU, trenutačna i sidrena cijena, datum i jedinica mjere |
| Arhiva | CSV/XML zapisi, filtriranje i otvaranje datoteka |
| Poslovnice | Pregled i odabir poslovnice ili webshopa |
| Pretraživanje | Pretraga po nazivima, šiframa i ID-ovima |
| Postavke | Povezivanje, osvježavanje, odspajanje i informacije o aplikaciji |
| O aplikaciji | Verzija, kratke upute i kontakt podrške |

Izgled je usklađen s [Android dizajnerskim standardom](../docs/ANDROID-UI-REFERENCE.md).

## Povezivanje sa SIDRENA dodatkom

Aplikacija koristi javno dostupne rute postojećeg WordPress dodatka:

- `GET /wp-json/brendigo-sidrena/v1/cjenici`
- `GET /wp-json/brendigo-sidrena/v1/cjenici/proizvodi/csv`
- `GET /wp-json/brendigo-sidrena/v1/cjenici/usluge/csv`
- `GET /wp-json/brendigo-sidrena/v1/arhiva`

Pojedinačni CSV može se preusmjeriti na objavljenu datoteku na istoj HTTPS domeni. Sadržaj poslovnica i arhive ovisi o stvarno objavljenim podacima na stranici. Aplikacija ne prikazuje izmišljene podatke kada javno sučelje ne nudi povijest promjena ili druge informacije.

**Uređivanje cijena ostaje u WordPressu ili Windows aplikaciji** dok ne postoji autorizirani REST API za mobilno uređivanje.

## Instalacija

[Otvorite GitHub izdanja](https://github.com/bren-wp/SIDRENA/releases) i odaberite Android APK. Za izdavanje u Google Playu potreban je odgovarajući potpisani AAB.

## Tehnička dokumentacija

Izvorni kod koristi Kotlin, Jetpack Compose i Material 3. Za izgradnju otvorite mapu `android/` u Android Studiju (JDK 17, Android SDK 36, Gradle 8.13). Provjere kompilacije, lint i JUnit nalaze se u GitHub Actions postupku.

[Izdanja](RELEASE.md) · [Audit](../docs/CODE-AUDIT-2026-10-21.md) · [Podrška](mailto:sidrena@brendigo.com)
