<script>
(function () {
    var form = document.getElementById('events-edit-form');
    if (!form) return;

    var alertEl = document.getElementById('events-form-validation-alert');
    var statusSelect = document.getElementById('event_status_data');
    var eventUrlInp = form.querySelector('[data-required-for-publish="1"]');
    var publishStatus = 'publish';
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

    if (statusSelect) {
        statusSelect.addEventListener('change', syncEventUrlRequired);
    }
    form.querySelectorAll('button[type="submit"][name="save_action"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            pendingSaveAction = btn.value || '';
            syncEventUrlRequired();
        });
    });
    form.addEventListener('submit', function () {
        syncEventUrlRequired();
    });
    syncEventUrlRequired();

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
