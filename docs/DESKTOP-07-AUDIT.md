# SIDRENA Windows Desktop 0.7 — pregled, audit i status podudaranja

Datum: 16. 10. 2026.

## Potvrđeni nalazi ispravljeni u ovom PR-u

| Područje | Prije | Sada |
|---|---|---|
| Windows prozor | Fiksnih 1550×910 DIP, min širina 1000; uskim monitorima nije odgovaralo | Normalan prozor do 88% radne površine, najveća početna veličina 1320×820; min 560×420 i ručna promjena veličine |
| Navigacija | 246 DIP čak i na malim prozorima | 75 DIP s vektorskim ikonama, naziv stranice dostupan kroz tooltip i pristupačno ime |
| Dvostupčani prikazi | Min širine 400+325 DIP uz fiksne margine | Dva stupca pri većoj širini; ispod 800 DIP raspored jedan ispod drugog |
| KPI kartice | Fiksna mreža četiri kartice; rizik vodoravnog skrolanja | WrapPanel automatski prelama četiri/dvije/jednu karticu |
| Glavni ScrollViewer | Vodoravni scrollbar skrivao loše raspoređene kontrole | Ograničena raspoloživa širina; zasebno vodoravno pomicanje u samim tablicama |
| Forme / tipkovnica | Nedostajala programatska veza opisa i polja; fokus nejasan | AutomationProperties.LabeledBy, pregledniji obrubi na fokus/pogrešku, 40 DIP ciljevi gumba |
| Dashboard performanse | Materijalizirao sve artikle i najviše 10.000 promjena za prikaz brojila | SQLite COUNT upiti daju točne ukupan broj / potvrđene reference / promjene / izvoze |
| Povijest performanse | Ponovljeni upit do 10.000 redaka pri svakom tipu ili selekciji | Jedan snapshot po otvaranju ekrana; filtri su lokalni |
| WP javni REST API | Prikaz 4 osnovna izvora, bez izvora po poslovnici | Javni cjenik, arhiva, `type_archive`, `combined`, vrste i formati, poslovnice i vlastiti CSV/XML feedovi |
| Dead code | Neaktivni `ShowUpcoming` handler bez poziva; parametri `gap` u UIkit nikad se nisu koristili | Handler i mrtvi parametri uklonjeni |

## Provjeren ugovor s WordPress.org dodatkom

Službeni izvor: `wordpress/brendigo-sidrena-cijena/includes/class-public-api.php`, verzija dodatka **1.5.0**.
Odgovor GET `/wp-json/brendigo-sidrena/v1/cjenici` sadrži
`published_at`, `cjenik`, `archive`, `type_archive`,
`combined`, `feeds.proizvodi.csv/xml`, `feeds.usluge.csv/xml` te
`outlets[].name/code/feeds.proizvodi/usluge.csv/xml`.
Windows sada razumije sva navedena polja bez API lozinke i prikazuje
samo čitalačke poveznice.

Obrambene provjere: HTTPS, isti host/port kao izvorišna stranica,
nedozvoljeni vanjski URL-ovi, ograničena veličina JSON-a, valjani
kodovi poslovnica i testiranje povratnog odgovora bez vanjskog poziva.

**Nije dovršeno:** autorizirano pisanje/upis u WP, dvosmjerni import,
spajanje razlika cijena, rasporedi objava, propisani javni
PDF/CSV/XML serializer, autentikacija i provjera kompatibilnosti svake
kombinacije WooCommerce proizvoda/varijacija i poslovnica.
Ne prikazivati ih kao funkcionalne ili zakonski verificirane.

## QA i otvoreni kriteriji

- Automatski Windows build, SQLite/API testovi, 10 zaslona i dodatne
  dimenzije prozora 760×560/1060×690 DIP.
- Testovi čistog DPI layout algoritma pri 1920×1080 i 800×600.
- Vizualni cilj: deset korisničkih referenci; stvarne WPF snimke
  **nisu dokaz pixel-perfect podudaranja**.
- Sljedeća ručna provjera: Windows 10/11 na 100%, 125%, 150% i 200%,
  1366×768 i 1920×1080, tablične opcije, touch i tipkovnica.
- Potpuni Roslyn call-graph / dependency audit trećih paketa i
  ručna validacija svih deset ekrana još su otvoreni. Ovaj dokument
  ne tvrdi da je cjelokupni dead-code audit završen.
- Službeni WordPress.org dodatak i jedino izdanje/tag **1.5.0**
  ostaju nepromijenjeni. Windows 0.7 isporučuje se kroz postojeće
  GitHub Release datoteke Portable.exe, Setup.exe i SHA-256 nakon
  uspješnog CI-ja na main.
