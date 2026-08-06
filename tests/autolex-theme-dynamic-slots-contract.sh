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

for fallback in \
  'A tényleges lefedettségi adatok betöltése folyamatban.' \
  'A népszerű márkák a valós használati adatok alapján jelennek meg.' \
  'Forrásrekordok'
do
  grep -Fq "$fallback" "$front"
done

echo 'Autolex dynamic homepage slot contract passed.'
