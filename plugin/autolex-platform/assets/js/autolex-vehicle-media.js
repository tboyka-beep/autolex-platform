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

    function extractIdentity(card) {
        if (!card) {
            return null;
        }

        var dataMake = normalize(card.getAttribute('data-alx-make'));
        var dataModel = normalize(card.getAttribute('data-alx-model'));
        var dataGeneration = normalize(card.getAttribute('data-alx-generation'));
        if (dataMake && dataModel) {
            return {
                make: dataMake,
                model: dataModel,
                generation: dataGeneration,
                label: normalize([dataModel, dataGeneration].filter(Boolean).join(' ')),
                structured: true
            };
        }

        if (card.matches('.alx3-vehicle-card')) {
            var makeNode = card.querySelector('header .alx3-brand-mark + div > span');
            var labelNode = card.querySelector('header .alx3-brand-mark + div h2');
            if (!makeNode || !labelNode) {
                return null;
            }
            return {
                make: normalize(makeNode.textContent),
                model: '',
                generation: '',
                label: normalize(labelNode.textContent),
                structured: true
            };
        }

        if (card.matches('.alxp-vehicle-card')) {
            var make = card.querySelector('span');
            var label = card.querySelector('h3');
            if (!make || !label) {
                return null;
            }
            return {
                make: normalize(make.textContent),
                model: '',
                generation: '',
                label: normalize(label.textContent),
                structured: true
            };
        }

        return null;
    }

    function matchesMedia(identity, media) {
        if (!identity || !identity.structured || !media) {
            return false;
        }

        var make = normalize(media.make);
        var model = normalize(media.model);
        var generation = normalize(media.generation);
        if (!make || !model || identity.make !== make) {
            return false;
        }

        if (identity.model) {
            if (identity.model !== model) {
                return false;
            }
            if (identity.generation && generation && identity.generation !== generation) {
                return false;
            }
            if (identity.generation && !generation) {
                return false;
            }
            return true;
        }

        if (!identity.label) {
            return false;
        }

        if (identity.label === model) {
            return true;
        }

        if (generation) {
            var exactGenerationPrefix = model + ' ' + generation;
            return identity.label === exactGenerationPrefix || identity.label.indexOf(exactGenerationPrefix + ' ') === 0;
        }

        return identity.label.indexOf(model + ' ') === 0;
    }

    function findMatch(identity) {
        var keys = Object.keys(mediaMap);
        for (var i = 0; i < keys.length; i += 1) {
            var media = mediaMap[keys[i]] || {};
            if (matchesMedia(identity, media)) {
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
        var identity = extractIdentity(card);
        var media = findMatch(identity);
        if (!media || !media.image || !media.source || !media.credit) {
            return;
        }

        var existing = card.querySelector('img[data-alx-vehicle-image="1"]');
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
        image.dataset.alxVehicleImage = '1';
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
            '[data-alx-make][data-alx-model]'
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
                        if (node.matches && node.matches('.alx3-vehicle-card,.alxp-vehicle-card,[data-alx-make][data-alx-model]')) {
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
