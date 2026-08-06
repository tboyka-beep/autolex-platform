#!/usr/bin/env bash
set -euo pipefail

run_wp() {
  npx wp-env run cli -- "$@"
}

extract_numeric_id() {
  grep -Eo '^[0-9]+$' | tail -n 1
}

run_wp wp theme activate autolex-theme
run_wp wp plugin activate autolex-platform || true
run_wp wp option update blogname 'Autolex'
run_wp wp option update permalink_structure '/%postname%/'
run_wp wp rewrite flush --hard

ensure_page() {
  local title="$1"
  local slug="$2"
  local template="$3"
  local id

  id="$(run_wp wp post list --post_type=page --name="$slug" --field=ID --format=ids | tr -d '\r' | extract_numeric_id || true)"
  if [[ -z "$id" ]]; then
    id="$(run_wp wp post create --post_type=page --post_status=publish --post_title="$title" --post_name="$slug" --porcelain | tr -d '\r' | extract_numeric_id)"
  fi
  if [[ -n "$template" ]]; then
    run_wp wp post meta update "$id" _wp_page_template "$template" >/dev/null
  fi
  printf '%s\n' "$id"
}

home_id="$(ensure_page 'Főoldal' 'fooldal' 'default')"
ensure_page 'Autók' 'autok' 'page-autok.php' >/dev/null
ensure_page 'Márkák' 'markak' 'page-markak.php' >/dev/null
ensure_page 'Modellek' 'modellek' 'page-modellek.php' >/dev/null
ensure_page 'Generációk' 'generaciok' 'page-generaciok.php' >/dev/null
ensure_page 'Jármű' 'jarmu' 'page-jarmu.php' >/dev/null
ensure_page 'Források' 'forrasok' 'page-forrasok.php' >/dev/null
ensure_page 'Visszahívások' 'visszahivasok' 'page-visszahivasok.php' >/dev/null
ensure_page 'Összehasonlítás' 'osszehasonlitas' 'page-osszehasonlitas.php' >/dev/null

[[ "$home_id" =~ ^[0-9]+$ ]]
run_wp wp option update show_on_front page
run_wp wp option update page_on_front "$home_id"
run_wp wp rewrite flush --hard
