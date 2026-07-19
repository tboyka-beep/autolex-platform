document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('alxbc-search-input');
  const flyout = document.getElementById('alxbc-search-flyout');
  let timer;

  if (input && flyout && window.AutolexCatalog) {
    input.addEventListener('input', () => {
      window.clearTimeout(timer);
      const query = input.value.trim();
      if (query.length < 2) {
        flyout.hidden = true;
        flyout.style.display = 'none';
        flyout.innerHTML = '';
        return;
      }
      timer = window.setTimeout(async () => {
        try {
          const url = new URL(AutolexCatalog.endpoint);
          url.searchParams.set('q', query);
          url.searchParams.set('limit', '8');
          const response = await fetch(url, { headers: { Accept: 'application/json' } });
          if (!response.ok) throw new Error('search_failed');
          const data = await response.json();
          flyout.innerHTML = data.items.length
            ? data.items.map((item) => `<a href="${escapeAttribute(item.url)}"><strong>${escapeHtml([item.make, item.model, item.generation].filter(Boolean).join(' '))}</strong><br><small>${escapeHtml([item.engine, item.years].filter(Boolean).join(' • '))}</small></a>`).join('')
            : `<a href="${escapeAttribute(`${AutolexCatalog.carsUrl}?kereses=${encodeURIComponent(query)}`)}">Nincs közvetlen találat – összes keresése</a>`;
          flyout.hidden = false;
          flyout.style.display = 'block';
        } catch (error) {
          flyout.innerHTML = '<span class="alxp-search-error">A keresés átmenetileg nem elérhető.</span>';
          flyout.hidden = false;
          flyout.style.display = 'block';
        }
      }, 220);
    });
  }

  const slider = document.querySelector('[data-alxbc-slider]');
  if (slider) {
    const slides = [...slider.querySelectorAll('.alxbc-slide')];
    const dots = [...slider.querySelectorAll('.alxbc-slider-dots button')];
    let current = 0;
    let interval;
    const show = (index) => {
      current = (index + slides.length) % slides.length;
      slides.forEach((slide, i) => slide.classList.toggle('is-active', i === current));
      dots.forEach((dot, i) => dot.classList.toggle('is-active', i === current));
    };
    const start = () => {
      window.clearInterval(interval);
      interval = window.setInterval(() => show(current + 1), 6500);
    };
    dots.forEach((dot, i) => dot.addEventListener('click', () => { show(i); start(); }));
    if (slides.length) { show(0); start(); }
  }
});

function escapeHtml(value) {
  const node = document.createElement('div');
  node.textContent = String(value ?? '');
  return node.innerHTML;
}

function escapeAttribute(value) {
  return escapeHtml(value).replace(/`/g, '&#96;');
}
