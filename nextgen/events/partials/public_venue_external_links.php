<?php
declare(strict_types=1);

/**
 * @var string $venueWebsiteUrl
 * @var string $venueGoogleMapsUrl
 * @var array<string, string> $linkLabels ['website' => ..., 'google_maps' => ...]
 */

$venueWebsiteUrl = trim((string) ($venueWebsiteUrl ?? ''));
$venueGoogleMapsUrl = trim((string) ($venueGoogleMapsUrl ?? ''));
if ($venueWebsiteUrl === '' && $venueGoogleMapsUrl === '') {
    return;
}
$labels = $linkLabels ?? [];
$websiteLabel = (string) ($labels['website'] ?? 'Weboldal');
$mapsLabel = (string) ($labels['google_maps'] ?? 'Google Maps');
?>
<div class="venue-external-links" role="group" aria-label="<?= h($websiteLabel) ?> / <?= h($mapsLabel) ?>">
    <?php if ($venueWebsiteUrl !== ''): ?>
        <a class="venue-external-links__item" href="<?= h($venueWebsiteUrl) ?>" target="_blank" rel="noopener noreferrer">
            <span class="venue-external-links__icon" aria-hidden="true">🌐</span>
            <span><?= h($websiteLabel) ?></span>
        </a>
    <?php endif; ?>
    <?php if ($venueGoogleMapsUrl !== ''): ?>
        <a class="venue-external-links__item" href="<?= h($venueGoogleMapsUrl) ?>" target="_blank" rel="noopener noreferrer">
            <span class="venue-external-links__icon" aria-hidden="true">📍</span>
            <span><?= h($mapsLabel) ?></span>
        </a>
    <?php endif; ?>
</div>
