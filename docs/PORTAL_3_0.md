# Autolex Portal 3.0

## Vizuális irány

A régi, autós sablonhatás helyett a teljes nyilvános felület sötét, matt,
infrastruktúra- és adatközpont-hangulatú rendszert kap. A megoldás kizárólag
saját PHP, CSS, JavaScript és inline SVG elemeket használ. Nincs fizetős sablon,
képbank, külső betűkészlet vagy JavaScript-komponenskönyvtár.

## Főoldal

- adatfolyam-állapotot mutató technikai hero;
- valós adatbázis-számlálók;
- népszerű márkák dinamikus listája;
- műszaki képességek és adatminőségi magyarázat;
- hivatalos ingyenes forrásjegyzék;
- importált → matched → reviewed → verified folyamat.

## Katalógus

A régi egyszerű kereső és négyoszlopos kártyarács helyett részletes,
progresszíven fejlesztett katalógus készül. JavaScript nélkül is működik,
JavaScripttel pedig oldalújratöltés nélkül frissíti a találatokat.

Szűrők:

- szabad szöveges keresés;
- márka és függő modelllista;
- üzemanyag;
- motorkód;
- gyártási év tartomány;
- teljesítmény tartomány;
- A/B/C adatminőség;
- dokumentáltság, márka, évjárat vagy teljesítmény szerinti rendezés.

## Adatminőség

- **A:** motorazonosítás, üzemanyag, hengerűrtartalom, teljesítmény és évjárat.
- **B:** azonosítható motor és legalább egy további műszaki mező.
- **C:** alap márka/modell/generáció rekord.

A betűfokozat nem azonos a forrásellenőrzési státusszal. Egy A minőségű,
részletes rekord is maradhat `proposed`, amíg nincs elég független bizonyítéka.

## Safety Gate visszahívások

A 3.0-s adatmag fail-closed importert tartalmaz az EU Safety Gate járműriasztásaihoz.
Az aktuális heti XML-cím nem kerül beégetésre: a rendszer a hivatalos data.europa
metaadatból oldja fel, kizárólag allowlistes EU-hostot fogad el, és sikertelen
lekérés vagy ismeretlen XML-séma esetén a korábbi adatokat érintetlenül hagyja.
A nyilvános `/autolex/v1/recalls` végpont márka és modell szerint szűrhető.
