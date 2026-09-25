<!--
Sidrena source file.
Author: Brendigo
Author URI: https://brendigo.com/
Plugin URI: https://brendigo.com/sidrene-cijene/
Support: sidrena@brendigo.com
-->

<p align="center">
  <img src="assets/images/logo-horizontal.svg" alt="Sidrena — usklađene cijene, sigurno poslovanje" width="560">
</p>

<h1 align="center">Sidrena 0.9.0</h1>

<p align="center">
  <strong>Dva produkcijska WordPress dodatka za upravljanje cijenama, javnim cjenicima, lokacijama, poviješću i arhivom objava.</strong><br>
  WordPress izdanje radi s postojećim WordPress sadržajem i vlastitim katalogom, a WooCommerce izdanje nadograđuje postojeće WooCommerce proizvode i varijacije — bez dupliciranja kataloga.
</p>

<p align="center">
  <a href="https://brendigo.com/sidrene-cijene/"><strong>Službena stranica</strong></a>
  ·
  <a href="mailto:sidrena@brendigo.com"><strong>Podrška</strong></a>
</p>

---

## Dva izdanja. Jedan Sidrena sustav.

<table>
<tr>
<td width="50%" valign="top">
<p align="center"><img src="assets/images/logo-wordpress.svg" alt="Sidrena WordPress" width="390"></p>
<p><strong>Sidrena WordPress</strong> namijenjena je web stranicama koje žele upravljati cijenama kroz vlastiti katalog ili postojeći javni WordPress sadržaj.</p>
<ul>
<li>proizvodi i usluge</li>
<li>povezivanje s postojećim WordPress sadržajem</li>
<li>javni HTML cjenik</li>
<li>CSV/XML izvoz</li>
<li>lokacije i arhiva objava</li>
<li>povijest i referentni podaci o cijenama</li>
</ul>
</td>
<td width="50%" valign="top">
<p align="center"><img src="assets/images/logo-woocommerce.svg" alt="Sidrena WooCommerce" width="390"></p>
<p><strong>Sidrena WooCommerce</strong> radi izravno s postojećim WooCommerce proizvodima i varijacijama.</p>
<ul>
<li>bez zasebnog duplog kataloga</li>
<li>proizvodi i varijacije</li>
<li>povijest cijena</li>
<li>lokacije / webshop kanali</li>
<li>javni cjenici i arhiva</li>
<li>automatska sinkronizacija podataka</li>
</ul>
</td>
</tr>
</table>

> Istodobno smije biti aktivno samo jedno Sidrena izdanje. Ugrađeni edition-conflict guard sprječava dvostruke hookove, duplicirane procese i paralelnu objavu iz oba izdanja.

## Stvarni izgled plugina

Slike ispod nisu dizajnerski mockupovi. Automatski se snimaju iz stvarnog aktivnog WordPress administratorskog sučelja tijekom CI procesa.

### Sidrena WordPress

<table>
<tr>
<td width="50%"><img src="wporg-assets/sidrena-wordpress/assets/screenshot-1.png" alt="Sidrena WordPress — nadzorna ploča"></td>
<td width="50%"><img src="wporg-assets/sidrena-wordpress/assets/screenshot-2.png" alt="Sidrena WordPress — katalog"></td>
</tr>
<tr>
<td width="50%"><img src="wporg-assets/sidrena-wordpress/assets/screenshot-3.png" alt="Sidrena WordPress — cjenici i objava"></td>
<td width="50%"><img src="wporg-assets/sidrena-wordpress/assets/screenshot-4.png" alt="Sidrena WordPress — postavke"></td>
</tr>
</table>

### Sidrena WooCommerce

<table>
<tr>
<td width="50%"><img src="wporg-assets/sidrena-woocommerce/assets/screenshot-1.png" alt="Sidrena WooCommerce — nadzorna ploča"></td>
<td width="50%"><img src="wporg-assets/sidrena-woocommerce/assets/screenshot-2.png" alt="Sidrena WooCommerce — proizvodi"></td>
</tr>
<tr>
<td width="50%"><img src="wporg-assets/sidrena-woocommerce/assets/screenshot-3.png" alt="Sidrena WooCommerce — cjenici i objava"></td>
<td width="50%"><img src="wporg-assets/sidrena-woocommerce/assets/screenshot-4.png" alt="Sidrena WooCommerce — lokacije"></td>
</tr>
</table>

## Stvarne ikone i identitet

<table>
<tr>
<td align="center"><img src="wporg-assets/sidrena-wordpress/assets/icon-128x128.png" alt="Sidrena WordPress ikona" width="96"><br><strong>WordPress</strong></td>
<td align="center"><img src="wporg-assets/sidrena-woocommerce/assets/icon-128x128.png" alt="Sidrena WooCommerce ikona" width="96"><br><strong>WooCommerce</strong></td>
<td align="center"><img src="assets/images/menu-anchor.svg" alt="Sidrena WordPress menu ikona" width="48"><br><strong>WP admin sidro</strong></td>
<td align="center"><img src="assets/images/app-icon.svg" alt="Sidrena app ikona" width="96"><br><strong>App / WP.org ikona</strong></td>
</tr>
</table>

Kompletan vizualni sustav nalazi se u `branding/`, dok su svi runtime logotipi i ikone lokalno u `assets/images/`. Plugin ne ovisi o vanjskom CDN-u za svoj administratorski identitet.

## Što donosi 0.9.0

**0.9.0** je veliko produkcijsko izdanje usmjereno na potpuno novi Sidrena UI/UX i jedinstven branding oba dodatka:

- novi navy/cyan Sidrena administratorski sustav izrađen od nule
- kompaktna S + sidro ikona u WordPress bočnom meniju
- zasebni WordPress plavi i WooCommerce ljubičasti edition akcent
- responzivne nadzorne ploče, tablice, statusne kartice i obrasci
- redizajnirani katalog, cjenici, lokacije, arhiva, postavke i pomoć
- lokalni logo, app icon, favicon, hero i edition-specific asseti
- stvarni WordPress.org screenshotovi snimljeni iz aktivnog wp-admin okruženja
- CI provjera desktop i mobilnog overflowa prije objave screenshotova
- WordPress.org ikone i banneri pripremljeni zasebno za oba izdanja

## Glavne mogućnosti

- aktualna, referentna i povijesna cijena
- najniža cijena u prethodnom razdoblju kada je primjenjiva i dostupna u evidenciji
- jedinična cijena kada je primjenjiva
- proizvodi, usluge, WooCommerce varijacije i lokacije
- javni HTML cjenik
- CSV/XML izvoz
- arhiva prethodnih objava
- automatizirana dnevna objava i provjera propuštenog rasporeda
- streaming obrada većih kataloga
- lokalni audit zapis
- nonce/capability zaštita administrativnih akcija
- bez ugrađene telemetrije i praćenja korisnika

## WordPress administracija

Glavni Sidrena meni je namjerno kratak i pregledan:

- **Pregled**
- **Katalog** / **Proizvodi**
- **Usluge**
- **Cjenici**
- **Lokacije**
- **Postavke**
- **Pomoć**

Sekundarne funkcije poput arhive, tehničke provjere, dijagnostike, dnevnika i dokumentacije otvaraju se unutar postojećih radnih stranica umjesto dupliciranja stavki u WordPress bočnom meniju.

## Javni cjenik i ugradnja

Sidrena može objaviti javni cjenik i prikazati ga na WordPress stranici. Dostupan je i shortcode:

`[sidrena_objava_cjenika]`

To omogućuje ugradnju Sidrena javnog prikaza cijena u postojeći sadržaj bez dupliciranja podataka.

## WordPress.org asseti

Za oba izdanja repozitorij održava zaseban WordPress.org set:

- `wporg-assets/sidrena-wordpress/assets/`
- `wporg-assets/sidrena-woocommerce/assets/`

Svaki set uključuje stvarne runtime screenshotove, ikone 128×128 i 256×256 te bannere. Screenshot pipeline podiže pravi WordPress, a za WooCommerce izdanje i pravi WooCommerce, aktivira Sidrena plugin i tek tada snima sučelje.

## Dokumentacija i branding

- WordPress upute: `docs/UPUTE-WORDPRESS.md`
- WooCommerce upute: `docs/UPUTE-WOOCOMMERCE.md`
- brand vodič: `branding/BRAND-GUIDE.md`
- runtime logotipi i ikone: `assets/images/`
- stvarne slike administracije: `wporg-assets/*/assets/screenshot-*.png`
- tehničke i pravne napomene: `docs/legal-and-technical-notes.md`
- PDF podrška nastaje pri buildu i ulazi u oba instalacijska paketa

## Build

```bash
./tools/build-editions.sh 0.9.0 /tmp/sidrena-build
```

Build proizvodi dva službena instalacijska ZIP paketa:

- `sidrena-wordpress-0.9.0.zip`
- `sidrena-woocommerce-0.9.0.zip`

Uz ZIP-ove lokalno nastaju i SHA-256 kontrolne datoteke za provjeru reproduktivnog builda.

## Privatnost

Sidrena nema ugrađenu telemetriju. Vanjske poveznice prema službenoj stranici, podršci, WhatsAppu i dobrovoljnoj donaciji otvaraju se samo nakon korisničke akcije. Provjera vlastitih javnih datoteka koristi WordPress HTTP API.

## Napomena o propisima

Sidrena tehnički pomaže voditi, provjeravati i objavljivati podatke o cijenama prema pravilima koja projekt prati. Plugin nije automatska pravna potvrda konkretnog poslovanja. Povijesne i referentne cijene moraju odgovarati stvarnoj poslovnoj evidenciji, a korisnik mora provjeriti primjenjivost obveza na svoj poslovni model.

## Podrška

- Službena stranica: **https://brendigo.com/sidrene-cijene/**
- E-mail: **sidrena@brendigo.com**
- Telefon / WhatsApp: **+385 91 901 0092**
- Autor: **Brendigo**

## Licenca

Sidrena se distribuira pod licencom **GPLv2 ili novijom**. Pogledajte [LICENSE](LICENSE).
