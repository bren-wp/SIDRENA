# SIDRENA Desktop 0.10 — sigurnost, privatnost, stabilnost i audit

## Rezultat ove nadogradnje

Pregledane su postojeće komponente SIDRENA Windows 0.9, WPF prikazi,
lokalne SQLite evidencije, CSV/XML/HTML izvoz, WordPress javni API,
sigurnosne kopije, testovi i Windows instalacijski postupak. Uz postojeće
testove izvedeni su sljedeći provjerljivi zahvati:

| Područje | Prijašnji rizik | Rješenje |
|---|---|---|
| Prikaz grešaka | MessageBox prikazivao sirovi ex.Message (SQL, datotečne putanje, mrežne adrese i interni detalji) | Testirani UserFacingError vraća korisniku razumljive hrvatske poruke bez osjetljivih detalja |
| Neobrađene WPF pogreške | Neočekivana pogreška mogla je izbaciti tehnički dijalog sustava | DispatcherUnhandledException prikazuje sanitiziranu poruku i zaustavlja program prije daljnjeg rada u nepouzdanom stanju |
| Otvaranje Arhive | ShellExecute bez allowlista mogao je pokrenuti neočekivanu datoteku | DocumentOpenPolicy dopušta samo postojeće .csv/.xml/.html dokumente; izvršni i skriptni format blokiran |
| Internetske poveznice | Proces otvarao nekontroliranu shemu | Dozvoljeni HTTPS i namjenski mailto |
| WordPress HTTPS | DNS ime moglo je pokazivati na lokalne, privatne ili metadata IP adrese | SocketsHttpHandler povezuje se samo na verificirane javne IP adrese uz provjeru u trenutku uspostave TCP veze, bez auto-redirecta i proxyja |
| Stabilnost mreže | Jedan neuspjeli javni IPv6 endpoint mogao je spriječiti povezivanje premda postoji javni IPv4 | Nakon neuspjeha pokušava se druga **javna** adresa; privatna odredišta se ne koriste |
| Izmjena povezanog weba | Spor javni GET mogao je vratiti stare podatke nakon promjene stranice ili napuštanja ekrana | CancellationTokenSource prekida prethodni zahtjev; zakašnjeli rezultat ne mijenja aktualni prikaz |
| UI tekstovi | Razvojne poruke o internoj bazi, REST-u, putanjama, serializeru i obliku lokalnog pregleda | Korisničke poruke svedene na razumljiv opis postupka i jasnu pravnu napomenu o javnim cjenicima |

## Dead-code audit — pregledani slojevi

1. Python CI alat provjerava privatne metode, privatna polja i unutarnje tipove
   u desktop C# te uspoređuje reference u C# i WPF XAML datotekama.
2. Roslyn pravila IDE0051/IDE0052 zasebno provjeravaju semantički
   neiskorištene privatne članove jezgre Sidrena.Core.
3. Provjera korisničkih tekstova otkriva poznate razvojne oznake ili
   prikaz sirovih iznimki. Za otvaranje lokalnih datoteka obvezan je
   centralni DocumentOpenPolicy.
4. CI testira Core na Windowsu i Linuxu, zatim Windows WPF compile,
   pokretanje Portable EXE-a, 30 WPF snimki pri tri širine te stvarnu
   Inno Setup instalaciju i deinstalaciju uz očuvanje podataka.

Skriptni tekstualni audit i Roslyn pravila nisu matematički dokaz
odsutnosti svih neizvedenih grana. Za potpuno pokrivanje dinamičkog
pozivanja, reflectiona, runtime funkcija i vizualnog rada svih uređaja
i dalje su potrebni profile-guided / coverage testovi, ručna provjera
WPF/XAML sučelja te specijalizirani dependency/SBOM audit. Nijedan
korisnički podatak ne briše se automatizmom u svrhu čišćenja koda.

## Privatnost i integracija

Windows i dalje ne šalje korisnički katalog u WordPress, ne prikuplja
telemetriju i ne zahtijeva lozinku za čitanje javno dostupnih cjenika.
Mrežni zahtjev pokreće se korisničkom radnjom. Tijekom obrade sigurnosne
kopije, korisnikovi podaci ostaju pod njegovom kontrolom na računalu;
obrana od drugih procesa na istom uređaju ovisi i o zaštiti Windows
računa, diska, sustava i sigurnosnih kopija.

Autorizirano pisanje u WordPress i zakonski verificirana automatska
javna objava nisu ovdje aktivirani i ne smiju se prikazivati kao
završena funkcionalnost.

## Izdanja i GPL

Službeni WordPress dodatak ostaje 1.5.0 i GPLv2 ili novija.
GitHub ima samo jedan Release i tag 1.5.0. Nakon zelenog Windows
CI-ja na main osvježavaju se samo Windows datoteke 0.10.0:
Portable.exe, Setup.exe, SHA256SUMS.txt i release-info.json.
WordPress ZIP i njegova SHA-256 datoteka ostaju neizmijenjeni.

Komercijalni EULA nacrt Brendigo LTD zahtijeva završnu pravnu provjeru
vlasničkog lanca, ranije GPL distribucije, nadležnosti, potrošačkih
prava i ovisnosti prije naplatne distribucije.
