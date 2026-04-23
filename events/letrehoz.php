<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
require_once __DIR__ . '/lib/event_request.php';
requireLogin();

$db = getDb();
$organizers = events_load_organizer_options($db);

$defaults = [
    'event_name' => '',
    'event_slug' => '',
    'event_content' => '',
    'event_status' => events_default_post_status(),
    'event_start_date' => null,
    'event_start_time' => null,
    'event_end_date' => null,
    'event_end_time' => null,
    'event_allday' => 0,
    'event_cost_from' => null,
    'event_cost_to' => null,
    'event_url' => null,
    'event_latinfohu_partner' => 0,
    'organizer_id' => null,
    'venue_id' => null,
];

$hiba = '';
$e = events_row_for_form($defaults);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$row, $err] = events_row_from_request($db, $defaults, null);
    if ($err !== null) {
        $hiba = $err;
        $e = events_row_for_form($row);
    } else {
        try {
            $stmt = $db->prepare('
                INSERT INTO `events_calendar_events` (
                    event_name, event_slug, event_content, event_status,
                    event_start_date, event_start_time, event_end_date, event_end_time, event_allday,
                    event_cost_from, event_cost_to, event_url, event_latinfohu_partner,
                    organizer_id, venue_id
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ');
            $stmt->execute([
                $row['event_name'],
                $row['event_slug'],
                $row['event_content'],
                $row['event_status'],
                $row['event_start_date'],
                $row['event_start_time'],
                $row['event_end_date'],
                $row['event_end_time'],
                $row['event_allday'],
                $row['event_cost_from'],
                $row['event_cost_to'],
                $row['event_url'],
                $row['event_latinfohu_partner'],
                $row['organizer_id'],
                $row['venue_id'],
            ]);
            $newId = (int) $db->lastInsertId();
            rendszer_log('esemény', $newId, 'Létrehozva', $row['event_name']);
            flash('success', 'Esemény létrehozva.');
            redirect(events_url('events_admin.php'));
        } catch (Throwable $ex) {
            $hiba = 'Mentési hiba: ' . $ex->getMessage();
            $e = events_row_for_form($row);
        }
    }
}

$pageTitle = 'Új esemény';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<div class="card">
    <h2>Új esemény</h2>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post">
        <?php require __DIR__ . '/partials/event_fields.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/partials/html_editor_script.php'; ?>
<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
