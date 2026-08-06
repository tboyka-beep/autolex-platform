# Autolex saját téma – Blocksy-kivezetési audit

## Hatókör

Ez az audit a repository publikus megjelenítési rétegeit vizsgálja a saját `theme/autolex-theme` bevezetése előtt. Célja, hogy a funkcionális pluginmag és a publikus theme-shell határa egyértelmű, tesztelhető és visszaállítható legyen.

## Repository-megállapítások

- A saját téma önálló könyvtárban él: `theme/autolex-theme`.
- A saját témában nincs `.ct-*` selector és nincs Blocksy-specifikus PHP-hívás.
- A saját téma nem child theme: a `style.css` nem deklarál `Template:` szülőtémát.
- A publikus shellt a téma saját `header.php`, `footer.php`, route-template-ek, design tokenek és minimális JavaScript kezeli.
- A plugin marad az adat-, import-, REST-, proveniencia-, Safety Gate-, keresési és összehasonlítási logika tulajdonosa.
- A téma route-shelljei a WordPress/plugin által renderelt valós tartalmat fogadják be; nem duplikálják a plugin üzleti logikáját.
- A téma assetjei explicit dependency-sorrenddel és fájlszintű cache-bustinggal töltődnek.

## Betöltési sorrend

1. `style.css`: design tokenek, reset, publikus shell, header, navigáció, footer és közös layout.
2. `assets/css/states.css`: loading, üres, részleges, konfliktusos és hibaállapotok.
3. `assets/css/content.css`: általános dokumentum-, archívum- és Tudástár-layout.
4. Route-specifikus stylesheet kizárólag a megfelelő WordPress route-on.
5. `assets/js/theme-shell.js`: mobil drawer és billentyűzettel kezelhető keresőtabok.

A sorrendet a `functions.php` WordPress dependency-listái rögzítik. A theme nem támaszkodik a Blocksy assetjeire, DOM-jára vagy selectoraira.

## Aktiválási határ

A repository jelenlegi theme release candidate-je productiont nem aktivál. Automatikus aktiválás csak akkor engedhető meg, ha a deployfolyamat bizonyíthatóan:

1. rögzíti az előző aktív téma stylesheet-nevét;
2. idempotensen telepíti és aktiválja az `autolex-theme` témát;
3. azonnal lefuttatja a health, Live Production QA és vizuális viewport-contractokat;
4. hiba esetén automatikusan visszaaktiválja az előző témát;
5. a rollback eredményét és az aktív theme markert artifactban rögzíti.

Amíg ez secret vagy kézi admin nélkül nem bizonyított, a téma release candidate marad, és production aktiválás tilos.

## Kötelező migrációs contract

A `tests/autolex-theme-migration-contract.sh` fail-closed módon ellenőrzi, hogy:

- nincs Blocksy-szülőtémás kapcsolat;
- nincs `.ct-*` selector vagy `blocksy` hivatkozás a saját theme-kódban;
- nincs `!important` halmozás;
- a saját header, footer és route assetek léteznek;
- az asset dependency-sorrend deklarált;
- ez az audit és az aktiválási rollback-követelmények megmaradnak.

## Fennmaradó production blocker

A repositoryban még nincs bizonyított, idempotens theme-aktiválási és automatikus rollback mechanizmus valós production health- és screenshot-kapuval. Emiatt a saját téma aktiválása jelenleg tiltott, miközben a release candidate fejlesztése és statikus/home-server validálása folytatható.
