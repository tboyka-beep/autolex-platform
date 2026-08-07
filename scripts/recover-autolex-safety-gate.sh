#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${AUTOLEX_BASE_URL:-https://autolex.hu}"
MAX_ATTEMPTS="${AUTOLEX_SAFETY_RECOVERY_ATTEMPTS:-18}"
WAIT_SECONDS="${AUTOLEX_SAFETY_RECOVERY_WAIT_SECONDS:-6}"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

fail() {
  printf 'SAFETY_RECOVERY_FAIL: %s\n' "$*" >&2
  exit 1
}

read_status() {
  local attempt="$1" body="$TMP_DIR/status-${attempt}.json" headers="$TMP_DIR/status-${attempt}.headers" code
  code="$(curl --silent --show-error --location \
    --retry 3 --retry-delay 2 --retry-all-errors \
    --connect-timeout 15 --max-time 45 \
    --dump-header "$headers" --output "$body" --write-out '%{http_code}' \
    "${BASE_URL%/}/wp-json/autolex/v1/safety-gate-status")" || return 2
  [[ "$code" == "200" ]] || return 3
  if grep -Fqi 'Please wait while your request is being verified' "$body" || \
     grep -Fqi '<title>One moment, please...</title>' "$body"; then
    return 4
  fi
  python3 - "$body" <<'PY'
import json, sys
try:
    data = json.load(open(sys.argv[1], encoding='utf-8'))
except Exception:
    raise SystemExit(5)
if data.get('service') != 'autolex-safety-gate':
    raise SystemExit(6)
print(json.dumps({
    'status': data.get('status'),
    'alerts': data.get('alerts'),
    'last_sync_at': data.get('last_sync_at'),
    'last_error': data.get('last_error'),
    'policy': data.get('policy'),
}, ensure_ascii=False))
raise SystemExit(0 if data.get('status') == 'ok' else 1)
PY
}

nudge_cron() {
  curl --silent --show-error --fail \
    --retry 3 --retry-delay 2 --retry-all-errors \
    --connect-timeout 15 --max-time 150 \
    "${BASE_URL%/}/wp-cron.php?doing_wp_cron=autolex-safety-recovery-${RANDOM}-${RANDOM}" \
    >/dev/null
}

for attempt in $(seq 1 "$MAX_ATTEMPTS"); do
  set +e
  status_output="$(read_status "$attempt" 2>&1)"
  status_code=$?
  set -e

  if [[ "$status_code" -eq 0 ]]; then
    printf 'SAFETY_RECOVERY_OK: attempt=%s %s\n' "$attempt" "$status_output"
    exit 0
  fi

  printf 'SAFETY_RECOVERY_WAIT: attempt=%s/%s code=%s status=%s\n' \
    "$attempt" "$MAX_ATTEMPTS" "$status_code" "$status_output" >&2

  if [[ "$attempt" -ge "$MAX_ATTEMPTS" ]]; then
    break
  fi

  # A status request runs WordPress init; the resilient importer schedules a
  # near-term one-shot recovery event when an earlier Safety Gate sync failed.
  sleep "$WAIT_SECONDS"
  nudge_cron || true
  sleep 2
 done

fail "Safety Gate did not return to status=ok after ${MAX_ATTEMPTS} bounded recovery checks"
