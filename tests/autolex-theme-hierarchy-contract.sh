#!/usr/bin/env bash
set -euo pipefail

THEME='theme/autolex-theme'
required=(
  page-markak.php
  page-modellek.php
  page-generaciok.php
  page-motorok.php
  template-parts/hierarchy-shell.php
  assets/css/hierarchy.css
)

for file in "${required[@]}"; do
  test -f "$THEME/$file" || { echo "missing hierarchy theme file: $file"; exit 1; }
done

for file in page-markak.php page-modellek.php page-generaciok.php page-motorok.php template-parts/hierarchy-shell.php; do
  php -l "$THEME/$file" >/dev/null
done

for context in brand model generation engine; do
  grep -Fq "'context' => '$context'" "$THEME/page-$([ "$context" = brand ] && echo markak || [ "$context" = model ] && echo modellek || [ "$context" = generation ] && echo generaciok || echo motorok).php"
done

grep -Fq 'id="alx-hierarchy-title"' "$THEME/template-parts/hierarchy-shell.php"
grep -Fq 'aria-live="polite"' "$THEME/template-parts/hierarchy-shell.php"
grep -Fq 'data-hierarchy-level=' "$THEME/template-parts/hierarchy-shell.php"
grep -Fq 'alx-hierarchy-plugin-output' "$THEME/template-parts/hierarchy-shell.php"
grep -Fq 'get_the_content()' "$THEME/template-parts/hierarchy-shell.php"
grep -Fq 'alx-hierarchy-empty' "$THEME/template-parts/hierarchy-shell.php"
grep -Fq "is_page(array('markak', 'modellek', 'generaciok', 'motorok'))" "$THEME/functions.php"
grep -Fq 'autolex-theme-hierarchy' "$THEME/functions.php"
grep -Fq '.alx-hierarchy-workspace' "$THEME/assets/css/hierarchy.css"
grep -Fq '@media (max-width: 768px)' "$THEME/assets/css/hierarchy.css"

if grep -R -n -E 'prefers-color-scheme:[[:space:]]*dark|\.ct-|#000000|background:[[:space:]]*#000|!important' \
  "$THEME/page-markak.php" \
  "$THEME/page-modellek.php" \
  "$THEME/page-generaciok.php" \
  "$THEME/page-motorok.php" \
  "$THEME/template-parts/hierarchy-shell.php" \
  "$THEME/assets/css/hierarchy.css"; then
  echo 'forbidden dark, Blocksy-specific or important marker found in hierarchy unit'
  exit 1
fi

echo 'Autolex vehicle hierarchy contract passed.'
