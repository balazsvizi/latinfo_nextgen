<?php
declare(strict_types=1);

/**
 * Útvonaltervezés: egy gomb (default|inline) vagy appválasztó panel (apps).
 *
 * @var array{lat: float, lng: float}|null $venueCoords
 * @var string $addrLine
 * @var string $venueTitle
 * @var string $directionsLabel
 * @var string $directionsAria
 * @var string $directionsVariant default|inline|apps
 * @var array<string, string> $directionsAppLabels google|apple|waze|tesla|heading|tesla_hint
 */

$venueCoords = $venueCoords ?? null;
$addrLine = trim((string) ($addrLine ?? ''));
$venueTitle = trim((string) ($venueTitle ?? ''));
$directionsLabel = trim((string) ($directionsLabel ?? 'Tervezz útvonalat'));
$directionsAria = trim((string) ($directionsAria ?? $directionsLabel));
$directionsVariant = trim((string) ($directionsVariant ?? 'default'));
$appLabels = is_array($directionsAppLabels ?? null) ? $directionsAppLabels : [];

$navUrls = events_venue_navigation_app_urls($venueCoords, $addrLine, $venueTitle);
if ($navUrls === null) {
    return;
}

$googleDirectionsUrl = $navUrls['google'];
$appleDirectionsUrl = $navUrls['apple'] ?? null;
$wazeDirectionsUrl = $navUrls['waze'] ?? null;
$teslaDirectionsUrl = $navUrls['tesla'] ?? null;

$shareTextParts = array_filter([$venueTitle, $addrLine], static fn (string $p): bool => $p !== '');
$shareText = implode("\n", $shareTextParts);
if ($venueCoords !== null) {
    $coordShare = events_venue_format_coord_for_form($venueCoords['lat'])
        . ', '
        . events_venue_format_coord_for_form($venueCoords['lng']);
    if ($coordShare !== ', ') {
        $shareText = $shareText !== '' ? $shareText . "\n" . $coordShare : $coordShare;
    }
}

if ($directionsVariant === 'apps') {
    $heading = trim((string) ($appLabels['heading'] ?? 'Navigálj ide'));
    $labelGoogle = trim((string) ($appLabels['google'] ?? 'Google Maps'));
    $labelApple = trim((string) ($appLabels['apple'] ?? 'Apple Maps'));
    $labelWaze = trim((string) ($appLabels['waze'] ?? 'Waze'));
    $labelTesla = trim((string) ($appLabels['tesla'] ?? 'Tesla'));
    $teslaHint = trim((string) ($appLabels['tesla_hint'] ?? 'Megnyitás, majd megosztás a Tesla appnak'));
    $groupAria = trim((string) ($appLabels['group_aria'] ?? $heading));
    ?>
<nav class="venue-nav-apps" aria-label="<?= h($groupAria) ?>">
    <p class="venue-nav-apps__heading"><?= h($heading) ?></p>
    <ul class="venue-nav-apps__list">
        <li>
            <a
                class="venue-nav-apps__btn venue-nav-apps__btn--google"
                href="<?= h($googleDirectionsUrl) ?>"
                target="_blank"
                rel="noopener noreferrer"
            >
                <span class="venue-nav-apps__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
                </span>
                <span><?= h($labelGoogle) ?></span>
            </a>
        </li>
        <?php if ($wazeDirectionsUrl !== null): ?>
        <li>
            <a
                class="venue-nav-apps__btn venue-nav-apps__btn--waze"
                href="<?= h($wazeDirectionsUrl) ?>"
                target="_blank"
                rel="noopener noreferrer"
            >
                <span class="venue-nav-apps__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 13c.8 1.5 2.3 2.5 4 2.5s3.2-1 4-2.5"/><circle cx="9" cy="10" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="10" r="1" fill="currentColor" stroke="none"/></svg>
                </span>
                <span><?= h($labelWaze) ?></span>
            </a>
        </li>
        <?php endif; ?>
        <?php if ($appleDirectionsUrl !== null): ?>
        <li>
            <a
                class="venue-nav-apps__btn venue-nav-apps__btn--apple"
                href="<?= h($appleDirectionsUrl) ?>"
                target="_blank"
                rel="noopener noreferrer"
            >
                <span class="venue-nav-apps__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3 7h7l-5.5 4.2L18.5 22 12 17.5 5.5 22l1.9-8.8L2 9h7z"/></svg>
                </span>
                <span><?= h($labelApple) ?></span>
            </a>
        </li>
        <?php endif; ?>
        <?php if ($teslaDirectionsUrl !== null): ?>
        <li>
            <a
                class="venue-nav-apps__btn venue-nav-apps__btn--tesla"
                href="<?= h($teslaDirectionsUrl) ?>"
                target="_blank"
                rel="noopener noreferrer"
                title="<?= h($teslaHint) ?>"
                aria-label="<?= h($labelTesla . ' — ' . $teslaHint) ?>"
                data-share-title="<?= h($venueTitle !== '' ? $venueTitle : $labelTesla) ?>"
                data-share-text="<?= h($shareText) ?>"
            >
                <span class="venue-nav-apps__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 16l1.5-5.5A3 3 0 0 1 9.4 8h5.2a3 3 0 0 1 2.9 2.5L19 16"/><path d="M5 16h14"/><circle cx="8" cy="16" r="1.5"/><circle cx="16" cy="16" r="1.5"/><path d="M9 8l.5-2h5L15 8"/></svg>
                </span>
                <span><?= h($labelTesla) ?></span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<script>
(function () {
    var btn = document.querySelector('.venue-nav-apps__btn--tesla[data-share-text]');
    if (!btn || typeof navigator.share !== 'function') return;
    btn.addEventListener('click', function (e) {
        var title = btn.getAttribute('data-share-title') || '';
        var text = btn.getAttribute('data-share-text') || '';
        var url = btn.getAttribute('href') || '';
        if (!text && !url) return;
        e.preventDefault();
        navigator.share({ title: title, text: text, url: url }).catch(function () {
            window.open(url, '_blank', 'noopener,noreferrer');
        });
    });
})();
</script>
    <?php
    return;
}

$directionsClass = 'venue-directions' . ($directionsVariant === 'inline' ? ' venue-directions--inline' : '');
$directionsBtnClass = 'venue-directions__btn' . ($directionsVariant === 'inline' ? ' venue-directions__btn--inline' : '');
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
