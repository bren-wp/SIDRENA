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
- **Ministarstvo gospodarstva, 22.09.2026.** — službena pojašnjenja za dodatnu cijenu i objavu cjenika od 1. listopada.
- **NN 59/2026, 728** — izmjene Zakona o zaštiti potrošača: najniža cijena u prethodnih 30 dana kod posebnih oblika prodaje te budući sloj bazne cijene; izmijenjeni članak 7. stavci 1.–9. počinju se primjenjivati **17.11.2026.**.
- **NN 105/2026, 1270** — Pravilnik o načinu isticanja maloprodajne cijene i cijene za jedinicu mjere proizvoda; objavljen 18.09.2026., stupa na snagu **26.09.2026.**

Službeni URL-ovi nalaze se i u Sidrena → Propisi.

## Primarni i pomoćni izvori

Sidrena razlikuje izvore po ulozi:

- **Narodne novine i Ministarstvo gospodarstva** koriste se kao primarni izvori za rokove, obvezna polja, referentne datume i način objave.
- **Hrvatska obrtnička komora (HOK)** koristi se kao praktično pojašnjenje za obrtnike, ali ne zamjenjuje tekst propisa.
- **Državni inspektorat** koristi se za opća pravila transparentnosti i predugovorne informacije kod internetske prodaje.
- Implementacije drugih WordPress plugina i javni cjenici drugih trgovaca koriste se samo kao UX/tehnička inspiracija; njihov format nije pravni standard.

## Tri odvojena cjenovna pojma

Sidrena ih namjerno ne spaja:

1. **Dodatna / sidrena cijena** — sloj iz NN 101/2026 s referentnim datumom 10.09.2026. odnosno 02.05.2025. za ranije obuhvaćene FMCG kategorije.
2. **Najniža cijena u prethodnih 30 dana** — referentna cijena za posebni oblik prodaje prema Zakonu o zaštiti potrošača; vodi se iz povijesti cijena ili provjerene ručne evidencije, uz propisane iznimke.
3. **Bazna cijena** — zaseban pojam iz izmijenjenog članka 7. Zakona. Relevantni stavci počinju se primjenjivati 17.11.2026., ali konkretan dan u prethodnom razdoblju, proizvodi i način isticanja ovise o provedbenom pravilniku. Dok taj provedbeni sloj nije određen i implementiran, Sidrena ga ne izjednačava sa sidrenom cijenom niti automatski popunjava vrijednost.

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

Sidrena koristi eksplicitnu oznaku primjenjivosti jedinične cijene. Administrator mora provjeriti je li proizvod obuhvaćen pravilom ili iznimkom; plugin to ne zaključuje automatski iz kategorije proizvoda.

NN 105/2026 navodi skupine robe za koje se ističe cijena za jedinicu mjere i posebne iznimke. Kada administrator označi da je jedinična cijena obvezna, Sidrena upozorava ako nedostaje jedinica ili iznos.

U praktičnom vodiču unutar **Sidrena → Pomoć → Propisi** navedene su skupine iz članka 8. Pravilnika: hrana i hrana za životinje, deterdženti, dječje pelene, određene boje i lakovi, ulja i tekućine za motorna vozila, destilirana voda te navedene skupine proizvoda za njegu i higijenu. Vodič navodi i iznimke, uključujući pakiranja ispod 50 g/50 ml, poklon-pakete/komplete, robu u posebnom obliku prodaje i druge iznimke iz članka 8.

Sidrena **ne klasificira automatski** proizvod u pravnu kategoriju na temelju naziva ili WooCommerce kategorije. Administrator označava je li jedinična cijena obvezna, nije primjenjiva ili postoji iznimka; plugin zatim provjerava potpunost potrebnih podataka.

## Usluge

NN 105/2026 čl. 9.–11. zahtijeva lako dostupan cjenik usluga, jasan prikaz cijena te naziv, vrstu i opseg usluge; cijena mora obuhvatiti pripadajuće troškove, a cijena ugradbene ili zamjenske robe mora biti istaknuta kada je roba sastavni dio usluge. Zasebno, NN 101/2026 za strojno čitljivi cjenik usluga propisuje naziv usluge, maloprodajnu cijenu s informacijom o posebnom obliku prodaje i sidrenu cijenu.

Sidrena zato vodi oba sloja podataka: obvezna polja digitalnog NN 101/2026 cjenika te detalje javnog prikaza iz NN 105/2026. First-publish finalizacija nove usluge odvija se nakon spremanja meta podataka kako se referentna cijena ne bi računala prije stvarno unesene aktualne cijene.

## Dvije različite evidencije “30 dana”

1. **Javna arhiva CSV/XML** — prethodne uspješne objave javno se čuvaju najmanje 30 dana. Zadana Sidrena postavka je 45 dana.
2. **Interna povijest cijena** — služi kao tehnička podloga za 30-dnevnu referencu kod sniženja.

To nisu ista evidencija i Sidrena ih ne spaja.

## Posebni oblik prodaje

Strogi publication preflight razlikuje proizvod i uslugu:

- proizvod na posebnoj prodaji mora imati provjerljivu najnižu cijenu u prethodnih 30 dana ili evidentiranu iznimku za lako pokvarljivu robu / robu kojoj brzo istječe rok; kod tih iznimaka Sidrena traži i krajnji rok uporabe
- usluga na posebnoj prodaji u fizičkoj poslovnici mora imati 30-dnevnu referencu
- iznimke za oglašavanje, ugovor na daljinu ili ugovor izvan poslovnih prostorija vode se odvojeno i ne koriste se za zaobilaženje reference fizičke poslovnice
- nepotpuna povijest ne pretvara se automatski u izmišljenu cijenu

## Povijesne cijene

Plugin ne može pouzdano rekonstruirati vrijeme prije instalacije. Ako nema poznatu cijenu na početku relevantnog prozora, referenca se označava kao nepotpuna. Ručni unos ili uvoz dopušten je samo za vrijednost provjerenu iz vjerodostojne poslovne evidencije.

## Lokacije i webshop

Ministarstvo je pojasnilo da se kod više fizičkih lokacija objavljuju zasebne datoteke po lokaciji, a webshop se vodi zasebno. Raspoloživost proizvoda odnosi se na konkretnu lokaciju i mora odgovarati stvarnom stanju.

WooCommerce izdanje zato za fizičku lokaciju traži eksplicitni lokacijski status raspoloživosti umjesto globalnog WooCommerce stock statusa. WordPress izdanje koristi zasebnu mapu dostupnosti po aktivnoj fizičkoj lokaciji; jedna opća vrijednost više se ne smatra dokazom stvarnog stanja svih poslovnica. Webshop može koristiti zadani kataloški status kada zasebni status kanala nije unesen.

## Automatizacija

Zadano vrijeme Sidrene je 06:30 prema WordPress vremenskoj zoni. WP-Cron ovisi o prometu stranice i ne jamči izvršavanje u točno određenoj minuti. Za poslovno kritične rokove preporučuje se pouzdani server cron koji pokreće WordPress cron ili WP-CLI naredba `wp sidrena generate`.

## Produkcijski i release guardovi

Aktualna stabilna release linija koristi zasebne provjere za:

- admin asset routing i obveznu prisutnost produkcijskog CSS/JS + logo runtime sloja u instalacijskom ZIP-u

- cross-version nadogradnju i idempotentni repair postojećih instalacija
- instalacijski ZIP do 1,5 MiB bez nepotrebnih screenshot/marketing asseta

- stvarni build dvaju ZIP paketa,
- zabranu generičkog/root/static PHP paketa,
- referentne datume i automation/legal obveze,
- kratak WordPress admin menu s lokalnom sidro ikonicom i bez dupliciranih tehničkih stavki,
- zabranu privremenih workflowova u release grani.

Službeni release smije nastati samo kroz GitHub release workflow i objavljuje dva instalacijska ZIP-a te njihove `.sha256` provjere: `sidrena-wordpress-1.0.20.zip`, `sidrena-wordpress-1.0.20.zip.sha256`, `sidrena-woocommerce-1.0.20.zip` i `sidrena-woocommerce-1.0.20.zip.sha256`.

## Podaci obrta / tvrtke na mrežnoj stranici

Podaci kao što su naziv i sjedište, kontaktni e-mail i telefon, podaci javnog registra, PDV identifikacija kada je primjenjiva te nadležno tijelo proizlaze iz širih pravila elektroničke trgovine i zaštite potrošača. To **nisu dodatni obvezni stupci NN 101/2026 CSV/XML cjenika**.

Sidrena ih zato vodi zasebno u Postavkama i, po izboru administratora, prikazuje iznad javne stranice **Objava cjenika**. OIB se tehnički provjerava kontrolnom znamenkom, ali administrator i dalje odgovara za točnost poslovnih podataka.

## Podrška, distribucija i donacija

Podrška je dostupna na sidrena@brendigo.com i putem WhatsApp broja +385 91 901 0092.

Plugin korisnik može instalirati, postaviti i održavati sam. Brendigo usluge naručuju se samo po želji korisnika:
- jednokratna instalacija i početno postavljanje: **80 EUR**

Dobrovoljna donacija za razvoj otvara se izravno preko Revolut gumba u Sidrena administraciji. Donacija nije naknada za instalaciju ili održavanje i ne predstavlja narudžbu usluge.

Sidrena se distribuira pod licencom GPLv2 ili novijom. Kod, dokumentacija i originalni projektni asseti uključeni u WordPress.org distribuciju moraju ostati GPL-kompatibilni.
