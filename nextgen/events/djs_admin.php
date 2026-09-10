<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
require_once __DIR__ . '/lib/djs_admin.php';
require_once __DIR__ . '/lib/event_public_lang.php';
requireLogin();

$db = getDb();

if (!events_tags_tables_available($db) || !events_tag_types_tables_available($db)) {
    $pageTitle = 'DJ-k';
    $mainContentClass = 'main-content main-content--fullwidth';
    require_once dirname(__DIR__) . '/partials/header.php';
    echo '<div class="card events-admin-card">';
    echo '<p class="alert alert-error">Hiányoznak a címke / típus táblák.</p>';
    echo '<p><a href="' . h(events_url('events_admin.php')) . '" class="btn btn-secondary">Vissza</a></p>';
    echo '</div>';
    require_once dirname(__DIR__) . '/partials/footer.php';
    exit;
}

$listLimitParsed = events_admin_list_limit_from_get();
$list_limit = $listLimitParsed['sql_limit'];
$listLimitValue = $listLimitParsed['value'];
$listTotalInDb = events_djs_admin_total_count($db);

$filters = events_djs_admin_filters_from_request();
$f_q = $filters['f_q'];
$order = $filters['order'];
$dir_param = $filters['dir_param'];
$get_params = events_admin_list_limit_merge_get_params($filters['get_params'], $listLimitValue);

$rows = events_djs_admin_fetch($db, $filters, $list_limit);
$listDisplayedCount = count($rows);
$hasFilters = $f_q !== '';
$colspan = 9;

/** @param array<string, string> $params */
$djSortTh = static function (string $label, string $orderCol, string $currentOrder, string $currentDir, array $params): string {
    $rel = sort_url($params, $orderCol, $currentOrder, $currentDir);
    $qs = ltrim($rel, '?');
    $href = events_url('djs_admin.php' . ($qs !== '' ? '?' . $qs : ''));
    $arrow = '';
    if ($currentOrder === $orderCol) {
        $arrow = $currentDir === 'asc'
            ? ' <span class="sort-arrow" aria-hidden="true">↑</span>'
            : ' <span class="sort-arrow" aria-hidden="true">↓</span>';
    }

    return '<a href="' . h($href) . '" class="th-sort">' . h($label) . $arrow . '</a>';
};

$pageTitle = 'DJ-k';
$mainContentClass = 'main-content main-content--fullwidth';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card">
    <form method="get" action="<?= h(events_url('djs_admin.php')) ?>" class="events-admin-form" id="djs-admin-filter-form">
        <input type="hidden" name="order" value="<?= h($order) ?>">
        <input type="hidden" name="dir" value="<?= h($dir_param) ?>">

        <div class="events-list-head">
            <div class="events-list-head__start">
                <h1 class="events-list-title card-title" style="margin:0;">DJ-k</h1>
                <?php
                $listLimitInForm = true;
                $listLimitStandalone = true;
                require __DIR__ . '/partials/admin_list_display_limit.php';
                ?>
            </div>
            <div class="events-list-actions">
                <a href="<?= h(events_url('djs_admin.php')) ?>" class="btn btn-secondary">Szűrők és rendezés törlése</a>
                <a href="<?= h(events_url('dj_letrehoz.php')) ?>" class="btn btn-primary">Új DJ</a>
                <a href="<?= h(events_url('organizers.php')) ?>" class="btn btn-secondary">Szervezők</a>
            </div>
        </div>

        <section class="events-filters-shell" aria-label="Szűrők">
            <div class="events-filters-grid">
                <div class="events-filter-field">
                    <label class="events-filter-label<?= $f_q !== '' ? ' events-filter-label--active' : '' ?>" for="dj-f-q">Keresés</label>
                    <input class="events-filter-input" type="search" name="f_q" id="dj-f-q" value="<?= h($f_q) ?>" placeholder="Név, slug vagy ID…" autocomplete="off">
                </div>
            </div>
        </section>

        <div class="table-wrap events-admin-table-wrap">
            <table class="sortable-table events-admin-table">
                <thead>
                    <tr>
                        <th class="events-djs-admin__th-photo"><?= $djSortTh('Fotó', 'photo', $order, $dir_param, $get_params) ?></th>
                        <th><?= $djSortTh('ID', 'id', $order, $dir_param, $get_params) ?></th>
                        <th><?= $djSortTh('Név', 'name', $order, $dir_param, $get_params) ?></th>
                        <th><?= $djSortTh('Slug', 'slug', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num"><?= $djSortTh('Események', 'events', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num"><?= $djSortTh('Közzétéve', 'published', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num"><?= $djSortTh('Közelgő', 'upcoming', $order, $dir_param, $get_params) ?></th>
                        <th><?= $djSortTh('Utolsó esemény', 'last_event', $order, $dir_param, $get_params) ?></th>
                        <th><?= $djSortTh('Következő', 'next_event', $order, $dir_param, $get_params) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="<?= (int) $colspan ?>">
                                <?php if ($hasFilters): ?>
                                    Nincs a szűrésnek megfelelő DJ.
                                <?php else: ?>
                                    Nincs DJ. Adj hozzá újat.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $did = (int) $r['id'];
                            $dname = (string) $r['name'];
                            $dslug = (string) $r['slug'];
                            $photo = trim((string) $r['photo_url']);
                            $logo = trim((string) ($r['logo_url'] ?? ''));
                            $thumb = $photo !== '' ? $photo : $logo;
                            $photoAbs = $thumb !== '' ? events_absolute_url($thumb) : '';
                            $thumbIsLogo = $photo === '' && $logo !== '';
                            $editUrl = events_url('dj_szerkeszt.php?id=') . $did;
                            $publicUrl = $dslug !== ''
                                ? events_public_dj_page_url($dslug, 'hu')
                                : events_public_tag_page_url($did, 'hu');
                            $eventsUrl = events_djs_admin_events_filter_url($did);
                            $eventCount = (int) ($r['event_count'] ?? 0);
                            $publishedCount = (int) ($r['published_count'] ?? 0);
                            $upcomingCount = (int) ($r['upcoming_count'] ?? 0);
                            ?>
                            <tr>
                                <td class="events-djs-admin__td-photo">
                                    <a href="<?= h($editUrl) ?>" class="events-djs-admin__thumb-link" title="Szerkesztés">
                                        <?php if ($photoAbs !== ''): ?>
                                            <img class="events-djs-admin__thumb<?= $thumbIsLogo ? ' events-djs-admin__thumb--logo' : '' ?>" src="<?= h($photoAbs) ?>" alt="" loading="lazy" width="40" height="40">
                                        <?php else: ?>
                                            <span class="events-djs-admin__thumb events-djs-admin__thumb--empty" aria-hidden="true">🎧</span>
                                        <?php endif; ?>
                                    </a>
                                </td>
                                <td><a href="<?= h($editUrl) ?>"><?= $did ?></a></td>
                                <td>
                                    <a href="<?= h($editUrl) ?>"><strong><?= h($dname) ?></strong></a>
                                    <div class="events-admin-row-actions">
                                        <a href="<?= h($publicUrl) ?>" target="_blank" rel="noopener">Nyilvános</a>
                                    </div>
                                </td>
                                <td><code><?= h($dslug !== '' ? $dslug : '—') ?></code></td>
                                <td class="td-num">
                                    <?php if ($eventCount > 0): ?>
                                        <a href="<?= h($eventsUrl) ?>" class="events-cell-link" title="Események szűrése erre a DJ-re"><?= $eventCount ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-num">
                                    <?php if ($publishedCount > 0): ?>
                                        <?= $publishedCount ?>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-num">
                                    <?php if ($upcomingCount > 0): ?>
                                        <?= $upcomingCount ?>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= h(events_djs_admin_format_datetime($r['last_event_at'] ?? null)) ?></td>
                                <td><?= h(events_djs_admin_format_datetime($r['next_event_at'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>
<?php
require __DIR__ . '/partials/admin_event_filters_script.php';
?>
<script>
(function () {
    var key = 'djs-admin-search-focus';
    var input = document.getElementById('dj-f-q');
    if (!input) return;
    try {
        var raw = sessionStorage.getItem(key);
        if (raw) {
            sessionStorage.removeItem(key);
            var data = JSON.parse(raw);
            if (data && typeof data.pos === 'number') {
                input.focus({ preventScroll: true });
                var pos = Math.min(Math.max(0, data.pos), (input.value || '').length);
                if (typeof input.setSelectionRange === 'function') {
                    input.setSelectionRange(pos, pos);
                }
            }
        }
    } catch (e) {}
    input.addEventListener('input', function () {
        try {
            sessionStorage.setItem(key, JSON.stringify({
                pos: typeof input.selectionStart === 'number' ? input.selectionStart : (input.value || '').length
            }));
        } catch (e) {}
    });
})();
</script>
<?php
require_once dirname(__DIR__) . '/partials/footer.php';
