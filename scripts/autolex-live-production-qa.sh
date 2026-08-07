#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${AUTOLEX_BASE_URL:-https://autolex.hu}"
EXPECTED_VERSION="${AUTOLEX_EXPECTED_VERSION:-4.2.0}"
EXPECTED_INDEXING="${AUTOLEX_EXPECT_INDEXING:-report}"
CURL_RETRIES="${AUTOLEX_CURL_RETRIES:-4}"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

fail() {
  printf 'LIVE_QA_FAIL: %s\n' "$*" >&2
  exit 1
}

fetch() {
  local url="$1" body="$2" headers="$3" code
  code="$(curl --silent --show-error --location \
    --retry "$CURL_RETRIES" --retry-delay 3 --retry-all-errors \
    --connect-timeout 15 --max-time 60 \
    --dump-header "$headers" --output "$body" \
    --write-out '%{http_code}' "$url")" || fail "transport error: $url"
  [[ "$code" == "200" ]] || fail "HTTP $code: $url"
  printf '%s\n' "$code"
}

assert_no_security_challenge() {
  local body="$1" path="$2"
  if grep -Fqi 'Please wait while your request is being verified' "$body" || \
     grep -Fqi '<title>One moment, please...</title>' "$body"; then
    fail "hosting security challenge intercepted route: $path"
  fi
}

assert_html_file_any() {
  local body="$1" label="$2"
  shift 2
  local marker
  grep -Eqi '<!doctype html|<html' "$body" || fail "non-HTML response: $label"
  assert_no_security_challenge "$body" "$label"
  for marker in "$@"; do
    if grep -Fqi "$marker" "$body"; then
      printf 'LIVE_QA_OK: %s marker=%s\n' "$label" "$marker"
      return 0
    fi
  done
  fail "none of the approved markers found on $label: $*"
}

assert_html_any() {
  local path="$1" slug="$2"
  shift 2
  local body="$TMP_DIR/${slug}.html" headers="$TMP_DIR/${slug}.headers"
  fetch "${BASE_URL%/}${path}" "$body" "$headers" >/dev/null
  assert_html_file_any "$body" "$path" "$@"
}

fetch_json() {
  local url="$1" slug="$2"
  local body="$TMP_DIR/${slug}.json" headers="$TMP_DIR/${slug}.headers"
  fetch "$url" "$body" "$headers" >/dev/null
  assert_no_security_challenge "$body" "$url"
  python3 - "$body" <<'PY'
import json, sys
try:
    json.load(open(sys.argv[1], encoding='utf-8'))
except Exception as exc:
    raise SystemExit(f'LIVE_QA_FAIL: invalid JSON from {sys.argv[1]}: {exc}')
PY
  printf '%s\n' "$body"
}

report_indexing_state() {
  local html="$1" headers="$2" expected="$3" state
  case "$expected" in
    report|allowed|blocked) ;;
    *) fail "AUTOLEX_EXPECT_INDEXING must be report, allowed or blocked" ;;
  esac
  state="$(python3 - "$html" "$headers" <<'PY'
import re, sys
html = open(sys.argv[1], encoding='utf-8', errors='replace').read().lower()
headers = open(sys.argv[2], encoding='utf-8', errors='replace').read().lower()
blocked = any('robots' in tag and 'noindex' in tag for tag in re.findall(r'<meta\b[^>]*>', html, flags=re.I))
blocked = blocked or any(line.startswith('x-robots-tag:') and 'noindex' in line for line in headers.splitlines())
print('blocked' if blocked else 'allowed')
PY
)"
  printf 'LIVE_QA_INFO: indexing=%s expected=%s\n' "$state" "$expected"
  if [[ "$expected" != "report" && "$state" != "$expected" ]]; then
    fail "indexing state is $state, expected $expected"
  fi
}

status_body="$(fetch_json "${BASE_URL%/}/wp-json/autolex/v1/status" 'status')"
python3 - "$status_body" "$EXPECTED_VERSION" <<'PY'
import json, sys
path, expected = sys.argv[1:]
data = json.load(open(path, encoding='utf-8'))
service = data.get('service')
status = data.get('status')
version = str(data.get('version', ''))
if service != 'autolex-platform' or status != 'ok' or version != expected:
    raise SystemExit(
        f'LIVE_QA_FAIL: status mismatch service={service!r} status={status!r} version={version!r}'
    )
print(f'LIVE_QA_OK: status autolex-platform / ok / {version}')
PY

# Guaranteed public production routes. Theme-template-only pages are exercised
# by the isolated Visual QA matrix instead of being assumed to exist in live WP.
assert_html_any '/' 'home' 'data-reference-dashboard="true"'
report_indexing_state "$TMP_DIR/home.html" "$TMP_DIR/home.headers" "$EXPECTED_INDEXING"
assert_html_any '/autok/' 'catalog' 'Járműkatalógus'
assert_html_any '/osszehasonlitas/' 'compare' 'alx3-compare'

# Prove official Safety Gate storage is healthy and sourced from an allowlisted
# EU host. A zero/unsynchronised store is a launch blocker, not a cosmetic state.
safety_body="$(fetch_json "${BASE_URL%/}/wp-json/autolex/v1/safety-gate-status" 'safety-gate-status')"
python3 - "$safety_body" <<'PY'
import json, sys
from urllib.parse import urlparse
data = json.load(open(sys.argv[1], encoding='utf-8'))
allowed = {'data.europa.eu', 'ec.europa.eu', 'webgate.ec.europa.eu'}
source = str(data.get('source_url') or '')
host = (urlparse(source).hostname or '').lower()
if data.get('service') != 'autolex-safety-gate':
    raise SystemExit('LIVE_QA_FAIL: Safety Gate service marker mismatch')
if data.get('status') != 'ok':
    raise SystemExit(f"LIVE_QA_FAIL: Safety Gate status={data.get('status')!r} error={data.get('last_error')!r}")
if data.get('policy') != 'official_eu_xml_fail_closed':
    raise SystemExit('LIVE_QA_FAIL: Safety Gate policy mismatch')
if not isinstance(data.get('alerts'), int) or data['alerts'] <= 0:
    raise SystemExit(f"LIVE_QA_FAIL: Safety Gate has no imported vehicle alerts: {data.get('alerts')!r}")
if not data.get('last_sync_at') or host not in allowed:
    raise SystemExit(f'LIVE_QA_FAIL: Safety Gate source proof incomplete: host={host!r}')
print(f"LIVE_QA_OK: safety-gate alerts={data['alerts']} source={host}")
PY

# Select one real, currently published catalogue record. The QA never hardcodes
# a make/model or fixture ID, so it follows the actual production dataset.
vehicle_body="$(fetch_json "${BASE_URL%/}/wp-json/autolex/v1/portal/vehicles?limit=1&sort=data_desc" 'vehicle-sample')"
vehicle_line="$(python3 - "$vehicle_body" "$BASE_URL" <<'PY'
import json, sys
from urllib.parse import quote_plus, urlparse
path, base = sys.argv[1:]
data = json.load(open(path, encoding='utf-8'))
items = data.get('items')
if not isinstance(items, list) or not items:
    raise SystemExit('LIVE_QA_FAIL: production vehicle catalogue returned no sample')
item = items[0]
vid = item.get('id')
make = str(item.get('make') or '').strip().replace('\t', ' ').replace('\n', ' ')
model = str(item.get('model') or '').strip().replace('\t', ' ').replace('\n', ' ')
url = str(item.get('url') or '').strip()
if not isinstance(vid, int) or vid <= 0 or not make or not model or not url:
    raise SystemExit(f'LIVE_QA_FAIL: incomplete vehicle sample: {item!r}')
base_url, item_url = urlparse(base), urlparse(url)
if item_url.scheme not in ('http', 'https') or item_url.netloc.lower() != base_url.netloc.lower():
    raise SystemExit(f'LIVE_QA_FAIL: vehicle URL escaped Autolex host: {url!r}')
if f'/auto-adatlap/{vid}/' not in item_url.path:
    raise SystemExit(f'LIVE_QA_FAIL: unexpected vehicle detail URL: {url!r}')
query = quote_plus(f'{make} {model}')
print(f'{vid}\t{url}\t{query}\t{make}\t{model}')
PY
)"
IFS=$'\t' read -r vehicle_id vehicle_url search_query vehicle_make vehicle_model <<<"$vehicle_line"
printf 'LIVE_QA_INFO: sample_vehicle id=%s make=%s model=%s\n' "$vehicle_id" "$vehicle_make" "$vehicle_model"

# The same real record must resolve as an HTML detail page and expose the
# server-side detail/SEO contract before client-side evidence enhancement runs.
detail_body="$TMP_DIR/vehicle-detail.html"
detail_headers="$TMP_DIR/vehicle-detail.headers"
fetch "$vehicle_url" "$detail_body" "$detail_headers" >/dev/null
assert_html_file_any "$detail_body" "$vehicle_url" 'autolex-vehicle-detail'
grep -Fqi 'application/ld+json' "$detail_body" || fail "vehicle detail JSON-LD missing: $vehicle_url"
grep -Fqi "/auto-adatlap/${vehicle_id}/" "$detail_body" || fail "vehicle detail canonical/id marker missing: $vehicle_url"
printf 'LIVE_QA_OK: dynamic vehicle detail id=%s\n' "$vehicle_id"

# Backend evidence for the selected live vehicle must resolve to the same ID.
maintenance_body="$(fetch_json "${BASE_URL%/}/wp-json/autolex/v1/maintenance/${vehicle_id}" 'maintenance-sample')"
python3 - "$maintenance_body" "$vehicle_id" "$vehicle_make" "$vehicle_model" <<'PY'
import json, sys
path, expected_id, make, model = sys.argv[1:]
data = json.load(open(path, encoding='utf-8'))
vehicle = data.get('vehicle') if isinstance(data.get('vehicle'), dict) else {}
if data.get('status') != 'ok' or int(data.get('vehicle_id') or 0) != int(expected_id):
    raise SystemExit(f'LIVE_QA_FAIL: maintenance vehicle mismatch status={data.get("status")!r}')
if str(vehicle.get('make') or '').strip().casefold() != make.casefold():
    raise SystemExit('LIVE_QA_FAIL: maintenance make mismatch')
if str(vehicle.get('model') or '').strip().casefold() != model.casefold():
    raise SystemExit('LIVE_QA_FAIL: maintenance model mismatch')
print(f'LIVE_QA_OK: maintenance evidence vehicle_id={expected_id}')
PY

# Free-text search must return a real result in both REST and server-rendered UI.
search_api_body="$(fetch_json "${BASE_URL%/}/wp-json/autolex/v1/portal/vehicles?q=${search_query}&limit=24" 'vehicle-search')"
python3 - "$search_api_body" "$vehicle_make" "$vehicle_model" <<'PY'
import json, sys
path, make, model = sys.argv[1:]
data = json.load(open(path, encoding='utf-8'))
items = data.get('items') if isinstance(data.get('items'), list) else []
match = any(str(item.get('make') or '').strip().casefold() == make.casefold() and
            str(item.get('model') or '').strip().casefold() == model.casefold()
            for item in items)
if not items or not match:
    raise SystemExit(f'LIVE_QA_FAIL: search REST did not return sampled {make} {model}')
print(f'LIVE_QA_OK: search REST matched {make} {model} results={len(items)}')
PY

search_html="$TMP_DIR/search.html"
search_headers="$TMP_DIR/search.headers"
fetch "${BASE_URL%/}/autok/?q=${search_query}" "$search_html" "$search_headers" >/dev/null
assert_html_file_any "$search_html" '/autok/?q=<dynamic>' 'alx3-vehicle-card'
grep -Fqi "$vehicle_make" "$search_html" || fail "search UI missing sampled make: $vehicle_make"
grep -Fqi "$vehicle_model" "$search_html" || fail "search UI missing sampled model: $vehicle_model"
printf 'LIVE_QA_OK: search UI matched %s %s\n' "$vehicle_make" "$vehicle_model"

# Recall lookup is allowed to return zero model-specific alerts, but the live
# query must remain valid JSON and tied to the official Safety Gate service.
recall_body="$(fetch_json "${BASE_URL%/}/wp-json/autolex/v1/recalls?make=${vehicle_make// /%20}&model=${vehicle_model// /%20}&limit=12" 'recall-sample')"
python3 - "$recall_body" <<'PY'
import json, sys
data = json.load(open(sys.argv[1], encoding='utf-8'))
if not isinstance(data.get('items'), list) or not isinstance(data.get('total'), int):
    raise SystemExit('LIVE_QA_FAIL: recalls response contract mismatch')
if data['total'] != len(data['items']):
    raise SystemExit('LIVE_QA_FAIL: recalls total/items mismatch')
print(f"LIVE_QA_OK: recall query valid items={data['total']}")
PY

printf 'LIVE_QA_SUCCESS: base=%s version=%s vehicle_id=%s\n' "$BASE_URL" "$EXPECTED_VERSION" "$vehicle_id"
