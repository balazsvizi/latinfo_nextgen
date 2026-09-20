<?php
declare(strict_types=1);

/**
 * Egy kezdőoldal-modul kirajzolása.
 *
 * @var array<string, mixed> $mod
 * @var array<string, string> $H
 * @var list<array<string, mixed>> $quickNews
 * @var array<string, mixed> $dayEvents
 * @var array<int, list<array{color?: string}>> $categoriesByEventId
 * @var list<array<string, mixed>> $spotlightVisible
 * @var list<array<string, mixed>> $spotlightCards
 * @var int $spotlightMobileCount
 * @var array<string, string> $Dj
 * @var string $cmsAnchorSpotlight
 * @var array<string, string> $ratingStrings
 * @var string $ratingAjaxUrl
 * @var array{average?: float, count?: int} $ratingSummary
 * @var array{title: string, lead: string, cta_label: string, cta_url: string, note: string, show_icon: bool, is_external: bool, configured: bool} $donablyView
 * @var string $calendarUrl
 * @var string $djsUrl
 * @var string $lang
 * @var string $eventsPartial
 */
$modKey = (string) ($mod['module_key'] ?? '');
if ($modKey === '') {
    return;
}
$mobileOrder = (int) ($mobileOrderIndex[$modKey] ?? ($mod['sort_order_mobile'] ?? 99));
?>
<div
    class="latinfo-home__module latinfo-home__module--<?= h($modKey) ?>"
    data-module="<?= h($modKey) ?>"
    style="--lh-mobile-order: <?= $mobileOrder ?>"
>
    <?php if ($modKey === 'announcements'): ?>
        <?php require __DIR__ . '/home_module_announcements.php'; ?>
    <?php elseif ($modKey === 'today'): ?>
        <?php
        $dayWord = $H['today'];
        $dayDate = latinfo_home_day_date($dayEvents['today_date'], $lang);
        $sectionId = 'lh-today';
        $events = $dayEvents['today'];
        $hasMore = $dayEvents['today_more'];
        $empty = $H['empty_today'];
        $moreLabel = $H['more'];
        $homeDayModuleKey = 'today';
        require __DIR__ . '/home_day_column.php';
        ?>
    <?php elseif ($modKey === 'tomorrow'): ?>
        <?php
        $dayWord = $H['tomorrow'];
        $dayDate = latinfo_home_day_date($dayEvents['tomorrow_date'], $lang);
        $sectionId = 'lh-tomorrow';
        $events = $dayEvents['tomorrow'];
        $hasMore = $dayEvents['tomorrow_more'];
        $empty = $H['empty_tomorrow'];
        $moreLabel = $H['more'];
        $homeDayModuleKey = 'tomorrow';
        require __DIR__ . '/home_day_column.php';
        ?>
    <?php elseif ($modKey === 'dj_spotlight'): ?>
        <?php if ($spotlightVisible !== []): ?>
            <?php
            $spotlightMoreHref = $djsUrl;
            $spotlightMoreLabel = $H['djs_all'];
            $spotlightTrackModule = 'dj_spotlight';
            $D = $Dj;
            require $eventsPartial . '/public_dj_spotlight.php';
            ?>
        <?php endif; ?>
    <?php elseif ($modKey === 'rating'): ?>
        <?php require __DIR__ . '/home_module_rating.php'; ?>
    <?php elseif ($modKey === 'donably'): ?>
        <?php require __DIR__ . '/home_module_donably.php'; ?>
    <?php endif; ?>
</div>
