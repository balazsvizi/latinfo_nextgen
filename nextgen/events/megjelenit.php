<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/venue_request.php';
require_once __DIR__ . '/lib/event_public_lang.php';
require_once __DIR__ . '/lib/category_locale.php';
require_once __DIR__ . '/lib/event_public_organizers.php';
require_once __DIR__ . '/lib/tag_type.php';
require_once __DIR__ . '/lib/event_public_tags.php';
require_once __DIR__ . '/lib/event_public_styles.php';
require_once __DIR__ . '/lib/event_view_tracking.php';
require_once __DIR__ . '/lib/event_change.php';
require_once __DIR__ . '/lib/admin_event_calendar.php';

$lang = events_public_resolve_megjelenit_lang();

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo events_public_megjelenit_not_found_html($lang);
    exit;
}

if (events_public_is_legacy_megjelenit_request()) {
    $legacyParams = [];
    foreach (['lang', 'ref'] as $param) {
        if (isset($_GET[$param]) && (string) $_GET[$param] !== '') {
            $legacyParams[$param] = (string) $_GET[$param];
        }
    }
    $targetLang = ($legacyParams['lang'] ?? $lang) === 'en' ? 'en' : 'hu';
    $target = events_public_event_page_url($slug, $targetLang);
    unset($legacyParams['lang']);
    events_public_redirect_to(events_public_append_query($target, $legacyParams));
}

events_public_send_noindex_header();
$T = events_public_megjelenit_strings($lang);

$db = getDb();
$stmt = $db->prepare('
    SELECT e.*,
        v.`name` AS `venue_name`,
        v.`slug` AS `venue_slug`,
        v.`country` AS `venue_country`,
        v.`city` AS `venue_city`,
        v.`postal_code` AS `venue_postal_code`,
        v.`address` AS `venue_address`,
        v.`latitude` AS `venue_latitude`,
        v.`longitude` AS `venue_longitude`,
        l.`name` AS `venue_linked_name`,
        l.`slug` AS `venue_linked_slug`
    FROM `events_calendar_events` e
    LEFT JOIN `events_venues` v ON v.`id` = e.`venue_id`
    LEFT JOIN `events_venues` l ON l.`id` = v.`linked_venue_id`
    WHERE e.`event_slug` = ? AND e.`event_status` = ?
    LIMIT 1
');
$stmt->execute([$slug, events_public_post_status()]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo events_public_megjelenit_not_found_html($lang);
    exit;
}

$eventId = (int) ($event['id'] ?? 0);
$eventOrganizers = events_public_event_organizers_for_display($db, $eventId);
$eventCategories = events_public_event_category_rows($db, $eventId);
$eventDjs = events_public_event_tags_by_types($db, $eventId, ['dj']);
$eventTags = events_public_event_tags_for_display($db, $eventId, ['dj']);
$eventMainStyles = events_public_event_main_styles_for_display($db, $eventId);
$eventSupplementaryStyles = events_public_event_supplementary_styles_for_display($db, $eventId);

$venueName = trim((string) ($event['venue_name'] ?? ''));
$venueSlug = trim((string) ($event['venue_slug'] ?? ''));
$showVenue = $venueName !== '' || $venueSlug !== '';
$venueAddrLine = '';
if ($showVenue) {
    $venueAddrLine = events_venue_address_summary([
        'postal_code' => (string) ($event['venue_postal_code'] ?? ''),
        'city' => (string) ($event['venue_city'] ?? ''),
        'address' => (string) ($event['venue_address'] ?? ''),
        'country' => (string) ($event['venue_country'] ?? ''),
    ]);
}
$venueLinkedName = trim((string) ($event['venue_linked_name'] ?? ''));
$venueLinkedSlug = trim((string) ($event['venue_linked_slug'] ?? ''));
$venueHasLinked = $venueLinkedName !== '' && $venueLinkedSlug !== '';
$venueCoords = $showVenue ? events_venue_coordinates_from_row([
    'latitude' => $event['venue_latitude'] ?? null,
    'longitude' => $event['venue_longitude'] ?? null,
]) : null;

$allday = !empty($event['event_allday']);
$tsStart = !empty($event['event_start']) ? strtotime((string) $event['event_start']) : false;
$tsEnd = !empty($event['event_end']) ? strtotime((string) $event['event_end']) : false;

$heroDateLines = events_public_megjelenit_hero_datetime_lines($allday, $tsStart, $tsEnd, $lang);

$cfRaw = $event['event_cost_from'] ?? null;
$ctRaw = $event['event_cost_to'] ?? null;
$cf = $cfRaw !== null && $cfRaw !== '' ? (float) $cfRaw : null;
$ct = $ctRaw !== null && $ctRaw !== '' ? (float) $ctRaw : null;
$costText = events_public_megjelenit_cost_text($cf, $ct, $lang);

$pageSource = events_view_tracking_resolve_page_source((string) ($_GET['ref'] ?? ''));
events_track_event_view($db, (int) $event['id'], EVENTS_VIEW_METRIC_PAGE, $pageSource);

$canonical = events_absolute_url(events_public_canonical_url((string) $event['event_slug']));
$title = $event['event_name'];
$safeEventContent = events_sanitize_html_fragment((string) ($event['event_content'] ?? ''));
$desc = mb_substr(trim(strip_tags($safeEventContent)), 0, 160, 'UTF-8');
$featuredRaw = trim(html_entity_decode(trim((string) ($event['event_featured_image_url'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
$featuredRaw = preg_replace('/^\x{FEFF}|\x{200B}/u', '', $featuredRaw) ?? $featuredRaw;
$featuredAbsolute = $featuredRaw !== '' ? events_absolute_url($featuredRaw) : '';
$ogPageUrl = events_absolute_url(events_public_event_page_url($slug, $lang));
$cssUrl = events_url('assets/event_public.css');
$urlHu = events_public_megjelenit_lang_switch_url($slug, 'hu');
$urlEn = events_public_megjelenit_lang_switch_url($slug, 'en');
$htmlLang = $lang === 'en' ? 'en' : 'hu';
$showAdminEdit = false;
$eventEditUrl = events_url('szerkeszt.php?id=') . (int) ($event['id'] ?? 0);
$S = $T;
$adminEditUrl = $eventEditUrl;

$eventMonthKey = events_admin_calendar_month_key_from_event($event);
$adminFloatTools = [];
if (isLoggedIn()) {
    $adminFloatTools = [
        [
            'href' => $eventEditUrl,
            'title' => (string) ($T['admin_edit_title'] ?? 'Szerkesztés'),
            'aria' => (string) ($T['admin_edit_aria'] ?? 'Esemény szerkesztése az adminban'),
            'icon' => 'edit',
        ],
        [
            'href' => events_url('letrehoz.php?copy_from=') . (int) ($event['id'] ?? 0),
            'title' => 'Esemény másolása',
            'aria' => 'Esemény másolása',
            'icon' => 'copy',
        ],
        [
            'href' => events_admin_calendar_view_url($eventMonthKey, []),
            'title' => 'Admin naptár',
            'aria' => 'Admin naptár megnyitása',
            'icon' => 'calendar',
        ],
        [
            'href' => events_admin_list_view_url([]),
            'title' => 'Eseménylista (admin)',
            'aria' => 'Eseménylista az Event Adminban',
            'icon' => 'list',
        ],
    ];
}

$eventExternalUrl = trim((string) ($event['event_url'] ?? ''));

$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Event',
    'name' => (string) $event['event_name'],
    'url' => $canonical,
    'inLanguage' => $lang === 'en' ? 'en' : 'hu',
];
if (!empty($event['event_start']) && $tsStart) {
    $jsonLd['startDate'] = date('c', $tsStart);
}
if (!empty($event['event_end']) && $tsEnd) {
    $jsonLd['endDate'] = date('c', $tsEnd);
}
if ($showVenue && ($venueName !== '' || $venueAddrLine !== '')) {
    $jsonLd['location'] = [
        '@type' => 'Place',
        'name' => $venueName !== '' ? $venueName : $venueSlug,
    ];
    if ($venueAddrLine !== '') {
        $jsonLd['location']['address'] = $venueAddrLine;
    }
}
if ($featuredAbsolute !== '') {
    $jsonLd['image'] = [$featuredAbsolute];
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= events_public_ga_head_markup() ?>
    <?= events_public_robots_noindex_head_markup() ?>
    <meta name="theme-color" content="#6d8f63">
    <title><?= h((string) $title) ?><?= h($T['html_title_suffix']) ?><?= h(SITE_NAME) ?></title>
    <meta name="description" content="<?= h($desc) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
    <meta property="og:title" content="<?= h((string) $title) ?>">
    <meta property="og:description" content="<?= h($desc) ?>">
    <meta property="og:url" content="<?= h($ogPageUrl) ?>">
    <?php if ($featuredAbsolute !== ''): ?>
        <meta property="og:image" content="<?= h($featuredAbsolute) ?>">
        <meta property="og:image:alt" content="<?= h((string) $title) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="<?= $featuredAbsolute !== '' ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= h((string) $title) ?>">
    <meta name="twitter:description" content="<?= h($desc) ?>">
    <?php if ($featuredAbsolute !== ''): ?>
        <meta name="twitter:image" content="<?= h($featuredAbsolute) ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?= h($canonical) ?>">
    <link rel="alternate" hreflang="hu" href="<?= h($urlHu) ?>">
    <link rel="alternate" hreflang="en" href="<?= h($urlEn) ?>">
    <link rel="alternate" hreflang="x-default" href="<?= h($urlHu) ?>">
    <?= events_public_favicon_head_markup() ?>
    <link rel="stylesheet" href="<?= h($cssUrl) ?>">
    <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</head>
<body class="event-public-page">
<?php require __DIR__ . '/partials/admin_float_tools.php'; ?>
<div class="event-shell">
<article class="event-public event-public--detail">
    <header class="event-public__hero">
        <?php require __DIR__ . '/partials/public_shell_hero_bar.php'; ?>
        <div class="event-public__hero-inner">
            <?php require __DIR__ . '/partials/public_event_change_notice.php'; ?>
            <h1 class="event-public__title<?= events_event_change_type($event) === events_event_change_type_cancelled() ? ' event-public__title--cancelled' : '' ?>"><?= h((string) $event['event_name']) ?></h1>
            <?php if (!empty($event['event_latinfohu_partner'])): ?>
                <div class="event-public__badges">
                    <span class="event-badge event-badge--accent"><?= h($T['badge_partner']) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($featuredAbsolute !== ''): ?>
                <figure class="event-featured">
                    <button type="button" class="event-featured__trigger" aria-label="<?= h((string) ($T['featured_image_zoom'] ?? '')) ?>">
                        <img
                            class="event-featured__img"
                            src="<?= h($featuredAbsolute) ?>"
                            alt="<?= h((string) $title) ?>"
                            decoding="async"
                            fetchpriority="high"
                            loading="eager"
                        >
                    </button>
                </figure>
            <?php endif; ?>

            <?php if ($heroDateLines !== []): ?>
                <div class="event-datetime-hero" role="group" aria-label="<?= h($T['meta_datetime']) ?>">
                    <div class="event-datetime-hero__row">
                        <span class="event-datetime-hero__cal" aria-hidden="true">🗓️</span>
                        <div class="event-datetime-hero__value">
                            <?php foreach ($heroDateLines as $i => $line): ?>
                                <?php if ($i > 0): ?>
                                    <span class="event-datetime-hero__line event-datetime-hero__line--secondary"><?= h($line) ?></span>
                                <?php else: ?>
                                    <span class="event-datetime-hero__line"><?= h($line) ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php
            $showMetaBlock = $showVenue;
            ?>
            <?php if ($showMetaBlock): ?>
            <div class="event-meta">
                    <div class="event-meta__card event-meta__card--wide" role="group" aria-label="<?= h($T['meta_venue']) ?>">
                        <div class="event-meta__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        </div>
                        <div>
                            <div class="event-venue-head-row">
                                <p class="event-meta__value event-venue-head-row__name">
                                    <?php if ($venueSlug !== ''): ?>
                                        <a class="event-venue-name-link" href="<?= h(events_helyszin_megjelenit_url($venueSlug)) ?>"><?= $venueName !== '' ? h($venueName) : h($venueSlug) ?></a>
                                    <?php else: ?>
                                        <?= $venueName !== '' ? h($venueName) : '' ?>
                                    <?php endif; ?>
                                </p>
                                <?php if (events_venue_has_directions_target($venueCoords, $venueAddrLine, $venueName !== '' ? $venueName : $venueSlug)): ?>
                                    <?php
                                    $venueTitle = $venueName !== '' ? $venueName : $venueSlug;
                                    $directionsLabel = (string) ($T['directions_label'] ?? 'Tervezz útvonalat');
                                    $directionsAria = (string) ($T['directions_aria'] ?? $directionsLabel);
                                    $directionsVariant = 'inline';
                                    require __DIR__ . '/partials/public_venue_directions.php';
                                    ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($venueHasLinked): ?>
                                <p class="event-meta__value event-meta__value--muted event-venue-linked-p">
                                    <a class="event-venue-sub-link" href="<?= h(events_helyszin_megjelenit_url($venueLinkedSlug)) ?>"><?= h($venueLinkedName) ?></a>
                                </p>
                            <?php endif; ?>
                            <?php if ($venueAddrLine !== ''): ?>
                                <p class="event-meta__value event-meta__value--muted event-venue-addr-p">
                                    <span class="event-venue-addr-row">
                                        <span class="event-venue-addr-row__text"><?= nl2br(h($venueAddrLine)) ?></span>
                                        <?php if ($venueCoords !== null): ?>
                                            <?php
                                            $mapDialogId = 'event-venue-map-dialog';
                                            $mapShowAria = (string) ($T['map_show_aria'] ?? 'Térkép megnyitása');
                                            require __DIR__ . '/partials/public_venue_map_trigger.php';
                                            ?>
                                        <?php endif; ?>
                                    </span>
                                </p>
                            <?php elseif ($venueCoords !== null): ?>
                                <p class="event-meta__value event-meta__value--muted event-venue-addr-p">
                                    <?php
                                    $mapDialogId = 'event-venue-map-dialog';
                                    $mapShowAria = (string) ($T['map_show_aria'] ?? 'Térkép megnyitása');
                                    require __DIR__ . '/partials/public_venue_map_trigger.php';
                                    ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
            </div>
            <?php endif; ?>

            <?php require __DIR__ . '/partials/public_event_hero_meta.php'; ?>

            <?php if (!empty($event['event_url'])): ?>
                <div class="event-cta-wrap">
                    <a class="event-cta" href="<?= h((string) $event['event_url']) ?>" target="_blank" rel="noopener noreferrer">
                        <span><?= h($T['cta_external']) ?></span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="event-public__content">
        <?php if ($eventExternalUrl !== ''): ?>
            <?php $placement = 'top'; require __DIR__ . '/partials/public_event_external_info_notice.php'; ?>
        <?php endif; ?>
        <div class="event-public__content-main">
            <div class="event-body">
                <?= $safeEventContent ?>
            </div>
        </div>
        <?php if ($eventExternalUrl !== ''): ?>
            <?php $placement = 'bottom'; require __DIR__ . '/partials/public_event_external_info_notice.php'; ?>
        <?php endif; ?>
    </div>
</article>

<footer class="event-public__footer event-public__footer--shell">
    <?php require __DIR__ . '/partials/public_shell_footer.php'; ?>
</footer>
</div>
<?php if ($featuredAbsolute !== ''): ?>
    <?php require __DIR__ . '/partials/public_event_featured_lightbox.php'; ?>
<?php endif; ?>
<?php if ($venueCoords !== null): ?>
    <?php
    $mapLat = $venueCoords['lat'];
    $mapLng = $venueCoords['lng'];
    $mapTitle = $venueName !== '' ? $venueName : $venueSlug;
    $mapAddress = $venueAddrLine;
    $mapHeading = $T['map_heading'] ?? 'Térkép';
    $mapAriaLabel = $T['map_aria'] ?? 'Helyszín a térképen';
    $mapCloseLabel = $T['map_popup_close'] ?? 'Bezárás';
    $mapDialogId = 'event-venue-map-dialog';
    require __DIR__ . '/partials/public_venue_map_popup.php';
    ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/event_image_orientation_script.php'; ?>
</body>
</html>
