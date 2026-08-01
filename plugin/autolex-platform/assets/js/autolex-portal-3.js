(() => {
  'use strict';

  const portalScript = document.currentScript?.src || '';
  const ready = (callback) => {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback);
    else callback();
  };

  const ensureSearchStyles = () => {
    if (!portalScript || document.querySelector('link[data-autolex-search-css]')) return;
    const href = portalScript.replace('/js/autolex-portal-3.js', '/css/autolex-search-3.css').split('?')[0];
    if (href === portalScript.split('?')[0]) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = `${href}?ver=${encodeURIComponent(window.AutolexPortal?.version || '3.3.0')}`;
    link.dataset.autolexSearchCss = 'true';
    document.head.appendChild(link);
  };

  const initGlobalSearch = () => {
    if (!window.AutolexPortal || !window.fetch) return;
    const inputs = [...document.querySelectorAll('.alx3-hero-search input[name="q"], [data-autolex-filter-form] input[name="q"]')];
    inputs.forEach((input, index) => enhanceSearch(input, index));
    renderNavigation();
  };

  const enhanceSearch = (input, index) => {
    const form = input.form;
    if (!form || input.dataset.autolexCombobox === 'true') return;
    input.dataset.autolexCombobox = 'true';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-haspopup', 'listbox');
    input.setAttribute('autocomplete', 'off');

    const shell = document.createElement('div');
    shell.className = 'alx3-search-shell';
    input.parentNode.insertBefore(shell, input);
    shell.appendChild(input);

    const listId = `alx3-global-search-${index + 1}`;
    const list = document.createElement('div');
    list.id = listId;
    list.className = 'alx3-search-results';
    list.setAttribute('role', 'listbox');
    list.hidden = true;
    shell.appendChild(list);
    input.setAttribute('aria-controls', listId);

    let controller;
    let timer;
    let activeIndex = -1;
    let items = [];

    const close = () => {
      list.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      activeIndex = -1;
    };

    const setActive = (next) => {
      if (!items.length) return;
      activeIndex = (next + items.length) % items.length;
      items.forEach((node, itemIndex) => node.classList.toggle('is-active', itemIndex === activeIndex));
      const active = items[activeIndex];
      input.setAttribute('aria-activedescendant', active.id);
      active.scrollIntoView({ block: 'nearest' });
    };

    const renderMessage = (message, state = '') => {
      list.className = `alx3-search-results${state ? ` is-${state}` : ''}`;
      list.innerHTML = `<div class="alx3-search-message" role="status">${html(message)}</div>`;
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      items = [];
      activeIndex = -1;
    };

    const renderItems = (vehicles, query) => {
      list.className = 'alx3-search-results';
      if (!vehicles.length) {
        renderMessage('Nincs közvetlen találat. Enterrel megnyithatod a teljes katalóguskeresést.', 'empty');
        return;
      }
      const allResultsUrl = new URL(AutolexPortal.catalogUrl);
      allResultsUrl.searchParams.set('q', query);
      list.innerHTML = vehicles.map((vehicle, itemIndex) => {
        const title = [vehicle.make, vehicle.model, vehicle.generation].filter(Boolean).join(' ');
        const meta = [vehicle.engine_code || vehicle.engine, vehicle.years].filter(Boolean).join(' • ');
        return `<a id="${listId}-option-${itemIndex}" role="option" href="${attr(vehicle.url)}" data-search-option>
          <span class="alx3-search-mark">${html(initials(vehicle.make))}</span>
          <span><strong>${html(title)}</strong><small>${html(meta || 'Járműadatlap')}</small></span>
          <b>${html(vehicle.data_grade || 'C')}</b>
        </a>`;
      }).join('') + `<a class="alx3-search-all" href="${attr(allResultsUrl.toString())}">Minden találat megnyitása →</a>`;
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      items = [...list.querySelectorAll('[data-search-option]')];
      activeIndex = -1;
    };

    const search = async () => {
      const query = input.value.trim();
      if (query.length < 2) {
        close();
        return;
      }
      controller?.abort();
      controller = new AbortController();
      renderMessage('Találatok keresése…', 'loading');
      try {
        const url = new URL(AutolexPortal.vehiclesEndpoint);
        url.searchParams.set('q', query);
        url.searchParams.set('limit', '7');
        url.searchParams.set('page', '1');
        url.searchParams.set('sort', 'data_desc');
        const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
        if (!response.ok) throw new Error('request_failed');
        const data = await response.json();
        renderItems(Array.isArray(data.items) ? data.items : [], query);
      } catch (error) {
        if (error.name === 'AbortError') return;
        renderMessage('Az élő keresés átmenetileg nem érhető el. Enterrel a szerveroldali keresés továbbra is használható.', 'error');
      }
    };

    input.addEventListener('input', () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(search, 260);
    });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowDown' && !list.hidden) {
        event.preventDefault();
        setActive(activeIndex + 1);
      } else if (event.key === 'ArrowUp' && !list.hidden) {
        event.preventDefault();
        setActive(activeIndex - 1);
      } else if (event.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
        event.preventDefault();
        window.location.assign(items[activeIndex].href);
      } else if (event.key === 'Escape') {
        close();
      }
    });
    input.addEventListener('focus', () => {
      if (list.innerHTML && input.value.trim().length >= 2) {
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
      }
    });
    list.addEventListener('mousemove', (event) => {
      const option = event.target.closest('[data-search-option]');
      const optionIndex = items.indexOf(option);
      if (optionIndex >= 0) setActive(optionIndex);
    });
    document.addEventListener('pointerdown', (event) => {
      if (!shell.contains(event.target)) close();
    });
  };

  const renderNavigation = () => {
    const catalog = document.querySelector('.alx3-catalog');
    if (!catalog || catalog.querySelector('[data-autolex-breadcrumbs]')) return;
    const params = new URLSearchParams(window.location.search);
    const crumbs = [{ label: 'Főoldal', url: '/' }, { label: 'Autókatalógus', url: AutolexPortal.catalogUrl }];
    ['make', 'model', 'generation'].forEach((key) => {
      const value = params.get(key);
      if (!value) return;
      const url = new URL(AutolexPortal.catalogUrl);
      ['make', 'model', 'generation'].forEach((allowed) => {
        const part = params.get(allowed);
        if (part) url.searchParams.set(allowed, part);
        if (allowed === key) return;
      });
      crumbs.push({ label: value, url: url.toString() });
    });
    const nav = document.createElement('nav');
    nav.className = 'alx3-breadcrumbs';
    nav.dataset.autolexBreadcrumbs = 'true';
    nav.setAttribute('aria-label', 'Morzsamenü');
    nav.innerHTML = crumbs.map((crumb, index) => index === crumbs.length - 1
      ? `<span aria-current="page">${html(crumb.label)}</span>`
      : `<a href="${attr(crumb.url)}">${html(crumb.label)}</a>`).join('<b aria-hidden="true">/</b>');
    catalog.prepend(nav);

    const form = document.querySelector('[data-autolex-filter-form]');
    if (!form) return;
    const quick = document.createElement('div');
    quick.className = 'alx3-quick-routes';
    quick.setAttribute('aria-label', 'Gyors keresési útvonalak');
    const links = [
      ['Márka kiválasztása', form.querySelector('[name="make"]')?.value],
      ['Modell pontosítása', form.querySelector('[name="model"]')?.value],
      ['Motorkód keresése', form.querySelector('[name="engine_code"]')?.value],
    ];
    quick.innerHTML = links.map(([label, value]) => `<button type="button" data-focus-field="${attr(label)}">${value ? `${html(label)}: ${html(value)}` : html(label)}</button>`).join('');
    form.prepend(quick);
    quick.addEventListener('click', (event) => {
      const button = event.target.closest('[data-focus-field]');
      if (!button) return;
      const map = { 'Márka kiválasztása': 'make', 'Modell pontosítása': 'model', 'Motorkód keresése': 'engine_code' };
      const field = form.elements[map[button.dataset.focusField]];
      field?.focus();
      field?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  };

  const initCatalogue = () => {
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
    const searchInput = form.querySelector('input[name="q"]');

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
      if (event.submitter?.closest('.alx3-hero-search')) return;
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
    searchInput?.addEventListener('input', () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => load(1), 520);
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
  };

  ready(() => {
    ensureSearchStyles();
    initGlobalSearch();
    initCatalogue();
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
