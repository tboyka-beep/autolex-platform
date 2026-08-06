#!/usr/bin/env bash
set -euo pipefail

THEME='theme/autolex-theme'
TEMPLATE="$THEME/page-jarmu.php"
STYLES="$THEME/assets/css/vehicle.css"

php -l "$TEMPLATE" >/dev/null

required_sections=(
  attekintes
  motor
  muszaki-adatok
  meretek-tomeg
  hajtas-valto
  folyadekok
  kerek-gumi
  emisszio
  biztonsag
  visszahivasok
  forrasok
  kapcsolodo-modellek
  ajanlott-termekek
)

for section in "${required_sections[@]}"; do
  grep -Fq "'$section'" "$TEMPLATE" || {
    echo "missing vehicle detail section: $section"
    exit 1
  }
done

grep -Fq 'aria-label="<?php esc_attr_e('\''Adatlap adatcsoportjai' "$TEMPLATE"
grep -Fq 'aria-labelledby="alx-vehicle-directory-title"' "$TEMPLATE"
grep -Fq 'id="alx-vehicle-data"' "$TEMPLATE"
grep -Fq 'aria-live="polite"' "$TEMPLATE"
grep -Fq 'alx-vehicle-plugin-output' "$TEMPLATE"
grep -Fq 'the_content()' "$TEMPLATE"
grep -Fq 'nem jelenít meg becsült vagy kitalált értékeket' "$TEMPLATE"
grep -Fq "home_url('/visszahivasok/')" "$TEMPLATE"
grep -Fq "home_url('/forrasok/')" "$TEMPLATE"

grep -Fq '.alx-vehicle-directory-grid' "$STYLES"
grep -Fq 'grid-template-columns: repeat(3' "$STYLES"
grep -Fq 'grid-template-columns: repeat(2' "$STYLES"
grep -Fq 'grid-template-columns: minmax(0, 1fr)' "$STYLES"
grep -Fq 'scroll-margin-top: 7rem' "$STYLES"
grep -Fq 'var(--alx-primary' "$STYLES"
grep -Fq 'var(--alx-safety' "$STYLES"

if grep -n -E '\.ct-|!important|prefers-color-scheme:[[:space:]]*dark' "$TEMPLATE" "$STYLES"; then
  echo 'forbidden Blocksy, important or dark-mode marker found in vehicle detail unit'
  exit 1
fi

echo 'Autolex vehicle detail information architecture contract passed.'
