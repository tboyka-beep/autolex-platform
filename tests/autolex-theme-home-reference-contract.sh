#!/usr/bin/env bash
set -euo pipefail

HOME_CSS='theme/autolex-theme/assets/css/reference-dashboard.css'
POLISH_CSS='theme/autolex-theme/assets/css/real-media-polish.css'
FRONT_PAGE='theme/autolex-theme/front-page.php'
MEDIA_DOC='docs/ALX-035-REAL-MEDIA-POLISH.md'

for file in "$HOME_CSS" "$POLISH_CSS" "$FRONT_PAGE" "$MEDIA_DOC"; do
  test -f "$file" || { echo "missing ALX-035 reference file: $file"; exit 1; }
done
php -l "$FRONT_PAGE" >/dev/null

# One semantic main is opened by header.php; the front page must not nest one.
if grep -Fq '<main' "$FRONT_PAGE"; then
  echo 'front-page.php must not open a second <main> element'
  exit 1
fi

grep -Fq 'data-reference-dashboard="true"' "$FRONT_PAGE"
grep -Fq 'data-real-media="true"' "$FRONT_PAGE"
grep -Fq 'alx-home-rail--left' "$FRONT_PAGE"
grep -Fq 'alx-mobile-card' "$FRONT_PAGE"
grep -Fq 'alx-safety-card' "$FRONT_PAGE"
grep -Fq 'alx-home-center' "$FRONT_PAGE"
grep -Fq 'alx-home-rail--right' "$FRONT_PAGE"
grep -Fq 'alx-knowledge-card' "$FRONT_PAGE"
grep -Fq 'alx-metrics' "$FRONT_PAGE"
grep -Fq 'alx-home-cards--primary' "$FRONT_PAGE"
grep -Fq 'alx-safety-strip' "$FRONT_PAGE"

# Reference geometry: left rail + center dashboard + right rail.
grep -Fq 'grid-template-columns: 250px minmax(0, 1fr) 320px;' "$HOME_CSS"
grep -Fq 'grid-template-columns: repeat(5,minmax(0,1fr));' "$HOME_CSS"
grep -Fq 'grid-template-columns: 1.08fr 1fr 1.56fr;' "$HOME_CSS"
grep -Fq 'min-height: 296px;' "$HOME_CSS"
grep -Fq '.alx-brand-fallback-grid' "$HOME_CSS"
grep -Fq '.alx-knowledge-list' "$HOME_CSS"
grep -Fq '@media (max-width: 640px)' "$HOME_CSS"

# ALX-035 intentionally replaces the old vector/demo artwork with documented,
# free stock photography while preserving the reference-dashboard geometry.
grep -Fq 'alx-hero-stock-photo' "$FRONT_PAGE"
grep -Fq 'alx-featured-media' "$FRONT_PAGE"
grep -Fq 'alx-compare-media' "$FRONT_PAGE"
grep -Fq 'alx-knowledge-thumb' "$FRONT_PAGE"
grep -Fq 'images.unsplash.com' "$FRONT_PAGE"
grep -Fq 'Unsplash License' "$MEDIA_DOC"

if grep -Fq '<svg class="alx-car-silhouette"' "$FRONT_PAGE"; then
  echo 'legacy vector car artwork is still present'
  exit 1
fi

# Remote image sources are allow-listed. Attribution links are intentionally
# allowed to point to unsplash.com and are checked by the media documentation.
while IFS= read -r remote; do
  case "$remote" in
    https://images.unsplash.com/*|https://cdn.simpleicons.org/*) ;;
    *) echo "unapproved remote homepage image source: $remote"; exit 1 ;;
  esac
done < <(grep -Eo 'src="https://[^" ]+' "$FRONT_PAGE" | sed 's/^src="//' | sort -u)

if grep -n -E '\.ct-|!important|#[0]{3,6}([;[:space:]]|$)' "$HOME_CSS" "$POLISH_CSS"; then
  echo 'forbidden Blocksy, important or black styling found in reference dashboard layer'
  exit 1
fi

echo 'Autolex real-media reference-dashboard homepage contract passed.'
