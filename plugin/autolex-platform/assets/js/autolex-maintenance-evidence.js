(() => {
  'use strict';

  document.addEventListener('DOMContentLoaded', initialiseVehicleDetail);

  async function initialiseVehicleDetail() {
    if (!window.AutolexMaintenance) return;
    const match = location.pathname.match(/\/auto-adatlap\/(\d+)(?:\/|$)/);
    if (!match) return;

    const root = document.querySelector('.alxbc-page-shell, .alxbc-dynamic-vehicle, main .entry-content, .entry-content');
    if (!root) return;

    document.body.classList.add('autolex-vehicle-detail');
    root.classList.add('alx3-vehicle-detail');
    const loading = renderLoading(root);

    try {
      const response = await fetch(`${AutolexMaintenance.endpoint}${match[1]}?v=${encodeURIComponent(AutolexMaintenance.version)}`, {
        headers: { Accept: 'application/json' },
      });
      if (!response.ok) throw new Error(`maintenance_${response.status}`);
      const data = await response.json();
      loading.remove();

      if (data.status !== 'ok') {
        renderUnavailable(root, 'Ehhez a járműhöz még nincs eltárolt kiegészítő bizonyíték. A meglévő műszaki adatlap továbbra is használható.');
        return;
      }

      decorateLegacySections(root);
      renderOverview(root, data);
      renderMaintenance(root, data);
      renderSources(root, data);
      renderRecommendations(root, data);
      await renderRecalls(root, data);
      renderNavigation(root);
    } catch (error) {
      loading.remove();
      renderUnavailable(root, AutolexMaintenance.labels?.error || 'A kiegészítő adatforrások átmenetileg nem érhetők el.');
    }
  }

  function renderLoading(root) {
    const section = document.createElement('section');
    section.className = 'alxbc-section alx3-detail-loading';
    section.setAttribute('aria-live', 'polite');
    section.innerHTML = `<div class="alx3-detail-loading__copy"><span></span><div><b>${escapeHtml(AutolexMaintenance.labels?.loading || 'Adatok betöltése…')}</b><small>Források, karbantartási állítások és visszahívások ellenőrzése</small></div></div>
      <div class="alx3-detail-skeleton"><i></i><i></i><i></i><i></i></div>`;
    const firstSection = root.querySelector('.alxbc-section');
    if (firstSection) firstSection.before(section);
    else root.prepend(section);
    return section;
  }

  function renderUnavailable(root, message) {
    const section = document.createElement('section');
    section.className = 'alxbc-section alx3-detail-unavailable';
    section.setAttribute('role', 'status');
    section.innerHTML = `<strong>Kiegészítő adatréteg</strong><p>${escapeHtml(message)}</p>`;
    const firstSection = root.querySelector('.alxbc-section');
    if (firstSection) firstSection.before(section);
    else root.prepend(section);
  }

  function decorateLegacySections(root) {
    [...root.querySelectorAll('.alxbc-section')].forEach((section, index) => {
      section.classList.add('alx3-detail-section');
      const title = section.querySelector('h2')?.textContent.trim() || '';
      if (!section.id) section.id = sectionId(title) || `adatlap-resz-${index + 1}`;
    });
  }

  function renderOverview(root, data) {
    const vehicle = data.vehicle || {};
    const summary = data.summary || {};
    const title = vehicle.title || document.querySelector('h1')?.textContent.trim() || 'Járműadatlap';
    const years = vehicle.year_from
      ? `${vehicle.year_from}${vehicle.year_to ? `–${vehicle.year_to}` : '–'}`
      : 'nincs megadva';
    const power = vehicle.power_ps
      ? `${formatNumber(vehicle.power_ps)} LE${vehicle.power_kw ? ` / ${formatNumber(vehicle.power_kw)} kW` : ''}`
      : 'nincs megadva';

    const section = document.createElement('section');
    section.className = 'alx3-detail-overview';
    section.id = 'adatlap-attekintes';
    section.innerHTML = `<div class="alx3-detail-overview__copy">
        <span class="alx3-detail-kicker">AUTOLEX 3.2 / FORRÁSOLT ADATLAP</span>
        <h1>${escapeHtml(title)}</h1>
        <p>A műszaki rekord, a karbantartási bizonyíték, a Safety Gate visszahívás és a vásárlási ajánlat külön megbízhatósági szinten jelenik meg.</p>
        <div class="alx3-detail-tags">
          ${vehicle.engine_code ? `<span>Motorkód: <b>${escapeHtml(vehicle.engine_code)}</b></span>` : ''}
          ${vehicle.fuel_type ? `<span>Hajtás: <b>${escapeHtml(vehicle.fuel_type)}</b></span>` : ''}
          <span>Gyártás: <b>${escapeHtml(years)}</b></span>
          <span>Teljesítmény: <b>${escapeHtml(power)}</b></span>
        </div>
      </div>
      <aside class="alx3-detail-confidence" aria-label="Adatbizalmi összesítés">
        <header><span></span><b>ADATBIZALMI ÁLLAPOT</b></header>
        <dl>
          ${metric('Karbantartási állítás', summary.claim_count || 0)}
          ${metric('Eltárolt forrás', summary.source_count || 0)}
          ${metric('Elsődleges forrás', summary.primary_sources || 0)}
          ${metric('Pontos keresési szabály', summary.exact_rules || 0)}
        </dl>
        <p class="${summary.vin_claims ? 'is-warning' : 'is-ok'}">${summary.vin_claims ? `${formatNumber(summary.vin_claims)} állítás VIN-ellenőrzést igényel` : 'Nincs jelzett VIN-köteles karbantartási állítás'}</p>
      </aside>`;

    const firstSection = root.querySelector('.alxbc-section');
    if (firstSection) firstSection.before(section);
    else root.prepend(section);
  }

  function metric(label, value) {
    return `<div><dt>${escapeHtml(label)}</dt><dd>${formatNumber(value)}</dd></div>`;
  }

  function renderMaintenance(root, data) {
    const section = ensureSection(root, 'Karbantartási anyagok', 'karbantartasi-anyagok', 'Műszaki adatok');
    section.classList.add('alxp-maintenance');
    const claims = data.claims || [];
    section.innerHTML = `<div class="alxbc-section-head alx3-detail-heading">
        <div><span>KARBANTARTÁSI BIZONYÍTÉK</span><h2>Karbantartási anyagok</h2></div>
        ${data.engine_code ? `<b>${escapeHtml(data.engine_code)}</b>` : ''}
      </div>
      ${claims.length ? `<div class="alxp-maintenance-grid">${claims.map(claimCard).join('')}</div>` : emptyPanel('Nincs eltárolt karbantartási állítás ehhez a változathoz.')}
      <p class="alxp-fitment-warning">${escapeHtml(data.disclaimer || '')}</p>`;
  }

  function claimCard(claim) {
    const status = sanitiseClass(claim.status || 'review');
    const sources = Array.isArray(claim.sources) ? claim.sources.length : 0;
    return `<article class="alx3-claim-card is-${status}">
      <header><span>${escapeHtml(claim.label)}</span><b>${formatNumber(claim.confidence || 0)}%</b></header>
      <strong>${escapeHtml(claim.value)}</strong>
      <p>${escapeHtml(claim.note || '')}</p>
      <footer><small class="is-${status}">${statusLabel(claim.status)}</small><span>${formatNumber(sources)} forrás</span></footer>
    </article>`;
  }

  function renderSources(root, data) {
    const section = ensureSection(root, 'Adatforrások és megerősítés', 'adatforrasok');
    const sources = data.sources || [];
    section.innerHTML = `<div class="alxbc-section-head alx3-detail-heading">
        <div><span>ELLENŐRIZHETŐ BIZONYÍTÉK</span><h2>Adatforrások és megerősítés</h2></div>
        <b>${formatNumber(sources.length)} forrás</b>
      </div>
      <div class="alx3-source-summary"><strong>${formatNumber((data.summary || {}).primary_sources || 0)} elsődleges forrás</strong><span>Az állítások forráskapcsolata külön tárolva</span></div>
      ${sources.length ? `<div class="alxp-source-list">${sources.map(sourceCard).join('')}</div>` : emptyPanel('Ehhez a járműhöz még nincs eltárolt forráskapcsolat.')}`;
  }

  function sourceCard(source) {
    return `<a class="alx3-source-card ${source.primary ? 'is-primary' : 'is-secondary'}" href="${escapeAttribute(source.url)}" target="_blank" rel="noopener noreferrer">
      <header><span>${source.primary ? 'Elsődleges gyártói forrás' : 'Független műszaki megerősítés'}</span><b>${source.primary ? 'PRIMARY' : 'SUPPORT'}</b></header>
      <strong>${escapeHtml(source.publisher)}</strong>
      <p>${escapeHtml(source.title)}</p>
      ${source.note ? `<small>${escapeHtml(source.note)}</small>` : ''}
      <footer><span>Ellenőrizve: ${escapeHtml(source.checked_at || '—')}</span><em>Forrás megnyitása →</em></footer>
    </a>`;
  }

  function renderRecommendations(root, data) {
    const section = ensureSection(root, 'Kapcsolódó FrissAuto ajánlatok', 'frissauto-ajanlatok');
    const recommendations = data.recommendations || [];
    const exact = recommendations.filter((rule) => rule.rule_type !== 'fallback');
    const fallback = recommendations.filter((rule) => rule.rule_type === 'fallback');
    const matched = exact.filter((rule) => rule.fitment === 'matched_product').length;
    const searches = exact.filter((rule) => rule.fitment === 'specification_search').length;

    section.innerHTML = `<div class="alxbc-section-head alx3-detail-heading">
        <div><span>SPECIFIKÁCIÓ-VEZÉRELT AJÁNLÁS</span><h2>FrissAuto ajánlatok</h2></div>
        <b>${formatNumber(recommendations.length)} útvonal</b>
      </div>
      <div class="alx3-fitment-legend">
        <span class="is-matched">${formatNumber(matched)} konkrét termék</span>
        <span class="is-search">${formatNumber(searches)} specifikációs keresés</span>
        <span class="is-universal">${formatNumber(fallback.length)} univerzális ajánlat</span>
      </div>
      ${exact.length ? `<div class="alx3-offer-group"><header><span>Pontos illesztési irány</span><h3>Motorkód és előírás alapján</h3><p>A keresési útvonal akkor is látható, ha még nincs egyetlen konkrét termékhez rögzítve.</p></header><div class="alxp-product-rules alxp-product-rules--visual">${exact.map(productRule).join('')}</div></div>` : emptyPanel('Ehhez a motorhoz még nincs specifikáció-alapú FrissAuto keresési szabály.')}
      ${fallback.length ? `<div class="alx3-offer-group is-fallback"><header><span>Biztonságos általános ajánlatok</span><h3>Nem motoralkatrész jellegű termékek</h3><p>A méretet és az univerzális kompatibilitást vásárlás előtt ezeknél is ellenőrizni kell.</p></header><div class="alxp-product-rules alxp-product-rules--visual">${fallback.map(productRule).join('')}</div></div>` : ''}
      <p class="alxp-fitment-warning">A FrissAuto-ajánlat nem minősül automatikus alkatrész-kompatibilitási igazolásnak. A termékoldalon ellenőrizd a specifikációt, motorkódot, méretet és szükség esetén a VIN-t.</p>`;
  }

  function productRule(rule) {
    const fitment = sanitiseClass(rule.fitment || (rule.rule_type === 'fallback' ? 'universal' : 'specification_search'));
    const title = rule.product_title || rule.required_spec || rule.label;
    const cta = fitment === 'matched_product' ? 'Termék megnyitása' : fitment === 'universal' ? 'Ajánlat megtekintése' : 'Keresés a FrissAutón';
    const badge = fitment === 'matched_product' ? 'Konkrét termék' : fitment === 'universal' ? 'Univerzális' : 'Specifikációs keresés';
    return `<a class="alx3-product-card is-${fitment}" href="${escapeAttribute(rule.url)}" target="_blank" rel="nofollow sponsored noopener">
      <div class="alx3-product-media">${rule.image_url ? `<img loading="lazy" src="${escapeAttribute(rule.image_url)}" alt="${escapeAttribute(title)}">` : '<span aria-hidden="true">FRISSAUTO<br>SEARCH</span>'}</div>
      <div class="alx3-product-copy"><span>${escapeHtml(rule.label)}</span><b class="alx3-fitment-badge">${escapeHtml(badge)}</b><strong>${escapeHtml(title)}</strong>
      <p>${escapeHtml(rule.fallback_reason || rule.required_spec || '')}</p>
      ${rule.price_text ? `<mark>${escapeHtml(rule.price_text)}</mark>` : `<small>Előírás: ${escapeHtml(rule.required_spec || 'ellenőrizendő')}</small>`}
      <em>${escapeHtml(cta)} →</em></div>
    </a>`;
  }

  async function renderRecalls(root, data) {
    const section = ensureSection(root, 'EU Safety Gate visszahívások', 'safety-gate-visszahivasok', 'Karbantartási anyagok');
    const vehicle = data.vehicle || {};
    section.innerHTML = `<div class="alxbc-section-head alx3-detail-heading"><div><span>HIVATALOS EU BIZTONSÁGI RÉTEG</span><h2>EU Safety Gate visszahívások</h2></div><b>LIVE QUERY</b></div>
      <div class="alx3-recall-status" aria-live="polite">Visszahívási adatok ellenőrzése…</div>`;

    if (!AutolexMaintenance.recallsEndpoint || (!vehicle.make && !vehicle.model)) {
      section.querySelector('.alx3-recall-status').innerHTML = emptyPanel('A jármű márka- és modellazonosítása nem elég pontos a Safety Gate lekérdezéshez.');
      return;
    }

    try {
      const url = new URL(AutolexMaintenance.recallsEndpoint);
      if (vehicle.make) url.searchParams.set('make', vehicle.make);
      if (vehicle.model) url.searchParams.set('model', vehicle.model);
      url.searchParams.set('limit', '12');
      const response = await fetch(url, { headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error(`recalls_${response.status}`);
      const payload = await response.json();
      const items = Array.isArray(payload.items) ? payload.items : [];
      section.querySelector('.alx3-recall-status').outerHTML = items.length
        ? `<div class="alx3-recall-summary"><strong>${formatNumber(items.length)} lehetséges egyezés</strong><span>Márka- és modell-szöveg alapján, VIN-ellenőrzés továbbra is szükséges</span></div><div class="alx3-recall-grid">${items.map(recallCard).join('')}</div>`
        : `<div class="alx3-recall-clear"><strong>Nincs eltárolt Safety Gate egyezés</strong><p>Ez nem jelenti azt, hogy a jármű biztosan visszahívásmentes. Ellenőrizd a gyártói VIN-alapú rendszert is.</p></div>`;
    } catch (error) {
      section.querySelector('.alx3-recall-status').innerHTML = emptyPanel('A Safety Gate visszahívási lekérdezés most nem érhető el. A műszaki adatlap ettől változatlan marad.');
    }
  }

  function recallCard(item) {
    return `<article class="alx3-recall-card">
      <header><span>${escapeHtml(item.reference_no || 'EU Safety Gate')}</span><time>${escapeHtml(item.notified_at || '—')}</time></header>
      <h3>${escapeHtml(item.brand || '')} ${escapeHtml(item.model || item.product_name || '')}</h3>
      <dl>
        <div><dt>Kockázat</dt><dd>${escapeHtml(item.risk_type || 'nincs megadva')}</dd></div>
        <div><dt>Típus</dt><dd>${escapeHtml(item.type_number || 'nincs megadva')}</dd></div>
      </dl>
      <p>${escapeHtml(item.risk_description || item.measures || 'A részletes leírás a hivatalos forrásban érhető el.')}</p>
      <footer><span>${escapeHtml(item.notifying_country || 'EU')}</span><a href="${escapeAttribute(item.source_url)}" target="_blank" rel="noopener noreferrer">Hivatalos forrás →</a></footer>
    </article>`;
  }

  function renderNavigation(root) {
    root.querySelector('.alx3-detail-nav')?.remove();
    const links = [
      ['adatlap-attekintes', 'Áttekintés'],
      ['muszaki-adatok', 'Műszaki adatok'],
      ['karbantartasi-anyagok', 'Karbantartás'],
      ['safety-gate-visszahivasok', 'Visszahívások'],
      ['adatforrasok', 'Források'],
      ['frissauto-ajanlatok', 'FrissAuto'],
    ].filter(([id]) => document.getElementById(id));
    if (!links.length) return;
    const nav = document.createElement('nav');
    nav.className = 'alx3-detail-nav';
    nav.setAttribute('aria-label', 'Járműadatlap szakaszai');
    nav.innerHTML = links.map(([id, label]) => `<a href="#${id}">${escapeHtml(label)}</a>`).join('');
    document.getElementById('adatlap-attekintes')?.after(nav);
  }

  function ensureSection(root, title, id, afterTitle = '') {
    let section = sectionsByTitle(root, title);
    if (!section) {
      section = document.createElement('section');
      section.className = 'alxbc-section alx3-detail-section';
      const after = afterTitle ? sectionsByTitle(root, afterTitle) : null;
      if (after) after.after(section);
      else root.append(section);
    }
    section.id = id;
    return section;
  }

  function sectionsByTitle(root, title) {
    return [...root.querySelectorAll('.alxbc-section')].find((section) => section.querySelector('h2')?.textContent.trim() === title);
  }

  function sectionId(title) {
    const mapping = {
      'Műszaki adatok': 'muszaki-adatok',
      'Adatforrások és megerősítés': 'adatforrasok',
      'Kapcsolódó FrissAuto ajánlatok': 'frissauto-ajanlatok',
    };
    return mapping[title] || '';
  }

  function emptyPanel(message) {
    return `<div class="alx3-detail-empty"><strong>Nincs megjeleníthető adat</strong><p>${escapeHtml(message)}</p></div>`;
  }

  function statusLabel(status) {
    const labels = {
      verified: 'Megerősített',
      reviewed: 'Felülvizsgált',
      review: 'Több forrásból felülvizsgálva',
      needs_vin: 'VIN szükséges',
      vin_required: 'VIN szükséges',
      conflict: 'Forrásellentmondás',
      provisional: 'Előzetes adat',
      proposed: 'Forrásalapú javaslat',
      imported: 'Importált alaprekord',
    };
    return labels[status] || 'Ellenőrzésre vár';
  }

  function sanitiseClass(value) {
    return String(value || '').toLowerCase().replace(/[^a-z0-9_-]+/g, '-') || 'unknown';
  }

  function formatNumber(value) {
    return new Intl.NumberFormat('hu-HU', { maximumFractionDigits: 1 }).format(Number(value || 0));
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
