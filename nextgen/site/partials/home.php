<?php
declare(strict_types=1);

/**
 * @var array<string, string> $H
 * @var list<array<string, mixed>> $quickNews
 * @var array<string, mixed> $dayEvents
 * @var array<int, list<array{color?: string}>> $categoriesByEventId
 * @var list<array<string, mixed>> $spotlightCards
 * @var list<array<string, mixed>> $spotlightVisible
 * @var int $spotlightMobileCount
 * @var array<string, string> $Dj
 * @var string $cmsAnchorSpotlight
 * @var string $calendarUrl
 * @var string $djsUrl
 * @var string $editUrl
 * @var string $cssPublicUrl
 * @var string $cssHomeUrl
 * @var int $homeSkin
 * @var array<int, string> $skinUrls
 * @var string $lang
 * @var string $htmlLang
 * @var array<string, string> $S
 * @var string $urlHu
 * @var string $urlEn
 * @var bool $isEventsHome
 * @var bool $showAdminEdit
 * @var string $adminEditUrl
 * @var list<array<string, mixed>> $adminFloatTools
 */
$eventsPartial = dirname(__DIR__, 2) . '/events/partials';
$D = $Dj;
$categoriesByEventId = is_array($categoriesByEventId ?? null) ? $categoriesByEventId : [];
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
<body class="event-public-page event-public-page--home event-public-page--latinfo-home latinfo-home--skin-<?= (int) $homeSkin ?>">
<?php require $eventsPartial . '/admin_float_tools.php'; ?>
<?php require __DIR__ . '/home_skin_switcher.php'; ?>
<div class="event-shell">
<article class="event-public home-public latinfo-home">
    <header class="event-public__hero event-public__hero--bar-only">
        <?php require $eventsPartial . '/public_shell_hero_bar.php'; ?>
        <h1 class="visually-hidden"><?= h(SITE_NAME) ?></h1>
    </header>

    <div class="latinfo-home__stage">
        <div class="latinfo-home__board">
            <div class="latinfo-home__rail">
                <section class="latinfo-home__flashes-wrap" id="hirek" aria-labelledby="lh-news-title">
                    <header class="latinfo-home__day-head">
                        <h2 class="latinfo-home__day-title" id="lh-news-title">
                            <span class="latinfo-home__day-word"><?= h($H['quick_news']) ?></span>
                        </h2>
                    </header>
                    <?php if ($quickNews === []): ?>
                        <p class="latinfo-home__day-empty"><?= h($H['empty_news']) ?></p>
                    <?php else: ?>
                        <ul class="latinfo-home__flashes" role="list" aria-label="<?= h($H['quick_news_aria']) ?>">
                            <?php foreach ($quickNews as $item): ?>
                                <?php
                                $itemUrl = trim((string) ($item['url'] ?? ''));
                                if ($itemUrl === '') {
                                    $itemUrl = $calendarUrl;
                                }
                                $itemKicker = trim((string) ($item['kicker'] ?? ''));
                                $itemDek = trim((string) ($item['dek'] ?? ''));
                                ?>
                                <li role="listitem">
                                    <a class="latinfo-home__flash" href="<?= h($itemUrl) ?>">
                                        <?php if ($itemKicker !== ''): ?>
                                            <span class="latinfo-home__flash-kicker"><?= h($itemKicker) ?></span>
                                        <?php endif; ?>
                                        <span class="latinfo-home__flash-title"><?= h((string) ($item['title'] ?? '')) ?></span>
                                        <?php if ($itemDek !== ''): ?>
                                            <span class="latinfo-home__flash-dek"><?= h($itemDek) ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <?php if ($spotlightVisible !== []): ?>
                    <?php require $eventsPartial . '/public_dj_spotlight.php'; ?>
                    <p class="latinfo-home__rail-foot">
                        <a class="latinfo-home__edit" href="<?= h($djsUrl) ?>">
                            <span><?= h($H['djs_all']) ?></span>
                            <span class="latinfo-home__day-more-arrow" aria-hidden="true">→</span>
                        </a>
                    </p>
                <?php endif; ?>
            </div>

            <div class="latinfo-home__calendar" id="naptar">
                <?php
                $dayWord = $H['today'];
                $dayDate = latinfo_home_day_date($dayEvents['today_date'], $lang, $homeSkin);
                $sectionId = 'lh-today';
                $events = $dayEvents['today'];
                $hasMore = $dayEvents['today_more'];
                $empty = $H['empty_today'];
                $moreLabel = $H['more'];
                require __DIR__ . '/home_day_column.php';

                $dayWord = $H['tomorrow'];
                $dayDate = latinfo_home_day_date($dayEvents['tomorrow_date'], $lang, $homeSkin);
                $sectionId = 'lh-tomorrow';
                $events = $dayEvents['tomorrow'];
                $hasMore = $dayEvents['tomorrow_more'];
                $empty = $H['empty_tomorrow'];
                require __DIR__ . '/home_day_column.php';
                ?>
            </div>
        </div>
    </div>

    <?php require $eventsPartial . '/public_shell_footer.php'; ?>
</article>
</div>
<?php require $eventsPartial . '/public_dj_spotlight_script.php'; ?>
</body>
</html>
