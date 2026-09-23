<div align="center">
  <img src="assets/images/logo-mark.svg" width="92" height="92" alt="Sidrena logo">
  <h1>Sidrena</h1>
  <p><strong>Besplatan WordPress i WooCommerce plugin za sidrene cijene, javne cjenike, povijest cijena i arhivu 30+ dana.</strong></p>
  <p>
    <a href="https://sidrena-cijena.com.hr/">Web</a> ·
    <a href="https://brendigo.com/">Brendigo</a> ·
    <a href="#instalacija">Instalacija</a> ·
    <a href="#što-sidrena-radi">Mogućnosti</a> ·
    <a href="#razvoj">Razvoj</a>
  </p>
</div>

<p align="center">
  <img alt="Version 1.5.1" src="https://img.shields.io/badge/version-1.5.1-2B6DF7">
  <img alt="WordPress 6.6+" src="https://img.shields.io/badge/WordPress-6.6%2B-21759B">
  <img alt="WooCommerce 8+" src="https://img.shields.io/badge/WooCommerce-8%2B-96588A">
  <img alt="PHP 7.4+" src="https://img.shields.io/badge/PHP-7.4%2B-777BB4">
  <img alt="GPLv2+" src="https://img.shields.io/badge/license-GPLv2%2B-2BC7B8">
  <img alt="Free forever" src="https://img.shields.io/badge/price-100%25%20free-16A085">
  <img alt="No telemetry" src="https://img.shields.io/badge/telemetry-none-0A2038">
</p>

<img src="docs/media/readme-hero.svg" width="100%" alt="Sidrena — sidrene cijene, cjenici i arhiva">

## Sidrene cijene bez ručnog kaosa

**Sidrena** je open-source WordPress plugin autora **Brendigo** napravljen za hrvatske trgovce, webshopove, obrtnike i pružatelje usluga kojima treba uredna evidencija cijena i javni digitalni cjenik.

Umjesto kombiniranja tablica, ručnih exporta i više nepovezanih alata, Sidrena objedinjavanjem WooCommerce podataka, usluga, lokacija, povijesti cijena i javne objave stvara jedan kontrolni centar unutar WordPressa.

**Nema Pro verzije. Nema licence. Nema pretplate. Nema telemetrije. Nema obaveznog vanjskog računa.**

> Sidrena je tehnički alat za evidenciju, prikaz i objavu podataka. Ne predstavlja pravno mišljenje niti automatsko jamstvo usklađenosti pojedinog poslovnog subjekta.

## Što Sidrena radi

<img src="docs/media/readme-features.svg" width="100%" alt="Sidrena ključne mogućnosti">

| Područje | Što dobivate |
| --- | --- |
| **WooCommerce** | Sidrena cijena i referentni datum za proizvode i varijacije, SKU fallback, povijest promjena i bulk katalog. |
| **Usluge** | Vlastiti katalog usluga bez obaveznog WooCommercea, s aktualnom i sidrenom cijenom. |
| **Digitalni cjenici** | Javni CSV i XML, zasebno po fizičkoj lokaciji i webshopu. |
| **Arhiva 30+ dana** | Svaka uspješna objava ostaje zasebna datoteka; zadano čuvanje je 45 dana, programski minimum 30. |
| **Povijest cijena** | Lokalna evidencija proizvoda, varijacija, usluga i lokacijskih vrijednosti, s odvojenim 30-dnevnim referencama za sniženja. |
| **Lokacije** | Cijene, sidrene cijene i raspoloživost po poslovnici; webshop se vodi kao zaseban objekt. |
| **Integritet** | SHA-256 zapis objavljenih datoteka i provjera arhive. |
| **Automatizacija** | Dnevno generiranje, WP-Cron dijagnostika i podrška za pouzdani server cron. |
| **REST** | Javni indeks cjenika i dohvat aktualnih cijena u strojno čitljivom obliku. |
| **Administracija** | Samostalni top-level **Sidrena** meni, moderni tabovi, katalog, audit log, Site Health i WP-CLI. |
| **Privatnost** | Bez telemetrije, udaljene aktivacije, obaveznog clouda ili skrivenog paywalla. |

## Moderan WordPress panel

<img src="docs/media/readme-dashboard.svg" width="100%" alt="Sidrena moderni administracijski panel">

Sidrena nije skrivena u podmeniju WooCommercea. Ima vlastiti glavni WordPress izbornik i jasno odvojena područja za svakodnevni rad:

**Pregled · Usklađenost · Katalog · Cjenici · Arhiva 30+ dana · Lokacije · Postavke · Alati · Dnevnik · Propisi**

Panel je napravljen tako da korisnik prvo vidi što je spremno, što nedostaje i koju radnju treba napraviti — bez otvaranja više različitih WordPress ekrana.

## Kako radi

<img src="docs/media/readme-flow.svg" width="100%" alt="Tok rada Sidrena plugina">

1. **Učitajte podatke** iz WooCommerce proizvoda i/ili Sidrena kataloga usluga.
2. **Vodite sidrene i aktualne cijene**, referentne datume, povijest te lokacijske vrijednosti.
3. **Generirajte i objavite** CSV/XML cjenike, javnu WordPress stranicu i REST podatke.
4. **Sačuvajte povijest objava** najmanje 30 dana i provjeravajte integritet arhive.

## Arhiva koja ne prepisuje povijest

Sidrena svaku uspješnu generaciju sprema kao novu javnu datoteku. Prethodni cjenici ne nestaju kada nastane novi.

Svaki arhivski zapis može sadržavati vrijeme objave, lokaciju, vrstu kataloga, format, broj redaka, veličinu datoteke, rok čuvanja i SHA-256 sažetak. Trenutačno važeća datoteka dodatno se štiti od automatskog uklanjanja samo zato što je starija od arhivskog prozora.

**Javna arhiva cjenika i interna povijest za izračun najniže cijene prije sniženja dvije su različite evidencije. Sidrena ih vodi odvojeno.**

## WooCommerce bez ručnog uređivanja svakog artikla

Sidrena radi s običnim i varijabilnim WooCommerce proizvodima. Za katalog može voditi:

- aktualnu cijenu
- sidrenu cijenu i referentni datum
- marku i šifru artikla
- barkod kada je dostupan
- posebni oblik prodaje
- lokacijsku cijenu i raspoloživost
- povijest promjena
- 30-dnevnu referencu za sniženja kada postoji dovoljno provjerljivih podataka

Bulk katalog omogućuje obradu većeg broja proizvoda bez otvaranja svakog proizvoda zasebno.

## Usluge rade i bez WooCommercea

Za poslovanja koja ne trebaju webshop Sidrena ima vlastiti katalog usluga. Usluga može imati aktualnu i sidrenu cijenu, referentni datum, opis vrste/opsega te dodatne podatke potrebne za javni prikaz i cjenik.

Shortcode `[sidrena_usluge]` omogućuje objavu cjenika usluga na WordPress stranici.

## Više poslovnica + webshop

Svaka fizička poslovnica može imati vlastitu cijenu, sidrenu cijenu i raspoloživost artikla. Webshop se može voditi kao zaseban objekt.

Lokacijski import podržava mapiranje prema `product_id` ili `sku`, a prazna lokacijska cijena može naslijediti osnovnu WooCommerce cijenu.

## Javni API i shortcodeovi

### REST endpointi

```text
/wp-json/sidrena/v1/cjenici
/wp-json/sidrena/v1/cijene
```

Endpoint aktualnih cijena podržava filtriranje prema vrsti i lokaciji te paginaciju. Odgovori za aktualne cijene koriste no-cache/no-store zaglavlja kako bi dohvat bio što bliži trenutačnom stanju WordPressa.

### Shortcodeovi

```text
[sidrena_cjenici]
[sidrena_usluge]
[sidrena_cijena]
```

### WP-CLI

```bash
wp sidrena generate
wp sidrena status
wp sidrena audit
```

## Privatnost i sigurnost

Sidrena je napravljena kao lokalni WordPress plugin. Runtime ne zahtijeva vanjski račun ili cloud servis.

- WordPress capability i nonce provjere za administracijske radnje
- sanitizacija i ograničenja CSV uvoza
- privremeni zapis prije zamjene konačne CSV/XML datoteke
- SHA-256 evidencija arhive
- bez telemetrije
- bez udaljene licence
- bez Pro paywalla
- bez obaveznog vanjskog API-ja

## Tehnička podrška za hrvatska pravila cijena

Projekt je razvijen kao tehnička podrška za rad s pravilima koja Sidrena dokumentacija prati, uključujući **NN 101/2026** i službena pojašnjenja nadležnog ministarstva iz rujna 2026.

Referentni datumi, iznimke i primjenjivost pojedinih pravila ovise o vrsti poslovanja i konkretnom proizvodu/usluzi. Zato plugin namjerno ne izmišlja povijesne vrijednosti koje WordPress nije zabilježio.

Detaljnije bilješke: [docs/legal-and-technical-notes.md](docs/legal-and-technical-notes.md)

Službeni izvori koje projekt koristi u dokumentaciji:

- Narodne novine — NN 101/2026
- Ministarstvo gospodarstva — pojašnjenja za dodatnu cijenu i objavu cjenika
- relevantne odredbe Zakona o zaštiti potrošača

## Instalacija

1. Preuzmite aktualni `sidrena-x.y.z.zip`.
2. U WordPressu otvorite **Dodaci → Dodaj novi → Prenesi dodatak**.
3. Instalirajte i aktivirajte Sidrena plugin.
4. Otvorite glavni **Sidrena** izbornik.
5. Odaberite način rada: proizvodi, usluge ili kombinirano.
6. Dodajte poslovnice i zaseban webshop ako ga koristite.
7. Provjerite sidrene cijene i referentne datume.
8. Generirajte prvi cjenik i provjerite kartice **Cjenici** i **Arhiva 30+ dana**.
9. Za poslovno kritično izvršavanje u točno određeno vrijeme koristite pouzdani server cron koji pokreće WordPress cron.

## Zahtjevi

| Komponenta | Zahtjev |
| --- | --- |
| WordPress | 6.6+ |
| PHP | 7.4+ |
| WooCommerce | 8.0+ samo ako koristite proizvode |
| Licenca | GPLv2 ili novija |
| Telemetrija | Nema |
| Pretplata | Nema |

## Struktura projekta

```text
sidrena/
├── admin/                  # admin CSS i JS
├── assets/images/          # Sidrena brand/logo
├── docs/                   # pravne, tehničke i marketing bilješke
├── includes/               # plugin klase i poslovna logika
├── languages/              # prijevodi / POT
├── public/                 # frontend stilovi
├── sidrena.php             # plugin bootstrap
├── readme.txt              # WordPress.org readme
└── uninstall.php           # uninstall cleanup
```

## Razvoj

Aktivni razvoj vodi se na GitHubu uz CI provjere za PHP i JavaScript, metapodatke WordPress.org paketa, stari branding i inline script/style blokove.

GitHub release workflow može iz taga izgraditi instalacijski ZIP i SHA-256 checksum.

Za prijave problema koristite GitHub Issues, a za promjene Pull Request workflow.

## Zašto je Sidrena besplatna

Cilj projekta je ponuditi kvalitetan, transparentan i provjerljiv WordPress alat bez zaključavanja osnovne funkcionalnosti iza pretplate.

Sidrena zato nema “lite” izdanje koje služi samo kao reklama za Pro. Kod je open source, funkcionalnost ostaje lokalna, a razvoj se javno prati na GitHubu.

## Autor i projekt

**Sidrena** razvija [Brendigo](https://brendigo.com/).

Službena stranica plugina: **https://sidrena-cijena.com.hr/**

Repozitorij: **https://github.com/bren-wp/SIDRENA**

## Licenca

Sidrena je objavljena pod **GPLv2 or later** licencom. Pogledajte [LICENSE](LICENSE).

---

<div align="center">
  <img src="assets/images/logo-mark.svg" width="48" height="48" alt="Sidrena">
  <p><strong>Sidrena — cijene pod kontrolom.</strong></p>
  <p>WordPress · WooCommerce · CSV/XML · REST · 30+ dana arhive</p>
</div>
