#!/usr/bin/env bash
set -euo pipefail

mode="${1:-}"
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
  echo 'Autolex release configuration contract passed.'
  exit 0
fi
test "$mode" = release || { echo 'Usage: release-autolex-theme.sh validate|release' >&2; exit 2; }

cp -a theme/autolex-theme/. release-build/theme/
printf '%s\n' "$GITHUB_SHA" > release-build/theme/.autolex-release-sha
python3 - <<'PY'
import os
from pathlib import Path
source = Path('release/autolex-theme-release-gate.php').read_text()
token = os.environ['AUTOLEX_THEME_RELEASE_TOKEN']
assert '__AUTOLEX_RELEASE_TOKEN__' in source
escaped = token.replace('\\', '\\\\').replace("'", "\\'")
Path('release-build/gate/autolex-theme-release-gate.php').write_text(source.replace('__AUTOLEX_RELEASE_TOKEN__', escaped))
PY

api_base="https://${CPANEL_API_HOST}:2083"
auth_header="Authorization: cpanel ${CPANEL_API_USER}:${CPANEL_API_TOKEN}"

mkdir_remote() {
  local full="$1" parent name
  parent="$(dirname "$full")"; name="$(basename "$full")"
  curl --silent --show-error --fail-with-body --get --retry 4 --retry-all-errors \
    --header "$auth_header" \
    --data-urlencode "cpanel_jsonapi_user=${CPANEL_API_USER}" \
    --data-urlencode 'cpanel_jsonapi_apiversion=2' \
    --data-urlencode 'cpanel_jsonapi_module=Fileman' \
    --data-urlencode 'cpanel_jsonapi_func=mkdir' \
    --data-urlencode "path=${parent}" --data-urlencode "name=${name}" \
    --data-urlencode 'permissions=0755' "${api_base}/json-api/cpanel" >/dev/null || true
}

upload_tree() {
  local root="$1" destination="$2" rel remote
  mkdir_remote "$destination"
  while IFS= read -r -d '' dir; do
    rel="${dir#${root}/}"
    mkdir_remote "${destination}/${rel}"
  done < <(find "$root" -mindepth 1 -type d -print0)
  while IFS= read -r -d '' file; do
    rel="${file#${root}/}"; remote="$destination"
    test "$(dirname "$rel")" = . || remote="${destination}/$(dirname "$rel")"
    curl --silent --show-error --fail-with-body --retry 4 --retry-all-errors \
      --header "$auth_header" --form "dir=${remote}" --form 'overwrite=1' \
      --form "file-1=@${file};filename=$(basename "$file")" \
      "${api_base}/execute/Fileman/upload_files" >/dev/null
  done < <(find "$root" -type f -print0)
}

upload_tree release-build/theme "$CPANEL_THEME_DIR"
upload_tree release-build/gate "$CPANEL_MU_PLUGIN_DIR"

base="${AUTOLEX_BASE_URL%/}"
header="X-Autolex-Release-Token: ${AUTOLEX_THEME_RELEASE_TOKEN}"
curl --silent --show-error --fail --retry 5 --retry-all-errors --header "$header" \
  "$base/wp-json/autolex-release/v1/theme-state" | tee release-evidence/before.json
curl --silent --show-error --fail --retry 3 --retry-all-errors --request POST \
  --header 'Content-Type: application/json' --header "$header" \
  --data "{\"release_sha\":\"${GITHUB_SHA}\"}" \
  "$base/wp-json/autolex-release/v1/activate" | tee release-evidence/activation.json
jq -e --arg sha "$GITHUB_SHA" '.status == "activated" and .after.stylesheet == "autolex-theme" and .after.release == $sha' release-evidence/activation.json >/dev/null

curl --silent --show-error --fail --retry 5 --retry-all-errors --header "$header" \
  "$base/wp-json/autolex-release/v1/theme-state" | tee release-evidence/after.json
jq -e --arg sha "$GITHUB_SHA" '.state.stylesheet == "autolex-theme" and .state.release == $sha' release-evidence/after.json >/dev/null
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

printf '{"repository":"%s","sha":"%s","run_id":"%s"}\n' "$GITHUB_REPOSITORY" "$GITHUB_SHA" "$GITHUB_RUN_ID" > release-evidence/manifest.json
find release-evidence -type f -print0 | sort -z | xargs -0 sha256sum > release-evidence/SHA256SUMS
