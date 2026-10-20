# SIDRENA · Android

**Status: prvi funkcionalni prototip za čitanje javnih cjenika.** Brendigo · `com.brendigo.sidrena` · hrvatski.

## Što stvarno radi

- 100 % native Kotlin + Jetpack Compose + Material 3; bez WebViewa i bez računa treće strane.
- Povezivanje korisnički odabrane HTTPS WordPress stranice sa SIDRENA 1.5.0 dodatkom.
- Čitanje objavljenih proizvoda, usluga, referentnih cijena i datuma iz stvarnog javnog REST/CSV sučelja, odvojeno po poslovnici.
- Pretraga po nazivu, SKU-u i ID-u, pregled objavljenih arhivskih zapisa i javnog cjenika.
- Ručno osvježavanje; sprema se samo javna adresa stranice.
- Nema oglasa, SDK-a za praćenje, lozinki, pristupa administratoru ili zahtjeva za nepotrebnim Android dozvolama.

### Postojeći WordPress API (read-only)

`GET /wp-json/brendigo-sidrena/v1/cjenici`

`GET /wp-json/brendigo-sidrena/v1/cjenici/proizvodi/csv`

`GET /wp-json/brendigo-sidrena/v1/cjenici/usluge/csv`

`GET /wp-json/brendigo-sidrena/v1/arhiva`

Odgovor za pojedini CSV može biti HTTP 302 prema već objavljenoj datoteci. Klijent provjerava HTTPS i istu domenu prilikom preuzimanja i preusmjeravanja. Ako je WordPress konfiguriran tako da služi datoteke s druge CDN domene, pristup se **namjerno odbija** do uvođenja sigurnog upravljanja dopuštenim domenama.

CSV datoteke koriste znak `;`, UTF-8 BOM i quoted field format. Android čita polja prema njihovim nazivima, ne prema rednom broju. Podaci se *ne modificiraju* niti se automatski koriste kao dokaz zakonske usklađenosti.

## Izrada

Otvorite **samo `android/`** kao projekt u Android Studiju (JDK 17, Android SDK API 36), sinkronizirajte Gradle i pokrenite modul `app`. Ako nemate Gradle na računalu, generirajte standardni Gradle wrapper lokalno naredbom `gradle wrapper --gradle-version 8.13` pa koristite `./gradlew :app:assembleDebug :app:testDebugUnitTest`.

Prototip ne uključuje Gradle wrapper JAR, provjereni APK/AAB ni automatski GitHub CI. Build i testovi **nisu izvršeni u ovom razvojnom okruženju**, stoga nije spreman za Play Store.

## Sigurnosni i funkcionalni opseg

- Jedina Android dozvola je INTERNET. Promet ide samo putem HTTPS-a.
- Nisu dopuštene lokalne/IP adrese, HTTP veze, korisnički podaci u URL-u ili preusmjeravanja na drugu domenu.
- Veličina REST/CSV odgovora je ograničena, a parsiranje je zaštićeno od neispravno zatvorenih CSV navodnika.
- Nema automatske sinkronizacije u pozadini niti nepotrebne potrošnje baterije.
- Android Backup/Device Transfer isključeni su; nema tajni u izvornom kodu.
- Nema spremanja javnog kataloga izvan memorije i nema obećanja offline pristupa.

## Što još nije implementirano

- **Autorizirano uređivanje i objavljivanje cijena**, dodavanje usluga, upravljanje poslovnicama te uvoz/izvoz s Androida: WordPress plugin ima samo javne read-only rute. Ne koristiti administratorsku lozinku kao prečac; za pisanje su potrebni novi, posebno projektirani sigurni API endpointi, odgovarajuće WP capability provjere, ograničena autentifikacija i audit log.
- Room cache s označavanjem zastarjelih podataka, preuzimanje datoteka putem Android Storage Access Frameworka, potpuni a11y i tablet QA, automatizirani build, uređajni testovi, pravna provjera i Play Data Safety deklaracija.

Komponente zadržavaju izvorne nazive poput SIDRENA/Brendigo, a WordPress i Windows kod izvan `android/` nisu izmijenjeni.
