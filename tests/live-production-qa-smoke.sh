#!/usr/bin/env bash
set -euo pipefail

SCRIPT='scripts/autolex-live-production-qa.sh'
[[ -f "$SCRIPT" ]] || { echo 'missing live QA script'; exit 1; }

required=(
  'LIVE_QA_FAIL'
  'LIVE_QA_SUCCESS'
  '/wp-json/autolex/v1/status'
  'autolex-platform'
  'AUTOLEX_EXPECTED_VERSION'
  'autolex-portal-3'
  '/autok/'
  '/osszehasonlitas/'
  '--retry-all-errors'
  '--connect-timeout'
  '--max-time'
)

for marker in "${required[@]}"; do
  grep -Fq -- "$marker" "$SCRIPT" || {
    echo "missing live QA contract marker: $marker"
    exit 1
  }
done

bash -n "$SCRIPT"

echo 'Live production QA contract smoke test passed.'
