<?php
declare(strict_types=1);

/** @var string $featuredAbsolute */
/** @var string $title */
/** @var array<string, string> $T */
?>
<dialog class="event-featured-lightbox" id="event-featured-lightbox" aria-label="<?= h((string) ($T['featured_lightbox_aria'] ?? '')) ?>">
    <button type="button" class="event-featured-lightbox__close" id="event-featured-lightbox-close" aria-label="<?= h((string) ($T['featured_lightbox_close'] ?? 'Bezárás')) ?>">×</button>
    <div class="event-featured-lightbox__stage">
        <img
            class="event-featured-lightbox__img"
            id="event-featured-lightbox-img"
            src="<?= h($featuredAbsolute) ?>"
            alt="<?= h((string) $title) ?>"
            decoding="async"
        >
    </div>
</dialog>
<script>
(function () {
    var trigger = document.querySelector('.event-featured__trigger');
    var dialog = document.getElementById('event-featured-lightbox');
    var closeBtn = document.getElementById('event-featured-lightbox-close');
    if (!trigger || !dialog) return;

    function openLightbox() {
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }
        document.body.classList.add('event-featured-lightbox-open');
    }

    function closeLightbox() {
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
        document.body.classList.remove('event-featured-lightbox-open');
    }

    trigger.addEventListener('click', openLightbox);
    if (closeBtn) closeBtn.addEventListener('click', closeLightbox);
    dialog.addEventListener('click', function (e) {
        if (e.target === dialog) closeLightbox();
    });
    dialog.addEventListener('close', function () {
        document.body.classList.remove('event-featured-lightbox-open');
    });
    dialog.addEventListener('cancel', function (e) {
        e.preventDefault();
        closeLightbox();
    });
})();
</script>
