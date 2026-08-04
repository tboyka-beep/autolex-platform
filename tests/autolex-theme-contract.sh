#!/usr/bin/env bash
set -euo pipefail

THEME='theme/autolex-theme'
required=(style.css functions.php header.php footer.php index.php front-page.php search.php 404.php page.php single.php archive.php page-osszehasonlitas.php assets/css/states.css assets/css/content.css assets/css/comparison.css assets/js/theme-shell.js)
for file in "${required[@]}"; do
  test -f "$THEME/$file" || { echo "missing theme file: $file"; exit 1; }
done

php_files=(functions.php header.php footer.php index.php front-page.php search.php 404.php page.php single.php archive.php page-osszehasonlitas.php)
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
grep -Fq "autolex-theme-content" "$THEME/functions.php"
grep -Fq "is_page('osszehasonlitas')" "$THEME/functions.php"
grep -Fq "autolex-theme-comparison" "$THEME/functions.php"

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

grep -Fq "post_class('alx-document')" "$THEME/page.php"
grep -Fq "post_class('alx-document alx-article')" "$THEME/single.php"
grep -Fq 'alx-archive-grid' "$THEME/archive.php"
grep -Fq 'the_posts_pagination' "$THEME/archive.php"
grep -Fq '.alx-document-layout' "$THEME/assets/css/content.css"
grep -Fq '.alx-archive-grid' "$THEME/assets/css/content.css"

grep -Fq 'id="alx-comparison-title"' "$THEME/page-osszehasonlitas.php"
grep -Fq 'aria-live="polite"' "$THEME/page-osszehasonlitas.php"
grep -Fq 'alx-comparison-plugin-output' "$THEME/page-osszehasonlitas.php"
grep -Fq 'get_the_content()' "$THEME/page-osszehasonlitas.php"
grep -Fq 'alx-comparison-empty' "$THEME/page-osszehasonlitas.php"
grep -Fq 'role="alert"' "$THEME/page-osszehasonlitas.php"
grep -Fq '.alx-comparison-workspace' "$THEME/assets/css/comparison.css"
grep -Fq 'overflow-x: auto' "$THEME/assets/css/comparison.css"
grep -Fq 'min-width: 720px' "$THEME/assets/css/comparison.css"

if grep -R -n -E 'prefers-color-scheme:[[:space:]]*dark|\.ct-|#000000|background:[[:space:]]*#000' "$THEME"; then
  echo 'forbidden dark or Blocksy-specific marker found in own theme'
  exit 1
fi

echo 'Autolex light theme foundation contract passed.'
