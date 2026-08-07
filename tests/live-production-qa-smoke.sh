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
  'assert_no_security_challenge'
  'assert_html_any'
  'Please wait while your request is being verified'
  'data-reference-dashboard="true"'
  '/autok/'
  'Járműkatalógus'
  '/osszehasonlitas/'
  'alx3-compare'
  'Guaranteed public production routes'
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

# Retired/staging-only assumptions must not silently return.
for stale in \
  "assert_html '/autok/' 'Autók'" \
  "assert_html_any '/jarmu/'" \
  "assert_html_any '/markak/'"; do
  if grep -Fq -- "$stale" "$SCRIPT"; then
    echo "stale or template-only live route assertion is active: $stale"
    exit 1
  fi
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

echo 'Live production QA public-route contract smoke test passed.'
