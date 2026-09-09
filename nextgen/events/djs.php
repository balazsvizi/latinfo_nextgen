<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_public_lang.php';
require_once __DIR__ . '/lib/event_public_djs.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
require_once __DIR__ . '/lib/public_event_filters.php';

$lang = events_public_resolve_megjelenit_lang();
$D = events_public_djs_strings($lang);

$listLimitParsed = events_admin_list_limit_from_get(EVENTS_ADMIN_LIST_DEFAULT_LIMIT);
$listLimitValue = $listLimitParsed['value'];
$limitParams = events_public_catalog_get_params($listLimitValue);

if (events_public_is_legacy_djs_request()) {
    $legacyParams = $limitParams;
    if (isset($_GET['lang']) && (string) $_GET['lang'] !== '') {
        $legacyParams['lang'] = (string) $_GET['lang'];
    }
    $targetLang = ($legacyParams['lang'] ?? $lang) === 'en' ? 'en' : 'hu';
    unset($legacyParams['lang']);
    events_public_redirect_to(events_public_djs_page_url($targetLang, $legacyParams));
}

events_public_send_noindex_follow_header();

$db = getDb();
$list_limit = $listLimitParsed['sql_limit'];
$listTotalInDb = events_public_dj_total_count($db);
$publishedStatus = events_public_post_status();
$djRowsAll = events_public_dj_catalog($db, $publishedStatus, null);
$hubStats = events_public_dj_hub_stats($db, $publishedStatus, $djRowsAll);
$djRows = $list_limit === null ? $djRowsAll : array_slice($djRowsAll, 0, $list_limit);

$title = (string) $D['page_title'];
$desc = (string) $D['page_desc'];
$canonical = events_absolute_url(events_public_djs_page_url('hu'));
$ogPageUrl = events_absolute_url(events_public_djs_page_url($lang, $limitParams));
$cssUrl = events_url('assets/event_public.css');
$urlHu = events_public_djs_lang_switch_url('hu', $limitParams);
$urlEn = events_public_djs_lang_switch_url('en', $limitParams);
$htmlLang = $lang === 'en' ? 'en' : 'hu';
$S = $D;

/**
 * @param array{id:int,name:string,slug:string} $row
 */
$djHref = static function (array $row, string $lang): string {
    $slug = trim((string) ($row['slug'] ?? ''));
    $id = (int) ($row['id'] ?? 0);
    if ($slug !== '') {
        return events_public_dj_page_url($slug, $lang);
    }

    return events_public_tag_page_url($id, $lang);
};

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
<body class="event-public-page">
<div class="event-shell">
<article class="event-public organizer-public djs-public">
    <header class="event-public__hero">
        <?php $S = $D; require __DIR__ . '/partials/public_shell_hero_bar.php'; ?>
        <div class="event-public__hero-inner djs-public__hero-inner">
            <p class="event-public__eyebrow">🎧 <?= h((string) $D['eyebrow']) ?></p>
            <h1 class="event-public__title"><?= h($title) ?></h1>
            <p class="djs-public__intro"><?= h((string) $D['page_intro']) ?></p>
            <?php if ($djRows !== []): ?>
                <p class="djs-public__hero-cta">
                    <a class="djs-public__hero-cta-link" href="#djs-catalog-heading"><?= h((string) $D['catalog_heading']) ?></a>
                </p>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($djRows !== []): ?>
        <div class="djs-public__dashboard">
            <section class="djs-public__stats" aria-labelledby="djs-stats-heading">
                <h2 class="djs-public__section-title" id="djs-stats-heading"><?= h((string) $D['stats_heading']) ?></h2>
                <ul class="djs-public__stat-grid" role="list">
                    <li class="djs-public__stat">
                        <span class="djs-public__stat-value"><?= (int) $hubStats['dj_total'] ?></span>
                        <span class="djs-public__stat-label"><?= h((string) $D['stat_djs']) ?></span>
                    </li>
                    <li class="djs-public__stat">
                        <span class="djs-public__stat-value"><?= (int) $hubStats['dj_with_events'] ?></span>
                        <span class="djs-public__stat-label"><?= h((string) $D['stat_djs_with_events']) ?></span>
                    </li>
                    <li class="djs-public__stat djs-public__stat--accent">
                        <span class="djs-public__stat-value"><?= (int) $hubStats['dj_with_upcoming'] ?></span>
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
                                    <a class="djs-public__rank-link" href="<?= h($djHref($rankRow, $lang)) ?>">
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
                                    <a class="djs-public__rank-link" href="<?= h($djHref($rankRow, $lang)) ?>">
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
                                    <a class="djs-public__rank-link djs-public__rank-link--stacked" href="<?= h($djHref($rankRow, $lang)) ?>">
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

    <section class="djs-public__catalog" aria-labelledby="djs-catalog-heading">
        <h2 class="djs-public__section-title" id="djs-catalog-heading"><?= h((string) $D['catalog_heading']) ?></h2>

        <?php if ($djRows === []): ?>
            <p class="organizer-public__empty"><?= h($D['empty']) ?></p>
        <?php else: ?>
            <?php require __DIR__ . '/partials/public_catalog_display_limit.php'; ?>
            <div class="djs-public__toolbar">
                <div class="djs-public__filter">
                    <label class="djs-public__filter-label" for="djs-filter-input"><?= h($D['filter_label']) ?></label>
                    <input type="search" id="djs-filter-input" class="djs-public__filter-input" placeholder="<?= h($D['filter_placeholder']) ?>" autocomplete="off">
                </div>
                <div class="djs-public__sort">
                    <label class="djs-public__sort-label" for="djs-sort-select"><?= h($D['sort_label']) ?></label>
                    <select id="djs-sort-select" class="djs-public__sort-select">
                        <option value="name_asc"><?= h($D['sort_name_asc']) ?></option>
                        <option value="name_desc"><?= h($D['sort_name_desc']) ?></option>
                        <option value="events_desc"><?= h($D['sort_events_desc']) ?></option>
                        <option value="events_asc"><?= h($D['sort_events_asc']) ?></option>
                        <option value="upcoming_desc"><?= h($D['sort_upcoming_desc']) ?></option>
                    </select>
                </div>
            </div>

            <p class="djs-public__empty-filter" id="djs-empty-filter" hidden><?= h($D['empty_filter']) ?></p>

            <ul class="djs-public__grid" id="djs-grid" role="list">
                <?php foreach ($djRows as $dj): ?>
                    <?php
                    $djId = (int) ($dj['id'] ?? 0);
                    $djName = (string) ($dj['name'] ?? '');
                    $djSlug = trim((string) ($dj['slug'] ?? ''));
                    $djPhoto = trim((string) ($dj['photo_url'] ?? ''));
                    $djPhotoAbs = $djPhoto !== '' ? events_absolute_url($djPhoto) : '';
                    $total = (int) ($dj['event_total'] ?? 0);
                    $upcoming = (int) ($dj['event_upcoming'] ?? 0);
                    $nextStart = (string) ($dj['next_event_start'] ?? '');
                    $href = $djHref(['id' => $djId, 'name' => $djName, 'slug' => $djSlug], $lang);
                    $nextTs = $nextStart !== '' ? strtotime($nextStart) : false;
                    $nextDisplay = $nextTs !== false
                        ? events_public_event_start_date_time_display(false, $nextTs, $lang)
                        : '';
                    $nameSort = mb_strtolower($djName, 'UTF-8');
                    $initials = events_public_dj_initials($djName);
                    $cardMod = $upcoming > 0 ? ' djs-public__card--live' : '';
                    ?>
                    <li
                        class="djs-public__cell"
                        data-name="<?= h($nameSort) ?>"
                        data-events="<?= $total ?>"
                        data-upcoming="<?= $upcoming ?>"
                    >
                        <a class="djs-public__card djs-public__card--person<?= h($cardMod) ?>" href="<?= h($href) ?>" aria-label="<?= h($D['card_aria'] . ': ' . $djName) ?>">
                            <span class="djs-public__card-media" aria-hidden="true">
                                <?php if ($djPhotoAbs !== ''): ?>
                                    <img class="djs-public__card-photo" src="<?= h($djPhotoAbs) ?>" alt="" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <span class="djs-public__card-initials"><?= h($initials) ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="djs-public__card-body">
                                <span class="djs-public__card-name"><?= h($djName) ?></span>
                                <span class="djs-public__card-stats">
                                    <span class="djs-public__card-stat djs-public__card-stat--muted">
                                        <strong><?= $total ?></strong> <?= h($D['events_total']) ?>
                                    </span>
                                    <span class="djs-public__card-stat djs-public__card-stat--upcoming">
                                        <strong><?= $upcoming ?></strong> <?= h($D['events_upcoming']) ?>
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

    <footer class="event-public__footer">
        <?php require __DIR__ . '/partials/public_shell_footer.php'; ?>
    </footer>
</article>
</div>
<?php if ($djRows !== []): ?>
<script>
(function () {
    var grid = document.getElementById('djs-grid');
    var filterInput = document.getElementById('djs-filter-input');
    var sortSelect = document.getElementById('djs-sort-select');
    var emptyMsg = document.getElementById('djs-empty-filter');
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
</body>
</html>
