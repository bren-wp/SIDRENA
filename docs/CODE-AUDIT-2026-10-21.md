# SIDRENA — pregled izvornog koda i kvalitete

## Opseg

- **Android:** Kotlin/Compose UI, javni REST parser, URL validacija, pretraga, navigacija, lint i JUnit testovi.
- **Windows:** WPF događaji, C# privatne metode i polja, .NET/Roslyn analiza, lokalne baze i sigurnosne kopije, deset zaslona, instalacija i portable pokretanje.
- **WordPress:** isključivo čitanje i provjera PHP sintakse/integriteta. **Izvor, verzija 1.5.0 i ZIP nisu mijenjani.**
- **GitHub Actions:** provjere obje aplikacije prije objave, SHA-256 potvrda artefakata i zasebno verzioniranje.

## Dead-code postupak

1. `tools/audit_android.py` detektira sumnjive privatne metode, zabranjene mrežne obrasce i neprimjerene API pozive.
2. Android lint, Kotlin kompilator, JUnit i R8 obavljaju dodatne provjere dosezljivosti i ispravnosti.
3. `tools/audit_desktop.py` uspoređuje privatne deklaracije s C#/XAML referencama; Roslyn provjerava `IDE0051`/`IDE0052`.
4. `tools/audit_repository.py` skenira potpisne datoteke/binarne artefakte, preklapajuće nazive i kontrolni SHA-256 WordPress izvora.
5. Sumnjivi kod ne uklanja se automatski bez provjere refleksije, XAML poveznica i javnih WordPress hookova.

## Jamstvo

Automatizirane provjere nisu matematički dokaz odsutnosti svih grešaka ili nepotrebnog koda. Kompletna funkcionalna usklađenost s WordPress administracijom zahtijeva dodatni autorizirani API, stvarne integracijske testove i QA na uređajima. U izvještajima o izdanjima prikazuje se stvarni rezultat CI postupaka, ne pretpostavljeni uspjeh.
