# SIDRENA Desktop za Windows

![SIDRENA](branding/sidrena-banner.svg)

**Vaše cijene. Vaša evidencija. Vaša kontrola.**

SIDRENA Desktop **0.10.3** je Windows aplikacija Brendigo LTD za upravljanje proizvodima, uslugama, trenutačnim i sidrenim cijenama. Tamnoplavi izgled s narančastim detaljima prilagođava se većim i manjim prozorima.

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

Windows pregled koristi **javne objave** WordPress dodatka **Brendigo Sidrena Cijena 1.5.1**. Lokalni Windows katalog ne objavljuje se automatski na WordPressu.

## Upute i dokumentacija

[Izdanja](https://github.com/bren-wp/SIDRENA/releases) · [Instalacija](installer/README.md) · [Pomoć](mailto:sidrena@brendigo.com) · [Licencija](legal/EULA-HR.txt)

**SIDRENA by Brendigo**

## Sinkronizacija javnih pravnih pravila

Windows 0.10.3 prepoznaje `plugin_version` i javni `legal` profil WordPress dodatka 1.5.1. Datumi primjene i arhiviranja preuzimaju se iz istog ugovora kao u Android aplikaciji. Lokalni katalog ostaje odvojena baza i ne mijenja WordPress bez izričite autorizirane integracije.

[Pravni okvir i interoperabilnost](../docs/LEGAL-2026-INTEROPERABILITY.md) · [Security i dead-code audit](../docs/SECURITY-DEADCODE-AUDIT-2026-10-24.md)
