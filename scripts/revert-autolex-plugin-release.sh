#!/usr/bin/env bash
set -Eeuo pipefail

MODE="${1:-validate}"
: "${CPANEL_API_HOST:=}"
: "${CPANEL_API_USER:=}"
: "${CPANEL_API_TOKEN:=}"
: "${CPANEL_PLUGIN_DIR:=}"
: "${AUTOLEX_BASE_URL:=https://autolex.hu}"
: "${AUTOLEX_REVERT_RELEASE_SHA:=57f99e043a2e7b59a92786bc5181135809a08ea6}"

fail() { printf 'PLUGIN_REVERT_FAIL: %s\n' "$*" >&2; exit 1; }

validate_config() {
  for name in CPANEL_API_HOST CPANEL_API_USER CPANEL_API_TOKEN CPANEL_PLUGIN_DIR AUTOLEX_REVERT_RELEASE_SHA; do
    test -n "${!name:-}" || fail "missing ${name}"
  done
  case "$CPANEL_API_HOST" in *://*|*/*|*:*) fail 'CPANEL_API_HOST must be a bare hostname' ;; esac
  case "$CPANEL_PLUGIN_DIR" in */wp-content/plugins/autolex-platform) ;; *) fail 'unsafe CPANEL_PLUGIN_DIR' ;; esac
  printf '%s' "$AUTOLEX_REVERT_RELEASE_SHA" | grep -Eq '^[0-9a-f]{40}$' || fail 'release SHA must be a full lowercase git SHA'
  for cmd in curl jq grep; do command -v "$cmd" >/dev/null || fail "missing command: $cmd"; done
}

auth_header() { printf 'Authorization: cpanel %s:%s' "$CPANEL_API_USER" "$CPANEL_API_TOKEN"; }
api_base() { printf 'https://%s:2083' "$CPANEL_API_HOST"; }

list_dir() {
  local dir="$1" tmp code
  tmp="$(mktemp)"
  code="$(curl --silent --show-error --location --connect-timeout 15 --max-time 60 --get \
    --header "$(auth_header)" --output "$tmp" --write-out '%{http_code}' \
    --data-urlencode "cpanel_jsonapi_user=${CPANEL_API_USER}" \
    --data-urlencode 'cpanel_jsonapi_apiversion=2' \
    --data-urlencode 'cpanel_jsonapi_module=Fileman' \
    --data-urlencode 'cpanel_jsonapi_func=listfiles' \
    --data-urlencode "dir=${dir}" --data-urlencode 'showdotfiles=1' \
    "$(api_base)/json-api/cpanel")" || { rm -f "$tmp"; return 10; }
  [[ "$code" == 200 ]] || { rm -f "$tmp"; return 11; }
  jq -e '.cpanelresult.event.result == 1' "$tmp" >/dev/null || { rm -f "$tmp"; return 12; }
  cat "$tmp"
  rm -f "$tmp"
}

has_item() {
  local data="$1" item="$2"
  jq -e --arg item "$item" '[.cpanelresult.data[]? | (.file // .name // "")] | index($item) != null' <<<"$data" >/dev/null
}

rename_dir() {
  local source="$1" dest="$2" tmp code
  tmp="$(mktemp)"
  code="$(curl --silent --show-error --location --connect-timeout 15 --max-time 60 --get \
    --header "$(auth_header)" --output "$tmp" --write-out '%{http_code}' \
    --data-urlencode "cpanel_jsonapi_user=${CPANEL_API_USER}" \
    --data-urlencode 'cpanel_jsonapi_apiversion=2' \
    --data-urlencode 'cpanel_jsonapi_module=Fileman' \
    --data-urlencode 'cpanel_jsonapi_func=fileop' \
    --data-urlencode 'op=rename' \
    --data-urlencode "sourcefiles=${source}" \
    --data-urlencode "destfiles=${dest}" \
    --data-urlencode 'doubledecode=1' \
    "$(api_base)/json-api/cpanel")" || { rm -f "$tmp"; return 10; }
  if [[ "$code" != 200 ]] || ! jq -e '.cpanelresult.event.result == 1 and ([.cpanelresult.data[]?.result] | all(. == 1))' "$tmp" >/dev/null; then
    cat "$tmp" >&2
    rm -f "$tmp"
    return 11
  fi
  rm -f "$tmp"
}

verify_baseline() {
  local status tmp
  status="$(curl --silent --show-error --fail --location --connect-timeout 15 --max-time 60 \
    "${AUTOLEX_BASE_URL%/}/wp-json/autolex/v1/status")" || return 1
  jq -e '.service == "autolex-platform" and .status == "ok" and .version == "4.2.0"' <<<"$status" >/dev/null || return 1
  tmp="$(mktemp)"
  curl --silent --show-error --fail --location --connect-timeout 15 --max-time 60 \
    --output "$tmp" "${AUTOLEX_BASE_URL%/}/autok/" || { rm -f "$tmp"; return 1; }
  grep -Fq 'Járműkatalógus' "$tmp"
  local rc=$?; rm -f "$tmp"; return $rc
}

validate_config
if [[ "$MODE" == validate ]]; then
  bash -n "$0"
  printf 'PLUGIN_REVERT_OK: validation passed\n'
  exit 0
fi
[[ "$MODE" == revert ]] || fail "unsupported mode: ${MODE}"

plugin_dir="${CPANEL_PLUGIN_DIR%/}"
plugins_dir="${plugin_dir%/autolex-platform}"
plugin_name="${plugin_dir##*/}"
token="$(printf '%s' "$AUTOLEX_REVERT_RELEASE_SHA" | cut -c1-16)"
backup_name="${plugin_name}.rollback-${token}"
backup_dir="${plugins_dir}/${backup_name}"
failed_name="${plugin_name}.failed-gate-${token}"
failed_dir="${plugins_dir}/${failed_name}"

parent="$(list_dir "$plugins_dir")" || fail 'cannot list plugin parent'
has_item "$parent" "$plugin_name" || fail 'active plugin directory missing before revert'
has_item "$parent" "$backup_name" || fail 'exact rollback snapshot missing before revert'
if has_item "$parent" "$failed_name"; then fail 'failed-gate isolation directory already exists'; fi

printf 'PLUGIN_REVERT_INFO: release=%s rollback=%s\n' "$token" "$backup_name"
rename_dir "$plugin_dir" "$failed_name" || fail 'could not isolate current candidate; rollback not attempted'

if ! rename_dir "$backup_dir" "$plugin_name"; then
  if rename_dir "$failed_dir" "$plugin_name" && verify_baseline; then
    fail 'rollback activation failed; current candidate restored'
  fi
  fail 'rollback activation failed and current candidate restoration could not be proven'
fi

if ! verify_baseline; then
  fail 'previous snapshot activated but baseline verification failed'
fi

parent_after="$(list_dir "$plugins_dir")" || fail 'cannot verify plugin parent after revert'
has_item "$parent_after" "$plugin_name" || fail 'active plugin missing after revert'
if has_item "$parent_after" "$backup_name"; then fail 'rollback snapshot still present after successful rename'; fi
has_item "$parent_after" "$failed_name" || fail 'failed candidate isolation missing after revert'

printf 'PLUGIN_REVERT_SUCCESS: restored previous snapshot and isolated candidate=%s\n' "$failed_name"
