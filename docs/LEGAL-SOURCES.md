<!--
Sidrena source file.
Author: brendigo
Author URI: https://brendigo.com/
Plugin URI: https://brendigo.com/sidrene-cijene/
Support: sidrena@brendigo.com
-->

# SIDRENA — službeni pravni izvori i tehničke posljedice

**Provjereno:** 28. 9. 2026.  
**Jurisdikcija:** Republika Hrvatska  
**Status dokumenta:** tehnička dokumentacija projekta; nije pravni savjet niti jamstvo usklađenosti.

Ovaj dokument namjerno odvaja tri razine:

1. **zakonska / regulatorna obveza** — ono što proizlazi iz službenog izvora;
2. **tehnička preporuka** — način na koji se obveza može pouzdano podržati u WordPressu;
3. **SIDRENA implementacijska odluka** — konkretno ponašanje plugina.

## 1. Dodatna (sidrena) cijena od 1. listopada 2026.

### Službeni izvori

- Odluka o isticanju dodatne cijene kao mjera izravne kontrole cijena, **NN 101/2026-1212**, osobito točke II.–VI.:  
  https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html
- Ministarstvo gospodarstva, **„Pojašnjenja za primjenu dodatne cijene i objavu cjenika od 1. listopada“**, objavljeno 22. 9. 2026.:  
  https://mingo.gov.hr/vijesti/pojasnjenja-za-primjenu-dodatne-cijene-i-objavu-cjenika-od-1-listopada/10440

### Zakonska / regulatorna obveza

Odluka stupa na snagu **1. 10. 2026.** Primjena dodatne cijene proširena je na proizvode i usluge obuhvaćene Odlukom. Službeno pojašnjenje Ministarstva razlikuje dvije referentne skupine:

- za proizvode i usluge koji ranije nisu bili obuhvaćeni mjerom referentni je datum **10. 9. 2026.**;
- za ranije obuhvaćene proizvode široke potrošnje iz prethodne mjere ostaje datum **2. 5. 2025.** i ne uvodi se druga dodatna cijena.

Dodatna cijena prikazuje se uz važeću cijenu na istom mjestu, jasno i vidljivo.

### Tehnička preporuka

Sustav mora čuvati referentni datum zajedno s vrijednošću i izvorom podatka. Ne smije automatski rekonstruirati povijesnu cijenu ako nema vjerodostojan podatak.

### SIDRENA odluka

SIDRENA čuva dodatnu cijenu kao zaseban podatak i ne izjednačava je s 30-dnevnom najnižom cijenom. Automatizacija smije predložiti ili preuzeti vrijednost samo iz podataka kojima plugin stvarno raspolaže; inače stanje ostaje nepotpuno i traži korisničku provjeru.

## 2. Javni digitalni cjenici od 1. listopada 2026.

### Službeni izvor

Odluka o objavi cjenika proizvoda i usluga kao mjera izravne kontrole cijena, **NN 101/2026-1213**, osobito točke I.–IX.:  
https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html

### Zakonska / regulatorna obveza

Za obveznike s uspostavljenom mrežnom stranicom Odluka propisuje javnu objavu važećih cjenika. Ključne tehničke posljedice iz Odluke i službenog pojašnjenja su:

- datoteka je u **CSV ili XML** formatu pogodnom za automatsku obradu;
- trgovac cjenik proizvoda ažurira radnim danom najkasnije do **08:00**;
- pružatelj usluga ažurira cjenik kod promjene cijene, najkasnije do **08:00 na dan stupanja promjene na snagu**;
- prethodno objavljeni cjenici ostaju javno dostupni najmanje **30 dana**;
- naziv datoteke uključuje podatke o objektu, oznaci/broju pohrane te vremensku oznaku;
- struktura datoteke mora biti dosljedna;
- mrežna stranica mora omogućiti automatizirano dohvaćanje podataka o cijenama;
- Odluka zasebno propisuje polja za proizvode i za usluge.

### Tehnička preporuka

Generiranje treba biti atomsko: novi export prvo se generira i validira u privremenoj datoteci, a tek nakon uspjeha zamjenjuje javnu verziju. Neuspjeli build ne smije ukloniti zadnju valjanu javnu datoteku. Cron treba imati lock, last-success, last-error i recovery mehanizam.

### SIDRENA odluka

SIDRENA koristi validaciju → snapshot → export → privremenu datoteku → validaciju → atomic publish → arhivu → manifest. CSV i XML ostaju javno dostupni bez potrebe za prijavom. Arhiviranje je najmanje 30 dana; korisnik može zadržati dulje razdoblje.

## 3. Posebni oblici prodaje i 30-dnevna najniža cijena

### Službeni izvor

Zakon o izmjenama i dopunama Zakona o zaštiti potrošača, **NN 59/2026-728**, osobito članci 10.–14. kojima se mijenjaju pravila posebnih oblika prodaje:  
https://narodne-novine.nn.hr/clanci/sluzbeni/2026_06_59_728.html

### Zakonska / regulatorna obveza

Kod relevantnog posebnog oblika prodaje referenca je najniža cijena koju je trgovac primjenjivao za isti proizvod tijekom **30 dana prije provođenja posebnog oblika prodaje**. Zakon uređuje i iznimke / posebno postupanje za lako pokvarljivu robu i robu kojoj brzo istječe rok uporabe te zasebno pravila za usluge.

### Tehnička preporuka

Algoritam mora računati cijenu koja je stvarno bila **na snazi** u prozoru, a ne samo minimum zapisa nastalih unutar prozora. Zato je potreban posljednji poznati zapis na ili prije početka prozora (carry-forward baseline). Ako takav dokaz ne postoji, rezultat treba biti označen kao nepotpun, a ne izmišljen.

### SIDRENA odluka

WooCommerce izdanje vodi vlastitu povijest cijena i pri početku sniženja zamrzava referencu. Izračun koristi baseline na početku prozora i sve promjene do početka sniženja. Ako nema dovoljne pokrivenosti, automatska referenca ostaje nepotpuna; postoji eksplicitni ručni unos za vjerodostojno provjerenu vrijednost. Varijacije se prate zasebno.

## 4. Jedinična cijena i cijene usluga

### Službeni izvor

Pravilnik o načinu isticanja maloprodajne cijene i cijene za jedinicu mjere proizvoda, **NN 105/2026-1270**, osobito članci 3.–11.:  
https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_105_1270.html

Pravilnik je objavljen 18. 9. 2026. i prema članku 13. stupa na snagu osmoga dana od objave.

### Zakonska / regulatorna obveza

Pravilnik uređuje jasno i vidljivo isticanje cijena, kategorije za koje se ističe cijena za jedinicu mjere i propisane iznimke. Za usluge uređuje dostupnost cjenika i obvezne informacije o nazivu, vrsti, opsegu i pripadajućim troškovima.

### Tehnička preporuka

Automatski izračun jedinične cijene treba se raditi samo kad su poznati valjani maloprodajna cijena, količina i mjerna jedinica. Pravnu primjenjivost kategorije ne treba nagađati.

### SIDRENA odluka

SIDRENA podržava status primjenjivosti jedinične cijene i izračun iz validirane količine/mjerne jedinice. Ako podaci nisu dovoljni, polje ostaje za pregled umjesto da plugin generira lažnu vrijednost.

## 5. Budući režim bazne cijene — 17. studenoga 2026.

### Službeni izvor

Zakon o izmjenama i dopunama Zakona o zaštiti potrošača, **NN 59/2026-728**, članak 4. (novi članak 7.) i završna odredba o stupanju na snagu:  
https://narodne-novine.nn.hr/clanci/sluzbeni/2026_06_59_728.html

### Zakonska / regulatorna obveza

Izmijenjeni članak 7. definira **baznu cijenu** kao maloprodajnu cijenu primjenjivu na točno određen dan u prethodnom razdoblju i predviđa pravilnik kojim se propisuje za koji dan, za koje proizvode i kako se bazna cijena ističe. Prema završnoj odredbi, članak 7. stavci 1.–9. u tom izmijenjenom obliku stupaju na snagu **17. 11. 2026.**

### SIDRENA odluka

Na datum ovog audita SIDRENA **ne proglašava** postojeću „dodatnu/sidrenu cijenu“ automatski budućom „baznom cijenom“. Budući pravilnik i službene upute moraju se provjeriti prije eventualne migracije naziva ili semantike. Ovo je namjerni guard protiv preuranjene pravne automatizacije.

## 6. Što SIDRENA ne jamči

SIDRENA je tehnički alat. Dokumentacija i UI ne smiju tvrditi da je plugin „100% zakonski usklađen“, „certificiran“, „službeno odobren“ ili da „jamči prolazak inspekcije“. Korisnik ostaje odgovoran za točnost poslovnih podataka, klasifikaciju proizvoda/usluga, primjenjivost iznimaka i pravnu procjenu vlastitog poslovanja.
