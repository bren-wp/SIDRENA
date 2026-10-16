# SIDRENA by Brendigo

Repozitorij održava **jedan službeni WordPress dodatak** i zasebnu **Windows desktop aplikaciju**.

## Službeni WordPress dodatak 1.5.0

- WordPress.org: https://wordpress.org/plugins/brendigo-sidrena-cijena/
- Izvorni kod: [wordpress/brendigo-sidrena-cijena](wordpress/brendigo-sidrena-cijena/)
- Službeni slug i text domain: brendigo-sidrena-cijena
- Službena referentna verzija: **1.5.0**, 128 datoteka sadržajno provjerenih prema vlasnikovom ZIP-u.

Dvije nekadašnje paralelne edicije sidrena-wordpress i sidrena-woocommerce
više nisu dio aktualnog izvornog stabla. Povijesni Git commitovi zadržani su
radi oporavka i audita. WordPress.org objava ovim repozitorijem nije automatska.

## Stroga pravila GitHub izdanja

Na GitHubu se čuva **jedan Release 1.5.0 i jedan tag 1.5.0**:

https://github.com/bren-wp/SIDRENA/releases/tag/1.5.0

Izdanje trajno sadrži službeni WordPress ZIP 1.5.0 i njegov SHA-256, uz
**uvijek najnovije Windows datoteke**: SIDRENA-Desktop-win-x64-Portable.exe,
SIDRENA-Desktop-win-x64-Setup.exe, SHA256SUMS.txt i release-info.json.
WordPress paket i tag 1.5.0 ostaju nepromijenjeni; svaki uspješan Windows
build na main zamjenjuje samo Windows artefakte. Desktop verzija neovisna
je o verziji WordPress dodatka. Nema drugih GitHub izdanja ni tagova.

## Windows aplikacija

Izvor i testovi: [desktop/](desktop/)

Windows aplikacija koristi lokalnu SQLite bazu, upravlja katalozima i
poviješću cijena, generira radne CSV/XML datoteke i siguran lokalni HTML
pregled za ispis. Čita službeni javni REST API dodatka preko HTTPS-a, bez
pisanja u WordPress i bez spremanja vjerodajnica. Javno objavljivanje,
autorizirana sinkronizacija i propisani serializer još su u razvoju.

Dizajn i QA kriteriji: [docs/DESKTOP-UI-PARITY.md](docs/DESKTOP-UI-PARITY.md)

**Radni izvozi nisu zamjena za propisani i verificirani javni cjenik.**
