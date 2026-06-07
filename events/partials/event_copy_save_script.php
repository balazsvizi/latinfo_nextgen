<script>
(function () {
    var form = document.getElementById('events-edit-form');
    var isCopy = form && form.querySelector('input[name="is_copy"]');
    if (!form || !isCopy) return;

    var sourceHidden = document.getElementById('copy_source_featured_image');
    var urlInp = document.getElementById('event_featured_image_url');
    var pickHidden = document.getElementById('event_featured_image_pick');
    var eventUrlInp = document.getElementById('event_url');
    var EVENTPICS_PREFIX = '/events/eventpics/';

    function effectiveFeaturedImage() {
        if (urlInp) {
            var url = (urlInp.value || '').trim();
            if (url !== '') return url;
        }
        if (pickHidden) {
            var pick = (pickHidden.value || '').trim();
            if (pick !== '') return EVENTPICS_PREFIX + pick.replace(/^\//, '');
        }
        return '';
    }

    function buildWarnings() {
        var msgs = [];
        var sourceImg = sourceHidden ? (sourceHidden.value || '').trim() : '';
        var finalImg = effectiveFeaturedImage();
        var eventUrl = eventUrlInp ? (eventUrlInp.value || '').trim() : '';
        if (sourceImg !== '' && finalImg === sourceImg) {
            msgs.push('A borítókép nem lett lecserélve az eredeti másolatról.');
        }
        if (finalImg === '' && eventUrl === '') {
            msgs.push('Nincs borítókép és további információ URL sem megadva.');
        }
        return msgs;
    }

    var confirmed = false;
    form.addEventListener('submit', function (ev) {
        if (confirmed) return;
        var msgs = buildWarnings();
        if (msgs.length === 0) return;
        var text = msgs.join('\n\n') + '\n\nBiztosan mented így?';
        if (!window.confirm(text)) {
            ev.preventDefault();
            return;
        }
        confirmed = true;
    });
})();
</script>
