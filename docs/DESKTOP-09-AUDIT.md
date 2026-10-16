# SIDRENA Desktop 0.9 — pregled i audit koda

## Opseg

U ovoj razvojnoj fazi pregledani su desktop C# izvori, WPF XAML,
katalog, obrasci, arhiva, javni WordPress API, instalacijski čarobnjak,
licencijske obavijesti i Windows distribucija.

### Potvrđeni zahvati

- Uklonjen je nepotrebni privatni ComboBox helper Selected.
- User-facing sučelje ne prikazuje punu putanju do interne baze,
  SQLite terminologiju ni nepostojeće kanale sinkronizacije.
- Osjetljivi interni datotečni podaci i dalje su pohranjeni kako bi
  radili sigurnosna kopija, povrat, audit i zaštita korisničkih podataka.
- Prikaz licence dostupan je iz Postavki i Pomoći, tekst je ugrađen
  u samu WPF aplikaciju i uz instalacijski paket.
- Instalacijski čarobnjak sadrži vidljivu obveznu licencijsku stranicu
  za interaktivne instalacije, ikonu, originalnu grafiku, tvrtku i verziju.
- CI prije builda generira originalne Windows icon/bitmap resurse,
  a kasnije provjerava stvarni Setup i Portable te njihovo pokretanje.

### Trajna provjera

Skripta desktop/branding/build_assets.py bez vanjskih paketa generira
ICO i slike instalatera. Skripta tools/audit_desktop.py pregledava
private metode u izvornim C# i XAML datotekama te javlja one koje se
nigdje drugdje u tekstu projekta ne koriste.

Ovo je **statička preliminarna provjera**, ne potpun Roslyn semantički
call-graph audit. XAML binding, reflection, delegati, ovisnosti trećih
strana i putanje izvođenja zahtijevaju dodatne provjere; ne brišu se
automatski bez razumijevanja pozivatelja. Full dead code audit u
strogom smislu još nije zaključen.

### Pravni i produkcijski blokatori

Izrada opširne EULA licence nije pravno mišljenje niti sama po sebi
uspostavlja ekskluzivna prava na ranije GPL objavljen kod. Prije
komercijalne prodaje treba provjeriti identitet i sjedište Brendigo LTD,
autorska prava suradnika, ranije GPL potpore, kompletne licence NuGet
ovisnosti, modele naplate, dokumentaciju o privatnosti, potrošačka prava
i obveze održavanja. Interaktivno prihvaćanje u instalateru ne zamjenjuje
pravila koja vrijede pri sklapanju ugovora o kupnji.

Zakonski verificirani cjenici, automatska javna objava i dvosmjerna
autorizirana WordPress sinkronizacija nisu aktivirani ovom izmjenom.

### Izdavanje

Jedini GitHub Release/tag: 1.5.0 (WordPress dodatak 1.5.0).
Windows Desktop ima vlastitu verziju i pri zelenom main CI-ju
osvježava samo portable/setup, SHA-256 i manifest. WordPress paket
i njegov SHA-256 ne smiju se mijenjati.
