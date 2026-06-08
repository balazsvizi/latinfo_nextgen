<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_public_lang.php';
require_once __DIR__ . '/lib/event_public_djs.php';

$lang = events_public_resolve_megjelenit_lang();
$D = events_public_djs_strings($lang);

$db = getDb();
$djRows = events_public_dj_catalog($db, events_public_post_status());

$title = (string) $D['page_title'];
$desc = (string) $D['page_desc'];
$canonical = events_absolute_url(events_public_djs_page_url($lang));
$ogPageUrl = events_absolute_url(events_public_djs_page_url($lang));
$cssUrl = events_url('assets/event_public.css');
$urlHu = events_public_djs_lang_switch_url('hu');
$urlEn = events_public_djs_lang_switch_url('en');
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
    <div class="event-shell-toolbar">
        <div class="event-shell-toolbar__leading">
            <a class="event-brand-logo" href="<?= h($latinfoHomeUrl) ?>" title="<?= h($D['logo_home_title']) ?>" aria-label="<?= h($D['logo_home_aria']) ?>">
                <img src="<?= h($latinfoLogoSrc) ?>" alt="<?= h($D['logo_alt']) ?>" width="180" height="48" decoding="async" fetchpriority="high">
            </a>
        </div>
        <div class="event-lang-switch" role="navigation" aria-label="<?= h($D['lang_nav']) ?>">
            <a class="event-lang-switch__link<?= $lang === 'hu' ? ' is-active' : '' ?>" href="<?= h($urlHu) ?>" hreflang="hu" lang="hu"><?= h($D['lang_hu']) ?></a>
            <span class="event-lang-switch__sep" aria-hidden="true">|</span>
            <a class="event-lang-switch__link<?= $lang === 'en' ? ' is-active' : '' ?>" href="<?= h($urlEn) ?>" hreflang="en" lang="en"><?= h($D['lang_en']) ?></a>
        </div>
    </div>

<article class="event-public organizer-public djs-public">
    <header class="event-public__hero">
        <div class="event-public__hero-inner">
            <p class="event-public__eyebrow">🎧 <?= h((string) $D['eyebrow']) ?></p>
            <h1 class="event-public__title"><?= h($title) ?></h1>
        </div>
    </header>

    <section class="djs-public__catalog" aria-labelledby="djs-catalog-heading">
        <h2 class="visually-hidden" id="djs-catalog-heading"><?= h($title) ?></h2>

        <?php if ($djRows === []): ?>
            <p class="organizer-public__empty"><?= h($D['empty']) ?></p>
        <?php else: ?>
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
                    $upcoming = (int) ($dj['event_upcoming'] ?? 0);
                    $nextStart = (string) ($dj['next_event_start'] ?? '');
                    $nextTs = $nextStart !== '' ? strtotime($nextStart) : false;
                    $nextDisplay = $nextTs !== false
                        ? events_public_event_start_date_time_display(false, $nextTs, $lang)
                        : '';
                    $href = events_public_tag_page_url($djId, $lang);
                    $nameSort = mb_strtolower($djName, 'UTF-8');
                    ?>
                    <li
                        class="djs-public__cell"
                        data-name="<?= h($nameSort) ?>"
                        data-events="<?= $upcoming ?>"
                        data-upcoming="<?= $upcoming ?>"
                    >
                        <a class="djs-public__card" href="<?= h($href) ?>" aria-label="<?= h($D['card_aria'] . ': ' . $djName) ?>">
                            <span class="djs-public__card-icon" aria-hidden="true">🎧</span>
                            <span class="djs-public__card-name"><?= h($djName) ?></span>
                            <span class="djs-public__card-stats">
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
        <p class="event-site-line">
            <a href="<?= h($latinfoHomeUrl) ?>"><?= h($D['footer_home_link']) ?></a>
        </p>
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
<?php endif; ?>
</body>
</html>
