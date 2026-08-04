(() => {
  'use strict';

  const toggle = document.querySelector('.alx-menu-toggle');
  const menu = document.getElementById('alx-mobile-menu');

  if (toggle && menu) {
    const closeMenu = () => {
      toggle.setAttribute('aria-expanded', 'false');
      menu.hidden = true;
    };

    toggle.addEventListener('click', () => {
      const open = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', String(!open));
      menu.hidden = open;
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !menu.hidden) {
        closeMenu();
        toggle.focus();
      }
    });

    window.matchMedia('(min-width: 961px)').addEventListener('change', (event) => {
      if (event.matches) closeMenu();
    });
  }

  const searchForm = document.querySelector('[data-alx-search-form]');
  if (!searchForm) return;

  const tabs = Array.from(searchForm.querySelectorAll('[role="tab"][data-search-mode]'));
  const panels = Array.from(searchForm.querySelectorAll('[role="tabpanel"][data-search-panel]'));
  const searchType = searchForm.querySelector('[data-alx-search-type]');

  if (!tabs.length || !panels.length || !searchType) return;

  const activateMode = (mode, focusTab = false) => {
    tabs.forEach((tab) => {
      const active = tab.dataset.searchMode === mode;
      tab.setAttribute('aria-selected', String(active));
      tab.setAttribute('tabindex', active ? '0' : '-1');
      if (active && focusTab) tab.focus();
    });

    panels.forEach((panel) => {
      const active = panel.dataset.searchPanel === mode;
      panel.hidden = !active;
      panel.querySelectorAll('input, select, textarea, button').forEach((control) => {
        control.disabled = !active;
      });
    });

    searchType.value = mode;
  };

  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => activateMode(tab.dataset.searchMode));
    tab.addEventListener('keydown', (event) => {
      let nextIndex = null;
      if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
      if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
      if (event.key === 'Home') nextIndex = 0;
      if (event.key === 'End') nextIndex = tabs.length - 1;
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        activateMode(tab.dataset.searchMode);
        return;
      }
      if (nextIndex !== null) {
        event.preventDefault();
        activateMode(tabs[nextIndex].dataset.searchMode, true);
      }
    });
  });

  activateMode(searchType.value || 'vehicle');
})();
