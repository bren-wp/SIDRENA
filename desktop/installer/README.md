# SIDRENA Windows — dvije distribucije

- **SIDRENA-Desktop-win-x64-Portable.exe**: samostalni Windows 10/11 x64 EXE.
- **SIDRENA-Desktop-win-x64-Setup.exe**: pravi Inno Setup instalacijski program za trenutačnog korisnika; Start izbornik, izbor prečaca i deinstalacija.
- **SIDRENA-Desktop-win-x64-SHA256SUMS.txt** i **SIDRENA-Desktop-release-info.json**: kontrolni zbrojevi, Windows verzija i izvorni commit.

Jedini GitHub Release/tag ostaje **1.5.0**: https://github.com/bren-wp/SIDRENA/releases/tag/1.5.0. Svaki uspješan Windows build na glavnoj grani osvježava samo četiri Windows artefakta, ne dira WordPress.org dodatak 1.5.0 i njegove dvije datoteke.

CI provjerava build, SQLite testove, otvaranje aplikacije, svih deset WPF ekrana, stvarnu Inno Setup kompilaciju, tihu instalaciju i deinstalaciju te očuvanje baze podataka. Lokalne datoteke korisnika čuvaju se u `%LOCALAPPDATA%\Brendigo\SIDRENA`.

Izdanja nisu digitalno potpisana dok ne bude osiguran valjan Windows code-signing certifikat. SmartScreen može prikazati upozorenje. Provjerite SHA-256 datoteke prije instalacije. Radni cjenici još nisu certificirana javna objava.
