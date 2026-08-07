#!/usr/bin/env bash
set -euo pipefail

HEADER='theme/autolex-theme/header.php'
STYLE='theme/autolex-theme/style.css'

test -f "$HEADER"
test -f "$STYLE"
php -l "$HEADER" >/dev/null

# Reference dashboard header: repository-owned vector mark, compact navigation,
# explicit search affordance and blue account CTA.
grep -Fq 'alx-logo-gradient' "$HEADER"
grep -Fq 'alx-brand-word' "$HEADER"
grep -Fq 'alx-header-search-menu' "$HEADER"
grep -Fq 'alx-header-search-link' "$HEADER"
grep -Fq 'alx-header-search-field' "$HEADER"
grep -Fq 'Keresés' "$HEADER"
grep -Fq 'alx-utility-globe' "$HEADER"
grep -Fq 'Bejelentkezés' "$HEADER"
grep -Fq 'alx-login-link' "$HEADER"

# The compact trigger may reveal a search panel, but the old permanently boxed
# desktop input must not return.
if grep -Fq 'class="alx-header-search"' "$HEADER"; then
  echo 'legacy boxed desktop header search input found'
  exit 1
fi

grep -Fq -- '--alx-container: 1580px;' "$STYLE"
grep -Fq '.alx-brand-mark svg' "$STYLE"
grep -Fq '.alx-brand-word' "$STYLE"
grep -Fq '.alx-header-search-menu' "$STYLE"
grep -Fq '.alx-header-search-link' "$STYLE"
grep -Fq '.alx-header-search-panel' "$STYLE"
grep -Fq '.alx-header-search-field' "$STYLE"
grep -Fq '.alx-login-link' "$STYLE"
grep -Fq 'background: var(--alx-primary);' "$STYLE"
grep -Fq '@media (max-width: 1180px)' "$STYLE"
grep -Fq '.alx-header-search-link span { display: none; }' "$STYLE"

if grep -n -E 'url\([^)]*https?://' "$STYLE"; then
  echo 'remote shell asset found; reference shell must remain repository-owned'
  exit 1
fi

echo 'Autolex reference dashboard shell contract passed.'
