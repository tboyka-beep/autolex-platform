# Autolex műveleti központ

Az Autolex 2.9.0-tól a WordPress adminisztrációban az **Autolex Platform → Műveleti központ** oldal mutatja az EEA háttérimport állapotát.

## Mit jelez

- az összes és a befejezett EEA-forrásfeladatot;
- a függő, futó, újrapróbálkozó és hibás célokat;
- a következő ütemezett futást és az utolsó aktivitást;
- a normalizált EU-jármű- és motorváltozatok számát;
- a beolvasott EEA-sorokat és a konzervatív járműkapcsolati javaslatokat;
- legfeljebb húsz friss hibás feladat biztonságos diagnosztikáját.

## Biztonságos adminműveletek

### Import azonnali felébresztése

Újraütemezi az `autolex_eea_sync_batch` WordPress-eseményt öt másodpercen belülre. Nem módosít forrás- vagy járműadatot.

### Beragadt feladatok helyreállítása

Csak a legalább tizenöt perce `running` állapotban lévő feladatokat teszi `retry` állapotba, és csak az öt percnél régebbi globális zárat távolítja el. Az aktív feldolgozást nem szakítja meg.

### Hibás célok újrapróbálása

A `failed` állapotú forráscélokat visszahelyezi a feldolgozási sorba, nullázza a próbálkozásszámot, de a korábbi hibaüzenetet megőrzi. A művelet megerősítést kér.

## Jogosultság és védelem

Minden művelethez `manage_options` jogosultság és WordPress nonce szükséges. A nyilvános REST-végpontok továbbra is csak összesített, nem érzékeny adatot közölnek.
