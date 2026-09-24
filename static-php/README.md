# Sidrena Static PHP

Samostalno izdanje Sidrene za web stranice bez WordPressa, WooCommercea, baze podataka i admin panela.

## Minimalni zahtjevi

- PHP 7.4+
- mogućnost zapisivanja u `storage/`
- Apache, LiteSpeed, nginx ili IIS
- preporučeno: cron/CLI pristup, ali nije obavezan

## Brza instalacija

1. Uploadate sadržaj ovog ZIP-a u npr. `/sidrena/` na hostingu.
2. Uredite `config.php`.
3. U `storage/source/products.csv` unesite proizvode.
4. Po potrebi u `storage/source/services.csv` unesite usluge.
5. Otvorite `index.php`. Zadano auto-generiranje izradit će javni snapshot ako je source promijenjen.
6. Za pouzdan produkcijski rad postavite cron:

   `php /putanja/do/sidrena/generate.php`

## Bez admin panela

Nema login forme, baze korisnika ni CMS-a. CSV datoteke su izvor podataka. Možete ih održavati lokalno i uploadati SFTP/FTP/deployment procesom.

## Sigurnost

`storage/` je privatni direktorij. Paket uključuje Apache `.htaccess` i IIS `web.config` zabranu direktnog pristupa. Na nginxu obavezno dodajte:

```nginx
location ^~ /sidrena/storage/ {
    deny all;
    return 404;
}
```

Generirani CSV/XML se ne otvaraju direktno iz storagea, nego kroz `download.php`, koji dopušta samo datoteke registrirane u Sidrena manifestu/arhivi.

`generate.php` je iz CLI-ja uvijek dostupan. HTTP generiranje je po defaultu isključeno. Ako ga baš trebate, postavite `SIDRENA_GENERATE_TOKEN` environment varijablu ili `web_generate_token` u configu. HTTP endpoint prihvaća samo **POST**; token šaljite kroz `X-Sidrena-Token` ili `Authorization: Bearer ...` header (POST polje `token` postoji samo kao kompatibilni fallback). Token se namjerno ne prima kroz URL/query string kako ne bi završio u access logovima.

## Proizvodi

Minimalno se vode: naziv, šifra, marka, maloprodajna cijena, sidrena cijena, barkod i dostupnost. `jedinicna_cijena_status` može biti:

- `required`
- `not_required`
- `exception`
- `review`

Ako je status `required`, a jedinična cijena nije unesena, Sidrena je može automatski izračunati iz `kolicina_pakiranja`, `jedinica_pakiranja` i maloprodajne cijene.

Primjer:

```csv
Kava 750 g;KAVA-750;Primjer;750;g;required;;;6,00;ne;;5,50;2026-09-10;3850000000000;dostupno
```

## Usluge

Primjer:

```csv
Montaža uređaja;montaža;do 60 min;dolazak uključen;;45,00;ne;;40,00;2026-09-10
```

## Javni endpointi

- `index.php` — responsivni javni cjenik
- `api.php` — JSON snapshot proizvoda i usluga
- `health.php` — JSON health/status za uptime monitoring i cron provjere
- `download.php?file=products_csv`
- `download.php?file=products_xml`
- `download.php?file=services_csv`
- `download.php?file=services_xml`

Arhivske poveznice generira `index.php` iz internog archive indexa.

## Generiranje i integritet

Svaka objava koristi non-blocking `flock`, temp datoteku i atomski rename. Ako validacija ili zapis ne uspije, prethodni generirani cjenik ostaje netaknut.

Za svaku datoteku manifest sadrži:

- broj redaka
- veličinu
- SHA-256
- javni naziv datoteke
- vrijeme generiranja

Arhiva se čuva najmanje 30 dana.

## Datoteke

- `config.php` — postavke
- `src/SidrenaStatic.php` — engine
- `storage/source/products.csv` — izvor proizvoda
- `storage/source/services.csv` — izvor usluga
- `generate.php` — CLI/zaštićeni web generator
- `index.php` — HTML prikaz
- `api.php` — JSON
- `health.php` — health/status endpoint bez privatnih putanja
- `download.php` — sigurni download
- `assets/` — lokalni CSS/JS, bez vanjskih dependencyja

Službena stranica: https://sidrene-cijene.com.hr/
