<?php
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/events/lib/event_notify_email.php';
requireLogin();

$db = getDb();
ensure_levelsablonok_table($db);
events_notify_email_ensure_schema($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_event_notify_default'])) {
    if (!csrf_validate('levelsablonok_default')) {
        flash('error', 'Lejárt vagy érvénytelen munkamenet.');
        redirect(nextgen_url('config/levelsablonok/'));
    }
    $tplId = (int) ($_POST['template_id'] ?? 0);
    if ($tplId > 0) {
        $check = $db->prepare('SELECT `id` FROM `finance_email_templates` WHERE `id` = ? LIMIT 1');
        $check->execute([$tplId]);
        if ($check->fetchColumn()) {
            events_notify_email_set_default_template_id($db, $tplId);
            rendszer_log('levélsablon', $tplId, 'Esemény-értesítő default', null);
            flash('success', 'Az esemény-értesítő alapértelmezett sablonja beállítva.');
        } else {
            flash('error', 'A sablon nem található.');
        }
    }
    redirect(nextgen_url('config/levelsablonok/'));
}

$kereso = trim($_GET['kereso'] ?? '');
$order = isset($_GET['order']) && in_array($_GET['order'], ['név', 'kód', 'módosítva', 'létrehozva'], true) ? $_GET['order'] : 'név';
$dir_param = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';
$dir = $dir_param === 'desc' ? 'DESC' : 'ASC';

$where = '';
$params = [];
if ($kereso !== '') {
    $where = 'WHERE név LIKE ? OR kód LIKE ? OR megjegyzés LIKE ?';
    $p = '%' . $kereso . '%';
    $params = [$p, $p, $p];
}

$stmt = $db->prepare("SELECT id, név, kód, megjegyzés, módosítva, létrehozva FROM finance_email_templates $where ORDER BY $order $dir");
$stmt->execute($params);
$sablonok = $stmt->fetchAll();
$defaultNotifyTplId = events_notify_email_default_template_id($db);

$get_params = array_filter(['kereso' => $kereso]);
$pageTitle = 'Levélsablonok';
require_once __DIR__ . '/../../partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($e = flash('error')): ?><p class="alert alert-error"><?= h($e) ?></p><?php endif; ?>

<div class="card">
    <h2>Levélsablonok</h2>
    <p class="help">
        Az esemény szerkesztő „E-mail” funkciója az alábbi listából választ sablont.
        Az <strong>esemény-értesítő default</strong> jelölés határozza meg, melyik sablonnal nyílik meg a levél.
    </p>
    <form method="get" class="toolbar">
        <input type="search" name="kereso" placeholder="Név, kód vagy megjegyzés..." value="<?= h($kereso) ?>">
        <button type="submit" class="btn btn-primary">Keresés</button>
        <a href="<?= h(nextgen_url('config/levelsablonok/letrehoz.php')) ?>" class="btn btn-primary">Új levélsablon</a>
    </form>

    <div class="table-wrap">
        <table class="sortable-table">
            <thead>
                <tr>
                    <th><?= sort_th('Név', 'név', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Kód', 'kód', $order, $dir_param, $get_params) ?></th>
                    <th>Megjegyzés</th>
                    <th>Esemény-értesítő</th>
                    <th><?= sort_th('Módosítva', 'módosítva', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Létrehozva', 'létrehozva', $order, $dir_param, $get_params) ?></th>
                    <th>Műveletek</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sablonok as $s): ?>
                <?php $sid = (int) $s['id']; ?>
                <tr>
                    <td><?= h($s['név']) ?></td>
                    <td><code><?= h($s['kód']) ?></code></td>
                    <td><?= h(mb_substr($s['megjegyzés'] ?? '', 0, 80)) ?><?= mb_strlen($s['megjegyzés'] ?? '') > 80 ? '…' : '' ?></td>
                    <td>
                        <?php if ($sid === $defaultNotifyTplId): ?>
                            <strong>Default</strong>
                        <?php else: ?>
                            <form method="post" class="inline-form">
                                <?= csrf_input('levelsablonok_default') ?>
                                <input type="hidden" name="template_id" value="<?= $sid ?>">
                                <button type="submit" name="set_event_notify_default" value="1" class="btn btn-sm btn-secondary">Defaultnak</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td><?= h($s['módosítva']) ?></td>
                    <td><?= h($s['létrehozva']) ?></td>
                    <td class="actions">
                        <a href="<?= h(nextgen_url('config/levelsablonok/szerkeszt.php?id=')) ?><?= $sid ?>" class="btn btn-sm btn-secondary">Szerkeszt</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (empty($sablonok)): ?><p>Nincs még levélsablon.</p><?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
