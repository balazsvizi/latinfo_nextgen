<script>
(function () {
    var nameEl = document.getElementById('event_name');
    var slugEl = document.getElementById('event_slug');
    var dateEl = document.getElementById('event_start_date');
    var btn = document.getElementById('event-slug-refresh');
    if (!nameEl || !slugEl || !btn) return;

    function slugifyClient(text) {
        var map = {
            'á': 'a', 'é': 'e', 'í': 'i', 'ó': 'o', 'ö': 'o', 'ő': 'o',
            'ú': 'u', 'ü': 'u', 'ű': 'u'
        };
        var s = (text || '').trim().toLowerCase();
        s = s.replace(/[áéíóöőúüű]/g, function (ch) { return map[ch] || ch; });
        s = s.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        return s || 'esemeny';
    }

    function buildSlug() {
        var base = slugifyClient(nameEl.value || '');
        var date = (dateEl && dateEl.value) ? dateEl.value.trim() : '';
        if (date !== '' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
            return base + '-' + date;
        }
        return base;
    }

    btn.addEventListener('click', function () {
        slugEl.value = buildSlug();
        slugEl.focus();
    });
})();
</script>
