<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_request.php';
require_once __DIR__ . '/lib/event_log.php';
require_once __DIR__ . '/lib/event_edit_stats.php';
require_once __DIR__ . '/lib/event_delete.php';
require_once __DIR__ . '/lib/admin_event_calendar.php';
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
$categories = events_load_category_options($db);
$venues = events_load_venue_options($db);
$tags = events_load_tag_options($db);
$styles = events_load_style_options($db);
$event['organizer_ids'] = events_load_event_organizer_ids($db, $id);
$event['category_ids'] = events_load_event_category_ids($db, $id);
$event['tag_ids'] = events_load_event_tag_ids($db, $id);
$event['main_style_ids'] = events_load_event_main_style_ids($db, $id);
$event['supplementary_style_ids'] = events_load_event_supplementary_style_ids($db, $id);

$hiba = '';
$e = events_row_for_form($event);
$eventFormShowPermanentDelete = (string) ($event['event_status'] ?? '') === 'trash';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('events_szerkeszt')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } else {
    $formAction = (string) ($_POST['form_action'] ?? 'save');
    if ($formAction === 'permanent_delete') {
        [$deleted, $deleteMsg] = events_permanent_delete_event($db, $id);
        if (!$deleted) {
            $hiba = $deleteMsg;
        } else {
            rendszer_log('esemény', $id, 'Véglegesen törölve', $deleteMsg);
            flash('success', 'Az esemény véglegesen törölve.');
            redirect(events_url('events_admin.php'));
        }
    } else {
    [$row, $err, $organizerIds, $categoryIds, $tagIds, $mainStyleIds, $supplementaryStyleIds] = events_row_from_request($db, $event, $id);
    if ($err !== null) {
        $hiba = $err;
        $e = events_row_for_form($row);
        $e['organizer_ids'] = $organizerIds;
        $e['category_ids'] = $categoryIds;
        $e['tag_ids'] = $tagIds;
        $e['main_style_ids'] = $mainStyleIds;
        $e['supplementary_style_ids'] = $supplementaryStyleIds;
    } else {
        try {
            $db->beginTransaction();
            $upd = $db->prepare('
                UPDATE `events_calendar_events` SET
                    event_name = ?, event_slug = ?, event_content = ?, event_status = ?,
                    event_start = ?, event_end = ?, event_allday = ?,
                    event_change_active = ?, event_change_type = ?, event_change_note = ?,
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
                $row['event_change_active'],
                $row['event_change_type'],
                $row['event_change_note'],
                $row['event_cost_from'],
                $row['event_cost_to'],
                $row['event_url'],
                $row['event_featured_image_url'],
                $row['event_latinfohu_partner'],
                $row['venue_id'],
                $id,
            ]);
            events_save_event_organizers($db, $id, $organizerIds);
            events_save_event_categories($db, $id, $categoryIds);
            events_save_event_tags($db, $id, $tagIds);
            events_save_event_main_styles($db, $id, $mainStyleIds);
            events_save_event_supplementary_styles($db, $id, $supplementaryStyleIds);
            $db->commit();
            rendszer_log(
                'esemény',
                $id,
                'Módosítva',
                events_build_log_details($db, $row, $organizerIds, $categoryIds)
            );
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
            $e['category_ids'] = $categoryIds;
            $e['tag_ids'] = $tagIds;
            $e['main_style_ids'] = $mainStyleIds;
            $e['supplementary_style_ids'] = $supplementaryStyleIds;
        }
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

$statsParams = events_edit_stats_params_from_request($_GET);
$statsData = events_edit_stats_for_event($db, $id, $statsParams);

$eventEditMonthKey = events_admin_calendar_month_key_from_event($event);
$eventEditBackCalendarUrl = events_admin_calendar_view_url($eventEditMonthKey, []);
$eventEditPublicCalendarUrl = events_public_home_url('hu', ['month' => $eventEditMonthKey]);
$eventEditCopyUrl = events_url('letrehoz.php?copy_from=') . $id;
$eventEditPreviewUrl = null;
if ((string) ($event['event_status'] ?? '') === events_public_post_status()
    && trim((string) ($event['event_slug'] ?? '')) !== '') {
    $eventEditPreviewUrl = events_megjelenit_url((string) $event['event_slug']);
}

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Esemény szerkesztése: ' . ($event['event_name'] ?? '');
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('warning')): ?><p class="alert alert-warning"><?= h($s) ?></p><?php endif; ?>

<?php require __DIR__ . '/partials/admin_event_edit_float_tools.php'; ?>

<div class="events-edit-page">
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="events-edit-form" id="events-edit-form"
          data-entity-create-url="<?= h(events_url('ajax_entity_quick_create.php')) ?>"
          data-entity-create-csrf="<?= h(csrf_token('events_entity_create')) ?>">
        <?= csrf_input('events_szerkeszt') ?>
        <?php
        $eventFormCopyUrl = events_url('letrehoz.php?copy_from=') . $id;
        $eventFormCancelUrl = events_url('events_admin.php');
        require __DIR__ . '/partials/event_fields.php';
        $eventFormActionsPlacement = 'footer';
        require __DIR__ . '/partials/event_form_actions.php';
        ?>
    </form>
</div>

<?php require __DIR__ . '/partials/admin_event_edit_log.php'; ?>

<?php require __DIR__ . '/partials/admin_event_edit_stats.php'; ?>

<?php require __DIR__ . '/partials/html_editor_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
