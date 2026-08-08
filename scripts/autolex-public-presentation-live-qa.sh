#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${AUTOLEX_BASE_URL:-https://autolex.hu}"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

fail() {
  printf 'PUBLIC_PRESENTATION_LIVE_FAIL: %s\n' "$*" >&2
  exit 1
}

fetch() {
  local url="$1" body="$2" code
  code="$(curl --silent --show-error --location \
    --retry 4 --retry-delay 3 --retry-all-errors \
    --connect-timeout 15 --max-time 60 \
    --output "$body" --write-out '%{http_code}' "$url")" || fail "transport error: $url"
  [[ "$code" == "200" ]] || fail "HTTP $code: $url"
}

facets="$TMP_DIR/facets.json"
vehicles="$TMP_DIR/vehicles.json"
catalog="$TMP_DIR/catalog.html"
fetch "${BASE_URL%/}/wp-json/autolex/v1/portal/facets" "$facets"
fetch "${BASE_URL%/}/wp-json/autolex/v1/portal/vehicles?limit=48&sort=data_desc" "$vehicles"
fetch "${BASE_URL%/}/autok/" "$catalog"

detail_url="$(python3 - "$facets" "$vehicles" <<'PY'
import json, sys
facets_path, vehicles_path = sys.argv[1:]
facets = json.load(open(facets_path, encoding='utf-8'))
vehicles = json.load(open(vehicles_path, encoding='utf-8'))

expected = {
    'petrol': 'Benzin',
    'gasoline': 'Benzin',
    'diesel': 'Dízel',
    'electric': 'Elektromos',
    'electricity': 'Elektromos',
    'petrol/electric': 'Benzin / elektromos',
    'gasoline/electric': 'Benzin / elektromos',
    'diesel/electric': 'Dízel / elektromos',
    'lpg': 'LPG (autógáz)',
    'cng': 'CNG (sűrített földgáz)',
    'hydrogen': 'Hidrogén',
    'e85': 'E85 (etanol)',
}

def norm(value):
    return ' '.join(str(value or '').strip().lower().split()).replace(' / ', '/')

fuels = facets.get('fuels')
if not isinstance(fuels, list) or not fuels:
    raise SystemExit('PUBLIC_PRESENTATION_LIVE_FAIL: public fuel facets are empty')
proved = 0
for item in fuels:
    if not isinstance(item, dict):
        continue
    raw = str(item.get('value') or '').strip()
    label = str(item.get('label') or '').strip()
    key = norm(raw)
    if key in expected:
        proved += 1
        if label != expected[key]:
            raise SystemExit(f'PUBLIC_PRESENTATION_LIVE_FAIL: fuel facet {raw!r} label={label!r} expected={expected[key]!r}')
if proved == 0:
    raise SystemExit('PUBLIC_PRESENTATION_LIVE_FAIL: no known English source fuel was proven through localized facets')

items = vehicles.get('items')
if not isinstance(items, list) or not items:
    raise SystemExit('PUBLIC_PRESENTATION_LIVE_FAIL: production catalogue has no vehicle sample')
for item in items:
    if not isinstance(item, dict):
        continue
    public = str(item.get('fuel_type') or '').strip()
    raw = str(item.get('fuel_type_raw') or '').strip()
    if norm(public) in expected:
        raise SystemExit(f'PUBLIC_PRESENTATION_LIVE_FAIL: raw English fuel leaked through REST: {public!r}')
    if norm(raw) in expected and public != expected[norm(raw)]:
        raise SystemExit(f'PUBLIC_PRESENTATION_LIVE_FAIL: REST fuel mismatch raw={raw!r} public={public!r}')

sample = next((item for item in items if isinstance(item, dict) and item.get('url')), None)
if not sample:
    raise SystemExit('PUBLIC_PRESENTATION_LIVE_FAIL: no vehicle detail URL in public REST sample')
print(str(sample['url']))
PY
)" || exit 1

detail="$TMP_DIR/detail.html"
fetch "$detail_url" "$detail"

grep -Fq 'autolex-public-presentation.js' "$catalog" || fail 'public terminology JS is not enqueued on live catalogue'
grep -Fq 'autolex-public-presentation.css' "$detail" || fail 'public factual-content stylesheet is not live on vehicle detail'
grep -Fq 'data-autolex-public-facts="true"' "$detail" || fail 'record-backed factual vehicle summary is missing from live detail page'
grep -Fq 'RÖGZÍTETT KATALÓGUSADATOK' "$detail" || fail 'vehicle summary must identify values as recorded catalogue data'
if grep -Fq 'ELLENŐRZÖTT KATALÓGUSADATOK' "$detail"; then
  fail 'vehicle summary overstates all catalogue values as independently verified'
fi
grep -Fq 'Röviden erről a változatról' "$detail" || fail 'Hungarian factual vehicle summary heading is missing'

python3 - "$catalog" "$detail" <<'PY'
import sys
from html.parser import HTMLParser

forbidden = {'petrol', 'gasoline', 'diesel', 'electric', 'electricity', 'primary', 'support', 'live query', 'frissauto search'}

class VisibleText(HTMLParser):
    def __init__(self):
        super().__init__()
        self.skip = 0
        self.bad = []
    def handle_starttag(self, tag, attrs):
        if tag.lower() in {'script', 'style', 'pre', 'code', 'textarea'}:
            self.skip += 1
    def handle_endtag(self, tag):
        if tag.lower() in {'script', 'style', 'pre', 'code', 'textarea'} and self.skip:
            self.skip -= 1
    def handle_data(self, data):
        if self.skip:
            return
        value = ' '.join(data.strip().lower().split())
        if value in forbidden:
            self.bad.append(value)

for path in sys.argv[1:]:
    parser = VisibleText()
    parser.feed(open(path, encoding='utf-8', errors='replace').read())
    if parser.bad:
        raise SystemExit(f'PUBLIC_PRESENTATION_LIVE_FAIL: untranslated visible terms in {path}: {parser.bad}')
PY

printf 'PUBLIC_PRESENTATION_LIVE_OK: Hungarian fuel facets, REST output and record-backed factual vehicle summary are live\n'
