<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_public_lang.php';
require_once __DIR__ . '/lib/event_public_organizers.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
require_once __DIR__ . '/lib/public_event_filters.php';

$lang = events_public_resolve_megjelenit_lang();
events_public_send_noindex_header();
$D = events_public_organizers_catalog_strings($lang);

$db = getDb();
$listLimitParsed = events_admin_list_limit_from_get(EVENTS_ADMIN_LIST_DEFAULT_LIMIT);
$list_limit = $listLimitParsed['sql_limit'];
$listLimitValue = $listLimitParsed['value'];
$listTotalInDb = events_public_organizer_total_count($db);
$limitParams = events_public_catalog_get_params($listLimitValue);
$orgRows = events_public_organizer_catalog($db, events_public_post_status(), $list_limit);

$title = (string) $D['page_title'];
$desc = (string) $D['page_desc'];
$canonical = events_absolute_url(events_public_organizers_catalog_page_url($lang, $limitParams));
$ogPageUrl = $canonical;
$cssUrl = events_url('assets/event_public.css');
$urlHu = events_public_organizers_catalog_lang_switch_url('hu', $limitParams);
$urlEn = events_public_organizers_catalog_lang_switch_url('en', $limitParams);
$htmlLang = $lang === 'en' ? 'en' : 'hu';
$S = $D;

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
<article class="event-public organizer-public djs-public organizers-public">
    <header class="event-public__hero">
        <?php $S = $D; require __DIR__ . '/partials/public_shell_hero_bar.php'; ?>
        <div class="event-public__hero-inner">
            <p class="event-public__eyebrow">🎪 <?= h((string) $D['eyebrow']) ?></p>
            <h1 class="event-public__title"><?= h($title) ?></h1>
        </div>
    </header>

    <section class="djs-public__catalog" aria-labelledby="organizers-catalog-heading">
        <h2 class="visually-hidden" id="organizers-catalog-heading"><?= h($title) ?></h2>

        <?php if ($orgRows === []): ?>
            <p class="organizer-public__empty"><?= h($D['empty']) ?></p>
        <?php else: ?>
            <?php require __DIR__ . '/partials/public_catalog_display_limit.php'; ?>
            <div class="djs-public__toolbar">
                <div class="djs-public__filter">
                    <label class="djs-public__filter-label" for="organizers-filter-input"><?= h($D['filter_label']) ?></label>
                    <input type="search" id="organizers-filter-input" class="djs-public__filter-input" placeholder="<?= h($D['filter_placeholder']) ?>" autocomplete="off">
                </div>
                <div class="djs-public__sort">
                    <label class="djs-public__sort-label" for="organizers-sort-select"><?= h($D['sort_label']) ?></label>
                    <select id="organizers-sort-select" class="djs-public__sort-select">
                        <option value="name_asc"><?= h($D['sort_name_asc']) ?></option>
                        <option value="name_desc"><?= h($D['sort_name_desc']) ?></option>
                        <option value="events_desc"><?= h($D['sort_events_desc']) ?></option>
                        <option value="events_asc"><?= h($D['sort_events_asc']) ?></option>
                        <option value="upcoming_desc"><?= h($D['sort_upcoming_desc']) ?></option>
                    </select>
                </div>
            </div>

            <p class="djs-public__empty-filter" id="organizers-empty-filter" hidden><?= h($D['empty_filter']) ?></p>

            <ul class="djs-public__grid" id="organizers-grid" role="list">
                <?php foreach ($orgRows as $org): ?>
                    <?php
                    $orgId = (int) ($org['id'] ?? 0);
                    $orgName = (string) ($org['name'] ?? '');
                    $total = (int) ($org['event_total'] ?? 0);
                    $upcoming = (int) ($org['event_upcoming'] ?? 0);
                    $nextStart = (string) ($org['next_event_start'] ?? '');
                    $nextTs = $nextStart !== '' ? strtotime($nextStart) : false;
                    $nextDisplay = $nextTs !== false
                        ? events_public_event_start_date_time_display(false, $nextTs, $lang)
                        : '';
                    $href = events_public_organizer_page_url($orgId, $lang);
                    $nameSort = mb_strtolower($orgName, 'UTF-8');
                    ?>
                    <li
                        class="djs-public__cell"
                        data-name="<?= h($nameSort) ?>"
                        data-events="<?= $total ?>"
                        data-upcoming="<?= $upcoming ?>"
                    >
                        <a class="djs-public__card" href="<?= h($href) ?>" aria-label="<?= h($D['card_aria'] . ': ' . $orgName) ?>">
                            <span class="djs-public__card-icon" aria-hidden="true">🎪</span>
                            <span class="djs-public__card-name"><?= h($orgName) ?></span>
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
<?php if ($orgRows !== []): ?>
<script>
(function () {
    var grid = document.getElementById('organizers-grid');
    var filterInput = document.getElementById('organizers-filter-input');
    var sortSelect = document.getElementById('organizers-sort-select');
    var emptyMsg = document.getElementById('organizers-empty-filter');
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
