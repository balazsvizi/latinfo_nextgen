<?php
declare(strict_types=1);

/**
 * Közös nyilvános esemény lábléc.
 *
 * @var string $lang
 * @var array<string, string> $S footer_home_link kulccsal
 * @var bool $isEventsHome Alapból false
 * @var bool $standalone Önálló (404) elrendezés
 */
$isEventsHome = $isEventsHome ?? false;
$standalone = $standalone ?? false;
$C = events_public_common_nav_strings($lang);
$eventsHomeUrl = events_public_home_page_url($lang);
$latinfoHomeUrl = LATINFO_PUBLIC_HOME_URL;
$footerClass = 'event-site-line' . ($standalone ? ' event-site-line--standalone' : '');
?>
<p class="<?= h($footerClass) ?>">
    <?php if (!$isEventsHome): ?>
        <a href="<?= h($eventsHomeUrl) ?>"><?= h($C['events_home_link']) ?></a>
        <span class="event-site-line__sep" aria-hidden="true">·</span>
    <?php endif; ?>
    <a href="<?= h($latinfoHomeUrl) ?>"><?= h($S['footer_home_link']) ?></a>
</p>
