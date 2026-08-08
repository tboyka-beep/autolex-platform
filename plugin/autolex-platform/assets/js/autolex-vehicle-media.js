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

    function findNamedVehicle(label) {
        var normalizedLabel = normalize(label);
        if (!normalizedLabel) {
            return null;
        }
        var keys = Object.keys(mediaMap);
        for (var i = 0; i < keys.length; i += 1) {
            var media = mediaMap[keys[i]] || {};
            var name = normalize((media.make || '') + ' ' + (media.model || ''));
            if (name && normalizedLabel === name) {
                return media;
            }
        }
        return null;
    }

    function applyMediaToImage(image, media) {
        if (!image || !media || !media.image || !media.source || !media.credit) {
            return false;
        }
        image.src = media.image;
        image.removeAttribute('srcset');
        image.alt = media.alt || ((media.make || '') + ' ' + (media.model || '')).trim();
        image.dataset.alxVehicleImage = '1';
        image.dataset.alxVerifiedVehicleMedia = '1';
        return true;
    }

    function setFailClosedVisibility(element, failClosed) {
        if (!element) {
            return;
        }
        if (failClosed) {
            element.hidden = true;
            element.style.display = 'none';
            element.dataset.alxMediaFailClosed = '1';
            return;
        }
        element.hidden = false;
        element.style.removeProperty('display');
        element.dataset.alxMediaFailClosed = '0';
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
            applyMediaToImage(existing, media);
            return;
        }

        var figure = document.createElement('figure');
        figure.className = 'alx-verified-vehicle-media';

        var image = document.createElement('img');
        image.loading = 'lazy';
        image.decoding = 'async';
        applyMediaToImage(image, media);
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

    function syncFeaturedVehicle() {
        var card = document.querySelector('.alx-featured-vehicle-card');
        if (!card) {
            return;
        }
        var nameNode = card.querySelector('.alx-featured-data h2');
        var mediaBox = card.querySelector('.alx-featured-media');
        if (!nameNode || !mediaBox) {
            return;
        }

        var identityKey = normalize(nameNode.textContent);
        if (!identityKey || card.dataset.alxNamedMediaKey === identityKey) {
            return;
        }
        card.dataset.alxNamedMediaKey = identityKey;

        var media = findNamedVehicle(nameNode.textContent);
        var image = mediaBox.querySelector('img');
        if (!media || !image || !applyMediaToImage(image, media)) {
            setFailClosedVisibility(mediaBox, true);
            return;
        }

        setFailClosedVisibility(mediaBox, false);
        var credit = mediaBox.querySelector('.alx-stock-credit');
        if (credit) {
            credit.href = media.source;
            if (credit.textContent !== media.credit) {
                credit.textContent = media.credit;
            }
        }
    }

    function syncComparisonPreview() {
        var card = document.querySelector('.alx-compare-card');
        if (!card) {
            return;
        }
        var names = card.querySelectorAll('.alx-compare-data .alx-compare-names strong');
        var photoRow = card.querySelector('.alx-compare-vehicles--photos');
        if (!photoRow || names.length !== 2) {
            return;
        }

        var identityKey = normalize(names[0].textContent) + '|' + normalize(names[1].textContent);
        if (card.dataset.alxNamedMediaKey === identityKey) {
            return;
        }
        card.dataset.alxNamedMediaKey = identityKey;

        var left = findNamedVehicle(names[0].textContent);
        var right = findNamedVehicle(names[1].textContent);
        var images = photoRow.querySelectorAll('img');
        if (!left || !right || images.length !== 2) {
            setFailClosedVisibility(photoRow, true);
            return;
        }

        if (!applyMediaToImage(images[0], left) || !applyMediaToImage(images[1], right)) {
            setFailClosedVisibility(photoRow, true);
            return;
        }

        setFailClosedVisibility(photoRow, false);
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

    function syncNamedHomepageMedia() {
        syncFeaturedVehicle();
        syncComparisonPreview();
    }

    function boot() {
        scan(document);
        syncNamedHomepageMedia();
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
            syncNamedHomepageMedia();
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
