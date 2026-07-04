<script>
(function () {
    var shell = document.querySelector('.events-filters-shell');
    if (!shell) return;
    var form = shell.closest('form');
    var axisMin = shell.getAttribute('data-axis-min');
    var days = parseInt(shell.getAttribute('data-axis-days'), 10) || 0;

    var debounceTimer = null;
    var rangeTimer = null;

    function submitForm() {
        if (!form) return;
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

    if (form) {
        form.querySelectorAll('.events-filter-select').forEach(function (el) {
            el.addEventListener('change', submitForm);
        });

        var showAllToggle = form.querySelector('#ev-show-all');
        if (showAllToggle) {
            showAllToggle.addEventListener('change', submitForm);
        }

        form.querySelectorAll('input.events-filter-input[type="text"], input.events-filter-input[type="search"]').forEach(function (el) {
            el.addEventListener('input', function () {
                debouncedSubmit(450);
            });
        });

        form.querySelectorAll('input.events-filter-input[type="number"]').forEach(function (el) {
            el.addEventListener('input', function () {
                debouncedSubmit(450);
            });
            el.addEventListener('change', submitForm);
        });
    }

    if (!axisMin || days < 1) {
        return;
    }

    var axisStart = new Date(axisMin + 'T12:00:00');
    function idxToYmd(idx) {
        var d = new Date(axisStart);
        d.setDate(d.getDate() + idx);
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }
    function ymdToIdx(ymd) {
        if (!ymd) return null;
        var t = new Date(ymd + 'T12:00:00').getTime();
        if (isNaN(t)) return null;
        var diff = Math.round((t - axisStart.getTime()) / 86400000);
        if (diff < 0) return 0;
        if (diff > days) return days;
        return diff;
    }
    var rFrom = document.getElementById('ev-range-from');
    var rTo = document.getElementById('ev-range-to');
    var dFrom = document.getElementById('ev-f-start-from');
    var dTo = document.getElementById('ev-f-start-to');
    var fill = document.getElementById('ev-date-range-fill');
    if (!rFrom || !rTo || !dFrom || !dTo) return;

    function parseIdx(el) {
        var v = parseInt(el.value, 10);
        if (isNaN(v)) return 0;
        if (v < 0) return 0;
        if (v > days) return days;
        return v;
    }
    function updateFill() {
        var a = parseIdx(rFrom);
        var b = parseIdx(rTo);
        if (a > b) { b = a; rTo.value = String(b); }
        var p0 = (a / days) * 100;
        var p1 = (b / days) * 100;
        if (fill) {
            fill.style.left = p0 + '%';
            fill.style.width = Math.max(0, p1 - p0) + '%';
        }
    }
    function syncSlidersToDates() {
        var i0 = ymdToIdx(dFrom.value);
        var i1 = ymdToIdx(dTo.value);
        if (dFrom.value === '') rFrom.value = '0';
        else if (i0 !== null) rFrom.value = String(i0);
        if (dTo.value === '') rTo.value = String(days);
        else if (i1 !== null) rTo.value = String(i1);
        var a = parseIdx(rFrom);
        var b = parseIdx(rTo);
        if (a > b) { rFrom.value = String(b); a = b; }
        updateFill();
    }
    function syncDatesFromSliders() {
        var a = parseIdx(rFrom);
        var b = parseIdx(rTo);
        if (a > b) { rTo.value = String(a); b = a; }
        dFrom.value = a === 0 ? '' : idxToYmd(a);
        dTo.value = b >= days ? '' : idxToYmd(b);
        updateFill();
    }

    function onRangeInput() {
        syncDatesFromSliders();
        clearTimeout(rangeTimer);
        rangeTimer = setTimeout(submitForm, 500);
    }

    rFrom.addEventListener('input', onRangeInput);
    rTo.addEventListener('input', onRangeInput);
    rFrom.addEventListener('change', function () {
        syncDatesFromSliders();
        submitForm();
    });
    rTo.addEventListener('change', function () {
        syncDatesFromSliders();
        submitForm();
    });
    dFrom.addEventListener('change', function () {
        syncSlidersToDates();
        submitForm();
    });
    dTo.addEventListener('change', function () {
        syncSlidersToDates();
        submitForm();
    });

    syncSlidersToDates();
})();
</script>
