/**
 * Account kedvencek: AJAX levétel, animáció, összesítő frissítés.
 * Form POST fallback JS nélkül.
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

    function updateTotals() {
        var cards = document.querySelectorAll('[data-user-fav-card]');
        var totalEl = qs('[data-user-favorites-total]');
        if (totalEl) {
            totalEl.textContent = String(cards.length);
        }

        document.querySelectorAll('[data-fav-group]').forEach(function (group) {
            var count = group.querySelectorAll('[data-user-fav-card]').length;
            var countEl = qs('[data-fav-group-count]', group);
            if (countEl) {
                countEl.textContent = String(count);
            }
            if (count === 0) {
                group.classList.add('is-empty');
                group.setAttribute('hidden', 'hidden');
            }
        });
    }

    function showEmptyStateIfNeeded() {
        if (document.querySelectorAll('[data-user-fav-card]').length > 0) {
            return;
        }
        var section = qs('.user-favorites');
        if (!section || qs('.user-favorites-empty-state', section)) {
            return;
        }
        var home = section.getAttribute('data-empty-home') || '/';
        var wrap = document.createElement('div');
        wrap.className = 'user-favorites-empty-state';
        wrap.innerHTML =
            '<span class="user-favorites-empty-state__heart" aria-hidden="true">♡</span>' +
            '<p class="user-favorites-empty-state__title">Még nincs kedvenced</p>' +
            '<p class="user-favorites-empty-state__text">Eseményeken, szervezőknél, helyszíneken és DJ-knél a ♥ gombbal mentheted ide a kedvenceidet.</p>' +
            '<a class="user-favorites-empty-state__cta" href="' + home.replace(/"/g, '&quot;') + '">Naptár böngészése</a>';
        section.appendChild(wrap);
    }

    function removeCard(card) {
        card.classList.add('is-removing');
        window.setTimeout(function () {
            var group = card.closest('[data-fav-group]');
            card.remove();
            updateTotals();
            if (group && group.querySelectorAll('[data-user-fav-card]').length === 0) {
                group.setAttribute('hidden', 'hidden');
            }
            showEmptyStateIfNeeded();
        }, 280);
    }

    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form || !form.matches || !form.matches('[data-user-fav-remove]')) {
            return;
        }
        ev.preventDefault();

        var card = form.closest('[data-user-fav-card]');
        if (!card || card.classList.contains('is-busy')) {
            return;
        }

        var type = card.getAttribute('data-entity-type') || '';
        var id = card.getAttribute('data-entity-id') || '';
        if (!type || !id) {
            form.submit();
            return;
        }

        card.classList.add('is-busy');
        var btn = qs('.user-fav-card__unheart', form);
        if (btn) {
            btn.disabled = true;
        }

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
                // toggle: ha már nem aktív, sikeresen levéve
                if (data.active) {
                    // váratlanul újra aktív lett – maradjon a kártya
                    card.classList.remove('is-busy');
                    if (btn) {
                        btn.disabled = false;
                    }
                    return;
                }
                var countEl = qs('[data-user-fav-count]', card);
                if (countEl && typeof data.count === 'number') {
                    countEl.textContent = String(data.count);
                }
                removeCard(card);
            })
            .catch(function () {
                // AJAX hiba → klasszikus POST
                form.submit();
            });
    });
})();
