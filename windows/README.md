# Sidrena Windows

Sidrena Windows je premium desktop linija za pripremu, provjeru i izvoz sidrenih cijena prije javne objave kroz Sidrena WordPress/WooCommerce plugin ili kroz lokalni export paket.

## Cilj

Aplikacija nije zamjena za web plugin. Ona je radni alat za trgovca:

1. uvoz kataloga iz CSV/XLSX/XML izvora,
2. lokalna provjera aktualne, sidrene i jedinične cijene,
3. provjera referentnih datuma 10.09.2026. i 02.05.2025.,
4. priprema CSV/XML/HTML exporta,
5. arhiviranje svake pripreme,
6. buduća sigurna sinkronizacija prema Sidrena WordPress/WooCommerce API-ju.

## Premium Windows smjer

- WinUI 3 / Windows App SDK sučelje.
- Mica pozadina, tamni premium layout i kartični dashboard.
- Bez legacy Windows Forms/WPF pristupa.
- Bez telemetrije i bez slanja poslovnih podataka Brendigu.
- Lokalni export ide u korisnički `%LOCALAPPDATA%/SidrenaDesktop/exports` direktorij.
- Legal-readiness panel je tehnička provjera, ne pravna garancija.

## Trenutni opseg

Ova prva `/windows` verzija dodaje:

- WinUI projekt `Sidrena.Windows`.
- Premium dashboard shell.
- Demo katalog i readiness provjeru.
- Lokalni CSV/XML/HTML export servis.
- Smoke test koji čuva da aplikacija ostane moderna Windows App SDK linija.

## Plan sljedećih koraka

1. pravi uvoz CSV/XLSX/XML datoteka,
2. SQLite lokalna baza,
3. editor proizvoda i usluga,
4. provjera duplih SKU-ova i lokacija,
5. secure WordPress REST sinkronizacija,
6. signed installer / MSIX packaging,
7. offline/online status i audit export.

## Build smjer

Projekt je postavljen kao Windows App SDK desktop aplikacija:

```powershell
dotnet restore windows/Sidrena.Windows/Sidrena.Windows.csproj
dotnet build windows/Sidrena.Windows/Sidrena.Windows.csproj -c Release
```

Za produkcijski installer treba zaseban potpisani packaging korak.