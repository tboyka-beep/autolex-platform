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

assert_no_security_challenge() {
  local body="$1" path="$2"
  if grep -Fqi 'Please wait while your request is being verified' "$body" || \
     grep -Fqi '<title>One moment, please...</title>' "$body"; then
    fail "hosting security challenge intercepted route: $path"
  fi
}

assert_html_any() {
  local path="$1" slug="$2"
  shift 2
  local body="$TMP_DIR/${slug}.html" headers="$TMP_DIR/${slug}.headers" marker
  fetch "${BASE_URL%/}${path}" "$body" "$headers" >/dev/null
  grep -Eqi '<!doctype html|<html' "$body" || fail "non-HTML response: $path"
  assert_no_security_challenge "$body" "$path"

  for marker in "$@"; do
    if grep -Fqi "$marker" "$body"; then
      printf 'LIVE_QA_OK: %s marker=%s\n' "$path" "$marker"
      return 0
    fi
  done

  fail "none of the approved markers found on $path: $*"
}

status_body="$TMP_DIR/status.json"
status_headers="$TMP_DIR/status.headers"
fetch "${BASE_URL%/}/wp-json/autolex/v1/status" "$status_body" "$status_headers" >/dev/null
if grep -Fqi 'Please wait while your request is being verified' "$status_body" || \
   grep -Fqi '<title>One moment, please...</title>' "$status_body"; then
  fail 'hosting security challenge intercepted status endpoint'
fi
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

# Guaranteed public production routes. Theme-template-only pages are exercised
# by the isolated Visual QA matrix instead of being assumed to exist in live WP.
assert_html_any '/' 'home' 'data-reference-dashboard="true"'
assert_html_any '/autok/' 'catalog' 'Járműkatalógus'
assert_html_any '/osszehasonlitas/' 'compare' 'alx3-compare'

printf 'LIVE_QA_SUCCESS: base=%s version=%s\n' "$BASE_URL" "$EXPECTED_VERSION"
