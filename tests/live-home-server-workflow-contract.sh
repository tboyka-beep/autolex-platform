#!/usr/bin/env bash
set -euo pipefail

WORKFLOW='.github/workflows/autolex-live-home-server-qa.yml'
[[ -f "$WORKFLOW" ]] || { echo 'missing home-server live QA workflow'; exit 1; }

required=(
  'runs-on: [self-hosted, Linux, X64]'
  'test "$(hostname)" = "home-server"'
  "- 'plugin/autolex-platform/**'"
  'bash tests/live-production-qa-smoke.sh'
  'bash scripts/recover-autolex-safety-gate.sh'
  'bash scripts/autolex-live-production-qa.sh'
  'cancel-in-progress: true'
)

for marker in "${required[@]}"; do
  grep -Fq -- "$marker" "$WORKFLOW" || {
    echo "missing home-server live QA contract marker: $marker"
    exit 1
  }
done

# The old custom label left production validation queued even though the
# verified home-server quality runner was online. Never reintroduce it.
if grep -Fq -- 'home-server-autolex' "$WORKFLOW"; then
  echo 'stale home-server-autolex runner label must not be used'
  exit 1
fi

echo 'Autolex home-server live production workflow contract passed.'
