# SIDRENA by Brendigo

Ovaj repozitorij sadrži jedan službeni WordPress dodatak i Windows aplikaciju.

## WordPress dodatak

Službeni dodatak: https://wordpress.org/plugins/brendigo-sidrena-cijena/

Izvorni kod nalazi se u mapi wordpress/brendigo-sidrena-cijena/.
Jedina podržana WP.org domena: brendigo-sidrena-cijena.
Uvezeni paket 1.5.0 bit će byte-for-byte sadržajno jednak ZIP-u vlasnika (128 datoteka).

Druge neslužbene edicije sidrena-wordpress i sidrena-woocommerce uklanjaju se
iz aktualnog stabla; prethodni Git commitovi ostaju dostupni za oporavak.
WordPress.org objava ne izvršava se automatski.

## Windows aplikacija

Desktop kod: desktop/
Dokumentacija dizajna: docs/DESKTOP-UI-PARITY.md

Lokalni SQLite katalog, povijest cijena, radni CSV/XML i arhiva.
Povezivanje sa službenim WP.org dodatkom u razvoju je; njegov objavljeni javni
REST API može se čitati bez pisanja u WordPress bazu.
