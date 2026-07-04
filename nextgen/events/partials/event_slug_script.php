<script>
(function () {
    var nameEl = document.getElementById('event_name');
    var slugEl = document.getElementById('event_slug');
    var dateEl = document.getElementById('event_start_date');
    var btn = document.getElementById('event-slug-refresh');
    if (!nameEl || !slugEl) return;

    var autoSlug = slugEl.getAttribute('data-auto-slug') === '1';
    var slugTouched = !autoSlug && (slugEl.value || '').trim() !== '';

    var accentMap = {
        'á': 'a', 'é': 'e', 'í': 'i', 'ó': 'o', 'ö': 'o', 'ő': 'o',
        'ú': 'u', 'ü': 'u', 'ű': 'u'
    };

    function replaceAccents(s) {
        return s.replace(/[áéíóöőúüű]/g, function (ch) { return accentMap[ch] || ch; });
    }

    function slugifyClient(text) {
        var s = replaceAccents((text || '').trim().toLowerCase());
        s = s.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        return s || 'esemeny';
    }

    function normalizeSlugInput(raw) {
        var s = replaceAccents((raw || '').toLowerCase());
        s = s.replace(/[^a-z0-9-]+/g, '-').replace(/-+/g, '-').replace(/^-+|-+$/g, '');
        return s;
    }

    function todayYmd() {
        var d = new Date();
        return d.getFullYear() + '-'
            + String(d.getMonth() + 1).padStart(2, '0') + '-'
            + String(d.getDate()).padStart(2, '0');
    }

    function buildSlug(useToday) {
        var base = slugifyClient(nameEl.value || '');
        var date = '';
        if (useToday) {
            date = todayYmd();
        } else if (dateEl && dateEl.value) {
            date = dateEl.value.trim();
        }
        if (date !== '' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
            return base + '-' + date;
        }
        return base;
    }

    function applyAutoSlug() {
        if (!autoSlug || slugTouched) return;
        slugEl.value = buildSlug(true);
    }

    slugEl.addEventListener('input', function () {
        slugTouched = true;
        var cur = slugEl.value;
        var norm = normalizeSlugInput(cur);
        if (cur !== norm) {
            slugEl.value = norm;
        }
    });

    nameEl.addEventListener('input', applyAutoSlug);

    if (btn) {
        btn.addEventListener('click', function () {
            slugEl.value = buildSlug(autoSlug);
            if (autoSlug) {
                slugTouched = false;
            }
            slugEl.focus();
        });
    }

    if (autoSlug && (slugEl.value || '').trim() === '') {
        applyAutoSlug();
    } else if ((slugEl.value || '').trim() !== '') {
        var initialNorm = normalizeSlugInput(slugEl.value);
        if (initialNorm !== slugEl.value) {
            slugEl.value = initialNorm;
        }
    }

    var form = slugEl.closest('form');
    if (form) {
        form.addEventListener('click', function (e) {
            var submitter = e.target.closest('button[type="submit"], input[type="submit"]');
            if (!submitter || !form.contains(submitter)) {
                return;
            }
            if ((slugEl.value || '').trim() === '' && (nameEl.value || '').trim() !== '') {
                slugEl.value = buildSlug(autoSlug);
            } else if ((slugEl.value || '').trim() !== '') {
                slugEl.value = normalizeSlugInput(slugEl.value);
            }
        }, true);
    }
})();
</script>
