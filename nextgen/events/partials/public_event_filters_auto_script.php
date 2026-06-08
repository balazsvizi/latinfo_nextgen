<script>
(function () {
    var form = document.getElementById('events-home-filter-form');
    if (!form) return;

    var debounceTimer = null;

    function submitForm() {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    function debouncedSubmit(delay) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(submitForm, delay);
    }

    form.querySelectorAll('.events-filter-select').forEach(function (el) {
        el.addEventListener('change', submitForm);
    });

    form.querySelectorAll('input.events-filter-input[type="text"]').forEach(function (el) {
        el.addEventListener('input', function () {
            debouncedSubmit(450);
        });
    });

    form.querySelectorAll('input.events-filter-input[type="date"]').forEach(function (el) {
        el.addEventListener('change', submitForm);
    });

    var rFrom = document.getElementById('ev-range-from');
    var rTo = document.getElementById('ev-range-to');
    if (rFrom && rTo) {
        var rangeTimer = null;
        rFrom.addEventListener('change', submitForm);
        rTo.addEventListener('change', submitForm);
        rFrom.addEventListener('input', function () {
            clearTimeout(rangeTimer);
            rangeTimer = setTimeout(submitForm, 500);
        });
        rTo.addEventListener('input', function () {
            clearTimeout(rangeTimer);
            rangeTimer = setTimeout(submitForm, 500);
        });
    }
})();
</script>
