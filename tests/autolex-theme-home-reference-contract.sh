#!/usr/bin/env bash
set -euo pipefail

HOME_CSS='theme/autolex-theme/assets/css/home.css'
FRONT_PAGE='theme/autolex-theme/front-page.php'

test -f "$HOME_CSS"
test -f "$FRONT_PAGE"
php -l "$FRONT_PAGE" >/dev/null

grep -Fq 'grid-template-columns: repeat(5, minmax(0, 1fr))' "$HOME_CSS"
grep -Fq 'grid-template-columns: repeat(4, minmax(0, 1fr))' "$HOME_CSS"
grep -Fq '.alx-home-cards article' "$HOME_CSS"
grep -Fq '.alx-safety-strip' "$HOME_CSS"
grep -Fq 'rgba(217, 45, 63, .075)' "$HOME_CSS"
grep -Fq '@media (max-width: 640px)' "$HOME_CSS"
grep -Fq 'grid-template-columns: 1fr' "$HOME_CSS"

grep -Fq 'alx-metrics' "$FRONT_PAGE"
grep -Fq 'alx-home-cards' "$FRONT_PAGE"
grep -Fq 'Kiemelt jármű' "$FRONT_PAGE"
grep -Fq 'Márkák felfedezése' "$FRONT_PAGE"
grep -Fq 'Összehasonlítás' "$FRONT_PAGE"
grep -Fq 'Tudástár' "$FRONT_PAGE"
grep -Fq 'alx-safety-strip' "$FRONT_PAGE"

# The reference hero must use an original, repository-owned vector visual rather
# than a remote or uncertain-license vehicle image.
grep -Fq '<svg class="alx-car-silhouette"' "$FRONT_PAGE"
grep -Fq 'id="alx-car-body"' "$FRONT_PAGE"
grep -Fq 'id="alx-car-glass"' "$FRONT_PAGE"
grep -Fq 'id="alx-car-shadow"' "$FRONT_PAGE"
grep -Fq 'viewBox="0 0 760 300"' "$FRONT_PAGE"

# The SVG must render as artwork, not inherit the retired CSS-only silhouette
# border/skew treatment from the foundation stylesheet.
grep -Fq 'position: absolute;' "$HOME_CSS"
grep -Fq 'max-width: none;' "$HOME_CSS"
grep -Fq 'overflow: visible;' "$HOME_CSS"
grep -Fq 'border: 0;' "$HOME_CSS"
grep -Fq 'transform: none;' "$HOME_CSS"
grep -Fq 'width: 112%;' "$HOME_CSS"

if grep -n -E '<img[^>]+src="https?://' "$FRONT_PAGE" || grep -n -E 'url\([^)]*https?://' "$HOME_CSS"; then
  echo 'remote homepage visual found; hero artwork must stay repository-owned'
  exit 1
fi

if grep -n -E '\.ct-|!important|#[0]{3,6}([;[:space:]]|$)' "$HOME_CSS"; then
  echo 'forbidden Blocksy, important or black styling found in homepage reference layer'
  exit 1
fi

echo 'Autolex reference-aligned homepage continuation contract passed.'
