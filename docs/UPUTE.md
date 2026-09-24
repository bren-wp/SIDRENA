# Sidrena 1.6.6 - Upute za instalaciju i korištenje

**Službena stranica:** https://sidrene-cijene.com.hr/  
**GitHub:** https://github.com/bren-wp/SIDRENA  
**Autor:** Brendigo  
**Licenca:** GPL-2.0-or-later

> Sidrena je tehnički alat za vođenje, objavu i arhiviranje cijena. Ne zamjenjuje pravni ili računovodstveni savjet. Povijesne i referentne cijene moraju dolaziti iz stvarne poslovne evidencije.

## 1. Načini rada

Sidrena je jedan plugin za tri scenarija:

- **WooCommerce proizvodi i varijacije** - Sidrena polja na proizvodu, bulk editor, povijest, Woo CSV Import/Export i javni cjenici.
- **WordPress bez WooCommercea** - vlastiti paginirani katalog proizvoda s CSV/XML uvozom.
- **Usluge** - aktualna i sidrena cijena, vrsta/opseg usluge, pripadajući troškovi, ugradbena/zamjenska roba i cijene po lokacijama.

Možete koristiti proizvode, usluge ili mješoviti način rada.

### Samostalni WordPress način je punopravni način rada

WooCommerce nije potreban za aktivaciju niti za prikaz Sidrena izbornika. Bez WooCommercea dostupni su Sidrena katalog proizvoda, usluge, CSV/XML uvoz, automatsko generiranje, javni HTML, arhiva, REST API, Site Health i shortcodeovi. U toolbaru piše **Samostalni WordPress način**.

Ako kasnije aktivirate WooCommerce, ista Sidrena instalacija automatski uključuje Woo integraciju. Ne trebate mijenjati plugin niti migrirati postojeći standalone katalog.

### Ako Sidrena nije vidljiva u lijevom admin izborniku

Od verzije 1.6.6 administrator s WordPress capabilityjem `manage_options` mora uvijek vidjeti top-level **Sidrena** izbornik, čak i ako custom capability `manage_sidrena` još nije zapisan ili osvježen u trenutnoj sesiji. Isto vrijedi za link **Otvori Sidrenu** na stranici Dodaci.

Za prilagođene role i WooCommerce shop management i dalje su podržani `manage_sidrena` i `manage_woocommerce`.

## 2. Instalacija

1. WordPress: **Dodaci > Dodaj novi > Prenesi dodatak**.
2. Prenesite **sidrena-1.6.6.zip**.
3. Kliknite **Instaliraj sada** i **Aktiviraj**.
4. Otvorite **Sidrena > Pregled**.
5. Provjerite izbornike Pregled, Usklađenost, Katalog, Cjenici, Arhiva, Lokacije, Postavke, Alati, Dnevnik, Propisi i Upute.

Administrator i, kada postoji, WooCommerce Shop Manager dobivaju Sidrena ovlast `manage_sidrena`. Sidrena ne uklanja WordPressov core izbornik Postavke.

## 3. Prvo podešavanje

U **Sidrena > Postavke** odaberite poslovni način rada, referentne datume, CSV format, čuvanje arhive i vrijeme generiranja.

Za odluke NN 101/2026 nove kategorije proizvoda i usluga koriste cijenu koja je vrijedila **10. rujna 2026.** Ranije obuhvaćene kategorije hrane, pića, kozmetike, sredstava za čišćenje, toaletnih potrepština i proizvoda za kućanstvo zadržavaju referentni datum **2. svibnja 2025.**

## 4. Lokacije

U **Sidrena > Lokacije** za svaku aktivnu poslovnicu/webshop unesite:

- jedinstveni ID,
- vrstu objekta,
- oznaku objekta,
- adresu,
- sekvencu/redni broj gdje je potreban.

Neaktivna lokacija može ostati spremljena bez svih obveznih podataka. Barem jedna lokacija mora ostati u konfiguraciji. Brisanje lokacije čisti njene spremljene lokalne podatke.

## 5. WooCommerce proizvodi

Sidrena na WooCommerce proizvodima i varijacijama može voditi:

- sidrenu cijenu i referentni datum/skupinu,
- Sidrena šifru ako nema SKU-a,
- marku i barkod fallback,
- primjenjivost jedinične cijene,
- količinu i jedinicu pakiranja,
- jedinicu mjere i cijenu po jedinici,
- naziv posebnog oblika prodaje,
- podatke potrebne za internu povijest i provjeru sniženja.

Private, password-protected i druga nejavna stanja ne izlažu se kroz javni Sidrena REST/cjenik.

## 6. Automatska jedinična cijena

Kada je jedinična cijena označena kao obvezna, a ručni iznos je prazan, Sidrena može izračunati baznu cijenu iz aktualne maloprodajne cijene i pakiranja.

Primjeri:

- 3,75 EUR / 750 g = 5,00 EUR/kg
- 2,40 EUR / 1,5 l = 1,60 EUR/l

Podržane su uobičajene jedinice mase, volumena, duljine, površine, obujma i komada. Status **Nije primjenjiva** ili **Iznimka** sprječava objavu starih unit-price podataka.

## 7. Sidrena Katalog

### WooCommerce aktivan

**Sidrena > Katalog** je masovni editor. Uređujte katalog po stranicama i spremite prije prelaska na sljedeću stranicu. Količina/jedinica pakiranja i auto-izračun rade i u bulk editoru.

### Bez WooCommercea

Sidrena koristi vlastiti paginirani katalog. Možete ručno dodavati proizvode ili uvesti CSV/XML. Spremanje prikazuje upozorenje ako je dio stavki imao grešku.

## 8. Hrvatski CSV/XML uvoz

Sidrena normalizira UTF-8 i pokušava sigurno pretvoriti Windows-1250/ISO-8859-2. Preporuka je uvijek spremati CSV kao UTF-8.

Prihvaća hrvatska i tehnička zaglavlja, npr.:

- NAZIV PROIZVODA
- ŠIFRA PROIZVODA
- MALOPRODAJNA CIJENA
- SIDRENA CIJENA
- SIDRENA CIJENA NA 10.09.2026.
- BARKOD
- KOLIČINA PAKIRANJA / NETO KOLIČINA
- JEDINICA PAKIRANJA
- JEDINICA MJERE
- CIJENA ZA JEDINICU MJERE
- DOSTUPNOST

Brojevi `1.234,56` i `1,234.56` normaliziraju se na istu decimalnu vrijednost. Količina `750 g` može se razdvojiti na broj i jedinicu.

Zaštite uključuju ograničenje veličine, 50.000 redaka, provjeru dupliciranih zaglavlja i odbijanje XML DOCTYPE/ENTITY deklaracija.

## 9. WooCommerce CSV Import/Export

Standardni WooCommerce CSV alat dobiva Sidrena stupce za sidrenu cijenu/datum, marku, barkod, status jedinične cijene, količinu pakiranja, jedinicu pakiranja, jedinicu mjere, cijenu po jedinici i naziv posebnog oblika prodaje.

## 10. Usluge

U **Sidrena > Usluge** možete voditi:

- aktualnu i sidrenu cijenu,
- referentni datum,
- posebni oblik prodaje,
- vrstu i opseg usluge,
- pripadajuće troškove/napomene,
- ugradbenu ili zamjensku robu i cijenu,
- različite cijene po lokacijama.

Javni shortcode: **[sidrena_usluge]**.

## 11. Uvoz sidrenih cijena za WooCommerce

**Sidrena > Alati > Uvoz sidrenih cijena**.

Minimalno: `sku`/šifra i `anchor_price`/sidrena cijena. Dodatno se mogu koristiti referentni datum i skupina. Nakon uvoza Dnevnik bilježi obrađene, ažurirane i preskočene retke.

## 12. Lokacijske cijene i dostupnost

**Sidrena > Alati > Raspoloživost i cijena po lokaciji** prihvaća location_id, product_id ili sku, price, anchor_price i availability.

Availability je **dostupno** ili **nedostupno**. Prazna lokalna cijena koristi osnovnu WooCommerce vrijednost.

## 13. Digitalni cjenici

**Sidrena > Cjenici > Generiraj sada** radi preflight i objavljuje valjane CSV/XML datoteke. Generiranje je zaključano od paralelnih cron/CLI/manual procesa. Javne datoteke i snapshot objavljuju se atomskim temp-write -> rename postupkom, pa neuspjela nova generacija ne smije uništiti zadnju valjanu objavu.

## 14. Javni HTML i shortcodeovi

- **[sidrena_cjenik]** - pretraživi aktualni cjenik
- **[sidrena_arhiva]** - javna arhiva
- **[sidrena_cjenici]** - lista javnih datoteka
- **[sidrena_usluge]** - cjenik usluga
- **[sidrena_cijena]** / **[sidrena-cijena]** - referentna cijena proizvoda gdje je primjenjivo

Javni HTML poštuje postavku uključeno/isključeno. Pretraga prikazuje broj trenutno vidljivih stavki.

## 15. Provjera javne dostupnosti

U **Sidrena > Cjenici** kliknite **Provjeri javnu dostupnost**.

Provjera dohvaća samo Sidrena URL-ove unutar vlastitog uploads prostora i provjerava HTTP 200, neprazan odgovor i da umjesto CSV/XML nije vraćena HTML blokada/login/challenge stranica.

Ako provjera ne prođe, provjerite CDN/WAF/Cloudflare, Wordfence, Basic Auth, maintenance mode, cache i prava pristupa `wp-content/uploads/sidrena/`.

## 16. Arhiva i integritet

**Sidrena > Arhiva 30+ dana** prikazuje prethodne objave. Zapis može sadržavati broj redaka, veličinu, rok čuvanja, javni URL i SHA-256.

U Alatima je dostupan CSV indeks arhive.

## 17. Povijest cijena

WooCommerce povijest prati standardne Woo događaje i dodatno izravne promjene meta polja cijene koje mogu napraviti importeri/drugi pluginovi. Promjene su deduplicirane. Usluge i lokalne cijene imaju odvojenu internu povijest.

## 18. Cron i WP-CLI

Sidrena zakazuje dnevno generiranje prema WordPress vremenskoj zoni. Ako je `DISABLE_WP_CRON` uključen, potreban je pouzdani server cron ili WP-CLI automatizacija.

Primjeri:

    wp sidrena generate
    wp sidrena status
    wp sidrena audit
    wp sidrena fill --dry-run

## 19. REST

Ako je uključen u Postavkama, ekran Cjenici prikazuje Sidrena REST endpointove. Ako REST ne koristite, možete ga isključiti.

### REST u samostalnom i WooCommerce načinu

`/wp-json/sidrena/v1/cijene?type=products` radi i bez WooCommercea.

- bez WooCommercea: `products` sadrži standalone Sidrena proizvode
- s WooCommerceom: `products` sadrži WooCommerce proizvode, a `standalone_products` zasebno paginirane dodatne Sidrena stavke
- `catalog_mode` navodi `standalone` ili `woocommerce`
- `woocommerce_active` eksplicitno navodi stanje integracije
- standalone prikaz pojedine stavke dostupan je putem `/display/s123`

## 20. Site Health i Dnevnik

WordPress **Alati > Zdravlje web-mjesta** dobiva Sidrena provjere rasporeda i arhive. **Sidrena > Dnevnik** sadrži lokalni audit generiranja, uvoza, postavki, lokacija i javnih HTTP provjera.

## 21. Sigurnost i privatnost

Sidrena nema telemetriju, udaljenu aktivaciju, Pro paywall, licencni server ni obavezni cloud. Administratorske akcije koriste capability i nonce provjere. Uvozi su ograničeni i sanitizirani. CSV izvoz neutralizira spreadsheet formula injection. Javni URL checker koristi safe HTTP dohvat.

## 22. Česti problemi

### Sorry, you are not allowed to access this page

Koristite administrator ili WooCommerce Shop Manager račun. Sidrena koristi vlastitu ovlast `manage_sidrena`.

### WordPress Postavke nisu vidljive

Core WordPress Postavke traže `manage_options`. Sidrena ne uklanja taj izbornik i koristi vlastiti top-level izbornik.

### Cjenik se ne obnavlja

Provjerite Cjenici, Dnevnik, Site Health i `wp sidrena status`. Ako je WP-Cron isključen, provjerite server cron.

### Import ne prepoznaje stupce

Koristite podržana hrvatska/tehnička zaglavlja i izbjegavajte dva stupca koja se nakon normalizacije svode na isti naziv.

### Hrvatski znakovi su oštećeni

Spremite CSV kao UTF-8. Legacy encoding fallback postoji, ali UTF-8 je preporučen.

### Javni cjenik vraća 403 ili HTML

Dopustite anoniman dohvat javnih Sidrena datoteka kroz CDN/WAF/bot zaštitu.

## 23. Produkcijski checklist

- [ ] Način rada je točan.
- [ ] Aktivne lokacije imaju ID, vrstu, oznaku i adresu.
- [ ] Referentne cijene dolaze iz dokumentirane evidencije.
- [ ] Primjenjivost jedinične cijene je pregledana.
- [ ] Usklađenost nema neriješena tehnička upozorenja.
- [ ] Generiraj sada prolazi.
- [ ] Provjeri javnu dostupnost prolazi.
- [ ] CSV/XML se otvaraju anonimno.
- [ ] HTML cjenik radi na desktopu i mobitelu.
- [ ] Arhiva je zapisiva.
- [ ] Cron je zakazan i pouzdan.
- [ ] Dnevnik nema neriješenih grešaka.
- [ ] Backup baze i uploads/sidrena postoji.

## 24. Službeni izvori

- NN 101/2026 - objava cjenika proizvoda i usluga: https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html
- NN 101/2026 - dodatna cijena: https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html
- NN 105/2026 - isticanje maloprodajne/jedinične cijene: https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_105_1270.html
- HOK - informacije od 1. listopada 2026.: https://www.hok.hr/novosti-iz-hok/dodatna-cijena-i-objava-cjenika-od-1-listopada-2026-najvaznije-informacije

## 25. Podrška

- Web: https://sidrene-cijene.com.hr/
- GitHub: https://github.com/bren-wp/SIDRENA
- Autor: Brendigo

Sidrena ostaje besplatan open-source projekt bez Pro verzije i bez telemetrije.
