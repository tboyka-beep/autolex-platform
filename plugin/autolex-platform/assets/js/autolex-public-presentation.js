(() => {
  'use strict';

  const exactLabels = new Map([
    ['PRIMARY', 'ELSŐDLEGES'],
    ['SUPPORT', 'MEGERŐSÍTŐ'],
    ['LIVE QUERY', 'ÉLŐ LEKÉRDEZÉS'],
    ['FRISSAUTO SEARCH', 'FRISSAUTO KERESÉS'],
  ]);

  const fuelLabels = new Map([
    ['petrol', 'Benzin'],
    ['gasoline', 'Benzin'],
    ['benzin', 'Benzin'],
    ['diesel', 'Dízel'],
    ['gasoil', 'Dízel'],
    ['dízel', 'Dízel'],
    ['dizel', 'Dízel'],
    ['electric', 'Elektromos'],
    ['electricity', 'Elektromos'],
    ['electric vehicle', 'Elektromos'],
    ['bev', 'Elektromos'],
    ['petrol/electric', 'Benzin / elektromos'],
    ['gasoline/electric', 'Benzin / elektromos'],
    ['petrol electric', 'Benzin / elektromos'],
    ['gasoline electric', 'Benzin / elektromos'],
    ['diesel/electric', 'Dízel / elektromos'],
    ['diesel electric', 'Dízel / elektromos'],
    ['hybrid', 'Hibrid'],
    ['hybrid electric', 'Hibrid'],
    ['hev', 'Hibrid'],
    ['plug-in hybrid', 'Plug-in hibrid'],
    ['plug in hybrid', 'Plug-in hibrid'],
    ['phev', 'Plug-in hibrid'],
    ['mild hybrid', 'Lágy hibrid'],
    ['mhev', 'Lágy hibrid'],
    ['lpg', 'LPG (autógáz)'],
    ['liquefied petroleum gas', 'LPG (autógáz)'],
    ['cng', 'CNG (sűrített földgáz)'],
    ['compressed natural gas', 'CNG (sűrített földgáz)'],
    ['ng', 'Földgáz (NG)'],
    ['natural gas', 'Földgáz (NG)'],
    ['lng', 'LNG (cseppfolyósított földgáz)'],
    ['hydrogen', 'Hidrogén'],
    ['h2', 'Hidrogén'],
    ['ethanol', 'Etanol'],
    ['e85', 'E85 (etanol)'],
    ['biodiesel', 'Biodízel'],
    ['petrol/lpg', 'Benzin / LPG (autógáz)'],
    ['gasoline/lpg', 'Benzin / LPG (autógáz)'],
    ['petrol/cng', 'Benzin / CNG (sűrített földgáz)'],
    ['gasoline/cng', 'Benzin / CNG (sűrített földgáz)'],
    ['other', 'Egyéb'],
    ['unknown', 'Ismeretlen'],
    ['not available', 'Ismeretlen'],
    ['n/a', 'Ismeretlen'],
  ]);

  const normalizeKey = (value) => String(value || '')
    .trim()
    .toLocaleLowerCase('hu-HU')
    .replace(/[–—_]/g, ' ')
    .replace(/\s*\/\s*/g, '/')
    .replace(/\s+/g, ' ');

  const fuelLabel = (value) => {
    const raw = String(value || '').trim();
    if (!raw) return raw;
    const key = normalizeKey(raw);
    if (fuelLabels.has(key)) return fuelLabels.get(key);

    const petrolDetail = raw.match(/^(petrol|gasoline)\s*(\(.+\)|[a-z0-9-]+)$/i);
    if (petrolDetail) return `Benzin ${petrolDetail[2].trim()}`;
    const dieselDetail = raw.match(/^diesel\s*(\(.+\)|[a-z0-9-]+)$/i);
    if (dieselDetail) return `Dízel ${dieselDetail[1].trim()}`;
    return raw;
  };

  const localizeText = (text) => {
    const raw = String(text || '');
    const trimmed = raw.trim();
    if (!trimmed) return raw;
    const leading = raw.match(/^\s*/)?.[0] || '';
    const trailing = raw.match(/\s*$/)?.[0] || '';

    if (exactLabels.has(trimmed)) return `${leading}${exactLabels.get(trimmed)}${trailing}`;

    const fuel = fuelLabel(trimmed);
    if (fuel !== trimmed) return `${leading}${fuel}${trailing}`;

    const counted = trimmed.match(/^(.+?)\s+\(([0-9 .,.]+)\)$/);
    if (counted) {
      const label = fuelLabel(counted[1]);
      if (label !== counted[1]) return `${leading}${label} (${counted[2]})${trailing}`;
    }

    if (trimmed.includes('•')) {
      const parts = trimmed.split('•').map((part) => {
        const value = part.trim();
        return value ? fuelLabel(value) : '';
      });
      const rebuilt = parts.join(' • ');
      if (rebuilt !== trimmed) return `${leading}${rebuilt}${trailing}`;
    }
    return raw;
  };

  const shouldSkip = (node) => {
    const parent = node.parentElement;
    return !parent || Boolean(parent.closest('script, style, pre, code, textarea, [contenteditable="true"]'));
  };

  const localizeTree = (root) => {
    if (!root) return;
    if (root.nodeType === Node.TEXT_NODE) {
      if (shouldSkip(root)) return;
      const localized = localizeText(root.nodeValue);
      if (localized !== root.nodeValue) root.nodeValue = localized;
      return;
    }
    if (![Node.ELEMENT_NODE, Node.DOCUMENT_NODE, Node.DOCUMENT_FRAGMENT_NODE].includes(root.nodeType)) return;
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach((node) => {
      if (shouldSkip(node)) return;
      const localized = localizeText(node.nodeValue);
      if (localized !== node.nodeValue) node.nodeValue = localized;
    });
  };

  const start = () => {
    localizeTree(document.body);
    let scheduled = false;
    const pending = new Set();
    const flush = () => {
      scheduled = false;
      pending.forEach(localizeTree);
      pending.clear();
    };
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => pending.add(node)));
      if (!scheduled && pending.size) {
        scheduled = true;
        queueMicrotask(flush);
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
  else start();
})();
