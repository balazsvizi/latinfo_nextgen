<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$db = getDb();

$kereso = trim($_GET['kereso'] ?? '');
$order = isset($_GET['order']) && in_array($_GET['order'], ['id', 'szervezo_nev', 'idoszakok', 'összeg', 'megjegyzés', 'létrehozva'], true) ? $_GET['order'] : 'id';
$dir_param = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc';
$dir = $dir_param === 'desc' ? 'DESC' : 'ASC';

$where = [];
$params = [];
if ($kereso !== '') {
    $where[] = '(sz.név LIKE ? OR s.megjegyzés LIKE ?)';
    $p = '%' . $kereso . '%';
    $params = [$p, $p];
}
// Törölt tételek SOHA ne jelenjenek meg a listákban
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) . ' AND (COALESCE(s.törölve,0) = 0)' : 'WHERE (COALESCE(s.törölve,0) = 0)';
$where_nincs_szamla = $where_sql . ' AND s.számla_id IS NULL';
$order_sql = $order === 'id' ? 's.id' : ($order === 'szervezo_nev' ? 'sz.név' : ($order === 'idoszakok' ? 'idoszakok' : ($order === 'összeg' ? 's.összeg' : ($order === 'megjegyzés' ? 's.megjegyzés' : 's.létrehozva'))));

// Csak nem csatolt (nincs hozzárendelt számla) – felül külön lista; töröltek NEM jelennek meg
$sql_nincs = "
    SELECT s.id, s.szervező_id, s.összeg, s.megjegyzés, s.számla_id, s.létrehozva,
           sz.név AS szervezo_nev,
           (SELECT GROUP_CONCAT(CONCAT(si.év, '-', LPAD(si.hónap, 2, '0')) ORDER BY si.év, si.hónap)
            FROM számlázandó_időszak si WHERE si.számlázandó_id = s.id) AS idoszakok,
           NULL AS szamla_szam, NULL AS szamla_id, NULL AS elso_fajl_id
    FROM számlázandó s
    JOIN szervezők sz ON sz.id = s.szervező_id
    $where_nincs_szamla
    ORDER BY s.id DESC
";
$stmt_nincs = $db->prepare($sql_nincs);
$stmt_nincs->execute($params);
$lista_nincs_szamla = $stmt_nincs->fetchAll(PDO::FETCH_ASSOC);

// Összes (szűrővel), alap rendezés legújabb; törölt számlázandók NEM jelennek meg
$sql = "
    SELECT s.id, s.szervező_id, s.összeg, s.megjegyzés, s.számla_id, s.létrehozva,
           sz.név AS szervezo_nev,
           (SELECT GROUP_CONCAT(CONCAT(si.év, '-', LPAD(si.hónap, 2, '0')) ORDER BY si.év, si.hónap)
            FROM számlázandó_időszak si WHERE si.számlázandó_id = s.id) AS idoszakok,
           szam.számla_szám AS szamla_szam, szam.id AS szamla_id,
           (SELECT id FROM számla_fájlok WHERE számla_id = s.számla_id ORDER BY id LIMIT 1) AS elso_fajl_id
    FROM számlázandó s
    JOIN szervezők sz ON sz.id = s.szervező_id
    LEFT JOIN számlák szam ON szam.id = s.számla_id AND (COALESCE(szam.törölve,0) = 0)
    $where_sql
    ORDER BY $order_sql $dir, s.id DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

$get_params = array_filter(['kereso' => $kereso]);

$pageTitle = 'Számlázandó';
require_once __DIR__ . '/../../partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>

<div class="card">
    <h2>Nem számlázott tételek</h2>
    <p>Csak azok a tételek, amelyekhez még nincs csatolt számla. Legújabb elöl. A kereső az alatti szűrővel együtt használható.</p>
    <?php if (!empty($lista_nincs_szamla)): ?>
    <div class="table-wrap">
        <table class="sortable-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Szervező</th>
                    <th>Időszak</th>
                    <th>Összeg</th>
                    <th>Megjegyzés</th>
                    <th>Csatolt számla</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lista_nincs_szamla as $s): ?>
                <tr>
                    <td><?= (int)$s['id'] ?></td>
                    <td><a href="<?= h(BASE_URL) ?>/organizers/megtekint.php?id=<?= (int)$s['szervező_id'] ?>"><?= h($s['szervezo_nev']) ?></a></td>
                    <td><?= h($s['idoszakok'] ?? '–') ?></td>
                    <td><?= number_format((float)$s['összeg'], 0, ',', ' ') ?> Ft</td>
                    <td><?= h(mb_substr($s['megjegyzés'] ?? '', 0, 50)) ?><?= mb_strlen($s['megjegyzés'] ?? '') > 50 ? '…' : '' ?></td>
                    <td>–</td>
                    <td class="actions">
                        <a href="<?= h(BASE_URL) ?>/finance/szamlak/letrehoz.php?szervezo_id=<?= (int)$s['szervező_id'] ?>&szamlazando_id=<?= (int)$s['id'] ?>" class="btn btn-primary btn-sm">Számlakészítés</a>
                        <a href="<?= h(BASE_URL) ?>/finance/szamlazando/szerkeszt.php?id=<?= (int)$s['id'] ?>" class="btn btn-secondary btn-sm">Szerkeszt</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p>Nincs olyan tétel, amit még nem számláztak (vagy a szűrőre nincs találat).</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Összes számlázandó</h2>
    <p>Szűrhető lista, alapértelmezett sorrend: legújabb elöl. A csatolt számla mellett a letöltés ikonnal a számla első csatolt fájlja tölthető le.</p>
    <form method="get" class="toolbar">
        <input type="search" name="kereso" placeholder="Szervező vagy megjegyzés..." value="<?= h($kereso) ?>">
        <button type="submit" class="btn btn-primary">Szűrés</button>
    </form>
    <div class="table-wrap">
        <table class="sortable-table">
            <thead>
                <tr>
                    <th><?= sort_th('ID', 'id', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Szervező', 'szervezo_nev', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Időszak', 'idoszakok', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Összeg', 'összeg', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Megjegyzés', 'megjegyzés', $order, $dir_param, $get_params) ?></th>
                    <th>Csatolt számla</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lista as $s): ?>
                <tr>
                    <td><?= (int)$s['id'] ?></td>
                    <td><a href="<?= h(BASE_URL) ?>/organizers/megtekint.php?id=<?= (int)$s['szervező_id'] ?>"><?= h($s['szervezo_nev']) ?></a></td>
                    <td><?= h($s['idoszakok'] ?? '–') ?></td>
                    <td><?= number_format((float)$s['összeg'], 0, ',', ' ') ?> Ft</td>
                    <td><?= h(mb_substr($s['megjegyzés'] ?? '', 0, 50)) ?><?= mb_strlen($s['megjegyzés'] ?? '') > 50 ? '…' : '' ?></td>
                    <td>
                        <?php if (!empty($s['szamla_id']) && !empty($s['szamla_szam'])): ?>
                            <a href="<?= h(BASE_URL) ?>/finance/szamlak/szerkeszt.php?id=<?= (int)$s['szamla_id'] ?>&vissza=szamlazando"><?= h($s['szamla_szam']) ?></a>
                            <?php if (!empty($s['elso_fajl_id'])): ?>
                                <a href="<?= h(BASE_URL) ?>/finance/szamlak/letoltes.php?id=<?= (int)$s['elso_fajl_id'] ?>" class="szamla-letolt-icon" title="Csatolt fájl letöltése" target="_blank" rel="noopener" aria-label="Letöltés">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            –
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <?php if (empty($s['szamla_id'])): ?>
                            <a href="<?= h(BASE_URL) ?>/finance/szamlak/letrehoz.php?szervezo_id=<?= (int)$s['szervező_id'] ?>&szamlazando_id=<?= (int)$s['id'] ?>" class="btn btn-primary btn-sm">Számlakészítés</a>
                        <?php endif; ?>
                        <a href="<?= h(BASE_URL) ?>/finance/szamlazando/szerkeszt.php?id=<?= (int)$s['id'] ?>" class="btn btn-secondary btn-sm">Szerkeszt</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (empty($lista)): ?>
        <p>Még nincs számlázandó tétel, vagy a szűrőre nincs találat. A szervező megtekintőben tudsz újat létrehozni.</p>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
