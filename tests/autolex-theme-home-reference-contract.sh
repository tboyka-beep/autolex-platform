#!/usr/bin/env bash
set -euo pipefail

HOME_CSS='theme/autolex-theme/assets/css/reference-dashboard.css'
FRONT_PAGE='theme/autolex-theme/front-page.php'

test -f "$HOME_CSS"
test -f "$FRONT_PAGE"
php -l "$FRONT_PAGE" >/dev/null

# One semantic main is opened by header.php; the front page must not nest one.
if grep -Fq '<main' "$FRONT_PAGE"; then
  echo 'front-page.php must not open a second <main> element'
  exit 1
fi

grep -Fq 'data-reference-dashboard="true"' "$FRONT_PAGE"
grep -Fq 'alx-home-rail--left' "$FRONT_PAGE"
grep -Fq 'alx-mobile-card' "$FRONT_PAGE"
grep -Fq 'alx-safety-card' "$FRONT_PAGE"
grep -Fq 'alx-home-center' "$FRONT_PAGE"
grep -Fq 'alx-home-rail--right' "$FRONT_PAGE"
grep -Fq 'alx-knowledge-card' "$FRONT_PAGE"
grep -Fq 'alx-metrics' "$FRONT_PAGE"
grep -Fq 'alx-home-cards--primary' "$FRONT_PAGE"
grep -Fq 'alx-safety-strip' "$FRONT_PAGE"

# Reference geometry: left rail + center dashboard + right rail.
grep -Fq 'grid-template-columns: 250px minmax(0, 1fr) 320px;' "$HOME_CSS"
grep -Fq 'grid-template-columns: repeat(5,minmax(0,1fr));' "$HOME_CSS"
grep -Fq 'grid-template-columns: 1.08fr 1fr 1.56fr;' "$HOME_CSS"
grep -Fq 'min-height: 296px;' "$HOME_CSS"
grep -Fq '.alx-brand-fallback-grid' "$HOME_CSS"
grep -Fq '.alx-knowledge-list' "$HOME_CSS"
grep -Fq '@media (max-width: 640px)' "$HOME_CSS"

# Repository-owned hero artwork and card illustrations only.
grep -Fq '<svg class="alx-car-silhouette"' "$FRONT_PAGE"
grep -Fq 'id="alx-car-body"' "$FRONT_PAGE"
grep -Fq 'id="alx-car-glass"' "$FRONT_PAGE"
grep -Fq 'id="alx-car-shadow"' "$FRONT_PAGE"
grep -Fq 'viewBox="0 0 820 420"' "$FRONT_PAGE"

if grep -n -E '<img[^>]+src="https?://' "$FRONT_PAGE" || grep -n -E 'url\([^)]*https?://' "$HOME_CSS"; then
  echo 'remote homepage visual found; dashboard artwork must stay repository-owned'
  exit 1
fi

if grep -n -E '\.ct-|!important|#[0]{3,6}([;[:space:]]|$)' "$HOME_CSS"; then
  echo 'forbidden Blocksy, important or black styling found in reference dashboard layer'
  exit 1
fi

echo 'Autolex reference-dashboard homepage contract passed.'
