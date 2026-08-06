#!/usr/bin/env bash
set -euo pipefail

HEADER='theme/autolex-theme/header.php'

test -f "$HEADER" || { echo 'missing theme header'; exit 1; }
php -l "$HEADER" >/dev/null

grep -Fq "sanitize_text_field(wp_unslash(\$_GET['q']))" "$HEADER"
grep -Fq 'value="<?php echo esc_attr($autolex_header_query); ?>"' "$HEADER"
grep -Fq 'id="alx-header-search-hint"' "$HEADER"
grep -Fq 'aria-describedby="alx-header-search-hint"' "$HEADER"
grep -Fq 'enterkeyhint="search"' "$HEADER"
grep -Fq 'autocomplete="off"' "$HEADER"

if grep -Fq 'value="<?php echo $_GET' "$HEADER"; then
  echo 'unsafe raw search query output found'
  exit 1
fi

echo 'Autolex accessible global search contract passed.'
