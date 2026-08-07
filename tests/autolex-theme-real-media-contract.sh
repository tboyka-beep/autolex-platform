#!/usr/bin/env bash
set -euo pipefail

FRONT='theme/autolex-theme/front-page.php'
FUNCTIONS='theme/autolex-theme/functions.php'
BRIDGE='plugin/autolex-platform/includes/class-autolex-theme-data-bridge.php'
CSS='theme/autolex-theme/assets/css/real-media-polish.css'
DOC='docs/ALX-035-REAL-MEDIA-POLISH.md'

for file in "$FRONT" "$FUNCTIONS" "$BRIDGE" "$CSS" "$DOC"; do
  test -f "$file" || { echo "missing ALX-035 file: $file"; exit 1; }
done
php -l "$FRONT" >/dev/null
php -l "$FUNCTIONS" >/dev/null
php -l "$BRIDGE" >/dev/null

grep -Fq "autolex-theme-real-media-polish" "$FUNCTIONS"
grep -Fq "assets/css/real-media-polish.css" "$FUNCTIONS"
grep -Fq "autolex_theme_featured_vehicle" "$FRONT"
grep -Fq "autolex_theme_comparison_preview" "$FRONT"
grep -Fq "add_action('autolex_theme_featured_vehicle'" "$BRIDGE"
grep -Fq "add_action('autolex_theme_comparison_preview'" "$BRIDGE"
grep -Fq 'registration_count DESC' "$BRIDGE"
grep -Fq 'engine_capacity_cc' "$BRIDGE"
grep -Fq 'engine_power_kw' "$BRIDGE"
grep -Fq 'co2_wltp' "$BRIDGE"

# Approved stock photo identities and source documentation are immutable evidence.
for id in \
  'photo-1773793097960-5dbdcbc081c0' \
  'photo-1523983302122-73e869e1f850' \
  'photo-1537994725085-277ef72d1cb6'; do
  grep -Fq "$id" "$FRONT" || { echo "approved stock photo missing: $id"; exit 1; }
done
for page in '2kO5bZFLj1E' '_8WDl2zgB_0' 'sQm5sKi4i0w'; do
  grep -Fq "$page" "$DOC" || { echo "stock attribution page missing: $page"; exit 1; }
done
grep -Fq 'Unsplash License' "$DOC"

# Public images must have explicit dimensions and decode/loading semantics.
grep -Eq '<img[^>]+width="[0-9]+"[^>]+height="[0-9]+"' "$FRONT"
grep -Fq 'decoding="async"' "$FRONT"
grep -Fq 'loading="eager"' "$FRONT"
grep -Fq 'loading="lazy"' "$FRONT"

# Old demo artwork, synthetic compare labels and fabricated public counts are forbidden.
for forbidden in \
  'alx-car-silhouette' \
  'Jármű A' \
  'Jármű B' \
  '15 842 654' \
  '320 000+' \
  '98 765+' \
  '3 241' \
  '99.1%'; do
  if grep -Fq "$forbidden" "$FRONT"; then
    echo "forbidden public placeholder/demo value found: $forbidden"
    exit 1
  fi
done

# User-facing PHP/CSS must not carry development-only wording.
if grep -Ein '\b(lorem ipsum|dummy|demo data|test data|placeholder text)\b' "$FRONT" "$CSS"; then
  echo 'development-only public wording found'
  exit 1
fi

echo 'Autolex ALX-035 real-media contract passed.'
