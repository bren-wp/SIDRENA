# SIDRENA — verzije i izdavanja

| Komponenta | Verzija |
|---|---|
| Android | **0.3.1** |
| Windows Desktop | **0.10.4** |
| WordPress dodatak | **1.5.2** |

Verzije su **odvojene**. WordPress izvor i ZIP ostaju nepromijenjeni dok nema stvarnih promjena u dodatku.

## Provjere i artefakti

Automatski GitHub Actions postupci izvode Android Kotlin/JUnit/lint provjere i grade APK i AAB, a Windows postupak pokreće .NET testove, WPF navigacijske provjere, izrađuje prijenosnu aplikaciju i Inno Setup instalaciju. Poseban audit pokriva WordPress PHP sintaksu i integritet službenog paketa, Windows i Android statičke provjere.

Novo objedinjeno izdanje sadrži:
- `SIDRENA-Android-debug.apk` — Android instalacijski APK za provjeru.
- `SIDRENA-Android-unsigned.aab` — AAB bez produkcijskog potpisa.
- `SIDRENA-Desktop-win-x64-Portable.exe` — Windows prijenosna aplikacija.
- `SIDRENA-Desktop-win-x64-Setup.exe` — Windows instalacijski program.
- `brendigo-sidrena-cijena-1.5.2.zip` — **neizmijenjeni** službeni WordPress paket.
- Kontrolne SHA-256 datoteke i Windows izvještaj o porijeklu binarnih datoteka.

Objava novog paketa moguća je tek kada oba CI postupka uspješno završe za isti commit, kontrolni zbrojevi odgovaraju i WordPress paket zadrži izvorni SHA-256. U nedostatku produkcijskog potpisnog ključa Android AAB ne treba predstavljati kao spreman za Google Play.

[GitHub izdanja](https://github.com/bren-wp/SIDRENA/releases)
