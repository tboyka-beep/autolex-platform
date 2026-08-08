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
  '/wp-json/autolex/v1/portal/facets'
  '/wp-json/autolex/v1/portal/vehicles?limit=48&sort=data_desc'
  'fuel_type_raw'
  'Benzin'
  'Dízel'
  'autolex-public-presentation.js'
  'autolex-public-presentation.css'
  'data-autolex-public-facts="true"'
  'RÖGZÍTETT KATALÓGUSADATOK'
  'vehicle summary overstates all catalogue values as independently verified'
  'Röviden erről a változatról'
  'no known English source fuel was proven through localized facets'
  '--retry-all-errors'
  '--connect-timeout'
)
for marker in "${required[@]}"; do
  grep -Fq -- "$marker" "$SCRIPT" || {
    echo "missing ALX-050 live presentation marker: $marker"
    exit 1
  }
done

for workflow in "$HOSTED" "$HOME"; do
  grep -Fq 'bash scripts/autolex-public-presentation-live-qa.sh' "$workflow" || {
    echo "ALX-050 live presentation proof is not wired into $workflow"
    exit 1
  }
done

bash -n "$SCRIPT"
echo 'Autolex Hungarian public presentation live QA contract passed.'
