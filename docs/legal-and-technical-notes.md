<!--
Sidrena source file.
Author: Brendigo
Author URI: https://brendigo.com/
Plugin URI: https://brendigo.com/sidrene-cijene/
Support: sidrena@brendigo.com
-->

# Sidrena — pravne i tehničke bilješke

Sidrena je tehnički WordPress/WooCommerce alat za evidenciju, prikaz i objavu cijena. Ne zamjenjuje pravni savjet i ne smije izmišljati povijesne vrijednosti koje ne postoje u provjerljivom izvoru.

## Granica tehničke provjere

Sidrena može provjeriti strukturu, dostupnost i konzistentnost podataka koje ima na raspolaganju, ali ne može sama potvrditi da je konkretni poslovni subjekt u svakom trenutku potpuno pravno usklađen. To ovisi o stvarnom stanju zalihe, stvarnim cijenama, točnoj klasifikaciji proizvoda/usluga, drugim propisima i poslovnoj evidenciji korisnika.

Zbog toga oznake u administraciji predstavljaju **tehničku spremnost/pokrivenost**, a ne pravni certifikat.

## Službeni izvori

- **NN 101/2026, 1212** — Odluka o isticanju dodatne cijene; primjena od **1.10.2026.**
- **NN 101/2026, 1213** — Odluka o objavi cjenika proizvoda i usluga; primjena od **1.10.2026.**
- **Ministarstvo gospodarstva, 22.09.2026.** — službena pojašnjenja za dodatnu cijenu i objavu cjenika.
- **NN 59/2026, 728** — relevantne izmjene Zakona o zaštiti potrošača, uključujući najnižu cijenu u prethodnih 30 dana kod posebnih oblika prodaje.
- **NN 105/2026, 1270** — Pravilnik o načinu isticanja maloprodajne cijene i cijene za jedinicu mjere proizvoda; objavljen 18.09.2026., stupa na snagu **26.09.2026.**

Službeni URL-ovi nalaze se i u Sidrena → Propisi.

## Primarni i pomoćni izvori

Sidrena razlikuje izvore po ulozi:

- **Narodne novine i Ministarstvo gospodarstva** koriste se kao primarni izvori za rokove, obvezna polja, referentne datume i način objave.
- **Hrvatska obrtnička komora (HOK)** koristi se kao praktično pojašnjenje za obrtnike, ali ne zamjenjuje tekst propisa.
- **Državni inspektorat** koristi se za opća pravila transparentnosti i predugovorne informacije kod internetske prodaje.
- Implementacije drugih WordPress plugina i javni cjenici drugih trgovaca koriste se samo kao UX/tehnička inspiracija; njihov format nije pravni standard.

## Dodatna / sidrena cijena

Za novobuhvaćene proizvode i usluge Odluka koristi referentni datum **10.09.2026.** Za ranije obuhvaćene FMCG kategorije ostaje **02.05.2025.** Dodatna cijena prikazuje se uz aktualnu cijenu.

Službeno pojašnjenje Ministarstva dodatno obrađuje proizvode/usluge prvi put uvedene nakon referentnog datuma, promjene naziva ili šifre, proizvode bez zalihe te odnos aktualne cijene, najniže cijene prije sniženja i dodatne cijene.

## Digitalni cjenici

Za obveznike s mrežnom stranicom NN 101/2026, 1213 propisuje javne strojno obradive CSV/XML cjenike. Trgovac ažurira cjenik proizvoda radnim danom najkasnije do 08:00, a pružatelj usluge kod promjene cijene najkasnije do 08:00 na dan stupanja promjene na snagu.

Prethodne objave moraju ostati javno dostupne najmanje 30 dana. Tehničko rješenje mora omogućiti automatizirano prikupljanje podataka o aktualnim maloprodajnim cijenama.

Odluka ne propisuje točan redoslijed CSV stupaca, naziv XML elemenata, razdjelnik ni XSD shemu. Sidrena zato ne tvrdi da postoji jedinstveni službeni CSV/XML predložak, nego čuva obvezni skup podataka i stabilnu vlastitu strukturu.

Sidrena zato:
- generira zasebnu datoteku po aktivnoj lokaciji i zaseban webshop objekt,
- koristi istu strukturu za lokacije istog kataloga,
- u naziv datoteke uključuje vrstu objekta, adresu, oznaku, redni broj pohrane i vremensku oznaku,
- čuva svaku uspješnu objavu kao zasebnu datoteku,
- objavljuje REST dohvat aktualnih cijena,
- vodi SHA-256 zapis arhive.

## Proizvodi

Digitalni cjenik proizvoda podržava naziv, stabilnu šifru, marku, jedinicu mjere i cijenu za jedinicu mjere kada je primjenjivo, maloprodajnu cijenu, podatak o posebnom obliku prodaje i njegov naziv, sidrenu cijenu, barkod i dostupnost po lokaciji.

Sidrena 1.0.1 koristi eksplicitnu oznaku primjenjivosti jedinične cijene. Administrator mora provjeriti je li proizvod obuhvaćen pravilom ili iznimkom; plugin to ne zaključuje automatski iz kategorije proizvoda.

NN 105/2026 navodi skupine robe za koje se ističe cijena za jedinicu mjere i posebne iznimke. Kada administrator označi da je jedinična cijena obvezna, Sidrena upozorava ako nedostaje jedinica ili iznos.

## Usluge

NN 105/2026 zahtijeva lako dostupan cjenik usluga, jasan prikaz cijena te naziv, vrstu i opseg usluge. Cijena usluge obuhvaća pripadajuće troškove. Kada je ugradbena ili zamjenska roba sastavni dio usluge, njezina cijena mora biti istaknuta uz pripadajuću uslugu.

Sidrena zato vodi naziv, vrstu i opseg usluge, aktualnu i sidrenu cijenu, posebni oblik prodaje, pripadajuće troškove / napomenu o cijeni, ugradbenu ili zamjensku robu i njezinu cijenu kada je primjenjivo te lokalne cijene po poslovnici/webshopu.

## Dvije različite evidencije “30 dana”

1. **Javna arhiva CSV/XML** — prethodne uspješne objave javno se čuvaju najmanje 30 dana. Zadana Sidrena postavka je 45 dana.
2. **Interna povijest cijena** — služi kao tehnička podloga za 30-dnevnu referencu kod sniženja.

To nisu ista evidencija i Sidrena ih ne spaja.

## Povijesne cijene

Plugin ne može pouzdano rekonstruirati vrijeme prije instalacije. Ako nema poznatu cijenu na početku relevantnog prozora, referenca se označava kao nepotpuna. Ručni unos ili uvoz dopušten je samo za vrijednost provjerenu iz vjerodostojne poslovne evidencije.

## Lokacije i webshop

Ministarstvo je pojasnilo da se kod više fizičkih lokacija objavljuju zasebne datoteke po lokaciji, a webshop se vodi zasebno. Raspoloživost proizvoda odnosi se na konkretnu lokaciju i mora odgovarati stvarnom stanju.

## Automatizacija

Zadano vrijeme Sidrene je 06:30 prema WordPress vremenskoj zoni. WP-Cron ovisi o prometu stranice i ne jamči izvršavanje u točno određenoj minuti. Za poslovno kritične rokove preporučuje se pouzdani server cron koji pokreće WordPress cron ili WP-CLI naredba `wp sidrena generate`.

## Produkcijski i release guardovi

Sidrena 1.0.1 stabilna release linija koristi zasebne provjere za:

- stvarni build dvaju ZIP paketa,
- zabranu generičkog/root/static PHP paketa,
- referentne datume i automation/legal obveze,
- kratak WordPress admin menu s lokalnom sidro ikonicom i bez dupliciranih tehničkih stavki,
- zabranu privremenih workflowova u release grani.

Službeni release smije nastati samo kroz GitHub release workflow i objavljuje dva instalacijska ZIP-a te njihove `.sha256` provjere: `sidrena-wordpress-1.0.1.zip`, `sidrena-wordpress-1.0.1.zip.sha256`, `sidrena-woocommerce-1.0.1.zip` i `sidrena-woocommerce-1.0.1.zip.sha256`.

## Podaci obrta / tvrtke na mrežnoj stranici

Podaci kao što su naziv i sjedište, kontaktni e-mail i telefon, podaci javnog registra, PDV identifikacija kada je primjenjiva te nadležno tijelo proizlaze iz širih pravila elektroničke trgovine i zaštite potrošača. To **nisu dodatni obvezni stupci NN 101/2026 CSV/XML cjenika**.

Sidrena ih zato vodi zasebno u Postavkama i, po izboru administratora, prikazuje iznad javne stranice **Objava cjenika**. OIB se tehnički provjerava kontrolnom znamenkom, ali administrator i dalje odgovara za točnost poslovnih podataka.

## Podrška, distribucija i donacija

Podrška je dostupna na sidrena@brendigo.com i putem WhatsApp broja +385 91 901 0092.

Plugin korisnik može instalirati, postaviti i održavati sam. Brendigo usluge naručuju se samo po želji korisnika:
- jednokratna instalacija i početno postavljanje: **80 EUR**

Dobrovoljna donacija za razvoj otvara se izravno preko Revolut gumba u Sidrena administraciji. Donacija nije naknada za instalaciju ili održavanje i ne predstavlja narudžbu usluge.

Sidrena se distribuira pod licencom GPLv2 ili novijom. Kod, dokumentacija i originalni projektni asseti uključeni u WordPress.org distribuciju moraju ostati GPL-kompatibilni.
