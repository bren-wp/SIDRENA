<!--
Sidrena source file.
Author: Brendigo
Author URI: https://brendigo.com/
Plugin URI: https://sidrene-cijene.com.hr/
Support: sidrena@brendigo.com
-->

<p align="center">
  <img src="assets/images/logo-horizontal.svg" alt="Sidrena" width="420">
</p>

<p align="center">
  <strong>Sidrene cijene, javni cjenici i arhiva objava za WordPress i WooCommerce.</strong><br>
  Brendigo · verzija 0.2.0
</p>

<p align="center">
  ⚓ Sidrena WordPress · 🛒 Sidrena WooCommerce · 🌐 Objava cjenika · 🛟 Podrška
</p>

![Sidrena 0.2.0](docs/media/readme-hero.svg)

# Sidrena 0.2.0

Sidrena se isporučuje kao **dva zasebna WordPress plugina**. Instalira se samo izdanje koje odgovara web stranici.

| Izdanje | Namjena | Izvor proizvoda |
|---|---|---|
| ⚓ **Sidrena WordPress** | WordPress bez WooCommercea | Sidrena katalog ili povezani postojeći WordPress sadržaj |
| 🛒 **Sidrena WooCommerce** | WordPress + WooCommerce | postojeći WooCommerce proizvodi i varijacije |

> **Važno:** dva Sidrena izdanja ne smiju biti aktivna istodobno. Plugin ima ugrađenu zaštitu od konflikta.

## ✨ Glavne mogućnosti

- ⚓ sidrena/referentna cijena i datum
- 🧾 aktualna i primjenjiva jedinična cijena
- 📦 CSV/XML javni cjenici
- 🌐 javna stranica **Objava cjenika**
- 🔎 pretraživi HTML cjenik
- 🗂 arhiva prethodnih objava 30+ dana
- 🔄 REST/automatizirani pristup
- 🏬 više lokacija i webshop kao zaseban objekt
- ⏰ dnevno automatsko generiranje, zadano u 06:30
- 🧯 sigurnosna provjera propuštene dnevne objave
- ✉️ ograničena e-mail upozorenja kod neuspjele/zakašnjele objave
- 🏢 opcionalni javni podaci obrta/tvrtke s OIB provjerom
- 🛟 ugrađena PDF, e-mail i WhatsApp podrška

![Sidrena administracija](docs/media/readme-dashboard.svg)

## ⚓ Sidrena WordPress

Sidrena WordPress može povezati postojeći javni WordPress tip sadržaja s vlastitim katalogom.

1. Otvorite **Sidrena → Katalog**.
2. Odaberite tip sadržaja koji predstavlja postojeće proizvode.
3. Po potrebi unesite meta ključ postojeće cijene.
4. Pokrenite sinkronizaciju.
5. Dopunite sidrenu cijenu i podatke koji nedostaju.

Sinkronizacija se obrađuje u batchovima od 100 zapisa. Nakon povezivanja Sidrena prati promjene izvornog naziva i prepoznate cijene.

Na povezanoj javnoj stranici Sidrena automatski dodaje sidrenu cijenu. Shortcode `[sidrena_cijena]` ostaje dostupan za posebne rasporede.

## 🛒 Sidrena WooCommerce

WooCommerce ostaje jedini katalog proizvoda - nema dupliciranja.

1. Otvorite postojeći WooCommerce proizvod ili varijaciju.
2. Unesite sidrenu cijenu i referentne podatke.
3. Spremite proizvod.
4. Sidrena automatski prikazuje sidrenu cijenu uz WooCommerce cijenu.

Compatibility layer pokriva standardni WooCommerce prikaz cijene, varijacije, WooCommerce blokove i više popularnih buildera.

![Sidrena mogućnosti](docs/media/readme-features.svg)

## 🌐 Objava cjenika

Kompletna javna stranica koristi shortcode:

```text
[sidrena_objava_cjenika]
```

Prikaz može uključiti podatke obrta/tvrtke, aktualni pretraživi cjenik, datoteke za preuzimanje i arhivu.

Dostupni su i zasebni prikazi:

```text
[sidrena_cjenici]
[sidrena_cjenik]
[sidrena_arhiva]
[sidrena_cijena]
[sidrena_usluge]
```

## ⏰ Automatizirana dnevna objava

Zadano vrijeme generiranja je **06:30**. Ako je planirano vrijeme prošlo, a današnja objava nije evidentirana, sigurnosna provjera stavlja novu generaciju u red.

WordPress WP-Cron ovisi o izvršavanju WordPressa. Za poslovno kritičan termin preporučuje se pouzdan **server cron**.

![Kako Sidrena radi](docs/media/readme-flow.svg)

## 🛟 Podrška

| Kanal | Podatak |
|---|---|
| 📧 E-mail | **sidrena@brendigo.com** |
| 💬 WhatsApp | **+385 91 901 0092** |
| 🧰 Opcionalno jednokratno postavljanje | **80 EUR jednokratno**, samo ako želite da Brendigo sve postavi |
| ❤️ Donacija za razvoj | dobrovoljna, izravno preko Revoluta; nije naknada za uslugu |
| 👤 Autor | **Brendigo** |

PDF podrška uključena je u oba ZIP-a kao `docs/SIDRENA-PODRSKA.pdf`.

**Važno:** plugin možete instalirati, postaviti i održavati sami. Cijena 80 EUR odnosi se na jednokratnu instalaciju i početno postavljanje kada tu uslugu želite naručiti. Donacija je dobrovoljna i odvojena od usluge instalacije.

## 📥 Release 0.2.0

Objavljuju se samo:

- `sidrena-wordpress-0.2.0.zip`
- `sidrena-woocommerce-0.2.0.zip`

## 🔐 Licenca

Sidrena nije open-source projekt.

Korištenje je dopušteno prema **Sidrena Software License 1.0**. Nije dopušteno prodavati, preprodavati, sublicencirati, redistribuirati, white-labelati ili rebrandirati Sidrenu bez pisanog odobrenja Brendigo.

Puni tekst: [LICENSE](LICENSE)

## ⚖️ Tehnička podrška propisima

Sidrena 0.2.0 tehnički prati provjerene zahtjeve iz NN 101/2026 (dodatna cijena i objava cjenika), NN 105/2026 (maloprodajna/jedinična cijena i usluge) te službenih pojašnjenja Ministarstva gospodarstva od 22.09.2026.

Plugin podržava obvezni skup podataka, CSV/XML objavu, zasebne lokacije/webshop, najmanje 30 dana javne arhive, referentne datume i automatizirani dohvat aktualnih cijena. Referentne i povijesne cijene moraju odgovarati stvarnoj poslovnoj evidenciji korisnika.

Softver **ne predstavlja automatsku pravnu potvrdu konkretnog poslovanja**; primjenjivost obveza ovisi o stvarnim proizvodima/uslugama, poslovnom modelu i podacima koje korisnik unese.

## 🧪 Razvoj i provjera

CI provjerava PHP 7.4, 8.3 i 8.4, odvojeni runtime oba izdanja, međusobni konflikt, siguran uninstall, WP-CLI površinu i stvarne build staging pakete.

```bash
./tools/build-editions.sh 0.2.0 /tmp/sidrena-build
```

## Brendigo

Službena stranica: https://sidrene-cijene.com.hr/  
Podrška: sidrena@brendigo.com
