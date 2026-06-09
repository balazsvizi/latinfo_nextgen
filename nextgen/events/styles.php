<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/style_request.php';
requireLogin();

$db = getDb();

if (!events_styles_tables_available($db)) {
    $mainContentClass = 'main-content main-content--fullwidth';
    $pageTitle = 'Stílusok';
    require_once dirname(__DIR__) . '/partials/header.php';
    echo '<div class="card events-admin-card">';
    echo '<p class="alert alert-error">Hiányoznak a stílus táblák. Futtasd: <code>events/sql/migration_styles.sql</code></p>';
    echo '<p><a href="' . h(events_url('events_admin.php')) . '" class="btn btn-secondary">Vissza az eseményekhez</a></p>';
    echo '</div>';
    require_once dirname(__DIR__) . '/partials/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!csrf_validate('events_styles')) {
        flash('error', 'Lejárt vagy érvénytelen munkamenet.');
        redirect(events_url('styles.php'));
    }

    if ($action === 'save_style') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'A stílus neve kötelező.');
            redirect(events_url('styles.php?open_style=') . ($id > 0 ? (string) $id : 'new'));
        }
        if ($id > 0) {
            $st = $db->prepare('UPDATE `events_styles` SET `name` = ? WHERE `id` = ?');
            $st->execute([$name, $id]);
            flash('success', 'Stílus mentve.');
            rendszer_log('stílus', $id, 'Módosítva', $name);
            redirect(events_url('styles.php'));
        }
        $ins = $db->prepare('INSERT INTO `events_styles` (`name`) VALUES (?)');
        $ins->execute([$name]);
        $newId = (int) $db->lastInsertId();
        flash('success', 'Stílus létrehozva.');
        rendszer_log('stílus', $newId, 'Létrehozva', $name);
        redirect(events_url('styles.php'));
    }

    if ($action === 'delete_style') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Érvénytelen stílus azonosító.');
            redirect(events_url('styles.php'));
        }
        $st = $db->prepare('SELECT `name` FROM `events_styles` WHERE `id` = ?');
        $st->execute([$id]);
        $self = $st->fetch(PDO::FETCH_ASSOC);
        if (!$self) {
            flash('error', 'A stílus nem található.');
            redirect(events_url('styles.php'));
        }
        $nm = (string) $self['name'];
        $useCnt = events_admin_style_event_count($db, $id);
        if ($useCnt > 0) {
            flash('error', 'A stílus nem törölhető, mert ' . $useCnt . ' esemény használja.');
            redirect(events_url('styles.php?open_style=') . $id);
        }
        $db->prepare('DELETE FROM `events_styles` WHERE `id` = ?')->execute([$id]);
        flash('success', 'Stílus törölve.');
        rendszer_log('stílus', $id, 'Törölve', $nm);
        redirect(events_url('styles.php'));
    }

    redirect(events_url('styles.php'));
}

$styles = $db->query('
    SELECT s.`id`, s.`name`
    FROM `events_styles` s
    ORDER BY s.`name` ASC, s.`id` ASC
')->fetchAll(PDO::FETCH_ASSOC);

$openStyleRaw = (string) ($_GET['open_style'] ?? '');
$openStyleGroup = '';
if ($openStyleRaw === 'new') {
    $openStyleGroup = 'new';
} elseif ($openStyleRaw !== '' && ctype_digit($openStyleRaw)) {
    $openStyleGroup = (string) (int) $openStyleRaw;
}

$styleEventsById = [];
if ($openStyleGroup !== '' && $openStyleGroup !== 'new') {
    $styleEventsById[(int) $openStyleGroup] = events_admin_style_events($db, (int) $openStyleGroup);
}

$pageTitle = 'Stílusok';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card">
    <div class="events-list-head">
        <h2 class="events-list-title">Stílusok</h2>
        <div class="events-list-actions">
            <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Események</a>
        </div>
    </div>
    <p class="help">Egy eseményhez több fő és több kiegészítő stílus rendelhető. CSV import: <code>events_calendar_event_main_styles</code> / <code>events_calendar_event_supplementary_styles</code> táblák, <code>event_id</code> + <code>style_name</code> oszlopokkal.</p>

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
                <tr class="<?= $openStyleGroup === 'new' ? 'is-active' : '' ?>">
                    <td colspan="4">
                        <details <?= $openStyleGroup === 'new' ? 'open' : '' ?>>
                            <summary><strong>Új stílus</strong></summary>
                            <form method="post" style="margin-top:0.75rem;">
                                <?= csrf_input('events_styles') ?>
                                <input type="hidden" name="action" value="save_style">
                                <input type="hidden" name="id" value="0">
                                <div class="form-group">
                                    <label for="style_name_new">Név *</label>
                                    <input type="text" id="style_name_new" name="name" required maxlength="255" value="">
                                </div>
                                <button type="submit" class="btn btn-primary">Létrehozás</button>
                            </form>
                        </details>
                    </td>
                </tr>
                <?php if (!$styles): ?>
                    <tr><td colspan="4">Még nincs stílus. Hozz létre egyet fent, vagy importáld CSV-ből.</td></tr>
                <?php else: ?>
                    <?php foreach ($styles as $style): ?>
                        <?php
                        $styleId = (int) $style['id'];
                        $isOpen = $openStyleGroup === (string) $styleId;
                        $eventCount = events_admin_style_event_count($db, $styleId);
                        $styleEvents = $styleEventsById[$styleId] ?? [];
                        ?>
                        <tr>
                            <td><?= $styleId ?></td>
                            <td><?= h((string) $style['name']) ?></td>
                            <td><?= $eventCount ?></td>
                            <td>
                                <a href="<?= h(events_url('styles.php?open_style=') . $styleId) ?>" class="btn btn-secondary btn-sm">Szerkesztés / események</a>
                                <?php if ($eventCount === 0): ?>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Biztosan törlöd?');">
                                        <?= csrf_input('events_styles') ?>
                                        <input type="hidden" name="action" value="delete_style">
                                        <input type="hidden" name="id" value="<?= $styleId ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm">Törlés</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($isOpen): ?>
                            <tr>
                                <td colspan="4">
                                    <details open>
                                        <summary><strong><?= h((string) $style['name']) ?> szerkesztése</strong></summary>
                                        <form method="post" style="margin-top:0.75rem;">
                                            <?= csrf_input('events_styles') ?>
                                            <input type="hidden" name="action" value="save_style">
                                            <input type="hidden" name="id" value="<?= $styleId ?>">
                                            <div class="form-group">
                                                <label for="style_name_<?= $styleId ?>">Név *</label>
                                                <input type="text" id="style_name_<?= $styleId ?>" name="name" required maxlength="255" value="<?= h((string) $style['name']) ?>">
                                            </div>
                                            <button type="submit" class="btn btn-primary">Mentés</button>
                                        </form>
                                        <h4 style="margin-top:1.25rem;">Események ezzel a stílussal (<?= $eventCount ?>)</h4>
                                        <?php if ($eventCount === 0): ?>
                                            <p class="help">Még nincs hozzárendelt esemény.</p>
                                        <?php else: ?>
                                            <?php
                                            if ($styleEvents === []) {
                                                $styleEvents = events_admin_style_events($db, $styleId);
                                            }
                                            ?>
                                            <ul>
                                                <?php foreach ($styleEvents as $ev): ?>
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
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
