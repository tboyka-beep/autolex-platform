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

mapfile -d '' theme_sources < <(
  find "$THEME" -type f \( -name '*.php' -o -name '*.css' -o -name '*.js' -o -name '*.json' -o -name '*.svg' \) -print0
)

if ((${#theme_sources[@]} == 0)); then
  echo 'no theme source files found'
  exit 1
fi

if grep -HniE '(^|[^[:alnum:]_-])\.ct-[[:alnum:]_-]+' "${theme_sources[@]}"; then
  echo 'Blocksy .ct-* selector found in own theme'
  exit 1
fi

# Detect executable/configuration dependencies instead of harmless prose mentions.
# This remains fail-closed for Blocksy PHP APIs, handles, namespaces and asset paths.
if grep -HniE '(^|[^[:alnum:]_-])(blocksy[_-][[:alnum:]_-]*|ct_options|ct_get_[[:alnum:]_]*|ct_component[[:alnum:]_]*)([^[:alnum:]_-]|$)|/blocksy/' "${theme_sources[@]}"; then
  echo 'Blocksy-specific dependency found in own theme'
  exit 1
fi

if grep -qiE '^Template:[[:space:]]*' "$STYLE"; then
  echo 'own theme must not declare a parent theme'
  exit 1
fi

if grep -Hni '!important' "${theme_sources[@]}"; then
  echo '!important is forbidden in the own theme migration layer'
  exit 1
fi

assert_contains() {
  local needle="$1"
  local file="$2"
  local label="$3"

  if ! grep -Fq "$needle" "$file"; then
    echo "missing migration contract marker: $label"
    exit 1
  fi
}

assert_contains "array('autolex-theme')" "$FUNCTIONS" 'base stylesheet dependency'
assert_contains "array('autolex-theme', 'autolex-theme-states')" "$FUNCTIONS" 'route stylesheet dependency'
assert_contains "wp_enqueue_script('autolex-theme-shell'" "$FUNCTIONS" 'theme shell script enqueue'

assert_contains 'rögzíti az előző aktív téma stylesheet-nevét' "$AUDIT" 'previous theme capture requirement'
assert_contains 'automatikusan visszaaktiválja az előző témát' "$AUDIT" 'automatic rollback requirement'
assert_contains 'production aktiválás tilos' "$AUDIT" 'production activation blocker'

echo 'Autolex theme migration contract passed.'
