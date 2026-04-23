<?php
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
requireLogin();

$db = getDb();
$hasCimkeSzin = cimkek_has_szin($db);
$cimkeConcatExpr = $hasCimkeSzin
    ? "GROUP_CONCAT(CONCAT(c.név, '|', COALESCE(c.szín, '#6366F1')) ORDER BY c.név SEPARATOR '||')"
    : "GROUP_CONCAT(CONCAT(c.név, '|', '#6366F1') ORDER BY c.név SEPARATOR '||')";

// Státusz módosítás a táblázatból (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['szamla_id'], $_POST['státusz'])) {
    $sid = (int) $_POST['szamla_id'];
    $uj = $_POST['státusz'];
    if ($sid && in_array($uj, ['generált', 'kiküldve', 'kiegyenlítve', 'egyéb', 'KP', 'sztornó'], true)) {
        $db->prepare('UPDATE finance_invoices SET státusz = ? WHERE id = ? AND (COALESCE(törölve,0) = 0)')->execute([$uj, $sid]);
        rendszer_log('számla', $sid, 'Státusz módosítva', $uj);
        flash('success', 'Státusz frissítve.');
        redirect(nextgen_url('finance/szamlak/'));
    }
}

$kereso = trim($_GET['kereso'] ?? '');
$statusz_szuro = trim($_GET['státusz'] ?? '');
$order = isset($_GET['order']) && in_array($_GET['order'], ['id', 'szervezo_nev', 'számla_szám', 'idoszakok', 'összeg', 'státusz'], true) ? $_GET['order'] : 'id';
$dir_param = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc';
$dir = $dir_param === 'desc' ? 'DESC' : 'ASC';

$where = [];
$params = [];
if ($kereso !== '') {
    $where[] = '(sz.név LIKE ? OR s.számla_szám LIKE ? OR s.belső_megjegyzés LIKE ?)';
    $p = '%' . $kereso . '%';
    $params = array_merge($params, [$p, $p, $p]);
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Csak generáltak (külön lista felül), legújabb elöl; törölt finance_invoices kiszűrve
$where_gen = $where_sql ? $where_sql . " AND s.státusz = 'generált' AND (COALESCE(s.törölve,0) = 0)" : "WHERE s.státusz = 'generált' AND (COALESCE(s.törölve,0) = 0)";
$stmt_gen = $db->prepare("
    SELECT s.id, s.szervező_id, s.számla_szám, s.összeg, s.belső_megjegyzés, s.státusz, sz.név AS szervezo_nev,
           (SELECT $cimkeConcatExpr FROM finance_organizer_tags sc JOIN finance_tags c ON c.id = sc.címke_id WHERE sc.szervező_id = s.szervező_id) AS címkék,
           (SELECT id FROM finance_invoice_files WHERE számla_id = s.id ORDER BY id LIMIT 1) AS elso_fajl_id,
           (SELECT GROUP_CONCAT(DISTINCT CONCAT(si.év, '-', LPAD(si.hónap, 2, '0')) ORDER BY CONCAT(si.év, '-', LPAD(si.hónap, 2, '0')) SEPARATOR ', ')
            FROM finance_billing_items sz2
            JOIN finance_billing_periods si ON si.számlázandó_id = sz2.id
            WHERE sz2.számla_id = s.id AND (COALESCE(sz2.törölve,0) = 0)) AS idoszakok
    FROM finance_invoices s
    JOIN finance_organizers sz ON sz.id = s.szervező_id
    $where_gen
    ORDER BY s.id DESC
");
$stmt_gen->execute($params);
$szamlak_generalt = $stmt_gen->fetchAll();

// Összes (szűrővel), alap sorrend legújabb
if ($statusz_szuro !== '' && in_array($statusz_szuro, ['generált', 'kiküldve', 'kiegyenlítve', 'egyéb', 'KP', 'sztornó'], true)) {
    $where[] = 's.státusz = ?';
    $params[] = $statusz_szuro;
}
$where[] = '(COALESCE(s.törölve,0) = 0)';
$where_osszes_sql = 'WHERE ' . implode(' AND ', $where);
$order_sql = $order === 'id' ? 's.id' : ($order === 'szervezo_nev' ? 'sz.név' : ($order === 'számla_szám' ? 's.számla_szám' : ($order === 'idoszakok' ? 'idoszakok' : ($order === 'összeg' ? 's.összeg' : 's.státusz'))));

$stmt = $db->prepare("
    SELECT s.id, s.szervező_id, s.számla_szám, s.összeg, s.belső_megjegyzés, s.státusz, sz.név AS szervezo_nev,
           (SELECT $cimkeConcatExpr FROM finance_organizer_tags sc JOIN finance_tags c ON c.id = sc.címke_id WHERE sc.szervező_id = s.szervező_id) AS címkék,
           (SELECT id FROM finance_invoice_files WHERE számla_id = s.id ORDER BY id LIMIT 1) AS elso_fajl_id,
           (SELECT GROUP_CONCAT(DISTINCT CONCAT(si.év, '-', LPAD(si.hónap, 2, '0')) ORDER BY CONCAT(si.év, '-', LPAD(si.hónap, 2, '0')) SEPARATOR ', ')
            FROM finance_billing_items sz2
            JOIN finance_billing_periods si ON si.számlázandó_id = sz2.id
            WHERE sz2.számla_id = s.id AND (COALESCE(sz2.törölve,0) = 0)) AS idoszakok
    FROM finance_invoices s
    JOIN finance_organizers sz ON sz.id = s.szervező_id
    $where_osszes_sql
    ORDER BY $order_sql $dir, s.id DESC
");
$stmt->execute($params);
$szamlak = $stmt->fetchAll();

$get_params = array_filter(['kereso' => $kereso, 'státusz' => $statusz_szuro]);
$pageTitle = 'Számlák';
require_once __DIR__ . '/../../partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>

<div class="card">
    <h2>Generált finance_invoices</h2>
    <?php if (!empty($szamlak_generalt)): ?>
    <div class="table-wrap">
        <table class="sortable-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Szervező</th>
                    <th>Számlaszám</th>
                    <th>Időszak</th>
                    <th>Összeg</th>
                    <th>Megjegyzés</th>
                    <th>Státusz</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($szamlak_generalt as $s): ?>
                <tr>
                    <td><?= (int)$s['id'] ?></td>
                    <td>
                        <a href="<?= h(nextgen_url('organizers/megtekint.php?id=')) ?><?= (int)$s['szervező_id'] ?>"><?= h($s['szervezo_nev']) ?></a>
                        <?php if (!empty($s['címkék'])): ?>
                            <br>
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
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= h(nextgen_url('finance/szamlak/szerkeszt.php?id=')) ?><?= (int)$s['id'] ?>"><?= h($s['számla_szám']) ?></a>
                        <?php if (!empty($s['elso_fajl_id'])): ?>
                            <a href="<?= h(nextgen_url('finance/szamlak/letoltes.php?id=')) ?><?= (int)$s['elso_fajl_id'] ?>" class="szamla-letolt-icon" title="Csatolt fájl letöltése" target="_blank" rel="noopener" aria-label="Letöltés">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            </a>
                        <?php endif; ?>
                    </td>
                    <td><?= h($s['idoszakok'] ?? '–') ?></td>
                    <td><?= number_format((float)$s['összeg'], 0, ',', ' ') ?></td>
                    <td><?= h(mb_substr($s['belső_megjegyzés'] ?? '', 0, 60)) ?><?= mb_strlen($s['belső_megjegyzés'] ?? '') > 60 ? '…' : '' ?></td>
                    <td>
                        <form method="post" class="statusz-form">
                            <input type="hidden" name="szamla_id" value="<?= (int)$s['id'] ?>">
                            <select name="státusz" onchange="this.form.submit()">
                                <?php foreach (['generált', 'kiküldve', 'kiegyenlítve', 'egyéb', 'KP', 'sztornó'] as $st): ?>
                                    <option value="<?= h($st) ?>" <?= ($s['státusz'] ?? '') === $st ? 'selected' : '' ?>><?= szamla_statusz_label($st) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p>Nincs generált státuszú számla.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Összes számla</h2>
    <p>Szűrhető lista, alapértelmezett sorrend: legújabb elöl. Számlaszám mellett a letöltés ikonnal a csatolt fájl jön le.</p>
    <form method="get" class="toolbar">
        <input type="search" name="kereso" placeholder="Szervező, számlaszám, megjegyzés..." value="<?= h($kereso) ?>">
        <select name="státusz">
            <option value="">Minden státusz</option>
            <?php foreach (['generált', 'kiküldve', 'kiegyenlítve', 'egyéb', 'KP', 'sztornó'] as $st): ?>
                <option value="<?= h($st) ?>" <?= $statusz_szuro === $st ? 'selected' : '' ?>><?= szamla_statusz_label($st) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Szűrés</button>
    </form>
    <div class="table-wrap">
        <table class="sortable-table">
            <thead>
                <tr>
                    <th><?= sort_th('ID', 'id', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Szervező', 'szervezo_nev', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Számlaszám', 'számla_szám', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Időszak', 'idoszakok', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Összeg', 'összeg', $order, $dir_param, $get_params) ?></th>
                    <th>Megjegyzés</th>
                    <th><?= sort_th('Státusz', 'státusz', $order, $dir_param, $get_params) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($szamlak as $s): ?>
                <tr>
                    <td><?= (int)$s['id'] ?></td>
                    <td>
                        <a href="<?= h(nextgen_url('organizers/megtekint.php?id=')) ?><?= (int)$s['szervező_id'] ?>"><?= h($s['szervezo_nev']) ?></a>
                        <?php if (!empty($s['címkék'])): ?>
                            <br>
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
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= h(nextgen_url('finance/szamlak/szerkeszt.php?id=')) ?><?= (int)$s['id'] ?>"><?= h($s['számla_szám']) ?></a>
                        <?php if (!empty($s['elso_fajl_id'])): ?>
                            <a href="<?= h(nextgen_url('finance/szamlak/letoltes.php?id=')) ?><?= (int)$s['elso_fajl_id'] ?>" class="szamla-letolt-icon" title="Csatolt fájl letöltése" target="_blank" rel="noopener" aria-label="Letöltés">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            </a>
                        <?php endif; ?>
                    </td>
                    <td><?= h($s['idoszakok'] ?? '–') ?></td>
                    <td><?= number_format((float)$s['összeg'], 0, ',', ' ') ?></td>
                    <td><?= h(mb_substr($s['belső_megjegyzés'] ?? '', 0, 60)) ?><?= mb_strlen($s['belső_megjegyzés'] ?? '') > 60 ? '…' : '' ?></td>
                    <td>
                        <form method="post" class="statusz-form">
                            <input type="hidden" name="szamla_id" value="<?= (int)$s['id'] ?>">
                            <select name="státusz" onchange="this.form.submit()">
                                <?php foreach (['generált', 'kiküldve', 'kiegyenlítve', 'egyéb', 'KP', 'sztornó'] as $st): ?>
                                    <option value="<?= h($st) ?>" <?= ($s['státusz'] ?? '') === $st ? 'selected' : '' ?>><?= szamla_statusz_label($st) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (empty($szamlak)): ?>
        <p>Nincs találat, vagy még nincs számla. A szervező megtekintőben tudsz újat létrehozni.</p>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
