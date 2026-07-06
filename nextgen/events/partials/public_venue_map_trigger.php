<?php
declare(strict_types=1);

/**
 * Térkép megnyitó ikon (popup trigger).
 *
 * @var string $mapDialogId
 * @var string $mapShowAria
 */

$mapDialogId = trim((string) ($mapDialogId ?? 'event-venue-map-dialog'));
$mapShowAria = trim((string) ($mapShowAria ?? 'Térkép megnyitása'));
?>
<button
    type="button"
    class="event-venue-map-trigger"
    data-venue-map-open="<?= h($mapDialogId) ?>"
    aria-label="<?= h($mapShowAria) ?>"
    aria-haspopup="dialog"
>
    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polygon points="1 6 8 3 16 6 23 3 23 18 16 21 8 18 1 21 1 6"/>
        <line x1="8" y1="3" x2="8" y2="18"/>
        <line x1="16" y1="6" x2="16" y2="21"/>
    </svg>
</button>
