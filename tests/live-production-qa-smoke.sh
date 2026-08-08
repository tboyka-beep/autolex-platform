#!/usr/bin/env bash
set -euo pipefail

SCRIPT='scripts/autolex-live-production-qa.sh'
RECOVERY='scripts/recover-autolex-safety-gate.sh'
WORKFLOW='.github/workflows/autolex-live-production-qa.yml'
ROUTE='plugin/autolex-platform/includes/class-autolex-comparison-page.php'
PORTAL='plugin/autolex-platform/includes/class-autolex-portal.php'
QUERY='plugin/autolex-platform/includes/trait-autolex-portal-query.php'
MAINTENANCE='plugin/autolex-platform/includes/class-autolex-maintenance-evidence.php'
SAFETY='plugin/autolex-platform/includes/class-autolex-safety-gate.php'
SEO='plugin/autolex-platform/includes/class-autolex-vehicle-seo.php'
MEDIA_CLASS='plugin/autolex-platform/includes/class-autolex-vehicle-media.php'
MEDIA_JS='plugin/autolex-platform/assets/js/autolex-vehicle-media.js'
MEDIA_CSS='plugin/autolex-platform/assets/css/autolex-vehicle-media.css'

for file in "$SCRIPT" "$RECOVERY" "$WORKFLOW" "$ROUTE" "$PORTAL" "$QUERY" "$MAINTENANCE" "$SAFETY" "$SEO" "$MEDIA_CLASS" "$MEDIA_JS" "$MEDIA_CSS"; do
  [[ -f "$file" ]] || { echo "missing launch QA dependency: $file"; exit 1; }
done

required=(
  'LIVE_QA_FAIL'
  'LIVE_QA_SUCCESS'
  '/wp-json/autolex/v1/status'
  'autolex-platform'
  'AUTOLEX_EXPECTED_VERSION'
  'AUTOLEX_EXPECT_INDEXING'
  'report_indexing_state'
  'assert_no_security_challenge'
  'assert_html_any'
  'assert_html_file_any'
  'fetch_json'
  'Please wait while your request is being verified'
  'data-reference-dashboard="true"'
  '/autok/'
  'Járműkatalógus'
  '/osszehasonlitas/'
  'alx3-compare'
  'AutolexVehicleMedia'
  'Opel_Corsa_F_IMG_5815'
  '/wp-content/plugins/autolex-platform/assets/js/autolex-vehicle-media.js'
  '/wp-content/plugins/autolex-platform/assets/css/autolex-vehicle-media.css'
  'setFailClosedVisibility'
  'exactGenerationPrefix'
  'LIVE_QA_OK: vehicle-media exact mapping and fail-closed assets are live'
  '/wp-json/autolex/v1/safety-gate-status'
  'official_eu_xml_fail_closed'
  '/wp-json/autolex/v1/portal/vehicles?limit=1&sort=data_desc'
  '/auto-adatlap/'
  'autolex-vehicle-detail'
  'application/ld+json'
  '/wp-json/autolex/v1/maintenance/'
  '/wp-json/autolex/v1/portal/vehicles?q='
  'alx3-vehicle-card'
  '/wp-json/autolex/v1/recalls?make='
  'Guaranteed public production routes'
  'production vehicle catalogue returned no sample'
  '--retry-all-errors'
  '--connect-timeout'
  '--max-time'
)

for marker in "${required[@]}"; do
  grep -Fq -- "$marker" "$SCRIPT" || {
    echo "missing live launch QA contract marker: $marker"
    exit 1
  }
done

# Every plugin OR theme change must schedule the production journey after
# merge. Public behavior must not bypass the broad live gate through a narrow
# path filter.
for path_marker in "- 'plugin/autolex-platform/**'" "- 'theme/autolex-theme/**'"; do
  grep -Fq -- "$path_marker" "$WORKFLOW" || {
    echo "live production QA trigger missing: $path_marker"
    exit 1
  }
  [[ "$(grep -Fc -- "$path_marker" "$WORKFLOW")" -ge 2 ]] || {
    echo "live QA trigger must cover pull_request and push: $path_marker"
    exit 1
  }
done

recovery_required=(
  'SAFETY_RECOVERY_FAIL'
  'SAFETY_RECOVERY_OK'
  'SAFETY_RECOVERY_WAIT'
  '/wp-json/autolex/v1/safety-gate-status'
  '/wp-cron.php?doing_wp_cron=autolex-safety-recovery-'
  'AUTOLEX_SAFETY_RECOVERY_ATTEMPTS'
  'Please wait while your request is being verified'
  '--retry-all-errors'
  '--max-time 150'
)
for marker in "${recovery_required[@]}"; do
  grep -Fq -- "$marker" "$RECOVERY" || {
    echo "missing Safety Gate recovery marker: $marker"
    exit 1
  }
done

# Retired/staging-only assumptions and hard-coded showcase vehicles must not
# silently return to production monitoring. The Corsa string above is only a
# deployed media-contract marker, never the dynamic live vehicle sample.
for stale in \
  "assert_html '/autok/' 'Autók'" \
  "assert_html_any '/jarmu/'" \
  "assert_html_any '/markak/'" \
  'BMW E87' \
  '118d'; do
  if grep -Fq -- "$stale" "$SCRIPT"; then
    echo "stale, fixture-only or hard-coded live assertion is active: $stale"
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

portal_required=(
  "'/portal/vehicles'"
  "'get_vehicles_response'"
  "'q'"
)
for marker in "${portal_required[@]}"; do
  grep -Fq -- "$marker" "$PORTAL" || {
    echo "missing portal REST contract marker: $marker"
    exit 1
  }
done

grep -Fq "home_url('/auto-adatlap/' . \$id" "$QUERY" || {
  echo 'dynamic vehicle result URL contract is missing'
  exit 1
}
grep -Fq "'/maintenance/(?P<vehicle_id>\\d+)'" "$MAINTENANCE" || {
  echo 'maintenance REST contract is missing'
  exit 1
}
for marker in \
  "'/safety-gate-status'" \
  "'/recalls'" \
  'const FETCH_ATTEMPTS = 3' \
  "const RETRY_HOOK = 'autolex_safety_gate_retry'" \
  "'httpversion' => '1.1'" \
  'wp_schedule_single_event(time() + 5, self::RETRY_HOOK)' \
  "add_action(self::RETRY_HOOK, array(\$this, 'sync'))"; do
  grep -Fq -- "$marker" "$SAFETY" || {
    echo "missing Safety Gate resilience contract marker: $marker"
    exit 1
  }
done
grep -Fq "~/auto-adatlap/(\\d+)" "$SEO" || {
  echo 'vehicle SEO dynamic route parser contract is missing'
  exit 1
}

# Vehicle media remains fail-closed in both server mapping and browser logic.
for marker in "'opel|corsa'" "'generation' => 'F'" 'Opel_Corsa_F_IMG_5815'; do
  grep -Fq -- "$marker" "$MEDIA_CLASS" || {
    echo "missing verified vehicle media mapping marker: $marker"
    exit 1
  }
done
for marker in 'setFailClosedVisibility' 'exactGenerationPrefix' 'alxMediaFailClosed'; do
  grep -Fq -- "$marker" "$MEDIA_JS" || {
    echo "missing fail-closed vehicle media JS marker: $marker"
    exit 1
  }
done
grep -Fq '.alx-verified-vehicle-media' "$MEDIA_CSS" || {
  echo 'vehicle media CSS contract is missing'
  exit 1
}

bash -n "$SCRIPT"
bash -n "$RECOVERY"
php -l "$ROUTE" >/dev/null
php -l "$PORTAL" >/dev/null
php -l "$MAINTENANCE" >/dev/null
php -l "$SAFETY" >/dev/null
php -l "$SEO" >/dev/null
php -l "$MEDIA_CLASS" >/dev/null

echo 'Autolex production launch dynamic live QA, vehicle media and Safety Gate recovery contract passed.'
