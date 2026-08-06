#!/usr/bin/env bash
set -euo pipefail

gate='release/autolex-theme-release-gate.php'
workflow='.github/workflows/release-autolex-theme.yml'
script='scripts/release-autolex-theme.sh'

for file in "$gate" "$workflow" "$script"; do test -f "$file"; done

grep -Fq "hash_equals(AUTOLEX_THEME_RELEASE_TOKEN" "$gate"
grep -Fq "update_option(AUTOLEX_THEME_RELEASE_OPTION" "$gate"
grep -Fq "switch_theme(AUTOLEX_THEME_RELEASE_SLUG)" "$gate"
grep -Fq "register_rest_route('autolex-release/v1', '/rollback'" "$gate"
grep -Fq "delete_option('autolex_theme_release_sha')" "$gate"

grep -Fq "default: staging" "$workflow"
grep -Fq "environment: \${{ inputs.target }}" "$workflow"
grep -Fq "Rollback on failed live QA" "$workflow"
grep -Fq "if: failure()" "$workflow"
grep -Fq "actions/upload-artifact@v7" "$workflow"

grep -Fq "CPANEL_THEME_DIR" "$script"
grep -Fq "CPANEL_MU_PLUGIN_DIR" "$script"
grep -Fq "AUTOLEX_THEME_RELEASE_TOKEN" "$script"
grep -Fq "autolex-release/v1/activate" "$script"
grep -Fq "autolex-release/v1/theme-state" "$script"
grep -Fq "release-evidence/SHA256SUMS" "$script"
grep -Fq "*/wp-content/themes/autolex-theme" "$script"
grep -Fq "*/wp-content/mu-plugins" "$script"

if grep -Eq 'echo .*AUTOLEX_THEME_RELEASE_TOKEN|set -x' "$workflow" "$script"; then
  echo 'Release implementation may expose secrets.' >&2
  exit 1
fi

echo 'Autolex reversible theme release contract passed.'
