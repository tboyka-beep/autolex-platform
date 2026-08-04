#!/usr/bin/env bash
set -euo pipefail

SCRIPT='scripts/autolex-live-production-qa.sh'
ROUTE='plugin/autolex-platform/includes/class-autolex-comparison-page.php'
[[ -f "$SCRIPT" ]] || { echo 'missing live QA script'; exit 1; }
[[ -f "$ROUTE" ]] || { echo 'missing comparison route'; exit 1; }

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

route_required=(
  "add_action('template_redirect'"
  "'/osszehasonlitas/'"
  "Autolex_Vehicle_Comparison::normalize_ids"
  "wp_safe_redirect"
  "home_url('/autok/')"
  "array('compare' => '1')"
)

for marker in "${route_required[@]}"; do
  grep -Fq -- "$marker" "$ROUTE" || {
    echo "missing comparison route contract marker: $marker"
    exit 1
  }
done

bash -n "$SCRIPT"
php -l "$ROUTE" >/dev/null

echo 'Live production QA and comparison route contract smoke test passed.'
