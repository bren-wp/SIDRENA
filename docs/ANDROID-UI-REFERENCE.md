# SIDRENA Android — vizualni standard

Android zasloni koriste jedinstveni SIDRENA dizajn usklađen s dostavljenih deset referentnih prikaza.

## Dizajnerski sustav

- **Pozadina:** `#07182D` — tamnoplava.
- **Kartice:** `#10263D`, povišene površine `#19304A`.
- **Obrubi:** `#28455F`, suptilni i ujednačeni.
- **Primarna akcija:** `#FF852C` — Brendigo narančasta.
- **Tekst:** bijela; pomoćni tekst `#A9BAD1`.
- **Status:** `#52D9A3` za uspješnu povezanost, narančasta za upozorenja.
- Zaobljeni paneli (14–18 dp), razmak 8–16 dp, čitljivi hrvatski natpisi, uredni statusni prikazi i velika dodirna područja.

## Navigacija i zasloni

| Referenca | Android prikaz |
|---|---|
| 1. Povezivanje | HTTPS unos i povezivanje s WordPressom |
| 2. Početna | Povezana stranica, statistike, objava i brze kartice |
| 3. Proizvodi | Pretraga, dostupnost, trenutačna i sidrena cijena |
| 4. Usluge | Popis usluga, pretraga, cijene |
| 5. Detalj stavke | Naziv, SKU, cijene, datum i dostupnost |
| 6. Arhiva | CSV/XML filter i objavljene datoteke |
| 7. Poslovnice | Odabir webshopa i javnih poslovnica |
| 8. Pretraživanje | Proizvodi i usluge s pretragom |
| 9. Postavke | HTTPS stranica, osvježavanje i prikaz |
| 10. O aplikaciji | Verzija, upute i podrška |

Podnožje s četiri glavne kartice: **Početna, Cjenici, Arhiva, Postavke**. Trenutačno aktivna kartica naglašena je narančastom.

Interakcije ovise o stvarnoj dostupnosti server-side funkcionalnosti. Vizualne reference prikazuju i uređivanje, obavijesti, slike proizvoda i povijest promjena; javni WordPress REST API trenutno ne daje sve potrebne autorizirane podatke za te mogućnosti, pa se one ne smiju lažno simulirati. Dizajnerski cilj je što bliže podudaranje, uz stvarne izvore podataka i funkcionalne radnje.
