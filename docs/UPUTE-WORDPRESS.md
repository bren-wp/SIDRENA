# Sidrena WordPress 0.1.0 - Upute za korištenje

## Namjena

Sidrena WordPress namijenjena je WordPress web stranicama koje **ne koriste WooCommerce kao izvor proizvoda**. Proizvode možete voditi u Sidrena katalogu ili povezati s postojećim javnim WordPress sadržajem.

## Instalacija

1. Prenesite `sidrena-wordpress-0.1.0.zip` u WordPress > Dodaci > Dodaj novi > Prenesi dodatak.
2. Aktivirajte **Sidrena WordPress**.
3. Otvorite **Sidrena** u lijevom admin meniju.
4. Otvorite **Sidrena > Katalog**.

## Najbrži način za postojeću web stranicu

Ako na stranici već imate proizvode ili drugi tip sadržaja koji predstavlja proizvode:

1. U **Sidrena > Katalog** otvorite karticu za automatsko povezivanje.
2. Odaberite postojeći javni tip sadržaja.
3. Ako znate meta ključ cijene, unesite ga; inače polje ostavite prazno.
4. Kliknite **Pokreni sinkronizaciju**.
5. Sidrena obrađuje zapise u batchovima od 100.
6. Nakon povezivanja provjerite naziv i aktualnu cijenu.
7. Dopunite **sidrenu cijenu**, datum, marku, barkod i jediničnu cijenu kada su primjenjivi.

Sidrena pokušava prepoznati uobičajene meta ključeve cijene kao što su `_price`, `price`, `cijena`, `product_price`, `_regular_price` i `regular_price`.

## Automatski prikaz sidrene cijene

Povezanom WordPress zapisu Sidrena automatski dodaje sidrenu cijenu na javnoj pojedinačnoj stranici. Nije potrebno ručno umetati shortcode za svaki povezani proizvod.

Za posebne rasporede i page buildere možete koristiti:

`[sidrena_cijena id="s123"]`

gdje je `123` ID Sidrena proizvoda.

## Ručni katalog

Ako ne želite povezivati postojeći sadržaj, proizvode možete:
- dodati ručno u Sidrena katalog
- uvesti CSV/XML datotekom
- uređivati u tabličnom prikazu

## Usluge

Usluge se vode zasebno i mogu se prikazivati na javnom cjeniku zajedno s proizvodima ili kao poseban cjenik usluga.

## Lokacije

Za svaku aktivnu lokaciju/webshop Sidrena generira zasebnu objavu prema konfiguraciji. Provjerite:
- oznaku
- vrstu objekta
- adresu
- aktivnost lokacije
- redni broj objave

## Objava cjenika na web stranici

U **Sidrena > Cjenici** kliknite **Izradi stranicu Objava cjenika**.

Plugin objavljuje WordPress stranicu sa shortcodeom:

`[sidrena_cjenici]`

Dostupni su i zasebni prikazi:

`[sidrena_cjenik]`

`[sidrena_arhiva]`

`[sidrena_usluge]`

## Automatska dnevna objava

Zadano vrijeme generiranja je **06:30**.

Sidrena:
- zakazuje dnevno generiranje
- nakon spremanja bitnih podataka stavlja ponovno generiranje u red
- ima sigurnosnu provjeru koja nakon planiranog vremena provjerava postoji li današnja objava i po potrebi pokreće novu generaciju

WordPress WP-Cron ovisi o izvršavanju WordPressa. Za pouzdano izvršavanje prije poslovno kritičnog roka konfigurirajte server cron koji redovito pokreće WordPress cron.

## Arhiva

Prethodne uspješne CSV/XML objave ostaju javno dostupne prema postavljenom razdoblju čuvanja, najmanje 30 dana.

## WP-CLI

`wp sidrena generate`

`wp sidrena status`

`wp sidrena audit`

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
- stvarnu aktualnu cijenu
- stvarnu sidrenu/referentnu cijenu i datum
- marku, šifru i barkod
- jediničnu cijenu kada je primjenjiva
- dostupnost
- javni HTML prikaz
- CSV/XML datoteke
- arhivu
- Dnevnik

Sidrena je tehnički alat. Povijesne i referentne cijene moraju odgovarati stvarnoj poslovnoj evidenciji.
