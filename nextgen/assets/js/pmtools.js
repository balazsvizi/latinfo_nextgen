(function () {
    'use strict';

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.from((root || document).querySelectorAll(sel));
    }

    function createNoteRow(note, response) {
        const row = document.createElement('div');
        row.className = 'pm-tools-note-row';
        row.innerHTML =
            '<textarea class="pm-note-input" rows="2" placeholder="Jegyzet…"></textarea>' +
            '<textarea class="pm-response-input" rows="2" placeholder="Válasz…"></textarea>' +
            '<button type="button" class="pm-tools-row-del" title="Sor törlése">&times;</button>';
        qs('.pm-note-input', row).value = note || '';
        qs('.pm-response-input', row).value = response || '';
        return row;
    }

    function collectRows(container) {
        return qsa('.pm-tools-note-row', container).map(function (row) {
            return {
                note: qs('.pm-note-input', row).value.trim(),
                response: qs('.pm-response-input', row).value.trim(),
            };
        });
    }

    function setStatus(el, msg, type) {
        if (!el) return;
        el.textContent = msg || '';
        el.classList.remove('is-ok', 'is-err');
        if (type) el.classList.add(type === 'ok' ? 'is-ok' : 'is-err');
    }

    function copyToClipboard(text, btn) {
        function onSuccess() {
            if (!btn) return;
            btn.classList.add('is-copied');
            window.setTimeout(function () {
                btn.classList.remove('is-copied');
            }, 1500);
        }

        function fallbackCopy() {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                if (document.execCommand('copy')) {
                    onSuccess();
                }
            } finally {
                document.body.removeChild(ta);
            }
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(onSuccess).catch(fallbackCopy);
        } else {
            fallbackCopy();
        }
    }

    function bindCopyButtons(root) {
        qsa('.pm-tools-copy-btn', root).forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                const text = btn.dataset.copy || '';
                if (text) copyToClipboard(text, btn);
            });
        });
    }

    function savePage(root, statusEl, extra) {
        const api = root.dataset.api;
        const csrf = root.dataset.csrf;
        const pageId = root.dataset.pageId;
        const payload = {
            action: 'save_page',
            page_id: pageId,
            csrf: csrf,
            display_name: extra.display_name,
            purpose: extra.purpose,
            notes: extra.notes || [],
        };

        return fetch(api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'same-origin',
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok || !data.ok) {
                    throw new Error(data.error || 'Mentés sikertelen.');
                }
                return data;
            });
        }).then(function (data) {
            setStatus(statusEl, data.message || 'Mentve.', 'ok');
            if (extra.onSaved) extra.onSaved(data);
        }).catch(function (err) {
            setStatus(statusEl, err.message || 'Hiba történt.', 'err');
        });
    }

    function refreshNotesBtnUnanswered(container, notesBtn) {
        if (!container || !notesBtn) return;
        let hasUnanswered = false;
        qsa('.pm-tools-note-row', container).forEach(function (row) {
            const noteEl = qs('.pm-note-input', row);
            const respEl = qs('.pm-response-input', row);
            if (noteEl && respEl && noteEl.value.trim() !== '' && respEl.value.trim() === '') {
                hasUnanswered = true;
            }
        });
        notesBtn.classList.toggle('has-unanswered-notes', hasUnanswered);
    }

    function refreshUnansweredRow(row) {
        const noteEl = qs('.pm-note-input', row);
        const respEl = qs('.pm-response-input', row);
        if (!noteEl || !respEl) return;
        const unanswered = noteEl.value.trim() !== '' && respEl.value.trim() === '';
        respEl.classList.toggle('is-unanswered', unanswered);
        row.classList.toggle('is-unanswered-row', unanswered);
    }

    function bindUnansweredStyles(container, notesBtn) {
        if (!container) return;
        qsa('.pm-tools-note-row', container).forEach(refreshUnansweredRow);
        refreshNotesBtnUnanswered(container, notesBtn);
        if (container.dataset.unansweredBound) return;
        container.dataset.unansweredBound = '1';
        container.addEventListener('input', function (e) {
            const row = e.target.closest('.pm-tools-note-row');
            if (row && container.contains(row)) {
                refreshUnansweredRow(row);
                refreshNotesBtnUnanswered(container, notesBtn);
            }
        });
    }

    function bindRowDelete(container, notesBtn) {
        container.addEventListener('click', function (e) {
            const btn = e.target.closest('.pm-tools-row-del');
            if (!btn) return;
            const row = btn.closest('.pm-tools-note-row');
            const rows = qsa('.pm-tools-note-row', container);
            if (row && rows.length > 1) {
                row.remove();
            } else if (row) {
                qs('.pm-note-input', row).value = '';
                qs('.pm-response-input', row).value = '';
                refreshUnansweredRow(row);
            }
            refreshNotesBtnUnanswered(container, notesBtn);
        });
    }

    function initWidget() {
        const root = qs('#pm-tools-root');
        if (!root) return;

        const overlay = qs('#pm-tools-overlay', root);
        const notesBtn = qs('#pm-tools-notes-btn', root);
        const closeBtn = qs('#pm-tools-close', root);
        const addRowBtn = qs('#pm-tools-add-row', root);
        const saveBtn = qs('#pm-tools-save', root);
        const notesContainer = qs('#pm-tools-notes-rows', root);
        const statusEl = qs('#pm-tools-status', root);
        const nameInput = qs('#pm-tools-name', root);

        bindRowDelete(notesContainer, notesBtn);
        bindCopyButtons(root);
        bindUnansweredStyles(notesContainer, notesBtn);

        notesBtn.addEventListener('click', function () {
            overlay.hidden = false;
        });

        closeBtn.addEventListener('click', function () {
            overlay.hidden = true;
        });

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.hidden = true;
        });

        addRowBtn.addEventListener('click', function () {
            notesContainer.appendChild(createNoteRow('', ''));
            bindUnansweredStyles(notesContainer, notesBtn);
        });

        saveBtn.addEventListener('click', function () {
            setStatus(statusEl, 'Mentés…', null);
            savePage(root, statusEl, {
                display_name: nameInput.value,
                purpose: '',
                notes: collectRows(notesContainer),
                onSaved: function () {
                    const title = qs('#pm-tools-modal-title', root);
                    if (title) title.textContent = nameInput.value.trim() || root.dataset.phpPath;
                    refreshNotesBtnUnanswered(notesContainer, notesBtn);
                },
            });
        });
    }

    function initAdminCards() {
        const cards = qsa('.pm-admin-card');
        if (!cards.length) return;

        const api = cards[0].dataset.api;
        const csrf = cards[0].dataset.csrf;

        cards.forEach(function (card) {
            const pageId = card.dataset.pageId;
            const saveBtn = qs('.pm-admin-save', card);
            const addRowBtn = qs('.pm-admin-add-row', card);
            const notesContainer = qs('.pm-admin-notes-rows', card);
            const statusEl = qs('.pm-admin-card-status', card);
            const nameInput = qs('.pm-admin-name', card);

            bindRowDelete(notesContainer, null);
            bindUnansweredStyles(notesContainer, null);

            addRowBtn.addEventListener('click', function () {
                notesContainer.appendChild(createNoteRow('', ''));
                bindUnansweredStyles(notesContainer, null);
            });

            saveBtn.addEventListener('click', function () {
                setStatus(statusEl, 'Mentés…', null);
                const root = { dataset: { api: api, csrf: csrf, pageId: pageId } };
                savePage(root, statusEl, {
                    display_name: nameInput.value,
                    purpose: '',
                    notes: collectRows(notesContainer),
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initWidget();
        initAdminCards();
    });
})();
