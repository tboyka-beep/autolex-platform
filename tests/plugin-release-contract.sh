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

grep -Fq 'for cmd in bash curl jq zip sed grep sha256sum cp mktemp php' "$script"
grep -Fq 'cpanel_jsonapi_func=getdir' "$script"
grep -Fq -- "--data-urlencode 'dir=public_html'" "$script"
grep -Fq "php -r 'echo urldecode(\$argv[1]);'" "$script"
grep -Fq "grep -Eq '^/home/[^/]+/public_html\$'" "$script"
grep -Fq 'plugins_abs="$(resolve_plugins_abs)"' "$script"
grep -Fq 'remote_zip_abs="${plugins_abs}/${zip_name}"' "$script"
grep -Fq 'PLUGIN_RELEASE_ABS_PARENT_OK' "$script"

grep -Fq 'require_item "$plugins_dir" "$plugin_name"' "$script"
grep -Fq 'require_absent_item "$plugins_dir" "$staging_name"' "$script"
grep -Fq 'cp -a plugin/autolex-platform "${work_dir}/${staging_name}"' "$script"
grep -Fq 'api2_fileop extract "$remote_zip_abs" "$plugins_abs"' "$script"
grep -Fq 'PLUGIN_RELEASE_EXTRACT:' "$script"
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

# The absolute account path must be resolved before staging upload/extract, but
# only extract may use it. Existing upload/list/rename behavior stays relative.
resolve_line="$(grep -nF 'plugins_abs="$(resolve_plugins_abs)"' "$script" | head -1 | cut -d: -f1)"
upload_line="$(grep -nF 'upload_zip "$zip_path" "$plugins_dir"' "$script" | head -1 | cut -d: -f1)"
extract_line="$(grep -nF 'api2_fileop extract "$remote_zip_abs" "$plugins_abs"' "$script" | head -1 | cut -d: -f1)"
staged_entry_line="$(grep -nF "require_item \"\$staging_dir\" 'autolex-platform.php'" "$script" | head -1 | cut -d: -f1)"
backup_line="$(grep -nF 'if ! api2_fileop_try rename "$CPANEL_PLUGIN_DIR" "$backup_name"; then' "$script" | head -1 | cut -d: -f1)"
activate_line="$(grep -nF 'if ! api2_fileop_try rename "$staging_dir" "$plugin_name"; then' "$script" | head -1 | cut -d: -f1)"
verify_line="$(grep -nF 'if ! verify_live; then' "$script" | head -1 | cut -d: -f1)"
[[ "$resolve_line" -lt "$upload_line" ]]
[[ "$upload_line" -lt "$extract_line" ]]
[[ "$extract_line" -lt "$staged_entry_line" ]]
[[ "$staged_entry_line" -lt "$backup_line" ]]
[[ "$backup_line" -lt "$activate_line" ]]
[[ "$activate_line" -lt "$verify_line" ]]

# ALX-050M/P proved that implicit or relative extract destinations can report
# misleading success or double-prefix public_html. Forbid both regressions.
if grep -Fq 'api2_fileop extract "$remote_zip" "$plugins_dir"' "$script"; then
  echo 'Extract must not use account-relative source/destination paths.' >&2
  exit 1
fi
if grep -Fxq 'api2_fileop extract "$remote_zip"' "$script"; then
  echo 'Extract must not use an implicit cPanel destination.' >&2
  exit 1
fi

# Full paths are used only for extract. Rename destinations must remain
# basenames; changing this recreates the ALX-050H doubled-path failure.
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
if grep -Fq 'upload_zip "$zip_path" "$plugins_abs"' "$script"; then
  echo 'Upload must retain the proven account-relative destination.' >&2
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
