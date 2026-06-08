<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_public_lang.php';
require_once __DIR__ . '/lib/event_public_tags.php';
require_once __DIR__ . '/lib/event_public_djs.php';

$lang = events_public_resolve_megjelenit_lang();
events_public_send_noindex_header();
$G = events_public_tag_strings($lang);

$tagId = (int) ($_GET['id'] ?? 0);
if ($tagId <= 0) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo events_public_tag_not_found_html($lang);
    exit;
}

$db = getDb();
if (!events_tags_tables_available($db)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo events_public_tag_not_found_html($lang);
    exit;
}

$st = $db->prepare('SELECT `id`, `name` FROM `events_tags` WHERE `id` = ? LIMIT 1');
$st->execute([$tagId]);
$tag = $st->fetch(PDO::FETCH_ASSOC);
if (!$tag) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo events_public_tag_not_found_html($lang);
    exit;
}

$tagName = (string) ($tag['name'] ?? '');
$tagTypeRows = events_public_tag_type_rows_for_display($db, $tagId);
$tagIsDj = events_public_tag_has_type_code($db, $tagId, 'dj');
$eventsList = events_public_tag_published_events($db, $tagId, events_public_post_status());
$partitioned = events_public_organizer_partition_events($eventsList);
$eventsUpcoming = $partitioned['upcoming'];
$eventsPast = $partitioned['past'];
$eventsTotalCount = count($eventsList);
$eventsUpcomingCount = count($eventsUpcoming);
$eventsPastCount = count($eventsPast);

$title = $tagName !== '' ? $tagName : ('#' . $tagId);
$desc = $lang === 'en'
    ? ($tagName !== '' ? 'Published events tagged with ' . $tagName . ' on Latinfo.hu.' : 'Published events on Latinfo.hu.')
    : ($tagName !== '' ? $tagName . ' címkéjű közzétett események a Latinfo.hu-n.' : 'Közzétett események a Latinfo.hu-n.');

$canonical = events_absolute_url(events_url('tag.php?id=' . $tagId));
$ogPageUrl = events_absolute_url(events_public_tag_page_url($tagId, $lang));
$cssUrl = events_url('assets/event_public.css');
$urlHu = events_public_tag_lang_switch_url($tagId, 'hu');
$urlEn = events_public_tag_lang_switch_url($tagId, 'en');
$htmlLang = $lang === 'en' ? 'en' : 'hu';
$S = $G;
$showAdminEdit = isLoggedIn();
$adminEditUrl = events_url('tags.php?open_tag=') . $tagId;

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= events_public_robots_noindex_head_markup() ?>
    <meta name="theme-color" content="#6d8f63">
    <title><?= h($title) ?><?= h($G['html_title_suffix']) ?><?= h(SITE_NAME) ?></title>
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
    <?php require __DIR__ . '/partials/public_shell_toolbar.php'; ?>
<article class="event-public organizer-public">
    <header class="event-public__hero">
        <div class="event-public__hero-inner">
            <?php if ($tagTypeRows !== []): ?>
                <div class="tag-public__types" aria-label="<?= h($lang === 'en' ? 'Tag types' : 'Címke típusok') ?>">
                    <?php foreach ($tagTypeRows as $typeRow): ?>
                        <?php
                        $tone = (string) ($typeRow['tone'] ?? 'default');
                        $icon = (string) ($typeRow['icon'] ?? '🏷️');
                        ?>
                        <span class="tag-public__type-pill tag-public__type-pill--<?= h($tone) ?>">
                            <span class="tag-public__type-pill__icon" aria-hidden="true"><?= $icon ?></span>
                            <span class="tag-public__type-pill__label"><?= h((string) ($typeRow['name'] ?? '')) ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <h1 class="event-public__title"><?= h($title) ?></h1>
            <?php if ($tagIsDj): ?>
                <p class="tag-public__nav-link">
                    <a href="<?= h(events_public_djs_page_url($lang)) ?>">← <?= h($G['all_djs_link']) ?></a>
                </p>
            <?php endif; ?>
        </div>
    </header>

    <section class="organizer-public__events" aria-labelledby="tag-events-heading">
        <h2 class="organizer-public__events-title" id="tag-events-heading">
            <?= h($G['events_heading']) ?>
            <span class="organizer-public__heading-count">(<?= $eventsTotalCount ?>)</span>
        </h2>
        <?php if ($eventsList === []): ?>
            <p class="organizer-public__empty"><?= h($G['list_empty']) ?></p>
        <?php else: ?>
            <?php
            $tagEventBlocks = [
                ['id' => 'tag-upcoming', 'heading' => $G['section_upcoming'], 'rows' => $eventsUpcoming, 'empty' => $G['upcoming_empty']],
                ['id' => 'tag-past', 'heading' => $G['section_past'], 'rows' => $eventsPast, 'empty' => $G['past_empty']],
            ];
            ?>
            <?php foreach ($tagEventBlocks as $block): ?>
                <?php
                $isPastSection = ($block['id'] ?? '') === 'tag-past';
                $subsectionClass = 'organizer-public__subsection' . ($isPastSection ? ' organizer-public__subsection--past' : '');
                $subsectionTitleClass = 'organizer-public__subsection-title' . ($isPastSection ? ' organizer-public__subsection-title--past' : '');
                ?>
                <div class="<?= h($subsectionClass) ?>" id="<?= h((string) $block['id']) ?>">
                    <?php
                    $blockCount = ($block['id'] ?? '') === 'tag-past' ? $eventsPastCount : $eventsUpcomingCount;
                    ?>
                    <h3 class="<?= h($subsectionTitleClass) ?>">
                        <?= h((string) $block['heading']) ?>
                        <span class="organizer-public__heading-count">(<?= $blockCount ?>)</span>
                    </h3>
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
                                $relFeatRaw = trim(html_entity_decode(trim((string) ($rel['event_featured_image_url'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                                $relFeatRaw = preg_replace('/^\x{FEFF}|\x{200B}/u', '', $relFeatRaw) ?? $relFeatRaw;
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
        <?php require __DIR__ . '/partials/public_shell_footer.php'; ?>
    </footer>
</article>
</div>
</body>
</html>
