<script>
(function () {
    var form = document.getElementById('organizers-filter-form');
    if (!form) return;

    var debounceTimer = null;

    function submitForm() {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    form.querySelectorAll('.events-filter-select').forEach(function (el) {
        el.addEventListener('change', submitForm);
    });

    form.querySelectorAll('input.events-filter-input[type="text"], input.events-filter-input[type="search"]').forEach(function (el) {
        el.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(submitForm, 450);
        });
    });
})();
</script>
<script>
(function () {
    document.querySelectorAll('.organizers-admin-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var name = form.getAttribute('data-name') || 'ezt a szervezőt';
            var n = parseInt(form.getAttribute('data-events') || '0', 10);
            var msg;
            if (n > 0) {
                msg = 'A(z) „' + name + '” szervezőnek ' + n + ' eseménye van. A szervező törlődik, az események megmaradnak, de leválnak róla. Biztosan törlöd?';
            } else {
                msg = 'Biztosan törlöd a(z) „' + name + '” szervezőt?';
            }
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });
    });
})();
</script>
