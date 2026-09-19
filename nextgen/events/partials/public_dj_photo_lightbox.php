<?php
declare(strict_types=1);

/**
 * DJ profilkép lightbox (teljes méret, bezárható).
 *
 * @var string $djLightboxSrc
 * @var string $djLightboxAlt
 * @var array<string, string> $G
 */

$djLightboxSrc = trim((string) ($djLightboxSrc ?? ''));
$djLightboxAlt = trim((string) ($djLightboxAlt ?? ''));
if ($djLightboxSrc === '') {
    return;
}
?>
<dialog class="event-featured-lightbox" id="dj-photo-lightbox" aria-label="<?= h((string) ($G['dj_photo_lightbox_aria'] ?? '')) ?>">
    <button type="button" class="event-featured-lightbox__close" id="dj-photo-lightbox-close" aria-label="<?= h((string) ($G['dj_photo_lightbox_close'] ?? 'Bezárás')) ?>">×</button>
    <div class="event-featured-lightbox__stage">
        <img
            class="event-featured-lightbox__img"
            id="dj-photo-lightbox-img"
            src="<?= h($djLightboxSrc) ?>"
            alt="<?= h($djLightboxAlt) ?>"
            decoding="async"
        >
    </div>
</dialog>
<script>
(function () {
    var trigger = document.querySelector('.dj-public__avatar-trigger');
    var dialog = document.getElementById('dj-photo-lightbox');
    var closeBtn = document.getElementById('dj-photo-lightbox-close');
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
