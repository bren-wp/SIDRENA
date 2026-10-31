# SIDRENA Android — ekran 02/12: Poveži stranicu

## Referenca

Glavna referenca je **drugi telefon** na korisnički potvrđenom SIDRENA branding boardu (tamno plavo sučelje, bijeli naslov, plavi kružni znak veze, HTTPS polje, narančasti gumb i informativna kartica). Dodatni asset `screens_concept/02_povezivanje.png` služi kao sekundarna referenca za paletu i tipografiju; ne zamjenjuje glavni board.

## Provedeno

- Poseban nativni `ui/SidrenaConnectionScreen.kt`, koristi `ConnectionPage` i odgovarajuće Compose Previewe.
- Izvorni `ConnectionPage` izdvojen iz `HomeScreens.kt`; ne mijenja se prikaz početne, proizvoda, usluga ni arhive.
- `SidrenaApp.kt` tijekom povezivanja skriva naslovni `BrandHeader`, kako bi raspored odgovarao referenci.
- Strelica lijevo: na povezanom računu odustaje od promjene WordPress stranice, a tijekom prvog pokretanja zatvara aplikaciju (ne prikazuje nedjelotvornu akciju).
- URI unos ostaje povezan sa stvarnim `SidrenaViewModel.connect()` i strogom `SiteUrl.parse()` HTTPS validacijom.
- Stvarni status učitavanja, poruke greške i povratak iz prethodno povezanog stanja.
- Svjesno nisu uključene referentne oznake `DEMO` / `KONCEPT`, testni podaci ili simulirane mrežne operacije.
- Prilagodba za kompaktne uređaje i pomični sadržaj kada se prikaže tipkovnica; sadržaj se ne preklapa s navigacijskim trakama.

## Dizajnerske vrijednosti

| Element | Cilj |
|---|---|
| Pozadina | `#07182D` |
| Horizontalne margine | 24 dp |
| Polje URL-a | 64 dp; kartica `#19304A`, radius 14 dp |
| Primarna akcija | `#FF852C`, 56 dp, radius 14 dp |
| Kružni simbol veze | 118 dp / 104 dp za kompaktne zaslone |
| Naslov | 27 sp, bold |
| Tekst objašnjenja | 14 sp / 20 sp line-height |
| Informativna kartica | `#10263D`, border `#28455F`, radius 14 dp |
| Interakcije | onDone na tipkovnici, blokiranje dvostrukog zahtjeva, postojeća validacija |

## Kontrolni popis

- [ ] `./gradlew :app:assembleDebug :app:bundleRelease :app:testDebugUnitTest :app:lintDebug`.
- [ ] Screenshot s Android emulatora: 393 × 852 dp i 360 × 640 dp te overlay usporedba s referencom. Bez toga se ne smije tvrditi brojčana 1:1 preciznost.
- [ ] Test prazan, neispravan HTTPS URL, nepostojeća domena, valjani WordPress sa SIDRENA dodatkom, spora veza i gubitak interneta.
- [ ] Test ulaza iz Postavki, odustajanja od promjene web-lokacije i Android sustavne tipke za povratak.
- [ ] Test font scaling 100%/150%, portrait i tipkovnice bez preklapanja gumba i važnih poruka.
- [ ] Provjeriti da se javni podaci ne izmišljaju i da nema administratorskog uređivanja cijena.

**Napomena:** Konceptualna slika uključuje marketinške detalje, ali se produkcijska UI namjerno prilagođava stvarnoj veličini zaslona, pristupačnosti i ponašanju operacijskog sustava.
