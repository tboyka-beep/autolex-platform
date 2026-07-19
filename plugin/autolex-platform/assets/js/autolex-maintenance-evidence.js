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
  section.innerHTML = `<div class="alxbc-section-head"><h2>Illesztett FrissAuto ajánlatok</h2></div>
    <p class="alxp-recommendation-intro">A keresések a jármű motorkódjához és az előírt specifikációhoz igazodnak; nem általános márkaajánlatok.</p>
    <div class="alxp-product-rules">${data.recommendations.map((rule) => `<a href="${escapeAttribute(rule.url)}" target="_blank" rel="nofollow sponsored noopener">
      <span>${escapeHtml(rule.label)}</span><strong>${escapeHtml(rule.required_spec)}</strong><small>Keresés a FrissAuto kínálatában</small><em>Megnézem →</em>
    </a>`).join('')}</div><p class="alxp-fitment-warning">Vásárlás előtt ellenőrizd a termék adatlapján a BMW-jóváhagyást és a motorkód-kompatibilitást.</p>`;
}

function statusLabel(status) { return status === 'verified' ? 'Megerősített' : status === 'needs_vin' ? 'VIN szükséges' : 'Több forrásból felülvizsgálva'; }
function escapeHtml(value) { const node=document.createElement('div'); node.textContent=String(value ?? ''); return node.innerHTML; }
function escapeAttribute(value) { return escapeHtml(value).replace(/`/g,'&#96;'); }
