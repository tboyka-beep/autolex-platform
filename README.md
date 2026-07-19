# Autolex fejlesztési rendszer

Ez a repository az Autolex WordPress-platform verziókövetett forrását és a
cPaneles telepítési folyamatot tartalmazza.

## Könyvtárszerkezet

```text
.github/workflows/deploy-autolex.yml
plugin/autolex-platform/
├── autolex-platform.php
├── readme.txt
└── includes/
    └── class-autolex-platform.php
```

A telepíthető bővítmény forrása kizárólag a
`plugin/autolex-platform/` könyvtárban található. A workflow ebből készít
letölthető ZIP-csomagot.

## GitHub Secrets

Beállítási hely:

`Repository Settings → Secrets and variables → Actions → New repository secret`

Szükséges secret értékek:

- `CPANEL_FTP_HOST`
- `CPANEL_FTP_USER`
- `CPANEL_FTP_PASSWORD`
- `CPANEL_FTP_PORT`

Az ajánlott beállítás egy külön cPanel FTP-fiók, amelynek gyökérkönyvtára:

```text
public_html/wp-content/plugins/autolex-platform
```

Ezzel a fiókkal a workflow `server-dir` értéke biztonságosan `./` maradhat, így
az FTPS-hozzáférés csak az Autolex bővítmény könyvtárára korlátozható. A hostot
protokoll nélkül kell megadni, az explicit FTPS alapértelmezett portja általában
`21`. A tárhely hiányos TLS-tanúsítványlánca miatt a kapcsolat titkosított, de
a tanúsítvány hitelességének ellenőrzése ki van kapcsolva. A jelszót és a
tárhelyadatokat soha nem szabad a repository fájljaiba írni.

## Ellenőrzés

Pull request és a `main` ágra történő push esetén a GitHub Actions:

1. PHP 8.3 alatt szintaktikailag ellenőrzi az összes PHP-fájlt;
2. elkészíti az `autolex-platform.zip` telepítőcsomagot;
3. feltölti a ZIP-et workflow artifactként.

Aktivált bővítmény esetén a telepített verzió nyilvánosan, érzékeny adatok
nélkül ellenőrizhető:

```text
https://autolex.hu/wp-json/autolex/v1/status
```

## Telepítés cPanelre

Az ellenőrzött kód `main` ágba történő összevonása után a titkosított FTPS
deployment automatikusan elindul. A telepítés csak a PHP-ellenőrzés és a ZIP
összeállítása után futhat le. A távoli könyvtár teljes törlése nincs
engedélyezve; a szinkronizálás csak a verziókövetett bővítményfájlokat kezeli.

Szükség esetén kézzel is indítható:

`Actions → Validate and Deploy Autolex Plugin → Run workflow`

Egy nyitott pull request nem módosítja az élő webhelyet. Az összevonás viszont
automatikus production deploymentet indít, ezért csak sikeres ellenőrzéssel és
ellenőrzött tartalommal kerülhet változás a `main` ágba.

## Fejlesztési folyamat

1. Új `agent/...` fejlesztési ág.
2. Kód és dokumentáció módosítása.
3. Automatikus ellenőrzések lefuttatása pull requestben.
4. Ellenőrzött pull request összevonása a `main` ágba.
5. Automatikus production deployment a `main` ágról.
