<?php
declare(strict_types=1);

/**
 * @var array<string, string> $H
 * @var list<array<string, mixed>> $enabledModules
 * @var list<array<string, mixed>> $quickNews
 * @var array<string, mixed> $dayEvents
 * @var array<int, list<array{color?: string}>> $categoriesByEventId
 * @var list<array<string, mixed>> $spotlightCards
 * @var list<array<string, mixed>> $spotlightVisible
 * @var int $spotlightMobileCount
 * @var array<string, string> $Dj
 * @var string $cmsAnchorSpotlight
 * @var array<string, string> $ratingStrings
 * @var string $ratingAjaxUrl
 * @var string $calendarUrl
 * @var string $djsUrl
 * @var string $editUrl
 * @var string $cssPublicUrl
 * @var string $cssHomeUrl
 * @var string $lang
 * @var string $htmlLang
 * @var array<string, string> $S
 * @var string $urlHu
 * @var string $urlEn
 * @var bool $isEventsHome
 * @var bool $showAdminEdit
 * @var string $adminEditUrl
 * @var list<array<string, mixed>> $adminFloatTools
 * @var array<int, array<string, mixed>> $calendarPreviewById
 * @var bool $lhModuleTrackAllowed
 */
$eventsPartial = dirname(__DIR__, 2) . '/events/partials';
$D = $Dj;
$categoriesByEventId = is_array($categoriesByEventId ?? null) ? $categoriesByEventId : [];
$calendarPreviewById = is_array($calendarPreviewById ?? null) ? $calendarPreviewById : [];
$enabledModules = is_array($enabledModules ?? null) ? $enabledModules : [];
$lhModuleTrackAllowed = !empty($lhModuleTrackAllowed);
?>
<!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#6d8f63">
    <?= events_public_robots_noindex_head_markup() ?>
    <title><?= h(SITE_NAME) ?> – <?= h($H['page_title']) ?></title>
    <?= events_public_favicon_head_markup() ?>
    <link rel="stylesheet" href="<?= h($cssPublicUrl) ?>">
    <link rel="stylesheet" href="<?= h($cssHomeUrl) ?>">
</head>
<body class="event-public-page event-public-page--home event-public-page--latinfo-home">
<?php require $eventsPartial . '/admin_float_tools.php'; ?>
<div class="event-shell">
<article class="event-public home-public latinfo-home">
    <header class="event-public__hero event-public__hero--bar-only">
        <?php require $eventsPartial . '/public_shell_hero_bar.php'; ?>
        <h1 class="visually-hidden"><?= h(SITE_NAME) ?></h1>
        <?php require __DIR__ . '/home_calendar_cta.php'; ?>
    </header>

    <div class="latinfo-home__stage">
        <div class="latinfo-home__board">
            <?php foreach ($enabledModules as $modIndex => $mod): ?>
                <?php
                $modKey = (string) ($mod['module_key'] ?? '');
                $column = (string) ($mod['column'] ?? 'rail');
                $orderStyle = 'order:' . (int) $modIndex;
                ?>
                <div
                    class="latinfo-home__module latinfo-home__module--<?= h($modKey) ?> latinfo-home__module--col-<?= h($column) ?>"
                    style="<?= h($orderStyle) ?>"
                    data-module="<?= h($modKey) ?>"
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
                            require $eventsPartial . '/public_dj_spotlight.php';
                            ?>
                        <?php endif; ?>
                    <?php elseif ($modKey === 'rating'): ?>
                        <?php require __DIR__ . '/home_module_rating.php'; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php require $eventsPartial . '/public_shell_footer.php'; ?>
</article>
</div>
<?php require $eventsPartial . '/public_dj_spotlight_script.php'; ?>
<?php require $eventsPartial . '/event_image_orientation_script.php'; ?>
<?php require __DIR__ . '/home_module_track_script.php'; ?>
<?php if ($calendarPreviewById !== []): ?>
<?php
$D = $S;
require $eventsPartial . '/public_calendar_event_preview.php';
?>
<?php endif; ?>
</body>
</html>
