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
  for cmd in bash curl jq zip sed grep sha256sum; do
    command -v "$cmd" >/dev/null || fail "missing command: $cmd"
  done
  test -f plugin/autolex-platform/autolex-platform.php || fail 'plugin entry file missing'
}

api_base() { printf 'https://%s:2083' "$CPANEL_API_HOST"; }
auth_header() { printf 'Authorization: cpanel %s:%s' "$CPANEL_API_USER" "$CPANEL_API_TOKEN"; }

api2_fileop() {
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
    "$(api_base)/json-api/cpanel")" || { rm -rf "$tmp"; fail "cPanel transport failure: ${op} ${source}"; }
  test "$code" = 200 || { sed -n '1,12p' "$body" >&2; rm -rf "$tmp"; fail "cPanel HTTP ${code}: ${op} ${source}"; }
  grep -Fqi '<title>One moment, please...</title>' "$body" && { rm -rf "$tmp"; fail "cPanel security challenge: ${op} ${source}"; }
  jq -e '.cpanelresult.event.result == 1 and ([.cpanelresult.data[]?.result] | all(. == 1))' "$body" >/dev/null || {
    cat "$body" >&2; rm -rf "$tmp"; fail "cPanel fileop rejected: ${op} ${source}";
  }
  rm -rf "$tmp"
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

verify_live() {
  local expected_version status detail_sample vehicle_url tmp
  expected_version="$(sed -n "s/define('AUTOLEX_PLATFORM_VERSION', '\([^']*\)');/\1/p" plugin/autolex-platform/autolex-platform.php)"
  test -n "$expected_version" || fail 'cannot resolve expected plugin version'
  status="$(curl --silent --show-error --fail --location --retry 4 --retry-delay 3 --retry-all-errors \
    --connect-timeout 15 --max-time 60 "${AUTOLEX_BASE_URL%/}/wp-json/autolex/v1/status")" || return 1
  jq -e --arg version "$expected_version" '.service == "autolex-platform" and .status == "ok" and .version == $version' <<<"$status" >/dev/null || return 1
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

validate_config
if [[ "$MODE" == validate ]]; then
  bash -n "$0"
  printf 'PLUGIN_RELEASE_OK: validation passed\n'
  exit 0
fi
[[ "$MODE" == release ]] || fail "unsupported mode: ${MODE}"

# cPanel API 2 Fileman::fileop treats source paths as relative to the account's
# /home directory, but rename destinations are names relative to the source
# directory. Keep full paths for source/upload/extract operations and basenames
# for rename destinations so a path can never be prefixed twice.
CPANEL_PLUGIN_DIR="${CPANEL_PLUGIN_DIR%/}"
plugins_dir="${CPANEL_PLUGIN_DIR%/autolex-platform}"
plugin_name="${CPANEL_PLUGIN_DIR##*/}"
release_token="$(printf '%s' "$AUTOLEX_RELEASE_SHA" | tr -cd 'A-Za-z0-9' | cut -c1-16)"
backup_name="${plugin_name}.rollback-${release_token}"
failed_name="${plugin_name}.failed-${release_token}"
backup_dir="${plugins_dir}/${backup_name}"
failed_dir="${plugins_dir}/${failed_name}"
zip_name="autolex-platform-${release_token}.zip"
zip_path="$(pwd)/${zip_name}"
remote_zip="${plugins_dir}/${zip_name}"

case "$plugins_dir" in
  */wp-content/plugins) ;;
  *) fail 'derived plugin parent directory is unsafe' ;;
esac
[[ "$plugin_name" == autolex-platform ]] || fail 'derived plugin directory name is unsafe'

rm -f "$zip_path"
( cd plugin && zip -rq "$zip_path" autolex-platform -x '*/.git/*' '*/node_modules/*' '*/vendor/*' '*/.DS_Store' )
test -s "$zip_path" || fail 'release ZIP was not created'
printf 'PLUGIN_RELEASE_INFO: zip_sha256=%s\n' "$(sha256sum "$zip_path" | awk '{print $1}')"
printf 'PLUGIN_RELEASE_INFO: parent=%s active=%s backup=%s\n' "$plugins_dir" "$plugin_name" "$backup_name"

upload_zip "$zip_path" "$plugins_dir"
api2_fileop rename "$CPANEL_PLUGIN_DIR" "$backup_name"
rollback_needed=1
rollback() {
  if [[ "${rollback_needed:-0}" == 1 ]]; then
    api2_fileop rename "$CPANEL_PLUGIN_DIR" "$failed_name" || true
    api2_fileop rename "$backup_dir" "$plugin_name" || true
    printf 'PLUGIN_RELEASE_ROLLBACK: restored previous plugin directory\n' >&2
  fi
}
trap rollback ERR

# Extract operates on the uploaded archive itself; cPanel's API does not use
# destfiles for extract, so omission avoids another relative-path ambiguity.
api2_fileop extract "$remote_zip"
verify_live || fail 'post-activation live contract failed'
rollback_needed=0
trap - ERR
printf 'PLUGIN_RELEASE_SUCCESS: sha=%s backup=%s failed=%s\n' "$AUTOLEX_RELEASE_SHA" "$backup_dir" "$failed_dir"
