<?php
require_once __DIR__ . '/../../nextgen/lib/organizer/unify.php';

$pageTitle = 'Szervezők';
require_once __DIR__ . '/../partials/header.php';

$db = getDb();
organizer_unify_ensure_schema($db);
organizer_unify_auto_match($db);
$hasCimkeSzin = cimkek_has_szin($db);

$kereso = trim($_GET['kereso'] ?? '');
$cimke_id = isset($_GET['cimke_id']) ? (int) $_GET['cimke_id'] : 0;
// Alapértelmezett rendezés: név szerint növekvő
$order = isset($_GET['order']) && in_array($_GET['order'], ['id', 'név', 'címkék', 'szamlazando_nem', 'szamla_nem'], true) ? $_GET['order'] : 'név';
$dir_param = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';
$dir = $dir_param === 'desc' ? 'DESC' : 'ASC';

$where = [];
$params = [];
if ($kereso !== '') {
    $where[] = '(s.név LIKE ? OR s.id = ?)';
    $params[] = '%' . $kereso . '%';
    $params[] = $kereso;
}
if ($cimke_id > 0) {
    $where[] = 'EXISTS (SELECT 1 FROM finance_organizer_tags sc WHERE sc.szervező_id = s.id AND sc.címke_id = ?)';
    $params[] = $cimke_id;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$order_sql = $order === 'id' ? 's.id' : ($order === 'név' ? 's.név' : ($order === 'címkék' ? 'címkék' : ($order === 'szamlazando_nem' ? 'szamlazando_nem' : ($order === 'szamla_nem' ? 'szamla_nem' : 's.név'))));
$cimkeConcatExpr = $hasCimkeSzin
    ? "GROUP_CONCAT(CONCAT(c.név, '|', COALESCE(c.szín, '#6366F1')) ORDER BY c.név SEPARATOR '||')"
    : "GROUP_CONCAT(CONCAT(c.név, '|', '#6366F1') ORDER BY c.név SEPARATOR '||')";
$stmt = $db->prepare("
    SELECT s.id, s.név, COALESCE(s.events_organizer_id, 0) AS events_organizer_id, COALESCE(e.name, '') AS events_name,
           (SELECT $cimkeConcatExpr FROM finance_organizer_tags sc JOIN finance_tags c ON c.id = sc.címke_id WHERE sc.szervező_id = s.id) AS címkék,
           (SELECT COUNT(*) FROM finance_billing_items sz WHERE sz.szervező_id = s.id AND sz.számla_id IS NULL AND (COALESCE(sz.törölve,0) = 0)) AS szamlazando_nem,
           (SELECT COUNT(*) FROM finance_invoices szl WHERE szl.szervező_id = s.id AND szl.státusz NOT IN ('kiegyenlítve','sztornó') AND (COALESCE(szl.törölve,0) = 0)) AS szamla_nem
    FROM finance_organizers s
    LEFT JOIN events_organizers e ON e.id = s.events_organizer_id
    $where_sql
    ORDER BY $order_sql $dir
");
$stmt->execute($params);
$szervezok = $stmt->fetchAll();

$cimkeSelectSql = $hasCimkeSzin
    ? 'SELECT id, név, COALESCE(szín, "#6366F1") AS szín FROM finance_tags ORDER BY név'
    : 'SELECT id, név, "#6366F1" AS szín FROM finance_tags ORDER BY név';
$címkék = $db->query($cimkeSelectSql)->fetchAll();
$get_params = array_filter(['kereso' => $kereso, 'cimke_id' => $cimke_id ?: null]);
?>
<div class="card">
    <h2>Szervezők</h2>
    <p>Lista, keresés és szűrés.</p>

    <form method="get" class="toolbar">
        <input type="search" name="kereso" placeholder="Név vagy ID keresése..." value="<?= h($kereso) ?>">
        <span class="cimke-select-wrap">
            <select name="cimke_id" class="cimke-select" title="Szűrés címke szerint">
                <option value="">Minden címke</option>
                <?php foreach ($címkék as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $cimke_id === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['név']) ?></option>
                <?php endforeach; ?>
            </select>
        </span>
        <button type="submit" class="btn btn-primary">Keresés</button>
        <a href="<?= h(nextgen_url('organizers/letrehoz.php')) ?>" class="btn btn-primary">Új szervező</a>
        <a href="<?= h(nextgen_url('organizers/egyesites.php')) ?>" class="btn btn-secondary">Egyesítés az eseményszervezőkkel</a>
    </form>

    <div class="table-wrap">
        <table class="sortable-table">
            <thead>
                <tr>
                    <th><?= sort_th('ID', 'id', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Név', 'név', $order, $dir_param, $get_params) ?></th>
                    <th>Eseményszervező</th>
                    <th><?= sort_th('Címkék', 'címkék', $order, $dir_param, $get_params) ?></th>
                    <th class="th-center"><?= sort_th('Le nem számlázott', 'szamlazando_nem', $order, $dir_param, $get_params) ?></th>
                    <th class="th-center"><?= sort_th('Nem kiegyenlített', 'szamla_nem', $order, $dir_param, $get_params) ?></th>
                    <th>Műveletek</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($szervezok as $s): ?>
                <tr>
                    <td><?= (int)$s['id'] ?></td>
                    <td><a href="<?= h(nextgen_url('organizers/megtekint.php?id=')) ?><?= (int)$s['id'] ?>"><?= h($s['név']) ?></a></td>
                    <td>
                        <?php if ((int) ($s['events_organizer_id'] ?? 0) > 0): ?>
                            <a href="<?= h(nextgen_url('events/organizer_szerkeszt.php?id=') . (int) $s['events_organizer_id']) ?>"><?= h((string) ($s['events_name'] ?? '')) ?></a>
                        <?php else: ?>
                            <a href="<?= h(nextgen_url('organizers/egyesites.php#fin-') . (int) $s['id']) ?>">Nincs összekötve</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($s['címkék'])): ?>
                            <span class="cimke-badge-list">
                                <?php foreach (explode('||', $s['címkék']) as $rawTag): ?>
                                    <?php
                                        $tagParts = explode('|', $rawTag, 2);
                                        $tagNev = trim($tagParts[0] ?? '');
                                        $tagSzin = normalize_hex_color($tagParts[1] ?? '#6366F1', '#6366F1');
                                        $tagTextColor = contrast_text_color($tagSzin);
                                    ?>
                                    <?php if ($tagNev !== ''): ?>
                                        <span class="cimke-badge" style="--badge-bg: <?= h($tagSzin) ?>; --badge-text: <?= h($tagTextColor) ?>;"><?= h($tagNev) ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </span>
                        <?php else: ?>
                            –
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= (int)$s['szamlazando_nem'] ?></td>
                    <td class="text-center"><?= (int)$s['szamla_nem'] ?></td>
                    <td class="actions">
                        <a href="<?= h(nextgen_url('organizers/megtekint.php?id=')) ?><?= (int)$s['id'] ?>" class="btn btn-sm btn-secondary">Megtekint</a>
                        <a href="<?= h(nextgen_url('organizers/szerkeszt.php?id=')) ?><?= (int)$s['id'] ?>" class="btn btn-sm btn-secondary">Szerkeszt</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($szervezok) === 0): ?>
        <p>Nincs találat.</p>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
