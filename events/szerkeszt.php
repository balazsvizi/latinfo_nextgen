<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
require_once __DIR__ . '/lib/event_request.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Hiányzó azonosító.');
    redirect(events_url('events_admin.php'));
}

$db = getDb();
$stmt = $db->prepare('SELECT * FROM `events_calendar_events` WHERE id = ?');
$stmt->execute([$id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    flash('error', 'Esemény nem található.');
    redirect(events_url('events_admin.php'));
}

$organizers = events_load_organizer_options($db);
$venues = events_load_venue_options($db);
$event['organizer_ids'] = events_load_event_organizer_ids($db, $id);

$hiba = '';
$e = events_row_for_form($event);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('events_szerkeszt')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } else {
    [$row, $err, $organizerIds] = events_row_from_request($db, $event, $id);
    if ($err !== null) {
        $hiba = $err;
        $e = events_row_for_form($row);
        $e['organizer_ids'] = $organizerIds;
    } else {
        try {
            $db->beginTransaction();
            $upd = $db->prepare('
                UPDATE `events_calendar_events` SET
                    event_name = ?, event_slug = ?, event_content = ?, event_status = ?,
                    event_start = ?, event_end = ?, event_allday = ?,
                    event_cost_from = ?, event_cost_to = ?, event_url = ?, event_featured_image_url = ?, event_latinfohu_partner = ?,
                    venue_id = ?
                WHERE id = ?
            ');
            $upd->execute([
                $row['event_name'],
                $row['event_slug'],
                $row['event_content'],
                $row['event_status'],
                $row['event_start'],
                $row['event_end'],
                $row['event_allday'],
                $row['event_cost_from'],
                $row['event_cost_to'],
                $row['event_url'],
                $row['event_featured_image_url'],
                $row['event_latinfohu_partner'],
                $row['venue_id'],
                $id,
            ]);
            events_save_event_organizers($db, $id, $organizerIds);
            $db->commit();
            rendszer_log('esemény', $id, 'Módosítva', $row['event_name']);
            flash('success', 'Mentve.');
            redirect(events_url('szerkeszt.php?id=') . $id);
        } catch (Throwable $ex) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('events szerkeszt mentesi hiba: ' . $ex->getMessage());
            $hiba = 'Mentési hiba történt. Kérlek próbáld újra.';
            $e = events_row_for_form($row);
            $e['organizer_ids'] = $organizerIds;
        }
    }
    }
}

$logStmt = $db->prepare('
    SELECT r.*, a.név AS admin_név
    FROM nextgen_system_log r
    LEFT JOIN nextgen_admins a ON a.id = r.admin_id
    WHERE r.entitás = ? AND r.entitás_id = ?
    ORDER BY r.létrehozva DESC
    LIMIT 30
');
$logStmt->execute(['esemény', $id]);
$sablonLogok = $logStmt->fetchAll();

$pageTitle = 'Esemény szerkesztése: ' . ($event['event_name'] ?? '');
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<div class="card">
    <h2>Esemény szerkesztése</h2>
    <p class="help"><?php if (($e['event_status'] ?? '') === events_public_post_status()): ?>Nyilvános előnézet: <a href="<?= h(events_megjelenit_url($e['event_slug'])) ?>" target="_blank" rel="noopener"><?= h(events_megjelenit_url($e['event_slug'])) ?></a><?php else: ?>Nyilvános oldal csak „Közzétéve” (publish) státusznál érhető el.<?php endif; ?></p>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_input('events_szerkeszt') ?>
        <?php require __DIR__ . '/partials/event_fields.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Vissza a listához</a>
        </div>
    </form>
</div>
<div class="card">
    <h2>Napló</h2>
    <div class="log-list">
        <?php foreach ($sablonLogok as $l): ?>
        <div class="log-item">
            <span class="log-date"><?= h($l['létrehozva']) ?> <?= !empty($l['admin_név']) ? '(' . h($l['admin_név']) . ')' : '' ?></span>
            <p style="margin:0.25rem 0 0;"><?= h($l['művelet']) ?><?= !empty($l['részletek']) ? ' – ' . nl2br(h($l['részletek'])) : '' ?></p>
        </div>
        <?php endforeach; ?>
        <?php if (empty($sablonLogok)): ?><p>Még nincs naplóbejegyzés.</p><?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/partials/html_editor_script.php'; ?>
<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
