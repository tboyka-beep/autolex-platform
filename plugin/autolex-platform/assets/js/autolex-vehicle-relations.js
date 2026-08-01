(() => {
  'use strict';

  document.addEventListener('DOMContentLoaded', initialiseRelations);

  async function initialiseRelations() {
    if (!window.AutolexVehicleRelations) return;
    const match = location.pathname.match(/\/auto-adatlap\/(\d+)(?:\/|$)/);
    if (!match) return;
    const root = document.querySelector('.alxbc-page-shell, .alxbc-dynamic-vehicle, main .entry-content, .entry-content');
    if (!root) return;

    addSafeBackLink(root);

    try {
      const response = await fetch(`${AutolexVehicleRelations.endpoint}${match[1]}?v=${encodeURIComponent(AutolexVehicleRelations.version)}`, {
        headers: { Accept: 'application/json' },
      });
      if (!response.ok) throw new Error(`relations_${response.status}`);
      const data = await response.json();
      if (data.status !== 'ok') return;
      renderRelations(root, data);
      enhanceOfferClarity(root);
      renderRecallSummary(root, data.recalls || {});
    } catch (error) {
      renderRelationError(root);
    }
  }

  function addSafeBackLink(root) {
    if (root.querySelector('.alx3-detail-back')) return;
    const link = document.createElement('a');
    link.className = 'alx3-detail-back';
    link.href = AutolexVehicleRelations.catalogUrl || '/autok/';
    link.textContent = '← Vissza az autókatalógushoz';
    root.prepend(link);
  }

  function renderRelations(root, data) {
    const section = document.createElement('section');
    section.className = 'alxbc-section alx3-detail-section alx3-related-vehicles';
    section.id = 'kapcsolodo-valtozatok';
    section.innerHTML = `<div class="alxbc-section-head alx3-detail-heading">
      <div><span>KATALÓGUSKAPCSOLATOK</span><h2>Kapcsolódó generációk és motorváltozatok</h2></div>
      <b>${formatNumber((data.generations || []).length + (data.engines || []).length)} kapcsolat</b>
    </div>
    <p class="alx3-related-intro">A kapcsolatok az Autolex katalógus márka–modell–generáció azonosságából készülnek. Nem jelentenek automatikus alkatrész-kompatibilitást.</p>
    ${relationGroup('Más generációk', 'Ugyanazon márka és modell további katalógusrekordjai', data.generations || [], false)}
    ${relationGroup('Motorváltozatok', 'Azonos generációhoz tartozó további motorazonosítások', data.engines || [], true)}
    <p class="alx3-related-policy">Az üres vagy hiányos mezőket a rendszer nem becsüli meg. Vásárlási vagy szervizdöntés előtt a motorkód és a VIN ellenőrzése szükséges.</p>`;

    const recommendation = root.querySelector('#frissauto-ajanlatok, .alxp-maintenance');
    if (recommendation) recommendation.before(section);
    else root.append(section);
  }

  function relationGroup(title, description, items, engines) {
    if (!items.length) {
      return `<div class="alx3-related-group"><header><div><span>${escapeHtml(title)}</span><p>${escapeHtml(description)}</p></div></header><div class="alx3-related-empty">Nincs további megbízhatóan azonosított rekord ebben a csoportban.</div></div>`;
    }
    return `<div class="alx3-related-group"><header><div><span>${escapeHtml(title)}</span><p>${escapeHtml(description)}</p></div><b>${formatNumber(items.length)}</b></header>
      <div class="alx3-related-grid">${items.map((item) => relationCard(item, engines)).join('')}</div></div>`;
  }

  function relationCard(item, engines) {
    const years = item.year_from ? `${item.year_from}${item.year_to ? `–${item.year_to}` : '–'}` : 'Évjárat nincs megadva';
    const power = item.power_ps ? `${formatNumber(item.power_ps)} LE` : item.power_kw ? `${formatNumber(item.power_kw)} kW` : 'Teljesítmény nincs megadva';
    const label = engines
      ? (item.engine_code || item.engine || 'Motorazonosítás hiányos')
      : (item.generation || 'Generáció nincs megadva');
    return `<a class="alx3-related-card" href="${escapeAttribute(item.url)}">
      <span>${engines ? 'MOTORVÁLTOZAT' : 'GENERÁCIÓ'}</span>
      <strong>${escapeHtml(label)}</strong>
      <p>${escapeHtml(item.make)} ${escapeHtml(item.model)}${engines && item.engine ? ` · ${escapeHtml(item.engine)}` : ''}</p>
      <dl><div><dt>Gyártás</dt><dd>${escapeHtml(years)}</dd></div><div><dt>Teljesítmény</dt><dd>${escapeHtml(power)}</dd></div></dl>
      <em>Adatlap megnyitása →</em>
    </a>`;
  }

  function renderRecallSummary(root, recalls) {
    const section = root.querySelector('#safety-gate-visszahivasok');
    if (!section || section.querySelector('.alx3-recall-summary')) return;
    const summary = document.createElement('div');
    summary.className = `alx3-recall-summary ${recalls.total ? 'is-attention' : 'is-clear'}`;
    const risks = Array.isArray(recalls.risk_types) ? recalls.risk_types : [];
    summary.innerHTML = `<div><span>Szöveges Safety Gate egyezés</span><strong>${formatNumber(recalls.total || 0)}</strong></div>
      <div><span>Legutóbbi bejegyzés</span><strong>${escapeHtml(recalls.latest_at || 'nincs')}</strong></div>
      <div><span>Gyakori kockázattípusok</span><strong>${risks.length ? risks.map((risk) => `${escapeHtml(risk.label)} (${formatNumber(risk.total)})`).join(', ') : 'nincs adat'}</strong></div>
      <p>Ez márka–modell szöveges egyezés, nem VIN-alapú visszahívási igazolás.</p>`;
    const heading = section.querySelector('.alx3-detail-heading');
    if (heading) heading.after(summary);
    else section.prepend(summary);
  }

  function enhanceOfferClarity(root) {
    const section = root.querySelector('#frissauto-ajanlatok');
    if (!section || section.querySelector('.alx3-offer-policy')) return;
    const policy = document.createElement('div');
    policy.className = 'alx3-offer-policy';
    policy.innerHTML = `<strong>Az ajánlási szintek jelentése</strong><ul>
      <li><b>Konkrét termék:</b> eltárolt terméklink, de a járműkompatibilitás ettől még ellenőrizendő.</li>
      <li><b>Specifikációs keresés:</b> motorkód vagy előírás alapján indított keresési útvonal.</li>
      <li><b>Univerzális ajánlat:</b> nem motoralkatrész jellegű termék, méretellenőrzéssel.</li>
    </ul>`;
    const legend = section.querySelector('.alx3-fitment-legend');
    if (legend) legend.after(policy);
    else section.prepend(policy);
  }

  function renderRelationError(root) {
    if (root.querySelector('.alx3-related-error')) return;
    const note = document.createElement('div');
    note.className = 'alx3-related-error';
    note.setAttribute('role', 'status');
    note.textContent = 'A kapcsolódó generációk és motorváltozatok átmenetileg nem érhetők el; a meglévő adatlap változatlanul használható.';
    const target = root.querySelector('#frissauto-ajanlatok');
    if (target) target.before(note);
    else root.append(note);
  }

  function formatNumber(value) {
    return new Intl.NumberFormat('hu-HU').format(Number(value) || 0);
  }

  function escapeHtml(value) {
    const node = document.createElement('div');
    node.textContent = String(value ?? '');
    return node.innerHTML;
  }

  function escapeAttribute(value) {
    return escapeHtml(value).replace(/`/g, '&#96;');
  }
})();
