#!/usr/bin/env bash
set -euo pipefail

base="${AUTOLEX_BASE_URL:-http://127.0.0.1:8888}"
token="${AUTOLEX_THEME_RELEASE_TOKEN:?AUTOLEX_THEME_RELEASE_TOKEN is required}"
release_sha="${GITHUB_SHA:?GITHUB_SHA is required}"
baseline="${BASELINE_THEME:?BASELINE_THEME is required}"
header="X-Autolex-Release-Token: ${token}"

mkdir -p release-evidence/screenshots

for attempt in $(seq 1 30); do
  if curl --silent --show-error --fail "${base%/}/" >/dev/null; then
    break
  fi
  if [ "$attempt" -eq 30 ]; then
    echo 'Isolated WordPress did not become ready.' >&2
    exit 1
  fi
  sleep 2
done

curl --silent --show-error --fail --header "$header" \
  "${base%/}/wp-json/autolex-release/v1/theme-state" \
  | tee release-evidence/before.json
jq -e --arg baseline "$baseline" \
  '.status == "ok" and .state.stylesheet == $baseline and .state.release == ""' \
  release-evidence/before.json >/dev/null

curl --silent --show-error --fail --request POST \
  --header 'Content-Type: application/json' \
  --header "$header" \
  --data "{\"release_sha\":\"${release_sha}\"}" \
  "${base%/}/wp-json/autolex-release/v1/activate" \
  | tee release-evidence/activation.json
jq -e --arg sha "$release_sha" --arg baseline "$baseline" \
  '.status == "activated" and .before.stylesheet == $baseline and .after.stylesheet == "autolex-theme" and .after.release == $sha' \
  release-evidence/activation.json >/dev/null

curl --silent --show-error --fail --header "$header" \
  "${base%/}/wp-json/autolex-release/v1/theme-state" \
  | tee release-evidence/after.json
jq -e --arg sha "$release_sha" \
  '.status == "ok" and .state.stylesheet == "autolex-theme" and .state.release == $sha and (.state.design_marker | length) > 0' \
  release-evidence/after.json >/dev/null

curl --silent --show-error --fail "${base%/}/" > release-evidence/home.html
grep -Fq 'Minden jármű. Minden adat. Egy helyen.' release-evidence/home.html
grep -Fq '/themes/autolex-theme/' release-evidence/home.html

for width in 320 375 768 1024 1440; do
  npx playwright screenshot \
    --device="Desktop Chrome" \
    --viewport-size="${width},1100" \
    --full-page \
    "${base%/}/" \
    "release-evidence/screenshots/home-${width}.png"
done

curl --silent --show-error --fail --request POST \
  --header "$header" \
  "${base%/}/wp-json/autolex-release/v1/rollback" \
  | tee release-evidence/rollback.json
jq -e --arg baseline "$baseline" \
  '.status == "rolled_back" and .after.stylesheet == $baseline and .after.release == ""' \
  release-evidence/rollback.json >/dev/null

curl --silent --show-error --fail --header "$header" \
  "${base%/}/wp-json/autolex-release/v1/theme-state" \
  | tee release-evidence/final.json
jq -e --arg baseline "$baseline" \
  '.status == "ok" and .state.stylesheet == $baseline and .state.release == ""' \
  release-evidence/final.json >/dev/null

printf '{"repository":"%s","sha":"%s","run_id":"%s","baseline_theme":"%s"}\n' \
  "$GITHUB_REPOSITORY" "$release_sha" "$GITHUB_RUN_ID" "$baseline" \
  > release-evidence/manifest.json
find release-evidence -type f -print0 | sort -z | xargs -0 sha256sum > release-evidence/SHA256SUMS

echo 'Isolated Autolex activation and rollback proof passed.'
