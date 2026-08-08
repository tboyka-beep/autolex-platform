#!/usr/bin/env bash
set -euo pipefail

SCRIPT='scripts/autolex-vehicle-media-live-qa.sh'
MEDIA='plugin/autolex-platform/includes/class-autolex-vehicle-media.php'
HOSTED='.github/workflows/autolex-live-production-qa.yml'
HOME='.github/workflows/autolex-live-home-server-qa.yml'

for file in "$SCRIPT" "$MEDIA" "$HOSTED" "$HOME"; do
  [[ -f "$file" ]] || { echo "missing vehicle media live QA dependency: $file"; exit 1; }
done

for marker in \
  'VEHICLE_MEDIA_LIVE_FAIL' \
  'VEHICLE_MEDIA_LIVE_OK' \
  'AutolexVehicleMedia' \
  'Opel_Corsa_F_IMG_5815' \
  'Nissan_Qashqai_%28J12%29_IMG_4900' \
  '"generation":"J12"' \
  'exactGenerationPrefix' \
  'setFailClosedVisibility'; do
  grep -Fq -- "$marker" "$SCRIPT" || {
    echo "missing vehicle media live QA marker: $marker"
    exit 1
  }
done

for marker in \
  "'nissan|qashqai'" \
  "'model'      => 'Qashqai'" \
  "'generation' => 'J12'" \
  'Nissan_Qashqai_%28J12%29_IMG_4900'; do
  grep -Fq -- "$marker" "$MEDIA" || {
    echo "missing Qashqai verified mapping marker: $marker"
    exit 1
  }
done

for workflow in "$HOSTED" "$HOME"; do
  grep -Fq -- 'bash scripts/autolex-vehicle-media-live-qa.sh' "$workflow" || {
    echo "vehicle media live proof is not wired into $workflow"
    exit 1
  }
  grep -Fq -- "- 'scripts/autolex-vehicle-media-live-qa.sh'" "$workflow" || {
    echo "vehicle media live proof changes do not trigger $workflow"
    exit 1
  }
done

bash -n "$SCRIPT"
php -l "$MEDIA" >/dev/null

echo 'Autolex verified vehicle media live QA contract passed.'
