<?php
declare(strict_types=1);

require_once __DIR__ . '/../../nextgen/core/database.php';
require_once __DIR__ . '/../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../nextgen/includes/functions.php';
require_once __DIR__ . '/../../nextgen/lib/organizer/unify.php';
requireLogin();

$db = getDb();
$pageUrl = nextgen_url('organizers/egyesites.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require('organizer_egyesites', '_csrf', $pageUrl);
    $action = (string) ($_POST['action'] ?? '');
    $financeId = (int) ($_POST['finance_id'] ?? 0);
    if ($action === 'auto') {
        $matched = organizer_unify_auto_match($db);
        flash('success', 'Automatikus párosítás: ' . (int) $matched['linked'] . ' egyértelmű név összekötve.');
    } elseif ($action === 'link') {
        $eventsId = (int) ($_POST['events_organizer_id'] ?? 0);
        $result = organizer_unify_link($db, $financeId, $eventsId);
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Összekötve.' : (string) $result['error']);
    } elseif ($action === 'create') {
        $result = organizer_unify_create_event_organizer($db, $financeId);
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Eseményszervező létrehozva és összekötve.' : (string) $result['error']);
    } elseif ($action === 'unlink') {
        $result = organizer_unify_unlink($db, $financeId);
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Szétkapcsolva.' : (string) $result['error']);
    } else {
        flash('error', 'Ismeretlen művelet.');
    }
    redirect($pageUrl);
}

organizer_unify_auto_match($db);
$rows = organizer_unify_finance_rows($db);
$eventOptions = organizer_unify_event_organizer_options($db);
$unlinked = [];
$linked = [];
foreach ($rows as $row) {
    if ($row['events_organizer_id'] > 0) {
        $linked[] = $row;
    } else {
        $unlinked[] = $row;
    }
}

$pageTitle = 'Szervezők egyesítése';
require_once __DIR__ . '/../partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<div class="card">
    <h2>Finance és eseményszervezők</h2>
    <p class="help">Az egyértelműen azonos név automatikusan összekötődik. A többit itt lehet egy meglévő eseményszervezőhöz kötni, vagy abból a névből új eseményszervezőt létrehozni. A számla a finance rekordon marad, a szerkesztés az eseményszervezőn folytatódik.</p>
    <p>
        <a href="<?= h(nextgen_url('organizers/')) ?>" class="btn btn-secondary btn-sm">← Finance szervezők</a>
        <a href="<?= h(nextgen_url('events/organizers.php')) ?>" class="btn btn-secondary btn-sm">Eseményszervezők</a>
    </p>
</div>

<div class="card">
    <h3>Nincs még összekötve (<?= count($unlinked) ?>)</h3>
    <?php if ($unlinked === []): ?>
        <p>Minden finance szervező egy eseményszervezőhöz tartozik.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Finance</th><th>Meglévő eseményszervező</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($unlinked as $row): ?>
                        <tr id="fin-<?= (int) $row['id'] ?>">
                            <td><?= h($row['név']) ?> <span class="muted">#<?= (int) $row['id'] ?></span></td>
                            <td>
                                <form method="post" class="inline-form">
                                    <?= csrf_input('organizer_egyesites') ?>
                                    <input type="hidden" name="action" value="link">
                                    <input type="hidden" name="finance_id" value="<?= (int) $row['id'] ?>">
                                    <select name="events_organizer_id" required>
                                        <option value="">Válassz…</option>
                                        <?php foreach ($eventOptions as $option): ?>
                                            <option value="<?= (int) $option['id'] ?>"><?= h($option['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm">Összekötés</button>
                                </form>
                            </td>
                            <td>
                                <form method="post">
                                    <?= csrf_input('organizer_egyesites') ?>
                                    <input type="hidden" name="action" value="create">
                                    <input type="hidden" name="finance_id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">Új eseményszervező ebből a névből</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Összekötve (<?= count($linked) ?>)</h3>
    <?php if ($linked === []): ?>
        <p>Még nincs összekötött szervező.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Finance</th><th>Eseményszervező</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($linked as $row): ?>
                        <tr>
                            <td>
                                <a href="<?= h(nextgen_url('organizers/megtekint.php?id=') . (int) $row['id']) ?>"><?= h($row['név']) ?></a>
                            </td>
                            <td>
                                <a href="<?= h(nextgen_url('events/organizer_szerkeszt.php?id=') . (int) $row['events_organizer_id']) ?>"><?= h($row['events_name']) ?></a>
                            </td>
                            <td>
                                <form method="post" onsubmit="return confirm('Szétkapcsolod ezt a párt? A számlák a finance szervezőn maradnak.');">
                                    <?= csrf_input('organizer_egyesites') ?>
                                    <input type="hidden" name="action" value="unlink">
                                    <input type="hidden" name="finance_id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">Szétkapcsolás</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
