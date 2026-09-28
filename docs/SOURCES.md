<!--
Sidrena source file.
Author: brendigo
Author URI: https://brendigo.com/
Plugin URI: https://brendigo.com/sidrene-cijene/
Support: sidrena@brendigo.com
-->

# SIDRENA — evidencija izvora

**Datum zadnje provjere:** 28. 9. 2026.  
**Pravilo projekta:** vanjski izvor može biti proučen bez preuzimanja koda. Kod ili asset smije u distribuciju samo ako je licenca i pravo redistribucije provjereno i dokumentirano.

## Službeni pravni izvori

| Izvor | Autor/organizacija | URL | Datum pristupa | Uloga u SIDRENA-i |
|---|---|---|---|---|
| Odluka o isticanju dodatne cijene kao mjera izravne kontrole cijena, NN 101/2026-1212 | Vlada RH / Narodne novine | https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html | 28. 9. 2026. | Referentni datum i obveza dodatne cijene od 1. 10. 2026. |
| Odluka o objavi cjenika proizvoda i usluga kao mjera izravne kontrole cijena, NN 101/2026-1213 | Vlada RH / Narodne novine | https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html | 28. 9. 2026. | CSV/XML, sadržaj, ritam objave, arhiva i strojna dostupnost od 1. 10. 2026. |
| Pojašnjenja za primjenu dodatne cijene i objavu cjenika od 1. listopada | Ministarstvo gospodarstva | https://mingo.gov.hr/vijesti/pojasnjenja-za-primjenu-dodatne-cijene-i-objavu-cjenika-od-1-listopada/10440 | 28. 9. 2026. | Službeno operativno pojašnjenje odluka NN 101/2026. |
| Pravilnik o načinu isticanja maloprodajne cijene i cijene za jedinicu mjere proizvoda, NN 105/2026-1270 | Ministarstvo gospodarstva / Narodne novine | https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_105_1270.html | 28. 9. 2026. | Jedinična cijena, iznimke i isticanje cijena usluga. |
| Zakon o izmjenama i dopunama Zakona o zaštiti potrošača, NN 59/2026-728 | Hrvatski sabor / Narodne novine | https://narodne-novine.nn.hr/clanci/sluzbeni/2026_06_59_728.html | 28. 9. 2026. | Posebni oblici prodaje, najniža cijena prethodnih 30 dana i budući režim bazne cijene. |
| Direktiva 98/6/EZ o označivanju cijena | Europska unija / EUR-Lex | https://eur-lex.europa.eu/eli/dir/1998/6/oj | 28. 9. 2026. | EU okvir za isticanje cijena. |
| Direktiva (EU) 2019/2161 | Europska unija / EUR-Lex | https://eur-lex.europa.eu/eli/dir/2019/2161/oj | 28. 9. 2026. | EU izmjene povezane s objavama sniženja cijena. |

Detaljna razrada pravnih posljedica nalazi se u `docs/LEGAL-SOURCES.md`.

## WordPress

| Izvor | Autor/organizacija | URL | Datum pristupa | Što je proučeno |
|---|---|---|---|---|
| Detailed Plugin Guidelines | WordPress.org | https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/ | 28. 9. 2026. | GPL kompatibilnost, trialware, tracking, vanjski kod, admin UX, trademark/copyright, verzioniranje. |
| Header Requirements | WordPress.org | https://developer.wordpress.org/plugins/plugin-basics/header-requirements/ | 28. 9. 2026. | Plugin header i `Requires Plugins` slug format. |
| Common Issues | WordPress.org | https://developer.wordpress.org/plugins/wordpress-org/common-issues/ | 28. 9. 2026. | Nepotrebne datoteke, source/build zahtjevi i distribucijski paket. |
| WordPress Plugin Handbook | WordPress.org | https://developer.wordpress.org/plugins/ | 28. 9. 2026. | Security, cron, REST/AJAX, internacionalizacija i WordPress.org distribucija. |
| Plugin Check | WordPress.org | https://wordpress.org/plugins/plugin-check/ | 28. 9. 2026. | Automatizirana WordPress.org provjera finalnog plugina. |
| WordPress 7.1.2 Release | WordPress.org | https://wordpress.org/news/2026/09/wordpress-7-1-2-release/ | 28. 9. 2026. | Aktualna sigurnosna verzija WordPressa; `Tested up to` ostaje major/minor 7.1. |

## WooCommerce

| Izvor | Autor/organizacija | URL | Datum pristupa | Što je proučeno |
|---|---|---|---|---|
| How to design a simple extension | WooCommerce | https://developer.woocommerce.com/docs/extensions/getting-started-extensions/how-to-design-a-simple-extension | 28. 9. 2026. | `Developer`, `Developer URI`, `Requires Plugins: woocommerce`, odgođeni bootstrap i dependency guard; `Woo:` se ne dodaje za distribuciju izvan Marketplacea. |
| Example WordPress plugin header comment for WooCommerce extensions | WooCommerce | https://developer.woocommerce.com/docs/extensions/core-concepts/example-header-plugin-comment | 28. 9. 2026. | `WC requires at least` i `WC tested up to`. |
| WooCommerce releases | WooCommerce | https://developer.woocommerce.com/releases/ | 28. 9. 2026. | Potvrđeno da je 11.1.2 aktualno stabilno izdanje na datum provjere. |

## Istraženi open-source pluginovi

Ovi projekti služe **samo za usporednu analizu problema i UX-a**. U SIDRENA kod nije kopirana nijedna njihova funkcija, klasa, tekst, slika, ikona ili drugi asset.

| Plugin | Izvor | Točan commit | Licenca | Aktivnost / relevantna značajka | Korisna ideja | Primjena u SIDRENA-i |
|---|---|---|---|---|---|---|
| WooCommerce Cjenik HR | https://github.com/nikolapevic/woocommerce-cjenik-hr | `a22979473ef17357e876de0c92448e22a94df038` | GPL-2.0-or-later (repo LICENSE/README) | Hrvatski CSV cjenik, arhiva, 30-dnevna povijest | Jasno upozorenje da se povijest prije instalacije ne može rekonstruirati | Samo referenca; SIDRENA zadržava vlastiti model povijesti, validaciju i objavu. |
| WC Price History | https://github.com/kkarpieszuk/wc-price-history | `71d14dc866c388a7ecc33eca2e02cc7fa4e5471d` | MIT | Povijest Woo cijena, varijacije, testovi, PHPStan | Carry-forward cijene na početku 30-dnevnog prozora i važnost variation-specific prikaza | Problem je uspoređen sa SIDRENA implementacijom; SIDRENA već ima vlastiti baseline/carry-forward algoritam i zadržava ga. |
| Omnibus — show the lowest price | https://github.com/iworks/omnibus | `9417dd18dee4546dbcbd94d80f9944d8be11e8b0` | GPLv3 or later (readme.txt) | 30-dnevna cijena, Woo hooks, shortcode | Jednostavno odvajanje capture i display sloja | Samo arhitektonska usporedba; kod nije uključen. |

Dodatno su pregledane aktualne WordPress.org kategorije `lowest-price`, `price-history` i `omnibus` radi usporedbe dostupnih besplatnih rješenja. To nije pravni autoritet i iz tih plugina nije preuzet kod.

## Biblioteke i distribuirani third-party materijal

Audit izvornog stabla za 1.0.26 nije identificirao vendoriziranu third-party PHP/JS/CSS biblioteku koja se distribuira kao dio SIDRENA runtime ZIP-a. Runtime se oslanja na WordPress/WooCommerce API-je instalacije korisnika i na vlastite lokalne SIDRENA assete.

Zbog toga `THIRD-PARTY-NOTICES.md` nije dodan samo reda radi. Ako se u budućnosti uključi third-party kod ili asset, taj se dokument mora izraditi prije releasea i navesti autor, upstream, verziju/commit, licencu, copyright, lokalne datoteke i modifikacije.

## Branding i screenshotovi

SIDRENA logotipi, UI grafike i WordPress.org asseti u ovom repozitoriju tretiraju se kao projektni Brendigo/SIDRENA materijal. Tuđi WordPress/WooCommerce logo nije dio SIDRENA vizualnog identiteta. Screenshotovi za WordPress.org moraju nastati iz stvarnog instaliranog finalnog/RC paketa i koristiti vlastite ili generičke demo podatke.
