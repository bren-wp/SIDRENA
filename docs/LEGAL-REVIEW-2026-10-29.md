# SIDRENA — pravna revizija 29. listopada 2026.

## Potvrđeni propisi i vremenski slijed

- Zakon o zaštiti potrošača (NN 19/2022, 59/2023, 59/2026): izmjena NN 59/2026 izričito odgađa novi članak 7. stavke 1.–9. na **17. studenoga 2026.**, dok ostale izmjene imaju različite datume početka. https://narodne-novine.nn.hr/clanci/sluzbeni/2026_06_59_728.html
- NN 75/2025: prijašnja obveza za određenu robu, referentni datum **2. svibnja 2025.** https://narodne-novine.nn.hr/clanci/sluzbeni/2025_05_75_979.html
- NN 101/2026 (1212 i 1213), izmijenjena NN 110/2026 (1309 i 1310): proširenje na dodatne kategorije robe i usluga, referentni datum **10. rujna 2026.**, odgođena primjena **17. studenoga 2026.** https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html ; https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html ; https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_110_1309.html ; https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_110_1310.html
- NN 105/2026: pravilnik o isticanju maloprodajne cijene i cijene za jedinicu mjere objavljen 18. rujna i na snazi od **26. rujna 2026.**; primjenjive iznimke valja provjeriti pojedinačno. https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_105_1270.html
- Prijedlozi dvaju provedbenih pravilnika za baznu cijenu i objavu cjenika bili su **u savjetovanju 14. listopada – 13. studenoga 2026.** na datum ove revizije. Predviđene iznimke nisu još konačna prava ili obveze. https://mingo.gov.hr/vijesti/ministar-susnjar-kroz-dijalog-s-gospodarstvenicima-do-provedivih-pravila-i-vece-zastite-potrosaca/10461

## Tri različite cijene

**Aktualna maloprodajna cijena**: stvarno naplaćivana cijena. **Sidrena/bazna cijena**: povijesni iznos na propisani datum, koji mora biti dokumentiran, ne rekonstruira se iz sadašnje cijene. **Najniža cijena prije sniženja**: zasebna referenca za relevantni oblik prodaje, iz prethodnih 30 dana prije početka sniženja, ne iz zadnjih 30 dana koji uključuju samu akciju. Postoje zakonske iznimke za pojedine vrste robe i posebnosti za određene usluge/kanale. Nije sigurno u svim slučajevima automatski koristiti istu logiku.

## Javni cjenici i prijelaz

NN 101/2026 u izmijenjenom obliku zahtijeva, za obuhvaćene subjekte s web-stranicom, strojno čitljive CSV/XML cjenike, dnevno ažuriranje proizvoda najkasnije do 08:00, ažuriranje usluga uz promjene i arhivu dostupnu najmanje 30 dana. Objave uključuju relevantna polja proizvoda/usluge, posebne oblike prodaje, referentne cijene, dostupnost, poslovnice i automatizirani pristup. Primjena novih odluka zakazana je za 17. studenoga, ali su stara pravila već važila za pojedine kategorije.

## Implementacija i ograničenja

WordPress 1.5.3 u javni read-only legal profil dodaje price_reduction_lookback_days=30, discount_baseline_is_anchor_price=false, unit_price_rule_effective_from=2026-09-26, reviewed_on=2026-10-29 i draft_rulebooks_consultation_until=2026-11-13. Android 0.3.3 i Windows 0.10.6 prihvaćaju dodatna polja, validiraju proturječne vrijednosti, a stariji WP profili ostaju podržani. Sadržaj postojećih cijena, CSV/XML i arhive nije automatski prerađen.

WordPress je izvor javno objavljenih podataka; Android je klijent za čitanje; Windows ima vlastitu lokalnu bazu i ručni kontrolirani CSV postupak. Ne postoji automatska dvosmjerna autentificirana sinkronizacija.

## Provjere prije produkcijskog izdanja

Provjeriti stvarne dokumente cijena na oba povijesna datuma; klasifikaciju robe, usluga, iznimke i posebne propise; dostupnost cijene za jedinicu mjere; potpunost javnih datoteka; realnu pouzdanost CRON objava do 08:00 i arhive; integraciju WooCommercea; konačne pravilnike nakon savjetovanja; instalacijske i mobilne testove na stvarnim uređajima. Ugradnja pravnog profila ne izdaje potvrdu usklađenosti trgovca.

Ovaj pregled služi informiranju i nije zamjena za pravni savjet.