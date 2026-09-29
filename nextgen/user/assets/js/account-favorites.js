/**
 * Account kedvencek: szívecske toggle (levétel / visszarakás) frissítésig a kártya marad.
 */
(function () {
    'use strict';

    var ajaxUrl = document.body && document.body.getAttribute('data-favorites-ajax');
    if (!ajaxUrl) {
        return;
    }

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function setActive(card, active) {
        var btn = qs('[data-user-fav-heart]', card);
        card.setAttribute('data-active', active ? '1' : '0');
        card.classList.toggle('is-inactive', !active);
        if (!btn) {
            return;
        }
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        var label = card.getAttribute('data-item-label') || '';
        var icon = btn.querySelector('.user-fav-card__heart-icon');
        if (active) {
            btn.setAttribute('aria-label', label ? ('Kedvenc törlése: ' + label) : 'Kedvenc törlése');
            btn.setAttribute('title', 'Levétel a kedvencekből');
            if (icon) {
                icon.textContent = '♥';
            }
        } else {
            btn.setAttribute('aria-label', label ? ('Kedvencnek jelölés: ' + label) : 'Kedvencnek jelölés');
            btn.setAttribute('title', 'Vissza a kedvencekhez');
            if (icon) {
                icon.textContent = '♡';
            }
        }
    }

    function updateActiveTotals() {
        var activeCards = document.querySelectorAll('[data-user-fav-card][data-active="1"]');
        var totalEl = qs('[data-user-favorites-total]');
        if (totalEl) {
            totalEl.textContent = String(activeCards.length);
        }

        document.querySelectorAll('[data-fav-group]').forEach(function (group) {
            var count = group.querySelectorAll('[data-user-fav-card][data-active="1"]').length;
            var countEl = qs('[data-fav-group-count]', group);
            if (countEl) {
                countEl.textContent = String(count);
            }
        });
    }

    document.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('[data-user-fav-heart]') : null;
        if (!btn) {
            return;
        }
        ev.preventDefault();

        var card = btn.closest('[data-user-fav-card]');
        if (!card || card.classList.contains('is-busy')) {
            return;
        }

        var type = card.getAttribute('data-entity-type') || '';
        var id = card.getAttribute('data-entity-id') || '';
        if (!type || !id) {
            return;
        }

        var wasActive = card.getAttribute('data-active') !== '0';
        card.classList.add('is-busy');
        btn.disabled = true;

        var body = new FormData();
        body.append('action', 'toggle');
        body.append('entity_type', type);
        body.append('entity_id', id);
        body.append('lang', 'hu');

        fetch(ajaxUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { okHttp: res.ok, data: data };
                });
            })
            .then(function (result) {
                var data = result.data || {};
                if (!result.okHttp || !data.ok) {
                    throw new Error((data && data.error) || 'Hiba');
                }
                setActive(card, !!data.active);
                var countEl = qs('[data-user-fav-count]', card);
                if (countEl && typeof data.count === 'number') {
                    countEl.textContent = String(data.count);
                }
                updateActiveTotals();
            })
            .catch(function () {
                // Hiba esetén visszaállítjuk a korábbi állapotot vizuálisan
                setActive(card, wasActive);
            })
            .finally(function () {
                card.classList.remove('is-busy');
                btn.disabled = false;
            });
    });
})();
