<?php
declare(strict_types=1);

/**
 * Latinfo.hu nyilvános kezdőoldal.
 * URL: / (production) és /nextgen/site/ (közvetlen / előnézet).
 */

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/events/bootstrap.php';
require_once dirname(__DIR__) . '/events/lib/event_public_lang.php';
require_once dirname(__DIR__) . '/events/lib/event_public_djs.php';
require_once dirname(__DIR__) . '/events/lib/public_djs_content.php';
require_once dirname(__DIR__) . '/events/lib/public_event_filters.php';
require_once dirname(__DIR__) . '/events/lib/calendar_event_preview.php';
require_once dirname(__DIR__) . '/events/lib/event_public_styles.php';
require_once __DIR__ . '/lib/site_modules.php';

$lang = events_public_resolve_megjelenit_lang();
$D = events_public_home_strings($lang);
$S = $D;
$S['admin_edit_title'] = 'Szerkesztés';
$S['admin_edit_aria'] = 'Kezdőoldal szerkesztése';
$H = latinfo_home_strings($lang);

$db = getDb();
$schemaOk = latinfo_home_modules_ensure_schema($db);
$enabledModules = $schemaOk ? latinfo_home_modules_enabled($db, 'desktop') : [];
$mobileOrderIndex = [];
if ($schemaOk) {
    $rank = 1;
    foreach (latinfo_home_modules_enabled($db, 'mobile') as $mod) {
        $mobileOrderIndex[(string) $mod['module_key']] = $rank;
        $rank++;
    }
}
$news = $schemaOk ? latinfo_home_news_all($db, true) : [];
$quickNews = latinfo_home_quick_news($news, 3);
$dayEvents = latinfo_home_today_tomorrow_events($db);
$homeEventRows = array_merge($dayEvents['today'], $dayEvents['tomorrow']);
$categoriesByEventId = events_public_load_categories_by_event_id($db, $homeEventRows);
$organizersByEventId = events_calendar_load_organizers_by_event_id($db, $homeEventRows);
$stylesByEventId = events_public_load_styles_by_event_id($db, $homeEventRows);
$calendarPreviewById = events_calendar_preview_build_map(
    $homeEventRows,
    $categoriesByEventId,
    $organizersByEventId,
    $lang,
    $stylesByEventId['main'],
    $stylesByEventId['supplementary']
);
$djSpotlight = latinfo_home_dj_spotlight($db, $lang);

$calendarUrl = events_public_home_path();
$djsUrl = events_public_djs_hub_canonical_url();
$editUrl = latinfo_home_edit_url();
$cssPublicUrl = events_url('assets/event_public.css') . '?v=' . rawurlencode(nextgen_app_version());
$cssHomeUrl = latinfo_home_asset_url('css/site-home.css') . '?v=' . rawurlencode(nextgen_app_version());

$spotlightCards = $djSpotlight['cards'];
$spotlightMobileCount = $djSpotlight['visible_count'];
$spotlightVisible = array_slice($spotlightCards, 0, $spotlightMobileCount);
$cmsAnchorSpotlight = 'latinfo-dj-ajanlo';
$Dj = $djSpotlight['strings'];
$ratingStrings = latinfo_home_rating_strings($lang);
$ratingAjaxUrl = nextgen_url('site/ajax_rating.php');
$donablyView = latinfo_home_donably_view($db, $lang);
$lhModuleTrackAllowed = true;

$htmlLang = $lang === 'en' ? 'en' : 'hu';
$urlHu = latinfo_home_lang_switch_url('hu');
$urlEn = latinfo_home_lang_switch_url('en');
$isEventsHome = true;
$loggedIn = isLoggedIn();
$showAdminEdit = $loggedIn;
$adminEditUrl = $loggedIn ? $editUrl : '';
$adminFloatTools = [];
if ($loggedIn) {
    $adminFloatTools = [
        [
            'href' => $editUrl,
            'title' => 'Kezdőoldal szerkesztése',
            'aria' => 'Kezdőoldal szerkesztése',
            'icon' => 'edit',
        ],
        [
            'href' => nextgen_url('latinfo/'),
            'title' => 'Latinfo.hu',
            'aria' => 'Vissza a Latinfo.hu alkalmazáshoz',
            'icon' => 'home',
        ],
    ];
}

header('Content-Type: text/html; charset=UTF-8');

require __DIR__ . '/partials/home.php';
