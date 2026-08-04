#!/usr/bin/env bash
set -euo pipefail

THEME='theme/autolex-theme'
required=(style.css functions.php header.php footer.php index.php front-page.php assets/js/theme-shell.js)
for file in "${required[@]}"; do
  test -f "$THEME/$file" || { echo "missing theme file: $file"; exit 1; }
done

php_files=(functions.php header.php footer.php index.php front-page.php)
for file in "${php_files[@]}"; do
  php -l "$THEME/$file" >/dev/null
done

node --check "$THEME/assets/js/theme-shell.js"

grep -Fq 'Theme Name: Autolex' "$THEME/style.css"
grep -Fq -- '--alx-primary: #1769e8' "$THEME/style.css"
grep -Fq -- '--alx-safety: #d92d3f' "$THEME/style.css"
grep -Fq 'prefers-reduced-motion' "$THEME/style.css"
grep -Fq 'wp_body_open' "$THEME/header.php"
grep -Fq 'aria-expanded="false"' "$THEME/header.php"
grep -Fq 'wp_footer()' "$THEME/footer.php"
grep -Fq "register_nav_menus" "$THEME/functions.php"

grep -Fq 'role="tablist"' "$THEME/front-page.php"
grep -Fq 'role="tabpanel"' "$THEME/front-page.php"
grep -Fq 'data-alx-search-type' "$THEME/front-page.php"
grep -Fq 'name="search_type" value="vehicle"' "$THEME/front-page.php"
grep -Fq 'name="vin"' "$THEME/front-page.php"
grep -Fq 'name="engine_code"' "$THEME/front-page.php"
grep -Fq "ArrowRight" "$THEME/assets/js/theme-shell.js"
grep -Fq "ArrowLeft" "$THEME/assets/js/theme-shell.js"
grep -Fq "aria-selected" "$THEME/assets/js/theme-shell.js"
grep -Fq "control.disabled = !active" "$THEME/assets/js/theme-shell.js"

if grep -R -n -E 'prefers-color-scheme:[[:space:]]*dark|\.ct-|#000000|background:[[:space:]]*#000' "$THEME"; then
  echo 'forbidden dark or Blocksy-specific marker found in own theme'
  exit 1
fi

echo 'Autolex light theme foundation contract passed.'
