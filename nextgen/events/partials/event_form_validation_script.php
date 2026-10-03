<script>
(function () {
    var form = document.getElementById('events-edit-form');
    if (!form) return;

    var alertEl = document.getElementById('events-form-validation-alert');
    var statusSelect = document.getElementById('event_status_data');
    var eventUrlInp = form.querySelector('[data-required-for-publish="1"]');
    var contentInp = form.querySelector('[data-required-unless-preliminary="1"]');
    var preliminaryFill = document.getElementById('event_preliminary_content_fill');
    var publishStatus = 'publish';
    var preliminaryStatus = 'preliminary';
    var pendingSaveAction = '';

    function intendedStatus() {
        if (pendingSaveAction === 'publish') {
            return publishStatus;
        }
        if (pendingSaveAction === 'draft') {
            return 'draft';
        }
        return statusSelect ? (statusSelect.value || '') : '';
    }

    function contentApi() {
        if (!contentInp) return null;
        return contentInp.__eventsHtmlEditor || {
            getData: function () { return contentInp.value || ''; },
            setData: function (html) { contentInp.value = html || ''; }
        };
    }

    function normalizeHtml(html) {
        return String(html || '')
            .replace(/\s+/g, ' ')
            .replace(/&nbsp;/gi, ' ')
            .trim()
            .toLowerCase();
    }

    function stripTags(html) {
        var tmp = document.createElement('div');
        tmp.innerHTML = html || '';
        return (tmp.textContent || tmp.innerText || '').replace(/\s+/g, ' ').trim();
    }

    function syncEventUrlRequired() {
        if (!eventUrlInp) return;
        var need = intendedStatus() === publishStatus;
        if (need) {
            eventUrlInp.setAttribute('required', 'required');
            eventUrlInp.setAttribute('aria-label', 'További információ URL (közzétételhez kötelező)');
        } else {
            eventUrlInp.removeAttribute('required');
            eventUrlInp.setAttribute('aria-label', 'További információ URL');
        }
    }

    function syncContentRequired() {
        if (!contentInp) return;
        var preliminary = intendedStatus() === preliminaryStatus;
        if (preliminary) {
            contentInp.removeAttribute('required');
        } else {
            contentInp.setAttribute('required', 'required');
        }
    }

    function applyPreliminaryContent(checked) {
        var api = contentApi();
        if (!api || !preliminaryFill) return;
        var template = preliminaryFill.getAttribute('data-preliminary-content-template') || '';
        if (template === '') return;
        var current = api.getData();
        var currentPlain = stripTags(current);
        var templatePlain = stripTags(template);
        if (checked) {
            if (currentPlain === '' || normalizeHtml(current) === normalizeHtml(template) || currentPlain.indexOf('előzetes információ') !== -1) {
                api.setData(template);
            } else if (window.confirm('A leírás nem üres. Lecseréled az előzetes tájékoztató szövegre?')) {
                api.setData(template);
            } else {
                preliminaryFill.checked = false;
            }
            return;
        }
        if (normalizeHtml(current) === normalizeHtml(template) || currentPlain === templatePlain) {
            api.setData('');
        }
    }

    if (statusSelect) {
        statusSelect.addEventListener('change', function () {
            syncEventUrlRequired();
            syncContentRequired();
        });
    }
    if (preliminaryFill) {
        preliminaryFill.addEventListener('change', function () {
            applyPreliminaryContent(!!preliminaryFill.checked);
            if (preliminaryFill.checked && statusSelect && statusSelect.value !== preliminaryStatus && statusSelect.value !== publishStatus) {
                statusSelect.value = preliminaryStatus;
                syncEventUrlRequired();
                syncContentRequired();
            }
        });
    }
    form.querySelectorAll('button[type="submit"][name="save_action"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            pendingSaveAction = btn.value || '';
            syncEventUrlRequired();
            syncContentRequired();
        });
    });
    form.addEventListener('submit', function () {
        syncEventUrlRequired();
        syncContentRequired();
        var api = contentApi();
        if (api && contentInp) {
            contentInp.value = api.getData();
        }
    });
    syncEventUrlRequired();
    syncContentRequired();

    form.addEventListener('invalid', function (e) {
        var el = e.target;
        if (!el || !form.contains(el)) {
            return;
        }
        if (!alertEl) {
            alertEl = document.createElement('p');
            alertEl.id = 'events-form-validation-alert';
            alertEl.className = 'alert alert-error';
            alertEl.setAttribute('role', 'alert');
            form.parentNode.insertBefore(alertEl, form);
        }
        var label = '';
        if (el.labels && el.labels.length > 0) {
            label = (el.labels[0].textContent || '').trim();
        } else if (el.getAttribute('aria-label')) {
            label = el.getAttribute('aria-label') || '';
        } else if (el.id) {
            label = el.id;
        }
        var msg = el.validationMessage || 'Ellenőrizd a kötelező mezőket.';
        alertEl.textContent = label !== '' ? label + ': ' + msg : msg;
        alertEl.hidden = false;
    }, true);

    form.addEventListener('input', function (e) {
        if (!alertEl || alertEl.hidden) {
            return;
        }
        if (e.target && form.contains(e.target) && typeof e.target.checkValidity === 'function' && e.target.checkValidity()) {
            alertEl.hidden = true;
            alertEl.textContent = '';
        }
    }, true);

    form.addEventListener('change', function (e) {
        if (!alertEl || alertEl.hidden) {
            return;
        }
        if (form.checkValidity()) {
            alertEl.hidden = true;
            alertEl.textContent = '';
        }
    }, true);
})();
</script>
