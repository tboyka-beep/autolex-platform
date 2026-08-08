#!/usr/bin/env bash
set -euo pipefail
script='scripts/release-autolex-plugin.sh'
test -f "$script"
bash -n "$script"
grep -Fq 'api2_fileop rename "$CPANEL_PLUGIN_DIR" "$backup_dir"' "$script"
grep -Fq 'api2_fileop extract' "$script"
grep -Fq 'verify_live || fail' "$script"
grep -Fq 'PLUGIN_RELEASE_ROLLBACK' "$script"
grep -Fq "grep -Fqi 'autolex-vehicle-detail'" "$script"
grep -Fq "grep -Fqi 'application/ld+json'" "$script"
if grep -Fq 'upload_file "$entry_file"' "$script"; then
  echo 'Per-file in-place deployment must not be used by the atomic release helper.' >&2
  exit 1
fi
printf 'PLUGIN_RELEASE_CONTRACT_OK\n'
