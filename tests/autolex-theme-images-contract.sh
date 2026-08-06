#!/usr/bin/env bash
set -euo pipefail

FUNCTIONS='theme/autolex-theme/functions.php'

test -f "$FUNCTIONS"
php -l "$FUNCTIONS" >/dev/null

grep -Fq 'function autolex_theme_image_attributes' "$FUNCTIONS"
grep -Fq "add_filter('wp_get_attachment_image_attributes', 'autolex_theme_image_attributes', 10, 3)" "$FUNCTIONS"
grep -Fq "\$attributes['decoding'] = 'async'" "$FUNCTIONS"
grep -Fq "empty(\$attributes['loading']) && empty(\$attributes['fetchpriority'])" "$FUNCTIONS"
grep -Fq "\$attributes['loading'] = 'lazy'" "$FUNCTIONS"
grep -Fq "\$attributes['sizes'] = '(max-width: 768px) 100vw, 50vw'" "$FUNCTIONS"

if grep -Fq "\$attributes['fetchpriority'] =" "$FUNCTIONS"; then
  echo 'theme must not invent image fetch priority'
  exit 1
fi

echo 'Autolex theme responsive image contract passed.'
