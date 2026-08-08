#!/usr/bin/env bash
set -euo pipefail

SCRIPT='scripts/probe-autolex-live-detail-response.sh'
WORKFLOW='.github/workflows/autolex-live-detail-response-diagnostics.yml'

for file in "$SCRIPT" "$WORKFLOW"; do
  [[ -f "$file" ]] || { echo "missing ALX-050F diagnostic dependency: $file"; exit 1; }
done

for marker in \
  'ALX050F_PROBE_FAIL' \
  'ALX050F_PROBE_OK' \
  'http_code=%{http_code}' \
  'effective_url=%{url_effective}' \
  'content_type=%{content_type}' \
  'redirects=%{num_redirects}' \
  'body_sha256=' \
  'body_prefix=' \
  'Please wait while your request is being verified' \
  '<!doctype html|<html' \
  'autolex-vehicle-detail' \
  'application/ld+json'; do
  grep -Fq -- "$marker" "$SCRIPT" || { echo "missing diagnostic marker: $marker"; exit 1; }
done

for marker in \
  'runs-on: [self-hosted, Linux, X64]' \
  '[[ "$(hostname)" == "home-server" ]]' \
  'bash scripts/probe-autolex-live-detail-response.sh'; do
  grep -Fq -- "$marker" "$WORKFLOW" || { echo "missing Home Server diagnostic gate marker: $marker"; exit 1; }
done

bash -n "$SCRIPT"
echo 'ALX-050F live detail response diagnostic contract passed.'
