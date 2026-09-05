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
        if (el.classList.contains('events-filter-multiselect__toggle')) {
            return;
        }
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

    form.querySelectorAll('[data-filter-multiselect]').forEach(function (root) {
        var toggle = root.querySelector('.events-filter-multiselect__toggle');
        var panel = root.querySelector('.events-filter-multiselect__panel');
        var summary = root.querySelector('.events-filter-multiselect__summary');
        if (!toggle || !panel || !summary) {
            return;
        }
        var allLabel = root.getAttribute('data-all-label') || '';
        var countTpl = root.getAttribute('data-count-template') || '%d';

        function selectedBoxes() {
            return Array.prototype.slice.call(root.querySelectorAll('input[type="checkbox"]:checked'));
        }

        function optionLabel(box) {
            var textEl = box.closest('.events-filter-multiselect__option');
            if (!textEl) {
                return box.value;
            }
            var span = textEl.querySelector('.events-filter-multiselect__option-text');
            return span ? span.textContent.trim() : textEl.textContent.trim();
        }

        function updateSummary() {
            var checked = selectedBoxes();
            if (checked.length === 0) {
                summary.textContent = allLabel;
            } else if (checked.length === 1) {
                summary.textContent = optionLabel(checked[0]);
            } else {
                summary.textContent = countTpl.replace('%d', String(checked.length));
            }
        }

        function setOpen(open) {
            root.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', '');
            }
        }

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            setOpen(!root.classList.contains('is-open'));
        });

        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && root.classList.contains('is-open')) {
                setOpen(false);
                toggle.focus();
            }
        });

        root.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
            cb.addEventListener('change', function () {
                updateSummary();
                debouncedSubmit(450);
            });
        });
    });
})();
</script>
