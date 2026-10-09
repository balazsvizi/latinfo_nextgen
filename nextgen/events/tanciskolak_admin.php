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
        flash('success', "Seed kész. Új: {$created}, frissítve: {$updated}, összesen: {$total}. (Publikálatlanul mentve.)");
    }
    redirect($seedRedirect);
}

$f_q = trim((string) ($_GET['f_q'] ?? ''));
$allowedOrder = ['name', 'city', 'locations', 'teachers', 'events', 'views', 'active', 'id', 'slug'];
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

$get_params = [];
if ($f_q !== '') {
    $get_params['f_q'] = $f_q;
}
$get_params = events_admin_list_limit_merge_get_params($get_params, $listLimitValue);

$filters = [
    'f_q' => $f_q,
    'order' => $order,
    'dir_param' => $dir_param,
];
$rows = dance_schools_admin_fetch($db, $filters, $list_limit);
$listDisplayedCount = count($rows);
$hasFilters = $f_q !== '';
$colspan = 9;

/** @param array<string, string> $params */
$schoolSortTh = static function (string $label, string $orderCol, string $currentOrder, string $currentDir, array $params): string {
    $rel = sort_url($params, $orderCol, $currentOrder, $currentDir);
    $qs = ltrim($rel, '?');
    $href = events_url('tanciskolak_admin.php' . ($qs !== '' ? '?' . $qs : ''));
    $arrow = '';
    if ($currentOrder === $orderCol) {
        $arrow = $currentDir === 'asc'
            ? ' <span class="sort-arrow" aria-hidden="true">↑</span>'
            : ' <span class="sort-arrow" aria-hidden="true">↓</span>';
    }

    return '<a href="' . h($href) . '" class="th-sort">' . h($label) . $arrow . '</a>';
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
    <form method="post" action="<?= h(events_url('tanciskolak_admin.php')) ?>" onsubmit="return confirm('Feltölti / frissíti a latin tánciskola katalógust (slug alapján)? Publikálatlanul ment.');">
        <?= csrf_input('dance_schools_seed') ?>
        <div class="events-list-head">
            <div class="events-list-head__start">
                <p class="help" style="margin:0;">Katalógus feltöltés a nyilvános weboldalak alapján (~28 iskola). Újrafuttatható; meglévő slug frissül. Mindig publikálatlan.</p>
            </div>
            <div class="events-list-actions">
                <button type="submit" name="seed_dance_schools" value="1" class="btn btn-primary">Seed katalógus futtatása</button>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card events-admin-card">
    <form method="get" action="<?= h(events_url('tanciskolak_admin.php')) ?>" class="events-admin-form" id="tanciskolak-admin-filter-form">
        <input type="hidden" name="order" value="<?= h($order) ?>">
        <input type="hidden" name="dir" value="<?= h($dir_param) ?>">

        <div class="events-list-head">
            <div class="events-list-head__start">
                <h1 class="events-list-title card-title" style="margin:0;">Tánciskolák</h1>
                <p class="help" style="margin:0.35rem 0 0;">Tánciskolák, helyszínek, tanárok és workshopok.</p>
                <?php
                $listLimitInForm = true;
                $listLimitStandalone = true;
                require __DIR__ . '/partials/admin_list_display_limit.php';
                ?>
            </div>
            <div class="events-list-actions">
                <a href="<?= h(events_url('tanciskolak_admin.php')) ?>" class="btn btn-secondary">Szűrők és rendezés törlése</a>
                <a href="<?= h(events_url('tanciskola_letrehoz.php')) ?>" class="btn btn-primary">Új tánciskola</a>
                <a href="<?= h(events_url('tanarok_admin.php')) ?>" class="btn btn-secondary">Tánctanárok</a>
            </div>
        </div>

        <section class="events-filters-shell" aria-label="Szűrők">
            <div class="events-filters-grid">
                <div class="events-filter-field">
                    <label class="events-filter-label<?= $f_q !== '' ? ' events-filter-label--active' : '' ?>" for="school-f-q">Keresés</label>
                    <input class="events-filter-input" type="search" name="f_q" id="school-f-q" value="<?= h($f_q) ?>" placeholder="Név, város, slug vagy ID…" autocomplete="off">
                </div>
            </div>
        </section>

        <div class="table-wrap events-admin-table-wrap">
            <table class="sortable-table events-admin-table">
                <thead>
                    <tr>
                        <th class="events-djs-admin__th-photo">Fotó</th>
                        <th><?= $schoolSortTh('Név', 'name', $order, $dir_param, $get_params) ?></th>
                        <th><?= $schoolSortTh('Város', 'city', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num"><?= $schoolSortTh('Helyszínek', 'locations', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num"><?= $schoolSortTh('Tanárok', 'teachers', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num"><?= $schoolSortTh('Események', 'events', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num"><?= $schoolSortTh('Megtekintések', 'views', $order, $dir_param, $get_params) ?></th>
                        <th><?= $schoolSortTh('Aktív', 'active', $order, $dir_param, $get_params) ?></th>
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
                            $photo = trim((string) ($r['photo_url'] ?? ''));
                            $logo = trim((string) ($r['logo_url'] ?? ''));
                            $thumb = $photo !== '' ? $photo : $logo;
                            $photoAbs = $thumb !== '' ? events_absolute_url($thumb) : '';
                            $thumbIsLogo = $photo === '' && $logo !== '';
                            $editUrl = events_url('tanciskola_szerkeszt.php?id=') . $sid;
                            $isPublished = !empty($r['is_published']);
                            $isActive = !empty($r['is_active']);
                            ?>
                            <tr>
                                <td class="events-djs-admin__td-photo">
                                    <a href="<?= h($editUrl) ?>" class="events-djs-admin__thumb-link" title="Szerkesztés">
                                        <?php if ($photoAbs !== ''): ?>
                                            <img class="events-djs-admin__thumb<?= $thumbIsLogo ? ' events-djs-admin__thumb--logo' : '' ?>" src="<?= h($photoAbs) ?>" alt="" loading="lazy" width="40" height="40">
                                        <?php else: ?>
                                            <span class="events-djs-admin__thumb events-djs-admin__thumb--empty" aria-hidden="true">💃</span>
                                        <?php endif; ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="<?= h($editUrl) ?>"><strong><?= h($sname) ?></strong></a>
                                    <?php if (!$isPublished): ?>
                                        <span class="event-status-badge event-status-badge--draft" title="Alapértelmezés szerint még nem publikus">még nem publikus</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= h(trim((string) ($r['city'] ?? '')) !== '' ? (string) $r['city'] : '—') ?></td>
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
    var key = 'tanciskolak-admin-search-focus';
    var input = document.getElementById('school-f-q');
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
