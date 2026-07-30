# Autolex ingyenes és valós adatforrás-stratégia

## Alapelv

Az Autolex csak olyan adatot publikál kész műszaki állításként, amelyhez megvan
az eredeti forrás, a lekérés vagy dokumentum ideje, az adatminőségi állapot és
az újraellenőrizhető hivatkozás. Az ingyenes hozzáférés nem jelent automatikus
korlátlan újrafelhasználási jogot: minden új forrásnál külön ellenőrizni kell a
licencet, a felhasználási feltételeket, a lekérési korlátot és a robotizált
hozzáférés engedélyezését.

## Engedélyezett források

### 1. European Environment Agency – CO2 monitoring

- Hivatalos, elsődleges EU/EGT forrás.
- Automatikus SQL/JSON API-import már működik.
- Használható: márka, kereskedelmi modellnév, típus, variáns, verzió,
  üzemanyag, hengerűrtartalom, teljesítmény, tömeg, CO2 és piaci jelenlét.
- Nem használható önmagában motorkód, olajspecifikáció vagy alkatrészillesztés
  bizonyítására.

### 2. Eurostat közúti közlekedési adatok

- Hivatalos, nyilvános REST API.
- Ország-, év- és hajtáslánc-szintű járműállomány és új regisztráció.
- Piaci háttérgrafikonokhoz és trendekhez, nem egyedi járműspecifikációhoz.

### 3. European Alternative Fuels Observatory

- Hivatalos európai alternatívhajtás- és infrastruktúra-adatok.
- BEV, PHEV, hidrogén, LPG, CNG és LNG piac, állomány és regisztráció.
- Az automatizálás csak stabil letöltési formátum és felhasználási feltétel
  ellenőrzése után indulhat.

### 4. EU Safety Gate

- Hivatalos veszélyestermék- és visszahívási riasztások.
- Nyilvános heti XML és Excel formátum.
- Jármű-visszahívások külön biztonsági rétegként kapcsolhatók a márkához,
  modellhez, gyártási időszakhoz és kockázathoz.

### 5. Type Approval Register

- Hivatalos típusjóváhagyási referenciakatalógus.
- A data.europa.eu rekord jelenleg nem igazol stabil gépi terjesztési csatornát,
  ezért automatikus import helyett csak kézi vagy dokumentumalapú megerősítés.

### 6. EUR-Lex / Implementing Regulation (EU) 2020/683

- A CoC és típusjóváhagyási mezők hivatalos jelentése.
- Validációs séma és adatértelmezés, nem önálló járműadatbázis.

## OEM dokumentumok

Gyártói kézikönyv, szervizközlemény, alkatrészkatalógus vagy CoC csak
allowlistes, kézi ellenőrzés után használható. Fizetős adatbázis, kalózmásolat,
engedély nélküli tömeges scraping és fórumállítás nem lehet ellenőrzött forrás.

## Következő adatfázisok

1. Safety Gate jármű-visszahívási XML importer.
2. Eurostat járműállomány és regisztrációs trendek.
3. EAFO alternatívhajtás-adatok és magyar országprofil.
4. Típusjóváhagyási dokumentum-kapcsolatok.
5. Márkánkénti OEM kézikönyv-allowlist és karbantartási bizonyíték.
