#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${AUTOLEX_BASE_URL:-https://autolex.hu}"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

fail() {
  printf 'ALX050F_PROBE_FAIL: %s\n' "$*" >&2
  exit 1
}

json="$TMP_DIR/vehicle.json"
curl --silent --show-error --location --retry 3 --retry-delay 2 --retry-all-errors \
  --connect-timeout 15 --max-time 60 \
  "${BASE_URL%/}/wp-json/autolex/v1/portal/vehicles?limit=1&sort=data_desc" \
  --output "$json" || fail 'vehicle sample transport failed'

vehicle_url="$(python3 - "$json" "$BASE_URL" <<'PY'
import json, sys
from urllib.parse import urlparse
path, base = sys.argv[1:]
try:
    data = json.load(open(path, encoding='utf-8'))
except Exception as exc:
    raise SystemExit(f'ALX050F_PROBE_FAIL: invalid sample JSON: {exc}')
items = data.get('items')
if not isinstance(items, list) or not items:
    raise SystemExit('ALX050F_PROBE_FAIL: no live vehicle sample')
item = items[0]
vid = item.get('id')
url = str(item.get('url') or '').strip()
if not isinstance(vid, int) or vid <= 0 or not url:
    raise SystemExit('ALX050F_PROBE_FAIL: incomplete sample vehicle')
b = urlparse(base)
u = urlparse(url)
if u.scheme not in ('http', 'https') or u.netloc.lower() != b.netloc.lower():
    raise SystemExit(f'ALX050F_PROBE_FAIL: sample URL escaped host: {url!r}')
if f'/auto-adatlap/{vid}/' not in u.path:
    raise SystemExit(f'ALX050F_PROBE_FAIL: unexpected detail route: {url!r}')
print(url)
PY
)"

body="$TMP_DIR/detail.body"
headers="$TMP_DIR/detail.headers"
meta="$TMP_DIR/detail.meta"

curl --silent --show-error --location --retry 3 --retry-delay 2 --retry-all-errors \
  --connect-timeout 15 --max-time 60 \
  --dump-header "$headers" --output "$body" \
  --write-out 'http_code=%{http_code}\neffective_url=%{url_effective}\ncontent_type=%{content_type}\nredirects=%{num_redirects}\nsize_download=%{size_download}\n' \
  "$vehicle_url" >"$meta" || fail "detail transport failed: $vehicle_url"

cat "$meta"
printf 'requested_url=%s\n' "$vehicle_url"
printf 'body_sha256=%s\n' "$(sha256sum "$body" | awk '{print $1}')"
printf 'header_content_type=%s\n' "$(awk 'BEGIN{IGNORECASE=1} /^content-type:/{sub(/\r$/,""); value=$0} END{print value}' "$headers")"

python3 - "$body" <<'PY'
import re, sys
raw = open(sys.argv[1], 'rb').read(512)
text = raw.decode('utf-8', 'replace')
text = re.sub(r'\s+', ' ', text).strip()
print('body_prefix=' + text[:300])
PY

http_code="$(awk -F= '$1=="http_code"{print $2}' "$meta")"
effective_url="$(awk -F= '$1=="effective_url"{sub(/^effective_url=/,""); print}' "$meta")"
content_type="$(awk -F= '$1=="content_type"{sub(/^content_type=/,""); print}' "$meta")"

[[ "$http_code" == "200" ]] || fail "detail HTTP $http_code effective=$effective_url"

grep -Fqi 'Please wait while your request is being verified' "$body" && fail 'known hosting verification challenge'
grep -Fqi '<title>One moment, please...</title>' "$body" && fail 'known hosting challenge page'

if ! grep -Eqi '<!doctype html|<html' "$body"; then
  fail "detail response is not HTML content_type=${content_type:-unknown} effective=$effective_url"
fi

grep -Fqi 'autolex-vehicle-detail' "$body" || fail 'vehicle detail marker missing'
grep -Fqi 'application/ld+json' "$body" || fail 'vehicle JSON-LD missing'

printf 'ALX050F_PROBE_OK: live vehicle detail is HTML and carries the server detail contract\n'
