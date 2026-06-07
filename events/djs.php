<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
require_once __DIR__ . '/lib/dj_request.php';
requireLogin();

$db = getDb();

if (!events_djs_tables_available($db)) {
    $mainContentClass = 'main-content main-content--fullwidth';
    $pageTitle = 'DJ-k';
    require_once dirname(__DIR__) . '/nextgen/partials/header.php';
    echo '<div class="card events-admin-card">';
    echo '<p class="alert alert-error">Hiányoznak a DJ táblák. Futtasd: <code>events/sql/migration_djs.sql</code></p>';
    echo '<p><a href="' . h(events_url('events_admin.php')) . '" class="btn btn-secondary">Vissza az eseményekhez</a></p>';
    echo '</div>';
    require_once dirname(__DIR__) . '/nextgen/partials/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!csrf_validate('events_djs')) {
        flash('error', 'Lejárt vagy érvénytelen munkamenet.');
        redirect(events_url('djs.php'));
    }

    if ($action === 'save_dj') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'A DJ neve kötelező.');
            redirect(events_url('djs.php?open_dj=') . ($id > 0 ? (string) $id : 'new'));
        }
        if ($id > 0) {
            $st = $db->prepare('UPDATE `events_djs` SET `name` = ? WHERE `id` = ?');
            $st->execute([$name, $id]);
            flash('success', 'DJ mentve.');
            rendszer_log('dj', $id, 'Módosítva', $name);
            redirect(events_url('djs.php'));
        }
        $ins = $db->prepare('INSERT INTO `events_djs` (`name`) VALUES (?)');
        $ins->execute([$name]);
        $newId = (int) $db->lastInsertId();
        flash('success', 'DJ létrehozva.');
        rendszer_log('dj', $newId, 'Létrehozva', $name);
        redirect(events_url('djs.php'));
    }

    if ($action === 'delete_dj') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Érvénytelen DJ azonosító.');
            redirect(events_url('djs.php'));
        }
        $st = $db->prepare('SELECT `name` FROM `events_djs` WHERE `id` = ?');
        $st->execute([$id]);
        $self = $st->fetch(PDO::FETCH_ASSOC);
        if (!$self) {
            flash('error', 'A DJ nem található.');
            redirect(events_url('djs.php'));
        }
        $nm = (string) $self['name'];
        $stUse = $db->prepare('SELECT COUNT(*) FROM `events_calendar_event_djs` WHERE `dj_id` = ?');
        $stUse->execute([$id]);
        $useCnt = (int) $stUse->fetchColumn();
        if ($useCnt > 0) {
            flash('error', 'A DJ nem törölhető, mert ' . $useCnt . ' esemény használja.');
            redirect(events_url('djs.php?open_dj=') . $id);
        }
        $db->prepare('DELETE FROM `events_djs` WHERE `id` = ?')->execute([$id]);
        flash('success', 'DJ törölve.');
        rendszer_log('dj', $id, 'Törölve', $nm);
        redirect(events_url('djs.php'));
    }

    redirect(events_url('djs.php'));
}

$djs = $db->query('
    SELECT d.`id`, d.`name`,
           (SELECT COUNT(*) FROM `events_calendar_event_djs` ed WHERE ed.`dj_id` = d.`id`) AS `event_count`
    FROM `events_djs` d
    ORDER BY d.`name` ASC, d.`id` ASC
')->fetchAll(PDO::FETCH_ASSOC);

$openDjRaw = (string) ($_GET['open_dj'] ?? '');
$openDjGroup = '';
if ($openDjRaw === 'new') {
    $openDjGroup = 'new';
} elseif ($openDjRaw !== '' && ctype_digit($openDjRaw)) {
    $openDjGroup = (string) (int) $openDjRaw;
}

$djEventsById = [];
if ($openDjGroup !== '' && $openDjGroup !== 'new') {
    $djEventsById[(int) $openDjGroup] = events_admin_dj_events($db, (int) $openDjGroup);
}

$pageTitle = 'DJ-k';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card">
    <div class="events-list-head">
        <h2 class="events-list-title">DJ-k</h2>
        <div class="events-list-actions">
            <a href="<?= h(events_url('import_csv.php')) ?>" class="btn btn-secondary">CSV import</a>
            <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Események</a>
        </div>
    </div>
    <p class="help">A DJ-k külön entitás a címkéktől. Egy eseményhez több DJ rendelhető. CSV import: <code>events_calendar_event_djs</code> tábla, <code>event_id</code> + <code>dj_name</code> oszlopokkal.</p>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Név</th>
                    <th>Események</th>
                    <th>Műveletek</th>
                </tr>
            </thead>
            <tbody>
                <tr class="<?= $openDjGroup === 'new' ? 'is-active' : '' ?>">
                    <td colspan="4">
                        <details <?= $openDjGroup === 'new' ? 'open' : '' ?>>
                            <summary><strong>Új DJ</strong></summary>
                            <form method="post" style="margin-top:0.75rem;">
                                <?= csrf_input('events_djs') ?>
                                <input type="hidden" name="action" value="save_dj">
                                <input type="hidden" name="id" value="0">
                                <div class="form-group">
                                    <label for="dj_name_new">Név *</label>
                                    <input type="text" id="dj_name_new" name="name" required maxlength="255" value="">
                                </div>
                                <button type="submit" class="btn btn-primary">Létrehozás</button>
                            </form>
                        </details>
                    </td>
                </tr>
                <?php if (!$djs): ?>
                    <tr><td colspan="4">Még nincs DJ. Hozz létre egyet fent, vagy importáld CSV-ből.</td></tr>
                <?php else: ?>
                    <?php foreach ($djs as $dj): ?>
                        <?php
                        $djId = (int) $dj['id'];
                        $isOpen = $openDjGroup === (string) $djId;
                        $eventCount = (int) ($dj['event_count'] ?? 0);
                        $djEvents = $djEventsById[$djId] ?? [];
                        ?>
                        <tr>
                            <td><?= $djId ?></td>
                            <td><?= h((string) $dj['name']) ?></td>
                            <td><?= $eventCount ?></td>
                            <td>
                                <a href="<?= h(events_url('djs.php?open_dj=') . $djId) ?>" class="btn btn-secondary btn-sm">Szerkesztés / események</a>
                                <?php if ($eventCount === 0): ?>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Biztosan törlöd?');">
                                        <?= csrf_input('events_djs') ?>
                                        <input type="hidden" name="action" value="delete_dj">
                                        <input type="hidden" name="id" value="<?= $djId ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm">Törlés</button>
                                    </form>
                                <?php endif; ?>
                                <a href="<?= h(events_url('dj.php?id=') . $djId) ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">Nyilvános</a>
                            </td>
                        </tr>
                        <?php if ($isOpen): ?>
                            <tr>
                                <td colspan="4">
                                    <details open>
                                        <summary><strong><?= h((string) $dj['name']) ?> szerkesztése</strong></summary>
                                        <form method="post" style="margin-top:0.75rem;">
                                            <?= csrf_input('events_djs') ?>
                                            <input type="hidden" name="action" value="save_dj">
                                            <input type="hidden" name="id" value="<?= $djId ?>">
                                            <div class="form-group">
                                                <label for="dj_name_<?= $djId ?>">Név *</label>
                                                <input type="text" id="dj_name_<?= $djId ?>" name="name" required maxlength="255" value="<?= h((string) $dj['name']) ?>">
                                            </div>
                                            <button type="submit" class="btn btn-primary">Mentés</button>
                                        </form>
                                        <h4 style="margin-top:1.25rem;">Események, ahol fellépett (<?= $eventCount ?>)</h4>
                                        <?php if ($eventCount === 0): ?>
                                            <p class="help">Még nincs hozzárendelt esemény.</p>
                                        <?php else: ?>
                                            <?php
                                            if ($djEvents === []) {
                                                $djEvents = events_admin_dj_events($db, $djId);
                                            }
                                            ?>
                                            <ul>
                                                <?php foreach ($djEvents as $ev): ?>
                                                    <li>
                                                        <a href="<?= h(events_url('szerkeszt.php?id=') . (int) $ev['id']) ?>">
                                                            <?= h((string) $ev['event_name']) ?>
                                                        </a>
                                                        <?php if (!empty($ev['event_start'])): ?>
                                                            <span class="help"> — <?= h((string) $ev['event_start']) ?></span>
                                                        <?php endif; ?>
                                                        <span class="help"> (<?= h((string) ($ev['event_status'] ?? '')) ?>)</span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </details>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
