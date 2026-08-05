#!/usr/bin/env bash
set -euo pipefail

THEME='theme/autolex-theme'
AUDIT='docs/AUTOLEX-THEME-MIGRATION-AUDIT.md'
FUNCTIONS="$THEME/functions.php"
STYLE="$THEME/style.css"

required=(
  "$AUDIT"
  "$STYLE"
  "$FUNCTIONS"
  "$THEME/header.php"
  "$THEME/footer.php"
  "$THEME/assets/js/theme-shell.js"
  "$THEME/assets/css/states.css"
  "$THEME/assets/css/content.css"
)

for file in "${required[@]}"; do
  test -f "$file" || { echo "missing migration artifact: $file"; exit 1; }
done

if grep -RniE '(^|[^[:alnum:]_-])\.ct-[[:alnum:]_-]+' "$THEME"; then
  echo 'Blocksy .ct-* selector found in own theme'
  exit 1
fi

if grep -RniE 'blocksy|ct_options|ct_get_|ct_component' "$THEME"; then
  echo 'Blocksy-specific dependency found in own theme'
  exit 1
fi

if grep -qiE '^Template:[[:space:]]*' "$STYLE"; then
  echo 'own theme must not declare a parent theme'
  exit 1
fi

if grep -Rni '!important' "$THEME"; then
  echo '!important is forbidden in the own theme migration layer'
  exit 1
fi

grep -Fq "array('autolex-theme')" "$FUNCTIONS"
grep -Fq "array('autolex-theme', 'autolex-theme-states')" "$FUNCTIONS"
grep -Fq "wp_enqueue_script('autolex-theme-shell'" "$FUNCTIONS"

grep -Fq 'rögzíti az előző aktív téma stylesheet-nevét' "$AUDIT"
grep -Fq 'automatikusan visszaaktiválja az előző témát' "$AUDIT"
grep -Fq 'production aktiválás tilos' "$AUDIT"

echo 'Autolex theme migration contract passed.'
