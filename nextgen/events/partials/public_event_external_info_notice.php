<?php
declare(strict_types=1);

/**
 * További információ URL – tájékoztató a leírás doboz tetején / alján.
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
?>
<p class="event-external-info-notice event-external-info-notice--<?= h($placement) ?>" role="note">
    <?= h((string) ($T['external_info_notice_prefix'] ?? '')) ?>
    <a
        class="event-external-info-notice__link"
        href="<?= h($eventExternalUrl) ?>"
        target="_blank"
        rel="noopener noreferrer"
    ><?= h((string) ($T['external_info_notice_link'] ?? '')) ?></a><?= h((string) ($T['external_info_notice_suffix'] ?? '')) ?>
</p>
