#!/usr/bin/env bash
set -Eeuo pipefail

mode="${1:-}"
rm -rf release-build/theme release-build/gate
mkdir -p release-build/theme release-build/gate release-evidence/screenshots

required=(CPANEL_API_HOST CPANEL_API_USER CPANEL_API_TOKEN CPANEL_THEME_DIR CPANEL_MU_PLUGIN_DIR AUTOLEX_BASE_URL AUTOLEX_THEME_RELEASE_TOKEN)
for name in "${required[@]}"; do
  test -n "${!name:-}" || { echo "Missing ${name}" >&2; exit 1; }
done

case "$CPANEL_API_HOST" in *://*|*/*|*:*) echo 'Unsafe CPANEL_API_HOST' >&2; exit 1;; esac
case "$CPANEL_THEME_DIR" in /*|*..*|*[^A-Za-z0-9._/-]*) echo 'Unsafe CPANEL_THEME_DIR' >&2; exit 1;; esac
case "$CPANEL_THEME_DIR" in */wp-content/themes/autolex-theme) ;; *) echo 'Unexpected theme directory' >&2; exit 1;; esac
case "$CPANEL_MU_PLUGIN_DIR" in /*|*..*|*[^A-Za-z0-9._/-]*) echo 'Unsafe CPANEL_MU_PLUGIN_DIR' >&2; exit 1;; esac
case "$CPANEL_MU_PLUGIN_DIR" in */wp-content/mu-plugins) ;; *) echo 'Unexpected MU plugin directory' >&2; exit 1;; esac
case "$AUTOLEX_BASE_URL" in https://*) ;; *) echo 'HTTPS base URL required' >&2; exit 1;; esac
test "${#AUTOLEX_THEME_RELEASE_TOKEN}" -ge 32 || { echo 'Release token is too short' >&2; exit 1; }

if [ "$mode" = validate ]; then
  php tests/theme-release-filesystem-smoke.php
  echo 'Autolex release configuration and atomic rollback contract passed.'
  exit 0
fi
test "$mode" = release || { echo 'Usage: release-autolex-theme.sh validate|release' >&2; exit 2; }
test "${GITHUB_SHA:-}" != '' && [[ "$GITHUB_SHA" =~ ^[a-f0-9]{40}$ ]] || { echo 'Exact GITHUB_SHA is required.' >&2; exit 1; }

cp -a theme/autolex-theme/. release-build/theme/
printf '%s\n' "$GITHUB_SHA" > release-build/theme/.autolex-release-sha
cp release/autolex-theme-release-filesystem.php release-build/gate/autolex-theme-release-filesystem.php
python3 - <<'PY'
import os
from pathlib import Path
source = Path('release/autolex-theme-release-gate.php').read_text()
token = os.environ['AUTOLEX_THEME_RELEASE_TOKEN']
if '__AUTOLEX_RELEASE_TOKEN__' not in source:
    raise SystemExit('Release token placeholder is missing.')
escaped = token.replace('\\', '\\\\').replace("'", "\\'")
Path('release-build/gate/autolex-theme-release-gate.php').write_text(source.replace('__AUTOLEX_RELEASE_TOKEN__', escaped))
PY

api_base="https://${CPANEL_API_HOST}:2083"
auth_header="Authorization: cpanel ${CPANEL_API_USER}:${CPANEL_API_TOKEN}"
theme_root="$(dirname "$CPANEL_THEME_DIR")"
stage_dir="${theme_root}/.autolex-release/${GITHUB_SHA}/autolex-theme"

assert_no_challenge() {
  local body="$1" label="$2"
  if grep -Fqi 'Please wait while your request is being verified' "$body" || \
     grep -Fqi '<title>One moment, please...</title>' "$body"; then
    echo "cPanel security challenge intercepted ${label}." >&2
    exit 1
  fi
}

mkdir_remote() {
  local full="$1" parent name body
  parent="$(dirname "$full")"; name="$(basename "$full")"
  body="$(mktemp)"
  if ! curl --silent --show-error --fail-with-body --get --retry 4 --retry-all-errors \
      --header "$auth_header" \
      --data-urlencode "cpanel_jsonapi_user=${CPANEL_API_USER}" \
      --data-urlencode 'cpanel_jsonapi_apiversion=2' \
      --data-urlencode 'cpanel_jsonapi_module=Fileman' \
      --data-urlencode 'cpanel_jsonapi_func=mkdir' \
      --data-urlencode "path=${parent}" --data-urlencode "name=${name}" \
      --data-urlencode 'permissions=0755' "${api_base}/json-api/cpanel" >"$body"; then
    # Existing directories may make mkdir non-zero at the API level; transport
    # failures remain fatal and are handled by curl above.
    true
  fi
  assert_no_challenge "$body" "mkdir ${full}"
  rm -f "$body"
}

upload_tree() {
  local root="$1" destination="$2" rel remote file body
  mkdir_remote "$destination"
  while IFS= read -r -d '' dir; do
    rel="${dir#${root}/}"
    mkdir_remote "${destination}/${rel}"
  done < <(find "$root" -mindepth 1 -type d -print0 | sort -z)

  while IFS= read -r -d '' file; do
    rel="${file#${root}/}"; remote="$destination"
    test "$(dirname "$rel")" = . || remote="${destination}/$(dirname "$rel")"
    body="$(mktemp)"
    curl --silent --show-error --fail-with-body --retry 4 --retry-all-errors \
      --header "$auth_header" --form "dir=${remote}" --form 'overwrite=1' \
      --form "file-1=@${file};filename=$(basename "$file")" \
      "${api_base}/execute/Fileman/upload_files" >"$body"
    assert_no_challenge "$body" "upload ${rel}"
    python3 - "$body" "$rel" <<'PY'
import json, sys
path, label = sys.argv[1:]
try:
    data = json.load(open(path, encoding='utf-8'))
except Exception as exc:
    raise SystemExit(f'cPanel upload returned non-JSON for {label}: {exc}')
status = data.get('status')
if status not in (1, True, '1'):
    raise SystemExit(f'cPanel upload failed for {label}: status={status!r} errors={data.get("errors")!r}')
PY
    rm -f "$body"
  done < <(find "$root" -type f -print0 | sort -z)
}

# Gate/helper first: the currently active theme is untouched until the full new
# tree exists under a SHA-bound hidden staging directory.
upload_tree release-build/gate "$CPANEL_MU_PLUGIN_DIR"
upload_tree release-build/theme "$stage_dir"

base="${AUTOLEX_BASE_URL%/}"
header="X-Autolex-Release-Token: ${AUTOLEX_THEME_RELEASE_TOKEN}"
activated=0
previous_release=''

rollback_on_error() {
  local original_status="${1:-1}" rollback_status=0
  trap - ERR
  if [ "$activated" -eq 1 ]; then
    set +e
    curl --silent --show-error --fail --retry 4 --retry-all-errors --request POST \
      --header "$header" "$base/wp-json/autolex-release/v1/rollback" \
      > release-evidence/automatic-rollback.json
    rollback_status=$?
    if [ "$rollback_status" -eq 0 ]; then
      jq -e --arg previous "$previous_release" \
        '.status == "rolled_back" and .after.release == $previous' \
        release-evidence/automatic-rollback.json >/dev/null
      rollback_status=$?
    fi
    set -e
    if [ "$rollback_status" -ne 0 ]; then
      echo 'Automatic production theme rollback could not be proven.' >&2
    else
      echo 'Automatic production theme rollback completed and was proven.' >&2
    fi
  fi
  exit "$original_status"
}
trap 'rollback_on_error $?' ERR

curl --silent --show-error --fail --retry 5 --retry-all-errors --header "$header" \
  "$base/wp-json/autolex-release/v1/theme-state" | tee release-evidence/before.json
previous_release="$(jq -r '.state.release // ""' release-evidence/before.json)"

curl --silent --show-error --fail --retry 3 --retry-all-errors --request POST \
  --header 'Content-Type: application/json' --header "$header" \
  --data "{\"release_sha\":\"${GITHUB_SHA}\"}" \
  "$base/wp-json/autolex-release/v1/activate" | tee release-evidence/activation.json
activated=1
jq -e --arg sha "$GITHUB_SHA" \
  '.status == "activated" and .mode == "atomic_code_swap" and .after.stylesheet == "autolex-theme" and .after.release == $sha and .after.code_release == $sha and .rollback.available == true and .rollback.release_sha == $sha' \
  release-evidence/activation.json >/dev/null

curl --silent --show-error --fail --retry 5 --retry-all-errors --header "$header" \
  "$base/wp-json/autolex-release/v1/theme-state" | tee release-evidence/after.json
jq -e --arg sha "$GITHUB_SHA" \
  '.status == "ok" and .state.stylesheet == "autolex-theme" and .state.release == $sha and .state.code_release == $sha and .state.rollback.available == true and .state.rollback.mode == "atomic_code_swap"' \
  release-evidence/after.json >/dev/null
curl --silent --show-error --fail --retry 5 --retry-all-errors "$base/" > release-evidence/home.html

# The approved H1 intentionally contains <br> and <span> markup. Validate the
# semantic dashboard markers without assuming that its visible text is one
# contiguous HTML string.
grep -Fq 'data-reference-dashboard="true"' release-evidence/home.html
grep -Fq 'Minden jármű.' release-evidence/home.html
grep -Fq 'Minden adat.' release-evidence/home.html
grep -Fq 'Egy helyen.' release-evidence/home.html
grep -Fq '/themes/autolex-theme/' release-evidence/home.html

for width in 320 375 768 1024 1440; do
  npx --yes playwright@1.55.0 screenshot --device="Desktop Chrome" --viewport-size="${width},1100" --full-page \
    "$base/" "release-evidence/screenshots/home-${width}.png"
done

printf '{"repository":"%s","sha":"%s","run_id":"%s","previous_release":"%s","rollback_prepared":true}\n' \
  "$GITHUB_REPOSITORY" "$GITHUB_SHA" "$GITHUB_RUN_ID" "$previous_release" > release-evidence/manifest.json
find release-evidence -type f ! -name SHA256SUMS -print0 | sort -z | xargs -0 sha256sum > release-evidence/SHA256SUMS

# Release is healthy; retain the SHA-bound rollback snapshot for operator or
# automatic recovery. Do not consume it on successful production activation.
trap - ERR
printf 'Autolex production theme release OK: sha=%s rollback=prepared\n' "$GITHUB_SHA"
