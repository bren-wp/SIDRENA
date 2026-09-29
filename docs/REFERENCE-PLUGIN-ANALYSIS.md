# Analiza referentnih plugina

Datum pregleda: 29. 9. 2026.

Ovaj dokument bilježi funkcionalnu analizu dvaju plugina koje je korisnik dostavio kao referentni materijal:

- `canis-price-complience-hr` 0.7.0
- `canis-woo-price-compliance-hr` 2.1.1

Oba paketa deklariraju GPLv2-or-later licencu. SIDRENA ne preuzima njihov kod, tekstove, dizajn ni assete. Koriste se samo za usporedbu funkcionalnih obrazaca i ideja.

## Sažetak

SIDRENA ostaje vlastiti sustav s dva zasebna izdanja, vlastitim javnim slugovima/text-domainima, vlastitim modelom SIDRENA cijene i centraliziranim pravnim rulesetom. Korisne ideje iz referentnih plugina implementiraju se vlastitim kodom samo ako poboljšavaju postojeću SIDRENA arhitekturu.

## Referentni standalone plugin

### Što radi

Referentni standalone plugin ima vlastiti katalog, proizvode i usluge, više lokacija, CSV/XML import/export, javni digitalni cjenik, javnu arhivu, jedinične cijene, povijest cijena, zaseban koncept sidrene cijene i 30-dnevne najniže cijene te interni i vanjski cron model.

Uočeni korisni obrasci:

- jasno odvajanje SIDRENA/referentne cijene od 30-dnevne najniže cijene;
- zaseban importer/exporter sloj;
- ograničenje uvoza veličinom i brojem redaka;
- server-cron način rada kao alternativa WP-Cronu;
- audit događaji za mutacije i publikacije;
- zaštita vlastitog export direktorija;
- grupiranje javne arhive po publikaciji/batchu.

### SIDRENA stanje i odluke

SIDRENA već ima jače atomic-write i locking ponašanje, SHA-256 metapodatke, zaseban current/archive lifecycle, recovery zadnje valjane verzije i odvojene javne/strojne površine.

Preuzeta je ideja odabira scheduler načina, ali implementirana vlastitim SIDRENA kodom:

- interni WordPress WP-Cron;
- vanjski server cron / WP-CLI;
- u vanjskom načinu SIDRENA uklanja interni dnevni cron;
- watchdog ostaje aktivan samo kao nadzor i upozorenje;
- `wp sidrena publish` izvršava dnevnu publikaciju.

Nije preuzet njihov model globalno promjenjivog ili fallback referentnog datuma. SIDRENA zakonske datume vodi u vlastitom zaključanom rulesetu.

## Referentni WooCommerce plugin

### Što radi

Referentni WooCommerce plugin ima tracker povijesti regularne, sale i efektivne cijene, resolver referentne cijene, 30-dnevni minimum, zaključane snapshotove, cron/pricelist sloj, CSV import/export te podršku za proizvode i varijacije.

Korisni obrasci:

- odvojeni tracker i resolver slojevi;
- eksplicitno neizmišljanje 30-dnevne cijene kad povijest nije potpuna;
- obrada varijacija;
- zaseban lifecycle posebnog oblika prodaje;
- izbor internog ili vanjskog schedulera;
- ograničen i validiran CSV import.

### SIDRENA stanje i odluke

SIDRENA već razdvaja:

1. aktualnu cijenu;
2. SIDRENA/referentnu cijenu;
3. datum SIDRENA cijene;
4. najnižu cijenu prethodnih 30 dana kada je primjenjivo;
5. WooCommerce regular/sale/current lifecycle.

SIDRENA ne preuzima fallback koji bi današnju regularnu cijenu koristio kao povijesnu zakonsku SIDRENA cijenu. Ako nedostaje dokazivi povijesni podatak, stanje ostaje nepotpuno/potrebna provjera.

Za novu stavku nakon zakonskog referentnog datuma SIDRENA dopušta samo item-level iznimku s dokazivim datumom prvog objavljivanja i audit tragom. Iznimka nikad ne postaje globalni ruleset.

## Funkcionalna usporedba

### Već jače riješeno u SIDRENA

- atomic write preko privremene datoteke i rename/commit;
- process-level file locking;
- zadnja valjana javna verzija ostaje dostupna nakon greške;
- SHA-256 integritet javnih datoteka;
- odvojeni aktualni cjenik i arhiva;
- osvježavanje aktualnog cjenika bez stvaranja arhive;
- content-hash duplicate archive detection;
- javni HTML + CSV + XML + JSON manifest + REST;
- kanonski location ID i javni location code;
- zaključani zakonski datumi u backend rulesetu;
- migration starih editable pravnih datuma iz aktivnih postavki u audit-only snapshot;
- neograničena povijest proizvoda, usluga i lokacijskih cijena;
- server-side provjera stvarne temp veličine uvozne datoteke;
- formula-injection zaštita pri CSV exportu;
- dva odvojena instalacijska izdanja s vlastitim javnim slugovima.

### Ideje koje su usvojene

- vanjski server cron / WP-CLI način dnevne objave;
- dodatno naglašena razlika SIDRENA cijene i 30-dnevnog minimuma;
- zadržavanje audit traila bez izmišljanja nedostajuće povijesti;
- jasniji scheduler status u CLI/admin sloju.

### Ideje koje nisu preuzete

- globalno editable zakonski referentni datumi;
- fallback na trenutačnu cijenu kao povijesni SIDRENA podatak;
- automatsko stvaranje pravnog datuma bez dokazivog izvora;
- zaseban javni ON/OFF prekidač koji bi administratoru omogućio slučajno gašenje obveznih publikacijskih površina;
- tuđi UI, tekstovi, kod, nazivi, asseti ili branding;
- remote executable code, telemetrija, license key, premium modul ili paywall.

## Regression pokriće

Promjene inspirirane analizom moraju ostati pokrivene postojećim ili novim testovima za:

- zaključani ruleset;
- sigurnu migraciju starih datuma;
- 30-dnevni minimum kao zaseban koncept;
- neograničenu povijest;
- upload temp-size validation;
- current/archive separation;
- duplicate archive detection;
- internal/external scheduler mode;
- WP-CLI dnevnu publikaciju;
- PHP 7.4 / 8.3 / 8.4;
- WPCS, PHPStan, Plugin Check i distribution/legal/admin guardove.
