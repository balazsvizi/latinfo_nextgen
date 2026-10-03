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
        var status = intendedStatus();
        // Előzetes / piszkozat: leírás nem kötelező. Közzétételhez igen.
        if (status === preliminaryStatus || status === 'draft' || status === 'auto-draft') {
            contentInp.removeAttribute('required');
        } else if (status === publishStatus) {
            contentInp.setAttribute('required', 'required');
        } else {
            contentInp.removeAttribute('required');
        }
    }

    function syncRequiredFields() {
        syncEventUrlRequired();
        syncContentRequired();
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
                return;
            }
            if (statusSelect && statusSelect.value !== preliminaryStatus) {
                statusSelect.value = preliminaryStatus;
            }
            syncRequiredFields();
            return;
        }
        if (normalizeHtml(current) === normalizeHtml(template) || currentPlain === templatePlain) {
            api.setData('');
        }
    }

    function resolveSubmitterForm(submitter) {
        if (!submitter) return null;
        if (submitter.form) return submitter.form;
        var formId = submitter.getAttribute('form');
        if (formId) {
            return document.getElementById(formId);
        }
        return null;
    }

    if (statusSelect) {
        statusSelect.addEventListener('change', syncRequiredFields);
        statusSelect.addEventListener('input', syncRequiredFields);
    }
    if (preliminaryFill) {
        preliminaryFill.addEventListener('change', function () {
            applyPreliminaryContent(!!preliminaryFill.checked);
        });
    }

    // Capture: a böngésző HTML5 validációja ELŐTT szinkronizáljuk a required-eket
    // (lebegő Mentés gomb is form="events-edit-form"-mal jön).
    document.addEventListener('click', function (e) {
        var submitter = e.target && e.target.closest
            ? e.target.closest('button[type="submit"], input[type="submit"]')
            : null;
        if (!submitter || resolveSubmitterForm(submitter) !== form) {
            return;
        }
        pendingSaveAction = submitter.name === 'save_action' ? (submitter.value || '') : '';
        syncRequiredFields();
        var api = contentApi();
        if (api && contentInp) {
            contentInp.value = api.getData();
        }
    }, true);

    form.addEventListener('submit', function () {
        syncRequiredFields();
        var api = contentApi();
        if (api && contentInp) {
            contentInp.value = api.getData();
        }
    });
    syncRequiredFields();

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

    form.addEventListener('change', function () {
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
