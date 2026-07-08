<?php
declare(strict_types=1);

/**
 * Lebegő mini eszköztár — esemény szerkesztő (bal felső sarok).
 *
 * @var string $eventEditBackCalendarUrl Admin naptár
 * @var string $eventEditPublicCalendarUrl Nyilvános naptár (aktuális hónap)
 * @var string $eventEditCopyUrl Másolás
 * @var string|null $eventEditPreviewUrl Nyilvános megtekintés (közzétett esemény)
 */
$eventEditPreviewUrl = $eventEditPreviewUrl ?? null;
?>
<nav class="events-edit-float-tools" aria-label="Gyors műveletek">
    <?php if ($eventEditPreviewUrl !== null && $eventEditPreviewUrl !== ''): ?>
        <a
            href="<?= h($eventEditPreviewUrl) ?>"
            class="events-icon-action events-edit-float-tools__btn"
            title="Nyilvános megtekintés (új lap)"
            aria-label="Nyilvános megtekintés új lapon"
            target="_blank"
            rel="noopener"
        >
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
        </a>
    <?php endif; ?>
    <a
        href="<?= h($eventEditCopyUrl) ?>"
        class="events-icon-action events-edit-float-tools__btn"
        title="Esemény másolása"
        aria-label="Esemény másolása"
    >
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" stroke="currentColor" stroke-width="2"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
    </a>
    <a
        href="<?= h($eventEditBackCalendarUrl) ?>"
        class="events-icon-action events-edit-float-tools__btn"
        title="Vissza az admin naptárhoz"
        aria-label="Vissza az admin naptárhoz"
    >
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M19 12H5"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 19l-7-7 7-7"/></svg>
    </a>
    <a
        href="<?= h($eventEditPublicCalendarUrl) ?>"
        class="events-icon-action events-edit-float-tools__btn"
        title="Nyilvános naptár megtekintése (aktuális hónap, új lap)"
        aria-label="Nyilvános naptár megtekintése az esemény hónapjában új lapon"
        target="_blank"
        rel="noopener"
    >
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18"/></svg>
    </a>
</nav>
