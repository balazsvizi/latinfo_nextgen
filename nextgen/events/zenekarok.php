<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_public_lang.php';
require_once __DIR__ . '/lib/event_public_zenekarok.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
require_once __DIR__ . '/lib/public_event_filters.php';
require_once __DIR__ . '/lib/public_zenekarok_content.php';

$lang = events_public_resolve_megjelenit_lang();
$D = events_public_zenekarok_strings($lang);

$listLimitParsed = events_admin_list_limit_from_get(EVENTS_ADMIN_LIST_DEFAULT_LIMIT);
$listLimitValue = $listLimitParsed['value'];
$limitParams = events_public_catalog_get_params($listLimitValue);

events_public_send_noindex_follow_header();

$db = getDb();
$list_limit = $listLimitParsed['sql_limit'];
$listTotalInDb = events_public_zenekar_total_count($db);
$publishedStatus = events_public_post_status();
$zenekarRowsAll = events_public_zenekar_catalog($db, $publishedStatus, null);
$hubStats = events_public_zenekar_hub_stats($db, $publishedStatus, $zenekarRowsAll);
$zenekarRows = $list_limit === null ? $zenekarRowsAll : array_slice($zenekarRowsAll, 0, $list_limit);
$hubCms = events_public_zenekarok_hub_load($db);
$contentBefore = trim((string) ($hubCms['content_before'] ?? ''));
$contentAfter = trim((string) ($hubCms['content_after'] ?? ''));
$cmsAnchorBefore = EVENTS_PUBLIC_ZENEKAROK_HUB_ANCHOR_BEFORE;
$cmsAnchorAfter = EVENTS_PUBLIC_ZENEKAROK_HUB_ANCHOR_AFTER;
$cmsAnchorSpotlight = EVENTS_PUBLIC_ZENEKAROK_HUB_ANCHOR_SPOTLIGHT;
$cmsAnchorCatalog = EVENTS_PUBLIC_ZENEKAROK_HUB_ANCHOR_CATALOG;

$spotlightCards = events_public_zenekar_spotlight_cards(events_public_zenekar_spotlight_pool($zenekarRowsAll, 24), $lang, $D);
$spotlightMobileCount = 3;
$spotlightVisible = array_slice($spotlightCards, 0, $spotlightMobileCount);
$leadModifier = match (true) {
    $contentBefore === '' && $spotlightVisible === [] => ' djs-public__lead--empty',
    $contentBefore === '' || $spotlightVisible === [] => ' djs-public__lead--solo',
    default => '',
};

$title = (string) $D['page_title'];
$desc = (string) $D['page_desc'];
$canonical = events_absolute_url(events_public_zenekarok_page_url('hu'));
$ogPageUrl = events_absolute_url(events_public_zenekarok_page_url($lang, $limitParams));
$cssUrl = events_url('assets/event_public.css') . '?v=' . rawurlencode(nextgen_app_version());
$urlHu = events_public_zenekarok_lang_switch_url('hu', $limitParams);
$urlEn = events_public_zenekarok_lang_switch_url('en', $limitParams);
$htmlLang = $lang === 'en' ? 'en' : 'hu';
$S = $D;

$adminFloatTools = [];
if (isLoggedIn()) {
    $adminFloatTools = [
        [
            'href' => events_url('zenekarok_admin.php#zenekarok-hub-cms'),
            'title' => 'Szövegek szerkesztése',
            'aria' => 'Nyilvános zenekar oldal szövegeinek szerkesztése',
            'icon' => 'edit',
        ],
        [
            'href' => events_url('zenekar_letrehoz.php'),
            'title' => 'Új zenekar',
            'aria' => 'Új zenekar létrehozása',
            'icon' => 'plus',
        ],
    ];
}

/**
 * @param array{id:int,name:string,slug:string} $row
 */
$zenekarHref = static function (array $row, string $lang): string {
    $slug = trim((string) ($row['slug'] ?? ''));
    $id = (int) ($row['id'] ?? 0);
    if ($slug !== '') {
        return events_public_zenekar_page_url($slug, $lang);
    }

    return events_public_tag_page_url($id, $lang);
};

require_once __DIR__ . '/lib/public_traffic.php';
$eventsPublicTrafficPageKey = 'zenekarok';
events_public_traffic_hit($db, 'zenekarok', $lang);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= events_public_ga_head_markup() ?>
    <?= events_public_robots_noindex_follow_head_markup() ?>
    <meta name="theme-color" content="#6d8f63">
    <title><?= h($title) ?><?= h($D['html_title_suffix']) ?><?= h(SITE_NAME) ?></title>
    <meta name="description" content="<?= h($desc) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
    <meta property="og:title" content="<?= h($title) ?>">
    <meta property="og:description" content="<?= h($desc) ?>">
    <meta property="og:url" content="<?= h($ogPageUrl) ?>">
    <link rel="canonical" href="<?= h($canonical) ?>">
    <link rel="alternate" hreflang="hu" href="<?= h($urlHu) ?>">
    <link rel="alternate" hreflang="en" href="<?= h($urlEn) ?>">
    <link rel="alternate" hreflang="x-default" href="<?= h($urlHu) ?>">
    <?= events_public_favicon_head_markup() ?>
    <link rel="stylesheet" href="<?= h($cssUrl) ?>">
</head>
<body class="event-public-page event-public-page--catalog">
<?php require __DIR__ . '/partials/admin_float_tools.php'; ?>
<div class="event-shell">
<article class="event-public organizer-public djs-public">
    <header class="event-public__hero event-public__hero--bar-only">
        <?php $S = $D; require __DIR__ . '/partials/public_shell_hero_bar.php'; ?>
        <h1 class="visually-hidden"><?= h($title) ?></h1>
    </header>

    <nav class="djs-public__jump" aria-label="<?= h((string) $D['local_nav_aria']) ?>">
        <a class="djs-public__jump-link" href="#<?= h($cmsAnchorCatalog) ?>"><?= h((string) $D['local_nav_djs']) ?></a>
        <?php if ($spotlightVisible !== []): ?>
            <a class="djs-public__jump-link" href="#<?= h($cmsAnchorSpotlight) ?>"><?= h((string) $D['local_nav_spotlight']) ?></a>
        <?php endif; ?>
        <a class="djs-public__jump-link" href="#<?= h($cmsAnchorAfter) ?>"><?= h((string) $D['local_nav_info']) ?></a>
    </nav>

    <div class="djs-public__lead<?= $leadModifier ?>">
        <section
            id="<?= h($cmsAnchorBefore) ?>"
            class="djs-public__cms djs-public__cms--before<?= $contentBefore !== '' ? ' djs-public__cms--filled event-rich-text' : '' ?>"
            aria-label="<?= h((string) $D['cms_before_aria']) ?>"
        >
            <?php if ($contentBefore !== ''): ?>
                <?= $contentBefore ?>
            <?php endif; ?>
        </section>

        <?php require __DIR__ . '/partials/public_dj_spotlight.php'; ?>
    </div>

    <section class="djs-public__catalog" id="<?= h($cmsAnchorCatalog) ?>" aria-labelledby="zenekarok-catalog-heading">
        <div class="djs-public__catalog-head">
            <h2 class="djs-public__catalog-title" id="zenekarok-catalog-heading"><?= h((string) $D['catalog_heading']) ?></h2>
        </div>

        <?php if ($zenekarRows === []): ?>
            <p class="organizer-public__empty"><?= h($D['empty']) ?></p>
        <?php else: ?>
            <?php require __DIR__ . '/partials/public_catalog_display_limit.php'; ?>
            <div class="djs-public__toolbar">
                <div class="djs-public__filter">
                    <label class="djs-public__filter-label" for="zenekarok-filter-input"><?= h($D['filter_label']) ?></label>
                    <input type="search" id="zenekarok-filter-input" class="djs-public__filter-input" placeholder="<?= h($D['filter_placeholder']) ?>" autocomplete="off">
                </div>
                <div class="djs-public__sort">
                    <label class="djs-public__sort-label" for="zenekarok-sort-select"><?= h($D['sort_label']) ?></label>
                    <select id="zenekarok-sort-select" class="djs-public__sort-select">
                        <option value="name_asc"><?= h($D['sort_name_asc']) ?></option>
                        <option value="name_desc"><?= h($D['sort_name_desc']) ?></option>
                        <option value="events_desc"><?= h($D['sort_events_desc']) ?></option>
                        <option value="events_asc"><?= h($D['sort_events_asc']) ?></option>
                        <option value="upcoming_desc"><?= h($D['sort_upcoming_desc']) ?></option>
                    </select>
                </div>
            </div>

            <p class="djs-public__empty-filter" id="zenekarok-empty-filter" hidden><?= h($D['empty_filter']) ?></p>

            <ul class="djs-public__grid" id="zenekarok-grid" role="list">
                <?php foreach ($zenekarRows as $zenekar): ?>
                    <?php
                    $zenekarId = (int) ($zenekar['id'] ?? 0);
                    $zenekarName = (string) ($zenekar['name'] ?? '');
                    $zenekarSlug = trim((string) ($zenekar['slug'] ?? ''));
                    $zenekarPhoto = trim((string) ($zenekar['photo_url'] ?? ''));
                    $zenekarLogo = trim((string) ($zenekar['logo_url'] ?? ''));
                    $zenekarMedia = $zenekarPhoto !== '' ? $zenekarPhoto : $zenekarLogo;
                    $zenekarPhotoAbs = $zenekarMedia !== '' ? events_absolute_url($zenekarMedia) : '';
                    $zenekarMediaIsLogo = events_public_dj_media_is_logo($zenekar);
                    $zenekarMediaStyle = $zenekarPhotoAbs !== '' ? events_public_dj_media_img_style($zenekar) : '';
                    $zenekarBrandLogo = events_public_dj_brand_logo_url($zenekar);
                    $zenekarBrandStyle = $zenekarBrandLogo !== '' ? events_public_dj_brand_logo_style($zenekar) : '';
                    $total = (int) ($zenekar['event_total'] ?? 0);
                    $upcoming = (int) ($zenekar['event_upcoming'] ?? 0);
                    $nextStart = (string) ($zenekar['next_event_start'] ?? '');
                    $href = $zenekarHref(['id' => $zenekarId, 'name' => $zenekarName, 'slug' => $zenekarSlug], $lang);
                    $nextTs = $nextStart !== '' ? strtotime($nextStart) : false;
                    $nextDisplay = $nextTs !== false
                        ? events_public_megjelenit_day_line($nextTs, $lang)
                        : '';
                    $nameSort = mb_strtolower($zenekarName, 'UTF-8');
                    $initials = events_public_zenekar_initials($zenekarName);
                    $cardMod = $upcoming > 0 ? ' djs-public__card--live' : '';
                    ?>
                    <li
                        class="djs-public__cell"
                        data-name="<?= h($nameSort) ?>"
                        data-events="<?= $total ?>"
                        data-upcoming="<?= $upcoming ?>"
                    >
                        <a class="djs-public__card djs-public__card--person<?= h($cardMod) ?>" href="<?= h($href) ?>" aria-label="<?= h($D['card_aria'] . ': ' . $zenekarName) ?>">
                            <span class="djs-public__card-media<?= $zenekarMediaIsLogo ? ' djs-public__card-media--logo' : '' ?>" aria-hidden="true">
                                <?php if ($zenekarPhotoAbs !== ''): ?>
                                    <img class="djs-public__card-photo<?= $zenekarMediaIsLogo ? ' djs-public__card-photo--logo' : '' ?>" src="<?= h($zenekarPhotoAbs) ?>" alt="" loading="lazy" decoding="async"<?= $zenekarMediaStyle !== '' ? ' style="' . h($zenekarMediaStyle) . '"' : '' ?>>
                                <?php else: ?>
                                    <span class="djs-public__card-initials"><?= h($initials) ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="djs-public__card-body">
                                <span class="djs-public__card-heading">
                                    <span class="djs-public__card-name"><?= h($zenekarName) ?></span>
                                    <?php if ($zenekarBrandLogo !== ''): ?>
                                        <span class="djs-public__card-logo" aria-hidden="true">
                                            <img class="djs-public__card-logo-img" src="<?= h($zenekarBrandLogo) ?>" alt="" loading="lazy" decoding="async"<?= $zenekarBrandStyle !== '' ? ' style="' . h($zenekarBrandStyle) . '"' : '' ?>>
                                        </span>
                                    <?php endif; ?>
                                </span>
                                <span class="djs-public__card-stats">
                                    <span class="djs-public__card-stat djs-public__card-stat--upcoming">
                                        <strong><?= $upcoming ?></strong> <?= h($D['events_upcoming']) ?>
                                    </span>
                                    <span class="djs-public__card-stat djs-public__card-stat--muted">
                                        <?= h((string) ($D['events_total_lead'] ?? '')) ?>
                                        <strong><?= $total ?></strong> <?= h($D['events_total']) ?>
                                    </span>
                                </span>
                                <?php if ($nextDisplay !== ''): ?>
                                    <span class="djs-public__card-next">
                                        <?= h($D['next_event']) ?>: <?= h($nextDisplay) ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php if ($zenekarRows !== []): ?>
        <div class="djs-public__dashboard">
            <section class="djs-public__stats" aria-labelledby="zenekarok-stats-heading">
                <h2 class="djs-public__section-title" id="zenekarok-stats-heading"><?= h((string) $D['stats_heading']) ?></h2>
                <ul class="djs-public__stat-grid" role="list">
                    <li class="djs-public__stat">
                        <span class="djs-public__stat-value"><?= (int) $hubStats['zenekar_total'] ?></span>
                        <span class="djs-public__stat-label"><?= h((string) $D['stat_djs']) ?></span>
                    </li>
                    <li class="djs-public__stat">
                        <span class="djs-public__stat-value"><?= (int) $hubStats['zenekar_with_events'] ?></span>
                        <span class="djs-public__stat-label"><?= h((string) $D['stat_djs_with_events']) ?></span>
                    </li>
                    <li class="djs-public__stat djs-public__stat--accent">
                        <span class="djs-public__stat-value"><?= (int) $hubStats['zenekar_with_upcoming'] ?></span>
                        <span class="djs-public__stat-label"><?= h((string) $D['stat_djs_upcoming']) ?></span>
                    </li>
                    <li class="djs-public__stat">
                        <span class="djs-public__stat-value"><?= (int) $hubStats['events_total'] ?></span>
                        <span class="djs-public__stat-label"><?= h((string) $D['stat_events']) ?></span>
                    </li>
                    <li class="djs-public__stat djs-public__stat--accent">
                        <span class="djs-public__stat-value"><?= (int) $hubStats['events_upcoming'] ?></span>
                        <span class="djs-public__stat-label"><?= h((string) $D['stat_events_upcoming']) ?></span>
                    </li>
                </ul>
            </section>

            <section class="djs-public__rankings" aria-label="<?= h((string) $D['stats_heading']) ?>">
                <div class="djs-public__rank-col">
                    <h2 class="djs-public__section-title"><?= h((string) $D['rank_events_heading']) ?></h2>
                    <?php if ($hubStats['top_by_events'] === []): ?>
                        <p class="djs-public__rank-empty"><?= h((string) $D['rank_empty']) ?></p>
                    <?php else: ?>
                        <ol class="djs-public__rank-list">
                            <?php foreach ($hubStats['top_by_events'] as $rankRow): ?>
                                <li>
                                    <a class="djs-public__rank-link" href="<?= h($zenekarHref($rankRow, $lang)) ?>">
                                        <span class="djs-public__rank-name"><?= h((string) $rankRow['name']) ?></span>
                                        <span class="djs-public__rank-count"><?= (int) $rankRow['count'] ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
                <div class="djs-public__rank-col">
                    <h2 class="djs-public__section-title"><?= h((string) $D['rank_upcoming_heading']) ?></h2>
                    <?php if ($hubStats['top_by_upcoming'] === []): ?>
                        <p class="djs-public__rank-empty"><?= h((string) $D['rank_empty']) ?></p>
                    <?php else: ?>
                        <ol class="djs-public__rank-list">
                            <?php foreach ($hubStats['top_by_upcoming'] as $rankRow): ?>
                                <li>
                                    <a class="djs-public__rank-link" href="<?= h($zenekarHref($rankRow, $lang)) ?>">
                                        <span class="djs-public__rank-name"><?= h((string) $rankRow['name']) ?></span>
                                        <span class="djs-public__rank-count"><?= (int) $rankRow['count'] ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
                <div class="djs-public__rank-col djs-public__rank-col--next">
                    <h2 class="djs-public__section-title"><?= h((string) $D['rank_next_heading']) ?></h2>
                    <?php if ($hubStats['next_up'] === []): ?>
                        <p class="djs-public__rank-empty"><?= h((string) $D['rank_empty']) ?></p>
                    <?php else: ?>
                        <ol class="djs-public__rank-list">
                            <?php foreach ($hubStats['next_up'] as $rankRow): ?>
                                <?php
                                $nextTs = strtotime((string) $rankRow['next_event_start']);
                                if ($nextTs === false) {
                                    $nextDisplay = '';
                                } elseif ($lang === 'en') {
                                    $nextDisplay = date('M j, Y', $nextTs);
                                } else {
                                    $nextDisplay = date('Y.m.d.', $nextTs);
                                }
                                ?>
                                <li>
                                    <a class="djs-public__rank-link djs-public__rank-link--stacked" href="<?= h($zenekarHref($rankRow, $lang)) ?>">
                                        <span class="djs-public__rank-name"><?= h((string) $rankRow['name']) ?></span>
                                        <?php if ($nextDisplay !== ''): ?>
                                            <span class="djs-public__rank-meta"><?= h($nextDisplay) ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    <?php endif; ?>

    <section
        id="<?= h($cmsAnchorAfter) ?>"
        class="djs-public__cms djs-public__cms--after<?= $contentAfter !== '' ? ' djs-public__cms--filled event-rich-text' : '' ?>"
        aria-label="<?= h((string) $D['cms_after_aria']) ?>"
    >
        <?php if ($contentAfter !== ''): ?>
            <?= $contentAfter ?>
        <?php endif; ?>
    </section>

    <footer class="event-public__footer">
        <?php require __DIR__ . '/partials/public_shell_footer.php'; ?>
    </footer>
</article>
</div>
<?php if ($zenekarRows !== []): ?>
<script>
(function () {
    var grid = document.getElementById('zenekarok-grid');
    var filterInput = document.getElementById('zenekarok-filter-input');
    var sortSelect = document.getElementById('zenekarok-sort-select');
    var emptyMsg = document.getElementById('zenekarok-empty-filter');
    if (!grid || !filterInput || !sortSelect) return;

    function cells() {
        return Array.prototype.slice.call(grid.querySelectorAll('.djs-public__cell'));
    }

    function applyFilterSort() {
        var q = (filterInput.value || '').trim().toLowerCase();
        var sort = sortSelect.value || 'name_asc';
        var list = cells();

        list.forEach(function (li) {
            var name = li.getAttribute('data-name') || '';
            li.hidden = q !== '' && name.indexOf(q) === -1;
        });

        var visible = list.filter(function (li) { return !li.hidden; });
        visible.sort(function (a, b) {
            var na = a.getAttribute('data-name') || '';
            var nb = b.getAttribute('data-name') || '';
            var ea = parseInt(a.getAttribute('data-events') || '0', 10);
            var eb = parseInt(b.getAttribute('data-events') || '0', 10);
            var ua = parseInt(a.getAttribute('data-upcoming') || '0', 10);
            var ub = parseInt(b.getAttribute('data-upcoming') || '0', 10);
            if (sort === 'name_desc') return nb.localeCompare(na, 'hu', { sensitivity: 'base' });
            if (sort === 'events_desc') return eb - ea || na.localeCompare(nb, 'hu', { sensitivity: 'base' });
            if (sort === 'events_asc') return ea - eb || na.localeCompare(nb, 'hu', { sensitivity: 'base' });
            if (sort === 'upcoming_desc') return ub - ua || eb - ea || na.localeCompare(nb, 'hu', { sensitivity: 'base' });
            return na.localeCompare(nb, 'hu', { sensitivity: 'base' });
        });

        visible.forEach(function (li) { grid.appendChild(li); });
        list.filter(function (li) { return li.hidden; }).forEach(function (li) { grid.appendChild(li); });

        if (emptyMsg) {
            emptyMsg.hidden = visible.length > 0;
        }
    }

    filterInput.addEventListener('input', applyFilterSort);
    sortSelect.addEventListener('change', applyFilterSort);
})();
</script>
<?php
$listLimitDefault = EVENTS_ADMIN_LIST_DEFAULT_LIMIT;
require __DIR__ . '/partials/admin_list_display_limit_script.php';
?>
<?php endif; ?>
<?php require __DIR__ . '/partials/public_dj_spotlight_script.php'; ?>
</body>
</html>
