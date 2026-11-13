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
            $categoryIds = isset($event['category_ids']) && is_array($event['category_ids'])
                ? array_map('intval', $event['category_ids'])
                : [];
            $log .= "\n" . events_build_log_details($db, $event, [], $categoryIds);
            rendszer_log('esemény', $eventId, 'Partner módosította', $log);
            flash('success', 'Az esemény mentve.');
            redirect(partner_url('event.php?id=') . $eventId);
        }
        $hiba = $result['error'];
    }
}

if (!isset($event['category_ids'])) {
    $event['category_ids'] = events_load_event_category_ids($db, $eventId);
    $event['tag_ids'] = events_load_event_tag_ids($db, $eventId);
    $event['main_style_ids'] = events_load_event_main_style_ids($db, $eventId);
    $event['supplementary_style_ids'] = events_load_event_supplementary_style_ids($db, $eventId);
}

$e = events_row_for_form($event);
$toTokens = static function (array $map): array {
    $all = [];
    foreach ($map as $id => $name) {
        $all[] = ['id' => (int) $id, 'name' => (string) $name];
    }
    usort($all, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

    return $all;
};
$categoryOptions = $toTokens(events_load_category_options($db));
$tagOptions = $toTokens(events_load_tag_options($db));
$styleOptions = $toTokens(events_load_style_options($db));
$partnerStatuses = events_organizer_edit_statuses();
$status = (string) ($e['event_status'] ?? '');
if (!in_array($status, $partnerStatuses, true) && $status !== '' && $status !== 'trash') {
    $partnerStatuses[] = $status;
}
$eventpicsPick = (string) ($e['event_featured_image_pick'] ?? '');
$coverPreview = events_featured_image_form_preview_meta((string) ($e['event_featured_image_url'] ?? ''), $eventpicsPick);
$eventpicFiles = events_eventpics_list_files();
$openUrl = trim((string) ($e['event_url'] ?? ''));
$openHref = '';
if (preg_match('#^https?://#i', $openUrl) === 1 || (str_starts_with($openUrl, '/') && !str_starts_with($openUrl, '//'))) {
    $openHref = $openUrl;
}

$venueStmt = $db->prepare('SELECT `id`, `name`, `city` FROM `events_venues` ORDER BY `name` ASC, `id` ASC');
$venueStmt->execute();
$venues = $venueStmt->fetchAll(PDO::FETCH_ASSOC);
$selectedVenue = (int) ($event['venue_id'] ?? 0);
$organizers = partner_portal_event_organizer_names($db, $eventId);

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

<form method="post" enctype="multipart/form-data" class="card partner-event-edit" id="partner-event-edit-form">
    <?= csrf_input('partner_event_szerkeszt') ?>
    <h1 class="partner-page-title">Esemény szerkesztése</h1>
    <p class="help">A webcímet (slug) és a szervezőket az admin kezeli.<?php if ($organizers !== []): ?> Szervezők: <?php foreach ($organizers as $i => $org): ?><?php if ($i > 0): ?>, <?php endif; ?><?= h($org['name']) ?><?php endforeach; ?><?php endif; ?></p>

    <div class="partner-event-edit__layout">
    <div class="partner-event-edit__main">
    <div class="form-group">
        <label for="event_name">Esemény neve *</label>
        <input type="text" id="event_name" name="event_name" value="<?= h((string) ($e['event_name'] ?? '')) ?>" required maxlength="500">
    </div>

    <div class="partner-event-edit__datetime-head">
        <h2 class="partner-event-edit__section">Időpont</h2>
        <label class="events-toggle partner-event-edit__toggle" for="event_allday">
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
        <div class="partner-event-edit__url-row">
            <input type="url" id="event_url" name="event_url" value="<?= h((string) ($e['event_url'] ?? '')) ?>" maxlength="2000" placeholder="https://">
            <a
                class="btn btn-secondary partner-event-edit__url-open<?= $openHref === '' ? ' is-disabled' : '' ?>"
                id="event-url-open"
                href="<?= h($openHref !== '' ? $openHref : '#') ?>"
                target="_blank"
                rel="noopener noreferrer"
                <?= $openHref === '' ? 'aria-disabled="true"' : '' ?>
            >Megnyitás új ablakban</a>
        </div>
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
    </div>

    <aside class="partner-event-edit__side">
    <div class="form-group">
        <label for="event_status">Státusz</label>
        <select id="event_status" name="event_status" required>
            <?php foreach ($partnerStatuses as $statusValue): ?>
                <option value="<?= h($statusValue) ?>"<?= $status === $statusValue ? ' selected' : '' ?>><?= h(events_post_status_label($statusValue)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="partner-event-edit__datetime-head">
        <h2 class="partner-event-edit__section">Változás / elmaradás</h2>
        <label class="events-toggle partner-event-edit__toggle" for="event_change_active">
            <input type="checkbox" name="event_change_active" value="1" id="event_change_active" class="events-toggle__input" <?= !empty($e['event_change_active']) ? 'checked' : '' ?>>
            <span class="events-toggle__ui" aria-hidden="true"></span>
            <span class="events-toggle__label">Jelzés</span>
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

    <h2 class="partner-event-edit__section">Kategóriák</h2>
    <?php
    $wpTokenId = 'partner-event-categories';
    $wpTokenLabel = '';
    $wpTokenFieldName = 'category_ids[]';
    $wpTokenPlaceholder = 'Kategória hozzáadása…';
    $wpTokenHelp = '';
    $wpTokenManageUrl = null;
    $wpTokenAll = $categoryOptions;
    $wpTokenSelected = $e['category_ids'];
    $wpTokenAllowCreate = false;
    $wpTokenEntityType = '';
    $wpTokenSingle = false;
    $wpTokenShowPopular = true;
    $wpTokenChipLinkPattern = null;
    require dirname(__DIR__) . '/events/partials/wp_token_field.php';
    ?>

    <h2 class="partner-event-edit__section">Címkék</h2>
    <?php
    $wpTokenId = 'partner-event-tags';
    $wpTokenLabel = '';
    $wpTokenFieldName = 'tag_ids[]';
    $wpTokenPlaceholder = 'Címke hozzáadása…';
    $wpTokenHelp = '';
    $wpTokenManageUrl = null;
    $wpTokenAll = $tagOptions;
    $wpTokenSelected = $e['tag_ids'];
    $wpTokenAllowCreate = false;
    $wpTokenSingle = false;
    $wpTokenShowPopular = false;
    $wpTokenChipLinkPattern = null;
    require dirname(__DIR__) . '/events/partials/wp_token_field.php';
    ?>

    <h2 class="partner-event-edit__section">Fő stílusok</h2>
    <?php
    $wpTokenId = 'partner-event-main-styles';
    $wpTokenLabel = '';
    $wpTokenFieldName = 'main_style_ids[]';
    $wpTokenPlaceholder = 'Stílus hozzáadása…';
    $wpTokenHelp = '';
    $wpTokenManageUrl = null;
    $wpTokenAll = $styleOptions;
    $wpTokenSelected = $e['main_style_ids'];
    $wpTokenAllowCreate = false;
    $wpTokenSingle = false;
    $wpTokenShowPopular = true;
    $wpTokenChipLinkPattern = null;
    require dirname(__DIR__) . '/events/partials/wp_token_field.php';
    ?>

    <h2 class="partner-event-edit__section">Kiegészítő stílusok</h2>
    <?php
    $wpTokenId = 'partner-event-supplementary-styles';
    $wpTokenLabel = '';
    $wpTokenFieldName = 'supplementary_style_ids[]';
    $wpTokenPlaceholder = 'Stílus hozzáadása…';
    $wpTokenHelp = '';
    $wpTokenManageUrl = null;
    $wpTokenAll = $styleOptions;
    $wpTokenSelected = $e['supplementary_style_ids'];
    $wpTokenAllowCreate = false;
    $wpTokenSingle = false;
    $wpTokenShowPopular = true;
    $wpTokenChipLinkPattern = null;
    require dirname(__DIR__) . '/events/partials/wp_token_field.php';
    ?>

    <h2 class="partner-event-edit__section">Esemény képe</h2>
    <div class="form-group">
        <label for="event_featured_image_url">Kiemelt kép URL</label>
        <input type="text" id="event_featured_image_url" name="event_featured_image_url" value="<?= h((string) ($e['event_featured_image_url'] ?? '')) ?>" maxlength="2000" placeholder="https://… vagy /útvonal/kép.jpg" spellcheck="false" autocomplete="off">
        <p class="help">Ha URL meg van adva, ez elsőbbséget élvez a galériaképpel szemben.</p>
    </div>
    <div class="partner-event-edit__cover">
        <?php if ($coverPreview['src'] !== ''): ?>
            <img src="<?= h($coverPreview['src']) ?>" alt="Borító előnézet" id="partner-cover-preview" width="320" height="180">
        <?php else: ?>
            <img src="" alt="" id="partner-cover-preview" hidden width="320" height="180">
            <p class="help" id="partner-cover-empty">Nincs borítókép</p>
        <?php endif; ?>
    </div>
    <input type="hidden" name="event_featured_image_pick" id="event_featured_image_pick" value="<?= h($eventpicsPick) ?>">
    <p class="toolbar">
        <button type="button" class="btn btn-secondary btn-sm" id="partner-cover-gallery">Galéria</button>
        <button type="button" class="btn btn-secondary btn-sm" id="partner-cover-clear">Borító törlése</button>
    </p>
    <div class="form-group">
        <label for="event_featured_image_upload">Új kép feltöltése</label>
        <input type="file" id="event_featured_image_upload" name="event_featured_image_upload" accept="image/jpeg,image/png,image/webp,image/gif">
        <p class="help">A feltöltés mentéskor kerül a galériába, és borító lesz, ha nincs kitöltött URL.</p>
    </div>
    </aside>
    </div>

    <p class="toolbar">
        <button type="submit" class="btn btn-primary">Mentés</button>
        <a href="<?= h(partner_url('event.php?id=') . $eventId) ?>" class="btn btn-secondary">Mégse</a>
    </p>
</form>

<dialog class="partner-cover-dialog" id="partner-cover-dialog">
    <div class="partner-cover-dialog__inner">
        <h2 class="partner-event-edit__section">Borítókép a galériából</h2>
        <input type="search" id="partner-cover-filter" placeholder="Szűrés fájlnév szerint…" autocomplete="off">
        <div class="partner-cover-dialog__grid">
            <?php foreach ($eventpicFiles as $picFile): ?>
                <button
                    type="button"
                    class="partner-cover-dialog__item<?= $picFile === $eventpicsPick ? ' is-selected' : '' ?>"
                    data-file="<?= h($picFile) ?>"
                    data-src="<?= h(events_url('eventpics/' . rawurlencode($picFile))) ?>"
                >
                    <img src="<?= h(events_url('eventpics/' . rawurlencode($picFile))) ?>" alt="" loading="lazy" width="120" height="120">
                </button>
            <?php endforeach; ?>
        </div>
        <p class="toolbar">
            <button type="button" class="btn btn-secondary" id="partner-cover-dialog-close">Bezárás</button>
        </p>
    </div>
</dialog>

<?php require dirname(__DIR__) . '/events/partials/wp_token_input_script.php'; ?>
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

    var urlInput = document.getElementById('event_url');
    var urlOpen = document.getElementById('event-url-open');
    function openableUrl(value) {
        if (/^https?:\/\//i.test(value)) return value;
        if (value.charAt(0) === '/' && value.indexOf('//') !== 0) return value;
        return '';
    }
    function syncUrlOpen() {
        if (!urlInput || !urlOpen) return;
        var href = openableUrl(urlInput.value.trim());
        if (href) {
            urlOpen.href = href;
            urlOpen.classList.remove('is-disabled');
            urlOpen.removeAttribute('aria-disabled');
        } else {
            urlOpen.href = '#';
            urlOpen.classList.add('is-disabled');
            urlOpen.setAttribute('aria-disabled', 'true');
        }
    }
    if (urlInput && urlOpen) {
        urlInput.addEventListener('input', syncUrlOpen);
        urlOpen.addEventListener('click', function (event) {
            if (urlOpen.getAttribute('aria-disabled') === 'true') event.preventDefault();
        });
    }

    var dialog = document.getElementById('partner-cover-dialog');
    var pick = document.getElementById('event_featured_image_pick');
    var preview = document.getElementById('partner-cover-preview');
    var empty = document.getElementById('partner-cover-empty');
    var urlImage = document.getElementById('event_featured_image_url');
    var openGallery = document.getElementById('partner-cover-gallery');
    var clearCover = document.getElementById('partner-cover-clear');
    var closeGallery = document.getElementById('partner-cover-dialog-close');
    var filter = document.getElementById('partner-cover-filter');
    function showPreview(src) {
        if (!preview) return;
        if (src) {
            preview.src = src;
            preview.hidden = false;
            if (empty) empty.hidden = true;
        } else {
            preview.removeAttribute('src');
            preview.hidden = true;
            if (empty) empty.hidden = false;
        }
    }
    if (openGallery && dialog && typeof dialog.showModal === 'function') {
        openGallery.addEventListener('click', function () { dialog.showModal(); });
    }
    if (closeGallery && dialog) {
        closeGallery.addEventListener('click', function () { dialog.close(); });
    }
    if (dialog) {
        dialog.addEventListener('click', function (event) {
            var item = event.target.closest('[data-file]');
            if (!item) return;
            if (pick) pick.value = item.getAttribute('data-file') || '';
            if (urlImage) urlImage.value = '';
            showPreview(item.getAttribute('data-src') || '');
            dialog.querySelectorAll('.partner-cover-dialog__item').forEach(function (el) {
                el.classList.toggle('is-selected', el === item);
            });
            dialog.close();
        });
    }
    if (clearCover) {
        clearCover.addEventListener('click', function () {
            if (pick) pick.value = '';
            if (urlImage) urlImage.value = '';
            var file = document.getElementById('event_featured_image_upload');
            if (file) file.value = '';
            showPreview('');
        });
    }
    if (filter && dialog) {
        filter.addEventListener('input', function () {
            var q = filter.value.trim().toLowerCase();
            dialog.querySelectorAll('.partner-cover-dialog__item').forEach(function (el) {
                var name = (el.getAttribute('data-file') || '').toLowerCase();
                el.hidden = q !== '' && name.indexOf(q) === -1;
            });
        });
    }
})();
</script>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
