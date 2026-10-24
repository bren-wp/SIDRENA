# SIDRENA — dodatni sigurnosni i dead-code audit (24. 10. 2026.)

## Provjereni dio

- Android 0.3.2: stroga provjera WordPress `api_version=1`, strukture javnog kataloga, HTTPS istog podrijetla, jedinstvenih oznaka poslovnica, granice od 500 poslovnica i pravnog profila.
- Windows 0.10.5: odbijanje nepoznate verzije javnog API-ja te validacija informativnih pravnih identifikatora. Testovi za nepoznatu verziju, nedostajuću verziju i neispravan pravni izvor.
- WordPress 1.5.2: zadržani postojeći GET-only javni REST putovi; nije dodana ruta za anonimno uređivanje niti su mijenjani povijesni cjenici.
- Repo audit i dead-code alati: kontinuirane statičke provjere PHP/Kotlin/C#, Android lint i JUnit, .NET Roslyn i regresijski testovi, reproducibilnost pakiranja i SHA-256.

## Konkretnost

**Ispravljeno:** Android ranije nije odbijao nepodržanu API verziju, mogao je tiho ograničiti popis poslovnica na 200 i tolerirao neispravne ili ponovljene zapise. Windows i Android sad koriste isti javni protokol. Dopunjene su provjere izvora pravnih metapodataka.

**Neizmijenjeno:** WordPress povijesne sidrene cijene, referentni datumi, autorizacija i struktura objavljenog CSV/XML sadržaja. WordPress ostaje izvor istine za javne objave, Windows lokalni katalog ne postaje automatski objavljen.

## Granice audita

Alati za otkrivanje mrtvog koda su heuristički i ne brišu WordPress hookove, refleksiju ili WPF XAML handlere na temelju broja tekstualnih referenci. Ovaj audit nije penetracijski test ni formalna potvrda pravne usklađenosti. Regresijska kompilacija/testovi i paketiranje provjeravaju se u GitHub Actions prije izdavanja, a stvarno ponašanje na različitim Android uređajima treba zasebni QA.

## Produkcijski potpis

Android APK je debug, AAB nije produkcijski potpisan; instalater i prijenosna Windows aplikacija nisu Authenticode potpisani. Oznaka izdanja ne smije sugerirati suprotno.
