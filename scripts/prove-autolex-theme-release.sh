#!/usr/bin/env bash
set -euo pipefail

base="${AUTOLEX_BASE_URL:-http://127.0.0.1:8888}"
token="${AUTOLEX_THEME_RELEASE_TOKEN:?AUTOLEX_THEME_RELEASE_TOKEN is required}"
release_sha="${GITHUB_SHA:?GITHUB_SHA is required}"
baseline="${BASELINE_THEME:?BASELINE_THEME is required}"
header="X-Autolex-Release-Token: ${token}"
rolled_back=0

mkdir -p release-evidence/screenshots

rollback_on_exit() {
  local status=$?
  local state_json=''
  local rollback_status=0
  trap - EXIT

  if [ "$rolled_back" -eq 0 ]; then
    set +e
    state_json="$(curl --silent --show-error --fail --header "$header" \
      "${base%/}/wp-json/autolex-release/v1/theme-state")"
    if printf '%s' "$state_json" | jq -e '.state.stylesheet == "autolex-theme"' >/dev/null 2>&1; then
      curl --silent --show-error --fail --request POST \
        --header "$header" \
        "${base%/}/wp-json/autolex-release/v1/rollback" \
        | tee release-evidence/emergency-rollback.json
      rollback_status=${PIPESTATUS[0]}
      if [ "$rollback_status" -eq 0 ]; then
        jq -e --arg baseline "$baseline" \
          '.status == "rolled_back" and .after.stylesheet == $baseline and .after.release == ""' \
          release-evidence/emergency-rollback.json >/dev/null
        rollback_status=$?
      fi
      if [ "$rollback_status" -ne 0 ]; then
        echo 'Emergency rollback could not be proven.' >&2
        status=1
      fi
    fi
    set -e
  fi

  exit "$status"
}
trap rollback_on_exit EXIT

for attempt in $(seq 1 30); do
  if curl --silent --show-error --fail --location "${base%/}/" >/dev/null; then
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

curl --silent --show-error --fail --location "${base%/}/" > release-evidence/home.html

node - "${base%/}/" <<'NODE'
const fs = require('node:fs');
const { chromium } = require('playwright');

(async () => {
  const url = process.argv[2];
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  await page.goto(url, { waitUntil: 'networkidle' });
  const headings = await page.locator('h1').allInnerTexts();
  const normalized = headings.map((value) => value.replace(/\s+/g, ' ').trim());
  if (normalized.length !== 1 || normalized[0] !== 'Minden jármű. Minden adat. Egy helyen.') {
    throw new Error(`Unexpected H1 set: ${JSON.stringify(normalized)}`);
  }

  const themeStylesheet = await page.locator('link[rel="stylesheet"]').evaluateAll((links) =>
    links.some((link) => link.href.includes('/themes/autolex-theme/'))
  );
  if (!themeStylesheet) {
    throw new Error('The rendered page does not load the Autolex theme stylesheet.');
  }

  const evidence = {
    finalUrl: page.url(),
    h1: normalized[0],
    h1Count: normalized.length,
    themeStylesheet,
  };
  fs.writeFileSync('release-evidence/dom-check.json', `${JSON.stringify(evidence, null, 2)}\n`);

  for (const width of [320, 375, 768, 1024, 1440]) {
    await page.setViewportSize({ width, height: 1100 });
    await page.goto(url, { waitUntil: 'networkidle' });
    await page.screenshot({
      path: `release-evidence/screenshots/home-${width}.png`,
      fullPage: true,
    });
  }

  await browser.close();
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
NODE

curl --silent --show-error --fail --request POST \
  --header "$header" \
  "${base%/}/wp-json/autolex-release/v1/rollback" \
  | tee release-evidence/rollback.json
jq -e --arg baseline "$baseline" \
  '.status == "rolled_back" and .after.stylesheet == $baseline and .after.release == ""' \
  release-evidence/rollback.json >/dev/null
rolled_back=1

curl --silent --show-error --fail --header "$header" \
  "${base%/}/wp-json/autolex-release/v1/theme-state" \
  | tee release-evidence/final.json
jq -e --arg baseline "$baseline" \
  '.status == "ok" and .state.stylesheet == $baseline and .state.release == ""' \
  release-evidence/final.json >/dev/null

printf '{"repository":"%s","sha":"%s","run_id":"%s","baseline_theme":"%s"}\n' \
  "$GITHUB_REPOSITORY" "$release_sha" "$GITHUB_RUN_ID" "$baseline" \
  > release-evidence/manifest.json
find release-evidence -type f ! -name SHA256SUMS -print0 | sort -z | xargs -0 sha256sum > release-evidence/SHA256SUMS

echo 'Isolated Autolex activation and rollback proof passed.'
