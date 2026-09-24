<!--
Sidrena source file.
Author: Brendigo
Author URI: https://brendigo.com/
Plugin URI: https://sidrene-cijene.com.hr/
Support: sidrena@brendigo.com
-->

# Sidrena Windows

Sidrena Windows je premium nativna Windows desktop aplikacija za pripremu, provjeru i izvoz sidrenih cijena prije javne objave kroz Sidrena WordPress/WooCommerce plugin ili kroz lokalni export paket.

Ova linija je razvojni desktop temelj unutar repozitorija. Ne predstavlja objavljen Windows release dok CI, release workflow i asset verifikacija ne prođu kroz službeni GitHub proces.

## Cilj

Aplikacija nije zamjena za web plugin. Ona je nativni radni alat za trgovca:

1. uvoz kataloga iz CSV/XML izvora,
2. jasno označen XLSX plan bez lažne podrške,
3. lokalna provjera aktualne, sidrene i jedinične cijene,
4. provjera referentnih datuma 10.09.2026. i 02.05.2025.,
5. priprema CSV/XML/HTML exporta,
6. arhiviranje svake pripreme,
7. buduća sigurna sinkronizacija prema Sidrena WordPress/WooCommerce API-ju.

## Native Windows smjer

- Nativna WinUI 3 / Windows App SDK desktop aplikacija.
- Bez Electrona, bez Tauri webviewa, bez React/HTML shell pristupa i bez web stranice u desktop prozoru.
- Bez legacy Windows Forms/WPF pristupa i bez Win32-style UI sloja.
- Windows 11 stil: Mica, NavigationView, InfoBar, zaobljene kartice, čisti status badges i Fluent spacing.
- Izvršavanje kao lokalna Windows aplikacija, s C# servisima za katalog, provjeru, import/export i sync stub.
- Aplikacija lokalno obrađuje katalog i ne šalje poslovne podatke Brendigu.
- Lokalni export ide u korisnički `%LOCALAPPDATA%/SidrenaDesktop/exports` direktorij.
- Legal-readiness panel je tehnička provjera prije objave; konačnu procjenu usklađenosti radi korisnik ili njegov stručni savjetnik.

## Arhitektura

```text
windows/
├─ Sidrena.Windows.sln
├─ Sidrena.Windows/
│  ├─ App.xaml
│  ├─ MainWindow.xaml
│  ├─ Models/
│  ├─ ViewModels/
│  ├─ Views/
│  ├─ Services/
│  └─ Assets/
└─ packaging/
```

Slojevi:

- `Views` — WinUI stranice: Dashboard, Katalog, Import/Export, Legal readiness i WordPress sync.
- `ViewModels` — workspace modeli za prikaz i osvježavanje kataloga.
- `Models` — katalog, rezultati importa/exporta, readiness issues i sync postavke.
- `Services` — demo katalog, tehnička provjera, CSV/XML/HTML export, import preview, dashboard snapshot i WordPress REST sync stub.
- `packaging` — Inno Setup nacrt za `setup.exe`; `portable.exe` se gradi kao launcher bez instalacijskog čarobnjaka.

## UX opis

Aplikacija koristi Windows 11 / Fluent smjer: tamni sidebar, gornju titlebar zonu, hero dashboard, kartice s metrikama, status poruke i jasne akcije. Sve glavne opcije imaju stvaran lokalni handler:

- Dashboard: provjera kataloga, lokalni export i ponovno učitavanje demo kataloga.
- Katalog: prikaz proizvoda/usluga i demo auto-fix očitih tehničkih grešaka.
- Import/Export: CSV/XML preview import, jasno označen XLSX plan i CSV/XML/HTML export.
- Legal readiness: prikaz referentnih datuma, NN napomena i rezultata provjere.
- WordPress sync: lokalni nacrt veze i sync preview bez mrežnog slanja.

## Sigurnost i privatnost

- Aplikacija ne koristi telemetriju.
- Katalog se ne šalje Brendigu.
- Prva desktop verzija ne radi mrežni prijenos kataloga.
- WordPress REST sync je pripremljen kao apstrakcija za kasniji ručno konfigurirani prijenos.
- Tokeni i tajne nisu implementirani u skeletonu i ne smiju se hardkodirati.
- Lokalni export ostaje na računalu korisnika.

## Zakonsko-tehničke napomene

Desktop app uključuje tehničke markere:

- referentni datum 10.09.2026. za novobuhvaćene proizvode/usluge,
- referentni datum 02.05.2025. za ranije obuhvaćene FMCG kategorije,
- datum primjene 01.10.2026.,
- NN 101/2026,
- NN 105/2026,
- službena pojašnjenja Ministarstva gospodarstva od 22.09.2026.

Aplikacija tehnički pomaže u pripremi podataka, ali ne daje automatsku pravnu potvrdu konkretnog poslovanja.

## Build smjer

Projekt je postavljen kao nativna Windows App SDK desktop aplikacija:

```powershell
dotnet restore windows/Sidrena.Windows/Sidrena.Windows.csproj
dotnet build windows/Sidrena.Windows/Sidrena.Windows.csproj -c Release
```

Službeni release workflow objavljuje dva Windows asseta:

- `portable.exe` — direktno pokretanje bez instalacijskog čarobnjaka; raspakira runtime u prijenosnu radnu mapu i pokreće aplikaciju.
- `setup.exe` — standardni Windows installer.

Ručno napravljeni lokalni ZIP paketi nisu službeni release artefakti.

## Veza s postojećim pluginima

Sidrena Windows priprema lokalni katalog i export datoteke koje su konceptualno usklađene s postojećim pluginima:

- `sidrena-wordpress`
- `sidrena-woocommerce`

Ne mijenja WordPress/WooCommerce build pravila i ne vraća root/generic/static PHP paket.