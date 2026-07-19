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
- EEA Discodata SQL/JSON API leírás:
  https://discodata.eea.europa.eu/Help.html
- EEA CO2 passenger-car dataset metadata:
  https://sdi.eea.europa.eu/catalogue/srv/api/records/fa8b1229-3db6-495d-b18e-9c9b3267c02b
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

## Motorváltozatok és karbantartási pontosság

Egy modell vagy generáció nem tekinthető motorváltozatnak. Minden eltérő
motorkód, üzemanyag, hengerűrtartalom, teljesítmény vagy gyártási időszak külön
motorrekordot kap. A katalógussor és a motor között több-a-többhöz kapcsolat
van, mert ugyanazt a modellt több motorral, ugyanazt a motort pedig több
modellben is forgalmazhatták.

A motorazonosítás első rétege az EEA típus–variáns–verzió, üzemanyag,
hengerűrtartalom és teljesítmény kombinációja. A motorkódot és a
karbantartási előírásokat a megfelelőségi dokumentum, a gyártó hivatalos
kézikönyve, műszaki közleménye vagy alkatrész-katalógusa erősíti meg. Az EEA
rekord önmagában nem bizonyít olajspecifikációt, feltöltési mennyiséget vagy
alkatrész-illeszkedést.

Az automatikus szinkron a Discodata `CO2Emission.latest.co2cars` táblájából
először a legfrissebb lezárt, 2021-es `F` állományt, majd a 2010–2020 közötti
lezárt éveket dolgozza fel. A 2022-es `P` állomány előzetes, ezért nem kerül a
végleges adatfolyamba. A lekérdezések márka + kereskedelmi név + év szerint
elkülönülnek, és a típus, variáns, verzió, üzemanyag, hengerűrtartalom és
teljesítmény kombinációját aggregálják. Egy újrapróbált lap nem növeli meg
tévesen a regisztrációszámot, mert minden forrásmegfigyelés stabil
ujjlenyomatot kap.

Az EEA-egyezés mindig `proposed`: pontos EU piaci jelenlétet és műszaki
alapmezőket igazol, de motorkódot nem. `verified` csak külön OEM/CoC vagy más
elsődleges gyártói dokumentum után lehet.

Minden műszaki állításhoz külön bizonyíték tartozik:

- mezőnév és normalizált érték;
- forrás kiadója, dokumentumazonosítója és közvetlen hivatkozása;
- lekérés ideje és tartalmi ujjlenyomata;
- elsődleges vagy megerősítő forrás minősítése;
- ellenőrzési állapot: `pending`, `proposed`, `reviewed`, `verified`,
  `vin_required` vagy `conflict`.

Az olaj, hűtőfolyadék, fékfolyadék, szűrő és más illesztett termék csak akkor
kap „ellenőrzött” jelölést, ha a motorváltozat és az adott műszaki mező is
forrással igazolt. Ha a gyártó év közben módosított, vagy az adat csak
alvázszám alapján dönthető el, a rendszer nem választ találomra: `vin_required`
állapotot mutat, és csak biztonságos általános terméket ajánl.

Az EU megfelelőségi nyilatkozat mintája az engine code, hengerelrendezés,
hengerűrtartalom, üzemanyag és maximális teljesítmény mezőit is definiálja:
https://eur-lex.europa.eu/eli/reg_impl/2020/683/oj
