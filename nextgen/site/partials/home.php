<?php
declare(strict_types=1);

/**
 * @var array<string, string> $settings
 * @var array<string, string> $H
 * @var list<array<string, mixed>> $quickNews
 * @var list<array<string, mixed>> $collectors
 * @var array<string, mixed> $dayEvents
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
 * @var string $heroTitle
 * @var string $heroDek
 * @var string $heroKicker
 * @var string $heroUrl
 * @var string $heroCta
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
    </header>

    <div class="latinfo-home__stage">
        <div class="latinfo-home__intro">
            <div class="latinfo-home__intro-copy">
                <?php if ($heroKicker !== ''): ?>
                    <p class="latinfo-home__kicker"><?= h($heroKicker) ?></p>
                <?php endif; ?>
                <h1 class="latinfo-home__title"><?= h($heroTitle) ?></h1>
                <?php if ($heroDek !== ''): ?>
                    <p class="latinfo-home__intro-lead"><?= h($heroDek) ?></p>
                <?php endif; ?>
            </div>
            <a class="event-cta latinfo-home__intro-cta" href="<?= h($heroUrl) ?>"><?= h($heroCta) ?></a>
        </div>

        <div class="latinfo-home__board">
            <div class="latinfo-home__calendar" id="naptar">
                <?php
                $heading = latinfo_home_day_label($dayEvents['today_date'], $lang, $H['today']);
                $sectionId = 'lh-today';
                $events = $dayEvents['today'];
                $hasMore = $dayEvents['today_more'];
                $empty = $H['empty_today'];
                $moreLabel = $H['more'];
                $allDayLabel = $H['all_day'];
                require __DIR__ . '/home_day_column.php';

                $heading = latinfo_home_day_label($dayEvents['tomorrow_date'], $lang, $H['tomorrow']);
                $sectionId = 'lh-tomorrow';
                $events = $dayEvents['tomorrow'];
                $hasMore = $dayEvents['tomorrow_more'];
                $empty = $H['empty_tomorrow'];
                require __DIR__ . '/home_day_column.php';
                ?>
            </div>

            <div class="latinfo-home__rail">
                <section class="latinfo-home__flashes-wrap" id="hirek" aria-labelledby="lh-news-title">
                    <div class="latinfo-home__rail-head">
                        <h2 class="latinfo-home__rail-title" id="lh-news-title"><?= h($H['quick_news']) ?></h2>
                        <a class="latinfo-home__edit" href="<?= h(latinfo_home_edit_url('tab=news')) ?>"><?= h($H['edit_news']) ?></a>
                    </div>
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
                                $itemDek = latinfo_home_clip(trim((string) ($item['dek'] ?? '')), 88);
                                ?>
                                <li role="listitem">
                                    <a class="latinfo-home__flash" href="<?= h($itemUrl) ?>">
                                        <span class="latinfo-home__flash-body">
                                            <?php if ($itemKicker !== ''): ?>
                                                <span class="latinfo-home__flash-kicker"><?= h($itemKicker) ?></span>
                                            <?php endif; ?>
                                            <span class="latinfo-home__flash-title"><?= h((string) ($item['title'] ?? '')) ?></span>
                                            <?php if ($itemDek !== ''): ?>
                                                <span class="latinfo-home__flash-dek"><?= h($itemDek) ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="latinfo-home__flash-go" aria-hidden="true">→</span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <?php if ($spotlightVisible !== []): ?>
                    <?php require $eventsPartial . '/public_dj_spotlight.php'; ?>
                    <p class="latinfo-home__rail-foot">
                        <a class="latinfo-home__edit" href="<?= h($djsUrl) ?>"><?= h($H['djs_all']) ?></a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="home-public__main latinfo-home__main">
        <section class="latinfo-home__section" id="gyujtok" aria-labelledby="lh-collectors-title">
            <div class="latinfo-home__section-head">
                <h2 class="latinfo-home__heading" id="lh-collectors-title"><?= h($H['collectors']) ?></h2>
                <a class="latinfo-home__edit" href="<?= h(latinfo_home_edit_url('tab=collectors')) ?>"><?= h($H['edit_collectors']) ?></a>
            </div>
            <?php if ($collectors === []): ?>
                <p class="home-public__empty"><?= h($H['collectors_empty']) ?></p>
            <?php else: ?>
                <ul class="latinfo-home__collectors" role="list">
                    <?php foreach ($collectors as $collector): ?>
                        <?php
                        $cUrl = trim((string) ($collector['url'] ?? ''));
                        if ($cUrl === '') {
                            $cUrl = $calendarUrl;
                        }
                        $cImage = latinfo_home_media_src((string) ($collector['image_url'] ?? ''));
                        $cAccent = normalize_hex_color((string) ($collector['accent_color'] ?? ''), '#6D8F63');
                        $cSub = trim((string) ($collector['subtitle'] ?? ''));
                        ?>
                        <li role="listitem">
                            <a class="latinfo-home__collector" href="<?= h($cUrl) ?>" style="--home-event-accent: <?= h($cAccent) ?>">
                                <div class="home-public__list-media">
                                    <?php if ($cImage !== ''): ?>
                                        <img class="home-public__list-img" src="<?= h($cImage) ?>" alt="" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <div class="home-public__list-placeholder" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.25"><path d="M4 7h16M4 12h10M4 17h13"/></svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="home-public__list-body">
                                    <?php if ($cSub !== ''): ?>
                                        <span class="home-public__list-date"><?= h($cSub) ?></span>
                                    <?php endif; ?>
                                    <span class="home-public__list-name"><?= h((string) ($collector['title'] ?? '')) ?></span>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <?php if ($settings['newsletter_title'] !== '' || $settings['newsletter_lead'] !== ''): ?>
            <section class="latinfo-home__cta" aria-labelledby="lh-cta-title">
                <div>
                    <h2 class="latinfo-home__heading" id="lh-cta-title"><?= h($settings['newsletter_title']) ?></h2>
                    <?php if ($settings['newsletter_lead'] !== ''): ?>
                        <p class="latinfo-home__lead"><?= h($settings['newsletter_lead']) ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($settings['newsletter_cta_label'] !== '' && $settings['newsletter_cta_url'] !== ''): ?>
                    <a class="event-cta" href="<?= h($settings['newsletter_cta_url']) ?>"><?= h($settings['newsletter_cta_label']) ?></a>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>

    <?php require $eventsPartial . '/public_shell_footer.php'; ?>
</article>
</div>
<?php require $eventsPartial . '/public_dj_spotlight_script.php'; ?>
</body>
</html>
