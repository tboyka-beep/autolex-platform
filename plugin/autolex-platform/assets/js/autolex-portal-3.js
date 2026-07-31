(() => {
  'use strict';

  const ready = (callback) => {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback);
    else callback();
  };

  ready(() => {
    const form = document.querySelector('[data-autolex-filter-form]');
    const grid = document.querySelector('[data-vehicle-grid]');
    const pagination = document.querySelector('[data-pagination]');
    const status = document.querySelector('[data-result-status]');
    const sort = document.querySelector('[data-sort-select]');
    const make = document.querySelector('[data-make-select]');
    const model = document.querySelector('[data-model-select]');
    const panel = document.querySelector('[data-filter-panel]');
    const open = document.querySelector('[data-filter-open]');
    const close = document.querySelector('[data-filter-close]');

    const setPanel = (isOpen) => {
      panel?.classList.toggle('is-open', isOpen);
      open?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (isOpen) panel?.querySelector('input, select, button')?.focus();
      else open?.focus();
    };

    open?.addEventListener('click', () => setPanel(true));
    close?.addEventListener('click', () => setPanel(false));
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && panel?.classList.contains('is-open')) setPanel(false);
    });

    if (!form || !grid || !pagination || !status || !window.AutolexPortal || !window.fetch) return;

    let controller;
    let timer;

    const currentParams = () => {
      const params = new URLSearchParams(new FormData(form));
      params.set('sort', sort?.value || 'data_desc');
      [...params.keys()].forEach((key) => {
        if (!String(params.get(key) || '').trim()) params.delete(key);
      });
      return params;
    };

    const load = async (page = 1, push = true) => {
      controller?.abort();
      controller = new AbortController();
      const params = currentParams();
      params.set('page', String(page));
      params.set('limit', '24');

      grid.classList.add('is-loading');
      status.hidden = false;
      status.textContent = AutolexPortal.labels?.loading || 'Adatok betöltése…';

      try {
        const url = new URL(AutolexPortal.vehiclesEndpoint);
        params.forEach((value, key) => url.searchParams.set(key, value));
        const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
        if (!response.ok) throw new Error('request_failed');
        const data = await response.json();
        grid.innerHTML = data.items?.length ? data.items.map(vehicleCard).join('') : emptyState();
        pagination.innerHTML = paginationMarkup(data);
        document.querySelectorAll('[data-result-count]').forEach((node) => { node.textContent = formatNumber(data.total || 0); });
        const summary = document.querySelector('[data-active-summary]');
        if (summary) summary.textContent = filterSummary(params);
        status.hidden = true;
        if (push) {
          const browserUrl = new URL(AutolexPortal.catalogUrl);
          params.forEach((value, key) => browserUrl.searchParams.set(key, value));
          window.history.replaceState({}, '', browserUrl);
        }
        setPanel(false);
      } catch (error) {
        if (error.name === 'AbortError') return;
        status.hidden = false;
        status.textContent = AutolexPortal.labels?.error || 'A szűrés átmenetileg nem érhető el.';
      } finally {
        grid.classList.remove('is-loading');
      }
    };

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      load(1);
    });
    sort?.addEventListener('change', () => load(1));
    form.addEventListener('change', (event) => {
      window.clearTimeout(timer);
      if (event.target === make) {
        if (model) {
          model.value = '';
          model.disabled = true;
        }
        updateModels(make.value).finally(() => {
          if (model) model.disabled = false;
          load(1);
        });
        return;
      }
      timer = window.setTimeout(() => load(1), 120);
    });
    form.querySelector('input[name="q"]')?.addEventListener('input', () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => load(1), 380);
    });
    pagination.addEventListener('click', (event) => {
      const link = event.target.closest('[data-page]');
      if (!link) return;
      event.preventDefault();
      load(Number(link.dataset.page || 1));
      document.getElementById('autolex-catalog')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    const updateModels = async (makeValue) => {
      if (!model) return;
      try {
        const url = new URL(AutolexPortal.facetsEndpoint);
        if (makeValue) url.searchParams.set('make', makeValue);
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        const data = await response.json();
        model.innerHTML = '<option value="">Összes modell</option>' + (data.models || []).map((item) => `<option value="${attr(item.value)}">${html(item.value)} (${formatNumber(item.total)})</option>`).join('');
      } catch (_) {
        // The server-rendered form remains fully usable without this enhancement.
      }
    };
  });

  const vehicleCard = (vehicle) => `
    <article class="alx3-vehicle-card">
      <header>
        <div class="alx3-brand-mark">${html(initials(vehicle.make))}</div>
        <div><span>${html(vehicle.make)}</span><h2><a href="${attr(vehicle.url)}">${html([vehicle.model, vehicle.generation].filter(Boolean).join(' '))}</a></h2></div>
        <b class="alx3-grade is-${attr(String(vehicle.data_grade || 'C').toLowerCase())}" title="Adatminőség">${html(vehicle.data_grade || 'C')}</b>
      </header>
      <div class="alx3-card-badges">
        <span class="is-${attr(vehicle.verification_status || 'imported')}">${html(vehicle.verification_label || 'Importált alaprekord')}</span>
        ${vehicle.evidence_count ? `<span>${formatNumber(vehicle.evidence_count)} bizonyíték</span>` : ''}
        ${vehicle.eu_observations ? `<span>${formatNumber(vehicle.eu_observations)} EU-megfigyelés</span>` : ''}
      </div>
      <dl class="alx3-specs">
        ${spec('Motor', vehicle.engine || 'nincs megadva')}
        ${spec('Motorkód', vehicle.engine_code || '—')}
        ${spec('Üzemanyag', vehicle.fuel_type || '—')}
        ${spec('Hengerűrtartalom', vehicle.capacity_cc ? `${formatNumber(vehicle.capacity_cc)} cm³` : '—')}
        ${spec('Teljesítmény', vehicle.power_ps ? `${formatNumber(vehicle.power_ps)} LE${vehicle.power_kw ? ` / ${formatNumber(vehicle.power_kw)} kW` : ''}` : '—')}
        ${spec('Gyártás', vehicle.years || '—')}
      </dl>
      <footer><small>Forrásállapot: ${html(vehicle.verification_label || 'Importált alaprekord')}</small><a href="${attr(vehicle.url)}">Teljes adatlap →</a></footer>
    </article>`;

  const spec = (name, value) => `<div><dt>${html(name)}</dt><dd>${html(value)}</dd></div>`;
  const emptyState = () => '<div class="alx3-empty"><b>Nincs találat</b><p>Próbálj kevesebb vagy tágabb szűrőt használni.</p></div>';
  const paginationMarkup = (data) => {
    if (!data.pages || data.pages < 2) return '';
    const previous = data.page > 1 ? `<a href="#" data-page="${data.page - 1}">← Előző</a>` : '';
    const next = data.page < data.pages ? `<a href="#" data-page="${data.page + 1}">Következő →</a>` : '';
    return `${previous}<span>${data.page} / ${data.pages} oldal</span>${next}`;
  };
  const filterSummary = (params) => {
    const labels = ['make', 'model', 'generation', 'fuel', 'engine_code', 'grade', 'verification'].map((key) => params.get(key)).filter(Boolean);
    return labels.length ? `• ${labels.join(' • ')}` : '• minden autó';
  };
  const initials = (value) => String(value || 'AL').trim().split(/[\s-]+/).slice(0, 2).map((part) => part[0] || '').join('').toUpperCase();
  const formatNumber = (value) => new Intl.NumberFormat('hu-HU', { maximumFractionDigits: 1 }).format(Number(value || 0));
  const html = (value) => { const node = document.createElement('div'); node.textContent = String(value ?? ''); return node.innerHTML; };
  const attr = (value) => html(value).replace(/`/g, '&#96;');
})();
