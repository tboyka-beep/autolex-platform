#!/usr/bin/env bash
set -euo pipefail

REPOSITORY_URL="https://github.com/tboyka-beep/autolex-platform"
RUNNER_NAME="home-server-autolex"
RUNNER_LABELS="autolex"
RUNNER_USER="${RUNNER_USER:-tboy}"
INSTALL_DIR="${INSTALL_DIR:-/home/${RUNNER_USER}/actions-runner-autolex}"

if [[ "$(hostname)" != "home-server" ]]; then
  echo "This installer must run on the home-server machine." >&2
  exit 1
fi

if [[ -z "${RUNNER_TOKEN:-}" ]]; then
  echo "RUNNER_TOKEN is required. Create a fresh repository runner token in GitHub Settings → Actions → Runners." >&2
  exit 1
fi

for command in curl jq tar sudo; do
  command -v "$command" >/dev/null 2>&1 || {
    echo "Missing required command: $command" >&2
    exit 1
  }
done

if [[ -e "${INSTALL_DIR}/.runner" ]]; then
  echo "A runner is already configured in ${INSTALL_DIR}. Remove it through ./config.sh remove before reinstalling." >&2
  exit 1
fi

mkdir -p "$INSTALL_DIR"
cd "$INSTALL_DIR"

runner_version="$(curl --fail --silent --show-error --location \
  https://api.github.com/repos/actions/runner/releases/latest | jq -r '.tag_name // empty' | sed 's/^v//')"

if [[ -z "$runner_version" ]]; then
  echo "Could not resolve the latest official GitHub Actions runner version." >&2
  exit 1
fi

archive="actions-runner-linux-x64-${runner_version}.tar.gz"
download_url="https://github.com/actions/runner/releases/download/v${runner_version}/${archive}"

curl --fail --silent --show-error --location --retry 4 --retry-all-errors \
  --output "$archive" "$download_url"
tar xzf "$archive"
rm -f "$archive"

./config.sh \
  --url "$REPOSITORY_URL" \
  --token "$RUNNER_TOKEN" \
  --name "$RUNNER_NAME" \
  --labels "$RUNNER_LABELS" \
  --work "_work" \
  --unattended \
  --replace

sudo ./svc.sh install "$RUNNER_USER"
sudo ./svc.sh start

unset RUNNER_TOKEN

echo
echo "Autolex runner installed."
echo "Expected runner name: ${RUNNER_NAME}"
echo "Expected labels: self-hosted, Linux, X64, ${RUNNER_LABELS}"
echo "Service status:"
sudo ./svc.sh status
