<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
require_once __DIR__ . '/lib/dance_schools.php';
requireLogin();

$db = getDb();
dance_schools_ensure_schema($db);

$seedRedirect = events_url('tanciskolak_admin.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seed_dance_schools'])) {
    if (!isSuperadmin()) {
        flash('error', 'Nincs jogosultságod ehhez a művelethez.');
        redirect($seedRedirect);
    }
    csrf_require('dance_schools_seed', '_csrf', $seedRedirect);
    require_once dirname(__DIR__) . '/tools/seed_dance_schools.php';
    @set_time_limit(120);
    $result = dance_schools_seed_run($db);
    $created = (int) ($result['created'] ?? 0);
    $updated = (int) ($result['updated'] ?? 0);
    $errors = (int) ($result['errors'] ?? 0);
    $total = (int) ($result['total'] ?? 0);
    if (!empty($result['error'])) {
        flash('error', (string) $result['error']);
    } elseif ($errors > 0) {
        flash('error', "Seed kész hibákkal. Új: {$created}, frissítve: {$updated}, hiba: {$errors}, összesen: {$total}.");
    } else {
        flash('success', "Seed kész. Új: {$created}, frissítve: {$updated}, összesen: {$total}. Az új iskolák Import státuszt kaptak.");
    }
    redirect($seedRedirect);
}

$f_name = trim((string) ($_GET['f_name'] ?? $_GET['f_q'] ?? ''));
$f_city = trim((string) ($_GET['f_city'] ?? ''));
$f_status = trim((string) ($_GET['f_status'] ?? ''));
if (!isset(dance_school_status_labels()[$f_status])) {
    $f_status = '';
}
$f_tag = trim((string) ($_GET['f_tag'] ?? ''));
$countFilter = static function (string $key): string {
    $raw = trim((string) ($_GET[$key] ?? ''));

    return $raw !== '' && ctype_digit($raw) ? $raw : '';
};
$f_locations = $countFilter('f_locations');
$f_teachers = $countFilter('f_teachers');
$f_events = $countFilter('f_events');
$f_views = $countFilter('f_views');
$f_active = (string) ($_GET['f_active'] ?? '');
if ($f_active !== '1' && $f_active !== '0') {
    $f_active = '';
}
$allowedOrder = ['name', 'city', 'status', 'tags', 'locations', 'teachers', 'events', 'views', 'active', 'id', 'slug'];
if (isset($_GET['order']) && in_array((string) $_GET['order'], $allowedOrder, true)) {
    $order = (string) $_GET['order'];
    $dir_param = isset($_GET['dir']) && strtolower((string) $_GET['dir']) === 'asc' ? 'asc' : 'desc';
} else {
    $order = 'name';
    $dir_param = 'asc';
}

$listLimitParsed = events_admin_list_limit_from_get();
$list_limit = $listLimitParsed['sql_limit'];
$listLimitValue = $listLimitParsed['value'];
$listTotalInDb = dance_schools_admin_total_count($db);

$columnFilters = [
    'f_name' => $f_name,
    'f_city' => $f_city,
    'f_status' => $f_status,
    'f_tag' => $f_tag,
    'f_locations' => $f_locations,
    'f_teachers' => $f_teachers,
    'f_events' => $f_events,
    'f_views' => $f_views,
    'f_active' => $f_active,
];
$get_params = [];
foreach ($columnFilters as $paramKey => $paramValue) {
    if ($paramValue !== '') {
        $get_params[$paramKey] = $paramValue;
    }
}
$get_params = events_admin_list_limit_merge_get_params($get_params, $listLimitValue);

$filters = $columnFilters + [
    'order' => $order,
    'dir_param' => $dir_param,
];
$rows = dance_schools_admin_fetch($db, $filters, $list_limit);
$listDisplayedCount = count($rows);
$hasFilters = $columnFilters !== array_fill_keys(array_keys($columnFilters), '');
$colspan = 10;

/** @param array<string, string> $params */
$schoolSortTh = static function (string $label, string $orderCol, string $currentOrder, string $currentDir, array $params): string {
    $nextDir = ($currentOrder === $orderCol && $currentDir === 'asc') ? 'desc' : 'asc';
    $params['order'] = $orderCol;
    $params['dir'] = $nextDir;
    $qs = http_build_query(array_filter(
        $params,
        static fn (mixed $value): bool => $value !== '' && $value !== null
    ));
    $href = events_url('tanciskolak_admin.php' . ($qs !== '' ? '?' . $qs : ''));
    $arrow = '';
    if ($currentOrder === $orderCol) {
        $arrow = $currentDir === 'asc'
            ? ' <span class="sort-arrow" aria-hidden="true">↑</span>'
            : ' <span class="sort-arrow" aria-hidden="true">↓</span>';
    }

    return '<a href="' . h($href) . '" class="th-sort">' . h($label) . $arrow . '</a>';
};

/** @param array{type?:string,placeholder?:string,extra?:string} $opts */
$schoolFilterInput = static function (string $name, string $value, string $label, array $opts = []): string {
    $type = $opts['type'] ?? 'search';
    $placeholder = $opts['placeholder'] ?? 'Szűrés…';
    $extra = $opts['extra'] ?? ($type === 'number' ? ' min="0" step="1" inputmode="numeric"' : ' autocomplete="off"');

    return '<input class="events-filter-input events-inline-filter-input" type="' . h($type)
        . '" name="' . h($name) . '" id="school-' . h($name) . '" value="' . h($value)
        . '" placeholder="' . h($placeholder) . '" aria-label="' . h($label) . '"' . $extra . '>';
};

$pageTitle = 'Tánciskolák';
$mainContentClass = 'main-content main-content--fullwidth';

$adminFloatTools = [
    [
        'href' => events_url('tanciskola_letrehoz.php'),
        'title' => 'Új tánciskola',
        'aria' => 'Új tánciskola létrehozása',
        'icon' => 'plus',
    ],
    [
        'href' => events_url('tanarok_admin.php'),
        'title' => 'Tánctanárok',
        'aria' => 'Tánctanárok listája',
        'icon' => 'back',
    ],
];
$adminFloatToolsRequireLogin = false;

require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<?php require __DIR__ . '/partials/admin_float_tools.php'; ?>

<?php if (isSuperadmin()): ?>
<div class="card events-admin-card" style="margin-bottom:1rem;">
    <form method="post" action="<?= h(events_url('tanciskolak_admin.php')) ?>" onsubmit="return confirm('Feltölti / frissíti a latin tánciskola katalógust (slug alapján)? Új iskola Import státuszt kap, a meglévő státusz megmarad.');">
        <?= csrf_input('dance_schools_seed') ?>
        <div class="events-list-head">
            <div class="events-list-head__start">
                <p class="help" style="margin:0;">Katalógus feltöltés a nyilvános weboldalak alapján (~28 iskola). Újrafuttatható; meglévő slug frissül. Új iskola Import státuszt kap, a már beállított státusz megmarad.</p>
            </div>
            <div class="events-list-actions">
                <button type="submit" name="seed_dance_schools" value="1" class="btn btn-primary">Seed katalógus futtatása</button>
            </div>
        </div>
    </form>
</div>
<div class="card events-admin-card" style="margin-bottom:1rem;">
    <div class="events-list-head">
        <div class="events-list-head__start">
            <p class="help" style="margin:0;">XLSX feltöltés a „Tánciskolák” lapról. Ha az iskola még nincs meg, Import státuszban jön létre (nem publikus). Ha megvan, csak az üres mezők töltődnek. Perjeles, összevont név nem jön létre automatikusan.</p>
        </div>
        <div class="events-list-actions">
            <a href="<?= h(events_url('tanciskola_import.php')) ?>" class="btn btn-primary">XLSX import</a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card events-admin-card">
    <form method="get" action="<?= h(events_url('tanciskolak_admin.php')) ?>" class="events-admin-form" id="tanciskolak-admin-filter-form">
        <input type="hidden" name="order" value="<?= h($order) ?>">
        <input type="hidden" name="dir" value="<?= h($dir_param) ?>">

        <div class="events-list-head">
            <div class="events-list-head__start">
                <h1 class="events-list-title card-title" style="margin:0;">Tánciskolák</h1>
                <p class="help" style="margin:0.35rem 0 0;">Minden oszlop szűrhető és rendezhető. Az eseményszám a közelgő eseményeket számolja.</p>
                <?php
                $listLimitInForm = true;
                $listLimitStandalone = true;
                require __DIR__ . '/partials/admin_list_display_limit.php';
                ?>
            </div>
            <div class="events-list-actions">
                <a href="<?= h(events_url('tanciskolak_admin.php')) ?>" class="btn btn-secondary">Szűrők és rendezés törlése</a>
                <a href="<?= h(events_url('tanciskola_letrehoz.php')) ?>" class="btn btn-primary">Új tánciskola</a>
                <a href="<?= h(events_url('tanciskola_osszevonas.php')) ?>" class="btn btn-secondary">Összevonás</a>
                <a href="<?= h(events_url('tanarok_admin.php')) ?>" class="btn btn-secondary">Tánctanárok</a>
            </div>
        </div>

        <div class="table-wrap events-admin-table-wrap events-filters-shell">
            <table class="sortable-table events-admin-table events-school-admin-table">
                <thead>
                    <tr>
                        <th><?= $schoolSortTh('Név', 'name', $order, $dir_param, $get_params) ?></th>
                        <th><?= $schoolSortTh('Város', 'city', $order, $dir_param, $get_params) ?></th>
                        <th><?= $schoolSortTh('Státusz', 'status', $order, $dir_param, $get_params) ?></th>
                        <th><?= $schoolSortTh('Címkék', 'tags', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num"><?= $schoolSortTh('Helyszínek', 'locations', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num"><?= $schoolSortTh('Tanárok', 'teachers', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num" title="Közelgő események"><?= $schoolSortTh('Események', 'events', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num"><?= $schoolSortTh('Megtekintések', 'views', $order, $dir_param, $get_params) ?></th>
                        <th><?= $schoolSortTh('Aktív', 'active', $order, $dir_param, $get_params) ?></th>
                        <th></th>
                    </tr>
                    <tr class="events-inline-filter-row">
                        <th><?= $schoolFilterInput('f_name', $f_name, 'Szűrés név, slug vagy ID szerint', ['placeholder' => 'Név, slug, ID']) ?></th>
                        <th><?= $schoolFilterInput('f_city', $f_city, 'Szűrés város szerint', ['placeholder' => 'Város']) ?></th>
                        <th>
                            <div class="events-filter-select-wrap">
                                <select class="events-filter-select" name="f_status" id="school-f_status" aria-label="Szűrés státusz szerint">
                                    <option value="">Összes</option>
                                    <?php foreach (dance_school_status_labels() as $statusCode => $statusLabel): ?>
                                        <option value="<?= h($statusCode) ?>"<?= $f_status === $statusCode ? ' selected' : '' ?>><?= h($statusLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </th>
                        <th><?= $schoolFilterInput('f_tag', $f_tag, 'Szűrés címke szerint', ['placeholder' => 'Címke']) ?></th>
                        <th><?= $schoolFilterInput('f_locations', $f_locations, 'Szűrés helyszínek pontos száma szerint', ['type' => 'number', 'placeholder' => '=']) ?></th>
                        <th><?= $schoolFilterInput('f_teachers', $f_teachers, 'Szűrés tanárok pontos száma szerint', ['type' => 'number', 'placeholder' => '=']) ?></th>
                        <th><?= $schoolFilterInput('f_events', $f_events, 'Szűrés közelgő események pontos száma szerint', ['type' => 'number', 'placeholder' => '=']) ?></th>
                        <th><?= $schoolFilterInput('f_views', $f_views, 'Szűrés megtekintések pontos száma szerint', ['type' => 'number', 'placeholder' => '=']) ?></th>
                        <th>
                            <div class="events-filter-select-wrap">
                                <select class="events-filter-select" name="f_active" id="school-f_active" aria-label="Szűrés aktív szerint">
                                    <option value="">Összes</option>
                                    <option value="1"<?= $f_active === '1' ? ' selected' : '' ?>>Igen</option>
                                    <option value="0"<?= $f_active === '0' ? ' selected' : '' ?>>Nem</option>
                                </select>
                            </div>
                        </th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="<?= (int) $colspan ?>">
                                <?php if ($hasFilters): ?>
                                    Nincs a szűrésnek megfelelő tánciskola.
                                <?php else: ?>
                                    Nincs tánciskola. Adj hozzá újat.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $sid = (int) $r['id'];
                            $sname = (string) $r['name'];
                            $editUrl = events_url('tanciskola_szerkeszt.php?id=') . $sid;
                            $schoolStatus = dance_school_normalize_status($r['status'] ?? (!empty($r['is_published']) ? 'public' : 'draft'));
                            $isActive = !empty($r['is_active']);
                            $tagNames = trim((string) ($r['tag_names'] ?? ''));
                            $cityLabel = trim((string) ($r['city'] ?? ''));
                            ?>
                            <tr>
                                <td><a href="<?= h($editUrl) ?>"><strong><?= h($sname) ?></strong></a></td>
                                <td><?= h($cityLabel !== '' ? $cityLabel : '—') ?></td>
                                <td><span class="event-status-badge<?= dance_school_status_is_publicly_visible($schoolStatus) ? '' : ' event-status-badge--draft' ?>"><?= h(dance_school_status_label($schoolStatus)) ?></span></td>
                                <td><?= $tagNames !== '' ? h($tagNames) : '<span class="text-muted">—</span>' ?></td>
                                <td class="td-num"><?= (int) ($r['location_count'] ?? 0) ?></td>
                                <td class="td-num"><?= (int) ($r['teacher_count'] ?? 0) ?></td>
                                <td class="td-num"><?= (int) ($r['upcoming_count'] ?? 0) ?></td>
                                <td class="td-num"><?= (int) ($r['view_count'] ?? 0) ?></td>
                                <td><?= $isActive ? 'Igen' : '<span class="text-muted">Nem</span>' ?></td>
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
    var key = 'tanciskolak-admin-filter-focus';
    var form = document.getElementById('tanciskolak-admin-filter-form');
    if (!form) return;
    try {
        var id = sessionStorage.getItem(key);
        if (id) {
            sessionStorage.removeItem(key);
            var el = document.getElementById(id);
            if (el && form.contains(el)) {
                el.focus({ preventScroll: true });
                if (el.type !== 'number' && typeof el.setSelectionRange === 'function') {
                    var len = (el.value || '').length;
                    el.setSelectionRange(len, len);
                }
            }
        }
    } catch (e) {}
    form.addEventListener('focusin', function (e) {
        var target = e.target;
        if (!target || !target.id || !target.classList) return;
        if (!target.classList.contains('events-filter-input') && !target.classList.contains('events-filter-select')) return;
        try {
            sessionStorage.setItem(key, target.id);
        } catch (err) {}
    });
})();
</script>
<?php
require_once dirname(__DIR__) . '/partials/footer.php';
