(() => {
  'use strict';

  const toggle = document.querySelector('.alx-menu-toggle');
  const menu = document.getElementById('alx-mobile-menu');
  if (!toggle || !menu) return;

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
})();
