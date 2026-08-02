# Autolex 4.2 — design- és adatalap

## Stabil kiinduló állapot

- Kiinduló `main`: `1b0ea2c20d01855b4116f1f1fce9ec52fb86e49a`.
- Élő pluginverzió: `4.1.0`.
- Nyitott pull request az audit kezdetén: nincs.
- A 4.1 kiadás bizonyított production deployja: workflow `30741724552`, cPanel job `91480268956`, élő HTTP `200`, service/status/version: `autolex-platform / ok / 4.1.0`.
- A legutóbbi teljes home-server kapu: run `30741691465`, job `91480152653`, host `home-server`, artifact `8831495991`.

## Auditált jelenlegi rétegek

A plugin jelenleg külön, egymás után betöltött portál-, katalógus-, járműélmény- és főoldali CSS-réteget használ. A 4.2 célja ezek fokozatos összevonása egyetlen token- és komponensrendszerbe úgy, hogy a Blocksy és a WordPress admin változatlan maradjon.

Meglévő adatmagok:

- EU/EEA járműkatalógus;
- ország- és évszintű piaci előfordulások;
- importnapló és forrásmegfigyelések;
- motorváltozat-katalógus;
- EEA szinkron és javaslatok;
- karbantartási bizonyítékok;
- Safety Gate, Eurostat és EAFO integrációk.

A jelenlegi `autolex_eu_observations` tábla már őriz forráskódot, évet, státuszt, tartalomhash-t és importidőt, de nem biztosít általános, mezőszintű provenienciát több, eltérő típusú forráshoz. A 4.2-ben ezt visszafelé kompatibilis, általános forrás- és állításmodell egészíti ki.

## Kötelező 4.2 forrásproveniencia

Minden új vagy frissített műszaki állítás a következő fogalmakhoz kötődik:

### Forrásrekord

- stabil forrásazonosító;
- forrástípus: `manufacturer`, `official_registry`, `official_statistics`, `trusted_secondary`;
- cím és kiadó/szervezet;
- nyilvános URL;
- dokumentum- vagy rekordazonosító;
- kiadási/adatév;
- lekérés UTC-időpontja;
- tartalom SHA-256, amikor jogszerűen és technikailag előállítható;
- licenc- vagy felhasználhatósági megjegyzés;
- aktív, visszavont vagy lecserélt állapot.

### Állításrekord

- entitástípus és entitásazonosító;
- mező vagy mezőcsoport stabil kulcsa;
- normalizált érték és mértékegység;
- forrásrekord kapcsolata;
- forráson belüli hely vagy hivatkozás;
- megerősítési állapot;
- VIN-kötöttség;
- importbatch és időbélyegek;
- kanonikusérték-jelölés;
- konfliktuscsoport.

### Engedélyezett megerősítési állapotok

- `manufacturer_source` — gyártói elsődleges forrás;
- `official_registry` — hivatalos nyilvántartás;
- `multi_source_match` — legalább két független, egyező forrás;
- `single_source_confirmed` — egy hiteles forrásból igazolt;
- `source_conflict` — egymásnak ellentmondó források;
- `incomplete` — hiányos vagy részleges adat;
- `vin_required` — VIN alapján ellenőrizendő.

`multi_source_match` csak akkor állítható be, ha legalább két külön forrásrekord egymással egyező, normalizált értéket támaszt alá. Gyártói adat önmagában `manufacturer_source` vagy `single_source_confirmed`, nem hamis kétforrásos megerősítés.

## Konfliktus- és kanonizálási szabály

1. Eltérő forrásérték nem írható felül csendben.
2. Minden változat külön állításrekordként megmarad.
3. A konfliktus `source_conflict` státuszt kap.
4. Kanonikus érték csak dokumentált prioritási szabály alapján választható.
5. A publikus adatlap megjeleníti a konfliktust, a forrásokat és a lekérési dátumot.
6. Hiányzó adatból nem készül becslés.

## Importkövetelmények

- idempotens batchazonosító és forrás-fingerprint;
- dry-run mód;
- visszafelé kompatibilis sémafrissítés;
- duplikációvédelem;
- mező- és forrásszintű validáció;
- pontos elfogadott, frissített, kihagyott, konfliktusos és hibás rekordszám;
- adatvesztő újraimport tiltása;
- minden importhoz összesítő és tartalomhash.

## Vizuális backlog

1. A vizuális tokenek áthelyezése verziózott, önálló design-system stylesheetbe.
2. A fejléc, mobilmenü és footer teljes komponensszintű egységesítése.
3. BCS INFOSEC-minőségű, autós/adatplatform hero valódi lefedettségi adatokkal.
4. Főoldali blokkok: márkák, modellek, lefedettség, Safety Gate, frissítések, összehasonlítás, Tudástár, FrissAuto.
5. Katalógus: egységes szűrődrawer, sticky desktop filter, kártya- és badge-rendszer.
6. Márka-, modell-, generáció- és motoroldalak egységes információs hierarchiája.
7. Jármű-adatlap: áttekintés, motor, műszaki adatok, méretek/tömeg, hajtás/váltó, folyadékok, kerék/gumi, emisszió, biztonság, visszahívások és források.
8. Összehasonlítás: legfeljebb három jármű, forrás- és konfliktusállapotokkal.
9. Skeleton, üres, részleges, konfliktusos és hibaállapot.
10. Mobil/tablet/desktop, billentyűzet, ARIA, reduced-motion, SEO és performance contractok.

## Következő koherens egység

A következő kapu visszafelé kompatibilis `Autolex_Source_Provenance` sémát és determinisztikus smoke contractot készít. A séma bevezetése önmagában nem módosít és nem töröl meglévő katalógusadatot.
