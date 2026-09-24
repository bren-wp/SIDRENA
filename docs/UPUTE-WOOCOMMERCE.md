# Sidrena WooCommerce 0.1.0 - Upute za korištenje

## Namjena

Sidrena WooCommerce namijenjena je WordPress trgovinama s aktivnim WooCommerceom. WooCommerce proizvodi i varijacije ostaju jedini izvor proizvoda - Sidrena ne izrađuje dupli katalog.

## Instalacija

1. Instalirajte i aktivirajte WooCommerce.
2. Prenesite `sidrena-woocommerce-0.1.0.zip`.
3. Aktivirajte **Sidrena WooCommerce**.
4. Otvorite **Sidrena** u lijevom admin meniju.

## Postojeći WooCommerce proizvodi

Nije potrebno ponovno unositi proizvode.

1. Otvorite postojeći WooCommerce proizvod.
2. U Sidrena polja unesite sidrenu cijenu.
3. Odaberite referentnu skupinu ili datum.
4. Dopunite šifru, marku, barkod i podatke za jediničnu cijenu kada nedostaju.
5. Spremite proizvod.

Za varijabilni proizvod podaci se mogu voditi po varijaciji.

## Automatski prikaz sidrene cijene

Kada je sidrena cijena unesena i prikaz uključen, Sidrena je automatski dodaje uz WooCommerce cijenu.

Automatski prikaz pokriva:
- pojedinačni proizvod
- shop i produktne arhive gdje se koristi WooCommerce price HTML
- varijacije
- WooCommerce product-price blokove
- podržane prikaze Elementor, Divi, Bricks, Beaver Builder, Avada/Fusion i druge kompatibilne price elemente

Za posebne rasporede ostaje dostupan shortcode:

`[sidrena_cijena id="123"]`

## Povijest cijena

WooCommerce izdanje prati dostupnu povijest cijena i promjene proizvoda/varijacija. Povijest se ne rekonstruira iz podataka koji ne postoje.

Kod posebnih oblika prodaje 30-dnevna referenca prikazuje se kada plugin ima dovoljno podataka ili kada je unesena provjerena ručna vrijednost.

## WooCommerce CSV

Sidrena polja integrirana su u WooCommerce CSV Import/Export.

## Lokacije

Za fizičke lokacije možete voditi:
- raspoloživost
- cijenu po lokaciji
- sidrenu cijenu po lokaciji

Svaka aktivna lokacija/webshop dobiva odgovarajuću javnu objavu prema konfiguraciji.

## Usluge

Sidrena usluge rade i u WooCommerce izdanju kao zaseban katalog usluga.

## Objava cjenika na web stranici

U **Sidrena > Cjenici** kliknite **Izradi stranicu Objava cjenika**.

Kompletni javni prikaz koristi:

`[sidrena_cjenici]`

Dostupni su i:

`[sidrena_cjenik]`

`[sidrena_arhiva]`

`[sidrena_usluge]`

## Automatska dnevna objava

Zadano vrijeme generiranja je **06:30**.

Sidrena:
- zakazuje dnevno generiranje
- nakon promjene Sidrena podataka ili relevantne cijene stavlja ponovno generiranje u red
- ima sigurnosnu provjeru današnje objave nakon konfiguriranog vremena

Za pouzdano izvršavanje prije poslovno kritičnog roka koristite server cron koji redovito pokreće WordPress cron.

## Arhiva

Prethodne uspješne CSV/XML objave ostaju javno dostupne prema postavljenom razdoblju čuvanja, najmanje 30 dana.

## WP-CLI

`wp sidrena generate`

`wp sidrena status`

`wp sidrena audit`

WooCommerce izdanje dodatno ima Woo-specifične WP-CLI naredbe kada su registrirane.

## Podrška

- E-mail: **sidrena@brendigo.com**
- WhatsApp: **+385 91 901 0092**
- Instalacija i početno postavljanje: **80 EUR jednokratno**
- PDF: `docs/SIDRENA-PODRSKA.pdf`
- Developer: **Brendigo LTD Developer**

## Donacija

Izravni Revolut gumb nalazi se u Sidrena administraciji.

## Licenca

Sidrena se koristi prema **Sidrena Software License 1.0**. Plugin se ne smije prodavati, preprodavati, sublicencirati, redistribuirati, white-labelati ili rebrandirati bez pisanog odobrenja Brendigo LTD.

Puni tekst licence nalazi se u datoteci `LICENSE`.

## Prije produkcijske objave

Provjerite:
- stvarne WooCommerce cijene
- sidrene/referentne cijene i datume
- podatke za jediničnu cijenu
- raspoloživost po lokacijama
- automatski prikaz uz proizvod
- javni HTML prikaz
- CSV/XML datoteke
- arhivu
- Dnevnik

Sidrena je tehnički alat. Povijesne i referentne cijene moraju odgovarati stvarnoj poslovnoj evidenciji.
