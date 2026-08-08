#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${AUTOLEX_BASE_URL:-https://autolex.hu}"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

fail() {
  printf 'VEHICLE_MEDIA_LIVE_FAIL: %s\n' "$*" >&2
  exit 1
}

fetch() {
  local url="$1" target="$2" code
  code="$(curl --silent --show-error --location \
    --retry 4 --retry-delay 3 --retry-all-errors \
    --connect-timeout 15 --max-time 60 \
    --output "$target" --write-out '%{http_code}' "$url")" || fail "transport error: $url"
  [[ "$code" == "200" ]] || fail "HTTP $code: $url"
}

catalog="$TMP_DIR/catalog.html"
fetch "${BASE_URL%/}/autok/" "$catalog"

grep -Fqi 'AutolexVehicleMedia' "$catalog" || fail 'localized vehicle media config missing from live catalogue'

# Each named mapping is an exact deploy proof. Do not loosen these to brand-only
# markers: a different Nissan or Opel photo must never satisfy this gate.
for marker in \
  'Opel_Corsa_F_IMG_5815' \
  'Nissan_Qashqai_%28J12%29_IMG_4900' \
  '"make":"Nissan"' \
  '"model":"Qashqai"' \
  '"generation":"J12"'; do
  grep -Fq "$marker" "$catalog" || fail "live catalogue missing verified vehicle-media marker: $marker"
done

media_js="$TMP_DIR/autolex-vehicle-media.js"
fetch "${BASE_URL%/}/wp-content/plugins/autolex-platform/assets/js/autolex-vehicle-media.js" "$media_js"
for marker in 'matchesMedia' 'exactGenerationPrefix' 'setFailClosedVisibility' 'alxMediaFailClosed'; do
  grep -Fq "$marker" "$media_js" || fail "live vehicle media JS missing fail-closed marker: $marker"
done

printf 'VEHICLE_MEDIA_LIVE_OK: Opel Corsa F and Nissan Qashqai J12 exact mappings are live\n'
