# SIDRENA — sigurnosni i dead-code audit (24. listopada 2026.)

## Opseg
Provjere obuhvaćaju WordPress/PHP, Android/Kotlin, Windows/.NET, GitHub Actions, izdavanje ZIP/APK/AAB/EXE paketa i poznate mrežne granice. Automatske provjere ne dokazuju potpuno odsustvo svih ranjivosti i nepotrebnog koda; ručni penetracijski testovi, stvarne instalacije i produkcijski promet i dalje su potrebni.

### WordPress 1.5.1
- Javni REST API izlaže samo objavljene podatke; ne vraća privatne ili administratorske zapise.
- Novi pravni profil je **čisto informativan**, bez korisničkih podataka ili write callbacka.
- Filtriranje poslovnica, sanitizacija i stroga HTTPS objava i dalje se provjeravaju. PHP lint, test pravnog ugovora i kontrolni pregled sumnjivih funkcija dio su CI-ja.
- Potvrda pravne usklađenosti nije automatska, povijesne vrijednosti ne smiju se zaključivati iz trenutačne cijene.
- Otvoreno: nezavisna dinamička analiza WordPress capability/nonce matrice, CSRF, XSS, SQL upita i autoriteta kod uvoza, kao i opterećenje velikog kataloga.

### Android 0.3.0
- Kotlin/JVM unit testovi i Android lint.
- TLS (HTTPS) uz isključeni cleartext HTTP, provjera iste domene pri preusmjeravanju, ograničene veličine odgovora i testiran CSV parser.
- Android OkHttp koristi vlastiti DNS resolver za provjeru svih IP adresa u trenutku uspostave veze, bez sistemskog proxyja i bez automatskih preusmjeravanja. Time je uklonjen raniji interval između zasebne DNS provjere i naknadnog razrješavanja unutar URLConnectiona. Preostaje potreba za neovisnim mrežnim penetracijskim testom.
- Nema administratorove lozinke, nije uključen write REST ni oglašivačka telemetrija.
- Otvoreno: neovisno pen-testiranje, provjera Android Keystorea kada se uvede autentifikacija, testovi stvarnih uređaja i potpisani Google Play AAB.

### Windows 0.10.3
- Roslyn statička analiza potencijalno neupotrijebljenih članova, jedinični i UI smoke testovi, lokalni testovi baze i sigurnosne kopije.
- Javni REST endpoint koristi HTTPS, ograničenja veličine, zabranu preusmjeravanja i provjeru stvarnog mrežnog odredišta kroz socket callback.
- Lokalna Windows baza nije server-side WordPress katalog, pa nije dvosmjerno sinkronizirana.
- Otvoreno: nezavisna provjera ovisnosti, potpisi Windows binarnih datoteka, stvarni antivirus/SmartScreen test.

### CI / izdavanje
- API verzija i pravna revizija testiraju se u sva tri runtimea.
- Sve verzije komponenti međusobno se uspoređuju prije izdavanja.
- WordPress ZIP generira se reproducibilno iz istog commit-a, a svi artefakti imaju SHA-256 datoteke.
- GitHub Actions kontrola koristi konkretan uspješan Android i Windows build istog izvornog commit-a.
- Android debug APK i nepotpisani AAB **nisu produkcijski potpisani paketi**.

## Metodologija
PHP lint i ciljani standalone testovi, Kotlin/JUnit/Android lint/R8, .NET/Roslyn/unit/UI testovi, statička provjera tekstnih obrazaca, pretraživanje neupotrijebljenih metoda uz uvažavanje refleksije i WordPress hookova, konzistentnost verzija, pregled ovisnosti i potencijalnih tajni te provjera ZIP integriteta.

**Preostala ograničenja**: automatski dead-code kandidati nisu dokaz mrtvog koda dok se ne provjeri pozivanje kroz WordPress hookove, XAML, refleksiju i aplikacijski životni ciklus. Ne briše se kod samo temeljem pretraživanja teksta.

## Dodatni integracijski test

Windows izvozni CSV sada koristi zaseban serializer sa službenim nazivima WordPress stupaca. Testovi provjeravaju redoslijed i nazive zaglavlja, citiranje polja s točkom-zarezom i navodnicima, mapiranje `proizvod/usluga`, neizmišljanje cijena i očuvanje provjerenog referentnog datuma. Oprez: sam WordPress administratorski uvoz nije pokrenut na produkcijskim podacima.
