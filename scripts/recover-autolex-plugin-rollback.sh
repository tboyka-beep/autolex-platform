#!/usr/bin/env bash
set -euo pipefail

: "${CPANEL_API_HOST:=}"
: "${CPANEL_API_USER:=}"
: "${CPANEL_API_TOKEN:=}"
: "${CPANEL_PLUGIN_DIR:=}"
: "${AUTOLEX_BASE_URL:=https://autolex.hu}"
: "${AUTOLEX_FAILED_RELEASE_SHA:=bd7508031eae13a5c757f5ca56a10d11e44486e2}"

fail() { printf 'PLUGIN_ROLLBACK_FAIL: %s\n' "$*" >&2; exit 1; }
for name in CPANEL_API_HOST CPANEL_API_USER CPANEL_API_TOKEN CPANEL_PLUGIN_DIR; do
  test -n "${!name:-}" || fail "missing ${name}"
done
case "$CPANEL_API_HOST" in *://*|*/*|*:*) fail 'CPANEL_API_HOST must be a bare hostname' ;; esac
case "$CPANEL_PLUGIN_DIR" in */wp-content/plugins/autolex-platform) ;; *) fail 'unsafe CPANEL_PLUGIN_DIR' ;; esac
for cmd in curl jq grep; do command -v "$cmd" >/dev/null || fail "missing command: $cmd"; done

plugin_dir="${CPANEL_PLUGIN_DIR%/}"
plugins_dir="${plugin_dir%/autolex-platform}"
plugin_name="${plugin_dir##*/}"
release_token="$(printf '%s' "$AUTOLEX_FAILED_RELEASE_SHA" | tr -cd 'A-Za-z0-9' | cut -c1-16)"
rollback_name="${plugin_name}.rollback-${release_token}"
rollback_dir="${plugins_dir}/${rollback_name}"
auth="Authorization: cpanel ${CPANEL_API_USER}:${CPANEL_API_TOKEN}"
api_base="https://${CPANEL_API_HOST}:2083"

list_parent() {
  local tmp code
  tmp="$(mktemp)"
  code="$(curl --silent --show-error --location --connect-timeout 15 --max-time 60 \
    --header "$auth" --get --output "$tmp" --write-out '%{http_code}' \
    --data-urlencode "cpanel_jsonapi_user=${CPANEL_API_USER}" \
    --data-urlencode 'cpanel_jsonapi_apiversion=2' \
    --data-urlencode 'cpanel_jsonapi_module=Fileman' \
    --data-urlencode 'cpanel_jsonapi_func=listfiles' \
    --data-urlencode "dir=${plugins_dir}" --data-urlencode 'showdotfiles=1' \
    "${api_base}/json-api/cpanel")" || { rm -f "$tmp"; fail 'cPanel list transport failure'; }
  test "$code" = 200 || { rm -f "$tmp"; fail "cPanel list HTTP ${code}"; }
  jq -e '.cpanelresult.event.result == 1' "$tmp" >/dev/null || { cat "$tmp" >&2; rm -f "$tmp"; fail 'cPanel list rejected'; }
  cat "$tmp"
  rm -f "$tmp"
}

fileop_rename() {
  local source="$1" dest="$2" tmp code
  tmp="$(mktemp)"
  code="$(curl --silent --show-error --location --connect-timeout 15 --max-time 60 --get \
    --header "$auth" --output "$tmp" --write-out '%{http_code}' \
    --data-urlencode "cpanel_jsonapi_user=${CPANEL_API_USER}" \
    --data-urlencode 'cpanel_jsonapi_apiversion=2' \
    --data-urlencode 'cpanel_jsonapi_module=Fileman' \
    --data-urlencode 'cpanel_jsonapi_func=fileop' \
    --data-urlencode 'op=rename' \
    --data-urlencode "sourcefiles=${source}" \
    --data-urlencode "destfiles=${dest}" \
    --data-urlencode 'doubledecode=1' \
    "${api_base}/json-api/cpanel")" || { rm -f "$tmp"; fail 'cPanel rename transport failure'; }
  test "$code" = 200 || { sed -n '1,8p' "$tmp" >&2; rm -f "$tmp"; fail "cPanel rename HTTP ${code}"; }
  jq -e '.cpanelresult.event.result == 1 and ([.cpanelresult.data[]?.result] | all(. == 1))' "$tmp" >/dev/null || {
    cat "$tmp" >&2; rm -f "$tmp"; fail 'cPanel rollback rename rejected';
  }
  rm -f "$tmp"
}

parent_before="$(list_parent)"
jq -e --arg name "$rollback_name" '[.cpanelresult.data[]? | (.file // .name // "")] | index($name) != null' <<<"$parent_before" >/dev/null \
  || fail "exact rollback directory missing: ${rollback_name}"
if jq -e --arg name "$plugin_name" '[.cpanelresult.data[]? | (.file // .name // "")] | index($name) != null' <<<"$parent_before" >/dev/null; then
  fail 'active plugin directory already exists; refusing ambiguous recovery'
fi

printf 'PLUGIN_ROLLBACK_INFO: restoring=%s active=%s\n' "$rollback_name" "$plugin_name"
fileop_rename "$rollback_dir" "$plugin_name"

parent_after="$(list_parent)"
jq -e --arg name "$plugin_name" '[.cpanelresult.data[]? | (.file // .name // "")] | index($name) != null' <<<"$parent_after" >/dev/null \
  || fail 'active plugin directory not present after rollback rename'
if jq -e --arg name "$rollback_name" '[.cpanelresult.data[]? | (.file // .name // "")] | index($name) != null' <<<"$parent_after" >/dev/null; then
  fail 'rollback directory still present after rename; recovery state ambiguous'
fi

status=''
for attempt in 1 2 3 4 5; do
  if status="$(curl --silent --show-error --fail --location --connect-timeout 15 --max-time 60 "${AUTOLEX_BASE_URL%/}/wp-json/autolex/v1/status")"; then
    if jq -e '.service == "autolex-platform" and .status == "ok" and .version == "4.2.0"' <<<"$status" >/dev/null; then
      printf 'PLUGIN_ROLLBACK_STATUS_OK: attempt=%s version=4.2.0\n' "$attempt"
      break
    fi
  fi
  status=''
  sleep 3
done
test -n "$status" || fail 'restored plugin status endpoint did not recover'

catalog="$(mktemp)"
curl --silent --show-error --fail --location --connect-timeout 15 --max-time 60 \
  --output "$catalog" "${AUTOLEX_BASE_URL%/}/autok/" || { rm -f "$catalog"; fail 'vehicle catalog unavailable after rollback'; }
grep -Fq 'Járműkatalógus' "$catalog" || { rm -f "$catalog"; fail 'vehicle catalog marker missing after rollback'; }
rm -f "$catalog"
printf 'PLUGIN_ROLLBACK_SUCCESS: restored known previous plugin snapshot %s\n' "$rollback_name"
