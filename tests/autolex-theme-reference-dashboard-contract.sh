#!/usr/bin/env bash
set -euo pipefail

THEME='theme/autolex-theme'
FRONT="$THEME/front-page.php"
HOME="$THEME/assets/css/reference-dashboard.css"
RESP="$THEME/assets/css/reference-dashboard-responsive.css"
FOOTER="$THEME/footer.php"
FOOTER_CSS="$THEME/assets/css/footer.css"
FUNCTIONS="$THEME/functions.php"
JS="$THEME/assets/js/theme-shell.js"
VISUAL='tests/visual/autolex-visual.spec.mjs'

for file in "$FRONT" "$HOME" "$RESP" "$FOOTER" "$FOOTER_CSS" "$FUNCTIONS" "$JS" "$VISUAL"; do
  test -f "$file" || { echo "missing reference-dashboard file: $file"; exit 1; }
done
php -l "$FRONT" >/dev/null
php -l "$FOOTER" >/dev/null
php -l "$FUNCTIONS" >/dev/null
node --check "$JS"
node --check "$VISUAL"

# Strict 10-point sequence markers.
grep -Fq 'alx-site-header' "$THEME/header.php"                         # 1 header/navigation
grep -Fq 'alx-hero' "$FRONT"                                          # 2 hero/search
grep -Fq 'alx-home-rail--left' "$FRONT"                               # 3 left rail
grep -Fq 'alx-home-rail--right' "$FRONT"                              # 4 right rail
grep -Fq 'alx-metrics' "$FRONT"                                       # 5 statistics strip
grep -Fq 'alx-home-cards--primary' "$FRONT"                           # 6 dashboard cards
grep -Fq 'alx-safety-strip' "$FRONT"                                  # 7 recall strip
grep -Fq 'alx-footer-newsletter' "$FOOTER"                            # 8 footer
grep -Fq '@media (max-width: 640px)' "$RESP"                          # 9 mobile/tablet
grep -Fq "name: 'reference', width: 1672, height: 941" "$VISUAL"      # 10 exact reference viewport
grep -Fq 'mainCount: document.querySelectorAll' "$VISUAL"
grep -Fq 'expect(diagnostics.mainCount).toBe(1)' "$VISUAL"
grep -Fq 'expect(diagnostics.scrollHeight).toBeLessThanOrEqual(950)' "$VISUAL"
grep -Fq 'expect(geometry.navCount).toBe(6)' "$VISUAL"
grep -Fq "viewport.name === 'reference'" "$VISUAL"
grep -Fq 'geometry.lastQuick.bottom' "$VISUAL"
grep -Fq 'geometry.footer.height' "$VISUAL"
grep -Fq 'data-reference-dashboard="true"' "$FRONT"

grep -Fq "autolex-theme-footer" "$FUNCTIONS"
grep -Fq "autolex-theme-reference-dashboard" "$FUNCTIONS"
grep -Fq "autolex-theme-reference-dashboard-responsive" "$FUNCTIONS"
grep -Fq "assets/css/reference-dashboard.css" "$FUNCTIONS"
grep -Fq "assets/css/reference-dashboard-responsive.css" "$FUNCTIONS"
grep -Fq "assets/css/footer.css" "$FUNCTIONS"
grep -Fq '.alx-footer-grid' "$FOOTER_CSS"
grep -Fq 'grid-template-columns: 1.45fr .8fr .8fr .8fr .8fr 1.7fr;' "$FOOTER_CSS"
grep -Fq 'grid-template-columns: 284px minmax(0, 1fr) 360px;' "$RESP"
grep -Fq 'li:has(> a[href*="/osszehasonlitas/"])' "$RESP"

# header.php owns the single semantic <main>; route templates may only add
# structural wrappers inside it. Protect every route, not only the screenshot matrix.
while IFS= read -r php_file; do
  if [ "$php_file" = "$THEME/header.php" ]; then
    continue
  fi
  if grep -n -E '<main([[:space:]>])' "$php_file"; then
    echo "duplicate semantic <main> found outside header.php: $php_file"
    exit 1
  fi
done < <(find "$THEME" -type f -name '*.php' -print | sort)

# No Blocksy-specific coupling is allowed in the custom-theme reference layer.
if grep -R -n -E '\.ct-|blocksy|Blocksy' "$FRONT" "$HOME" "$RESP" "$FOOTER" "$FOOTER_CSS" "$JS"; then
  echo 'Blocksy coupling found in the custom reference dashboard'
  exit 1
fi

# Reference demonstration numbers must never become fabricated public data.
for fake_count in '15 842 654' '320 000' '98 765' '3 241' '100 000+' '5 000+' '1200+' '99.1%'; do
  if grep -Fq "$fake_count" "$FRONT"; then
    echo "fabricated reference count found: $fake_count"
    exit 1
  fi
done

echo 'Autolex strict 10-point reference dashboard contract passed.'
