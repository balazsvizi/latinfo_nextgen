<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_public_lang.php';
require_once __DIR__ . '/lib/public_home_content.php';
require_once __DIR__ . '/lib/public_event_filters.php';
require_once __DIR__ . '/lib/public_event_calendar.php';

$lang = events_public_resolve_megjelenit_lang();
$D = events_public_home_strings($lang);

$db = getDb();
$homeContent = events_public_home_load($db);
$filters = events_public_filters_from_request($db);
$filtersActive = events_public_filters_are_active($filters);
$view = (string) ($filters['view'] ?? 'cal');

[$monthFirst, $monthLast, $monthKey] = events_admin_calendar_resolve_month((string) ($_GET['month'] ?? ''));
$prevMonthKey = $monthFirst->modify('-1 month')->format('Y-m');
$nextMonthKey = $monthFirst->modify('+1 month')->format('Y-m');
$monthLabel = events_public_calendar_month_label($monthFirst, $lang);

$rows = events_public_fetch_filtered_events($db, $filters);
$categoriesByEventId = events_public_load_categories_by_event_id($db, $rows);

$bucket = events_admin_calendar_bucket_events($rows, $monthFirst, $monthLast);
$byDay = $bucket['byDay'];
$undated = $bucket['undated'];
$gridDays = events_admin_calendar_grid_days($monthFirst, $monthLast);
$weekdayHeaders = events_public_calendar_weekday_headers($lang);

$navBaseParams = $filters['get_params'];
unset($navBaseParams['view']);
$prevMonthUrl = events_public_calendar_month_url($prevMonthKey, $navBaseParams);
$nextMonthUrl = events_public_calendar_month_url($nextMonthKey, $navBaseParams);
$todayMonthUrl = events_public_calendar_month_url((new DateTimeImmutable('today'))->format('Y-m'), $navBaseParams);

$listViewParams = $filters['get_params'];
$listViewParams['view'] = 'list';
$homeScript = events_public_home_page_script();
$listViewUrl = events_url($homeScript . '?' . http_build_query($listViewParams));

$calViewParams = $filters['get_params'];
unset($calViewParams['view']);
$calViewParams['month'] = $monthKey;
$calViewUrl = events_url($homeScript . '?' . http_build_query($calViewParams));

$filterFormAction = events_url($homeScript);
$filterFormHidden = ['month' => $monthKey];
if ($view === 'list') {
    $filterFormHidden['view'] = 'list';
}
$filterClearParams = ['month' => $monthKey];
if ($lang !== 'hu') {
    $filterClearParams['lang'] = $lang;
}
$filterClearUrl = events_url($homeScript . '?' . http_build_query($filterClearParams));

$title = (string) $D['page_title'];
$desc = (string) $D['page_desc'];
$canonical = events_absolute_url(events_public_home_page_url($lang));
$ogPageUrl = $canonical;
$cssUrl = events_url('assets/event_public.css');
$urlHu = events_public_home_lang_switch_url('hu');
$urlEn = events_public_home_lang_switch_url('en');
$htmlLang = $lang === 'en' ? 'en' : 'hu';
$latinfoHomeUrl = LATINFO_PUBLIC_HOME_URL;
$latinfoLogoSrc = site_url('lanueva/assets/images/logo/latinfo_black.png');
$contentTop = trim((string) ($homeContent['content_top'] ?? ''));
$contentBottom = trim((string) ($homeContent['content_bottom'] ?? ''));

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#6d8f63">
    <title><?= h($title) ?><?= h($D['html_title_suffix']) ?><?= h(SITE_NAME) ?></title>
    <meta name="description" content="<?= h($desc) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
    <meta property="og:title" content="<?= h($title) ?>">
    <meta property="og:description" content="<?= h($desc) ?>">
    <meta property="og:url" content="<?= h($ogPageUrl) ?>">
    <link rel="canonical" href="<?= h($canonical) ?>">
    <link rel="alternate" hreflang="hu" href="<?= h(events_absolute_url(events_public_home_page_url('hu'))) ?>">
    <link rel="alternate" hreflang="en" href="<?= h(events_absolute_url(events_public_home_page_url('en'))) ?>">
    <link rel="alternate" hreflang="x-default" href="<?= h(events_absolute_url(events_public_home_page_url('hu'))) ?>">
    <?= events_public_favicon_head_markup() ?>
    <link rel="stylesheet" href="<?= h($cssUrl) ?>">
</head>
<body class="event-public-page event-public-page--home">
<div class="event-shell">
    <div class="event-shell-toolbar">
        <div class="event-shell-toolbar__leading">
            <a class="event-brand-logo" href="<?= h($latinfoHomeUrl) ?>" title="<?= h($D['logo_home_title']) ?>" aria-label="<?= h($D['logo_home_aria']) ?>">
                <img src="<?= h($latinfoLogoSrc) ?>" alt="<?= h($D['logo_alt']) ?>" width="180" height="48" decoding="async" fetchpriority="high">
            </a>
        </div>
        <div class="event-lang-switch" role="navigation" aria-label="<?= h($D['lang_nav']) ?>">
            <a class="event-lang-switch__link<?= $lang === 'hu' ? ' is-active' : '' ?>" href="<?= h($urlHu) ?>" hreflang="hu" lang="hu"><?= h($D['lang_hu']) ?></a>
            <span class="event-lang-switch__sep" aria-hidden="true">|</span>
            <a class="event-lang-switch__link<?= $lang === 'en' ? ' is-active' : '' ?>" href="<?= h($urlEn) ?>" hreflang="en" lang="en"><?= h($D['lang_en']) ?></a>
        </div>
    </div>

<article class="event-public home-public">
    <header class="event-public__hero">
        <div class="event-public__hero-inner">
            <p class="event-public__eyebrow">📅 <?= h((string) $D['eyebrow']) ?></p>
            <h1 class="event-public__title"><?= h($title) ?></h1>
        </div>
    </header>

    <?php if ($contentTop !== ''): ?>
        <div class="home-public__cms home-public__cms--top event-rich-text">
            <?= $contentTop ?>
        </div>
    <?php endif; ?>

    <section class="home-public__main" aria-label="<?= h((string) $D['calendar_aria']) ?>">
        <form method="get" action="<?= h($filterFormAction) ?>" class="home-public__form" id="events-home-filter-form">
            <details class="home-public__filters-panel" id="home-filters-panel"<?= $filtersActive ? ' open' : '' ?>>
                <summary class="home-public__filters-summary">
                    <span class="home-public__filters-summary-text"><?= h((string) $D['filters_toggle']) ?></span>
                    <?php if ($filtersActive): ?>
                        <span class="home-public__filters-badge"><?= h((string) $D['filters_active_badge']) ?></span>
                    <?php endif; ?>
                </summary>
                <div class="home-public__filters-body">
                    <?php if ($filtersActive): ?>
                        <p class="home-public__filters-actions">
                            <a href="<?= h($filterClearUrl) ?>" class="home-public__clear-filters"><?= h((string) $D['clear_filters']) ?></a>
                        </p>
                    <?php endif; ?>
                    <?php require __DIR__ . '/partials/public_event_filters.php'; ?>
                </div>
            </details>

            <?php if ($view === 'cal'): ?>
                <div class="events-cal-toolbar" aria-label="<?= h((string) $D['cal_controls_aria']) ?>">
                    <div class="events-cal-toolbar__left">
                        <div class="events-cal-toolbar__nav" aria-label="<?= h((string) $D['month_nav_aria']) ?>">
                            <a class="events-cal-toolbar__arrow" href="<?= h($prevMonthUrl) ?>" rel="prev" aria-label="<?= h((string) $D['prev_month']) ?>">‹</a>
                            <a class="events-cal-toolbar__today" href="<?= h($todayMonthUrl) ?>"><?= h((string) $D['this_month']) ?></a>
                            <a class="events-cal-toolbar__arrow" href="<?= h($nextMonthUrl) ?>" rel="next" aria-label="<?= h((string) $D['next_month']) ?>">›</a>
                        </div>
                        <h2 class="events-cal-toolbar__month"><?= h($monthLabel) ?></h2>
                    </div>
                    <nav class="events-cal-view-switch" aria-label="<?= h((string) $D['view_switch_aria']) ?>">
                        <span class="events-cal-view-switch__item is-active" aria-current="page"><?= h((string) $D['view_cal']) ?></span>
                        <a class="events-cal-view-switch__item" href="<?= h($listViewUrl) ?>"><?= h((string) $D['view_list']) ?></a>
                    </nav>
                </div>
                <?php require __DIR__ . '/partials/public_calendar_grid.php'; ?>
            <?php else: ?>
                <nav class="events-cal-view-switch events-cal-view-switch--standalone" aria-label="<?= h((string) $D['view_switch_aria']) ?>">
                    <a class="events-cal-view-switch__item" href="<?= h($calViewUrl) ?>"><?= h((string) $D['view_cal']) ?></a>
                    <span class="events-cal-view-switch__item is-active" aria-current="page"><?= h((string) $D['view_list']) ?></span>
                </nav>
                <h2 class="home-public__list-heading"><?= h((string) $D['list_heading']) ?> (<?= count($rows) ?>)</h2>
                <?php require __DIR__ . '/partials/public_event_list.php'; ?>
            <?php endif; ?>
        </form>
    </section>

    <?php if ($contentBottom !== ''): ?>
        <div class="home-public__cms home-public__cms--bottom event-rich-text">
            <?= $contentBottom ?>
        </div>
    <?php endif; ?>
</article>
</div>
<?php require __DIR__ . '/partials/admin_event_filters_script.php'; ?>
</body>
</html>
