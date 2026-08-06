#!/usr/bin/env bash
set -euo pipefail

FUNCTIONS='theme/autolex-theme/functions.php'

test -f "$FUNCTIONS"
php -l "$FUNCTIONS" >/dev/null

grep -Fq 'function autolex_theme_asset_version' "$FUNCTIONS"
grep -Fq "ltrim((string) \$relative_path, '/')" "$FUNCTIONS"
grep -Fq 'get_template_directory()' "$FUNCTIONS"
grep -Fq 'is_file($absolute_path)' "$FUNCTIONS"
grep -Fq 'filemtime($absolute_path)' "$FUNCTIONS"
grep -Fq "wp_get_theme()->get('Version')" "$FUNCTIONS"

assets=(
  style.css
  assets/css/states.css
  assets/css/content.css
  assets/css/home.css
  assets/css/catalog.css
  assets/css/hierarchy.css
  assets/css/vehicle.css
  assets/css/comparison.css
  assets/css/safety.css
  assets/css/sources.css
  assets/js/theme-shell.js
)

for asset in "${assets[@]}"; do
  grep -Fq "autolex_theme_asset_version('$asset')" "$FUNCTIONS" || {
    echo "asset is not file-versioned: $asset"
    exit 1
  }
done

grep -Fq "is_front_page()" "$FUNCTIONS"
grep -Fq "autolex-theme-home" "$FUNCTIONS"
grep -Fq "array('autolex-theme', 'autolex-theme-states')" "$FUNCTIONS"

if grep -Fq '$version = wp_get_theme()->get' "$FUNCTIONS"; then
  echo 'shared static theme version still used for all assets'
  exit 1
fi

echo 'Autolex theme asset cache-busting contract passed.'
