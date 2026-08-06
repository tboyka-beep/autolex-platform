# ALX-033 — Valós főoldali adatblokkok

## Megtalált hiba

A világos téma főoldalán a lefedettség, a népszerű márkák és a mérőszámsáv közvetlenül lefuttatta a plugin hookját, majd minden esetben kirajzolta a fallback tartalmat is. Valós plugin-kimenet mellett ez egyszerre jelenítette volna meg az adatot és a „betöltés folyamatban” vagy üres állapotot.

## Javítás

A főoldal a három hook kimenetét output bufferben gyűjti. A plugin által előállított, nem üres markup elsőbbséget kap; a lokalizált fallback kizárólag üres hook-kimenetnél fut le. A megoldás szerveroldali, ezért JavaScript nélkül is helyes.

Érintett hookok:

- `autolex_theme_coverage_panel`
- `autolex_theme_popular_brands`
- `autolex_theme_metric_strip`

## Biztonsági határ

A hook-kimenet ugyanúgy megbízható theme/plugin markupként kerül kiírásra, mint a korábbi közvetlen `do_action()` esetén. Kitalált számláló vagy kliensoldali fallback-elrejtés nincs. Production deploy nem része ennek az egységnek.
