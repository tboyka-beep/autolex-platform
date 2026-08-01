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

- Hivatalos, ingyenes Statistics API, JSON-stat 2.0 válaszformátummal.
- Az Autolex csak az allowlistes `ec.europa.eu` hostot és az előre engedélyezett
  közúti járműállomány-/regisztrációs adatsorokat fogadja el.
- Beépített korlátok: 25 másodperces timeout, átirányítás tiltása, 4 MB
  válaszméret, legfeljebb 1 millió deklarált cella, séma- és dimenzióellenőrzés,
  hatórás cache.
- Használható: ország-, év- és hajtáslánc-szintű járműállomány,
  regisztráció és piaci háttérgrafikonok.
- Nem használható egyedi jármű, motorkód, karbantartási specifikáció vagy
  alkatrészillesztés `verified` bizonyítékaként.

### 3. European Alternative Fuels Observatory

- Hivatalos európai alternatívhajtás- és infrastruktúra-adatok.
- Az EAFO a grafikonok nagy részéhez CSV/XLS letöltést biztosít, de az Autolex
  által igazolt, stabil nyilvános gépi API jelenleg nincs.
- Emiatt automatizált scraping tilos. Import csak kézzel letöltött hivatalos
  fájlból, forrás-URL-t, referencia-időszakot, letöltési időt, fájlméretet és
  SHA-256 ujjlenyomatot tartalmazó manifeszttel indulhat.
- Az adapter csak az
  `alternative-fuels-observatory.ec.europa.eu` hostot, 10 MB alatti CSV/XLSX
  fájlt és az előre engedélyezett AF-flotta-, regisztráció-, piaci részesedés-,
  töltő- és üzemanyag-infrastruktúra adatkört fogadja el.
- Az importált statisztika `source_download_validated`, nem egyedi járműre
  vonatkozó `verified` állapotot kap.

### 4. EU Safety Gate

- Hivatalos veszélyestermék- és visszahívási riasztások.
- A heti XML aktuális URL-jét a hivatalos data.europa metaadatból oldja fel.
- Az importer csak EU-s allowlistes HTTPS hostot fogad el, és hiba esetén nem ír
  felül korábbi rekordot.
- Jármű-visszahívások márka, modell, típus, kockázat és intézkedés szerint
  normalizált, kereshető biztonsági rétegbe kerülnek.

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

1. Safety Gate első élő importjának és heti frissülésének hálózati ellenőrzése.
2. Eurostat járműállomány és regisztrációs mintalekérések tartós tárolása.
3. EAFO hivatalos CSV-manifeszt és ellenőrzött magyar országprofil importja.
4. Típusjóváhagyási dokumentum-kapcsolatok.
5. Márkánkénti OEM kézikönyv-allowlist és karbantartási bizonyíték.
