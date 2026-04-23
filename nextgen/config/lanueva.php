<?php
$pageTitle = 'LaNueva';
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../includes/landingpage_table.php';

$db = getDb();
ensure_landingpage_table($db);

$tipus = $_GET['tipus'] ?? '';
if (!in_array($tipus, ['', 'visszajelzes', 'ertesites'], true)) {
    $tipus = '';
}

$oldal = max(1, (int) ($_GET['oldal'] ?? 1));
$per_page = 50;
$offset = ($oldal - 1) * $per_page;

$where_sql = '';
$params = [];
if ($tipus === 'visszajelzes') {
    $where_sql = 'WHERE (email IS NULL OR email = \'\')';
} elseif ($tipus === 'ertesites') {
    $where_sql = 'WHERE email IS NOT NULL AND email != \'\'';
}

$order = 'létrehozva';
$dir_param = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc';
$dir = $dir_param === 'desc' ? 'DESC' : 'ASC';

$count_stmt = $db->prepare("SELECT COUNT(*) FROM nextgen_landing_feedback $where_sql");
$count_stmt->execute($params);
$total = (int) $count_stmt->fetchColumn();

$stmt = $db->prepare("
    SELECT id, ilyen_legyen, ilyen_ne_legyen, email, ip, user_agent, létrehozva
    FROM nextgen_landing_feedback
    $where_sql
    ORDER BY $order $dir
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$sorok = $stmt->fetchAll();

$get_params = array_filter([
    'tipus' => $tipus,
    'order' => $order,
    'dir' => $dir_param,
]);
$export_query = http_build_query(array_filter([
    'tipus' => $tipus,
], function ($v) {
    return $v !== '' && $v !== null;
}));
$export_href = nextgen_url('config/lanueva_export.php') . ($export_query !== '' ? '?' . $export_query : '');

function landing_lista_tipus(array $r): string {
    if (isset($r['email']) && trim((string) $r['email']) !== '') {
        return 'Értesítés (e-mail)';
    }
    return 'Visszajelzés';
}

function landing_lista_ua_rovid(?string $ua, int $max = 100): string {
    if ($ua === null || $ua === '') {
        return '–';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($ua, 'UTF-8') <= $max) {
            return $ua;
        }
        return mb_substr($ua, 0, $max, 'UTF-8') . '…';
    }
    if (strlen($ua) <= $max) {
        return $ua;
    }
    return substr($ua, 0, $max) . '…';
}
?>
<div class="card card-landing-visszajelzesek">
    <h2>LaNueva</h2>
    <p class="card-lead">A nyilvános landingről érkezett szöveges visszajelzések és induláskori e-mail feliratkozások. Az időbélyeg a szerver szerinti mentés ideje (<?= h(date_default_timezone_get()) ?>).</p>

    <form method="get" class="toolbar toolbar-inline" action="<?= h(nextgen_url('config/lanueva.php')) ?>">
        <label for="landing-tipus-szuro">Típus</label>
        <select name="tipus" id="landing-tipus-szuro">
            <option value="" <?= $tipus === '' ? 'selected' : '' ?>>Mind</option>
            <option value="visszajelzes" <?= $tipus === 'visszajelzes' ? 'selected' : '' ?>>Visszajelzés (La nueva)</option>
            <option value="ertesites" <?= $tipus === 'ertesites' ? 'selected' : '' ?>>Értesítés (e-mail)</option>
        </select>
        <button type="submit" class="btn btn-primary">Szűrés</button>
        <a href="<?= h($export_href) ?>" class="btn btn-secondary">Letöltés Excelbe</a>
    </form>

    <div class="table-wrap">
        <table class="sortable-table landing-visszajelzesek-table">
            <thead>
                <tr>
                    <th><?= sort_th('Időbélyeg', 'létrehozva', $order, $dir_param, $get_params) ?></th>
                    <th>Típus</th>
                    <th>Tartalom</th>
                    <th>IP</th>
                    <th>Böngésző (rövid)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sorok)): ?>
                <tr>
                    <td colspan="5" class="text-muted">Még nincs bejegyzés.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($sorok as $r): ?>
                <tr>
                    <td class="landing-ts"><?= h($r['létrehozva']) ?></td>
                    <td><?= h(landing_lista_tipus($r)) ?></td>
                    <td class="landing-tartalom">
                        <?php if (trim((string) ($r['email'] ?? '')) !== ''): ?>
                            <strong>E-mail:</strong> <?= h($r['email']) ?>
                        <?php else: ?>
                            <?php if (trim((string) ($r['ilyen_legyen'] ?? '')) !== ''): ?>
                                <div class="landing-blob"><strong>Ilyen legyen:</strong> <?= nl2br(h($r['ilyen_legyen'])) ?></div>
                            <?php endif; ?>
                            <?php if (trim((string) ($r['ilyen_ne_legyen'] ?? '')) !== ''): ?>
                                <div class="landing-blob"><strong>Ilyen ne legyen:</strong> <?= nl2br(h($r['ilyen_ne_legyen'])) ?></div>
                            <?php endif; ?>
                            <?php if (trim((string) ($r['ilyen_legyen'] ?? '')) === '' && trim((string) ($r['ilyen_ne_legyen'] ?? '')) === ''): ?>
                                <span class="text-muted">(üres)</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= h($r['ip'] ?? '–') ?></td>
                    <td class="landing-ua" title="<?= h($r['user_agent'] ?? '') ?>"><?= h(landing_lista_ua_rovid($r['user_agent'] ?? null)) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p class="landing-visszajelzesek-meta text-muted">Összesen: <?= (int) $total ?> bejegyzés.</p>

    <?php
    $total_pages = (int) ceil($total / $per_page);
    if ($total_pages > 1):
        $q = http_build_query(array_filter([
            'tipus' => $tipus,
            'order' => $order,
            'dir' => $dir_param,
        ], function ($v) { return $v !== '' && $v !== null; }));
        $base = nextgen_url('config/lanueva.php?') . $q . ($q ? '&' : '');
    ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i === $oldal): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= h($base . 'oldal=' . $i) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
