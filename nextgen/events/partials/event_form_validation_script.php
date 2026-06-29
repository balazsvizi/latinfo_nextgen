<script>
(function () {
    var form = document.getElementById('events-edit-form');
    if (!form) return;

    var alertEl = document.getElementById('events-form-validation-alert');

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
