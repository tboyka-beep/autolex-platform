#!/usr/bin/env bash
set -euo pipefail

gate='release/autolex-theme-release-gate.php'
workflow='.github/workflows/release-autolex-theme.yml'
release_script='scripts/release-autolex-theme.sh'
proof_script='scripts/prove-autolex-theme-release.sh'

fail() {
  echo "Theme release contract failed: $*" >&2
  exit 1
}

require_file() {
  local file="$1"
  test -f "$file" || fail "missing file: ${file}"
}

require_text() {
  local file="$1"
  local needle="$2"
  grep -Fq -- "$needle" "$file" || fail "${file} is missing required marker: ${needle}"
}

for file in "$gate" "$workflow" "$release_script" "$proof_script"; do
  require_file "$file"
done

require_text "$gate" 'hash_equals(AUTOLEX_THEME_RELEASE_TOKEN'
require_text "$gate" 'strlen(AUTOLEX_THEME_RELEASE_TOKEN) >= 32'
require_text "$gate" 'update_option(AUTOLEX_THEME_RELEASE_OPTION'
require_text "$gate" 'switch_theme(AUTOLEX_THEME_RELEASE_SLUG)'
require_text "$gate" "register_rest_route('autolex-release/v1', '/rollback'"
require_text "$gate" "delete_option('autolex_theme_release_sha')"
require_text "$gate" "AUTOLEX_THEME_RELEASE_SLUG . '@'"

require_text "$workflow" 'default: staging'
require_text "$workflow" 'push:'
require_text "$workflow" 'agent/autolex-light-theme-foundation'
require_text "$workflow" 'Prove activation and rollback in isolated WordPress'
require_text "$workflow" "if: github.event_name == 'push' || github.event.inputs.target == 'staging'"
require_text "$workflow" 'Production release is allowed only from main.'
require_text "$workflow" 'CPANEL_PLUGIN_DIR'
require_text "$workflow" 'wp-content/themes/autolex-theme'
require_text "$workflow" 'Rollback after failed production activation'
require_text "$workflow" "steps.deploy.outcome == 'failure'"
require_text "$workflow" 'actions/upload-artifact@v7'
require_text "$workflow" 'echo "::add-mask::${token}"'
require_text "$workflow" 'echo "AUTOLEX_THEME_RELEASE_TOKEN=${token}" >> "$GITHUB_ENV"'

require_text "$release_script" 'CPANEL_THEME_DIR'
require_text "$release_script" 'CPANEL_MU_PLUGIN_DIR'
require_text "$release_script" 'AUTOLEX_THEME_RELEASE_TOKEN'
require_text "$release_script" 'autolex-release/v1/activate'
require_text "$release_script" 'autolex-release/v1/theme-state'
require_text "$release_script" 'release-evidence/SHA256SUMS'
require_text "$release_script" '*/wp-content/themes/autolex-theme'
require_text "$release_script" '*/wp-content/mu-plugins'
require_text "$release_script" 'data-reference-dashboard="true"'
require_text "$release_script" "grep -Fq 'Minden jármű.'"
require_text "$release_script" "grep -Fq 'Minden adat.'"
require_text "$release_script" "grep -Fq 'Egy helyen.'"
require_text "$release_script" "grep -Fq '/themes/autolex-theme/'"

if grep -Fq "grep -Fq 'Minden jármű. Minden adat. Egy helyen.'" "$release_script"; then
  fail 'production QA must not assume the styled H1 is contiguous raw HTML'
fi

require_text "$proof_script" 'release-evidence/activation.json'
require_text "$proof_script" 'release-evidence/rollback.json'
require_text "$proof_script" 'release-evidence/emergency-rollback.json'
require_text "$proof_script" 'rollback_on_exit'
require_text "$proof_script" 'Minden jármű. Minden adat. Egy helyen.'
require_text "$proof_script" 'h1Count'
require_text "$proof_script" 'themeStylesheet'
require_text "$proof_script" '[320, 375, 768, 1024, 1440]'
require_text "$proof_script" '! -name SHA256SUMS'

if grep -Fq 'set -x' "$workflow" "$release_script" "$proof_script"; then
  fail 'release implementation enables shell tracing and may expose secrets'
fi

if grep -Eq '(^|[[:space:]])(echo|printf)[[:space:]]+.*\$\{?AUTOLEX_THEME_RELEASE_TOKEN\}?' "$release_script" "$proof_script"; then
  fail 'release scripts may print the release token'
fi

echo 'Autolex reversible theme release contract passed.'
