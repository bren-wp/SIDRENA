<!--
Sidrena source file.
Author: Brendigo
Author URI: https://brendigo.com/
Plugin URI: https://brendigo.com/sidrene-cijene/
Support: sidrena@brendigo.com
-->

<p align="center">
  <img src="assets/images/logo-mark.svg" alt="Sidrena" width="48" height="48">
</p>

<h1 align="center">Sidrena</h1>

<p align="center">
  <strong>WordPress/WooCommerce dodatak za sidrene cijene, javne cjenike i arhivu objava.</strong><br>
  <a href="https://brendigo.com/sidrene-cijene/">brendigo.com/sidrene-cijene</a>
</p>

### Sidrena WordPress

![Stvarni Sidrena WordPress administracijski ekran](docs/media/screenshot-wordpress.png)

### Sidrena WooCommerce

![Stvarni Sidrena WooCommerce administracijski ekran](docs/media/screenshot-woocommerce.png)

## Izdanje 0.8.1

Projekt isporučuje dva odvojena instalacijska paketa:

| Paket | Namjena | Izvor proizvoda |
|---|---|---|
| **Sidrena WordPress** | WordPress bez WooCommercea | Sidrena katalog ili povezani javni WordPress sadržaj |
| **Sidrena WooCommerce** | WordPress + WooCommerce | postojeći WooCommerce proizvodi i varijacije |

Istodobno smije biti aktivno samo jedno Sidrena izdanje. Ugrađeni conflict guard sprječava dvostruke hookove i objave.

## Administracija

WordPress bočni meni namjerno je kratak:

- **Početak**
- **Proizvodi**
- **Usluge**
- **Objava cjenika**
- **Lokacije / webshop**
- **Zakonske postavke**
- **Pomoć**

Sidrena koristi vlastitu lokalnu sidro ikonicu u WordPress meniju. Sekundarne funkcije poput arhive, tehničke provjere, dijagnostike, dnevnika i dokumentacije otvaraju se kao podsekcije postojećih radnih stranica; ne registriraju dodatne skrivene ili duplicirane stavke u bočnom meniju.

## Glavne mogućnosti

- sidrena/referentna i aktualna cijena
- jedinična cijena kada je primjenjiva
- proizvodi, usluge, varijacije i lokacije
- javni CSV/XML cjenici
- pretraživi javni HTML cjenik
- arhiva prethodnih objava najmanje 30 dana
- automatizirana dnevna objava i provjera propuštenog rasporeda
- streaming obrada većih kataloga
- lokalni audit zapis s ograničenjem rasta
- bez ugrađene telemetrije i praćenja korisnika
- nonce/capability zaštita administrativnih akcija
- WordPress HTTP API za provjeru vlastitih javnih datoteka

## WordPress.org priprema

Za oba izdanja repozitorij sadrži zasebne WordPress.org assete u:

- `wporg-assets/sidrena-wordpress/assets/`
- `wporg-assets/sidrena-woocommerce/assets/`

Asseti sadrže lokalne PNG ikone, bannere izvedene iz stvarnog administracijskog prikaza i stvarne screenshotove snimljene iz aktivnog WordPress admin sučelja. Install ZIP ne uključuje WordPress.org listing bannere, jer WordPress.org zahtijeva da se oni nalaze u zasebnom SVN `assets/` direktoriju.

Readme datoteke imaju najviše pet tagova, Stable tag odgovara verziji plugina, a distribucija koristi GPLv2 ili noviju licencu.

## Dokumentacija

- WordPress: `docs/UPUTE-WORDPRESS.md`
- WooCommerce: `docs/UPUTE-WOOCOMMERCE.md`
- pravne i tehničke napomene: `docs/legal-and-technical-notes.md`
- PDF podrška nastaje pri buildu i ulazi u oba ZIP paketa
- stvarne slike administracije: `docs/media/`

## Build

```bash
./tools/build-editions.sh 0.8.1 /tmp/sidrena-build
```

Build stvara samo:

- `sidrena-wordpress-0.8.1.zip`
- `sidrena-woocommerce-0.8.1.zip`

## Privatnost

Sidrena nema telemetriju. Vanjske poveznice za službenu stranicu, podršku, WhatsApp i dobrovoljnu donaciju otvaraju se samo nakon korisničke akcije. Provjera javne dostupnosti koristi WordPress HTTP API prema javnim Sidrena datotekama na istoj web stranici.

## Licenca

Sidrena se distribuira pod licencom **GPLv2 ili novijom**, u skladu sa zahtjevima WordPress.org Plugin Directoryja. Pogledajte [LICENSE](LICENSE).

## Napomena o propisima

Sidrena tehnički pomaže voditi i objaviti podatke o cijenama prema pravilima koja projekt prati. Plugin nije automatska pravna potvrda konkretnog poslovanja. Povijesne i referentne cijene moraju odgovarati stvarnoj poslovnoj evidenciji, a korisnik mora provjeriti primjenjivost obveza na svoj poslovni model.

## Brendigo

Službena stranica: https://brendigo.com/sidrene-cijene/  
Podrška: sidrena@brendigo.com
