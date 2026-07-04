<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_public_lang.php';
require_once __DIR__ . '/lib/public_home_content.php';
require_once __DIR__ . '/lib/public_event_filters.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
require_once __DIR__ . '/lib/public_event_calendar.php';
require_once __DIR__ . '/lib/calendar_event_preview.php';
require_once __DIR__ . '/lib/event_public_organizers.php';

$lang = events_public_resolve_megjelenit_lang();
events_public_send_noindex_header();
$D = events_public_home_strings($lang);
$langNav = events_public_lang_nav_params($lang);

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
$listPartition = events_public_list_partition_events($rows);
$listDisplayedCount = 0;
$listLimitValue = (string) ($filters['list_limit_value'] ?? EVENTS_ADMIN_EVENTS_LIST_DEFAULT_LIMIT);
if ($view === 'list') {
    $listDisplayedCount = events_public_list_displayed_count($db, $filters);
}
$categoriesByEventId = events_public_load_categories_by_event_id($db, $rows);
$calendarPreviewById = [];
if ($view === 'cal') {
    $organizersByEventId = events_calendar_load_organizers_by_event_id($db, $rows);
    $calendarPreviewById = events_calendar_preview_build_map($rows, $categoriesByEventId, $organizersByEventId, $lang);
}

$bucket = events_admin_calendar_bucket_events($rows, $monthFirst, $monthLast);
$undated = $bucket['undated'];
$gridDays = events_admin_calendar_grid_days($monthFirst, $monthLast);
$calendarWeeks = events_admin_calendar_build_week_layouts($rows, $gridDays, $monthFirst, $monthLast);
$weekdayHeaders = events_public_calendar_weekday_headers($lang);

$navBaseParams = array_merge($filters['get_params'], $langNav);
unset($navBaseParams['view']);
$prevMonthUrl = events_public_calendar_month_url($prevMonthKey, $navBaseParams);
$nextMonthUrl = events_public_calendar_month_url($nextMonthKey, $navBaseParams);
$todayMonthUrl = events_public_calendar_month_url((new DateTimeImmutable('today'))->format('Y-m'), $navBaseParams);

$listViewParams = array_merge($filters['get_params'], $langNav, ['view' => 'list']);
$homeScript = events_public_home_page_script();
$listViewUrl = events_url($homeScript . '?' . http_build_query($listViewParams));

$calViewParams = array_merge($filters['get_params'], $langNav, ['month' => $monthKey]);
unset($calViewParams['view']);
$calViewUrl = events_url($homeScript . '?' . http_build_query($calViewParams));

$filterFormAction = events_url($homeScript);
$filterFormHidden = array_merge(['month' => $monthKey], $langNav);
if ($view === 'list') {
    $filterFormHidden['view'] = 'list';
}
$filterClearParams = array_merge(['month' => $monthKey], $langNav);
$filterClearUrl = events_url($homeScript . '?' . http_build_query($filterClearParams));

$icalFeedParams = array_merge($filters['get_params'], $langNav);
unset($icalFeedParams['month'], $icalFeedParams['view']);

$title = (string) $D['page_title'];
$desc = (string) $D['page_desc'];
$canonical = events_absolute_url(events_public_home_page_url($lang));
$ogPageUrl = $canonical;
$cssUrl = events_url('assets/event_public.css');
$urlHu = events_public_home_lang_switch_url('hu');
$urlEn = events_public_home_lang_switch_url('en');
$htmlLang = $lang === 'en' ? 'en' : 'hu';
$S = $D;
$isEventsHome = true;
$showAdminEdit = isLoggedIn();
$publicAdminParams = $filters['get_params'];
if ($view === 'list') {
    $adminEditUrl = events_admin_list_view_url($publicAdminParams);
    $S['admin_edit_aria'] = (string) $D['admin_edit_aria_list'];
} else {
    $adminEditUrl = events_admin_calendar_view_url($monthKey, $publicAdminParams);
    $S['admin_edit_aria'] = (string) $D['admin_edit_aria_cal'];
}
$heroInlineTitle = $title;
$contentTop = trim((string) ($homeContent['content_top'] ?? ''));
$contentBottom = trim((string) ($homeContent['content_bottom'] ?? ''));

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= events_public_robots_noindex_head_markup() ?>
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
<article class="event-public home-public">
    <header class="event-public__hero">
        <?php require __DIR__ . '/partials/public_shell_hero_bar.php'; ?>
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
                        <span class="home-public__filters-meta">
                            <span class="home-public__filters-badge"><?= h((string) $D['filters_active_badge']) ?></span>
                            <a href="<?= h($filterClearUrl) ?>" class="home-public__clear-filters" onclick="event.stopPropagation();"><?= h((string) $D['clear_filters']) ?></a>
                        </span>
                    <?php endif; ?>
                </summary>
                <div class="home-public__filters-body">
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
                <?php
                $calendarLang = $lang;
                require __DIR__ . '/partials/public_calendar_grid.php';
                ?>
                <?php require __DIR__ . '/partials/public_calendar_subscribe.php'; ?>
            <?php else: ?>
                <div class="events-cal-view-switch-row home-public__view-switch-row">
                    <nav class="events-cal-view-switch events-cal-view-switch--standalone" aria-label="<?= h((string) $D['view_switch_aria']) ?>">
                        <a class="events-cal-view-switch__item" href="<?= h($calViewUrl) ?>"><?= h((string) $D['view_cal']) ?></a>
                        <span class="events-cal-view-switch__item is-active" aria-current="page"><?= h((string) $D['view_list']) ?></span>
                    </nav>
                    <span class="events-cal-view-switch-row__sep" aria-hidden="true">|</span>
                    <?php
                    $listLimitDefault = EVENTS_ADMIN_EVENTS_LIST_DEFAULT_LIMIT;
                    $listLimitInForm = true;
                    $listLimitLabel = (string) $D['list_display_label'];
                    $listLimitAllLabel = (string) $D['list_display_all'];
                    $listCountLabel = events_public_list_count_label($lang, $listDisplayedCount, $listLimitValue);
                    require __DIR__ . '/partials/admin_list_display_limit.php';
                    ?>
                </div>
                <?php
                $listLang = $lang;
                require __DIR__ . '/partials/public_event_list_partitioned.php';
                ?>
                <?php require __DIR__ . '/partials/public_calendar_subscribe.php'; ?>
            <?php endif; ?>
        </form>
    </section>

    <?php if ($contentBottom !== ''): ?>
        <div class="home-public__cms home-public__cms--bottom event-rich-text">
            <?= $contentBottom ?>
        </div>
    <?php endif; ?>

    <footer class="event-public__footer">
        <?php require __DIR__ . '/partials/public_shell_footer.php'; ?>
    </footer>
</article>
</div>
<?php if ($view === 'cal' && $calendarPreviewById !== []): ?>
<?php require __DIR__ . '/partials/public_calendar_event_preview.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/admin_event_filters_script.php'; ?>
<?php require __DIR__ . '/partials/public_event_filters_auto_script.php'; ?>
</body>
</html>
