#!/usr/bin/env bash
set -euo pipefail

front='theme/autolex-theme/front-page.php'
test -f "$front"
php -l "$front" >/dev/null

grep -Fq '$autolex_render_home_slot = static function' "$front"
grep -Fq 'ob_start();' "$front"
grep -Fq 'do_action($hook_name);' "$front"
grep -Fq 'ob_get_clean()' "$front"
grep -Fq '$fallback_renderer();' "$front"

for hook in \
  autolex_theme_coverage_panel \
  autolex_theme_popular_brands \
  autolex_theme_metric_strip
do
  grep -Fq "'$hook'," "$front"
  if grep -Fq "do_action('$hook')" "$front"; then
    echo "Homepage hook bypasses the buffered slot renderer: $hook" >&2
    exit 1
  fi
done

if [ "$(grep -Fc '$autolex_render_home_slot(' "$front")" -ne 3 ]; then
  echo 'Expected exactly three homepage slot renderer calls.' >&2
  exit 1
fi

grep -Fq 'alx-coverage-fallback' "$front"
grep -Fq 'alx-brand-fallback-grid' "$front"
grep -Fq 'alx-live-metric--fallback' "$front"
grep -Fq 'Lefedettség' "$front"

for fake_count in '15 842 654' '320 000' '98 765' '3 241' '100 000+' '5 000+' '1200+' '99.1%'; do
  if grep -Fq "$fake_count" "$front"; then
    echo "fabricated public dashboard count found in theme fallback: $fake_count" >&2
    exit 1
  fi
done

echo 'Autolex dynamic homepage slot contract passed.'
