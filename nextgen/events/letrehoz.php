<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_request.php';
require_once __DIR__ . '/lib/event_log.php';
requireLogin();

$db = getDb();
$organizers = events_load_organizer_options($db);
$categories = events_load_category_options($db);
$venues = events_load_venue_options($db);
$tags = events_load_tag_options($db);
$styles = events_load_style_options($db);

$defaults = [
    'event_name' => '',
    'event_slug' => '',
    'event_content' => '',
    'event_status' => events_public_post_status(),
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
    'main_style_ids' => [],
    'supplementary_style_ids' => [],
    'venue_id' => null,
];

$hiba = '';
$copyNotice = '';
$eventFormIsCopy = false;
$eventCopySourceFeaturedImage = '';
$e = events_row_for_form($defaults);

$copyFromId = (int) ($_GET['copy_from'] ?? 0);
if ($copyFromId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $copied = events_load_event_copy_template($db, $copyFromId);
    if ($copied !== null) {
        $e = events_row_for_form($copied);
        $eventFormIsCopy = true;
        $eventCopySourceFeaturedImage = trim((string) ($e['event_featured_image_url'] ?? ''));
        $copyNotice = 'Esemény másolva piszkozatként. A dátumok és a további információ URL nem kerültek át — add meg őket, majd mentsd.';
    } else {
        flash('error', 'A másolandó esemény nem található.');
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['is_copy'] ?? '') === '1') {
    $eventFormIsCopy = true;
    $eventCopySourceFeaturedImage = trim((string) ($_POST['copy_source_featured_image'] ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('events_letrehoz')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } else {
    [$row, $err, $organizerIds, $categoryIds, $tagIds, $mainStyleIds, $supplementaryStyleIds] = events_row_from_request($db, $defaults, null);
    if ($eventFormIsCopy) {
        $saveAction = (string) ($_POST['save_action'] ?? 'draft');
        $row['event_status'] = $saveAction === 'publish'
            ? events_public_post_status()
            : events_default_post_status();
    }
    $copyWarnings = $eventFormIsCopy ? events_copy_save_warnings($row, $_POST) : [];
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
            $stmt = $db->prepare('
                INSERT INTO `events_calendar_events` (
                    event_name, event_slug, event_content, event_status,
                    event_start, event_end, event_allday,
                    event_change_active, event_change_type, event_change_note,
                    event_cost_from, event_cost_to, event_url, event_featured_image_url, event_latinfohu_partner,
                    venue_id
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ');
            $stmt->execute([
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
            ]);
            $newId = (int) $db->lastInsertId();
            events_save_event_organizers($db, $newId, $organizerIds);
            events_save_event_categories($db, $newId, $categoryIds);
            events_save_event_tags($db, $newId, $tagIds);
            events_save_event_main_styles($db, $newId, $mainStyleIds);
            events_save_event_supplementary_styles($db, $newId, $supplementaryStyleIds);
            $db->commit();
            rendszer_log(
                'esemény',
                $newId,
                'Létrehozva',
                events_build_log_details($db, $row, $organizerIds, $categoryIds)
            );
            if ($copyWarnings !== []) {
                flash('warning', implode(' ', $copyWarnings));
            }
            flash('success', 'Esemény létrehozva.');
            redirect(events_url('szerkeszt.php?id=' . $newId));
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
            $e['main_style_ids'] = $mainStyleIds;
            $e['supplementary_style_ids'] = $supplementaryStyleIds;
        }
    }
    }
}

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Új esemény';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<div class="events-edit-page">
    <header class="events-edit-header">
        <div class="events-edit-header__main">
            <h1 class="events-edit-title">Új esemény</h1>
            <p class="events-edit-subtitle help">Töltsd ki az alapadatokat, majd mentsd az eseményt.</p>
        </div>
        <div class="events-edit-header__actions">
            <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary btn-sm">Vissza a listához</a>
        </div>
    </header>
    <?php if ($copyNotice !== ''): ?><p class="alert alert-success"><?= h($copyNotice) ?></p><?php endif; ?>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="events-edit-form" id="events-edit-form"
          data-entity-create-url="<?= h(events_url('ajax_entity_quick_create.php')) ?>"
          data-entity-create-csrf="<?= h(csrf_token('events_entity_create')) ?>">
        <?= csrf_input('events_letrehoz') ?>
        <?php if ($eventFormIsCopy): ?>
            <input type="hidden" name="is_copy" value="1">
            <input type="hidden" name="copy_source_featured_image" id="copy_source_featured_image" value="<?= h($eventCopySourceFeaturedImage) ?>">
        <?php endif; ?>
        <?php
        $eventFormAutoSlug = true;
        $eventFormCancelUrl = events_url('events_admin.php');
        require __DIR__ . '/partials/event_fields.php';
        $eventFormActionsPlacement = 'footer';
        require __DIR__ . '/partials/event_form_actions.php';
        ?>
    </form>
</div>
<?php require __DIR__ . '/partials/html_editor_script.php'; ?>
<?php if ($eventFormIsCopy): require __DIR__ . '/partials/event_copy_save_script.php'; endif; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
