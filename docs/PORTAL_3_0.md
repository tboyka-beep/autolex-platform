# Autolex Portal 3.2.1

## Cél

Az Autolex nyilvános felülete világos, prémium, szerkesztőségi autóadat-portál. A főoldal, a szűrhető katalógus és a jármű-adatlap ugyanazt a design- és adatbizalmi rendszert használja, fizetős sablon, font vagy komponenskönyvtár nélkül.

## Főoldal

- világos hero valós adatbázis-számlálókkal;
- adatfolyam- és rendszerállapot-panel;
- népszerű márkák valós katalógusdarabszámmal;
- adatlefedettségi mérőszámok;
- forrásjegyzék túlállítás nélküli státuszokkal;
- adatminőségi módszertan és katalógus CTA.

## Katalógus

- márka → modell függő szűrés;
- generáció, üzemanyag, motorkód, évjárat és teljesítmény;
- A/B/C adatkitöltöttség és külön ellenőrzési állapot;
- dokumentáltság, márka, évjárat és teljesítmény szerinti rendezés;
- szerveroldali működés JavaScript nélkül;
- gyorsított REST-frissítés JavaScripttel;
- URL-paraméteres állapot és lapozás;
- sticky asztali szűrő és mobil drawer;
- reszponzív 3/2/1 oszlopos kártyarács.

## Jármű-adatlap 3.2.1

A dinamikus `/auto-adatlap/{id}/` útvonal is megkapja az `autolex-portal-3` és `autolex-vehicle-detail` rétegeket.

### Áttekintés

- márka, modell és generáció alapú cím;
- motorkód, hajtás, gyártási idő és teljesítmény;
- karbantartási állítás-, forrás-, elsődlegesforrás- és illesztési szabály számlálók;
- VIN-köteles állítások külön figyelmeztetése;
- sticky szakasznavigáció.

### Karbantartási bizonyíték

- minden állítás külön kártya;
- státusz, bizalmi százalék és forrásszám;
- elsődleges és támogató források külön jelölése;
- a forrás URL-je, ellenőrzési dátuma és kapcsolati megjegyzése megmarad;
- a VIN-ellenőrzést igénylő érték nem jelenhet meg kész, biztos specifikációként.

### Safety Gate

- márka–modell alapú read-only lekérdezés az eltárolt hivatalos EU Safety Gate adatokra;
- referencia, dátum, kockázat, típus, intézkedés és hivatalos forrás;
- a szöveges egyezés nem helyettesíti a VIN-alapú gyártói ellenőrzést;
- üres vagy hibás lekérdezés nem módosítja a járműadatokat.

### FrissAuto ajánlások

Három külön szint jelenik meg:

1. `matched_product` – konkrét eltárolt termék URL-lel, képpel és árral;
2. `specification_search` – motorkód- vagy specifikáció-alapú FrissAuto keresés, konkrét termék nélkül is látható;
3. `universal` – nem motoralkatrész jellegű fallback, kötelező méret- és kompatibilitás-figyelmeztetéssel.

Az ajánlat nem minősül automatikus alkatrész-kompatibilitási igazolásnak.

## Adatforrás-állapotok

- EEA CO₂: `active`;
- Eurostat: `adapter_ready`;
- EAFO: `adapter_ready`;
- Safety Gate: `live_validated`;
- Type Approval Register: `reference_only`;
- EU CoC séma: `active_reference`.

## Minőségi kapuk

A GitHub-hosted portal workflow ellenőrzi:

- PHP 8.3 szintaxis;
- portál- és adatlap-JavaScript szintaxis;
- forrásállapot-politika;
- katalógusszűrési contract;
- teljes portáldesign contract;
- jármű-adatlap, Safety Gate és FrissAuto contract;
- Safety Gate normalizálás;
- élő hivatalos Safety Gate XML.

A home-server quality gate ugyanezek mellett futtatja az EEA-, motor-, Eurostat-, EAFO- és Műveleti központ regressziókat, majd deployolható ZIP-et készít.

## Kiadási szabály

A PR draft marad mindaddig, amíg a `home-server-autolex` runneren a teljes quality gate és az ott készített ZIP artifact nincs igazolva. Main merge és production telepítés csak külön emberi jóváhagyással történhet.
