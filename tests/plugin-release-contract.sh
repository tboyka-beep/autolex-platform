#!/usr/bin/env bash
set -euo pipefail
script='scripts/release-autolex-plugin.sh'
test -f "$script"
bash -n "$script"

grep -Fq 'plugins_dir="${CPANEL_PLUGIN_DIR%/autolex-platform}"' "$script"
grep -Fq 'plugin_name="${CPANEL_PLUGIN_DIR##*/}"' "$script"
grep -Fq 'backup_name="${plugin_name}.rollback-${release_token}"' "$script"
grep -Fq 'failed_name="${plugin_name}.failed-${release_token}"' "$script"
grep -Fq 'backup_dir="${plugins_dir}/${backup_name}"' "$script"
grep -Fq 'remote_zip="${plugins_dir}/${zip_name}"' "$script"

grep -Fq 'api2_fileop rename "$CPANEL_PLUGIN_DIR" "$backup_name"' "$script"
grep -Fq 'api2_fileop rename "$CPANEL_PLUGIN_DIR" "$failed_name"' "$script"
grep -Fq 'api2_fileop rename "$backup_dir" "$plugin_name"' "$script"
grep -Fq 'api2_fileop extract "$remote_zip"' "$script"
grep -Fq 'verify_live || fail' "$script"
grep -Fq 'PLUGIN_RELEASE_ROLLBACK' "$script"
grep -Fq "grep -Fqi 'autolex-vehicle-detail'" "$script"
grep -Fq "grep -Fqi 'application/ld+json'" "$script"

# Rename destinations are interpreted relative to the source directory by the
# cPanel Fileman API. A full plugins path here recreates the ALX-050H doubled
# public_html/wp-content/plugins production failure.
if grep -Fq 'api2_fileop rename "$CPANEL_PLUGIN_DIR" "$backup_dir"' "$script"; then
  echo 'Full backup path must not be passed as a rename destination.' >&2
  exit 1
fi
if grep -Fq 'api2_fileop rename "$CPANEL_PLUGIN_DIR" "$failed_dir"' "$script"; then
  echo 'Full failed-release path must not be passed as a rename destination.' >&2
  exit 1
fi
if grep -Fq 'api2_fileop rename "$backup_dir" "$CPANEL_PLUGIN_DIR"' "$script"; then
  echo 'Full active plugin path must not be passed as a rollback rename destination.' >&2
  exit 1
fi
if grep -Fq '${plugins_dir}${zip_name}' "$script"; then
  echo 'Remote ZIP path must contain an explicit directory separator.' >&2
  exit 1
fi
if grep -Fq 'upload_file "$entry_file"' "$script"; then
  echo 'Per-file in-place deployment must not be used by the atomic release helper.' >&2
  exit 1
fi

printf 'PLUGIN_RELEASE_CONTRACT_OK\n'
