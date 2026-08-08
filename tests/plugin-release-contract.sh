#!/usr/bin/env bash
set -euo pipefail
script='scripts/release-autolex-plugin.sh'
test -f "$script"
bash -n "$script"

grep -Fq 'plugins_dir="${CPANEL_PLUGIN_DIR%/autolex-platform}"' "$script"
grep -Fq 'plugin_name="${CPANEL_PLUGIN_DIR##*/}"' "$script"
grep -Fq 'staging_name="${plugin_name}.release-${release_token}"' "$script"
grep -Fq 'backup_name="${plugin_name}.rollback-${release_token}"' "$script"
grep -Fq 'failed_name="${plugin_name}.failed-${release_token}"' "$script"
grep -Fq 'staging_dir="${plugins_dir}/${staging_name}"' "$script"
grep -Fq 'backup_dir="${plugins_dir}/${backup_name}"' "$script"
grep -Fq 'remote_zip="${plugins_dir}/${zip_name}"' "$script"

grep -Fq 'require_item "$plugins_dir" "$plugin_name"' "$script"
grep -Fq 'require_absent_item "$plugins_dir" "$staging_name"' "$script"
grep -Fq 'cp -a plugin/autolex-platform "${work_dir}/${staging_name}"' "$script"
grep -Fq 'api2_fileop extract "$remote_zip"' "$script"
grep -Fq "require_item \"\$staging_dir\" 'autolex-platform.php'" "$script"
grep -Fq 'if ! api2_fileop_try rename "$CPANEL_PLUGIN_DIR" "$backup_name"; then' "$script"
grep -Fq 'if ! api2_fileop_try rename "$staging_dir" "$plugin_name"; then' "$script"
grep -Fq 'api2_fileop_try rename "$backup_dir" "$plugin_name"' "$script"
grep -Fq 'if ! verify_live; then' "$script"
grep -Fq 'api2_fileop_try rename "$CPANEL_PLUGIN_DIR" "$failed_name"' "$script"
grep -Fq 'verify_restored_baseline' "$script"
grep -Fq 'PLUGIN_RELEASE_STAGE_OK' "$script"
grep -Fq 'PLUGIN_RELEASE_ROLLBACK' "$script"
grep -Fq "grep -Fqi 'autolex-vehicle-detail'" "$script"
grep -Fq "grep -Fqi 'application/ld+json'" "$script"

# Prove the staging tree is extracted and inspected before the live directory
# is renamed away. This is the core ALX-050L safety invariant.
extract_line="$(grep -nF 'api2_fileop extract "$remote_zip"' "$script" | head -1 | cut -d: -f1)"
staged_entry_line="$(grep -nF "require_item \"\$staging_dir\" 'autolex-platform.php'" "$script" | head -1 | cut -d: -f1)"
backup_line="$(grep -nF 'if ! api2_fileop_try rename "$CPANEL_PLUGIN_DIR" "$backup_name"; then' "$script" | head -1 | cut -d: -f1)"
activate_line="$(grep -nF 'if ! api2_fileop_try rename "$staging_dir" "$plugin_name"; then' "$script" | head -1 | cut -d: -f1)"
verify_line="$(grep -nF 'if ! verify_live; then' "$script" | head -1 | cut -d: -f1)"
[[ "$extract_line" -lt "$staged_entry_line" ]]
[[ "$staged_entry_line" -lt "$backup_line" ]]
[[ "$backup_line" -lt "$activate_line" ]]
[[ "$activate_line" -lt "$verify_line" ]]

# Full paths are sources; rename destinations must remain basenames. Reintroducing
# a full plugins path recreates the ALX-050H doubled public_html path failure.
if grep -Fq 'rename "$CPANEL_PLUGIN_DIR" "$backup_dir"' "$script"; then
  echo 'Full backup path must not be passed as a rename destination.' >&2
  exit 1
fi
if grep -Fq 'rename "$CPANEL_PLUGIN_DIR" "$failed_dir"' "$script"; then
  echo 'Full failed-release path must not be passed as a rename destination.' >&2
  exit 1
fi
if grep -Fq 'rename "$backup_dir" "$CPANEL_PLUGIN_DIR"' "$script"; then
  echo 'Full active plugin path must not be passed as a rollback rename destination.' >&2
  exit 1
fi

# A live failure must invoke explicit rollback logic. ERR trap behavior is not
# accepted because `cmd || fail` suppresses ERR trap propagation in Bash.
if grep -Fq 'trap rollback ERR' "$script"; then
  echo 'ERR-trap-only rollback is forbidden.' >&2
  exit 1
fi
if grep -Fq 'verify_live || fail' "$script"; then
  echo 'Live verification must use an explicit rollback branch.' >&2
  exit 1
fi
if grep -Fq '${plugins_dir}${zip_name}' "$script"; then
  echo 'Remote ZIP path must contain an explicit directory separator.' >&2
  exit 1
fi
if grep -Fq 'upload_file "$entry_file"' "$script"; then
  echo 'Per-file in-place deployment must not be used by the transactional release helper.' >&2
  exit 1
fi

printf 'PLUGIN_RELEASE_CONTRACT_OK\n'
