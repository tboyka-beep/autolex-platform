#!/usr/bin/env bash
set -euo pipefail

body_file="${1:?body file required}"
headers_file="${2:?headers file required}"
http_status="${3:?HTTP status required}"
relative_file="${4:-unknown file}"

content_type="$(awk 'BEGIN{IGNORECASE=1} /^content-type:/ {sub(/^[^:]*:[[:space:]]*/, ""); sub(/\r$/, ""); value=$0} END{print value}' "$headers_file")"
content_type="${content_type:-unknown}"

safe_excerpt() {
  tr '\r\n\t' '   ' < "$body_file" \
    | sed -E 's/[[:space:]]+/ /g; s/(Authorization:[[:space:]]*cpanel[[:space:]]+)[^ ]+/\1[REDACTED]/Ig; s/(token|api[_-]?token|authorization)([=":[:space:]]+)[^ ,;"]+/\1\2[REDACTED]/Ig' \
    | cut -c1-500
}

case "$http_status" in
  2??) ;;
  *)
    echo "cPanel upload HTTP failure for ${relative_file}: status=${http_status}, content-type=${content_type}" >&2
    echo "Response excerpt: $(safe_excerpt)" >&2
    exit 1
    ;;
esac

if ! jq -e . "$body_file" >/dev/null 2>&1; then
  echo "cPanel returned a non-JSON response for ${relative_file}: status=${http_status}, content-type=${content_type}" >&2
  echo "Response excerpt: $(safe_excerpt)" >&2
  exit 1
fi

if ! jq -e '.status == 1' "$body_file" >/dev/null; then
  echo "cPanel rejected upload of ${relative_file}: status=${http_status}, content-type=${content_type}" >&2
  jq -r '[.errors[]?, .messages[]?] | map(select(type == "string")) | if length > 0 then .[] else "Unknown cPanel API error" end' "$body_file" >&2
  exit 1
fi

echo "cPanel accepted ${relative_file}: status=${http_status}, content-type=${content_type}"
