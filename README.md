Besplatan WordPress/WooCommerce dodatak za sidrene cijene, povijest cijena te javne CSV/XML cjenike i arhivu 30+ dana.

== Description ==

**Sidrena** je potpuno besplatan open-source WordPress dodatak autora **Brendigo** za trgovce i pružatelje usluga u Hrvatskoj. Nema Pro izdanja, licencnog ključa, pretplate, telemetrije ni obaveznog vanjskog računa.

Plugin web: https://sidrena-cijena.com.hr/
Autor: https://brendigo.com/

Verzija 1.3.0 tehnički podržava rad s pravilima iz NN 101/2026 i službenim pojašnjenjima Ministarstva gospodarstva od 22. rujna 2026., koja se primjenjuju od 1. listopada 2026. Sidrena je tehnički alat za evidenciju i objavu podataka; ne predstavlja pravno mišljenje niti jamstvo usklađenosti konkretnog poslovnog subjekta.

= Sidrena / dodatna cijena =

* Standardni referentni datum za novobuhvaćene proizvode i usluge: 10.09.2026.
* Ranije obuhvaćene FMCG kategorije zadržavaju 02.05.2025.
* Ako je proizvod ili usluga na referentni dan bio na akciji, sidrena vrijednost treba odgovarati prethodnoj redovnoj cijeni, a ne sniženoj cijeni.
* Novouvedeni proizvodi i usluge nakon referentnog datuma mogu koristiti cijenu pri prvom uvrštenju i datum prvog uvrštenja.
* Promjena šifre ili naziva sama po sebi ne pretvara isti proizvod u novi proizvod; administrator mora provjeriti stvarni identitet artikla.
* WooCommerce proizvod i varijacija mogu imati vlastitu sidrenu cijenu, referentnu skupinu i datum.
* Usluge bez WooCommercea imaju vlastitu aktualnu i sidrenu cijenu.
* Na webu se sidrena cijena može prikazati uz aktualnu cijenu oznakom poput „Cijena na 10.09.2026.“.

Sidrena ne pokušava izmisliti povijesnu cijenu koju WordPress nije zabilježio. Za postojeće artikle povijesne vrijednosti treba provjeriti u vlastitoj poslovnoj evidenciji te ih unijeti ručno ili CSV uvozom.

= Tko objavljuje digitalni cjenik =

Prema službenom pojašnjenju Ministarstva gospodarstva od 22.09.2026., obveza objave digitalnog cjenika odnosi se na trgovce na malo i pružatelje usluga koji već imaju uspostavljenu mrežnu stranicu, uključujući i informativne/prezentacijske stranice. Sam profil na društvenoj mreži ne smatra se mrežnom stranicom.

= Javni CSV/XML cjenici =

Sidrena može generirati CSV, XML ili oba formata. Za svaku aktivnu fizičku lokaciju stvara se zasebna datoteka, a webshop se vodi kao zaseban objekt.

Cjenik proizvoda sadrži:

* naziv
* šifru
* marku
* jedinicu mjere, ako je primjenjivo
* cijenu za jedinicu mjere, ako je primjenjivo
* maloprodajnu cijenu
* informaciju o posebnom obliku prodaje i njegov naziv
* sidrenu cijenu i referentni datum
* barkod, ako je dostupan
* raspoloživost `dostupno` / `nedostupno`

Cjenik usluga sadrži naziv usluge, maloprodajnu cijenu, podatak o posebnom obliku prodaje i njegov naziv te sidrenu cijenu i referentni datum. Sidrena dodatno omogućuje unos vrste usluge, opsega usluge i pripadajućih troškova/napomene o cijeni radi tehničke podrške pravilima za cjenike usluga iz NN 105/2026.

Naziv generirane datoteke sadrži vrstu objekta, adresu, oznaku objekta, redni broj pohrane te datum i vrijeme generiranja. Sve lokacije koriste jednaku tehničku strukturu datoteka.

= Arhiva cjenika najmanje 30 dana =

Svaka uspješna objava ulazi u javnu arhivu kao zasebna datoteka. Prethodne objave ne prepisuju se.

* Minimalno razdoblje čuvanja je programski ograničeno na 30 dana.
* Zadana postavka je 45 dana radi operativne rezerve.
* Svaka arhivska stavka ima vlastiti datum do kojeg se čuva.
* Kasnije povećanje razdoblja čuvanja produljuje i postojeće zapise; prethodno obećani rok čuvanja ne skraćuje se.
* Trenutačno važeća datoteka ne briše se samo zato što je starija od arhivskog prozora, što je važno za usluge bez čestih promjena cijene.
* Arhivski indeks bilježi lokaciju, vrstu, format, vrijeme objave, broj redaka, veličinu i SHA-256 sažetak.
* Administracija provjerava postoje li indeksirane datoteke i odgovara li njihov SHA-256 zapis.
* Kartica „Arhiva 30+ dana“ prikazuje kalendar objava i rok čuvanja svake datoteke.
* Evidenciju arhive moguće je izvesti u CSV radi interne kontrole.
* Ako nova datoteka ne može biti generirana, prethodna uspješna datoteka za isti objekt/katalog/format ostaje aktualna.

Javna arhiva cjenika i povijest cijena za izračun najniže cijene u prethodnih 30 dana dvije su različite evidencije; Sidrena ih vodi odvojeno.

= Automatska objava i strojni dohvat =

* Zadano dnevno generiranje cjenika: 06:30 prema vremenskoj zoni WordPress stranice.
* Panel upozorava ako je dnevno vrijeme postavljeno na 08:00 ili kasnije.
* Promjene proizvoda i usluga mogu pokrenuti odgođeno ponovno generiranje.
* `[sidrena_cjenici]` prikazuje javne aktualne i arhivske datoteke.
* Javni REST indeks objavljenih datoteka: `/wp-json/sidrena/v1/cjenici`.
* Javni REST dohvat aktualnih maloprodajnih cijena u realnom vremenu: `/wp-json/sidrena/v1/cijene` uz parametre `type`, `location`, `page` i `per_page`.
* Opcionalni javni `manifest.json` daje strojno čitljiv indeks aktualnih i arhivskih datoteka, verziju generatora, razdoblje čuvanja, veličine i SHA-256 sažetke.

WordPress WP-Cron ovisi o prometu na webu i sam po sebi ne jamči izvršavanje točno u određenoj minuti. Za poslovno kritičnu objavu prije 08:00 preporučuje se pouzdan server cron koji redovito pokreće `wp-cron.php`.

= Najniža cijena u prethodnih 30 dana =

Sidrena lokalno prati promjene cijena WooCommerce proizvoda/varijacija i Sidrena usluga. Kada počne novo neprekinuto sniženje, dodatak pokušava zamrznuti provjerljivu referentnu cijenu iz 30 dana prije početka tog sniženja.

* Ako ne postoji poznato stanje cijene na početku 30-dnevnog prozora, status je „nepotpun“ umjesto izmišljene vrijednosti.
* Administrator može unijeti provjerenu ručnu vrijednost iz druge vjerodostojne evidencije.
* Za aktivna sniženja mogu se odvojeno prikazati aktualna snižena cijena, najniža cijena u prethodnih 30 dana i sidrena cijena.
* Za lako pokvarljivu robu i robu kojoj brzo istječe rok uporabe postoji zasebna oznaka izuzeća te polje „Krajnji rok uporabe“; kada je primjenjivo i proizvod je na sniženju, datum se prikazuje uz cijenu.
* Za usluge se mogu evidentirati posebne iznimke samo nakon provjere da se doista primjenjuju na konkretan slučaj.

= Više poslovnica i webshop =

Raspoloživost robe odnosi se na konkretnu fizičku lokaciju. Sidrena zato podržava lokacijski CSV uvoz s kolonama `location_id`, `product_id` ili `sku`, `price`, `anchor_price` i `availability`.

Vrijednost `availability` mora biti `dostupno` ili `nedostupno`. Prazna lokacijska cijena koristi osnovnu WooCommerce cijenu. Usluge također mogu imati različitu aktualnu i sidrenu cijenu po lokaciji.

= Usluge bez WooCommercea =

WooCommerce nije obavezan ako se Sidrena koristi samo za usluge. Dodatak ima vlastiti katalog usluga unutar administracije te shortcode `[sidrena_usluge]` za javni cjenik usluga.

= Moderan administracijski panel =

Sidrena ima vlastiti glavni WordPress izbornik pri dnu administracije, a ne stavku skrivenu u postavkama drugog plugina. Sučelje je podijeljeno u kartice:

* Pregled
* Cjenici
* Arhiva 30+ dana
* Lokacije
* Postavke
* Alati
* Propisi

Pregled prikazuje tehničke provjere sidrenih cijena, 30-dnevne reference, lokacijske raspoloživosti, termina generiranja, arhive i integriteta datoteka.

= Privatnost i sigurnost =

* Nema telemetrije ni praćenja korisnika.
* Nema udaljene aktivacije, licence, triala ili Pro paywalla.
* Nema obaveznog vanjskog API-ja.
* Administracijske radnje koriste WordPress capability i nonce provjere.
* Uvoz ograničava vrstu i veličinu datoteke te sanitizira podatke.
* CSV/XML se zapisuju preko privremene datoteke pa se tek nakon uspješnog zapisa zamjenjuju konačnom datotekom.
* Arhivske datoteke imaju SHA-256 sažetak za provjeru integriteta.

= Važna napomena o pravilima =

Odluke iz NN 101/2026 stupaju na snagu 1. listopada 2026. Izmjene Zakona o zaštiti potrošača iz NN 59/2026 imaju i odredbe s drugim datumima stupanja na snagu, uključujući dio novog članka 7. od 17. studenoga 2026. Sidrena te režime u dokumentaciji razlikuje i ne predstavlja jedan datum kao datum početka svih obveza.

Službeni izvori:

* https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html
* https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html
* https://mingo.gov.hr/vijesti/pojasnjenja-za-primjenu-dodatne-cijene-i-objavu-cjenika-od-1-listopada/10440
* https://narodne-novine.nn.hr/clanci/sluzbeni/2026_06_59_728.html

== Installation ==

1. U WordPress administraciji otvorite Dodaci > Dodaj novi > Prenesi dodatak.
2. Prenesite `sidrena-1.3.0.zip`, instalirajte i aktivirajte dodatak.
3. Otvorite glavni izbornik **Sidrena**.
4. U Postavkama odaberite proizvode, usluge ili mješoviti način rada.
5. U Lokacijama upišite svaki fizički objekt i, ako postoji, zasebni webshop.
6. Provjerite sidrene cijene i referentne datume. Povijesne vrijednosti uvezite samo iz vjerodostojne evidencije.
7. Kod fizičkih poslovnica uvezite raspoloživost i eventualne cijene po lokaciji.
8. Generirajte prvi cjenik te provjerite kartice Cjenici i Arhiva 30+ dana.
9. Ako termin prije 08:00 mora biti pouzdan, konfigurirajte server cron za redovito pokretanje WordPress crona.

== Frequently Asked Questions ==

= Je li Sidrena potpuno besplatna? =

Da. Verzija 1.3.0 nema Pro izdanje, licencni ključ, pretplatu, vremensko ograničenje ni udaljenu aktivaciju.

= Radi li bez WooCommercea? =

Da, za katalog usluga i cjenike usluga. Za cjenike proizvoda potreban je WooCommerce.

= Čuva li stvarno svaki objavljeni cjenik najmanje 30 dana? =

Sidrena svaku uspješnu generaciju sprema kao zasebnu javnu CSV/XML datoteku i ne dopušta postavku čuvanja kraću od 30 dana. Zadano je 45 dana. Trenutačno važeća datoteka dodatno se štiti od automatskog brisanja čak i ako je starija od tog prozora.

= Je li 30-dnevna arhiva cjenika isto što i najniža cijena prije sniženja? =

Ne. Javna arhiva čuva objavljene cjenike, dok se zasebna interna povijest koristi kao tehnička podloga za referentnu cijenu prije sniženja.

= Može li plugin automatski znati cijenu koja je vrijedila prije instalacije? =

Ne pouzdano. Ako WordPress/WooCommerce nema vjerodostojan povijesni zapis, Sidrena traži ručni unos ili CSV uvoz umjesto nagađanja.

= Mora li svaka poslovnica imati svoj cjenik? =

Prema službenom pojašnjenju Ministarstva od 22.09.2026., za više fizičkih lokacija objavljuje se zasebna datoteka po lokaciji, a webshop je zasebna datoteka. Sidrena je zato strukturirana po lokacijama.

= Jamči li aktivacija plugina zakonsku usklađenost? =

Ne. Plugin automatizira tehnički dio evidencije, prikaza i objave. Točnost podataka, primjenjivost iznimki, poslovni model i konačnu pravnu obvezu mora provjeriti sam poslovni subjekt ili odgovarajući stručnjak.

== Screenshots ==

1. Premium Sidrena pregled s tehničkim statusom i glavnim akcijama.
2. Upravljanje javnim CSV/XML cjenicima.
3. Arhiva 30+ dana s kalendarom, SHA-256 provjerom i rokovima čuvanja.
4. Upravljanje fizičkim lokacijama i zasebnim webshopom.
5. Moderne postavke s karticama, prekidačima i kontrolom automatske objave.
6. Alati za CSV uvoz sidrenih cijena i lokacijske raspoloživosti.

== Changelog ==

= 1.3.0 =
* Dodan javni REST endpoint za aktualne cijene u realnom vremenu (`/sidrena/v1/cijene`) kao tehnička podrška automatiziranom dohvatnom zahtjevu iz NN 101/2026.
* REST odgovor koristi `no-store/no-cache` zaglavlja kako bi automatizirani dohvat dobivao aktualno stanje, a ne zastarjelu predmemoriju.
* REST dohvat podržava proizvode, varijacije, usluge i odabir lokacije te poštuje lokacijske cijene, sidrene cijene i raspoloživost.
* Administracija sada jasno prikazuje zasebne URL-ove za indeks cjenika i aktualne cijene u realnom vremenu.
* Kartica Propisi dopunjena je detaljima službenog pojašnjenja Ministarstva gospodarstva od 22.09.2026., uključujući informativne web-stranice, profile na društvenim mrežama, proizvode/usluge bez unaprijed fiksne cijene i rokove ažuriranja.
* Dodatno pojašnjena razlika između javne 30+ dnevne arhive objavljenih cjenika i interne 30-dnevne povijesti za posebne oblike prodaje.

= 1.2.0 =
* Dodana evidencija vrste, opsega i pripadajućih troškova usluge te uključivanje tih podataka u javni prikaz i digitalni cjenik.
* Dodana provjera zakazanog dnevnog WP-Cron događaja i samopopravak rasporeda ako događaj nedostaje.
* Dodan izvoz evidencije arhive sa SHA-256 sažecima, datumom objave i obveznim datumom čuvanja.

* Novi premium Sidrena administracijski panel kao samostalni glavni WordPress izbornik pri dnu administracije.
* Arhiva svake uspješno objavljene CSV/XML datoteke uz minimalno 30 dana čuvanja i zadanu rezervu od 45 dana.
* Dodan trajni `retain_until` zapis po arhivskoj datoteci i zaštita trenutačno važećeg cjenika od prerane pohrane/brisanja.
* Povećanje postavke čuvanja produljuje postojeće arhivske zapise bez skraćivanja ranije obećanog roka.
* SHA-256 provjera integriteta javne arhive i manifest schema 2.
* Dodan CSV izvoz evidencije arhive za internu kontrolu.
* Kalendar objava za posljednjih 35 dana.
* Zadano automatsko generiranje pomaknuto na 06:30 radi rezerve prije 08:00.
* Upozorenje ako je automatsko generiranje postavljeno na 08:00 ili kasnije.
* Poboljšano zadržavanje prethodne aktualne datoteke kada pojedina nova generacija ne uspije.
* Podrška za krajnji rok uporabe kod označene lako pokvarljive robe/robe kojoj brzo istječe rok tijekom posebnog oblika prodaje.
* Dodatne tehničke provjere 30-dnevne reference, raspoloživosti po lokaciji, arhive i cjenika.
* Poboljšan WooCommerce rad s varijacijama, lokacijskim cijenama i sidrenim vrijednostima.
* Usklađena dokumentacija s NN 101/2026, službenim MINGO pojašnjenjima od 22.09.2026. i relevantnim odredbama NN 59/2026.
* Metapodaci: autor Brendigo, https://brendigo.com/, plugin web https://sidrena-cijena.com.hr/.
* Testirano do WordPress 7.1.2 i WooCommerce 11.1.2.

= 1.1.0 =

* Proširena podrška za sidrene cijene, 30-dnevnu povijest, usluge, lokacije, CSV/XML i javnu arhivu.

= 1.0.0 =

* Prvo javno izdanje Sidrena plugina.

== Upgrade Notice ==

= 1.3.0 =

Preporučena nadogradnja: dodaje javni dohvat aktualnih cijena u realnom vremenu, dodatno usklađuje panel sa službenim pojašnjenjem Ministarstva i zadržava 30+ dnevnu javnu arhivu.

= 1.2.0 =

Preporučena nadogradnja: poboljšava arhivu 30+ dana, integritet cjenika, kontrolu termina objave, WooCommerce prikaz i administracijski UI.
