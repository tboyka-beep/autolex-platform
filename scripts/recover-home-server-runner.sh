#!/usr/bin/env bash
set -euo pipefail

RUNNER_USER="${RUNNER_USER:-tboy}"
INSTALL_DIR="${INSTALL_DIR:-/home/${RUNNER_USER}/actions-runner-autolex}"
EXPECTED_HOST="${EXPECTED_HOST:-home-server}"

fail() { echo "[autolex-runner] ERROR: $*" >&2; exit 1; }
log() { echo "[autolex-runner] $*"; }

[[ "$(hostname)" == "$EXPECTED_HOST" ]] || fail "wrong host: $(hostname), expected ${EXPECTED_HOST}"
[[ -d "$INSTALL_DIR" ]] || fail "runner directory missing: ${INSTALL_DIR}; run setup-home-server-runner.sh with a fresh RUNNER_TOKEN"
[[ -x "$INSTALL_DIR/run.sh" ]] || fail "runner installation is incomplete: ${INSTALL_DIR}/run.sh missing"
[[ -x "$INSTALL_DIR/svc.sh" ]] || fail "runner service helper missing: ${INSTALL_DIR}/svc.sh"

cd "$INSTALL_DIR"

if [[ ! -s .service ]]; then
  log "service unit is not registered for this runner; installing it for ${RUNNER_USER}"
  sudo ./svc.sh install "$RUNNER_USER"
fi

service_name="$(tr -d '\r\n' < .service 2>/dev/null || true)"
[[ -n "$service_name" ]] || fail "could not read the Autolex runner service name from ${INSTALL_DIR}/.service"
[[ "$service_name" == actions.runner.*.service ]] || fail "unexpected service name in .service: ${service_name}"

configured_name="$(jq -r '.agentName // empty' .runner 2>/dev/null || true)"
log "configured runner: ${configured_name:-unknown}"
log "Autolex runner service: ${service_name}"

sudo systemctl daemon-reload
sudo systemctl reset-failed "$service_name" || true
sudo systemctl enable "$service_name"
sudo systemctl restart "$service_name"
sleep 4

sudo systemctl is-active --quiet "$service_name" || {
  sudo systemctl status "$service_name" --no-pager || true
  sudo journalctl -u "$service_name" -n 120 --no-pager || true
  fail "Autolex runner service did not become active"
}

for command in php node zip curl jq; do
  command -v "$command" >/dev/null || fail "missing required command: ${command}"
done
php -r 'if (PHP_VERSION_ID < 80300) { fwrite(STDERR, "PHP 8.3 or newer is required.\n"); exit(1); } if (!extension_loaded("simplexml")) { fwrite(STDERR, "PHP SimpleXML is required.\n"); exit(1); }'

log "service active"
log "runner name: ${configured_name:-unknown}"
log "host: $(hostname)"
log "PHP: $(php -r 'echo PHP_VERSION;')"
log "Node: $(node --version)"
log "The workflow selects this runner by the autolex label; the runner display name may remain ${configured_name:-home-server}."
log "Next action: re-run the queued Autolex Home Server Quality job in GitHub Actions."
