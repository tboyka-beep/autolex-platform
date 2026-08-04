#!/usr/bin/env bash
set -euo pipefail

THEME='theme/autolex-theme'
required=(style.css functions.php header.php footer.php index.php front-page.php search.php 404.php assets/css/states.css assets/js/theme-shell.js)
for file in "${required[@]}"; do
  test -f "$THEME/$file" || { echo "missing theme file: $file"; exit 1; }
done

php_files=(functions.php header.php footer.php index.php front-page.php search.php 404.php)
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
grep -Fq "autolex-theme-states" "$THEME/functions.php"

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

grep -Fq 'id="alx-not-found-title"' "$THEME/404.php"
grep -Fq 'role="search"' "$THEME/404.php"
grep -Fq 'id="alx-search-title"' "$THEME/search.php"
grep -Fq 'have_posts()' "$THEME/search.php"
grep -Fq 'alx-search-empty' "$THEME/search.php"
grep -Fq '.alx-state-page' "$THEME/assets/css/states.css"
grep -Fq '.alx-result-grid' "$THEME/assets/css/states.css"

if grep -R -n -E 'prefers-color-scheme:[[:space:]]*dark|\.ct-|#000000|background:[[:space:]]*#000' "$THEME"; then
  echo 'forbidden dark or Blocksy-specific marker found in own theme'
  exit 1
fi

echo 'Autolex light theme foundation contract passed.'
