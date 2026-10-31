# SIDRENA Android — ekran 01: Splash

Referenca: korisnički odobreni SIDRENA branding board i `SIDRENA-Android-Branding/screens_concept/01_splash.png`.

## Implementacija

- Kotlin/Jetpack Compose: `ui/SidrenaSplashScreen.kt`.
- Pokretanje: `MainActivity` prikazuje splash jednom prije postojećeg `SidrenaApp()`.
- Tamnoplava podloga `#07182D`, geometrijski tonovi, narančasti postojeći resurs `ic_sidrena_mark`, plavi obrub, bijeli naziv i slogan.
- Narančasta traka učitavanja animira se linearno kroz **2200 ms**, zatim se otvara postojeće sučelje (povezivanje ili početna, ovisno o stvarnom stanju aplikacije).
- Ne pokreće se lažni mrežni zahtjev, ne modificira WordPress konfiguracija i ne uvodi se nova vanjska ovisnost.
- Previews: 393 × 852 dp i 360 × 640 dp (kompaktni raspored).

## Ručna vizualna provjera

1. Čisti start, ponovno otvaranje i rotacija tijekom animacije.
2. Provjera na 360 × 640 dp, 393 × 852 dp i višim zaslonima.
3. Provjera da statusna i navigacijska traka imaju tamnu podlogu i svijetle ikonice.
4. Snimiti screenshot stvarnog emulatora i usporediti s referentnim boardom uz overlay/diff; brojčani 1:1 rezultat nije potvrđen bez te usporedbe.
5. Osigurati da nakon animacije prijelaz ide u postojeći WordPress povezani/nepovezani tok.
6. Provjeriti Android 12+ sustavni splash i prijelaz prema Compose zaslonu bez vidljivog bijelog bljeska.

## Provjera izgradnje

Iz mape `android/`: `./gradlew :app:assembleDebug :app:testDebugUnitTest :app:lintDebug`.

Ova izmjena ne tvrdi da su Android testovi pokrenuti bez dostupnog Android SDK/Gradle okruženja.
