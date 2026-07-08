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

    function resolveSlugDate() {
        if (dateEl && dateEl.value) {
            var fromField = dateEl.value.trim();
            if (/^\d{4}-\d{2}-\d{2}$/.test(fromField)) {
                return fromField;
            }
        }
        if (autoSlug) {
            return todayYmd();
        }
        return '';
    }

    function buildSlug() {
        var base = slugifyClient(nameEl.value || '');
        var date = resolveSlugDate();
        if (date !== '') {
            return base + '-' + date;
        }
        return base;
    }

    function copySlugToClipboard(text) {
        if (!text) return;

        function onSuccess() {
            if (!btn) return;
            var prevTitle = btn.getAttribute('title') || '';
            btn.classList.add('is-copied');
            btn.setAttribute('title', 'Slug a vágólapon');
            window.setTimeout(function () {
                btn.classList.remove('is-copied');
                btn.setAttribute('title', prevTitle);
            }, 1500);
        }

        function fallbackCopy() {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                if (document.execCommand('copy')) {
                    onSuccess();
                }
            } finally {
                document.body.removeChild(ta);
            }
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(onSuccess).catch(fallbackCopy);
        } else {
            fallbackCopy();
        }
    }

    function applyGeneratedSlug(copyToClipboard) {
        var slug = buildSlug();
        slugEl.value = slug;
        if (copyToClipboard) {
            copySlugToClipboard(slug);
        }
    }

    function applyAutoSlug() {
        if (!autoSlug || slugTouched) return;
        applyGeneratedSlug(false);
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
            applyGeneratedSlug(true);
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
                slugEl.value = buildSlug();
            } else if ((slugEl.value || '').trim() !== '') {
                slugEl.value = normalizeSlugInput(slugEl.value);
            }
        }, true);
    }
})();
</script>
