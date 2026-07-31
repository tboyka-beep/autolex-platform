#!/usr/bin/env bash
set -euo pipefail

RUNNER_USER="${RUNNER_USER:-tboy}"
INSTALL_DIR="${INSTALL_DIR:-/home/${RUNNER_USER}/actions-runner-autolex}"
EXPECTED_HOST="${EXPECTED_HOST:-home-server}"
EXPECTED_RUNNER="${EXPECTED_RUNNER:-home-server-autolex}"

fail() { echo "[autolex-runner] ERROR: $*" >&2; exit 1; }
log() { echo "[autolex-runner] $*"; }

[[ "$(hostname)" == "$EXPECTED_HOST" ]] || fail "wrong host: $(hostname), expected ${EXPECTED_HOST}"
[[ -d "$INSTALL_DIR" ]] || fail "runner directory missing: ${INSTALL_DIR}; run setup-home-server-runner.sh with a fresh RUNNER_TOKEN"
[[ -x "$INSTALL_DIR/run.sh" ]] || fail "runner installation is incomplete: ${INSTALL_DIR}/run.sh missing"

cd "$INSTALL_DIR"

service_name="$(systemctl list-unit-files --type=service --no-legend 2>/dev/null | awk '/actions\.runner\..*\.service/ {print $1}' | head -n1 || true)"
if [[ -z "$service_name" && -x ./svc.sh ]]; then
  log "service unit not registered; installing it for ${RUNNER_USER}"
  sudo ./svc.sh install "$RUNNER_USER"
  service_name="$(systemctl list-unit-files --type=service --no-legend 2>/dev/null | awk '/actions\.runner\..*\.service/ {print $1}' | head -n1 || true)"
fi
[[ -n "$service_name" ]] || fail "could not resolve the GitHub Actions runner service"

log "runner service: ${service_name}"
sudo systemctl daemon-reload
sudo systemctl reset-failed "$service_name" || true
sudo systemctl enable "$service_name"
sudo systemctl restart "$service_name"
sleep 3

sudo systemctl is-active --quiet "$service_name" || {
  sudo systemctl status "$service_name" --no-pager || true
  sudo journalctl -u "$service_name" -n 120 --no-pager || true
  fail "runner service did not become active"
}

configured_name="$(jq -r '.agentName // empty' .runner 2>/dev/null || true)"
[[ -z "$configured_name" || "$configured_name" == "$EXPECTED_RUNNER" ]] || fail "configured runner is ${configured_name}, expected ${EXPECTED_RUNNER}"

for command in php node zip curl jq; do
  command -v "$command" >/dev/null || fail "missing required command: ${command}"
done
php -r 'if (PHP_VERSION_ID < 80300) { fwrite(STDERR, "PHP 8.3 or newer is required.\n"); exit(1); } if (!extension_loaded("simplexml")) { fwrite(STDERR, "PHP SimpleXML is required.\n"); exit(1); }'

log "service active"
log "runner name: ${configured_name:-unknown}"
log "host: $(hostname)"
log "PHP: $(php -r 'echo PHP_VERSION;')"
log "Node: $(node --version)"
log "Next action: re-run the queued Autolex Home Server Quality job in GitHub Actions."
