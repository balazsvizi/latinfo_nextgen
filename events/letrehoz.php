<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
require_once __DIR__ . '/lib/event_request.php';
requireLogin();

$db = getDb();
$organizers = events_load_organizer_options($db);
$categories = events_load_category_options($db);
$venues = events_load_venue_options($db);
$tags = events_load_tag_options($db);

$defaults = [
    'event_name' => '',
    'event_slug' => '',
    'event_content' => '',
    'event_status' => events_default_post_status(),
    'event_start' => null,
    'event_end' => null,
    'event_allday' => 0,
    'event_cost_from' => null,
    'event_cost_to' => null,
    'event_url' => null,
    'event_featured_image_url' => null,
    'event_latinfohu_partner' => 0,
    'organizer_ids' => [],
    'category_ids' => [],
    'tag_ids' => [],
    'venue_id' => null,
];

$hiba = '';
$e = events_row_for_form($defaults);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('events_letrehoz')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } else {
    [$row, $err, $organizerIds, $categoryIds, $tagIds] = events_row_from_request($db, $defaults, null);
    if ($err !== null) {
        $hiba = $err;
        $e = events_row_for_form($row);
        $e['organizer_ids'] = $organizerIds;
        $e['category_ids'] = $categoryIds;
        $e['tag_ids'] = $tagIds;
    } else {
        try {
            $db->beginTransaction();
            $stmt = $db->prepare('
                INSERT INTO `events_calendar_events` (
                    event_name, event_slug, event_content, event_status,
                    event_start, event_end, event_allday,
                    event_cost_from, event_cost_to, event_url, event_featured_image_url, event_latinfohu_partner,
                    venue_id
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
            ');
            $stmt->execute([
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
            ]);
            $newId = (int) $db->lastInsertId();
            events_save_event_organizers($db, $newId, $organizerIds);
            events_save_event_categories($db, $newId, $categoryIds);
            events_save_event_tags($db, $newId, $tagIds);
            $db->commit();
            rendszer_log('esemény', $newId, 'Létrehozva', $row['event_name']);
            flash('success', 'Esemény létrehozva.');
            redirect(events_url('events_admin.php'));
        } catch (Throwable $ex) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('events letrehoz mentesi hiba: ' . $ex->getMessage());
            $hiba = 'Mentési hiba történt. Kérlek próbáld újra.';
            $e = events_row_for_form($row);
            $e['organizer_ids'] = $organizerIds;
            $e['category_ids'] = $categoryIds;
            $e['tag_ids'] = $tagIds;
        }
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
    <form method="post" enctype="multipart/form-data">
        <?= csrf_input('events_letrehoz') ?>
        <?php require __DIR__ . '/partials/event_fields.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/partials/html_editor_script.php'; ?>
<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
