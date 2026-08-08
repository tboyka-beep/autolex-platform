#!/usr/bin/env bash
set -euo pipefail

SCRIPT='scripts/autolex-public-presentation-live-qa.sh'
HOSTED='.github/workflows/autolex-live-production-qa.yml'
HOME='.github/workflows/autolex-live-home-server-qa.yml'

for file in "$SCRIPT" "$HOSTED" "$HOME"; do
  [[ -f "$file" ]] || { echo "missing ALX-050 live presentation dependency: $file"; exit 1; }
done

required=(
  'PUBLIC_PRESENTATION_LIVE_FAIL'
  'PUBLIC_PRESENTATION_LIVE_OK'
  'PUBLIC_PRESENTATION_LIVE_INFO'
  '/wp-json/autolex/v1/portal/facets'
  '/wp-json/autolex/v1/portal/vehicles?limit=48&sort=data_desc'
  'fuel_type_raw'
  "['petrol', 'Benzin']"
  "['gasoline', 'Benzin']"
  "['diesel', 'Dízel']"
  'MutationObserver'
  'public vehicle fuel is missing fuel_type_raw provenance'
  'raw English fuel leaked through public REST'
  'public fuel facet has no presentation label'
  'English fuel leaked as public facet label'
  'autolex-public-presentation.js'
  'autolex-public-presentation.css'
  'data-autolex-public-facts="true"'
  'RÖGZÍTETT KATALÓGUSADATOK'
  'vehicle summary overstates all catalogue values as independently verified'
  'Röviden erről a változatról'
  '--retry-all-errors'
  '--connect-timeout'
)
for marker in "${required[@]}"; do
  grep -Fq -- "$marker" "$SCRIPT" || {
    echo "missing ALX-050 live presentation marker: $marker"
    exit 1
  }
done

if grep -Fq 'no known English source fuel was proven through localized facets' "$SCRIPT"; then
  echo 'live proof must not depend on the current dataset containing an English raw fuel value'
  exit 1
fi

for workflow in "$HOSTED" "$HOME"; do
  grep -Fq 'bash scripts/autolex-public-presentation-live-qa.sh' "$workflow" || {
    echo "ALX-050 live presentation proof is not wired into $workflow"
    exit 1
  }
done

bash -n "$SCRIPT"
echo 'Autolex Hungarian public presentation live QA contract passed.'
