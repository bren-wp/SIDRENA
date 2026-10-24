# SIDRENA — pravni okvir i interoperabilnost (24. 10. 2026.)

## Što je utvrđeno u službenim propisima

**Mjerodavni tekstovi**:
- NN 101/2026-1212: https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html
- NN 101/2026-1213: https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html
- NN 110/2026-1309: https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_110_1309.html
- NN 110/2026-1310: https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_110_1310.html
- Zakon o zaštiti potrošača — izmjene NN 59/2026: https://narodne-novine.nn.hr/clanci/sluzbeni/2026_06_59_728.html

Odluke NN 101/2026 izmijenjene su u NN 110/2026, tako da **17. studenoga 2026.** zamjenjuje prvotno planirani **1. listopada 2026.** za primjenu proširenih obveza. Referentni datumi nisu promijenjeni: **2. svibnja 2025.** za ranije obuhvaćene kategorije (hrana, piće, kozmetika, sredstva za čišćenje, toaletne potrepštine, proizvodi za kućanstvo) i **10. rujna 2026.** za ostalu robu i usluge.

Prema Odluci o objavi cjenika, trgovac s mrežnom stranicom objavljuje strojno čitljiv CSV/XML cjenik proizvoda jednom dnevno najkasnije do 8:00, a pružatelj usluga pri promjeni cijena, prema propisanom roku. Objavljeni cjenici moraju biti dostupni najmanje 30 dana. Datoteke moraju biti pogodne za automatizirano prikupljanje, s jedinstvenom strukturom po poslovnicama.

**Nacrti nisu propisi:** U listopadu 2026. objavljeni su prijedlozi dvaju pravilnika o baznoj cijeni i objavi cjenika, s javnim savjetovanjem do 13. studenoga 2026. Nisu automatski stupili na snagu. Nakon objave konačnih pravilnika potrebna je dodatna provjera izuzeća, trajnih pravila i mogućih prijelaznih odredbi. SIDRENA ne izdaje automatiziranu potvrdu pravne usklađenosti.

## Sinkronizirani javni profil

WordPress dodatak **1.5.1** dodaje informacijsko polje `legal` u postojeći javni `GET /wp-json/brendigo-sidrena/v1/cjenici` bez izmjena u starim `feeds`, `outlets` ili arhivskim rutama. Također vraća `plugin_version` i `api_version`. U `legal` se prenose pravna revizija, datumi, rokovi objave, trajanje arhive, formati, popisi traženih javnih polja i URL-ovi propisa.

Android **0.3.0** i Windows **0.10.3** čitaju taj isti profil, parsiraju ga s provjerama tipova i prikazuju izvorne referentne datume. Za stariji plugin 1.5.0 ostaje moguć **samo javni pregled**, uz upozorenje da novi profil nije dostupan. Ne mijenjaju se već pohranjene sidrene cijene niti se pokušava rekonstruirati cijena za prošle datume.

### Granice povezivanja

- WordPress je **izvor istine za objavljene online cjenike**. Android je javni klijent za čitanje.
- Windows ima **zasebnu lokalnu bazu**. Pregledava objavljene WordPress podatke, ali ne provodi automatsko dvosmjerno uređivanje.
- Potpuno administratorsko uređivanje na Androidu i Windowsu zahtijeva poseban autentificirani protokol, minimalne WordPress capability dozvole, autentifikaciju bez administratorove lozinke u aplikaciji, ograničenja brzine, verifikaciju konkurentnih izmjena, opoziv pristupa i audit log.
- Nikad ne koristite javni REST za izmjene kataloga ni ne pretpostavljajte da su lokalni Windows nacrti automatski objavljeni na webu.
- Sami API metapodaci ne znače da korisnikov katalog sadrži sve podatke ili da su cijene povijesno točne.

## Kontrolni popis prije 17. 11. 2026.

1. Za svaku stavku provjerite kojoj kategoriji pripada te referentni datum i **dokazivu cijenu** na taj datum; prazna povijest se ne popunjava iz trenutačne cijene.
2. Provjerite popunjenost obveznih javnih CSV/XML polja i ispravnost cijena, uključujući posebne oblike prodaje, barkod, marku, jedinice i dostupnost po poslovnici.
3. Provjerite stvarno objavljivanje prije propisanog roka, postavite pouzdani poslužiteljski cron i potvrdite najmanje 30 dana javne dostupnosti arhive.
4. Provjerite funkcionalnost dohvaćanja bez prijave, odvojene datoteke poslovnica, stabilne URL-ove te sigurnu dostupnost XML/CSV sadržaja automatiziranim klijentima.
5. Pratite objavu konačnih pravilnika i izuzeća prije promjene poslovnih pravila.

Ovaj dokument opisuje stanje na datum iz naslova i nije zamjena za pravni savjet.

## Kontrolirani prijenos lokalnog Windows kataloga u WordPress

Windows **0.10.3** nudi eksplicitnu mogućnost izvoza radnog CSV-a s 15 zaglavlja usklađenih s WordPress SIDRENA uvoznikom (`naziv;sku;vrsta;marka;url;cijena_redovna;cijena_akcijska;naziv_posebnog_oblika;jedinica_mjere;cijena_jedinice_mjere;sidrena_cijena;sidrena_datum;barkod;dostupnost;poslovnica`).

Izvoze se samo stvarno spremljeni naziv, vrsta, SKU, barkod, jedinica, aktualna cijena te već potvrđeni referentna cijena i datum. Polja bez lokalnog izvora ostaju prazna. Slijedi **ručni pregled i potvrda** u WordPress administratorskom uvozu; datoteka nije samostalno zakonski ovjerena javna objava. Windows lokalni ID ne smije se automatski prenositi kao globalni WordPress ID.
