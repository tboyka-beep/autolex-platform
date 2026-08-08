#!/usr/bin/env bash
set -Eeuo pipefail

MODE="${1:-validate}"
: "${CPANEL_API_HOST:=}"
: "${CPANEL_API_USER:=}"
: "${CPANEL_API_TOKEN:=}"
: "${CPANEL_PLUGIN_DIR:=}"
: "${AUTOLEX_BASE_URL:=https://autolex.hu}"
: "${AUTOLEX_RELEASE_SHA:=${GITHUB_SHA:-manual}}"

fail() { printf 'PLUGIN_RELEASE_FAIL: %s\n' "$*" >&2; exit 1; }

validate_config() {
  for name in CPANEL_API_HOST CPANEL_API_USER CPANEL_API_TOKEN CPANEL_PLUGIN_DIR; do
    test -n "${!name:-}" || fail "missing ${name}"
  done
  case "$CPANEL_API_HOST" in *://*|*/*|*:*) fail 'CPANEL_API_HOST must be a bare hostname' ;; esac
  case "$CPANEL_PLUGIN_DIR" in
    /*|*..*|*[^A-Za-z0-9._/-]*) fail 'unsafe CPANEL_PLUGIN_DIR' ;;
  esac
  case "$CPANEL_PLUGIN_DIR" in
    */wp-content/plugins/autolex-platform) ;;
    *) fail 'CPANEL_PLUGIN_DIR must end in /wp-content/plugins/autolex-platform' ;;
  esac
  for cmd in bash curl jq zip sed grep sha256sum cp mktemp; do
    command -v "$cmd" >/dev/null || fail "missing command: $cmd"
  done
  test -f plugin/autolex-platform/autolex-platform.php || fail 'plugin entry file missing'
}

api_base() { printf 'https://%s:2083' "$CPANEL_API_HOST"; }
auth_header() { printf 'Authorization: cpanel %s:%s' "$CPANEL_API_USER" "$CPANEL_API_TOKEN"; }

api2_fileop_try() {
  local op="$1" source="$2" dest="${3:-}" metadata="${4:-}"
  local tmp body headers code
  tmp="$(mktemp -d)"; body="$tmp/body"; headers="$tmp/headers"
  code="$(curl --silent --show-error --location --retry 4 --retry-delay 3 --retry-all-errors \
    --connect-timeout 15 --max-time 90 --get --header "$(auth_header)" \
    --dump-header "$headers" --output "$body" --write-out '%{http_code}' \
    --data-urlencode "cpanel_jsonapi_user=${CPANEL_API_USER}" \
    --data-urlencode 'cpanel_jsonapi_apiversion=2' \
    --data-urlencode 'cpanel_jsonapi_module=Fileman' \
    --data-urlencode 'cpanel_jsonapi_func=fileop' \
    --data-urlencode "op=${op}" \
    --data-urlencode "sourcefiles=${source}" \
    --data-urlencode 'doubledecode=1' \
    ${dest:+--data-urlencode "destfiles=${dest}"} \
    ${metadata:+--data-urlencode "metadata=${metadata}"} \
    "$(api_base)/json-api/cpanel")" || { rm -rf "$tmp"; return 10; }
  if [[ "$code" != 200 ]]; then
    sed -n '1,12p' "$body" >&2
    rm -rf "$tmp"
    return 11
  fi
  if grep -Fqi '<title>One moment, please...</title>' "$body"; then
    rm -rf "$tmp"
    return 12
  fi
  if ! jq -e '.cpanelresult.event.result == 1 and ([.cpanelresult.data[]?.result] | all(. == 1))' "$body" >/dev/null; then
    cat "$body" >&2
    rm -rf "$tmp"
    return 13
  fi
  rm -rf "$tmp"
}

api2_fileop() {
  local op="$1" source="$2" dest="${3:-}" metadata="${4:-}"
  api2_fileop_try "$op" "$source" "$dest" "$metadata" || fail "cPanel fileop rejected: ${op} ${source}"
}

api2_list_dir() {
  local dir="$1" tmp code
  tmp="$(mktemp)"
  code="$(curl --silent --show-error --location --retry 3 --retry-delay 2 --retry-all-errors \
    --connect-timeout 15 --max-time 60 --get --header "$(auth_header)" \
    --output "$tmp" --write-out '%{http_code}' \
    --data-urlencode "cpanel_jsonapi_user=${CPANEL_API_USER}" \
    --data-urlencode 'cpanel_jsonapi_apiversion=2' \
    --data-urlencode 'cpanel_jsonapi_module=Fileman' \
    --data-urlencode 'cpanel_jsonapi_func=listfiles' \
    --data-urlencode "dir=${dir}" --data-urlencode 'showdotfiles=1' \
    "$(api_base)/json-api/cpanel")" || { rm -f "$tmp"; return 10; }
  if [[ "$code" != 200 ]] || ! jq -e '.cpanelresult.event.result == 1' "$tmp" >/dev/null 2>&1; then
    rm -f "$tmp"
    return 11
  fi
  cat "$tmp"
  rm -f "$tmp"
}

dir_has_item() {
  local dir="$1" item="$2" data
  data="$(api2_list_dir "$dir")" || return 2
  jq -e --arg item "$item" '[.cpanelresult.data[]? | (.file // .name // "")] | index($item) != null' <<<"$data" >/dev/null
}

require_item() {
  local dir="$1" item="$2" label="$3"
  dir_has_item "$dir" "$item" || fail "missing ${label}: ${item}"
}

require_absent_item() {
  local dir="$1" item="$2" label="$3" rc
  set +e
  dir_has_item "$dir" "$item"
  rc=$?
  set -e
  [[ "$rc" -eq 1 ]] || {
    [[ "$rc" -eq 0 ]] && fail "${label} already exists: ${item}"
    fail "cannot prove ${label} absence: ${item}"
  }
}

upload_zip() {
  local zip_path="$1" remote_dir="$2" tmp body headers code
  tmp="$(mktemp -d)"; body="$tmp/body"; headers="$tmp/headers"
  code="$(curl --silent --show-error --location --retry 4 --retry-delay 3 --retry-all-errors \
    --connect-timeout 15 --max-time 120 --header "$(auth_header)" \
    --dump-header "$headers" --output "$body" --write-out '%{http_code}' \
    --form "dir=${remote_dir}" --form 'overwrite=1' \
    --form "file-1=@${zip_path};filename=$(basename "$zip_path")" \
    "$(api_base)/execute/Fileman/upload_files")" || { rm -rf "$tmp"; fail 'cPanel zip upload transport failure'; }
  test "$code" = 200 || { sed -n '1,12p' "$body" >&2; rm -rf "$tmp"; fail "cPanel zip upload HTTP ${code}"; }
  grep -Fqi '<title>One moment, please...</title>' "$body" && { rm -rf "$tmp"; fail 'cPanel security challenge during zip upload'; }
  jq -e '.status == 1 and (.errors == null or .errors == [])' "$body" >/dev/null || { cat "$body" >&2; rm -rf "$tmp"; fail 'cPanel rejected zip upload'; }
  rm -rf "$tmp"
}

expected_version() {
  sed -n "s/define('AUTOLEX_PLATFORM_VERSION', '\([^']*\)');/\1/p" plugin/autolex-platform/autolex-platform.php
}

verify_live() {
  local version status detail_sample vehicle_url tmp
  version="$(expected_version)"
  test -n "$version" || return 1
  status="$(curl --silent --show-error --fail --location --retry 4 --retry-delay 3 --retry-all-errors \
    --connect-timeout 15 --max-time 60 "${AUTOLEX_BASE_URL%/}/wp-json/autolex/v1/status")" || return 1
  jq -e --arg version "$version" '.service == "autolex-platform" and .status == "ok" and .version == $version' <<<"$status" >/dev/null || return 1
  detail_sample="$(curl --silent --show-error --fail --location --retry 4 --retry-delay 3 --retry-all-errors \
    --connect-timeout 15 --max-time 60 "${AUTOLEX_BASE_URL%/}/wp-json/autolex/v1/portal/vehicles?limit=1&sort=data_desc")" || return 1
  vehicle_url="$(jq -r '.items[0].url // empty' <<<"$detail_sample")"
  test -n "$vehicle_url" || return 1
  tmp="$(mktemp)"
  curl --silent --show-error --fail --location --retry 4 --retry-delay 3 --retry-all-errors \
    --connect-timeout 15 --max-time 60 --output "$tmp" "$vehicle_url" || { rm -f "$tmp"; return 1; }
  grep -Eqi '<!doctype html|<html' "$tmp" && grep -Fqi 'autolex-vehicle-detail' "$tmp" && grep -Fqi 'application/ld+json' "$tmp"
  local rc=$?; rm -f "$tmp"; return $rc
}

verify_restored_baseline() {
  local status tmp
  status="$(curl --silent --show-error --fail --location --retry 3 --retry-delay 2 --retry-all-errors \
    --connect-timeout 15 --max-time 60 "${AUTOLEX_BASE_URL%/}/wp-json/autolex/v1/status")" || return 1
  jq -e '.service == "autolex-platform" and .status == "ok"' <<<"$status" >/dev/null || return 1
  tmp="$(mktemp)"
  curl --silent --show-error --fail --location --retry 3 --retry-delay 2 --retry-all-errors \
    --connect-timeout 15 --max-time 60 --output "$tmp" "${AUTOLEX_BASE_URL%/}/autok/" || { rm -f "$tmp"; return 1; }
  grep -Fq 'Járműkatalógus' "$tmp"
  local rc=$?; rm -f "$tmp"; return $rc
}

validate_config
if [[ "$MODE" == validate ]]; then
  bash -n "$0"
  printf 'PLUGIN_RELEASE_OK: staging-first validation passed\n'
  exit 0
fi
[[ "$MODE" == release ]] || fail "unsupported mode: ${MODE}"

CPANEL_PLUGIN_DIR="${CPANEL_PLUGIN_DIR%/}"
plugins_dir="${CPANEL_PLUGIN_DIR%/autolex-platform}"
plugin_name="${CPANEL_PLUGIN_DIR##*/}"
release_token="$(printf '%s' "$AUTOLEX_RELEASE_SHA" | tr -cd 'A-Za-z0-9' | cut -c1-16)"
staging_name="${plugin_name}.release-${release_token}"
backup_name="${plugin_name}.rollback-${release_token}"
failed_name="${plugin_name}.failed-${release_token}"
staging_dir="${plugins_dir}/${staging_name}"
backup_dir="${plugins_dir}/${backup_name}"
failed_dir="${plugins_dir}/${failed_name}"
zip_name="${staging_name}.zip"
remote_zip="${plugins_dir}/${zip_name}"
work_dir="$(mktemp -d)"
zip_path="${work_dir}/${zip_name}"
trap 'rm -rf "$work_dir"' EXIT

case "$plugins_dir" in
  */wp-content/plugins) ;;
  *) fail 'derived plugin parent directory is unsafe' ;;
esac
[[ "$plugin_name" == autolex-platform ]] || fail 'derived plugin directory name is unsafe'

# Fail before any production mutation unless the current active plugin is
# present and every exact-SHA reserved directory name is unused.
require_item "$plugins_dir" "$plugin_name" 'active plugin directory'
require_absent_item "$plugins_dir" "$staging_name" 'staging directory'
require_absent_item "$plugins_dir" "$backup_name" 'rollback directory'
require_absent_item "$plugins_dir" "$failed_name" 'failed-release directory'

cp -a plugin/autolex-platform "${work_dir}/${staging_name}"
( cd "$work_dir" && zip -rq "$zip_path" "$staging_name" -x '*/.git/*' '*/node_modules/*' '*/vendor/*' '*/.DS_Store' )
test -s "$zip_path" || fail 'release ZIP was not created'
printf 'PLUGIN_RELEASE_INFO: zip_sha256=%s\n' "$(sha256sum "$zip_path" | awk '{print $1}')"
printf 'PLUGIN_RELEASE_INFO: parent=%s active=%s staging=%s backup=%s\n' "$plugins_dir" "$plugin_name" "$staging_name" "$backup_name"

# Stage and prove the new tree while the live plugin is still untouched.
upload_zip "$zip_path" "$plugins_dir"
api2_fileop extract "$remote_zip"
require_item "$plugins_dir" "$plugin_name" 'active plugin directory after staging extract'
require_item "$plugins_dir" "$staging_name" 'staging directory after extract'
require_item "$staging_dir" 'autolex-platform.php' 'staged plugin entry file'
require_item "$staging_dir" 'includes' 'staged plugin includes directory'
printf 'PLUGIN_RELEASE_STAGE_OK: %s\n' "$staging_name"

# The only short mutation window begins here. Each failure path explicitly
# restores the known previous directory; no ERR-trap semantics are relied on.
if ! api2_fileop_try rename "$CPANEL_PLUGIN_DIR" "$backup_name"; then
  fail 'could not move active plugin to rollback snapshot; active release left untouched'
fi

if ! api2_fileop_try rename "$staging_dir" "$plugin_name"; then
  if api2_fileop_try rename "$backup_dir" "$plugin_name" && verify_restored_baseline; then
    printf 'PLUGIN_RELEASE_ROLLBACK: activation rename failed; previous plugin restored\n' >&2
    fail 'staging activation failed; previous plugin restored'
  fi
  fail 'staging activation failed and rollback could not be proven'
fi

if ! verify_live; then
  isolated=0
  restored=0
  if api2_fileop_try rename "$CPANEL_PLUGIN_DIR" "$failed_name"; then
    isolated=1
  fi
  if [[ "$isolated" -eq 1 ]] && api2_fileop_try rename "$backup_dir" "$plugin_name"; then
    restored=1
  fi
  if [[ "$restored" -eq 1 ]] && verify_restored_baseline; then
    printf 'PLUGIN_RELEASE_ROLLBACK: live proof failed; previous plugin restored, failed release isolated=%s\n' "$failed_name" >&2
    fail 'post-activation live contract failed; previous plugin restored'
  fi
  fail 'post-activation live contract failed and rollback could not be proven'
fi

printf 'PLUGIN_RELEASE_SUCCESS: sha=%s active=%s backup=%s staged_from=%s\n' \
  "$AUTOLEX_RELEASE_SHA" "$plugin_name" "$backup_dir" "$staging_name"
