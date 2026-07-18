<?php
declare(strict_types=1);

/**
 * További információ URL – tájékoztató a leírás doboz tetején / alján.
 *
 * Felül: aktuális infó felhívás. Alul: felelősségvállalás (mindkettőben link az esemény oldalára).
 *
 * @var array<string, string> $T
 * @var string $eventExternalUrl
 * @var string $placement top|bottom
 */
$eventExternalUrl = trim((string) ($eventExternalUrl ?? ''));
if ($eventExternalUrl === '') {
    return;
}
$placement = ($placement ?? 'top') === 'bottom' ? 'bottom' : 'top';

if ($placement === 'bottom') {
    $prefix = (string) ($T['external_info_disclaimer_prefix'] ?? '');
    $linkLabel = (string) ($T['external_info_disclaimer_link'] ?? '');
    $suffix = (string) ($T['external_info_disclaimer_suffix'] ?? '');
    $class = 'event-external-info-notice event-external-info-notice--bottom event-external-info-notice--disclaimer';
} else {
    $prefix = (string) ($T['external_info_notice_prefix'] ?? '');
    $linkLabel = (string) ($T['external_info_notice_link'] ?? '');
    $suffix = (string) ($T['external_info_notice_suffix'] ?? '');
    $class = 'event-external-info-notice event-external-info-notice--top';
}
?>
<p class="<?= h($class) ?>" role="note">
    <?= h($prefix) ?>
    <a
        class="event-external-info-notice__link"
        href="<?= h($eventExternalUrl) ?>"
        target="_blank"
        rel="noopener noreferrer"
    ><?= h($linkLabel) ?></a><?= h($suffix) ?>
</p>
