<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
require_once __DIR__ . '/lib/dance_teachers.php';
requireLogin();

$db = getDb();
dance_schools_ensure_schema($db);

if (!events_tags_tables_available($db) || !events_tag_types_tables_available($db)) {
    $pageTitle = 'Tánctanárok';
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
$listTotalInDb = dance_teachers_admin_total_count($db);

$filters = dance_teachers_admin_filters_from_request();
$f_q = $filters['f_q'];
$order = $filters['order'];
$dir_param = $filters['dir_param'];
$get_params = events_admin_list_limit_merge_get_params($filters['get_params'], $listLimitValue);

$rows = dance_teachers_admin_fetch($db, $filters, $list_limit);
$listDisplayedCount = count($rows);
$hasFilters = $f_q !== '';
$colspan = 7;

/** @param array<string, string> $params */
$teacherSortTh = static function (string $label, string $orderCol, string $currentOrder, string $currentDir, array $params): string {
    $rel = sort_url($params, $orderCol, $currentOrder, $currentDir);
    $qs = ltrim($rel, '?');
    $href = events_url('tanarok_admin.php' . ($qs !== '' ? '?' . $qs : ''));
    $arrow = '';
    if ($currentOrder === $orderCol) {
        $arrow = $currentDir === 'asc'
            ? ' <span class="sort-arrow" aria-hidden="true">↑</span>'
            : ' <span class="sort-arrow" aria-hidden="true">↓</span>';
    }

    return '<a href="' . h($href) . '" class="th-sort">' . h($label) . $arrow . '</a>';
};

$pageTitle = 'Tánctanárok';
$mainContentClass = 'main-content main-content--fullwidth';

$adminFloatTools = [
    [
        'href' => events_url('tanar_letrehoz.php'),
        'title' => 'Új tánctanár',
        'aria' => 'Új tánctanár létrehozása',
        'icon' => 'plus',
    ],
    [
        'href' => events_url('tanciskolak_admin.php'),
        'title' => 'Tánciskolák',
        'aria' => 'Tánciskolák listája',
        'icon' => 'back',
    ],
];
$adminFloatToolsRequireLogin = false;

require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<?php require __DIR__ . '/partials/admin_float_tools.php'; ?>

<div class="card events-admin-card">
    <form method="get" action="<?= h(events_url('tanarok_admin.php')) ?>" class="events-admin-form" id="tanarok-admin-filter-form">
        <input type="hidden" name="order" value="<?= h($order) ?>">
        <input type="hidden" name="dir" value="<?= h($dir_param) ?>">

        <div class="events-list-head">
            <div class="events-list-head__start">
                <h1 class="events-list-title card-title" style="margin:0;">Tánctanárok</h1>
                <p class="help" style="margin:0.35rem 0 0;">Tánctanárok (tanar típusú címkék) és profiladatok.</p>
                <?php
                $listLimitInForm = true;
                $listLimitStandalone = true;
                require __DIR__ . '/partials/admin_list_display_limit.php';
                ?>
            </div>
            <div class="events-list-actions">
                <a href="<?= h(events_url('tanarok_admin.php')) ?>" class="btn btn-secondary">Szűrők és rendezés törlése</a>
                <a href="<?= h(events_url('tanar_letrehoz.php')) ?>" class="btn btn-primary">Új tánctanár</a>
                <a href="<?= h(events_url('tanciskolak_admin.php')) ?>" class="btn btn-secondary">Tánciskolák</a>
            </div>
        </div>

        <section class="events-filters-shell" aria-label="Szűrők">
            <div class="events-filters-grid">
                <div class="events-filter-field">
                    <label class="events-filter-label<?= $f_q !== '' ? ' events-filter-label--active' : '' ?>" for="teacher-f-q">Keresés</label>
                    <input class="events-filter-input" type="search" name="f_q" id="teacher-f-q" value="<?= h($f_q) ?>" placeholder="Név, város, slug vagy ID…" autocomplete="off">
                </div>
            </div>
        </section>

        <div class="table-wrap events-admin-table-wrap">
            <table class="sortable-table events-admin-table">
                <thead>
                    <tr>
                        <th class="events-djs-admin__th-photo"><?= $teacherSortTh('Fotó', 'photo', $order, $dir_param, $get_params) ?></th>
                        <th><?= $teacherSortTh('Név', 'name', $order, $dir_param, $get_params) ?></th>
                        <th><?= $teacherSortTh('Város', 'city', $order, $dir_param, $get_params) ?></th>
                        <th><?= $teacherSortTh('Magánóra', 'private', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num"><?= $teacherSortTh('Iskolák', 'schools', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num"><?= $teacherSortTh('Megtekintések', 'views', $order, $dir_param, $get_params) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="<?= (int) $colspan ?>">
                                <?php if ($hasFilters): ?>
                                    Nincs a szűrésnek megfelelő tánctanár.
                                <?php else: ?>
                                    Nincs tánctanár. Adj hozzá újat.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $tid = (int) $r['id'];
                            $tname = (string) $r['name'];
                            $photo = trim((string) ($r['photo_url'] ?? ''));
                            $logo = trim((string) ($r['logo_url'] ?? ''));
                            $thumb = $photo !== '' ? $photo : $logo;
                            $photoAbs = $thumb !== '' ? events_absolute_url($thumb) : '';
                            $thumbIsLogo = $photo === '' && $logo !== '';
                            $editUrl = events_url('tanar_szerkeszt.php?id=') . $tid;
                            $offersPrivate = !empty($r['offers_private_lessons']);
                            ?>
                            <tr>
                                <td class="events-djs-admin__td-photo">
                                    <a href="<?= h($editUrl) ?>" class="events-djs-admin__thumb-link" title="Szerkesztés">
                                        <?php if ($photoAbs !== ''): ?>
                                            <img class="events-djs-admin__thumb<?= $thumbIsLogo ? ' events-djs-admin__thumb--logo' : '' ?>" src="<?= h($photoAbs) ?>" alt="" loading="lazy" width="40" height="40">
                                        <?php else: ?>
                                            <span class="events-djs-admin__thumb events-djs-admin__thumb--empty" aria-hidden="true">🕺</span>
                                        <?php endif; ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="<?= h($editUrl) ?>"><strong><?= h($tname) ?></strong></a>
                                </td>
                                <td><?= h(trim((string) ($r['city'] ?? '')) !== '' ? (string) $r['city'] : '—') ?></td>
                                <td><?= $offersPrivate ? 'Igen' : '<span class="text-muted">Nem</span>' ?></td>
                                <td class="td-num"><?= (int) ($r['school_count'] ?? 0) ?></td>
                                <td class="td-num"><?= (int) ($r['view_count'] ?? 0) ?></td>
                                <td><a href="<?= h($editUrl) ?>" class="btn btn-secondary btn-sm">Szerkesztés</a></td>
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
    var key = 'tanarok-admin-search-focus';
    var input = document.getElementById('teacher-f-q');
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
