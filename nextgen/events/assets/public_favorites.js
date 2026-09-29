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
        if (btn) {
            btn.classList.toggle('is-active', !!active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        }
        if (countEl) {
            countEl.textContent = String(count);
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
                resolve(dlg.returnValue || '');
            }
            dlg.addEventListener('close', onClose);
            dlg.showModal();
        });
    }

    function buildEventChoices(listEl, picker, lang) {
        listEl.innerHTML = '';
        var items = (picker && picker.items) || [];
        items.forEach(function (item) {
            if (!item || !item.type || !item.id) {
                return;
            }
            var li = document.createElement('li');
            li.className = 'public-favorite-dialog__choice';
            var label = document.createElement('label');
            label.className = 'public-favorite-dialog__choice-label';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'fav_pick[]';
            cb.value = item.type + ':' + item.id;
            cb.checked = !!item.active;
            cb.dataset.type = item.type;
            cb.dataset.id = String(item.id);
            var span = document.createElement('span');
            span.textContent = item.label || (item.type + ' #' + item.id);
            label.appendChild(cb);
            label.appendChild(span);
            li.appendChild(label);
            listEl.appendChild(li);
        });
        if (!listEl.children.length) {
            var empty = document.createElement('p');
            empty.className = 'public-favorite-dialog__empty';
            empty.textContent = lang === 'en' ? 'Nothing to favorite on this event.' : 'Ehhez az eseményhez nincs választható elem.';
            listEl.appendChild(empty);
        }
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
            syncAllOfType(type, id, !!res.json.active, res.json.count);
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
        buildEventChoices(listEl, picker, lang);
        var cancelBtn = qs('[data-public-favorite-event-cancel]', dlg);
        if (cancelBtn) {
            cancelBtn.onclick = function () {
                if (typeof dlg.close === 'function') {
                    dlg.close('cancel');
                }
            };
        }
        return openDialog(dlg).then(function (val) {
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
            var chain = Promise.resolve();
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
                chain = chain.then(function () {
                    return postForm({
                        action: 'batch',
                        active: '1',
                        items: JSON.stringify(toOn),
                        lang: lang,
                    });
                });
            }
            return chain.then(function (res) {
                if (res && res.json && res.json.results) {
                    res.json.results.forEach(function (row) {
                        syncAllOfType(row.type, row.id, !!row.active, row.count);
                    });
                }
                var anyActive = checks.some(function (cb) {
                    return cb.checked;
                });
                var eventId = root.getAttribute('data-entity-id');
                return postForm({
                    action: 'state',
                    entity_type: 'event',
                    entity_id: eventId,
                    lang: lang,
                }).then(function (st) {
                    var count = parseInt(root.getAttribute('data-count'), 10) || 0;
                    if (st.ok && st.json && st.json.ok) {
                        count = st.json.count;
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
        btn.disabled = true;
        ensureAuth(root)
            .then(function (ok) {
                if (!ok) {
                    return;
                }
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
