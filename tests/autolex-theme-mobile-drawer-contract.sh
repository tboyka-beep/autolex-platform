#!/usr/bin/env bash
set -euo pipefail

JS='theme/autolex-theme/assets/js/theme-shell.js'
HEADER='theme/autolex-theme/header.php'

test -f "$JS"
test -f "$HEADER"
node --check "$JS"
php -l "$HEADER" >/dev/null

grep -Fq 'aria-controls="alx-mobile-menu"' "$HEADER"
grep -Fq 'aria-expanded="false"' "$HEADER"
grep -Fq 'id="alx-mobile-menu" hidden' "$HEADER"

grep -Fq 'const getFocusableItems' "$JS"
grep -Fq "document.body.classList.add('alx-menu-open')" "$JS"
grep -Fq "document.body.classList.remove('alx-menu-open')" "$JS"
grep -Fq 'if (firstItem) firstItem.focus()' "$JS"
grep -Fq "if (event.key === 'Escape')" "$JS"
grep -Fq "if (event.key !== 'Tab') return" "$JS"
grep -Fq 'event.shiftKey && document.activeElement === firstItem' "$JS"
grep -Fq '!event.shiftKey && document.activeElement === lastItem' "$JS"
grep -Fq "event.target.closest('a[href]')" "$JS"
grep -Fq 'closeMenu(true)' "$JS"

echo 'Autolex mobile drawer accessibility contract passed.'
