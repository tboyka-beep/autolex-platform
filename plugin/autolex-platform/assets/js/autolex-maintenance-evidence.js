document.addEventListener('DOMContentLoaded', async () => {
  if (!window.AutolexMaintenance) return;
  const match = location.pathname.match(/\/auto-adatlap\/(\d+)\//);
  if (!match) return;
  try {
    const response = await fetch(`${AutolexMaintenance.endpoint}${match[1]}?v=${encodeURIComponent(AutolexMaintenance.version)}`);
    if (!response.ok) return;
    const data = await response.json();
    if (data.status !== 'ok') return;
    renderMaintenance(data);
    renderSources(data);
    renderRecommendations(data);
  } catch (error) {
    // The legacy detail page remains usable if enrichment is unavailable.
  }
});

function sectionsByTitle(title) {
  return [...document.querySelectorAll('.alxbc-section')].find((section) => section.querySelector('h2')?.textContent.trim() === title);
}

function renderMaintenance(data) {
  const technical = sectionsByTitle('Műszaki adatok');
  if (!technical) return;
  let panel = document.querySelector('.alxp-maintenance');
  if (!panel) {
    panel = document.createElement('section');
    panel.className = 'alxbc-section alxp-maintenance';
    technical.after(panel);
  }
  panel.innerHTML = `<div class="alxbc-section-head"><h2>Karbantartási anyagok</h2><span>${escapeHtml(data.engine_code)}</span></div>
    <div class="alxp-maintenance-grid">${data.claims.map((claim) => `<article>
      <span>${escapeHtml(claim.label)}</span><strong>${escapeHtml(claim.value)}</strong>
      <p>${escapeHtml(claim.note || '')}</p><small class="is-${escapeHtml(claim.status)}">${statusLabel(claim.status)} • ${claim.confidence}% • ${claim.sources.length} forrás</small>
    </article>`).join('')}</div><p class="alxp-fitment-warning">${escapeHtml(data.disclaimer)}</p>`;
}

function renderSources(data) {
  const section = sectionsByTitle('Adatforrások és megerősítés');
  if (!section) return;
  section.innerHTML = `<div class="alxbc-section-head"><h2>Adatforrások és megerősítés</h2></div>
    <div class="alxbc-source-summary"><strong>${data.sources.length} eltárolt forrás</strong><span>állításonként ellenőrizve</span></div>
    <div class="alxp-source-list">${data.sources.map((source) => `<a href="${escapeAttribute(source.url)}" target="_blank" rel="noopener noreferrer">
      <span>${source.primary ? 'Elsődleges gyártói forrás' : 'Független műszaki megerősítés'}</span>
      <strong>${escapeHtml(source.publisher)}</strong><p>${escapeHtml(source.title)}</p><small>Ellenőrizve: ${escapeHtml(source.checked_at || '—')} →</small>
    </a>`).join('')}</div>`;
}

function renderRecommendations(data) {
  const section = sectionsByTitle('Kapcsolódó FrissAuto ajánlatok');
  if (!section || !data.recommendations.length) return;
  const exact = data.recommendations.filter((rule) => rule.rule_type !== 'fallback' && rule.product_url);
  const fallback = data.recommendations.filter((rule) => rule.rule_type === 'fallback');
  section.innerHTML = `<div class="alxbc-section-head"><h2>Illesztett FrissAuto ajánlatok</h2></div>
    <p class="alxp-recommendation-intro">${exact.length ? 'Az alábbi termékek a jármű motorkódjához és az előírt specifikációhoz igazodnak.' : 'Jelenleg nincs eltárolt, pontosan illesztett FrissAuto-termék ehhez a motorhoz, ezért biztonságos általános ajánlatokat mutatunk.'}</p>
    ${exact.length ? `<div class="alxp-product-rules alxp-product-rules--visual">${exact.map((rule) => productRule(rule)).join('')}</div>` : ''}
    ${fallback.length ? `<div class="alxp-fallback-head"><span>Konkrét, általános termékek</span><h3>FrissAuto-ajánlatok</h3><p>Ezek nem motoralkatrészek, ezért pontos motorillesztés nélkül is hasznos alternatívák. A feltüntetett méretet ettől még ellenőrizni kell.</p></div>
    <div class="alxp-product-rules alxp-product-rules--fallback alxp-product-rules--visual">${fallback.map((rule) => productRule(rule)).join('')}</div>` : ''}
    <p class="alxp-fitment-warning">Vásárlás előtt ellenőrizd a termék adatlapján a BMW-jóváhagyást, a motorkód-kompatibilitást vagy az univerzális termék méretét.</p>`;
}

function productRule(rule) {
  return `<a href="${escapeAttribute(rule.url)}" target="_blank" rel="nofollow sponsored noopener">
      ${rule.image_url ? `<img loading="lazy" src="${escapeAttribute(rule.image_url)}" alt="${escapeAttribute(rule.product_title || rule.label)}">` : ''}
      <span>${escapeHtml(rule.label)}</span><strong>${escapeHtml(rule.product_title || rule.required_spec)}</strong>
      ${rule.price_text ? `<b>${escapeHtml(rule.price_text)}</b>` : `<small>${escapeHtml(rule.required_spec)}</small>`}<em>Megnézem →</em>
    </a>`;
}

function statusLabel(status) { return status === 'verified' ? 'Megerősített' : status === 'needs_vin' ? 'VIN szükséges' : 'Több forrásból felülvizsgálva'; }
function escapeHtml(value) { const node=document.createElement('div'); node.textContent=String(value ?? ''); return node.innerHTML; }
function escapeAttribute(value) { return escapeHtml(value).replace(/`/g,'&#96;'); }
