# SIDRENA Android izdavanje i QA

Android verzija 0.1.0 i WordPress verzija 1.5.0 **nisu ista verzija**. Windows verzija ostaje 0.10.0 dok se ne promijeni Windows kod i ne prođe release provjera.

## CI za Android
`.github/workflows/android-ci.yml` gradi kod na Ubuntu GitHub runneru s JDK 17, Android SDK 36 i Gradle 8.13:
- Android dead-code/security preflight (konzervativna statička provjera)
- Android JUnit testovi i Android lint
- Instalacijski **debug APK** za interno testiranje
- **Nepotpisani release AAB** za validaciju gradnje, ne za objavu na Google Playu
- SHA-256 checksum za obje datoteke

Ako prođe CI na `main`, može dodati te **testne** datoteke u postojeće izdanje `1.5.0`; ne mijenja WordPress ZIP. Niti jedan od tih artefakata **nije službeni potpisani Android release**. Stabilno Android potpisivanje zahtijeva trajni privatni upload ključ pohranjen u GitHub Actions secrets i odobren release postupak. Nikad ne committati keystore ili lozinke.

## Windows i WordPress
Windows desktop CI i dalje odvojeno testira WPF aplikaciju, SQL integritet, deset ekrana i Inno Setup instalaciju. Njegovo objavljivanje ne smije ukloniti Android datoteke. WordPress dodatak i ZIP ostaju 1.5.0 dok se `wordpress/` ne mijenja. Svaka promjena WordPress koda zahtijeva zasebno povećanje WordPress verzije i novi reproducibilni ZIP.

## Još otvoreno
Službena release verzija s potpisanim AAB/APK, test na stvarnom telefonu, Play Data Safety, uređivanje WordPress podataka uz autentifikaciju i interoperabilnost s WooCommerce/WP nisu verificirani. Ne označavati aplikaciju kao potpuno dovršenu ili kao produkcijski potpisanu prije tih provjera.
