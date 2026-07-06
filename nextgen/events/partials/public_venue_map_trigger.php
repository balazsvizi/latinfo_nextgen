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
        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
        <circle cx="12" cy="10" r="3"/>
    </svg>
</button>
