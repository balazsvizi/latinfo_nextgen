<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_public_lang.php';

$lang = events_public_resolve_megjelenit_lang();
events_public_maybe_redirect_legacy_home($lang);

require_once __DIR__ . '/lib/public_home_content.php';
require_once __DIR__ . '/lib/public_event_filters.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
require_once __DIR__ . '/lib/public_event_calendar.php';
require_once __DIR__ . '/lib/public_mobile_calendar.php';
require_once __DIR__ . '/lib/calendar_event_preview.php';
require_once __DIR__ . '/lib/event_public_organizers.php';
require_once __DIR__ . '/lib/public_home_events_map.php';

events_public_send_noindex_header();
$D = events_public_home_strings($lang);
$langNav = events_public_lang_nav_params($lang);

$db = getDb();
$homeContent = events_public_home_load($db);
$filters = events_public_filters_from_request($db);
$filtersActive = events_public_filters_are_active($filters);
$view = (string) ($filters['view'] ?? events_public_default_home_calendar_view());
$viewExplicit = !empty($filters['view_explicit']);
$filtersPanelOpen = $filtersActive;
if ($view === 'mcal') {
    $filtersPanelOpen = events_public_filters_are_active_excluding_name($filters);
}

[$monthFirst, $monthLast, $monthKey] = events_admin_calendar_resolve_month((string) ($_GET['month'] ?? ''));

if (
    isset($_GET['view'])
    && (string) $_GET['view'] === 'map'
    && $view !== 'map'
) {
    $redirectParams = array_merge($filters['get_params'], $langNav, ['month' => $monthKey]);
    events_public_redirect_to(events_public_home_url($lang, $redirectParams), 302);
}
$prevMonthKey = $monthFirst->modify('-1 month')->format('Y-m');
$nextMonthKey = $monthFirst->modify('+1 month')->format('Y-m');
$monthLabel = events_public_calendar_month_label($monthFirst, $lang);

$rows = events_public_fetch_filtered_events($db, $filters);
$listPartition = events_public_list_partition_events($rows);
$listLimitValue = (string) ($filters['list_limit_value'] ?? EVENTS_ADMIN_EVENTS_LIST_DEFAULT_LIMIT);
$listTotalInDb = 0;
if ($view === 'list') {
    $listTotalInDb = events_public_published_events_total_count($db);
}
$categoriesByEventId = events_public_load_categories_by_event_id($db, $rows);
$mapPayload = $view === 'map'
    ? events_public_home_map_payload_from_rows($rows, $categoriesByEventId, $lang)
    : ['markers' => [], 'geocode_jobs' => [], 'skipped' => 0, 'pending' => 0, 'total' => 0];
$calendarColorLegend = [];
$calendarPreviewById = [];
if ($view === 'cal' || $view === 'mcal') {
    $organizersByEventId = events_calendar_load_organizers_by_event_id($db, $rows);
    $calendarPreviewById = events_calendar_preview_build_map($rows, $categoriesByEventId, $organizersByEventId, $lang);
    if ($view === 'cal') {
        $calendarColorLegend = events_admin_calendar_category_legend_items($db, $lang);
    }
}

$bucket = events_admin_calendar_bucket_events($rows, $monthFirst, $monthLast);
$undated = $bucket['undated'];
$byDay = $bucket['byDay'];
$gridDays = events_admin_calendar_grid_days($monthFirst, $monthLast);
$calendarWeeks = events_admin_calendar_build_week_layouts($rows, $gridDays, $monthFirst, $monthLast);
$weekdayHeaders = events_public_calendar_weekday_headers($lang);

$mcalMode = ((string) ($_GET['mcal_mode'] ?? '') === 'day') ? 'day' : 'month';
$selectedDayKey = events_public_mobile_calendar_resolve_selected_day(
    $monthFirst,
    $byDay,
    (string) ($_GET['day'] ?? '')
);

$navBaseParams = array_merge($filters['get_params'], $langNav);
if ($view === 'mcal') {
    $navBaseParams['view'] = 'mcal';
    unset($navBaseParams['day']);
    if ($mcalMode === 'day') {
        $navBaseParams['mcal_mode'] = 'day';
    } else {
        unset($navBaseParams['mcal_mode']);
    }
} elseif ($view === 'cal' && $viewExplicit) {
    $navBaseParams['view'] = 'cal';
    unset($navBaseParams['mcal_mode'], $navBaseParams['day']);
} else {
    unset($navBaseParams['view'], $navBaseParams['mcal_mode'], $navBaseParams['day']);
}
$prevMonthUrl = events_public_calendar_month_url($prevMonthKey, $navBaseParams);
$nextMonthUrl = events_public_calendar_month_url($nextMonthKey, $navBaseParams);
$todayMonthUrl = events_public_calendar_month_url(events_admin_calendar_effective_today()->format('Y-m'), $navBaseParams);

$listViewParams = array_merge($filters['get_params'], $langNav, ['view' => 'list']);
unset($listViewParams['mcal_mode'], $listViewParams['day']);
$listViewUrl = events_public_home_url($lang, $listViewParams);

$mapViewParams = array_merge($filters['get_params'], $langNav, ['view' => 'map']);
unset($mapViewParams['mcal_mode'], $mapViewParams['day']);
$mapViewUrl = events_public_home_url($lang, $mapViewParams);

// Eszközfüggő alap naptár (nincs view param).
$adaptiveCalViewParams = array_merge($filters['get_params'], $langNav, ['month' => $monthKey]);
unset($adaptiveCalViewParams['view'], $adaptiveCalViewParams['mcal_mode'], $adaptiveCalViewParams['day']);
$adaptiveCalViewUrl = events_public_home_url($lang, $adaptiveCalViewParams);

// Kényszerített klasszikus / mobil (Hun/Eng „/” váltó).
$calViewParams = array_merge($filters['get_params'], $langNav, ['month' => $monthKey, 'view' => 'cal']);
unset($calViewParams['mcal_mode'], $calViewParams['day']);
$calViewUrl = events_public_home_url($lang, $calViewParams);

$mcalViewParams = array_merge($filters['get_params'], $langNav, ['month' => $monthKey, 'view' => 'mcal']);
unset($mcalViewParams['mcal_mode'], $mcalViewParams['day']);
$mcalViewUrl = events_public_home_url($lang, $mcalViewParams);
$mcalMonthViewUrl = $mcalViewUrl;
$mcalDayModeParams = array_merge($mcalViewParams, ['mcal_mode' => 'day', 'day' => $selectedDayKey]);
$mcalDayModeUrl = events_public_home_url($lang, $mcalDayModeParams);
$mcalListViewUrl = $listViewUrl;

$filterFormAction = events_public_home_path();
$filterFormHidden = array_merge(['month' => $monthKey], $langNav);
if ($view === 'list') {
    $filterFormHidden['view'] = 'list';
} elseif ($view === 'map') {
    $filterFormHidden['view'] = 'map';
} elseif ($view === 'mcal') {
    $filterFormHidden['view'] = 'mcal';
    if ($mcalMode === 'day') {
        $filterFormHidden['mcal_mode'] = 'day';
    }
    $filterFormHidden['day'] = $selectedDayKey;
} elseif ($view === 'cal' && $viewExplicit) {
    $filterFormHidden['view'] = 'cal';
}
$filterClearParams = array_merge(['month' => $monthKey], $langNav);
if ($view === 'map') {
    $filterClearParams['view'] = 'map';
} elseif ($view === 'mcal') {
    $filterClearParams['view'] = 'mcal';
} elseif ($view === 'cal' && $viewExplicit) {
    $filterClearParams['view'] = 'cal';
}
$filterClearUrl = events_public_home_url($lang, $filterClearParams);

$icalFeedParams = array_merge($filters['get_params'], $langNav);
unset($icalFeedParams['month'], $icalFeedParams['view'], $icalFeedParams['mcal_mode'], $icalFeedParams['day']);

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
$showAdminEdit = false;
$adminEditUrl = '';
$publicAdminParams = $filters['get_params'];
if ($view === 'list') {
    $matchingAdminUrl = events_admin_list_view_url($publicAdminParams);
    $matchingAdminTitle = (string) $D['admin_edit_aria_list'];
    $matchingAdminIcon = 'list';
} elseif ($view === 'map') {
    $matchingAdminUrl = events_admin_list_view_url($publicAdminParams);
    $matchingAdminTitle = (string) $D['admin_edit_aria_list'];
    $matchingAdminIcon = 'list';
} else {
    $matchingAdminUrl = events_admin_calendar_view_url($monthKey, $publicAdminParams);
    $matchingAdminTitle = (string) $D['admin_edit_aria_cal'];
    $matchingAdminIcon = 'calendar';
}

$adminFloatTools = [];
if (isLoggedIn()) {
    $adminFloatTools = [
        [
            'href' => $matchingAdminUrl,
            'title' => (string) ($D['admin_edit_title'] ?? 'Szerkesztés'),
            'aria' => $matchingAdminTitle,
            'icon' => $matchingAdminIcon,
        ],
        [
            'href' => events_url('letrehoz.php'),
            'title' => 'Új esemény',
            'aria' => 'Új esemény létrehozása',
            'icon' => 'plus',
        ],
        [
            'href' => events_url('fooldal_szerkeszt.php'),
            'title' => 'Főoldal szövegek szerkesztése',
            'aria' => 'Főoldal szövegek szerkesztése',
            'icon' => 'edit',
        ],
    ];
}
$heroInlineTitle = '';
$contentTop = trim((string) ($homeContent['content_top'] ?? ''));
$contentBottom = trim((string) ($homeContent['content_bottom'] ?? ''));

$mcalToggleUrl = $view === 'mcal' ? $calViewUrl : $mcalViewUrl;
$mcalToggleTitle = $view === 'mcal'
    ? (string) ($D['mcal_toggle_back'] ?? ($lang === 'en' ? 'Classic calendar' : 'Klasszikus naptár'))
    : (string) ($D['mcal_toggle_open'] ?? ($lang === 'en' ? 'Mobile calendar' : 'Mobil naptár'));
$needsDeviceViewSync = !$viewExplicit && ($view === 'cal' || $view === 'mcal');

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($needsDeviceViewSync): ?>
    <script>
    (function () {
        try {
            // Csak keskeny viewport + klasszikus nézet → mobil naptár.
            // Fordítva nem váltunk (tablet landscape UA+mcal ne loopoljon).
            if (!window.matchMedia('(max-width: 639px)').matches) return;
            if (<?= json_encode($view, JSON_UNESCAPED_UNICODE) ?> !== 'cal') return;
            var url = new URL(window.location.href);
            url.searchParams.set('view', 'mcal');
            window.location.replace(url.pathname + url.search + url.hash);
        } catch (e) { /* ignore */ }
    })();
    </script>
    <?php endif; ?>
    <?= events_public_ga_head_markup() ?>
    <?= events_public_robots_noindex_head_markup() ?>
    <meta name="theme-color" content="<?= $view === 'mcal' ? '#3B50FF' : '#6d8f63' ?>">
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
<body class="event-public-page event-public-page--home<?= $view === 'mcal' ? ' event-public-page--mcal' : '' ?>">
<?php require __DIR__ . '/partials/admin_float_tools.php'; ?>
<div class="event-shell<?= $view === 'mcal' ? ' event-shell--mcal' : '' ?>">
<article class="event-public home-public<?= $view === 'mcal' ? ' home-public--mcal' : '' ?>">
    <header class="event-public__hero">
        <?php require __DIR__ . '/partials/public_shell_hero_bar.php'; ?>
    </header>

    <?php if ($view !== 'mcal' && $contentTop !== ''): ?>
        <div class="home-public__cms home-public__cms--top event-rich-text">
            <?= $contentTop ?>
        </div>
    <?php endif; ?>

    <section class="home-public__main" aria-label="<?= h((string) $D['calendar_aria']) ?>">
        <form method="get" action="<?= h($filterFormAction) ?>" class="home-public__form" id="events-home-filter-form">
            <details class="home-public__filters-panel<?= $view === 'mcal' ? ' home-public__filters-panel--mcal' : '' ?>" id="home-filters-panel"<?= $filtersPanelOpen ? ' open' : '' ?>>
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
                    <?php
                    $hideMapDateFiltersInPanel = ($view === 'map');
                    require __DIR__ . '/partials/public_event_filters.php';
                    ?>
                </div>
            </details>

            <?php
            $homeActiveView = ($view === 'mcal' || $view === 'cal') ? 'cal' : $view;
            $homeCalViewUrl = $adaptiveCalViewUrl;
            $homeListViewUrl = $listViewUrl;
            $homeMapViewUrl = $mapViewUrl;
            $showMapView = isLoggedIn();
            ?>

            <?php if ($view === 'mcal'): ?>
                <?php require __DIR__ . '/partials/public_mobile_calendar.php'; ?>
            <?php elseif ($view === 'cal'): ?>
                <div class="events-cal-toolbar" aria-label="<?= h((string) $D['cal_controls_aria']) ?>">
                    <div class="events-cal-toolbar__left">
                        <div class="events-cal-toolbar__nav" aria-label="<?= h((string) $D['month_nav_aria']) ?>">
                            <a class="events-cal-toolbar__arrow" href="<?= h($prevMonthUrl) ?>" rel="prev" aria-label="<?= h((string) $D['prev_month']) ?>">‹</a>
                            <a class="events-cal-toolbar__today" href="<?= h($todayMonthUrl) ?>"><?= h((string) $D['this_month']) ?></a>
                            <a class="events-cal-toolbar__arrow" href="<?= h($nextMonthUrl) ?>" rel="next" aria-label="<?= h((string) $D['next_month']) ?>">›</a>
                        </div>
                        <h2 class="events-cal-toolbar__month"><?= h($monthLabel) ?></h2>
                    </div>
                    <div class="events-cal-toolbar__end">
                        <?php require __DIR__ . '/partials/public_calendar_color_help.php'; ?>
                        <?php
                        $homeStandalone = false;
                        require __DIR__ . '/partials/public_home_view_switch.php';
                        ?>
                    </div>
                </div>
                <?php
                $calendarLang = $lang;
                require __DIR__ . '/partials/public_calendar_grid.php';
                ?>
                <?php require __DIR__ . '/partials/public_calendar_subscribe.php'; ?>
            <?php elseif ($view === 'map'): ?>
                <div class="events-cal-view-switch-row home-public__view-switch-row home-public__view-switch-row--map">
                    <?php
                    $homeStandalone = true;
                    require __DIR__ . '/partials/public_home_view_switch.php';
                    ?>
                </div>
                <?php require __DIR__ . '/partials/public_map_date_filters.php'; ?>
                <?php require __DIR__ . '/partials/public_home_events_map.php'; ?>
                <?php require __DIR__ . '/partials/public_calendar_subscribe.php'; ?>
            <?php else: ?>
                <div class="events-cal-view-switch-row home-public__view-switch-row">
                    <?php
                    $homeStandalone = true;
                    require __DIR__ . '/partials/public_home_view_switch.php';
                    ?>
                    <span class="events-cal-view-switch-row__sep" aria-hidden="true">|</span>
                    <?php
                    $listLimitDefault = EVENTS_ADMIN_EVENTS_LIST_DEFAULT_LIMIT;
                    $listLimitInForm = true;
                    $listLimitLabel = (string) $D['list_display_label'];
                    $listLimitAllLabel = (string) $D['list_display_all'];
                    $listCountSuffix = $lang === 'en' ? ' shown' : ' megjelenítve';
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

    <?php if ($view !== 'mcal' && $contentBottom !== ''): ?>
        <div class="home-public__cms home-public__cms--bottom event-rich-text">
            <?= $contentBottom ?>
        </div>
    <?php endif; ?>

    <footer class="event-public__footer">
        <?php require __DIR__ . '/partials/public_shell_footer.php'; ?>
    </footer>
</article>
</div>
<?php require __DIR__ . '/partials/event_image_orientation_script.php'; ?>
<?php if (($view === 'cal' || $view === 'mcal') && $calendarPreviewById !== []): ?>
<?php require __DIR__ . '/partials/public_calendar_event_preview.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/admin_event_filters_script.php'; ?>
<?php require __DIR__ . '/partials/public_event_filters_auto_script.php'; ?>
</body>
</html>
