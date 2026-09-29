(function () {
    'use strict';

    var GUEST_KEY = 'latinfo_fav_guest';
    var ajaxUrl = document.body && document.body.getAttribute('data-favorites-ajax');
    if (!ajaxUrl) {
        return;
    }

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function guestOk() {
        try {
            return localStorage.getItem(GUEST_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function setGuestOk() {
        try {
            localStorage.setItem(GUEST_KEY, '1');
        } catch (e) {
            /* ignore */
        }
    }

    function postForm(data) {
        return fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams(data).toString(),
        }).then(function (r) {
            return r.json().then(function (j) {
                return { ok: r.ok, json: j };
            });
        });
    }

function updateWidget(root, active, count) {
        root.setAttribute('data-active', active ? '1' : '0');
        root.setAttribute('data-count', String(count));
        var btn = qs('[data-public-favorite-btn]', root);
        var countEl = qs('[data-public-favorite-count]', root);
        var lang = root.getAttribute('data-lang') || 'hu';
        if (btn) {
            btn.classList.toggle('is-active', !!active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            btn.setAttribute(
                'aria-label',
                active
                    ? (lang === 'en' ? 'Remove from favorites' : 'Kedvenc törlése')
                    : (lang === 'en' ? 'Add to favorites' : 'Kedvencnek jelölés')
            );
        }
        if (countEl) {
            countEl.textContent = String(count);
        }
    }

    function markLoggedIn(root) {
        if (root) {
            root.setAttribute('data-logged-in', '1');
        }
        qsa('[data-public-favorite]').forEach(function (el) {
            el.setAttribute('data-logged-in', '1');
        });
        try {
            localStorage.removeItem(GUEST_KEY);
        } catch (e) {
            /* ignore */
        }
    }

    function syncPickerActive(root, type, id, active) {
        var raw = root.getAttribute('data-event-picker');
        if (!raw) {
            return;
        }
        try {
            var picker = JSON.parse(raw);
            (picker.items || []).forEach(function (item) {
                if (item && item.type === type && String(item.id) === String(id)) {
                    item.active = !!active;
                }
            });
            root.setAttribute('data-event-picker', JSON.stringify(picker));
        } catch (e) {
            /* ignore */
        }
    }

    function syncAllOfType(type, id, active, count) {
        qsa('[data-public-favorite]').forEach(function (root) {
            if (root.getAttribute('data-entity-type') === type && String(root.getAttribute('data-entity-id')) === String(id)) {
                updateWidget(root, active, count);
            }
        });
    }

    function authDialog() {
        return qs('[data-public-favorite-auth-dialog]');
    }

    function eventDialog() {
        return qs('[data-public-favorite-event-dialog]');
    }

    function openDialog(dlg) {
        if (!dlg || typeof dlg.showModal !== 'function') {
            return Promise.resolve(null);
        }
        return new Promise(function (resolve) {
            function onClose() {
                dlg.removeEventListener('close', onClose);
                dlg.removeEventListener('click', onBackdrop);
                resolve(dlg.returnValue || '');
            }
            function onBackdrop(ev) {
                if (ev.target === dlg && typeof dlg.close === 'function') {
                    dlg.close('cancel');
                }
            }
            dlg.addEventListener('close', onClose);
            dlg.addEventListener('click', onBackdrop);
            dlg.showModal();
        });
    }

    function buildEventChoices(listEl, picker, lang) {
        listEl.innerHTML = '';
        var items = (picker && picker.items) || [];
        var kindLabels = {
            event: lang === 'en' ? 'Event' : 'Esemény',
            organizer: lang === 'en' ? 'Organizer' : 'Szervező',
            venue: lang === 'en' ? 'Venue' : 'Helyszín',
            dj: 'DJ',
            zenekar: lang === 'en' ? 'Band' : 'Zenekar',
        };
        var order = ['event', 'organizer', 'venue', 'dj', 'zenekar'];
        var sorted = items.slice().sort(function (a, b) {
            var ai = order.indexOf(a.type);
            var bi = order.indexOf(b.type);
            if (ai < 0) ai = 99;
            if (bi < 0) bi = 99;
            if (ai !== bi) return ai - bi;
            return String(a.label || '').localeCompare(String(b.label || ''), lang === 'en' ? 'en' : 'hu');
        });
        var ul = document.createElement('ul');
        ul.className = 'public-favorite-dialog__choices';
        sorted.forEach(function (item) {
            if (!item || !item.type || !item.id) {
                return;
            }
            var li = document.createElement('li');
            li.className = 'public-favorite-dialog__choice';
            var label = document.createElement('label');
            label.className = 'public-favorite-dialog__choice-label';
            var kind = document.createElement('span');
            kind.className = 'public-favorite-dialog__choice-kind';
            kind.textContent = item.groupLabel || kindLabels[item.type] || item.type;
            var name = document.createElement('span');
            name.className = 'public-favorite-dialog__choice-name';
            name.textContent = item.label || ('#' + item.id);
            name.title = item.label || '';
            var heart = document.createElement('span');
            heart.className = 'public-favorite-dialog__choice-heart';
            heart.setAttribute('aria-hidden', 'true');
            heart.textContent = '♥';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'fav_pick[]';
            cb.value = item.type + ':' + item.id;
            cb.checked = !!item.active;
            cb.dataset.type = item.type;
            cb.dataset.id = String(item.id);
            cb.className = 'public-favorite-dialog__choice-input';
            label.appendChild(kind);
            label.appendChild(name);
            label.appendChild(heart);
            label.appendChild(cb);
            li.appendChild(label);
            ul.appendChild(li);
        });
        if (!ul.children.length) {
            var empty = document.createElement('p');
            empty.className = 'public-favorite-dialog__empty';
            empty.textContent = lang === 'en' ? 'Nothing to favorite on this event.' : 'Ehhez az eseményhez nincs választható elem.';
            listEl.appendChild(empty);
            return;
        }
        listEl.appendChild(ul);
    }

    function ensureAuth(root) {
        if (root.getAttribute('data-logged-in') === '1' || guestOk()) {
            return Promise.resolve(true);
        }
        var dlg = authDialog();
        var loginLink = qs('[data-public-favorite-login-link]', dlg);
        if (loginLink) {
            try {
                loginLink.href = loginLink.pathname.indexOf('login') >= 0
                    ? window.location.pathname + window.location.search
                    : loginLink.href;
            } catch (e) {
                /* keep default */
            }
        }
        return openDialog(dlg).then(function (val) {
            if (val === 'guest') {
                setGuestOk();
                return true;
            }
            return false;
        });
    }

    function toggleSimple(root) {
        var type = root.getAttribute('data-entity-type');
        var id = root.getAttribute('data-entity-id');
        var lang = root.getAttribute('data-lang') || 'hu';
        return postForm({
            action: 'toggle',
            entity_type: type,
            entity_id: id,
            lang: lang,
        }).then(function (res) {
            if (!res.ok || !res.json || !res.json.ok) {
                throw new Error((res.json && res.json.error) || 'error');
            }
            if (res.json.logged_in) {
                markLoggedIn(root);
            }
            syncAllOfType(type, id, !!res.json.active, res.json.count);
            syncPickerActive(root, type, id, !!res.json.active);
        });
    }

    function openEventPicker(root) {
        var raw = root.getAttribute('data-event-picker');
        if (!raw) {
            return toggleSimple(root);
        }
        var picker;
        try {
            picker = JSON.parse(raw);
        } catch (e) {
            return toggleSimple(root);
        }
        var dlg = eventDialog();
        var listEl = qs('[data-public-favorite-event-choices]', dlg);
        var lang = root.getAttribute('data-lang') || 'hu';
        var items = (picker && picker.items) || [];
        var statePayload = items.map(function (item) {
            return { type: item.type, id: item.id };
        });

        return postForm({
            action: 'states',
            items: JSON.stringify(statePayload),
            lang: lang,
        }).then(function (st) {
            if (st.ok && st.json && st.json.ok && Array.isArray(st.json.results)) {
                if (st.json.logged_in) {
                    markLoggedIn(root);
                }
                var byKey = {};
                st.json.results.forEach(function (row) {
                    byKey[row.type + ':' + row.id] = !!row.active;
                    syncPickerActive(root, row.type, row.id, !!row.active);
                    if (row.type === root.getAttribute('data-entity-type')
                        && String(row.id) === String(root.getAttribute('data-entity-id'))) {
                        // count always from event row if present
                    }
                });
                items.forEach(function (item) {
                    var key = item.type + ':' + item.id;
                    if (Object.prototype.hasOwnProperty.call(byKey, key)) {
                        item.active = byKey[key];
                    }
                });
                picker.items = items;
                root.setAttribute('data-event-picker', JSON.stringify(picker));
                var anyActive = items.some(function (item) {
                    return !!item.active;
                });
                var eventState = st.json.results.find(function (row) {
                    return row.type === 'event'
                        && String(row.id) === String(root.getAttribute('data-entity-id'));
                });
                var count = parseInt(root.getAttribute('data-count'), 10) || 0;
                if (eventState && typeof eventState.count === 'number') {
                    count = eventState.count;
                }
                updateWidget(root, anyActive, count);
            }
            buildEventChoices(listEl, picker, lang);
            qsa('[data-public-favorite-event-cancel]', dlg).forEach(function (cancelBtn) {
                cancelBtn.onclick = function () {
                    if (typeof dlg.close === 'function') {
                        dlg.close('cancel');
                    }
                };
            });
            return openDialog(dlg);
        }).then(function (val) {
            if (val !== 'save') {
                return;
            }
            var checks = qsa('input[type="checkbox"][name="fav_pick[]"]', listEl);
            var toOn = [];
            var toOff = [];
            checks.forEach(function (cb) {
                var item = { type: cb.dataset.type, id: parseInt(cb.dataset.id, 10) };
                if (cb.checked) {
                    toOn.push(item);
                } else {
                    toOff.push(item);
                }
            });
            var chain = Promise.resolve({ ok: true, json: { results: [], logged_in: false } });
            if (toOff.length) {
                chain = chain.then(function () {
                    return postForm({
                        action: 'batch',
                        active: '0',
                        items: JSON.stringify(toOff),
                        lang: lang,
                    });
                });
            }
            if (toOn.length) {
                chain = chain.then(function (prev) {
                    return postForm({
                        action: 'batch',
                        active: '1',
                        items: JSON.stringify(toOn),
                        lang: lang,
                    }).then(function (next) {
                        var merged = [];
                        if (prev && prev.json && prev.json.results) {
                            merged = merged.concat(prev.json.results);
                        }
                        if (next && next.json && next.json.results) {
                            merged = merged.concat(next.json.results);
                        }
                        return {
                            ok: !!(next && next.ok && next.json && next.json.ok),
                            json: {
                                results: merged,
                                logged_in: next && next.json ? next.json.logged_in : false,
                                error: next && next.json ? next.json.error : '',
                            },
                        };
                    });
                });
            }
            return chain.then(function (res) {
                if (!res || !res.ok) {
                    throw new Error((res && res.json && res.json.error) || 'error');
                }
                if (res.json && res.json.logged_in) {
                    markLoggedIn(root);
                }
                (res.json.results || []).forEach(function (row) {
                    syncAllOfType(row.type, row.id, !!row.active, row.count);
                    syncPickerActive(root, row.type, row.id, !!row.active);
                });
                checks.forEach(function (cb) {
                    syncPickerActive(root, cb.dataset.type, cb.dataset.id, !!cb.checked);
                });
                var anyActive = checks.some(function (cb) {
                    return cb.checked;
                });
                var eventId = root.getAttribute('data-entity-id');
                return postForm({
                    action: 'state',
                    entity_type: 'event',
                    entity_id: eventId,
                    lang: lang,
                }).then(function (st2) {
                    var count = parseInt(root.getAttribute('data-count'), 10) || 0;
                    if (st2.ok && st2.json && st2.json.ok) {
                        count = st2.json.count;
                        if (st2.json.logged_in) {
                            markLoggedIn(root);
                        }
                    }
                    updateWidget(root, anyActive, count);
                });
            });
        });
    }

    function onHeartClick(ev) {
        var btn = ev.target.closest('[data-public-favorite-btn]');
        if (!btn) {
            return;
        }
        var root = btn.closest('[data-public-favorite]');
        if (!root) {
            return;
        }
        ev.preventDefault();
        if (btn.disabled) {
            return;
        }
        btn.disabled = true;
        ensureAuth(root)
            .then(function (ok) {
                if (!ok) {
                    return;
                }
        // Bejelentkezve az esemény szíven is jöjjön a választó; entitásoldalon marad a toggle.
                if (root.getAttribute('data-mode') === 'event') {
                    return openEventPicker(root);
                }
                return toggleSimple(root);
            })
            .catch(function () {
                /* silent */
            })
            .finally(function () {
                btn.disabled = false;
            });
    }

    document.addEventListener('click', onHeartClick);
})();
