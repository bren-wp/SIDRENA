# SIDRENA Desktop za Windows

![SIDRENA](branding/sidrena-banner.svg)

**Vaše cijene. Vaša evidencija. Vaša kontrola.**

SIDRENA Desktop **0.10.5** je Windows aplikacija Brendigo LTD za upravljanje proizvodima, uslugama, trenutačnim i sidrenim cijenama. Tamnoplavi izgled s narančastim detaljima prilagođava se većim i manjim prozorima.

## Funkcionalnosti

- Upravljanje lokalnim katalozima i uslugama uz brzu pretragu.
- Promjene cijena s evidencijom vremena i razloga.
- Sidrene cijene s dokumentiranim datumom i izvorom.
- Priprema i pregled CSV/XML cjenika.
- Poslovnice, arhiva, izvještaji i sigurnosne kopije.
- Pregled javnih WordPress cjenika putem HTTPS-a.
- Oporavak lokalnih podataka uz sigurnosne provjere.

## Preuzimanje

[**Windows Setup.exe**](https://github.com/bren-wp/SIDRENA/releases) · [**Windows Portable.exe**](https://github.com/bren-wp/SIDRENA/releases)

Obje verzije koriste isti direktorij korisničkih podataka u sustavu Windows. Pri ažuriranju ili deinstalaciji lokalna evidencija ne briše se automatski.

## WordPress povezivanje

Windows pregled koristi **javne objave** WordPress dodatka **Brendigo Sidrena Cijena 1.5.2**. Lokalni Windows katalog ne objavljuje se automatski na WordPressu.

## Upute i dokumentacija

[Izdanja](https://github.com/bren-wp/SIDRENA/releases) · [Instalacija](installer/README.md) · [Pomoć](mailto:sidrena@brendigo.com) · [Licencija](legal/EULA-HR.txt)

**SIDRENA by Brendigo**

## Sinkronizacija javnih pravnih pravila

Windows 0.10.5 prepoznaje `plugin_version` i javni `legal` profil WordPress dodatka 1.5.2. Datumi primjene i arhiviranja preuzimaju se iz istog ugovora kao u Android aplikaciji. Lokalni katalog ostaje odvojena baza i ne mijenja WordPress bez izričite autorizirane integracije.

[Pravni okvir i interoperabilnost](../docs/LEGAL-2026-INTEROPERABILITY.md) · [Security i dead-code audit](../docs/SECURITY-DEADCODE-AUDIT-2026-10-24.md)

## Ručna razmjena cjenika s WordPressom

U izborniku **Cjenici** opcija **CSV za uvoz u WordPress** izrađuje datoteku s 15 stupaca i nazivima koje prepoznaje službeni WordPress SIDRENA uvoznik. Uvoz se zatim izvodi samo u WordPress administraciji (**SIDRENA → Uvoz cjenika**), uz pregled mapiranja i izričitu potvrdu. Dodatna polja koja ne postoje u Windows lokalnoj bazi ostaju prazna; nijedna povijesna cijena, barkod ili dostupnost ne izmišljaju se.

**Oprez:** pri potvrđivanju u WordPressu postojeće stavke mogu biti ažurirane prema SKU-u i pravilima uvoznika. Prije uvoza provjerite sigurnosnu kopiju, obuhvat i podatke. Ovo nije automatska sinkronizacija ili automatska javna objava.
