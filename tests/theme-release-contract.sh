#!/usr/bin/env bash
set -euo pipefail

gate='release/autolex-theme-release-gate.php'
filesystem='release/autolex-theme-release-filesystem.php'
workflow='.github/workflows/release-autolex-theme.yml'
once_workflow='.github/workflows/alx-050c-theme-production-once.yml'
retry_workflow='.github/workflows/alx-050d-theme-production-retry-once.yml'
release_script='scripts/release-autolex-theme.sh'
proof_script='scripts/prove-autolex-theme-release.sh'
filesystem_smoke='tests/theme-release-filesystem-smoke.php'
release_marker='theme/autolex-theme/ALX-050C-RELEASE'
retry_marker='theme/autolex-theme/ALX-050D-RELEASE'

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

require_order() {
  local file="$1" first="$2" second="$3" first_line second_line
  first_line="$(grep -Fn -- "$first" "$file" | head -n 1 | cut -d: -f1)"
  second_line="$(grep -Fn -- "$second" "$file" | head -n 1 | cut -d: -f1)"
  test -n "$first_line" && test -n "$second_line" && [ "$first_line" -lt "$second_line" ] || \
    fail "${file} must place '${first}' before '${second}'"
}

for file in "$gate" "$filesystem" "$workflow" "$once_workflow" "$retry_workflow" "$release_script" "$proof_script" "$filesystem_smoke" "$release_marker" "$retry_marker"; do
  require_file "$file"
done

require_text "$gate" 'hash_equals(AUTOLEX_THEME_RELEASE_TOKEN'
require_text "$gate" 'strlen(AUTOLEX_THEME_RELEASE_TOKEN) >= 32'
require_text "$gate" "require_once __DIR__ . '/autolex-theme-release-filesystem.php'"
require_text "$gate" "'atomic_code_swap'"
require_text "$gate" 'autolex_theme_release_atomic_promote('
require_text "$gate" 'autolex_theme_release_atomic_rollback('
require_text "$gate" 'autolex_theme_release_atomic_rollback_available('
require_text "$gate" "'staged_release_missing'"
require_text "$gate" "register_rest_route('autolex-release/v1', '/rollback'"
require_text "$gate" "'rollback'      => autolex_theme_release_rollback_state()"
require_text "$gate" "'code_release'"
require_text "$gate" 'opcache_reset()'
require_text "$gate" 'delete_option(AUTOLEX_THEME_RELEASE_OPTION)'
require_text "$gate" "AUTOLEX_THEME_RELEASE_SLUG . '@'"

require_text "$filesystem" "'staged'      => \$root . '/.autolex-release/'"
require_text "$filesystem" "'backup'      => \$root . '/.autolex-rollback/'"
require_text "$filesystem" "'failed'      => \$root . '/.autolex-failed/'"
require_text "$filesystem" "rename(\$paths['active'], \$paths['backup'])"
require_text "$filesystem" "rename(\$paths['staged'], \$paths['active'])"
require_text "$filesystem" "rename(\$paths['active'], \$paths['failed'])"
require_text "$filesystem" "rename(\$paths['backup'], \$paths['active'])"
require_text "$filesystem" 'rollback_available'
require_text "$filesystem_smoke" 'theme-release-filesystem-smoke: OK'
require_text "$filesystem_smoke" 'autolex_theme_release_atomic_promote'
require_text "$filesystem_smoke" 'autolex_theme_release_atomic_rollback'
require_text "$filesystem_smoke" 'Failed release tree was not preserved for forensics.'

require_text "$workflow" 'default: staging'
require_text "$workflow" 'push:'
require_text "$workflow" 'agent/autolex-light-theme-foundation'
require_text "$workflow" 'Prove activation and rollback in isolated WordPress'
require_text "$workflow" "if: github.event_name == 'push' || github.event.inputs.target == 'staging'"
require_text "$workflow" 'Production release is allowed only from main.'
require_text "$workflow" 'CPANEL_PLUGIN_DIR'
require_text "$workflow" 'wp-content/themes/autolex-theme'
require_text "$workflow" 'php tests/theme-release-filesystem-smoke.php'
require_text "$workflow" 'autolex-theme-release-filesystem.php'
require_text "$workflow" 'Install exact production release browser'
require_text "$workflow" 'npx --yes playwright@1.55.0 install --with-deps chromium'
require_text "$workflow" 'Verify production release browser preflight'
require_text "$workflow" '/tmp/autolex-release-browser-preflight.png'
require_text "$workflow" 'Deploy, atomically activate and run immediate live QA'
require_text "$workflow" 'Emergency rollback check after failed production release'
require_text "$workflow" '.state.rollback.available == true'
require_text "$workflow" 'actions/upload-artifact@v7'
require_text "$workflow" 'echo "::add-mask::${token}"'
require_text "$workflow" 'echo "AUTOLEX_THEME_RELEASE_TOKEN=${token}" >> "$GITHUB_ENV"'
require_order "$workflow" 'npx --yes playwright@1.55.0 install --with-deps chromium' 'Prepare production release configuration'
require_order "$workflow" 'Verify production release browser preflight' 'Deploy, atomically activate and run immediate live QA'

require_text "$once_workflow" 'ALX-050C Production Theme Release Once'
require_text "$once_workflow" "- '.github/workflows/alx-050c-theme-production-once.yml'"
require_text "$once_workflow" 'git diff-tree --no-commit-id --name-only --diff-filter=A -r "$GITHUB_SHA"'
require_text "$once_workflow" "grep -Fx '.github/workflows/alx-050c-theme-production-once.yml'"
require_text "$once_workflow" "grep -Fx 'theme/autolex-theme/ALX-050C-RELEASE'"
require_text "$once_workflow" 'environment: production'
require_text "$once_workflow" 'bash scripts/release-autolex-theme.sh release'
require_text "$once_workflow" 'one-shot-emergency-rollback.json'
require_text "$release_marker" 'atomic same-slug promotion'

# ALX-050D is a separate add-only retry because the ALX-050C one-shot guard is
# intentionally consumed. The retry must prove the exact browser binary before
# any release configuration or activation begins.
require_text "$retry_workflow" 'ALX-050D Production Theme Retry Once'
require_text "$retry_workflow" "- '.github/workflows/alx-050d-theme-production-retry-once.yml'"
require_text "$retry_workflow" 'Enforce one-shot retry main commit boundary'
require_text "$retry_workflow" 'git diff-tree --no-commit-id --name-only --diff-filter=A -r "$GITHUB_SHA"'
require_text "$retry_workflow" "grep -Fx '.github/workflows/alx-050d-theme-production-retry-once.yml'"
require_text "$retry_workflow" "grep -Fx 'theme/autolex-theme/ALX-050D-RELEASE'"
require_text "$retry_workflow" 'environment: production'
require_text "$retry_workflow" 'Install exact production release browser'
require_text "$retry_workflow" 'npx --yes playwright@1.55.0 install --with-deps chromium'
require_text "$retry_workflow" 'Verify production release browser preflight'
require_text "$retry_workflow" '/tmp/autolex-release-browser-preflight.png'
require_text "$retry_workflow" 'Validate rollback-safe immutable retry'
require_text "$retry_workflow" 'bash scripts/release-autolex-theme.sh release'
require_text "$retry_workflow" 'alx-050d-emergency-rollback.json'
require_text "$retry_marker" 'Playwright 1.55.0 Chromium runtime before any production theme activation'
require_order "$retry_workflow" 'npx --yes playwright@1.55.0 install --with-deps chromium' 'Prepare production release configuration'
require_order "$retry_workflow" 'Verify production release browser preflight' 'bash scripts/release-autolex-theme.sh release'

require_text "$release_script" 'CPANEL_THEME_DIR'
require_text "$release_script" 'CPANEL_MU_PLUGIN_DIR'
require_text "$release_script" 'AUTOLEX_THEME_RELEASE_TOKEN'
require_text "$release_script" 'theme_root="$(dirname "$CPANEL_THEME_DIR")"'
require_text "$release_script" 'stage_base="${theme_root}/.autolex-release"'
require_text "$release_script" 'stage_release_root="${stage_base}/${GITHUB_SHA}"'
require_text "$release_script" 'stage_dir="${stage_release_root}/autolex-theme"'
require_text "$release_script" 'mkdir_remote "$stage_base"'
require_text "$release_script" 'mkdir_remote "$stage_release_root"'
require_text "$release_script" 'upload_tree release-build/gate "$CPANEL_MU_PLUGIN_DIR"'
require_text "$release_script" 'upload_tree release-build/theme "$stage_dir"'
require_text "$release_script" 'cPanel security challenge intercepted'
require_text "$release_script" 'automatic-rollback.json'
require_text "$release_script" '.rollback.available == true'
require_text "$release_script" '.state.rollback.mode == "atomic_code_swap"'
require_text "$release_script" 'rollback=prepared'
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

if grep -Fq 'upload_tree release-build/theme "$CPANEL_THEME_DIR"' "$release_script"; then
  fail 'production release must never overwrite the active theme tree directly'
fi

gate_line="$(grep -Fn 'upload_tree release-build/gate "$CPANEL_MU_PLUGIN_DIR"' "$release_script" | cut -d: -f1)"
stage_line="$(grep -Fn 'upload_tree release-build/theme "$stage_dir"' "$release_script" | cut -d: -f1)"
test -n "$gate_line" && test -n "$stage_line" && [ "$gate_line" -lt "$stage_line" ] || \
  fail 'authenticated release gate must be uploaded before the staged theme tree'

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

if grep -Fq 'set -x' "$workflow" "$once_workflow" "$retry_workflow" "$release_script" "$proof_script"; then
  fail 'release implementation enables shell tracing and may expose secrets'
fi

if grep -Eq '(^|[[:space:]])(echo|printf)[[:space:]]+.*\$\{?AUTOLEX_THEME_RELEASE_TOKEN\}?' "$release_script" "$proof_script"; then
  fail 'release scripts may print the release token'
fi

php "$filesystem_smoke" >/dev/null
bash -n "$release_script"
echo 'Autolex atomic same-slug reversible theme release and browser preflight contract passed.'
