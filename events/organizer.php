<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_public_lang.php';
require_once __DIR__ . '/lib/event_public_organizers.php';

$lang = events_public_resolve_megjelenit_lang();
$O = events_public_organizer_strings($lang);

$organizerId = (int) ($_GET['id'] ?? 0);
if ($organizerId <= 0) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo events_public_organizer_not_found_html($lang);
    exit;
}

$db = getDb();
$st = $db->prepare('SELECT `id`, `name` FROM `events_organizers` WHERE `id` = ? LIMIT 1');
$st->execute([$organizerId]);
$organizer = $st->fetch(PDO::FETCH_ASSOC);
if (!$organizer) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo events_public_organizer_not_found_html($lang);
    exit;
}

$orgName = (string) ($organizer['name'] ?? '');
$eventsList = events_public_organizer_published_events($db, $organizerId, events_public_post_status());
$partitioned = events_public_organizer_partition_events($eventsList);
$eventsUpcoming = $partitioned['upcoming'];
$eventsPast = $partitioned['past'];

$title = $orgName !== '' ? $orgName : ('#' . $organizerId);
$desc = $lang === 'en'
    ? ($orgName !== '' ? 'Published events by ' . $orgName . ' on Latinfo.hu.' : 'Published events on Latinfo.hu.')
    : ($orgName !== '' ? $orgName . ' közzétett eseményei a Latinfo.hu-n.' : 'Közzétett események a Latinfo.hu-n.');

$canonical = events_absolute_url(events_url('organizer.php?id=' . $organizerId));
$ogPageUrl = events_absolute_url(events_public_organizer_page_url($organizerId, $lang));
$cssUrl = events_url('assets/event_public.css');
$urlHu = events_public_organizer_lang_switch_url($organizerId, 'hu');
$urlEn = events_public_organizer_lang_switch_url($organizerId, 'en');
$htmlLang = $lang === 'en' ? 'en' : 'hu';
$latinfoHomeUrl = LATINFO_PUBLIC_HOME_URL;
$latinfoLogoSrc = site_url('lanueva/assets/images/logo/latinfo_black.png');

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#6d8f63">
    <title><?= h($title) ?><?= h($O['html_title_suffix']) ?><?= h(SITE_NAME) ?></title>
    <meta name="description" content="<?= h($desc) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
    <meta property="og:title" content="<?= h($title) ?>">
    <meta property="og:description" content="<?= h($desc) ?>">
    <meta property="og:url" content="<?= h($ogPageUrl) ?>">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= h($title) ?>">
    <meta name="twitter:description" content="<?= h($desc) ?>">
    <link rel="canonical" href="<?= h($canonical) ?>">
    <link rel="alternate" hreflang="hu" href="<?= h($urlHu) ?>">
    <link rel="alternate" hreflang="en" href="<?= h($urlEn) ?>">
    <link rel="alternate" hreflang="x-default" href="<?= h($urlHu) ?>">
    <?= events_public_favicon_head_markup() ?>
    <link rel="stylesheet" href="<?= h($cssUrl) ?>">
</head>
<body class="event-public-page">
<div class="event-shell">
    <div class="event-shell-toolbar">
        <div class="event-shell-toolbar__leading">
            <a class="event-brand-logo" href="<?= h($latinfoHomeUrl) ?>" title="<?= h($O['logo_home_title']) ?>" aria-label="<?= h($O['logo_home_aria']) ?>">
                <img src="<?= h($latinfoLogoSrc) ?>" alt="<?= h($O['logo_alt']) ?>" width="180" height="48" decoding="async" fetchpriority="high">
            </a>
        </div>
        <div class="event-lang-switch" role="navigation" aria-label="<?= h($O['lang_nav']) ?>">
            <a class="event-lang-switch__link<?= $lang === 'hu' ? ' is-active' : '' ?>" href="<?= h($urlHu) ?>" hreflang="hu" lang="hu"><?= h($O['lang_hu']) ?></a>
            <span class="event-lang-switch__sep" aria-hidden="true">|</span>
            <a class="event-lang-switch__link<?= $lang === 'en' ? ' is-active' : '' ?>" href="<?= h($urlEn) ?>" hreflang="en" lang="en"><?= h($O['lang_en']) ?></a>
        </div>
    </div>
<article class="event-public organizer-public">
    <header class="event-public__hero">
        <div class="event-public__hero-inner">
            <p class="event-public__eyebrow"><?= h($O['eyebrow']) ?></p>
            <h1 class="event-public__title"><?= h($title) ?></h1>
        </div>
    </header>

    <section class="organizer-public__events" aria-labelledby="organizer-events-heading">
        <h2 class="organizer-public__events-title" id="organizer-events-heading"><?= h($O['events_heading']) ?></h2>
        <?php if ($eventsList === []): ?>
            <p class="organizer-public__empty"><?= h($O['list_empty']) ?></p>
        <?php else: ?>
            <?php
            $organizerEventBlocks = [
                ['id' => 'organizer-upcoming', 'heading' => $O['section_upcoming'], 'rows' => $eventsUpcoming, 'empty' => $O['upcoming_empty']],
                ['id' => 'organizer-past', 'heading' => $O['section_past'], 'rows' => $eventsPast, 'empty' => $O['past_empty']],
            ];
            ?>
            <?php foreach ($organizerEventBlocks as $block): ?>
                <div class="organizer-public__subsection" id="<?= h((string) $block['id']) ?>">
                    <h3 class="organizer-public__subsection-title"><?= h((string) $block['heading']) ?></h3>
                    <?php if ($block['rows'] === []): ?>
                        <p class="organizer-public__subsection-empty"><?= h((string) $block['empty']) ?></p>
                    <?php else: ?>
                        <ul class="event-related-grid" role="list">
                            <?php foreach ($block['rows'] as $rel): ?>
                                <?php
                                $relSlug = (string) ($rel['event_slug'] ?? '');
                                $relTitle = (string) ($rel['event_name'] ?? '');
                                $relHref = events_public_event_page_url($relSlug, $lang);
                                $relAllday = !empty($rel['event_allday']);
                                $relTsStart = !empty($rel['event_start']) ? strtotime((string) $rel['event_start']) : false;
                                $dateDisplay = events_public_event_start_date_time_display($relAllday, $relTsStart, $lang);
                                $relFeatRaw = trim((string) ($rel['event_featured_image_url'] ?? ''));
                                $relFeatAbs = $relFeatRaw !== '' ? events_absolute_url($relFeatRaw) : '';
                                $venueCity = trim((string) ($rel['venue_city'] ?? ''));
                                ?>
                                <li class="event-related-grid__cell">
                                    <a class="event-related-card" href="<?= h($relHref) ?>">
                                        <div class="event-related-card__media">
                                            <?php if ($relFeatAbs !== ''): ?>
                                                <img
                                                    class="event-related-card__img"
                                                    src="<?= h($relFeatAbs) ?>"
                                                    alt=""
                                                    width="640"
                                                    height="360"
                                                    loading="lazy"
                                                    decoding="async"
                                                >
                                            <?php else: ?>
                                                <div class="event-related-card__placeholder" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.25"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 16l5-5 4 4 5-6 5 7"/></svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="event-related-card__body">
                                            <span class="event-related-card__title"><?= h($relTitle) ?></span>
                                            <?php if ($dateDisplay !== '' || $venueCity !== ''): ?>
                                                <div class="event-related-card__meta">
                                                    <?php if ($dateDisplay !== ''): ?>
                                                        <span class="event-related-card__date"><?= h($dateDisplay) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($venueCity !== ''): ?>
                                                        <span class="event-related-card__city"><?= h($venueCity) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <footer class="event-public__footer">
        <p class="event-site-line">
            <a href="<?= h($latinfoHomeUrl) ?>"><?= h($O['footer_home_link']) ?></a>
        </p>
    </footer>
</article>
</div>
</body>
</html>
