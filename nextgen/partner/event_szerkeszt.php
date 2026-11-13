<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/events/lib/event_request.php';
require_once dirname(__DIR__) . '/events/lib/event_log.php';

partner_require_portal_permission('stat');

$db = getDb();
$partnerId = partner_current_id();
$eventId = (int) ($_GET['id'] ?? 0);
partner_require_event_access($db, $eventId);

$event = partner_portal_event_by_id($db, $eventId);
if ($event === null || !partner_portal_event_organizer_can_edit($event)) {
    flash('error', 'Ezt az eseményt nem szerkesztheted.');
    redirect(partner_url('events.php'));
}

$hiba = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('partner_event_szerkeszt')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } else {
        $result = events_organizer_edit_apply($db, $event, $_POST);
        $event = $result['row'];
        if ($result['ok']) {
            $partner = partner_current($db);
            $partnerName = trim((string) ($partner['név'] ?? partner_session_display_name()));
            $log = "Partnerportál\nPartner: " . ($partnerName !== '' ? $partnerName : 'Partner') . ' (#' . $partnerId . ')';
            if (partner_is_admin_impersonating()) {
                $log .= "\nAdmin nézet";
            }
            $log .= "\n" . events_build_log_details($db, $event, [], []);
            rendszer_log('esemény', $eventId, 'Partner módosította', $log);
            flash('success', 'Az esemény mentve.');
            redirect(partner_url('event.php?id=') . $eventId);
        }
        $hiba = $result['error'];
    }
}

$e = events_row_for_form($event);
$coverField = trim((string) ($event['organizer_cover_url'] ?? ''));
if ($coverField === '') {
    $existingCover = trim((string) ($event['event_featured_image_url'] ?? ''));
    if (preg_match('#^https?://#i', $existingCover) === 1) {
        $coverField = $existingCover;
    }
}
$hasLocalCover = $coverField === '' && trim((string) ($event['event_featured_image_url'] ?? '')) !== '';

$venueStmt = $db->prepare('SELECT `id`, `name`, `city` FROM `events_venues` ORDER BY `name` ASC, `id` ASC');
$venueStmt->execute();
$venues = $venueStmt->fetchAll(PDO::FETCH_ASSOC);
$selectedVenue = (int) ($event['venue_id'] ?? 0);
$organizers = partner_portal_event_organizer_names($db, $eventId);
$status = (string) ($event['event_status'] ?? '');

$pageTitle = 'Szerkesztés: ' . (string) ($e['event_name'] ?? '');
$activeNav = 'events';
require_once __DIR__ . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<p class="toolbar partner-toolbar">
    <a href="<?= h(partner_url('event.php?id=') . $eventId) ?>" class="btn btn-secondary">← Esemény</a>
    <a href="<?= h(partner_url('events.php')) ?>" class="btn btn-secondary">Események</a>
</p>

<form method="post" class="card partner-event-edit" id="partner-event-edit-form">
    <?= csrf_input('partner_event_szerkeszt') ?>
    <h1 class="partner-page-title">Esemény szerkesztése</h1>
    <p class="help">
        Státusz: <span class="event-status-badge <?= h(events_post_status_badge_class($status)) ?>"><?= h(events_post_status_label($status)) ?></span>
        · A státuszt, a webcímet és a szervezőket az admin kezeli.
    </p>

    <div class="form-group">
        <label for="event_name">Esemény neve *</label>
        <input type="text" id="event_name" name="event_name" value="<?= h((string) ($e['event_name'] ?? '')) ?>" required maxlength="500">
    </div>

    <div class="form-group">
        <label class="events-toggle" for="event_allday">
            <input type="checkbox" name="event_allday" value="1" id="event_allday" class="events-toggle__input" <?= !empty($e['event_allday']) ? 'checked' : '' ?>>
            <span class="events-toggle__ui" aria-hidden="true"></span>
            <span class="events-toggle__label">Időpont nélkül</span>
        </label>
    </div>
    <div class="partner-event-edit__dates">
        <div class="form-group">
            <label for="event_start_date">Kezdő dátum *</label>
            <input type="date" id="event_start_date" name="event_start_date" value="<?= h((string) ($e['event_start_date'] ?? '')) ?>" required>
        </div>
        <div class="form-group" id="partner-event-start-time">
            <label for="event_start_time">Kezdő idő</label>
            <input type="time" id="event_start_time" name="event_start_time" value="<?= h((string) ($e['event_start_time'] ?? '')) ?>">
        </div>
        <div class="form-group">
            <label for="event_end_date">Záró dátum</label>
            <input type="date" id="event_end_date" name="event_end_date" value="<?= h((string) ($e['event_end_date'] ?? '')) ?>">
        </div>
        <div class="form-group" id="partner-event-end-time">
            <label for="event_end_time">Záró idő</label>
            <input type="time" id="event_end_time" name="event_end_time" value="<?= h((string) ($e['event_end_time'] ?? '')) ?>">
        </div>
    </div>

    <div class="form-group">
        <label for="venue_id">Helyszín</label>
        <select id="venue_id" name="venue_id">
            <option value="">— nincs —</option>
            <?php foreach ($venues as $venue): ?>
                <?php
                $venueId = (int) ($venue['id'] ?? 0);
                $venueLabel = trim((string) ($venue['name'] ?? ''));
                $venueCity = trim((string) ($venue['city'] ?? ''));
                if ($venueCity !== '') {
                    $venueLabel .= ' (' . $venueCity . ')';
                }
                ?>
                <option value="<?= $venueId ?>"<?= $venueId === $selectedVenue ? ' selected' : '' ?>><?= h($venueLabel) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="event_url">További információ URL<?= $status === events_public_post_status() ? ' *' : '' ?></label>
        <input type="url" id="event_url" name="event_url" value="<?= h((string) ($e['event_url'] ?? '')) ?>" maxlength="2000" placeholder="https://">
    </div>

    <div class="partner-event-edit__costs">
        <div class="form-group">
            <label for="event_cost_from">Belépő tól (Ft)</label>
            <input type="number" id="event_cost_from" name="event_cost_from" min="0" step="any" value="<?= h((string) ($e['event_cost_from'] ?? '')) ?>">
        </div>
        <div class="form-group">
            <label for="event_cost_to">Belépő ig (Ft)</label>
            <input type="number" id="event_cost_to" name="event_cost_to" min="0" step="any" value="<?= h((string) ($e['event_cost_to'] ?? '')) ?>">
        </div>
    </div>

    <div class="form-group">
        <label for="event_content">Leírás<?= events_is_preliminary_post_status($status) ? '' : ' *' ?></label>
        <textarea id="event_content" name="event_content" class="js-html-editor-source" rows="12"><?= h((string) ($e['event_content'] ?? '')) ?></textarea>
    </div>

    <div class="form-group">
        <label for="organizer_cover_url">Borítókép URL</label>
        <input type="url" id="organizer_cover_url" name="organizer_cover_url" value="<?= h($coverField) ?>" maxlength="2000" placeholder="https://">
        <p class="help">
            <?php if ($hasLocalCover): ?>
                Most galériakép a borító. Ha megadsz egy URL-t, az lép a helyére; üresen hagyva a jelenlegi kép marad.
            <?php else: ?>
                Üresen hagyva a jelenlegi borító marad.
            <?php endif; ?>
        </p>
    </div>

    <div class="form-group">
        <label class="events-toggle" for="event_change_active">
            <input type="checkbox" name="event_change_active" value="1" id="event_change_active" class="events-toggle__input" <?= !empty($e['event_change_active']) ? 'checked' : '' ?>>
            <span class="events-toggle__ui" aria-hidden="true"></span>
            <span class="events-toggle__label">Változás / elmaradás jelzése</span>
        </label>
    </div>
    <div id="partner-event-change-fields"<?= empty($e['event_change_active']) ? ' hidden' : '' ?>>
        <div class="form-group">
            <label for="event_change_type">Típus *</label>
            <select id="event_change_type" name="event_change_type">
                <option value="">— válassz —</option>
                <?php foreach (events_event_change_types() as $typeKey => $typeLabel): ?>
                    <option value="<?= h($typeKey) ?>"<?= (($e['event_change_type'] ?? '') === $typeKey) ? ' selected' : '' ?>><?= h($typeLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="event_change_note">Publikus megjegyzés</label>
            <textarea id="event_change_note" name="event_change_note" rows="3" maxlength="2000"><?= h((string) ($e['event_change_note'] ?? '')) ?></textarea>
        </div>
    </div>

    <?php if ($organizers !== []): ?>
        <p class="help">Szervezők: <?php foreach ($organizers as $i => $org): ?><?php if ($i > 0): ?>, <?php endif; ?><?= h($org['name']) ?><?php endforeach; ?></p>
    <?php endif; ?>

    <p class="toolbar">
        <button type="submit" class="btn btn-primary">Mentés</button>
        <a href="<?= h(partner_url('event.php?id=') . $eventId) ?>" class="btn btn-secondary">Mégse</a>
    </p>
</form>

<?php require dirname(__DIR__) . '/events/partials/html_editor_script.php'; ?>
<script>
(function () {
    var allDay = document.getElementById('event_allday');
    var startTime = document.getElementById('partner-event-start-time');
    var endTime = document.getElementById('partner-event-end-time');
    function syncTimes() {
        var hide = allDay && allDay.checked;
        if (startTime) startTime.hidden = hide;
        if (endTime) endTime.hidden = hide;
    }
    if (allDay) {
        allDay.addEventListener('change', syncTimes);
        syncTimes();
    }
    var changeToggle = document.getElementById('event_change_active');
    var changeFields = document.getElementById('partner-event-change-fields');
    if (changeToggle && changeFields) {
        changeToggle.addEventListener('change', function () {
            changeFields.hidden = !changeToggle.checked;
        });
    }
})();
</script>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
