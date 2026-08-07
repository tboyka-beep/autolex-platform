#!/usr/bin/env bash
set -euo pipefail

WORKFLOW='.github/workflows/sync-autolex-safety-gate.yml'
BUILDER='scripts/build-safety-gate-inbox-payload.php'
INBOX='plugin/autolex-platform/includes/class-autolex-safety-gate-inbox.php'
LIVE='scripts/autolex-live-production-qa.sh'

for file in "$WORKFLOW" "$BUILDER" "$INBOX" "$LIVE"; do
  [[ -f "$file" ]] || { echo "missing Safety Gate CI transport dependency: $file"; exit 1; }
done

builder_required=(
  'verified_inbox_v1'
  'GITHUB_SHA'
  'GITHUB_RUN_ID'
  'Autolex_Safety_Gate::DATASET_APIS'
  'Autolex_Safety_Gate::discover_xml_url'
  'Autolex_Safety_Gate::normalize_alert'
  'CURLPROTO_HTTPS'
  'CURL_HTTP_VERSION_1_1'
  'LIBXML_NONET | LIBXML_NOCDATA'
  '<!DOCTYPE'
  'recognized_vehicle_alerts'
  "'sha256' => \$sha"
  "'bytes' => \$bytes"
  "'commit_sha' => \$commit_sha"
  "'workflow_run_id' => \$run_id"
)
for marker in "${builder_required[@]}"; do
  grep -Fq -- "$marker" "$BUILDER" || { echo "missing CI builder marker: $marker"; exit 1; }
done

workflow_required=(
  'pull_request:'
  'schedule:'
  "cron: '23 4 * * *'"
  'workflow_dispatch:'
  'php scripts/build-safety-gate-inbox-payload.php safety-gate-build'
  'actions/upload-artifact@v7'
  'if: github.event_name != '\''pull_request'\'''
  'CPANEL_API_HOST'
  'CPANEL_API_USER'
  'CPANEL_API_TOKEN'
  'CPANEL_PLUGIN_DIR'
  'wp_root="${CPANEL_PLUGIN_DIR%/wp-content/plugins/autolex-platform}"'
  'inbox_parent="${wp_root}/wp-content"'
  'inbox_dir="${inbox_parent}/autolex-safety-gate-inbox"'
  'scripts/validate-cpanel-response.sh'
  'safety-gate-ingest-status'
  'manifest.json'
  'wp-cron.php?doing_wp_cron=autolex-safety-gate-'
  'last_payload_sha256'
  'last_commit_sha'
  'last_workflow_run_id'
  'scripts/autolex-live-production-qa.sh'
)
for marker in "${workflow_required[@]}"; do
  grep -Fq -- "$marker" "$WORKFLOW" || { echo "missing CI transport workflow marker: $marker"; exit 1; }
done

# Manifest must be the final uploaded data file so production never sees a
# complete trigger before its SHA-bound XML exists.
payload_line="$(grep -n 'upload_file "\$payload_file"' "$WORKFLOW" | head -n1 | cut -d: -f1)"
manifest_line="$(grep -n 'upload_file safety-gate-build/manifest.json' "$WORKFLOW" | head -n1 | cut -d: -f1)"
[[ -n "$payload_line" && -n "$manifest_line" && "$payload_line" -lt "$manifest_line" ]] || {
  echo 'Safety Gate payload must upload before manifest.json'
  exit 1
}

# Production upload steps must never run for PR validation.
secret_line="$(grep -n 'name: Validate production cPanel configuration' "$WORKFLOW" | head -n1 | cut -d: -f1)"
[[ -n "$secret_line" ]] || { echo 'missing production cPanel step'; exit 1; }
sed -n "${secret_line},$((secret_line + 2))p" "$WORKFLOW" | grep -Fq "if: github.event_name != 'pull_request'" || {
  echo 'production cPanel step is not PR-gated'
  exit 1
}

# The production-side class stays local/read-only from HTTP perspective.
grep -Fq "'/safety-gate-ingest-status'" "$INBOX"
grep -Fq "'methods' => 'GET'" "$INBOX"
if grep -Fq "'methods' => 'POST'" "$INBOX"; then
  echo 'verified inbox unexpectedly exposes public POST'
  exit 1
fi

php -l "$BUILDER" >/dev/null
bash -n "$LIVE"

echo 'Safety Gate CI transport contract passed.'
