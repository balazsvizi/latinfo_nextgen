<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
require_once __DIR__ . '/lib/venue_request.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Hiányzó azonosító.');
    redirect(events_url('venues.php'));
}

$db = getDb();
$stmt = $db->prepare('SELECT * FROM `events_venues` WHERE `id` = ?');
$stmt->execute([$id]);
$venue = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$venue) {
    flash('error', 'Helyszín nem található.');
    redirect(events_url('venues.php'));
}

$hiba = '';
$v = events_venue_row_for_form($venue);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'delete') {
        try {
            $db->prepare('UPDATE `events_calendar_events` SET `venue_id` = NULL WHERE `venue_id` = ?')->execute([$id]);
            $db->prepare('DELETE FROM `events_venues` WHERE `id` = ?')->execute([$id]);
            rendszer_log('helyszín', $id, 'Törölve', (string) ($venue['name'] ?? ''));
            flash('success', 'Helyszín törölve.');
            redirect(events_url('venues.php'));
        } catch (Throwable $ex) {
            $hiba = 'Törlési hiba: ' . $ex->getMessage();
        }
    } else {
        [$row, $err] = events_venue_row_from_post($db, $venue, $id);
        if ($err !== null) {
            $hiba = $err;
            $v = events_venue_row_for_form($row);
        } else {
            try {
                $upd = $db->prepare('
                    UPDATE `events_venues` SET
                        `name` = ?, `slug` = ?, `description` = ?, `address` = ?
                    WHERE `id` = ?
                ');
                $upd->execute([
                    $row['name'],
                    $row['slug'],
                    $row['description'] === '' ? null : $row['description'],
                    $row['address'] === '' ? null : $row['address'],
                    $id,
                ]);
                rendszer_log('helyszín', $id, 'Módosítva', $row['name']);
                flash('success', 'Mentve.');
                redirect(events_url('venue_szerkeszt.php?id=') . $id);
            } catch (Throwable $ex) {
                $hiba = 'Mentési hiba: ' . $ex->getMessage();
                $v = events_venue_row_for_form($row);
            }
        }
    }
}

$pageTitle = 'Helyszín: ' . ($venue['name'] ?? '');
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<div class="card">
    <h2>Helyszín szerkesztése</h2>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post">
        <?php require __DIR__ . '/partials/venue_fields.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" name="action" value="save">Mentés</button>
            <a href="<?= h(events_url('venues.php')) ?>" class="btn btn-secondary">Vissza a listához</a>
            <button type="submit" class="btn btn-danger" style="margin-left:auto" name="action" value="delete" onclick="return confirm('Biztosan törlöd ezt a helyszínt? Az eseményekről leválasztjuk a hivatkozást.');">Törlés</button>
        </div>
    </form>
</div>
<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
