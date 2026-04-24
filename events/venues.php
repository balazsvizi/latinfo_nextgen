<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
require_once __DIR__ . '/lib/venue_request.php';
requireLogin();

$db = getDb();

$f_q = trim((string) ($_GET['f_q'] ?? ''));
$f_city = trim((string) ($_GET['f_city'] ?? ''));
$f_id = trim((string) ($_GET['f_id'] ?? ''));

$allowedOrder = ['id', 'name', 'cim', 'modified'];
if (isset($_GET['order']) && in_array((string) $_GET['order'], $allowedOrder, true)) {
    $order = (string) $_GET['order'];
    $dir_param = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc';
} else {
    $order = 'name';
    $dir_param = 'asc';
}

$where = [];
$params = [];
if ($f_q !== '') {
    $like = '%' . $f_q . '%';
    $where[] = '(v.`name` LIKE ? OR v.`slug` LIKE ? OR v.`city` LIKE ? OR v.`address` LIKE ? OR v.`postal_code` LIKE ? OR v.`country` LIKE ? OR CAST(v.`id` AS CHAR) LIKE ?)';
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
}
if ($f_city !== '') {
    $where[] = 'v.`city` LIKE ?';
    $params[] = '%' . $f_city . '%';
}
if ($f_id !== '') {
    if (ctype_digit($f_id)) {
        $where[] = 'v.`id` = ?';
        $params[] = (int) $f_id;
    } else {
        $where[] = 'CAST(v.`id` AS CHAR) LIKE ?';
        $params[] = '%' . $f_id . '%';
    }
}

$whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
$dirSql = $dir_param === 'asc' ? 'ASC' : 'DESC';
$orderSql = match ($order) {
    'id' => "v.`id` $dirSql",
    'name' => "v.`name` $dirSql",
    'cim' => "v.`city` IS NULL, v.`city` $dirSql, v.`postal_code` IS NULL, v.`postal_code` $dirSql, v.`address` IS NULL, v.`address` $dirSql",
    'modified' => "v.`modified` $dirSql",
    default => 'v.`name` ASC, v.`id` ASC',
};

$sql = "SELECT v.`id`, v.`name`, v.`slug`, v.`country`, v.`city`, v.`postal_code`, v.`address`, v.`modified`
        FROM `events_venues` v
        $whereSql
        ORDER BY $orderSql";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$get_params = array_filter([
    'f_q' => $f_q !== '' ? $f_q : null,
    'f_city' => $f_city !== '' ? $f_city : null,
    'f_id' => $f_id !== '' ? $f_id : null,
]);

$excerpt = static function (?string $s, int $max): string {
    $s = $s ?? '';
    if (function_exists('mb_strlen') && mb_strlen($s, 'UTF-8') > $max) {
        return mb_substr($s, 0, $max, 'UTF-8') . '…';
    }
    if (strlen($s) > $max) {
        return substr($s, 0, $max) . '…';
    }
    return $s;
};

$hasFilters = $f_q !== '' || $f_city !== '' || $f_id !== '';
$colspan = 4;

$pageTitle = 'Helyszínek';
$mainContentClass = 'main-content main-content--fullwidth';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card">
    <form method="get" action="<?= h(events_url('venues.php')) ?>" class="events-admin-form" id="venues-filter-form">
        <input type="hidden" name="order" value="<?= h($order) ?>">
        <input type="hidden" name="dir" value="<?= h($dir_param) ?>">

        <div class="events-list-head">
            <h2 class="events-list-title">Helyszínek</h2>
            <div class="events-list-actions">
                <a href="<?= h(events_url('venues.php')) ?>" class="btn btn-secondary">Szűrők és rendezés törlése</a>
                <a href="<?= h(events_url('venue_letrehoz.php')) ?>" class="btn btn-primary">Új helyszín</a>
                <a href="<?= h(events_url('import_csv.php')) ?>?target_table=events_venues" class="btn btn-secondary">CSV import</a>
                <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Események</a>
            </div>
        </div>

        <section class="events-filters-shell" aria-label="Szűrők">
            <div class="events-filters-grid">
                <div class="events-filter-field">
                    <label class="events-filter-label" for="v-f-q">Keresés</label>
                    <input class="events-filter-input" type="search" name="f_q" id="v-f-q" value="<?= h($f_q) ?>" placeholder="Név, település, utca, IRSZ, ország, ID…" autocomplete="off">
                </div>
                <div class="events-filter-field">
                    <label class="events-filter-label" for="v-f-city">Település</label>
                    <input class="events-filter-input" type="text" name="f_city" id="v-f-city" value="<?= h($f_city) ?>" placeholder="Részlet a településből…" autocomplete="off">
                </div>
                <div class="events-filter-field">
                    <label class="events-filter-label" for="v-f-id">ID</label>
                    <input class="events-filter-input" type="text" name="f_id" id="v-f-id" value="<?= h($f_id) ?>" placeholder="Pontos vagy részlet" inputmode="numeric" autocomplete="off">
                </div>
                <div class="events-filter-field">
                    <label class="events-filter-label visually-hidden" for="v-f-submit">Szűrés alkalmazása</label>
                    <button type="submit" class="btn btn-primary" id="v-f-submit">Szűrés</button>
                </div>
            </div>
        </section>

        <div class="table-wrap events-admin-table-wrap">
            <table class="sortable-table events-admin-table">
                <thead>
                    <tr>
                        <th><?= sort_th('ID', 'id', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('Név', 'name', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('Cím', 'cim', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('Módosítva', 'modified', $order, $dir_param, $get_params) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="<?= (int) $colspan ?>">
                                <?php if ($hasFilters): ?>
                                    Nincs a szűrésnek megfelelő helyszín.
                                <?php else: ?>
                                    Még nincs helyszín. Futtasd az SQL migrációt (<code>events/sql/migration_events.sql</code> vagy <code>migration_venues.sql</code>), majd vegyél fel egy helyszínt.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $vid = (int) $r['id'];
                            $editUrl = events_url('venue_szerkeszt.php?id=') . $vid;
                            $pubUrl = events_helyszin_megjelenit_url((string) ($r['slug'] ?? ''));
                            ?>
                            <tr>
                                <td><?= $vid ?></td>
                                <td class="venues-td-name">
                                    <span class="venues-name-with-action">
                                        <a class="events-cell-edit" href="<?= h($editUrl) ?>"><?= h((string) $r['name']) ?></a>
                                        <a href="<?= h($pubUrl) ?>" class="events-icon-action" title="Nyilvános megjelenítés (új lap)" aria-label="Nyilvános megjelenítés új lapon" target="_blank" rel="noopener">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                        </a>
                                    </span>
                                </td>
                                <td><?php
                                    $line = events_venue_address_summary($r);
                                    echo $line !== '' ? h($excerpt($line, 200)) : '–';
                                ?></td>
                                <td><?= !empty($r['modified']) ? h((string) $r['modified']) : '–' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>
<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
