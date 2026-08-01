=== Autolex Platform ===
Contributors: autolex
Requires at least: 6.4
Requires PHP: 8.1
Stable tag: 3.3.0
License: Proprietary

Az Autolex autós adatplatform központi WordPress-bővítménye.

== Description ==

Az Autolex Platform EU/EGT fókuszú, forrásállapotot és adatminőséget kezelő
autóadat-rendszer. A nyilvános portál, a szűrhető katalógus, a jármű- és
motoradatok, a forrásbizonyítékok, valamint a FrissAuto-integráció elkülönített
modulokból épül fel.

== Changelog ==

= 3.3.0 =
* Akadálymentes globális autókeresés márka, modell, generáció, motor és motorkód alapján.
* Billentyűzetes combobox/listbox navigáció, URL-ben megosztható keresés és JavaScript nélküli fallback.
* Legfeljebb három járműves, URL-paraméteres összehasonlítás műszaki, adatminőségi és Safety Gate jelzésekkel.
* Kapcsolódó generációk és motorváltozatok a jármű-adatlapon, visszahívási összesítéssel és biztonságos katalógus-visszalépéssel.
* A FrissAuto-ajánlatok konkrét termék, specifikációs keresés és univerzális fallback szintjeinek egyértelmű jelölése.
* Vehicle és BreadcrumbList JSON-LD kizárólag tényleges, nem üres katalógusadatokból.
* Stabil canonical, meta title, meta description, robots policy és verziózott SEO-adatcache.
* Új keresési, összehasonlítási, kapcsolódójármű-, SEO-, teljesítmény- és accessibility regressziós contractok.

= 3.2.1 =
* Teljes prémium világos jármű-adatlap külön áttekintő, navigációs és adatbizalmi blokkal.
* Karbantartási állítások, elsődleges és támogató források külön bizonyítékkártyákon.
* Márka–modell alapú Safety Gate visszahívási lekérdezés, VIN-ellenőrzési figyelmeztetéssel.
* Háromszintű FrissAuto-ajánlás: konkrét termék, specifikációs keresés és univerzális fallback.
* A specifikációs keresések konkrét termék-URL nélkül is láthatók és használhatók.
* Reszponzív mobil adatlap, sticky szakasznavigáció, betöltési, üres és hibaállapotok.
* Külön jármű-adatlap regressziós contract a GitHub-hosted és home-server quality gate-ben.

= 3.2.0 =
* Teljes prémium világos designrendszer a főoldal és a katalógus minden komponensére.
* Új forráskártya-, adatfolyam-, módszertan-, CTA-, járműkártya- és találati toolbar megjelenés.
* Sticky asztali szűrő és mobil drawer, reszponzív 3/2/1 oszlopos járműrács.
* Generáció- és ellenőrzésiállapot-szűrés, JavaScript nélküli rendezés és stabil márka–modell váltás.
* Akadálymentes fókuszállapotok, Escape-bezárás, aria-expanded kezelés és csökkentett mozgás támogatása.
* Eurostat és EAFO tesztelt adapterállapot, élő Safety Gate forrásvalidálás túlállítás nélkül.
* Külön design- és katalógusszerződés-regressziós tesztek.

= 3.1.0 =
* Világos, szerkesztőségi Autolex portál törtfehér háttérrel és grafit tipográfiával.
* Biztonságos Műveleti központ az EEA-feldolgozás felügyeletéhez.
* Eurostat JSON-stat 2.0 és EAFO forrásmanifeszt-adapteralap.
* Élő, read-only Safety Gate XML-felderítés és fail-closed validálás.
* Same-repo PR-re korlátozott self-hosted quality gate.

= 3.0.0 =
* Teljesen új, adatportál-központú nyilvános felület.
* Külső fizetős sablon, képbank, betűkészlet vagy JavaScript-könyvtár nélküli megvalósítás.
* Új információgazdag főoldal valós katalógus-, motor-, forrás- és EEA-számlálókkal.
* Márka, modell, üzemanyag, évjárat, teljesítmény, motorkód és adatminőség szerinti szűrés.
* A járműkártyákon A/B/C adatminőség, ellenőrzési állapot, forrásbizonyíték és EU-megfigyelés.
* Nyilvános, géppel olvasható forrásjegyzék kizárólag ingyenes és hivatalos forrásokkal.
* EEA, Eurostat, EAFO, Safety Gate, EU típusjóváhagyási és CoC forrásstratégia.

= 2.8.1 =
* EEA-kompatibilis teljes márkaindex-lekérdezés az API saját lapozójához.
* Kompakt ütemezési, újrapróbálási és zárolási diagnosztika.

= 2.8.0 =
* A 2022-es és 2023-as végleges EEA személyautó-táblák bevonása.
* A 2024-es és 2025-ös új autók külön, előzetes minőségi állapottal kerülnek be.
* Teljes hivatalos márkaindexből induló újmárka- és újmodell-felderítés.
* Gyorsabb, 30 másodperces kötegütemezés, óránkénti sor-karbantartás és beragadt feladatok helyreállítása.
* Az előzetes forrás nem írhat felül végleges vagy ellenőrzött motoradatot.

= 2.7.0 =
* Automatikus, kötegelt szinkron a hivatalos EEA Discodata végleges 2010–2021-es személyautó-adataiból.
* Márka, kereskedelmi név és év szerinti célzott lekérdezések minden örökölt modell- és motorjelöléshez.
* Megismételhető forrásmegfigyelések, mezőszintű EEA-bizonyíték és konzervatív motorváltozat-illesztés.
* Külön nyilvános állapotvégpont a forráscélok, motorjavaslatok és járműkapcsolatok követéséhez.

= 2.6.0 =
* Külön motorváltozat-, járműkapcsolat- és forrásbizonyíték-adatréteg.
* Automatikus, teljes katalógusra kiterjedő motoradat-feldolgozási sor.
* Nyilvános, márkánkénti motoradat-lefedettségi és minőségellenőrzési végpont.
* A motoradatok állapotai elkülönítik a függő, ellenőrzött, VIN-köteles és ellentmondásos adatokat.

= 2.5.1 =
* A konkrét FrissAuto-termékképek hivatalos 500×500-as kiszolgálási URL-jei.

= 2.5.0 =
* Konkrét FrissAuto-termékkártyák képpel, árral és közvetlen terméklinkkel.
* Az illesztett termék hiányának egyértelmű jelzése és biztonságos általános ajánlatok.
* A főoldali FrissAuto-képek alatti örökölt piros árnyék/díszítés eltávolítása.

= 2.4.3 =
* Általános FrissAuto-ajánlások, ha nincs megfelelő specifikáció- vagy motorkód-találat.
* Ablaktörlő/szélvédőápolás, kormányvédő és autóápolás biztonsági méretjelöléssel.

= 2.4.2 =
* Verziózott karbantartási REST-válaszok az azonnali adatfrissítéshez.

= 2.4.1 =
* A VIN-ellenőrzést igénylő állítások státuszának pontos tárolása és újravetése.

= 2.4.0 =
* Normalizált karbantartási állítás-, forrásbizonyíték- és termékillesztési adatmodell.
* BMW E87 118d / N47D20 többforrásos karbantartási pilot.
* Specifikáció és motorkód alapján illesztett FrissAuto-keresések.

= 2.3.3 =
* A teljes katalógusblokk középre igazítása széles képernyőn és fehér katalóguscím.

= 2.3.2 =
* Középre rendezett autókártyák és jól olvasható, fehér modellnevek.

= 2.3.1 =
* Gyors, lapozott autókatalógus márka- és szöveges szűréssel.
* Többszavas élő keresés és stabil járműadatlap-linkek.
* Rövid keresési gyorsítótár és reszponzív találati kártyák.

= 2.2.0 =
* Elkülönített, normalizált EU-járműkatalógus márka-, modell-, változat- és piaci táblákkal.
* Streaming EEA CSV-importáló WP-CLI parancs nagy adatállományok feldolgozásához.
* Nyilvános, csak összesített EU-lefedettségi állapotvégpont.
* M1 személyautó és N1 kishaszonjármű fókusz, EU/EGT piaci jelenlét alapján.

= 2.1.0 =
* Egységes, reszponzív Autolex vizuális réteg a Blocksy-alapú nyilvános oldalhoz.
* Letisztult hero, kereső, kártyák, fejléc, lábléc és sütiértesítő.
* Akadálymentes fókuszállapotok és csökkentett mozgás támogatása.

= 2.0.1 =
* Nyilvános, csak olvasható rendszerállapot-végpont a telepítés ellenőrzéséhez.

= 2.0.0-dev =
* GitHub Actions ellenőrzési és kézi cPanel-telepítési folyamat.
* Bővíthető plugin bootstrap struktúra.
