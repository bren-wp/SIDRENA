=== Sidrena WooCommerce ===
Contributors: brendigo
Donate link: https://revolut.me/catanyus?currency=EUR&amount=1000&note=Sidrena%20WordPress%20plugin%20-%20donacija
Tags: woocommerce, cijene, cjenik, csv, xml, hrvatska, ecommerce
Requires at least: 6.6
Tested up to: 7.1.2
Requires PHP: 7.4
Stable tag: 0.8.0
License: Sidrena Software License 1.0
License URI: https://github.com/bren-wp/SIDRENA/blob/main/LICENSE

Sidrena WooCommerce automatski prikazuje sidrenu cijenu uz WooCommerce cijenu i objavljuje javne CSV/XML cjenike s arhivom.

Autor: Brendigo
Plugin URI: https://sidrene-cijene.com.hr/
Author URI: https://brendigo.com/
Support: sidrena@brendigo.com

== Description ==

= Sidrena WooCommerce 0.8.0 =

Sidrena WooCommerce koristi WooCommerce proizvode i varijacije kao jedini izvor proizvoda.

🛒 Postojeći WooCommerce katalog ostaje glavni katalog
* nema duplog unosa proizvoda
* otvorite postojeći WooCommerce proizvod
* unesite sidrenu cijenu
* Sidrena je automatski prikazuje uz WooCommerce cijenu

⚓ Automatski prikaz
* proizvodi i varijacije
* shop/arhive i pojedinačni proizvod
* WooCommerce price HTML
* WooCommerce blokovi
* podržani page builder prikazi
* pristupačni tooltip uz sidrenu cijenu

📈 Povijest cijena
* praćenje promjena cijena
* 30-dnevna referenca kada postoji dovoljna evidencija
* podrška za varijacije

🏬 Lokacije
* cijena i raspoloživost po lokaciji
* zaseban cjenik po aktivnoj lokaciji/webshopu
* uvoz lokacijskih podataka

🌐 Objava cjenika
* javna stranica Objava cjenika
* shortcode [sidrena_objava_cjenika]
* pretraživi HTML cjenik
* server-side pretraga cijelog cjenika i paginacija za velike kataloge
* javna arhiva grupirana po datumu
* CSV/XML i REST

⏰ Automatizacija
* zadano dnevno generiranje u 06:30
* sigurnosna provjera propuštene objave
* compliance watchdog obnavlja sigurne tehničke postavke, cronove, javnu stranicu i upload zaštitu
* lokalni audit log ima limite, pruning i throttling ponavljajućih zapisa
* za precizno izvršavanje preporučuje se server cron

🛟 Podrška
* sidrena@brendigo.com
* WhatsApp +385 91 901 0092
* PDF podrška u plugin paketu
* instalacija i početno postavljanje: 80 EUR jednokratno

❤️ Donacija
Izravna Revolut donacija dostupna je u Sidrena administraciji.

Ovo izdanje zahtijeva WooCommerce. Za web bez WooCommercea koristite Sidrena WordPress. Istodobno može biti aktivno samo jedno izdanje.

Autor: Brendigo
Službena stranica: https://sidrene-cijene.com.hr/

== Support and optional services ==

Plugin možete instalirati i postaviti sami.

* opcionalno jednokratno postavljanje od strane Brendiga: 80 EUR
* e-mail: sidrena@brendigo.com
* WhatsApp: +385 91 901 0092
* dobrovoljna donacija za razvoj: izravni Revolut link

== Installation ==

1. Instalirajte i aktivirajte WooCommerce.
2. Prenesite sidrena-woocommerce-0.8.0.zip.
3. Aktivirajte Sidrena WooCommerce.
4. Otvorite postojeći WooCommerce proizvod i unesite Sidrena podatke.
5. Provjerite automatski prikaz sidrene cijene.
6. Provjerite Lokacije.
7. U Cjenicima generirajte prvu objavu.
8. Izradite stranicu Objava cjenika.
9. Provjerite javni cjenik, arhivu i mobilni prikaz.

Detaljne upute i PDF podrška nalaze se u docs/.

== Frequently Asked Questions ==

= Moram li ručno dodavati shortcode uz WooCommerce proizvod? =
Ne. Kada je sidrena cijena unesena i prikaz uključen, Sidrena je automatski dodaje uz WooCommerce cijenu.

= Hoće li se proizvod duplirati u Sidrena katalog? =
Ne. WooCommerce izdanje koristi postojeće WooCommerce proizvode i varijacije.

= Objavljuje li cjenik automatski? =
Da, WordPress raspored generira cjenik u konfigurirano vrijeme, zadano 06:30. Sidrena ima i sigurnosnu provjeru propuštene dnevne objave. Compliance watchdog provjerava ključne tehničke postavke i raspored. Za precizno izvršavanje preporučuje se server cron.

= Smijem li prodavati ili preprodavati plugin? =
Ne. Korištenje je dopušteno prema Sidrena Software License 1.0, ali prodaja, preprodaja, sublicenciranje, redistribucija i rebrandiranje nisu dopušteni bez pisanog odobrenja Brendigo.

== Changelog ==

= 0.8.0 =
* Windows aplikacija proširena je iz jednog shell ekrana u strukturirani WinUI 3 workspace s Views, ViewModels, Models i Services slojevima.
* Dodane su stranice Dashboard, Katalog, Import/Export, Legal readiness i WordPress sync.
* Dodani su lokalni servisi za demo katalog, tehničku provjeru, CSV/XML import preview, CSV/XML/HTML export i WordPress sync preview bez mrežnog slanja.
* Pojačani su Windows smoke guard i dokumentacija za native Windows 11 / Fluent smjer.

= 0.7.0 =
* Dodana je nativna Windows aplikacija pod /windows, temeljena na WinUI 3 / Windows App SDK, bez Electrona, Taurija, WebView2 shell pristupa i legacy WinForms/WPF UI-ja.
* Dodan je Windows app guard koji provjerava native-only strukturu i stvarni dotnet build na windows-latest runneru.
* Dodana je release automatizacija za Windows assete: portable.exe i setup.exe uz postojeća dva plugin ZIP paketa.
* Windows dokumentacija opisuje lokalni katalog, legal-readiness provjeru, CSV/XML/HTML export i buduću sigurnu sinkronizaciju prema Sidrena pluginu.

= 0.6.0 =
* Dodan je distribution guard koji čuva samo dva instalacijska ZIP-a i blokira generičke/root pakete.
* Dodan je legal/compliance watchdog za sigurne automatske popravke javne stranice, upload direktorija, ključnih postavki i cronova.
* Audit log ima produkcijske limite, pruning i throttling kako ne bi rastao bez kontrole.
* Legal automation guard čuva referentne datume, strict publication, failure notifications i hourly publication watch.

= 0.4.0 =
* Usluge koriste bounded server-side paginaciju; admin audit usluga obrađuje podatke u batchovima.
* CSV/XML import provjerava row-limit prije promjena i smanjuje memorijski pritisak streaming obradom.
* Preveliki Woo CSV više ne završava djelomičnim importom uz success rezultat.
* Release build je reproducibilan, a rerun postojećeg releasea ne overwritea assete.

= 0.3.0 =
* Javna arhiva cjenika grupirana je po datumima objave.
* Aktualne datoteke više se ne dupliciraju među prethodnim objavama.
* Arhivske kartice jasno prikazuju format, naziv datoteke, lokaciju/datum i akciju Preuzmi.
* Poboljšan je mobilni i tipkovnički prikaz dnevnih arhivskih grupa.

= 0.2.0 =
* Streaming JSONL snapshot za javni HTML cjenik.
* Server-side pretraga cijelog cjenika i paginacija bez učitavanja cijelog kataloga u memoriju.
* Poboljšana pristupačnost WooCommerce tooltipa i stabilnost objave.
* Batch obrada povijesti lokacijskih cijena za velike kataloge.
* Transakcijsko generiranje čuva prethodnu valjanu objavu ako novi format ili snapshot ne prođe.
* REST paginacija pravilno broji WooCommerce varijacije i ne prelazi traženi per_page.
* Strict validacija je ugrađena u streaming writere bez dodatnog punog preflight prolaza.

= 0.1.0 =
* Prvo javno izdanje.
* Automatski prikaz sidrene cijene uz WooCommerce cijenu.
* WooCommerce proizvodi, varijacije, povijest cijena i lokacije.
* CSV/XML, REST, Objava cjenika i arhiva 30+ dana.
* Automatska dnevna objava i sigurnosna provjera propuštenog rasporeda.
* Ugrađena PDF, e-mail i WhatsApp podrška.
