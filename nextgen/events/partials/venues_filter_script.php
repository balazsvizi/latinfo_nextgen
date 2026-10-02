<script>
(function () {
    var form = document.getElementById('venues-filter-form');
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
    document.querySelectorAll('.venues-admin-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var name = form.getAttribute('data-name') || 'ezt a helyszínt';
            var n = parseInt(form.getAttribute('data-events') || '0', 10);
            var msg;
            if (n > 0) {
                msg = 'A(z) „' + name + '” helyszínnek ' + n + ' eseménye van, ezért nem törölhető. Előbb válaszd le a helyszínt az eseményeken.';
                window.alert(msg);
                e.preventDefault();
                return;
            }
            msg = 'Biztosan törlöd a(z) „' + name + '” helyszínt?';
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });
    });
})();
</script>
<script>
(function () {
    var bulkForm = document.getElementById('venues-bulk-form');
    var table = document.getElementById('venues-admin-table');
    if (!bulkForm || !table) return;

    var checkAll = document.getElementById('venues-check-all');
    var bulkBtn = document.getElementById('venues-bulk-delete-btn');
    var selectedLabel = document.getElementById('venues-selected-label');

    function rowChecks() {
        return Array.prototype.slice.call(table.querySelectorAll('.venues-admin-row-check'));
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
        if (!t || !t.classList || !t.classList.contains('venues-admin-row-check')) return;
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
        var deletable = checked.length - withEvents;
        var msg;
        if (deletable === 0) {
            msg = 'A kijelölt helyszín(ek) eseményhez van(nak) rendelve, ezért nem törölhetők.';
            window.alert(msg);
            e.preventDefault();
            return;
        }
        if (withEvents > 0) {
            msg = deletable + ' helyszínt törölsz. ' + withEvents + ' helyszín kihagyásra kerül, mert eseményhez van rendelve. Biztosan folytatod?';
        } else {
            msg = checked.length + ' helyszínt törölsz. Biztosan folytatod?';
        }
        if (!window.confirm(msg)) {
            e.preventDefault();
        }
    });

    syncBulkUi();
})();
</script>
