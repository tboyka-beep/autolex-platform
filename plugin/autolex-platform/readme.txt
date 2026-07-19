=== Autolex Platform ===
Contributors: autolex
Requires at least: 6.4
Requires PHP: 8.1
Stable tag: 2.8.1
License: Proprietary

Az Autolex autós adatplatform központi WordPress-bővítménye.

== Description ==

Ez a fejlesztési váz biztosítja az Autolex Platform biztonságosan bővíthető
WordPress-belépési pontját. A járműadatbázis, a kereső, a SEO-rendszer és a
FrissAuto-integráció külön modulokban épülhet rá.

== Changelog ==

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
