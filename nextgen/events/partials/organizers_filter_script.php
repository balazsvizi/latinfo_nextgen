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
<script>
(function () {
    var bulkForm = document.getElementById('organizers-bulk-form');
    var table = document.getElementById('organizers-admin-table');
    if (!bulkForm || !table) return;

    var checkAll = document.getElementById('organizers-check-all');
    var bulkBtn = document.getElementById('organizers-bulk-delete-btn');
    var selectedLabel = document.getElementById('organizers-selected-label');

    function rowChecks() {
        return Array.prototype.slice.call(table.querySelectorAll('.organizers-admin-row-check'));
    }

    function syncBulkUi() {
        var checks = rowChecks();
        var checked = checks.filter(function (cb) { return cb.checked; });
        if (checkAll) {
            checkAll.checked = checks.length > 0 && checked.length === checks.length;
            checkAll.indeterminate = checked.length > 0 && checked.length < checks.length;
        }
        if (selectedLabel) {
            selectedLabel.textContent = checked.length + ' kiválasztva';
        }
        if (bulkBtn) {
            bulkBtn.disabled = checked.length === 0;
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            var on = checkAll.checked;
            rowChecks().forEach(function (cb) { cb.checked = on; });
            syncBulkUi();
        });
    }

    table.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || !t.classList || !t.classList.contains('organizers-admin-row-check')) return;
        syncBulkUi();
    });

    bulkForm.addEventListener('submit', function (e) {
        var checked = rowChecks().filter(function (cb) { return cb.checked; });
        if (checked.length === 0) {
            e.preventDefault();
            return;
        }
        var withEvents = checked.filter(function (cb) {
            return parseInt(cb.getAttribute('data-events') || '0', 10) > 0;
        }).length;
        var msg;
        if (withEvents > 0) {
            msg = checked.length + ' szervezőt törölsz, közülük ' + withEvents + ' eseményhez van rendelve. A szervezők törlődnek, az események megmaradnak, de leválnak róluk. Biztosan törlöd?';
        } else {
            msg = checked.length + ' szervezőt törölsz. Biztosan folytatod?';
        }
        if (!window.confirm(msg)) {
            e.preventDefault();
        }
    });

    syncBulkUi();
})();
</script>
