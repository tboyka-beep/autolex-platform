# Autolex home-server GitHub Actions runner

## Cél

Az Autolex külön repository-szintű self-hosted runnert kap a `home-server` gépen.
Nem használja a CompanyFlow, Trader Bot vagy BCS runner munkakönyvtárát és
szolgáltatását.

- repository: `tboyka-beep/autolex-platform`
- runner neve: `home-server-autolex`
- egyedi címke: `autolex`
- munkakönyvtár: `/home/tboy/actions-runner-autolex`
- elvárt címkék: `self-hosted`, `Linux`, `X64`, `autolex`

## 1. Regisztrációs token létrehozása

GitHubon nyisd meg:

`autolex-platform → Settings → Actions → Runners → New self-hosted runner`

Válaszd a Linux és x64 lehetőséget. A megjelenő regisztrációs token rövid ideig
érvényes, ezért közvetlenül a telepítés előtt másold ki. A tokent tilos fájlba,
commitba vagy shell historyba írni.

## 2. Telepítés a home-serveren

SSH-n jelentkezz be `tboy` felhasználóként, majd a repository egy friss
példányában futtasd:

```bash
cd /ahol/az/autolex-platform/van
read -rsp "GitHub runner token: " RUNNER_TOKEN && echo
export RUNNER_TOKEN
bash scripts/setup-home-server-runner.sh
unset RUNNER_TOKEN
```

A telepítő mindig a GitHub hivatalos `actions/runner` legfrissebb kiadását tölti
le, külön könyvtárba telepít, és systemd szolgáltatásként indítja el.

## 3. Ellenőrzés

GitHubon a runnernek `Idle` állapotban kell megjelennie ezzel a névvel:

```text
home-server-autolex
```

A szerveren:

```bash
sudo systemctl list-units --type=service | grep autolex
sudo systemctl status actions.runner.tboyka-beep-autolex-platform.home-server-autolex.service --no-pager
```

Ezután GitHubon indítsd el:

`Actions → Autolex Home Server Quality → Run workflow`

A futásnak a naplóban ki kell írnia:

```text
Host: home-server
```

## Szükséges helyi eszközök

A workflow nem használ `sudo`-t és nem telepít csomagokat minden futáskor. A
home-serveren előre elérhetőnek kell lennie:

- PHP 8.3 vagy újabb;
- Node.js;
- `zip`;
- `curl`;
- `jq`.

Ellenőrzés:

```bash
php --version
node --version
zip -v | head -n 2
curl --version | head -n 1
jq --version
```

## Átállítási sorrend

1. A külön Autolex runner regisztrálása és `Idle` állapotának ellenőrzése.
2. Az `Autolex Home Server Quality` kézi futtatása.
3. Csak zöld próba után a meglévő validate, portal, cron és deployment jobok
   `runs-on` értékének átállítása az `autolex` runnerre.
4. A GitHub-hosted futások eltávolítása, hogy ne fusson párhuzamosan két gépen
   ugyanaz a munka.

Ez a kétlépcsős átállás megakadályozza, hogy a repository összes munkája
végtelen várakozásba kerüljön egy még nem regisztrált runner miatt.
