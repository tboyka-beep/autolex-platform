# ALX-035 — valós média és végleges publikus felület

Cél: a jóváhagyott Autolex referencia-dashboard vizuális irányának megtartása valós autófotókkal, végleges publikus szövegekkel és kizárólag adatbázisból származó számszerű adatokkal.

## Médiaforrások

A felülethez használt általános, nem járműazonosító autófotók Unsplash képek, az Unsplash License szerint ingyenesen felhasználhatók.

- Hero: Chandler Cruttenden — “Silver sedan driving on a rural road.” — https://unsplash.com/photos/2kO5bZFLj1E
- Korábbi kiemelt-jármű illusztráció: Arteum.ro — “black BMW sedan” — https://unsplash.com/photos/_8WDl2zgB_0
- Korábbi összehasonlítási illusztráció: Ethan Sexton — “white BMW sedan on black asphalt road during daytime” — https://unsplash.com/photos/sQm5sKi4i0w

A képek a `images.unsplash.com` CDN-ről, méretezett `auto=format&fit=crop` URL-lel töltődnek. Nem használunk fizetős stockot és nem építünk be bizonytalan eredetű képet.

### ALX-046+ járműazonossági szabály

A stock fotó kizárólag olyan felületen maradhat, amely nem állítja, hogy a kép egy név szerint megjelenített járművet ábrázol. Ha egy kártya, modell-, generáció- vagy összehasonlítási nézet konkrét márkát/modellt nevez meg, a kép csak ellenőrzött `make + model`, ismert generáció esetén generáció-egyezéssel jelenhet meg.

Ha nincs ellenőrzött mapping, a média fail-closed módon elmarad; általános vagy más modellhez tartozó stock fotó nem helyettesítheti. Az első ellenőrzött mapping az Opel Corsa F Wikimedia Commons forrásból, forrás- és kreditmetaadattal.

## Adatpolitika

- A főoldali darabszámokat a plugin `Autolex_Theme_Data_Bridge` szolgáltatja a valós EU/EGT katalógusból.
- Nem kerülhet publikus felületre demo-, teszt-, placeholder- vagy kitalált számszerű adat.
- A járműkártyák adatbázisból választott katalógusrekordokat jelenítenek meg; név szerint azonosított jármű mellett kizárólag ellenőrzött járműmédia használható.

## UX

- A jármű-, VIN- és motorkód-keresés működő űrlap marad.
- A márka- és katalóguslinkek valós útvonalakra mutatnak.
- A vizuális képek `loading`, `decoding`, méret és alternatív szöveg attribútumokat kapnak.
- Mobilon nincs vízszintes overflow; a képek `object-fit: cover` módban skálázódnak.
