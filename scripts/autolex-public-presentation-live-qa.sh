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
presentation_js="$TMP_DIR/public-presentation.js"
fetch "${BASE_URL%/}/wp-json/autolex/v1/portal/facets" "$facets"
fetch "${BASE_URL%/}/wp-json/autolex/v1/portal/vehicles?limit=48&sort=data_desc" "$vehicles"
fetch "${BASE_URL%/}/autok/" "$catalog"
fetch "${BASE_URL%/}/wp-content/plugins/autolex-platform/assets/js/autolex-public-presentation.js" "$presentation_js"

# The deployed dynamic presentation asset itself must contain the explicit
# English-to-Hungarian mappings even when the current production dataset has
# no English raw fuel values at the moment this proof runs.
grep -Fq "['petrol', 'Benzin']" "$presentation_js" || fail 'live public presentation JS is missing Petrol -> Benzin mapping'
grep -Fq "['diesel', 'Dízel']" "$presentation_js" || fail 'live public presentation JS is missing Diesel -> Dízel mapping'
grep -Fq "['gasoline', 'Benzin']" "$presentation_js" || fail 'live public presentation JS is missing Gasoline -> Benzin mapping'
grep -Fq 'MutationObserver' "$presentation_js" || fail 'live public presentation JS is missing dynamic-content localization'

detail_url="$(python3 - "$facets" "$vehicles" <<'PY'
import json, sys
facets_path, vehicles_path = sys.argv[1:]
facets = json.load(open(facets_path, encoding='utf-8'))
vehicles = json.load(open(vehicles_path, encoding='utf-8'))

expected = {
    'petrol': 'Benzin',
    'gasoline': 'Benzin',
    'benzin': 'Benzin',
    'diesel': 'Dízel',
    'gasoil': 'Dízel',
    'dízel': 'Dízel',
    'dizel': 'Dízel',
    'electric': 'Elektromos',
    'electricity': 'Elektromos',
    'electric vehicle': 'Elektromos',
    'bev': 'Elektromos',
    'elektromos': 'Elektromos',
    'petrol/electric': 'Benzin / elektromos',
    'gasoline/electric': 'Benzin / elektromos',
    'diesel/electric': 'Dízel / elektromos',
    'hybrid': 'Hibrid',
    'hybrid electric': 'Hibrid',
    'hev': 'Hibrid',
    'plug-in hybrid': 'Plug-in hibrid',
    'plug in hybrid': 'Plug-in hibrid',
    'phev': 'Plug-in hibrid',
    'mild hybrid': 'Lágy hibrid',
    'mhev': 'Lágy hibrid',
    'lpg': 'LPG (autógáz)',
    'cng': 'CNG (sűrített földgáz)',
    'ng': 'Földgáz (NG)',
    'lng': 'LNG (cseppfolyósított földgáz)',
    'hydrogen': 'Hidrogén',
    'h2': 'Hidrogén',
    'ethanol': 'Etanol',
    'e85': 'E85 (etanol)',
    'biodiesel': 'Biodízel',
}
english_public_forbidden = {
    'petrol', 'gasoline', 'diesel', 'gasoil', 'electric', 'electricity',
    'electric vehicle', 'hybrid', 'hybrid electric', 'plug-in hybrid',
    'plug in hybrid', 'mild hybrid', 'hydrogen', 'ethanol', 'biodiesel',
}

def norm(value):
    return ' '.join(str(value or '').strip().lower().split()).replace(' / ', '/')

fuels = facets.get('fuels')
if not isinstance(fuels, list) or not fuels:
    raise SystemExit('PUBLIC_PRESENTATION_LIVE_FAIL: public fuel facets are empty')

known = 0
for item in fuels:
    if not isinstance(item, dict):
        raise SystemExit('PUBLIC_PRESENTATION_LIVE_FAIL: malformed fuel facet item')
    raw = str(item.get('value') or '').strip()
    label = str(item.get('label') or '').strip()
    if not raw:
        raise SystemExit('PUBLIC_PRESENTATION_LIVE_FAIL: empty fuel facet value')
    if not label:
        raise SystemExit(f'PUBLIC_PRESENTATION_LIVE_FAIL: public fuel facet has no presentation label: {raw!r}')
    key = norm(raw)
    label_key = norm(label)
    if label_key in english_public_forbidden:
        raise SystemExit(f'PUBLIC_PRESENTATION_LIVE_FAIL: English fuel leaked as public facet label: {label!r}')
    if key in expected:
        known += 1
        if label != expected[key]:
            raise SystemExit(f'PUBLIC_PRESENTATION_LIVE_FAIL: fuel facet {raw!r} label={label!r} expected={expected[key]!r}')

items = vehicles.get('items')
if not isinstance(items, list) or not items:
    raise SystemExit('PUBLIC_PRESENTATION_LIVE_FAIL: production catalogue has no vehicle sample')

fuel_rows = 0
raw_provenance_rows = 0
for item in items:
    if not isinstance(item, dict):
        continue
    public = str(item.get('fuel_type') or '').strip()
    raw = str(item.get('fuel_type_raw') or '').strip()
    if public:
        fuel_rows += 1
        if not raw:
            raise SystemExit('PUBLIC_PRESENTATION_LIVE_FAIL: public vehicle fuel is missing fuel_type_raw provenance')
        raw_provenance_rows += 1
    if norm(public) in english_public_forbidden:
        raise SystemExit(f'PUBLIC_PRESENTATION_LIVE_FAIL: raw English fuel leaked through public REST: {public!r}')
    raw_key = norm(raw)
    if raw_key in expected and public != expected[raw_key]:
        raise SystemExit(f'PUBLIC_PRESENTATION_LIVE_FAIL: REST fuel mismatch raw={raw!r} public={public!r} expected={expected[raw_key]!r}')

if fuel_rows == 0 or raw_provenance_rows == 0:
    raise SystemExit('PUBLIC_PRESENTATION_LIVE_FAIL: production vehicle sample cannot prove fuel raw/public provenance')

sample = next((item for item in items if isinstance(item, dict) and item.get('url')), None)
if not sample:
    raise SystemExit('PUBLIC_PRESENTATION_LIVE_FAIL: no vehicle detail URL in public REST sample')
print(f"PUBLIC_PRESENTATION_LIVE_INFO: fuel_facets={len(fuels)} known_facets={known} fuel_rows={fuel_rows} raw_provenance_rows={raw_provenance_rows}", file=sys.stderr)
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

printf 'PUBLIC_PRESENTATION_LIVE_OK: Hungarian fuel facets, REST raw/public provenance, dynamic terminology mappings and record-backed factual vehicle summary are live\n'
