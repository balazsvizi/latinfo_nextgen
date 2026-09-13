<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_public_lang.php';
require_once __DIR__ . '/lib/public_event_filters.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
require_once __DIR__ . '/lib/public_event_calendar.php';
require_once __DIR__ . '/lib/public_mobile_calendar.php';
require_once __DIR__ . '/lib/calendar_event_preview.php';
require_once __DIR__ . '/lib/event_public_organizers.php';
require_once __DIR__ . '/lib/event_public_styles.php';
require_once __DIR__ . '/lib/mcal_prototypes.php';

requireLogin();
events_public_send_noindex_header();

$lang = 'hu';
$layout = mcal_prototype_resolve_layout((string) ($_GET['layout'] ?? ''));
$layouts = mcal_prototype_layouts();
$cssPublicUrl = events_url('assets/event_public.css') . '?v=' . rawurlencode(nextgen_app_version());
$cssProtoUrl = events_url('assets/mcal_prototypes.css') . '?v=' . rawurlencode(nextgen_app_version());

if ($layout === null) {
    $pageTitle = 'Mobil naptár minták';
    $mainContentClass = 'main-content main-content--fullwidth';
    $extraHead = events_public_robots_noindex_head_markup()
        . '<link rel="stylesheet" href="' . h($cssProtoUrl) . '">';
    require dirname(__DIR__) . '/partials/header.php';
    require __DIR__ . '/partials/mcal_prototype_hub.php';
    require dirname(__DIR__) . '/partials/footer.php';
    exit;
}

$D = events_public_home_strings($lang);
$db = getDb();
$filters = events_public_filters_from_request($db);
[$monthFirst, $monthLast, $monthKey] = events_admin_calendar_resolve_month((string) ($_GET['month'] ?? ''));
$rows = events_public_fetch_filtered_events($db, $filters);
$categoriesByEventId = events_public_load_categories_by_event_id($db, $rows);
$organizersByEventId = events_calendar_load_organizers_by_event_id($db, $rows);
$stylesByEventId = events_public_load_styles_by_event_id($db, $rows);
$calendarPreviewById = events_calendar_preview_build_map(
    $rows,
    $categoriesByEventId,
    $organizersByEventId,
    $lang,
    $stylesByEventId['main'],
    $stylesByEventId['supplementary']
);

$bucket = events_admin_calendar_bucket_events($rows, $monthFirst, $monthLast);
$byDay = $bucket['byDay'];
$gridDays = events_admin_calendar_grid_days($monthFirst, $monthLast);
$gridWeeks = array_chunk($gridDays, 7);
$weekdayLetters = events_public_mobile_calendar_weekday_letters($lang);
$eventsByDayPayload = mcal_prototype_enrich_events_payload(
    events_public_mobile_calendar_events_by_day_payload($byDay, $categoriesByEventId, $lang),
    $lang
);
$selectedDayKey = events_public_mobile_calendar_resolve_selected_day(
    $monthFirst,
    $byDay,
    (string) ($_GET['day'] ?? '')
);
$selectedDayHeading = events_public_mobile_calendar_day_heading($selectedDayKey, $lang);
$selectedEvents = $eventsByDayPayload[$selectedDayKey] ?? [];
$headerDateLabel = events_public_calendar_month_label($monthFirst, $lang);
$emptyDayLabel = (string) ($D['mcal_empty_day'] ?? 'Nincs esemény ezen a napon.');
$maxDots = 3;

$liveMcalUrl = events_public_home_url($lang, ['month' => $monthKey, 'view' => 'mcal', 'day' => $selectedDayKey]);
$hubUrl = mcal_prototype_page_url();
$layoutMeta = $layouts[$layout];
$S = $D;
$isEventsHome = true;
$showAdminEdit = false;
$adminEditUrl = '';
$heroInlineTitle = '';
$heroExtraBackLinks = [];
$urlHu = mcal_prototype_page_url($layout, ['month' => $monthKey, 'day' => $selectedDayKey]);
$urlEn = $urlHu;
$mcalToggleUrl = $liveMcalUrl;
$mcalToggleTitle = 'Élő mobil naptár (kontroll)';
$renewalNotice = null;

$dayHeadings = [];
foreach ($gridDays as $day) {
    $k = (string) $day['key'];
    $dayHeadings[$k] = events_public_mobile_calendar_day_heading($k, $lang);
}

require __DIR__ . '/partials/mcal_prototype_view.php';
