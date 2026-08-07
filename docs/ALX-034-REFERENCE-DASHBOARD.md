# ALX-034 — Reference dashboard visual migration

## Cél

A publikus Autolex felületet fokozatosan a jóváhagyott világos, fehér/világosszürke, kék CTA-s autóadat-platform referencia felé visszük úgy, hogy minden lépés külön bizonyítható és regresszióbiztos maradjon.

## Elfogadott referenciairány

- kompakt, fehér felső navigáció;
- saját, repository-ban karbantartott kék Autolex jel;
- középre rendezett fő navigáció;
- könnyű keresés/nyelv/megjelenés vezérlők;
- elsődleges kék bejelentkezési CTA;
- háromoszlopos főoldali dashboard: bal gyorsmenü, középső hero és kereső, jobb adat/lefedettség;
- világos panelek, finom szegélyek, kis árnyékok, erős tipográfiai hierarchia;
- mobilon az információs sorrend és a hozzáférhetőség elsőbbséget élvez a desktop referencia geometriai másolásával szemben.

## Első egység — felső shell

Elkészült:

- eredeti inline SVG Autolex jel;
- referenciaarányú, kompakt fejléc;
- keresés link+ikon, globusz/HU és világos mód jelzés;
- kék `Bejelentkezés` CTA;
- régi boxed desktop search input eltávolítva;
- külön regressziós contract a shell vizuális szerződésére.

## Következő egységek

1. hero arányok és keresőpanel;
2. bal oldali gyorsmenü és biztonsági panel;
3. jobb oldali lefedettség és márkarács;
4. metrika-sáv;
5. alsó dashboard kártyák és visszahívási sáv;
6. footer;
7. mobil finomhangolás és teljes screenshot-mátrix.

Production deploy nem része ennek a fejlesztési ágnak.
