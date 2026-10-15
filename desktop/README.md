# SIDRENA Desktop — lokalno upravljanje cijenama

SIDRENA Desktop je **izdvojeni Windows 10/11 modul** unutar postojećeg SIDRENA projekta.
Namjerno ne zamjenjuje WordPress dodatke niti mijenja njihove baze podataka.

## Što radi početna verzija 0.1

- Izrađuje lokalni katalog proizvoda i usluga u SQLite bazi u \`%LOCALAPPDATA%\Brendigo\SIDRENA\sidrena.db\`.
- Sprema aktualne iznose u cijelim centima, bez pogrešaka binarnog zaokruživanja.
- Čuva odvojeni povijesni sidreni iznos, datum i izvor. Ne izmišlja sidrenu cijenu iz današnje cijene.
- Zaključava dva poznata referentna datuma na \`2026-09-10\` i \`2025-05-02\`, ali odabir pravnog režima ostaje odgovornost korisnika.
- Svaku stvarnu promjenu aktualne cijene upisuje u lokalni, postojani zapis s razlogom i vremenom.
- Izvozi **RADNI** CSV i XML u atomic-save načinu rada; ti izvozi nisu zakonski javni cjenik.
- Ne zahtijeva račun, licencu, internet ni telemetriju.

## Izgradnja

Na Windows računalu s .NET 8 SDK-om:

\`\`\`powershell
dotnet run --project desktop/Sidrena.Tests/Sidrena.Tests.csproj -c Release
dotnet run --project desktop/Sidrena.Desktop/Sidrena.Desktop.csproj -c Release
\`\`\`

GitHub Actions \`desktop-ci.yml\` izrađuje portable Windows x64 artefakt. Potpisani Setup, službeni PDF cjenik, WordPress API sinkronizacija, rasporedi slanja i dokumentirana kompatibilnost zakonskog izvoza **nisu dio ove početne verzije**.

## Veza sa službenim WordPress.org dodatkom

Vlasnikov postojeći službeni dodatak je:
https://wordpress.org/plugins/brendigo-sidrena-cijena/

Njegov WordPress.org slug **ostaje** \`brendigo-sidrena-cijena\`, a postojeći SIDRENA repo izdaje dodatne WordPress/WooCommerce edicije. To nisu automatski iste baze ni isti ugovori API-ja.

Sljedeća faza zahtijeva provjeru izvornog koda i konkretnih mogućnosti službenog dodatka te:
1. Verzionarni, **read-only** adapter za katalog (ID/SKU i eventualni ID poslovnice).
2. Mapiranje cijene, sidrenog datuma i izvora bez promjene WooCommerce primarnog kataloga.
3. Izričitu administrativnu autorizaciju za svaku operaciju pisanja.
4. TLS, WordPress application passwords ili odgovarajuću metodu s najmanjim potrebnim ovlastima, pohranjenu kroz Windows DPAPI/Credential Manager.
5. ETag/version conflict detekciju, idempotentni prijenos i provjeru posljednje uspješne objave.
6. Realne integracijske i migracijske testove prije uključivanja objave.

**Nema direktne razmjene podataka, izmjena WordPress podataka ni javne objave u 0.1.**

## Zakonska sigurnost

Odluke NN 101/2026 i NN 110/2026 propisuju sadašnji okvir s početkom primjene 17. 11. 2026. Radni CSV/XML nisu zamišljeni kao zamjena za konkretni, konačno validirani objavni format, naziv datoteke, obvezna polja, povijesnu arhivu i raspored objave. Razvoj zakonskog serijalizatora zahtijeva provjeru konačnih pravilnika.

Referentne sidrene cijene nisu akcijske cijene niti 30-dnevni minimum pri sniženju. Potonji su zaseban budući model.
