#!/usr/bin/env bash
set -euo pipefail

gate='release/autolex-theme-release-gate.php'
workflow='.github/workflows/release-autolex-theme.yml'
release_script='scripts/release-autolex-theme.sh'
proof_script='scripts/prove-autolex-theme-release.sh'

for file in "$gate" "$workflow" "$release_script" "$proof_script"; do test -f "$file"; done

grep -Fq "hash_equals(AUTOLEX_THEME_RELEASE_TOKEN" "$gate"
grep -Fq "strlen(AUTOLEX_THEME_RELEASE_TOKEN) >= 32" "$gate"
grep -Fq "update_option(AUTOLEX_THEME_RELEASE_OPTION" "$gate"
grep -Fq "switch_theme(AUTOLEX_THEME_RELEASE_SLUG)" "$gate"
grep -Fq "register_rest_route('autolex-release/v1', '/rollback'" "$gate"
grep -Fq "delete_option('autolex_theme_release_sha')" "$gate"
grep -Fq "autolex-theme@" "$gate"

grep -Fq "default: staging" "$workflow"
grep -Fq "push:" "$workflow"
grep -Fq "agent/autolex-light-theme-foundation" "$workflow"
grep -Fq "Prove activation and rollback in isolated WordPress" "$workflow"
grep -Fq "if: github.event_name == 'push' || github.event.inputs.target == 'staging'" "$workflow"
grep -Fq "Production release is allowed only from main." "$workflow"
grep -Fq "CPANEL_PLUGIN_DIR" "$workflow"
grep -Fq "wp-content/themes/autolex-theme" "$workflow"
grep -Fq "Rollback after failed production activation" "$workflow"
grep -Fq "steps.deploy.outcome == 'failure'" "$workflow"
grep -Fq "actions/upload-artifact@v7" "$workflow"

grep -Fq "CPANEL_THEME_DIR" "$release_script"
grep -Fq "CPANEL_MU_PLUGIN_DIR" "$release_script"
grep -Fq "AUTOLEX_THEME_RELEASE_TOKEN" "$release_script"
grep -Fq "autolex-release/v1/activate" "$release_script"
grep -Fq "autolex-release/v1/theme-state" "$release_script"
grep -Fq "release-evidence/SHA256SUMS" "$release_script"
grep -Fq "*/wp-content/themes/autolex-theme" "$release_script"
grep -Fq "*/wp-content/mu-plugins" "$release_script"

grep -Fq "release-evidence/activation.json" "$proof_script"
grep -Fq "release-evidence/rollback.json" "$proof_script"
grep -Fq "release-evidence/emergency-rollback.json" "$proof_script"
grep -Fq "rollback_on_exit" "$proof_script"
grep -Fq "Minden jármű. Minden adat. Egy helyen." "$proof_script"
grep -Fq "h1Count" "$proof_script"
grep -Fq "themeStylesheet" "$proof_script"
grep -Fq "[320, 375, 768, 1024, 1440]" "$proof_script"
grep -Fq "! -name SHA256SUMS" "$proof_script"

if grep -Eq 'echo .*AUTOLEX_THEME_RELEASE_TOKEN|set -x' "$workflow" "$release_script" "$proof_script"; then
  echo 'Release implementation may expose secrets.' >&2
  exit 1
fi

echo 'Autolex reversible theme release contract passed.'
