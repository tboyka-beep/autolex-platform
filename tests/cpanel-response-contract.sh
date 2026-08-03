#!/usr/bin/env bash
set -euo pipefail

validator='scripts/validate-cpanel-response.sh'
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

printf 'content-type: application/json\r\n' > "$tmp/json.headers"
printf '{"status":1,"data":{"uploads":[{"file":"readme.txt"}]}}' > "$tmp/success.json"
bash "$validator" "$tmp/success.json" "$tmp/json.headers" 200 readme.txt >/dev/null

printf '{"status":0,"errors":["Permission denied"]}' > "$tmp/error.json"
if bash "$validator" "$tmp/error.json" "$tmp/json.headers" 200 readme.txt >"$tmp/error.out" 2>&1; then
  echo 'Expected cPanel error JSON to fail' >&2
  exit 1
fi
grep -q 'Permission denied' "$tmp/error.out"

printf 'content-type: text/html; charset=UTF-8\r\n' > "$tmp/html.headers"
printf '<html><body>Login required</body></html>' > "$tmp/error.html"
if bash "$validator" "$tmp/error.html" "$tmp/html.headers" 200 readme.txt >"$tmp/html.out" 2>&1; then
  echo 'Expected HTML response to fail' >&2
  exit 1
fi
grep -q 'non-JSON response' "$tmp/html.out"
grep -q 'text/html' "$tmp/html.out"

printf '<html><body>Gateway error</body></html>' > "$tmp/gateway.html"
if bash "$validator" "$tmp/gateway.html" "$tmp/html.headers" 502 readme.txt >"$tmp/http.out" 2>&1; then
  echo 'Expected HTTP 502 response to fail' >&2
  exit 1
fi
grep -q 'status=502' "$tmp/http.out"

echo 'cPanel response contract passed.'
