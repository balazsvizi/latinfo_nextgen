<?php
declare(strict_types=1);

/**
 * @var array{lat: float, lng: float}|null $venueCoords
 * @var string $addrLine
 * @var string $venueTitle
 * @var string $directionsLabel
 * @var string $directionsAria
 * @var string $directionsVariant default|inline
 */

$venueCoords = $venueCoords ?? null;
$addrLine = trim((string) ($addrLine ?? ''));
$venueTitle = trim((string) ($venueTitle ?? ''));
$directionsLabel = trim((string) ($directionsLabel ?? 'Tervezz útvonalat'));
$directionsAria = trim((string) ($directionsAria ?? $directionsLabel));
$directionsVariant = trim((string) ($directionsVariant ?? 'default'));
$directionsClass = 'venue-directions' . ($directionsVariant === 'inline' ? ' venue-directions--inline' : '');
$directionsBtnClass = 'venue-directions__btn' . ($directionsVariant === 'inline' ? ' venue-directions__btn--inline' : '');

$destination = events_venue_directions_destination($venueCoords, $addrLine, $venueTitle);
$googleDirectionsUrl = events_venue_google_directions_url($destination);
$appleDirectionsUrl = events_venue_apple_directions_url($destination);
if ($googleDirectionsUrl === null) {
    return;
}
?>
<div class="<?= h($directionsClass) ?>">
    <a
        class="<?= h($directionsBtnClass) ?>"
        href="<?= h($googleDirectionsUrl) ?>"
        <?php if ($appleDirectionsUrl !== null): ?>data-apple-href="<?= h($appleDirectionsUrl) ?>"<?php endif; ?>
        target="_blank"
        rel="noopener noreferrer"
        aria-label="<?= h($directionsAria) ?>"
    >
        <span class="venue-directions__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
        </span>
        <span class="venue-directions__label"><?= h($directionsLabel) ?></span>
    </a>
</div>
<script>
(function () {
    var btn = document.querySelector('.venue-directions__btn[data-apple-href]');
    if (!btn) return;
    var ua = navigator.userAgent || '';
    var isAppleMobile = /iPad|iPhone|iPod/i.test(ua)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (isAppleMobile) {
        btn.setAttribute('href', btn.getAttribute('data-apple-href') || btn.getAttribute('href'));
    }
})();
</script>
