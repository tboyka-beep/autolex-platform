# Autolex EU használtautó-adatstratégia

## Célterület

Az Autolex katalógusa az Európai Unióban és az Európai Gazdasági Térségben
ténylegesen forgalomba helyezett járművekre épül.

- `M1`: személyautók;
- `N1`: legfeljebb 3,5 tonnás könnyű haszonjárművek;
- elsődleges időszak: 2000-től napjainkig;
- a magyar és közép-európai használtautó-piacon gyakori, 1990–1999 közötti
  modellek külön történeti forrásrétegből kerülnek be.

Egy globális modell nem kerül automatikusan a katalógusba. Legalább egy
EU/EGT piaci bizonyíték szükséges hozzá.

## Forrási sorrend

1. **EEA CO2 monitoring – személyautók és kishaszonjárművek.** A tagállamok
   által jelentett regisztrációk alapján igazolja az EU/EGT piaci jelenlétet,
   és tartalmazhat gyártó-, márka-, kereskedelmi név-, típus-, variáns-,
   verzió-, üzemanyag-, teljesítmény-, hengerűrtartalom-, tömeg- és
   kibocsátási mezőket.
2. **EU típusjóváhagyási és megfelelőségi adatok.** A műszaki változatok és a
   szabályozási kategóriák ellenőrzésére.
3. **Gyártói kézikönyvek és hivatalos műszaki dokumentumok.** Generációs név,
   karbantartási adat és modellévek megerősítésére.
4. **Ellenőrzött történeti forrás.** Kizárólag az EEA-időszak előtti, az EU
   használtautó-piacán ma is releváns modellekhez, egyértelmű licenccel és
   forráshivatkozással.

Elsődleges hivatkozások:

- EEA passenger-car monitoring:
  https://co2cars.apps.eea.europa.eu/
- EEA Datahub:
  https://www.eea.europa.eu/en/datahub/
- Regulation (EU) 2019/631:
  https://eur-lex.europa.eu/eli/reg/2019/631/oj

## Azonosítás és duplikációkezelés

A technikai változat az alábbi mezők normalizált kombinációjából kap stabil
SHA-256 ujjlenyomatot:

- gyártó, márka és kereskedelmi modellnév;
- típusjóváhagyás, variáns és verzió;
- járműkategória és üzemanyag;
- hengerűrtartalom és teljesítmény.

Az ország és a jelentési év nem része az ujjlenyomatnak. Ezek külön piaci
rekordként kapcsolódnak a változathoz, így megőrizhető, hogy egy autó mely
országokban és milyen időszakban volt regisztrálva.

## Publikálási minőség

- **Imported:** hivatalos EU/EGT rekord, de generációhoz még nem kapcsolt.
- **Matched:** márka, modell és technikai változat normalizálva.
- **Reviewed:** a generáció és modellév emberileg vagy második hivatalos
  forrással ellenőrizve.
- **Verified:** legalább két egymástól független hivatalos bizonyíték, teljes
  forráshivatkozással.

Az importált rekord nem jelenik meg automatikusan teljes értékű műszaki
adatlapként. A nyilvános oldalra csak a minimális mezők és a minőségi állapot
ellenőrzése után kerülhet.
