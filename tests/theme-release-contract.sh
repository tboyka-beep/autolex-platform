#!/usr/bin/env bash
set -euo pipefail

gate='release/autolex-theme-release-gate.php'
workflow='.github/workflows/release-autolex-theme.yml'

test -f "$gate"
test -f "$workflow"

grep -Fq "hash_equals(AUTOLEX_THEME_RELEASE_TOKEN" "$gate"
grep -Fq "update_option(AUTOLEX_THEME_RELEASE_OPTION" "$gate"
grep -Fq "switch_theme(AUTOLEX_THEME_RELEASE_SLUG)" "$gate"
grep -Fq "register_rest_route('autolex-release/v1', '/rollback'" "$gate"
grep -Fq "delete_option('autolex_theme_release_sha')" "$gate"

grep -Fq "target:" "$workflow"
grep -Fq "default: staging" "$workflow"
grep -Fq "environment: \${{ inputs.target }}" "$workflow"
grep -Fq "Rollback on failed live QA" "$workflow"
grep -Fq "if: failure()" "$workflow"
grep -Fq "CPANEL_THEME_DIR" "$workflow"
grep -Fq "CPANEL_MU_PLUGIN_DIR" "$workflow"
grep -Fq "AUTOLEX_THEME_RELEASE_TOKEN" "$workflow"
grep -Fq "autolex-release/v1/activate" "$workflow"
grep -Fq "autolex-release/v1/rollback" "$workflow"
grep -Fq "actions/upload-artifact@v7" "$workflow"

if grep -Eq 'echo .*AUTOLEX_THEME_RELEASE_TOKEN|set -x' "$workflow"; then
  echo 'Release workflow may expose secrets.' >&2
  exit 1
fi

echo 'Autolex reversible theme release contract passed.'
