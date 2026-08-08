(function () {
    'use strict';

    var config = window.AutolexVehicleMedia || {};
    var mediaMap = config.map || {};

    function normalize(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, ' ')
            .trim()
            .replace(/\s+/g, ' ');
    }

    function findMatch(text) {
        var normalizedText = ' ' + normalize(text) + ' ';
        var keys = Object.keys(mediaMap);
        for (var i = 0; i < keys.length; i += 1) {
            var media = mediaMap[keys[i]] || {};
            var make = normalize(media.make);
            var model = normalize(media.model);
            if (!make || !model) {
                continue;
            }
            if (normalizedText.indexOf(' ' + make + ' ') !== -1 && normalizedText.indexOf(' ' + model + ' ') !== -1) {
                return media;
            }
        }
        return null;
    }

    function ensureMedia(card) {
        if (!card || card.dataset.alxVehicleMediaResolved === '1') {
            return;
        }

        card.dataset.alxVehicleMediaResolved = '1';
        var media = findMatch(card.textContent || '');
        if (!media || !media.image || !media.source || !media.credit) {
            return;
        }

        var existing = card.querySelector('img');
        if (existing) {
            existing.src = media.image;
            existing.removeAttribute('srcset');
            existing.alt = media.alt || ((media.make || '') + ' ' + (media.model || '')).trim();
            existing.dataset.alxVerifiedVehicleMedia = '1';
            return;
        }

        var figure = document.createElement('figure');
        figure.className = 'alx-verified-vehicle-media';

        var image = document.createElement('img');
        image.src = media.image;
        image.alt = media.alt || ((media.make || '') + ' ' + (media.model || '')).trim();
        image.loading = 'lazy';
        image.decoding = 'async';
        image.dataset.alxVerifiedVehicleMedia = '1';
        figure.appendChild(image);

        var credit = document.createElement('a');
        credit.href = media.source;
        credit.target = '_blank';
        credit.rel = 'noopener noreferrer';
        credit.className = 'alx-verified-vehicle-media__credit';
        credit.textContent = media.credit;
        figure.appendChild(credit);

        card.insertBefore(figure, card.firstChild);
    }

    function scan(root) {
        var scope = root || document;
        var selectors = [
            '.alx3-vehicle-card',
            '.alxp-vehicle-card',
            '.alx-hierarchy-plugin-output article',
            '.alx-hierarchy-plugin-output li',
            '.alx-hierarchy-plugin-output a'
        ];
        var cards = scope.querySelectorAll(selectors.join(','));
        Array.prototype.forEach.call(cards, ensureMedia);
    }

    function boot() {
        scan(document);
        if (!window.MutationObserver || !document.body) {
            return;
        }
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                Array.prototype.forEach.call(mutation.addedNodes || [], function (node) {
                    if (node && node.nodeType === 1) {
                        if (node.matches && node.matches('.alx3-vehicle-card,.alxp-vehicle-card,.alx-hierarchy-plugin-output article,.alx-hierarchy-plugin-output li,.alx-hierarchy-plugin-output a')) {
                            ensureMedia(node);
                        }
                        scan(node);
                    }
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
