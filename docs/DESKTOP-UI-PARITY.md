<!--
Sidrena source file.
Author: brendigo
Author URI: https://brendigo.com/
Plugin URI: https://brendigo.com/sidrene-cijene/
Support: sidrena@brendigo.com
-->

# SIDRENA Desktop — kriteriji 1:1

Korisnik je dostavio deset dizajnerskih referenci. One određuju
vizualni cilj, ali ne dokazuju postojanje funkcija ni zakonsku usklađenost.

## Dizajn

Narančasta #FF7A1A; Navy 900 #0B1F39; Slate 800 #14263D;
Slate 700 #1E344E; bijela #F8FAFC; sekundarni tekst #94A3B8.
Jedinstveni sidebar, aktivna navigacija, 4 KPI kartice, zaobljeni paneli,
konzistentne tipke i statusi. Ciljana sistemska tipografija slična Interu.

## Ekrani i stvarno stanje

| Ekran | Implementirano | Preostale razlike |
|---|---|---|
| Pregled | Četiri KPI, brze radnje, stvarna povijest i radni izvozi | Foto-hero, potpuno podudaranje kartica javne objave i razmaka |
| Artikli | Tablica, pretraga, unos, promjena cijene, urednik metapodataka, sidreni filtar i sigurni radni CSV uvoz | Slike proizvoda, ostali filtri, dodatni tabovi |
| Usluge | Odvojeni katalog, unos, audit metapodataka, uvoz i izmjene cijena | Kategorije, porez, svi detaljni tabovi |
| Povijest cijena | Audit, pretraga, period i vrsta, CSV izvoz, graf iz lokalnih zapisa | Filtri po lokaciji i korisniku kada podaci postoje |
| Cjenici | Radni CSV/XML, pregled podataka | Službena shema, PDF, javni HTML pregled i objava |
| Lokacije | Lokalne poslovnice, adrese, aktivnost | Karta, veze cjenika, kontakt podaci |
| Sinkronizacija | Popis kanala s istinitim nepovezanim statusima | Sigurni WP/Woo/SFTP/FTPS/WebDAV/API adapteri |
| Arhiva | Evidencija radnih izvoza, SHA-256 provjera izmjena, otvaranje lokalne datoteke | Javni arhivski snapshotovi, pregled i verzije |
| Postavke | Sigurnosna kopija, mapa baze, informacije | Restore, enkripcija, zakazivanje, ažuriranja |
| Pomoć | Brzi početak, podrška, prečaci | Puni FAQ i dokumentacija |

## Strogi QA kriteriji

- Referentni datumi 10.09.2026. i 02.05.2025. ostaju zaključani; primjeri iz 2025. ne smiju postati stvarne sidrene vrijednosti.
- Sidrena cijena nije akcijska cijena i ne izmišlja se iz današnjeg iznosa.
- Ne prikazivati uspješne objave ili povezane integracije bez stvarnog dokaza.
- Provjeriti izgled pri Windows skaliranju 100%, 125%, 150% i 200% i na manjim prozorima.
- Ne smije biti preklapanja elemenata, nedostupnih kontrola ni skrivene funkcionalnosti.
- Navigacija i tipkovnica, čitači zaslona, kontrast i stanja pogrešaka moraju raditi.
- Prije spajanja PR-a: Windows build, testovi baze, launch smoke, otvaranje svih 10 prikaza,
  snimanje svih deset stvarnih WPF ekrana u GitHub CI artefakte i ručna usporedba s referencama.
- Postojeće WordPress edicije i službeni slug brendigo-sidrena-cijena ostaju nedirnuti.
- Nema javnog izdanja dok sigurnost, valjanost datoteka i svi relevantni QA uvjeti ne prođu.

Stanje: vizualno podudaranje 1:1 još NIJE dokazano. CI snimke stvarnih
ekrana služe za pronalaženje i uklanjanje preostalih razlika.
