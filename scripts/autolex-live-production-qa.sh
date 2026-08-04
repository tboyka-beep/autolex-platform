#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${AUTOLEX_BASE_URL:-https://autolex.hu}"
EXPECTED_VERSION="${AUTOLEX_EXPECTED_VERSION:-4.2.0}"
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

assert_html() {
  local path="$1" marker="$2" slug="$3"
  local body="$TMP_DIR/${slug}.html" headers="$TMP_DIR/${slug}.headers"
  fetch "${BASE_URL%/}${path}" "$body" "$headers" >/dev/null
  grep -Eqi '<!doctype html|<html' "$body" || fail "non-HTML response: $path"
  grep -Fqi "$marker" "$body" || fail "missing marker '$marker': $path"
  printf 'LIVE_QA_OK: %s\n' "$path"
}

status_body="$TMP_DIR/status.json"
status_headers="$TMP_DIR/status.headers"
fetch "${BASE_URL%/}/wp-json/autolex/v1/status" "$status_body" "$status_headers" >/dev/null
python3 - "$status_body" "$EXPECTED_VERSION" <<'PY'
import json, sys
path, expected = sys.argv[1:]
try:
    data = json.load(open(path, encoding='utf-8'))
except Exception as exc:
    raise SystemExit(f'LIVE_QA_FAIL: invalid status JSON: {exc}')
service = data.get('service')
status = data.get('status')
version = str(data.get('version', ''))
if service != 'autolex-platform' or status != 'ok' or version != expected:
    raise SystemExit(
        f'LIVE_QA_FAIL: status mismatch service={service!r} status={status!r} version={version!r}'
    )
print(f'LIVE_QA_OK: status autolex-platform / ok / {version}')
PY

assert_html '/' 'autolex-portal-3' 'home'
assert_html '/autok/' 'Autók' 'catalog'
assert_html '/osszehasonlitas/' 'Összehasonlítás' 'compare'

printf 'LIVE_QA_SUCCESS: base=%s version=%s\n' "$BASE_URL" "$EXPECTED_VERSION"
