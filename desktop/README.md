<!--
Sidrena source file.
Author: brendigo
Author URI: https://brendigo.com/
Plugin URI: https://brendigo.com/sidrene-cijene/
Support: sidrena@brendigo.com
-->

# SIDRENA Desktop — lokalne cijene i digitalni cjenici

SIDRENA Desktop razvija se kao Windows aplikacija uz postojeće WordPress
i WooCommerce dodatke. Povezana je vizualnim identitetom, ali ne mijenja
postojeće WordPress podatke niti tvrdi da je sinkronizacija aktivna.

## Razvojna verzija 0.2

U aplikaciji se otvara deset funkcionalnih prikaza: Pregled, Artikli,
Usluge, Povijest cijena, Cjenici, Lokacije, Sinkronizacija, Arhiva,
Postavke i Pomoć. Zajednička tema je tamnoplava s narančastim akcentima,
vektorskom navigacijom i stvarnim lokalnim stanjem.

Završene lokalne funkcije uključuju katalog i promjene aktualnih cijena,
zasebno verificiranu sidrenu cijenu, povijesni audit i graf, pretraživanje,
lokacije, radni CSV/XML izvoz, lokalnu evidenciju datoteka i sigurnosnu kopiju
SQLite baze koja uključuje WAL promjene. Nije potrebna registracija ni
telemetrija za lokalni rad.

PDF, službeni zakonski CSV/XML serializer, javna objava, sinkronizacija
s WordPressom/WooCommerceom i serverima, siguran unos pristupnih podataka,
restore, instalacija i digitalni potpis NISU još završeni. Ne postoji
lažni status uspješne objave.

## Pokretanje i provjere

Na Windows 10/11 sa .NET 8 SDK-om pokrenuti:
- dotnet run --project desktop/Sidrena.Tests/Sidrena.Tests.csproj -c Release
- dotnet run --project desktop/Sidrena.Desktop/Sidrena.Desktop.csproj -c Release

GitHub workflow desktop-ci.yml provjerava Release build, smoke testove,
portable Windows x64 EXE, otvaranje svih 10 ekrana i izrađuje stvarne
snimke aplikacije kao zaseban QA artefakt s rokom čuvanja 14 dana.

Baza: %LOCALAPPDATA%\Brendigo\SIDRENA\sidrena.db

## Daljnje poliranje

Kriteriji vizualnog podudaranja i otvorene razlike opisani su u
docs/DESKTOP-UI-PARITY.md. Snimke stvarnog UI-ja nisu dokaz 1:1 same
po sebi: potreban je ručni vizualni pregled i nastavak dorade.

Službeni WordPress dodatak:
https://wordpress.org/plugins/brendigo-sidrena-cijena/
