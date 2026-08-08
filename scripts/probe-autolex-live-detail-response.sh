#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${AUTOLEX_BASE_URL:-https://autolex.hu}"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

annotate_error() {
  local message="$*"
  if [[ -n "${GITHUB_ACTIONS:-}" ]]; then
    message="${message//'%'/'%25'}"
    message="${message//$'\r'/'%0D'}"
    message="${message//$'\n'/'%0A'}"
    printf '::error title=ALX-050F live detail probe::%s\n' "$message" >&2
  fi
}

fail() {
  annotate_error "ALX050F_PROBE_FAIL: $*"
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

http_code="$(awk -F= '$1=="http_code"{print $2}' "$meta")"
effective_url="$(awk -F= '$1=="effective_url"{sub(/^effective_url=/,""); print}' "$meta")"
content_type="$(awk -F= '$1=="content_type"{sub(/^content_type=/,""); print}' "$meta")"
redirects="$(awk -F= '$1=="redirects"{print $2}' "$meta")"
size_download="$(awk -F= '$1=="size_download"{print $2}' "$meta")"
body_sha256="$(sha256sum "$body" | awk '{print $1}')"
header_content_type="$(awk 'BEGIN{IGNORECASE=1} /^content-type:/{sub(/\r$/,""); value=$0} END{print value}' "$headers")"
body_prefix="$(python3 - "$body" <<'PY'
import re, sys
raw = open(sys.argv[1], 'rb').read(512)
text = raw.decode('utf-8', 'replace')
text = re.sub(r'\s+', ' ', text).strip()
print(text[:300])
PY
)"

cat "$meta"
printf 'requested_url=%s\n' "$vehicle_url"
printf 'body_sha256=%s\n' "$body_sha256"
printf 'header_content_type=%s\n' "$header_content_type"
printf 'body_prefix=%s\n' "$body_prefix"

fingerprint="http_code=${http_code:-unknown}; effective_url=${effective_url:-unknown}; content_type=${content_type:-unknown}; redirects=${redirects:-unknown}; size_download=${size_download:-unknown}; body_sha256=${body_sha256:-unknown}; body_prefix=${body_prefix:-empty}"

[[ "$http_code" == "200" ]] || fail "detail HTTP failure; $fingerprint"

grep -Fqi 'Please wait while your request is being verified' "$body" && fail "known hosting verification challenge; $fingerprint"
grep -Fqi '<title>One moment, please...</title>' "$body" && fail "known hosting challenge page; $fingerprint"

if ! grep -Eqi '<!doctype html|<html' "$body"; then
  fail "detail response is not HTML; $fingerprint"
fi

grep -Fqi 'autolex-vehicle-detail' "$body" || fail "vehicle detail marker missing; $fingerprint"
grep -Fqi 'application/ld+json' "$body" || fail "vehicle JSON-LD missing; $fingerprint"

printf 'ALX050F_PROBE_OK: live vehicle detail is HTML and carries the server detail contract\n'
