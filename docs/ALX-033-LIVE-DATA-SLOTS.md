# ALX-033 — Valós főoldali adatblokkok

## Megtalált hiba

A világos téma főoldalán a lefedettség, a népszerű márkák és a mérőszámsáv közvetlenül lefuttatta a plugin hookját, majd minden esetben kirajzolta a fallback tartalmat is. Valós plugin-kimenet mellett ez egyszerre jelenítette volna meg az adatot és a „betöltés folyamatban” vagy üres állapotot.

## Témaoldali javítás

A főoldal a három hook kimenetét output bufferben gyűjti. A plugin által előállított, nem üres markup elsőbbséget kap; a lokalizált fallback kizárólag üres hook-kimenetnél fut le. A megoldás szerveroldali, ezért JavaScript nélkül is helyes.

Érintett hookok:

- `autolex_theme_coverage_panel`
- `autolex_theme_popular_brands`
- `autolex_theme_metric_strip`

## Plugin-oldali valós adatkapcsolat

Az `Autolex_Theme_Data_Bridge` a publikus hookokat kizárólag adatbázisból származó, ellenőrizhető összesítésekkel tölti:

- járműváltozatok, márkák, modellek és EU/EGT piacok száma az EU-katalógusból;
- legfrissebb rendelkezésre álló adatév;
- népszerű márkák a valós regisztrációs és változatszám szerinti sorrendben;
- ellenőrzött motorváltozatok és forrásmegfigyelések száma.

Ha a katalógusséma nincs telepítve vagy nincs tényleges adat, a bridge nem publikál nullaértékű kártyákat. Ilyenkor a téma őszinte fallback állapota marad látható.

A bridge saját stílusa csak az `autolex-theme` aktív témánál töltődik be, és a téma főoldali stílusára épül.

## Automatikus bizonyítás

- `tests/autolex-theme-dynamic-slots-contract.sh`: a téma nem kerülheti meg a bufferelt slot renderelőt;
- `tests/theme-data-bridge-smoke.php`: valós adatkimenet, márkasorrend, öt mérőszám, scoped CSS és üresadat-fallback;
- a smoke bekerült a GitHub-hosted Portal és a home-server quality kapuba is.

## Biztonsági határ

A hook-kimenet ugyanúgy megbízható theme/plugin markupként kerül kiírásra, mint a korábbi közvetlen `do_action()` esetén. Kitalált számláló, kliensoldali fallback-elrejtés vagy production deploy nincs ebben az egységben.
