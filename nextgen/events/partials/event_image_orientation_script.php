<script>
(function () {
    function applyOrientation(img) {
        if (!img || !img.naturalWidth) {
            return;
        }
        var ratio = img.naturalWidth / img.naturalHeight;
        img.classList.remove('is-portrait', 'is-landscape', 'is-square');
        if (ratio < 0.92) {
            img.classList.add('is-portrait');
        } else if (ratio > 1.15) {
            img.classList.add('is-landscape');
        } else {
            img.classList.add('is-square');
        }
    }

    window.eventsApplyImageOrientation = applyOrientation;

    function bind(img) {
        if (!img) {
            return;
        }
        if (img.complete && img.naturalWidth) {
            applyOrientation(img);
        } else {
            img.addEventListener('load', function () {
                applyOrientation(img);
            }, { once: true });
        }
    }

    document.querySelectorAll('.event-featured__img, .home-public__list-img, .events-cal-preview__img, .event-related-card__img').forEach(bind);
})();
</script>
