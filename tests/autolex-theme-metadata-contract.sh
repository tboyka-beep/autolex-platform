#!/usr/bin/env bash
set -euo pipefail

THEME='theme/autolex-theme'
FUNCTIONS="$THEME/functions.php"

php -l "$FUNCTIONS" >/dev/null

grep -Fq "function autolex_theme_has_seo_plugin" "$FUNCTIONS"
grep -Fq "defined('WPSEO_VERSION')" "$FUNCTIONS"
grep -Fq "defined('RANK_MATH_VERSION')" "$FUNCTIONS"
grep -Fq "function autolex_theme_canonical_url" "$FUNCTIONS"
grep -Fq 'rel=\"canonical\"' "$FUNCTIONS"
grep -Fq "function autolex_theme_meta_description" "$FUNCTIONS"
grep -Fq "wp_strip_all_tags" "$FUNCTIONS"
grep -Fq "function autolex_theme_breadcrumb_schema" "$FUNCTIONS"
grep -Fq "'@type'           => 'BreadcrumbList'" "$FUNCTIONS"
grep -Fq "apply_filters('autolex_theme_vehicle_schema'" "$FUNCTIONS"
grep -Fq "\$vehicle_schema['@type'] === 'Vehicle'" "$FUNCTIONS"
grep -Fq "trim(\$vehicle_schema['name']) !== ''" "$FUNCTIONS"
grep -Fq "wp_json_encode" "$FUNCTIONS"
grep -Fq "JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES" "$FUNCTIONS"
grep -Fq "add_action('wp_head', 'autolex_theme_document_metadata', 2)" "$FUNCTIONS"

if grep -Fq "'@type' => 'Vehicle'" "$FUNCTIONS"; then
  echo 'hard-coded Vehicle schema found; vehicle data must come from the plugin filter'
  exit 1
fi

if grep -E -n "(horsepower|fuelCapacity|vehicleConfiguration|productionDate)[[:space:]]*=>[[:space:]]*['\"][^'\"]+" "$FUNCTIONS"; then
  echo 'hard-coded vehicle claim found in theme metadata'
  exit 1
fi

echo 'Autolex metadata contract passed.'
