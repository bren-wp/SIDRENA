=== Sidrena WordPress ===
Contributors: brendigo
Donate link: https://revolut.me/catanyus?currency=EUR&amount=1000&note=Sidrena%20WordPress%20plugin%20-%20donacija
Tags: cijene, cjenik, csv, xml, hrvatska, trgovina, usluge
Requires at least: 6.6
Tested up to: 7.1.2
Requires PHP: 7.4
Stable tag: 0.3.0
License: Sidrena Software License 1.0
License URI: https://github.com/bren-wp/SIDRENA/blob/main/LICENSE

Sidrena WordPress povezuje postojeći WordPress sadržaj ili vlastiti katalog sa sidrenim cijenama, javnim cjenicima i arhivom objava.

Autor: Brendigo
Plugin URI: https://sidrene-cijene.com.hr/
Author URI: https://brendigo.com/
Support: sidrena@brendigo.com

== Description ==

= Sidrena WordPress 0.3.0 =

Sidrena WordPress namijenjena je web stranicama koje ne koriste WooCommerce kao izvor proizvoda.

⚓ Automatski prikaz sidrene cijene
* povežite postojeći javni WordPress tip sadržaja s Sidrena katalogom
* Sidrena pokušava prepoznati postojeće polje cijene
* nakon povezivanja prati promjene naziva i cijene
* sidrena cijena automatski se prikazuje na povezanoj javnoj stranici

📦 Katalog i uvoz
* vlastiti WordPress katalog proizvoda
* CSV/XML uvoz
* proizvodi, usluge, marka, šifra, barkod i dostupnost
* jedinična cijena i podaci o pakiranju

🌐 Objava cjenika
* javna stranica Objava cjenika
* shortcode [sidrena_objava_cjenika]
* pretraživi HTML cjenik
* server-side pretraga cijelog cjenika i paginacija za velike kataloge
* javna arhiva prethodnih objava grupirana po datumu
* CSV/XML i REST pristup

⏰ Automatizacija
* zadano dnevno generiranje u 06:30
* sigurnosna provjera ponovno pokreće propuštenu objavu nakon planiranog vremena
* za pouzdano izvršavanje poslovno kritičnog termina preporučuje se server cron

🛟 Podrška
* sidrena@brendigo.com
* WhatsApp +385 91 901 0092
* PDF podrška u plugin paketu
* instalacija i početno postavljanje: 80 EUR jednokratno

❤️ Donacija
Izravna Revolut donacija dostupna je u Sidrena administraciji.

Ako web koristi WooCommerce proizvode, instalirajte zasebni Sidrena WooCommerce paket. Istodobno može biti aktivno samo jedno Sidrena izdanje.

Autor: Brendigo
Službena stranica: https://sidrene-cijene.com.hr/

== Support and optional services ==

Plugin možete instalirati i postaviti sami.

* opcionalno jednokratno postavljanje od strane Brendiga: 80 EUR
* e-mail: sidrena@brendigo.com
* WhatsApp: +385 91 901 0092
* dobrovoljna donacija za razvoj: izravni Revolut link

== Installation ==

1. Prenesite sidrena-wordpress-0.3.0.zip.
2. Aktivirajte Sidrena WordPress.
3. Otvorite Sidrena > Katalog.
4. Povežite postojeći WordPress tip sadržaja ili unesite/uvezite proizvode.
5. Dopunite sidrene cijene i obvezne podatke.
6. Provjerite Lokacije.
7. U Cjenicima generirajte prvu objavu.
8. Izradite stranicu Objava cjenika.
9. Provjerite automatski prikaz sidrene cijene i javnu arhivu.

Detaljne upute i PDF podrška nalaze se u docs/.

== Frequently Asked Questions ==

= Moram li ručno dodavati shortcode uz svaki proizvod? =
Ne kada je proizvod povezan s postojećim WordPress sadržajem. Sidrena automatski dodaje sidrenu cijenu na povezanu javnu stranicu.

= Može li Sidrena povući postojeće proizvode? =
Da. U Katalogu odaberite postojeći javni tip sadržaja i po potrebi meta ključ cijene. Sinkronizacija se obrađuje u batchovima.

= Objavljuje li cjenik automatski? =
Da, WordPress raspored generira cjenik u konfigurirano vrijeme, zadano 06:30. Sidrena ima i sigurnosnu provjeru propuštene dnevne objave. Za precizno izvršavanje preporučuje se server cron.

= Smijem li prodavati ili preprodavati plugin? =
Ne. Korištenje je dopušteno prema Sidrena Software License 1.0, ali prodaja, preprodaja, sublicenciranje, redistribucija i rebrandiranje nisu dopušteni bez pisanog odobrenja Brendigo.

== Changelog ==

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
* Strict validacija je ugrađena u streaming writere bez dodatnog punog preflight prolaza.

= 0.1.0 =
* Prvo javno izdanje.
* WordPress katalog i povezivanje postojećeg WordPress sadržaja.
* Automatski prikaz sidrene cijene na povezanoj javnoj stranici.
* CSV/XML, REST, Objava cjenika i arhiva 30+ dana.
* Automatska dnevna objava i sigurnosna provjera propuštenog rasporeda.
* Ugrađena PDF, e-mail i WhatsApp podrška.
