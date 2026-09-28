<!--
SIDRENA source file.
Author: brendigo
Author URI: https://brendigo.com/
Plugin URI: https://brendigo.com/sidrene-cijene/
Support: sidrena@brendigo.com
-->

# SIDRENA — WordPress izdanje - detaljne upute za korištenje

![Stvarni SIDRENA — WordPress izdanje admin prikaz](media/screenshot-wordpress.png)

> Screenshot se automatski snima iz aktivnog WordPress admin sučelja pri pripremi WordPress.org asseta. U instalacijskom ZIP-u nalazi se kao `docs/images/screenshot-admin.png`.

## Galerija stvarnog sučelja

<table>
<tr>
<td width="50%"><img src="media/screenshot-wordpress-1.png" alt="SIDRENA — WordPress izdanje pregled"></td>
<td width="50%"><img src="media/screenshot-wordpress-2.png" alt="SIDRENA — WordPress izdanje katalog"></td>
</tr>
<tr>
<td width="50%"><img src="media/screenshot-wordpress-3.png" alt="SIDRENA — WordPress izdanje cjenici"></td>
<td width="50%"><img src="media/screenshot-wordpress-4.png" alt="SIDRENA — WordPress izdanje lokacije"></td>
</tr>
<tr>
<td width="50%"><img src="media/screenshot-wordpress-5.png" alt="SIDRENA — WordPress izdanje postavke"></td>
<td width="50%"><img src="media/screenshot-wordpress-6.png" alt="SIDRENA — WordPress izdanje pomoć"></td>
</tr>
</table>

Svih šest slika snima se iz aktivnog plugina. Ne koriste se dizajnerski mockupovi kao dokaz stvarnog administratorskog sučelja.

## Vizualni sustav

SIDRENA koristi novi produkcijski UI/UX izrađen od nule: tamno plavu navigaciju, lokalni svjetionik vizual, SIDRENA S + sidro identitet, responzivne statusne kartice, čiste tablice i jasne akcije. WordPress izdanje koristi plavi akcent, a WooCommerce izdanje uz osnovni SIDRENA plavi koristi ljubičasti WooCommerce akcent.

Sve slike u ovoj dokumentaciji dolaze iz stvarnog aktivnog WordPress administratorskog sučelja koje automatski snima CI.

## Namjena

SIDRENA — WordPress izdanje namijenjena je WordPress web stranicama koje **ne koriste WooCommerce kao izvor proizvoda**. Proizvode možete voditi u SIDRENA katalogu ili povezati s postojećim javnim WordPress sadržajem.

## Instalacija

Instalacijski ZIP je namjerno optimiziran za shared hosting: admin CSS/JS i runtime logotipi ostaju obvezan dio paketa, dok WordPress.org screenshotovi i marketinški asseti ostaju izvan ZIP-a. WordPress.org screenshotovi i marketinški asseti nisu u ZIP-u jer nisu potrebni za rad plugina. Build ga ograničava na najviše 1,5 MiB kako bi stao ispod čestog PHP `upload_max_filesize = 2M` limita.

1. Prenesite aktualni `sidrena-wordpress-<verzija>.zip` u WordPress > Dodaci > Dodaj novi > Prenesi dodatak.
2. Aktivirajte **SIDRENA — WordPress izdanje**.
3. Otvorite **SIDRENA** u lijevom admin meniju.
4. Otvorite **SIDRENA > Katalog**.

Kod nadogradnje preko starijeg SIDRENA WordPress izdanja plugin automatski pokreće sigurni repair/migration prolaz i čuva postojeće kataloge, postavke, arhivu i povijest.

## Najbrži način za postojeću web stranicu

Ako na stranici već imate proizvode ili drugi tip sadržaja koji predstavlja proizvode:

1. U **SIDRENA > Katalog** otvorite karticu za automatsko povezivanje.
2. Odaberite postojeći javni tip sadržaja.
3. Ako znate meta ključ cijene, unesite ga; inače polje ostavite prazno.
4. Kliknite **Pokreni sinkronizaciju**.
5. SIDRENA obrađuje zapise u batchovima od 100.
6. Nakon povezivanja provjerite naziv i aktualnu cijenu.
7. Dopunite **sidrenu cijenu**, datum, marku, barkod i jediničnu cijenu kada su primjenjivi.

SIDRENA pokušava prepoznati uobičajene meta ključeve cijene kao što su `_price`, `price`, `cijena`, `product_price`, `_regular_price` i `regular_price`.

## Automatski prikaz sidrene cijene

Povezanom WordPress zapisu SIDRENA automatski dodaje sidrenu cijenu na javnoj pojedinačnoj stranici. Nije potrebno ručno umetati shortcode za svaki povezani proizvod.

Za posebne rasporede i page buildere možete koristiti:

`[sidrena_cijena id="s123"]`

gdje je `123` ID SIDRENA proizvoda.

## Ručni katalog

Ako ne želite povezivati postojeći sadržaj, proizvode možete:
- dodati ručno u SIDRENA katalog
- uvesti CSV/XML datotekom
- uređivati u tabličnom prikazu

## Uvoz velikih kataloga

SIDRENA provjerava maksimalan broj redaka prije poslovnih promjena. CSV/XML import podržava najviše 50.000 zapisa po datoteci. WordPress CSV obrađuje se streaming pristupom, a XML koristi XMLReader kada je dostupan uz NONET i zabranu DOCTYPE/ENTITY deklaracija. Prevelika datoteka odbija se prije djelomičnog unosa.

## Usluge

Usluge se vode zasebno i mogu se prikazivati na javnom cjeniku zajedno s proizvodima ili kao poseban cjenik usluga. Prva objava nove usluge finalizira sidrenu vrijednost tek nakon spremanja stvarno unesene aktualne cijene. Shortcode `[sidrena_usluge]` koristi server-side paginaciju. Zadano prikazuje 50 usluga po stranici; atribut `po_stranici` podržava vrijednosti od 10 do 100.

## Lokacije

Za svaku aktivnu lokaciju/webshop SIDRENA generira zasebnu objavu prema konfiguraciji. Provjerite:
- oznaku
- vrstu objekta
- adresu
- aktivnost lokacije
- redni broj objave

## Objava cjenika na web stranici

U **SIDRENA > Cjenici** kliknite **Izradi stranicu Objava cjenika**.

Plugin objavljuje WordPress stranicu sa shortcodeom [sidrena_objava_cjenika]. Prikaz je namjerno fokusiran na cijene, datoteke i arhivu; opći identitet tvrtke/obrta održava se u odgovarajućem dijelu web-stranice, a ne u SIDRENA postavkama.

Javni cjenik koristi server-side pretragu kroz cijeli snapshot i paginaciju. Zadano se prikazuje 50 stavki po stranici, a zasebni shortcode može koristiti npr. [sidrena_cjenik po_stranici="50"]. Podržan raspon je 10–100 stavki po stranici. Pretraga i paginacija rade bez JavaScripta.

Dostupni su i zasebni prikazi:

- [sidrena_cjenici] — aktualne datoteke i arhiva
- [sidrena_cjenik] — pretraživi javni cjenik
- [sidrena_arhiva] — prethodne objave
- [sidrena_cjenik_url] — URL aktualne datoteke
- [sidrena_usluge] — javni katalog usluga

Premium atributi za datoteke/arhivu uključuju lokacija, format (csv/xml), katalog (products/services), arhiva, limit, prikaz i naslovi. Prikaz može biti kartice, popis/list ili tablica/table.

## Pojednostavljene postavke

SIDRENA više ne traži da laik odlučuje treba li uključiti zakonski važan output. Automatski su uključeni CSV, XML, javni HTML cjenik, JSON manifest, REST indeks, strict publication, sidrena cijena, 30-dnevna referentna evidencija, povijest cijena i upozorenja.

Propis za strojno obradivi cjenik predviđa XML **ili** CSV format. SIDRENA namjerno generira oba formata radi interoperabilnosti i praktičnijeg automatiziranog dohvaćanja; oba formata su tehnička odluka plugina, a ne tvrdnja da zakon zahtijeva oba istodobno.

U **SIDRENA > Postavke** korisnik podešava samo:

1. objavljuje li proizvode, usluge ili oboje
2. vrijeme dnevnog generiranja prije 08:00
3. razdoblje čuvanja arhive, najmanje 30 dana
4. e-mail za upozorenja

Ako je e-mail prazan, koristi se WordPress administratorska adresa. CSV koristi stabilni razdjelnik, a referentni datumi imaju zakonski zadane vrijednosti; posebna stvarna situacija pojedinog proizvoda rješava se na proizvodu.

## Automatska dnevna objava

Zadano vrijeme generiranja je **06:30**.

SIDRENA:
- zakazuje dnevno generiranje
- nakon spremanja bitnih podataka stavlja ponovno generiranje u red
- ima sigurnosnu provjeru koja nakon planiranog vremena provjerava postoji li današnja objava i po potrebi pokreće novu generaciju
- može poslati ograničeno e-mail upozorenje kod neuspjele ili zakašnjele objave

WordPress WP-Cron ovisi o izvršavanju WordPressa. Za pouzdano izvršavanje prije poslovno kritičnog roka konfigurirajte server cron koji redovito pokreće WordPress cron.

## Arhiva

Prethodne uspješne CSV/XML objave ostaju javno dostupne prema postavljenom razdoblju čuvanja, najmanje 30 dana. Javna arhiva grupira datoteke po datumu objave (npr. 24.09.2026.), prikazuje format i naziv datoteke te akciju **Preuzmi**. Aktualne datoteke prikazuju se zasebno i ne dupliciraju se među prethodnim objavama.

## Korak-po-korak za korisnika koji prvi put koristi SIDRENA — WordPress izdanje

### Korak 1 — otvorite Postavke

Odaberite način rada. Za običnu trgovinu odaberite **Proizvodi / trgovina**. Ako imate samo usluge odaberite **Usluge**. Ako imate oboje, odaberite **Proizvodi i usluge**.

Ostavite vrijeme 06:30 ako nemate poseban razlog za drugačije vrijeme. SIDRENA neće prihvatiti rizično vrijeme 08:00 ili kasnije.

### Korak 2 — uredite Lokacije

Otvorite **SIDRENA > Lokacije**. Svaka aktivna lokacija mora imati jasan ID, vrstu objekta, oznaku i stvarnu adresu. Adresa ulazi i u naziv datoteke, zato izbjegavajte privremene ili testne vrijednosti.

### Korak 3 — unesite prvi proizvod

Otvorite **SIDRENA > Katalog** i unesite stvarni proizvod. Za početni test preporučuje se jedan proizvod s potpuno popunjenim podacima prije masovnog uvoza.

Provjerite naziv, šifru, marku, barkod, aktualnu cijenu, sidrenu cijenu, dostupnost te jedinicu/jediničnu cijenu ako je primjenjiva.

### Korak 4 — provjerite sidrenu cijenu

SIDRENA cijena mora dolaziti iz stvarne evidencije. Ne unosite izmišljenu vrijednost samo da biste uklonili upozorenje.

Za opće novobuhvaćene stavke zadani referentni datum je 10.09.2026., a za ranije obuhvaćene FMCG kategorije 02.05.2025. Posebni slučajevi evidentiraju se na stavci.

### Korak 5 — ako postoji poseban oblik prodaje

Evidentirajte da je poseban oblik prodaje aktivan i navedite njegov naziv. SIDRENA pritom i dalje koristi sidrenu cijenu određenu za mjerodavni datum; ne uvodi zasebnu 30-dnevnu referentnu cijenu.

### Korak 6 — provjerite jediničnu cijenu

Za proizvod označite je li jedinična cijena obvezna, nije primjenjiva ili postoji iznimka. Ako je obvezna, unesite količinu i jedinicu te provjerite izračun.

### Korak 7 — otvorite Provjeru

Otvorite **SIDRENA > Provjera** i redom riješite crvena/žuta upozorenja. Zeleni status znači da je tehnička provjera prošla, ne da plugin daje pravno jamstvo.

### Korak 8 — generirajte prvi cjenik

U **SIDRENA > Cjenici** kliknite **Generiraj cjenik odmah**. Nakon završetka provjerite da postoje CSV i XML datoteke.

### Korak 9 — otvorite javnu stranicu

Izradite stranicu **Objava cjenika** i otvorite je kao običan posjetitelj. Isprobajte pretragu, paginaciju i preuzimanje.

### Korak 10 — provjerite arhivu i integritet

Nakon više uspješnih objava starije datoteke ulaze u arhivu. SIDRENA prikazuje veličinu, broj redaka i SHA-256 podatak koji je spremljen pri generiranju.

### Korak 11 — provjerite e-mail upozorenja i cron

Provjerite da WordPress administratorski e-mail ili posebno upisana adresa može primati poruke. Ako hosting ima slab WP-Cron, postavite server cron.

### Korak 12 — nakon svake veće promjene

Nakon uvoza, promjene lokacija ili velikog uređivanja kataloga otvorite Dnevnik i napravite ručnu probnu generaciju.

## Što ne treba dirati ručno

Nemojte ručno brisati datoteke u uploads/sidrena, mijenjati manifest ili uređivati arhivske CSV/XML datoteke nakon objave. Ako morate migrirati web, prenesite cijeli uploads/sidrena sadržaj i bazu podataka zajedno.

## Produkcijska provjera

- SIDRENA prikazuje malu lokalnu sidro ikonicu u WordPress bočnom meniju, bez vanjskih asseta i bez dodatnog dupliciranja brenda.
- Release liniju dodatno čuva Admin polish guard workflow.
- Službeni release smije sadržavati samo `sidrena-wordpress-1.0.26.zip` i `sidrena-woocommerce-1.0.26.zip`.

## WP-CLI

`wp sidrena generate`

`wp sidrena status`

`wp sidrena audit`

## Tehnička kontrola obveznih elemenata

Prije produkcijske objave provjerite najmanje sljedeće:

- sidrena cijena uz važeću cijenu kada je obveza primjenjiva
- referentni datum 10.09.2026. za novobuhvaćene proizvode/usluge, odnosno 02.05.2025. za ranije obuhvaćene FMCG kategorije
- CSV ili XML javni cjenik
- zasebnu objavu po lokaciji i webshopu kada je primjenjivo
- najmanje 30 dana javne dostupnosti prethodnih objava
- aktualni cjenik trgovca za tekući radni dan najkasnije do 08:00, odnosno cjenik usluga pri promjeni cijene prema primjenjivom pravilu
- automatizirani dohvat aktualnih maloprodajnih cijena
- obvezna polja proizvoda: naziv, šifra, marka, primjenjiva jedinica i jedinična cijena, maloprodajna cijena, podatak o posebnom obliku prodaje, sidrena cijena, barkod i dostupnost
- za usluge: naziv, maloprodajna cijena, podatak o posebnom obliku prodaje i sidrena cijena, uz podatke o vrsti/opsegu i pripadajućim troškovima gdje ih traži primjenjivi propis

## SIDRENA cijena

SIDRENA cijena je zasebna referentna vrijednost koja se prikazuje uz aktualnu maloprodajnu cijenu kada je obveza primjenjiva. Poseban oblik prodaje vodi se samo kroz status i naziv oblika; 30-dnevno razdoblje u SIDRENA-i odnosi se na javnu arhivu objavljenih cjenika, ne na izračun sidrene cijene.

Vrijednost i datum moraju odgovarati stvarnoj poslovnoj evidenciji. SIDRENA može tehnički spremiti, prikazati i provjeriti vrijednost, ali ne može sama utvrditi je li uneseni povijesni podatak činjenično točan. Ako proizvod ili usluga imaju poseban status, prije objave provjerite primjenjivi službeni izvor ili stručnu pravnu procjenu.

## Jedinična cijena

Jedinična cijena računa se samo kada je za stavku označeno da je potrebna i kada postoje valjana količina i jedinica. Registry jedinica podržava standardne konverzije, a developer hookovi `sidrena_unit_definitions` i `sidrena_unit_aliases` omogućuju proširenje bez izmjene jezgre plugina.

Custom jedinica mora imati siguran canonical key, valjanu baznu jedinicu i pozitivan konačan multiplikator unutar razumnog raspona. Neispravne, negativne, beskonačne ili nesigurno imenovane definicije odbacuju se. SIDRENA ne zaključuje pravnu kategoriju proizvoda samo iz naziva, kategorije ili opisa.

## CSV i XML digitalni cjenici

Službena odluka za strojno obradivi cjenik predviđa XML **ili** CSV format. SIDRENA namjerno generira oba formata radi interoperabilnosti, automatizacije i lakšeg dohvaćanja od strane različitih sustava; to je tehnička odluka plugina i nije tvrdnja da su oba formata istodobno zakonski obvezna.

Za svaku uspješnu objavu provjerite naziv datoteke, lokaciju, katalog, format, datum generiranja, broj redaka, veličinu i SHA-256 vrijednost. Aktualne datoteke i arhivske datoteke moraju imati javni URL koji se može otvoriti. Akcija **Otvori** treba otvoriti javni resurs, dok je **Preuzmi** namijenjen spremanju datoteke.

## Shortcodeovi

SIDRENA podržava ove javne shortcodeove:

- `[sidrena_objava_cjenika]` za puni javni prikaz
- `[sidrena_cjenik]` za ciljani prikaz aktualnog cjenika
- `[sidrena_cjenici]` za popis ili grupu cjenika
- `[sidrena_arhiva]` za arhivu objava
- `[sidrena_cjenik_url]` kada trebate samo URL odgovarajuće datoteke
- `[sidrena_usluge]` za javni prikaz usluga

Atributi se koriste samo gdje imaju smisla i prolaze validaciju. Podržani obrasci uključuju lokaciju, format, katalog, arhivu, limit, prikaz i prilagođeni naslov. Za prikaz koristite kartice, popis ili tablicu prema prostoru stranice.

## REST, manifest i discovery

Javni REST sloj služi za dohvat javno objavljenih podataka i discovery metapodataka. Write REST endpointi nisu potrebni za ovaj workflow. JSON manifest povezuje aktualne datoteke, njihove URL-ove i integritetne metapodatke.

Ako REST ili manifest vratite kroz cache/CDN, provjerite da se nakon nove objave ne poslužuje zastarjela verzija dulje nego što je prihvatljivo za vaš produkcijski workflow. SIDRENA javne realtime odgovore označava tako da ih klijent ne bi trebao dugotrajno spremati.

## Automatizacija, watchdog i cron

Dnevno generiranje je osnovni automatski workflow. Vrijeme postavite dovoljno rano da objava završi prije relevantnog roka; nesigurne vrijednosti plugin normalizira na sigurnu vrijednost.

Publication watchdog provjerava je li očekivana objava stvarno nastala. Ako WordPress WP-Cron na hostingu nije pouzdan, konfigurirajte server cron koji redovito poziva WordPress cron. Nakon promjene cron konfiguracije napravite ručno generiranje i provjerite Dnevnik.

## E-mail upozorenja

E-mail za upozorenja koristi adresu iz SIDRENA postavki, a ako je prazna koristi WordPress administratorsku adresu. Upozorenje je namijenjeno stvarnom neuspjehu objave ili drugom stanju koje zahtijeva pažnju, ne marketingu.

Provjerite da hosting može slati WordPress e-mail. Ako koristite SMTP plugin, testirajte isporuku i provjerite spam mapu. Ne pretpostavljajte da je posao dovršen samo zato što je cron pokrenut; provjerite i objavljenu datoteku.

## Audit i dnevnik

Dnevnik bilježi važne administrativne i publikacijske događaje. Koristite ga pri rješavanju neuspjelog importa, ručnog generiranja, lokacijske promjene, greške integriteta ili watchdog upozorenja.

Audit zapis nije zamjena za računovodstvenu ili poslovnu evidenciju. Njegova je svrha tehnički trag rada plugina: što je SIDRENA pokušala napraviti, kada i s kojim rezultatom.

## Import i export

CSV i XML import služe za kontrolirani unos većih količina podataka. Datoteke imaju ograničenje veličine i broja redaka, provjeru ekstenzije i MIME tipa, normalizaciju tekstualnog kodiranja i sigurnosne provjere prije poslovnih promjena.

CSV vrijednosti tretiraju se kao podaci i izlaz se štiti od formula injection obrazaca. XML ne dopušta DOCTYPE/ENTITY deklaracije i koristi mrežno izolirani parser. Prije velikog importa napravite backup i testirajte manji uzorak.

## Troubleshooting

Ako se cjenik ne generira, prvo otvorite **SIDRENA > Provjera** i **Dnevnik**. Provjerite postoje li aktivne lokacije, valjani proizvodi/usluge i dozvola za zapisivanje u WordPress uploads direktorij.

Ako javni URL vraća 404, regenerirajte objavu i provjerite permalink/cache pravila. Ako se prikazuje stara datoteka, ispraznite relevantni page/cache/CDN sloj. Ako e-mail upozorenja ne stižu, testirajte WordPress mail odvojeno od SIDRENA workflowa.

Kod problema nakon nadogradnje nemojte ručno uređivati bazu. Napravite backup, provjerite Dnevnik, ponovno pokrenite generiranje i tek zatim razmatrajte rollback na prethodnu provjerenu verziju.

## Update postupak

Prije nadogradnje napravite backup baze podataka i `uploads/sidrena` direktorija. Nakon nadogradnje otvorite SIDRENA administraciju kako bi se izvršile potrebne upgrade provjere, zatim pokrenite ručno generiranje i pregledajte javnu objavu.

Ne instalirajte WordPress i WooCommerce izdanje istodobno. Ako mijenjate izdanje, prvo deaktivirajte postojeće izdanje, napravite backup i tek zatim aktivirajte drugo izdanje prema dokumentiranom migracijskom postupku.

## Sigurnost

Administrativne write akcije zahtijevaju odgovarajuću korisničku capability provjeru i nonce zaštitu. Importi dodatno provjeravaju veličinu, tip datoteke, broj redaka i sadržaj prije obrade. Javni REST endpointi su read-only i ne služe za administrativne izmjene.

Nemojte davati administratorski pristup korisnicima kojima nije potreban. Držite WordPress, PHP i SIDRENA verzije ažurnima te koristite HTTPS. Nikada ne šaljite backup baze, privatne korisničke podatke ili administratorske pristupne podatke kroz javni support kanal.

## Pravna napomena

SIDRENA je tehnički alat za vođenje i objavu podataka. Ne daje pravno jamstvo, ne potvrđuje da je svaki uneseni podatak činjenično točan i ne može procijeniti sve posebne okolnosti konkretnog trgovca ili pružatelja usluge. Korisnik ostaje odgovoran za stvarne cijene, povijesne podatke, primjenjivost propisa i pravodobnu objavu.

## Podrška

- E-mail: **sidrena@brendigo.com**
- WhatsApp: **+385 91 901 0092**
- Plugin možete instalirati i postaviti sami.
- Opcionalno jednokratno postavljanje od strane Brendiga: **80 EUR jednokratno**.
- Detaljni PDF priručnik i podrška: `docs/SIDRENA-UPUTE.pdf`
- Autor: **brendigo**

## Donacija

Donacija nije potrebna za korištenje SIDRENA funkcija. Plugin ne sadrži donacijski link niti donacija otključava funkcije, podršku ili pravnu potvrdu.

## Licenca

SIDRENA se distribuira pod licencom **GPLv2 ili novijom**, u skladu sa zahtjevima WordPress.org direktorija. Autorski i projektni identitet Brendiga ne smije se lažno predstavljati.

Puni tekst licence nalazi se u datoteci `LICENSE`.

## Prije produkcijske objave

Provjerite:
- stvarnu aktualnu cijenu
- stvarnu sidrenu/referentnu cijenu i datum
- marku, šifru i barkod
- jediničnu cijenu kada je primjenjiva
- dostupnost
- javni HTML prikaz
- CSV/XML datoteke
- arhivu
- Pomoć → Dnevnik

SIDRENA tehnički podržava provjerene zahtjeve za podatke i objavu cjenika, ali softver sam po sebi nije pravna potvrda poslovanja. Povijesne i referentne cijene moraju odgovarati stvarnoj poslovnoj evidenciji, a korisnik mora provjeriti primjenjivost važećih obveza na svoje konkretne proizvode, usluge i prodajna mjesta.
