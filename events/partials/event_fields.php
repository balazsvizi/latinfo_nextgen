<?php
declare(strict_types=1);
/** @var array $e aktuális mezőértékek */
/** @var array $organizers id => név */
/** @var array $categories id => név */
/** @var array<int, string> $venues id => név (events_load_venue_options) */
/** @var array<int, string> $tags id => név (events_load_tag_options) */
if (!isset($venues) || !is_array($venues)) {
    $venues = [];
}
if (!isset($categories) || !is_array($categories)) {
    $categories = [];
}
if (!isset($tags) || !is_array($tags)) {
    $tags = [];
}
$selOrg = isset($e['organizer_ids']) && is_array($e['organizer_ids']) ? array_values(array_unique(array_map('intval', $e['organizer_ids']))) : [];
$selCat = isset($e['category_ids']) && is_array($e['category_ids']) ? array_values(array_unique(array_map('intval', $e['category_ids']))) : [];
$selTag = isset($e['tag_ids']) && is_array($e['tag_ids']) ? array_values(array_unique(array_map('intval', $e['tag_ids']))) : [];
$orgPickerAll = [];
foreach ($organizers as $oid => $onev) {
    $orgPickerAll[] = ['id' => (int) $oid, 'name' => (string) $onev];
}
usort($orgPickerAll, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
$orgPickerJson = json_encode(
    ['all' => $orgPickerAll, 'selected' => $selOrg],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if ($orgPickerJson === false) {
    $orgPickerJson = '{"all":[],"selected":[]}';
}
$catPickerAll = [];
foreach ($categories as $cid => $cnev) {
    $catPickerAll[] = ['id' => (int) $cid, 'name' => (string) $cnev];
}
$catPickerJson = json_encode(
    ['all' => $catPickerAll, 'selected' => $selCat],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if ($catPickerJson === false) {
    $catPickerJson = '{"all":[],"selected":[]}';
}
$tagPickerAll = [];
foreach ($tags as $tid => $tnev) {
    $tagPickerAll[] = ['id' => (int) $tid, 'name' => (string) $tnev];
}
usort($tagPickerAll, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
$tagPickerJson = json_encode(
    ['all' => $tagPickerAll, 'selected' => $selTag],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if ($tagPickerJson === false) {
    $tagPickerJson = '{"all":[],"selected":[]}';
}
$eventpicFiles = events_eventpics_list_files();
$selectedVenueId = (string) ($e['venue_id'] ?? '');
$venuePickerAll = [];
foreach ($venues as $vid => $vname) {
    $venuePickerAll[] = ['id' => (int) $vid, 'name' => (string) $vname];
}
usort($venuePickerAll, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
$venuePickerJson = json_encode(
    ['all' => $venuePickerAll, 'selected' => $selectedVenueId !== '' ? (int) $selectedVenueId : 0],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if ($venuePickerJson === false) {
    $venuePickerJson = '{"all":[],"selected":0}';
}
$eventpicsPick = (string) ($e['event_featured_image_pick'] ?? '');
$coverPreview = events_featured_image_form_preview_meta((string) ($e['event_featured_image_url'] ?? ''), $eventpicsPick);
$coverPreviewCaption = $coverPreview['source'] === 'url'
    ? 'Előnézet a „Kiemelt kép URL” mező alapján (elsőbbség az eventpics felett).'
    : ($coverPreview['source'] === 'eventpic'
        ? 'Előnézet az eventpics borító alapján (nincs kitöltött URL).'
        : '');
?>
<div class="card" style="margin-bottom:1rem;">
    <h3 style="margin-top:0;">Szervezők és helyszín</h3>
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;">
        <fieldset class="form-group events-organizers-fieldset" id="events-org-picker-fieldset"<?= $organizers === [] ? ' data-organizers-empty="1"' : '' ?>>
            <legend>Szervezők</legend>
            <?php if ($organizers === []): ?>
                <p class="help">Nincs szervező rögzítve. Előbb <a href="<?= h(events_url('import_csv.php')) ?>?target_table=events_organizers">CSV importtal</a> vagy az adatbázisban vegyél fel szervezőket.</p>
            <?php else: ?>
                <p class="help">Felül szűrés, alatta választó lista, középen <strong>+</strong> / <strong>−</strong>, alul a kiválasztottak.</p>
                <input type="search" id="org-picker-filter" class="events-org-filter" placeholder="Szűrés név vagy ID szerint…" autocomplete="off" spellcheck="false">
                <div class="events-org-picker-grid">
                    <div class="events-org-picker-col">
                        <label class="events-org-picker-label" for="org-picker-pool">Kiválasztó lista</label>
                        <select id="org-picker-pool" class="events-org-list events-org-list--pool" size="5"></select>
                    </div>
                    <div class="events-org-picker-btns">
                        <button type="button" class="btn btn-secondary events-org-btn" id="org-picker-add" title="Hozzáadás">+</button>
                        <button type="button" class="btn btn-secondary events-org-btn" id="org-picker-remove" title="Eltávolítás">−</button>
                    </div>
                    <div class="events-org-picker-col">
                        <label class="events-org-picker-label" for="org-picker-selected">Kiválasztott</label>
                        <select id="org-picker-selected" class="events-org-list events-org-list--selected" size="3"></select>
                    </div>
                </div>
                <div id="org-picker-hiddens" class="org-picker-hiddens">
                    <?php foreach ($selOrg as $oid): ?>
                        <input type="hidden" name="organizer_ids[]" value="<?= (int) $oid ?>">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </fieldset>
        <fieldset class="form-group" id="events-venue-picker-fieldset">
            <legend>Helyszín (egy választható)</legend>
            <?php if ($venues === []): ?>
                <p class="help">Nincs helyszín felvéve. <a href="<?= h(events_url('venues.php')) ?>">Helyszínek</a> · <a href="<?= h(events_url('venue_letrehoz.php')) ?>">Új helyszín</a></p>
                <input type="hidden" name="venue_id" value="">
            <?php else: ?>
                <input type="search" id="venue-picker-filter" class="events-org-filter" placeholder="Helyszín keresése..." autocomplete="off" spellcheck="false">
                <div class="events-org-picker-grid">
                    <div class="events-org-picker-col">
                        <label class="events-org-picker-label" for="venue-picker-pool">Kiválasztó lista</label>
                        <select id="venue-picker-pool" class="events-org-list events-org-list--pool" size="5"></select>
                    </div>
                    <div class="events-org-picker-btns">
                        <button type="button" class="btn btn-secondary events-org-btn" id="venue-picker-add" title="Kiválasztás">+</button>
                        <button type="button" class="btn btn-secondary events-org-btn" id="venue-picker-remove" title="Törlés">−</button>
                    </div>
                    <div class="events-org-picker-col">
                        <label class="events-org-picker-label" for="venue-picker-selected">Kiválasztott</label>
                        <select id="venue-picker-selected" class="events-org-list events-org-list--selected" size="2"></select>
                    </div>
                </div>
                <div id="venue-picker-hidden">
                    <input type="hidden" name="venue_id" id="venue_id" value="<?= h($selectedVenueId) ?>">
                </div>
            <?php endif; ?>
        </fieldset>
    </div>
</div>
<div class="card" style="margin-bottom:1rem;">
    <h3 style="margin-top:0;">Alap adatok</h3>
    <div class="form-row events-form-row-name-slug" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(10rem,14rem);gap:1rem;">
        <div class="form-group">
            <label for="event_name">Esemény neve *</label>
            <input type="text" id="event_name" name="event_name" value="<?= h($e['event_name']) ?>" required maxlength="500">
        </div>
        <div class="form-group">
            <label for="event_slug">URL slug</label>
            <input type="text" id="event_slug" name="event_slug" value="<?= h($e['event_slug']) ?>" maxlength="255" pattern="[a-z0-9\-]*" title="Kisbetű, szám és kötőjel">
            <p class="help">Ha üres, a névből generáljuk.</p>
        </div>
    </div>
</div>
<div class="form-group">
    <label for="event_content">Leírás (HTML) *</label>
    <textarea id="event_content" name="event_content" class="js-html-editor-source" rows="14" required><?= h($e['event_content']) ?></textarea>
</div>
<div class="card" style="margin:1rem 0;">
    <h3 style="margin-top:0;">Adatok</h3>
    <div class="form-group">
        <label for="event_status">Státusz *</label>
        <select id="event_status_data" name="event_status" required>
            <?php foreach (events_allowed_post_statuses() as $val): ?>
                <option value="<?= h($val) ?>" <?= ($e['event_status'] === $val) ? 'selected' : '' ?>><?= h(events_post_status_label($val)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
<div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
    <div class="form-group">
        <label for="event_start_date">Kezdő dátum</label>
        <input type="date" id="event_start_date" name="event_start_date" value="<?= h($e['event_start_date']) ?>">
    </div>
    <div class="form-group">
        <label for="event_start_time">Kezdő idő</label>
        <input type="time" id="event_start_time" name="event_start_time" value="<?= h($e['event_start_time']) ?>">
    </div>
    <div class="form-group">
        <label for="event_end_date">Záró dátum</label>
        <input type="date" id="event_end_date" name="event_end_date" value="<?= h($e['event_end_date']) ?>">
    </div>
    <div class="form-group">
        <label for="event_end_time">Záró idő</label>
        <input type="time" id="event_end_time" name="event_end_time" value="<?= h($e['event_end_time']) ?>">
    </div>
</div>
<div class="form-group">
    <label><input type="checkbox" name="event_allday" value="1" <?= !empty($e['event_allday']) ? 'checked' : '' ?>> Egész napos</label>
</div>
<div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
    <div class="form-group">
        <label for="event_cost_from">Belépő (tól)</label>
        <input type="number" id="event_cost_from" name="event_cost_from" step="0.01" min="0" value="<?= h($e['event_cost_from']) ?>">
    </div>
    <div class="form-group">
        <label for="event_cost_to">Belépő (ig)</label>
        <input type="number" id="event_cost_to" name="event_cost_to" step="0.01" min="0" value="<?= h($e['event_cost_to']) ?>">
    </div>
</div>
</div>
<div class="card" style="margin-bottom:1rem;">
    <h3 style="margin-top:0;">További információ</h3>
    <div class="form-group events-url-open-row">
        <input type="url" id="event_url" name="event_url" value="<?= h($e['event_url']) ?>" maxlength="2000" placeholder="https://" aria-label="További információ URL">
        <?php if (!empty($e['event_url'])): ?>
            <a class="btn btn-secondary events-url-open-btn" href="<?= h($e['event_url']) ?>" target="_blank" rel="noopener noreferrer">Megnyitás új ablakban</a>
        <?php endif; ?>
    </div>
</div>
<div class="card" style="margin-bottom:1rem;">
    <h3 style="margin-top:0;">Kategóriák</h3>
    <?php if ($categories === []): ?>
        <p class="help">Nincs kategória felvéve. <a href="<?= h(events_url('categories.php')) ?>">Kategóriák</a> · vagy <a href="<?= h(events_url('import_csv.php')) ?>?target_table=events_categories">CSV import</a></p>
    <?php else: ?>
        <p class="help">Több kategória is hozzárendelhető az eseményhez. A választóban a magyar nevek látszanak.</p>
        <input type="search" id="cat-picker-filter" class="events-org-filter" placeholder="Kategória keresése..." autocomplete="off" spellcheck="false">
        <div class="events-org-picker-grid">
            <div class="events-org-picker-col">
                <label class="events-org-picker-label" for="cat-picker-pool">Kiválasztó lista</label>
                <select id="cat-picker-pool" class="events-org-list events-org-list--pool" size="6"></select>
            </div>
            <div class="events-org-picker-btns">
                <button type="button" class="btn btn-secondary events-org-btn" id="cat-picker-add" title="Hozzáadás">+</button>
                <button type="button" class="btn btn-secondary events-org-btn" id="cat-picker-remove" title="Eltávolítás">−</button>
            </div>
            <div class="events-org-picker-col">
                <label class="events-org-picker-label" for="cat-picker-selected">Kiválasztott</label>
                <select id="cat-picker-selected" class="events-org-list events-org-list--selected" size="6"></select>
            </div>
        </div>
        <div id="cat-picker-hiddens" class="org-picker-hiddens">
            <?php foreach ($selCat as $cid): ?>
                <input type="hidden" name="category_ids[]" value="<?= (int) $cid ?>">
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<div class="card" style="margin-bottom:1rem;">
    <h3 style="margin-top:0;">Címkék</h3>
    <?php if ($tags === []): ?>
        <p class="help">Még nincs címke felvéve. <a href="<?= h(events_url('tags.php')) ?>">Címkék szerkesztése</a></p>
    <?php else: ?>
        <p class="help">Több címke is választható. Felül szűrés, alatta választó lista, középen <strong>+</strong> / <strong>−</strong>, alul a kiválasztottak.</p>
        <input type="search" id="tag-picker-filter" class="events-org-filter" placeholder="Címke keresése…" autocomplete="off" spellcheck="false">
        <div class="events-org-picker-grid">
            <div class="events-org-picker-col">
                <label class="events-org-picker-label" for="tag-picker-pool">Kiválasztó lista</label>
                <select id="tag-picker-pool" class="events-org-list events-org-list--pool" size="6"></select>
            </div>
            <div class="events-org-picker-btns">
                <button type="button" class="btn btn-secondary events-org-btn" id="tag-picker-add" title="Hozzáadás">+</button>
                <button type="button" class="btn btn-secondary events-org-btn" id="tag-picker-remove" title="Eltávolítás">−</button>
            </div>
            <div class="events-org-picker-col">
                <label class="events-org-picker-label" for="tag-picker-selected">Kiválasztott</label>
                <select id="tag-picker-selected" class="events-org-list events-org-list--selected" size="6"></select>
            </div>
        </div>
        <div id="tag-picker-hiddens" class="org-picker-hiddens">
            <?php foreach ($selTag as $tgid): ?>
                <input type="hidden" name="tag_ids[]" value="<?= (int) $tgid ?>">
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<div class="card event-featured-card" style="margin-bottom:1rem;">
    <div class="event-featured-card__head">
        <h3 class="event-featured-card__title">Esemény képe</h3>
        <div class="event-featured-card__cover" id="eventpics-summary-preview"<?= $coverPreview['source'] === 'none' ? ' hidden' : '' ?>>
            <div class="event-featured-card__figure">
                <img id="eventpics-summary-img" src="<?= $coverPreview['src'] !== '' ? h($coverPreview['src']) : '' ?>" alt="Borító előnézet" decoding="async">
            </div>
            <div class="event-featured-card__caption">
                <span id="eventpics-summary-name" class="eventpics-form-summary__name"><?= $coverPreview['label'] !== '' ? h($coverPreview['label']) : '' ?></span>
                <span id="eventpics-summary-source" class="eventpics-form-summary__source help"><?= $coverPreviewCaption !== '' ? h($coverPreviewCaption) : '' ?></span>
            </div>
        </div>
    </div>
    <div class="form-group">
        <label for="event_featured_image_url">Kiemelt kép URL</label>
        <input type="text" id="event_featured_image_url" name="event_featured_image_url" value="<?= h($e['event_featured_image_url'] ?? '') ?>" maxlength="2000" placeholder="https://… vagy /útvonal/kép.jpg" spellcheck="false" autocomplete="off">
        <p class="help">Ha URL meg van adva, ez elsőbbséget élvez az eventpics képpel szemben.</p>
    </div>
    <div class="form-group eventpics-media-block">
        <label>Eventpics borítókép</label>
        <p class="help">Egy képet adhatsz meg. A választó ablakban tallózhatsz vagy feltölthetsz (egyszerre egy fájl). Ugyanaz a fájl több eseménynél is használható.</p>
        <input type="hidden" name="event_featured_image_pick" id="event_featured_image_pick" value="<?= h($eventpicsPick) ?>">
        <div class="eventpics-form-summary" id="eventpics-form-summary" data-base="<?= h(site_url('events/eventpics/')) ?>">
            <div class="eventpics-form-summary__inner eventpics-form-summary__inner--toolbar">
                <p class="eventpics-form-summary__empty help" id="eventpics-summary-empty"<?= $coverPreview['source'] !== 'none' ? ' hidden' : '' ?>>Nincs borítókép (adj meg URL-t vagy válassz eventpics képet).</p>
                <div class="eventpics-form-summary__actions">
                    <button type="button" class="btn btn-primary" id="eventpics-open-modal">Borítókép választása…</button>
                    <button type="button" class="btn btn-secondary" id="eventpics-clear-main">Törlés</button>
                </div>
            </div>
        </div>
    </div>
</div>

<dialog class="eventpics-modal" id="eventpics-modal" aria-labelledby="eventpics-modal-title">
    <div class="eventpics-modal__inner">
        <header class="eventpics-modal__header">
            <h2 class="eventpics-modal__title" id="eventpics-modal-title">Borítókép kiválasztása</h2>
            <button type="button" class="eventpics-modal__x" id="eventpics-modal-x" aria-label="Bezárás">×</button>
        </header>
        <div
            class="eventpics-browser"
            id="eventpics-browser"
            data-upload-url="<?= h(events_url('ajax_eventpic_upload.php')) ?>"
            data-csrf="<?= h(csrf_token('events_eventpics')) ?>"
        >
            <div class="eventpics-toolbar">
                <button type="button" class="btn btn-primary" id="eventpics-btn-upload">Kép feltöltése</button>
                <button type="button" class="btn btn-secondary" id="eventpics-btn-clear">Nincs kiválasztva</button>
                <input type="search" class="eventpics-filter" id="eventpics-filter" placeholder="Szűrés fájlnév szerint…" autocomplete="off" spellcheck="false">
                <span class="eventpics-msg" id="eventpics-msg" role="status"></span>
            </div>
            <div class="eventpics-dropzone" id="eventpics-dropzone" tabindex="-1">
                <div class="eventpics-grid" id="eventpics-grid">
                    <?php foreach ($eventpicFiles as $picFile): ?>
                        <button
                            type="button"
                            class="eventpics-item<?= $picFile === $eventpicsPick ? ' is-selected' : '' ?>"
                            data-filename="<?= h($picFile) ?>"
                            title="<?= h($picFile) ?>"
                        >
                            <span class="eventpics-item-check" aria-hidden="true"></span>
                            <img src="<?= h(site_url('events/eventpics/' . rawurlencode($picFile))) ?>" alt="" loading="lazy" width="150" height="150">
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <input type="file" id="eventpics-file-input" class="visually-hidden" accept="image/jpeg,image/png,image/webp,image/gif">
        </div>
        <footer class="eventpics-modal__footer">
            <button type="button" class="btn btn-secondary" id="eventpics-modal-cancel">Mégse</button>
            <button type="button" class="btn btn-primary" id="eventpics-modal-ok">Kiválasztás</button>
        </footer>
    </div>
</dialog>
<script type="application/json" id="events-org-picker-json"><?= $orgPickerJson ?></script>
<script type="application/json" id="events-venue-picker-json"><?= $venuePickerJson ?></script>
<script type="application/json" id="events-cat-picker-json"><?= $catPickerJson ?></script>
<script type="application/json" id="events-tag-picker-json"><?= $tagPickerJson ?></script>
<script>
(function () {
    var orgJsonEl = document.getElementById('events-org-picker-json');
    var pool = document.getElementById('org-picker-pool');
    var selected = document.getElementById('org-picker-selected');
    var filter = document.getElementById('org-picker-filter');
    var hiddens = document.getElementById('org-picker-hiddens');
    var btnAdd = document.getElementById('org-picker-add');
    var btnRemove = document.getElementById('org-picker-remove');
    if (orgJsonEl && pool && selected && filter && hiddens && btnAdd && btnRemove) {
        var raw = orgJsonEl.textContent || '{}';
        var data;
        try { data = JSON.parse(raw); } catch (e) { data = { all: [], selected: [] }; }
        var all = Array.isArray(data.all) ? data.all : [];
        var selectedIds = Array.isArray(data.selected) ? data.selected.map(function (x) { return parseInt(x, 10); }).filter(function (n) { return n > 0; }) : [];
        var nameById = {};
        all.forEach(function (row) { nameById[row.id] = row.name; });
        function syncHiddens() {
            hiddens.innerHTML = '';
            Array.from(selected.options).forEach(function (opt) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'organizer_ids[]';
                inp.value = opt.value;
                hiddens.appendChild(inp);
            });
        }
        function renderPool() {
            var q = (filter.value || '').trim().toLowerCase();
            pool.innerHTML = '';
            var taken = {};
            selectedIds.forEach(function (id) { taken[id] = true; });
            all.filter(function (row) { return !taken[row.id]; }).forEach(function (row) {
                if (q !== '' && (row.name + ' ' + row.id).toLowerCase().indexOf(q) === -1) return;
                var opt = document.createElement('option');
                opt.value = String(row.id);
                opt.textContent = row.name + ' (#' + row.id + ')';
                pool.appendChild(opt);
            });
        }
        function renderSelected() {
            selected.innerHTML = '';
            selectedIds.forEach(function (id) {
                var opt = document.createElement('option');
                opt.value = String(id);
                opt.textContent = (nameById[id] || '?') + ' (#' + id + ')';
                selected.appendChild(opt);
            });
            syncHiddens();
        }
        function addOrg() {
            var opt = pool.options[pool.selectedIndex];
            if (!opt) return;
            var id = parseInt(opt.value, 10);
            if (!id || selectedIds.indexOf(id) !== -1) return;
            selectedIds.push(id);
            renderSelected();
            renderPool();
        }
        function removeOrg() {
            var opt = selected.options[selected.selectedIndex];
            if (!opt) return;
            var id = parseInt(opt.value, 10);
            selectedIds = selectedIds.filter(function (x) { return x !== id; });
            renderSelected();
            renderPool();
        }
        filter.addEventListener('input', renderPool);
        btnAdd.addEventListener('click', addOrg);
        btnRemove.addEventListener('click', removeOrg);
        pool.addEventListener('dblclick', addOrg);
        selected.addEventListener('dblclick', removeOrg);
        renderSelected();
        renderPool();
    }

    var venueJsonEl = document.getElementById('events-venue-picker-json');
    var vPool = document.getElementById('venue-picker-pool');
    var vSel = document.getElementById('venue-picker-selected');
    var vFilter = document.getElementById('venue-picker-filter');
    var vAdd = document.getElementById('venue-picker-add');
    var vRemove = document.getElementById('venue-picker-remove');
    var vHidden = document.getElementById('venue_id');
    if (venueJsonEl && vPool && vSel && vFilter && vAdd && vRemove && vHidden) {
        var vData;
        try { vData = JSON.parse(venueJsonEl.textContent || '{}'); } catch (e) { vData = { all: [], selected: 0 }; }
        var vAll = Array.isArray(vData.all) ? vData.all : [];
        var selectedVenue = parseInt(vData.selected || 0, 10) || 0;
        function renderVenuePool() {
            var q = (vFilter.value || '').trim().toLowerCase();
            vPool.innerHTML = '';
            vAll.forEach(function (row) {
                if (selectedVenue === row.id) return;
                if (q !== '' && (row.name + ' ' + row.id).toLowerCase().indexOf(q) === -1) return;
                var opt = document.createElement('option');
                opt.value = String(row.id);
                opt.textContent = row.name + ' (#' + row.id + ')';
                vPool.appendChild(opt);
            });
        }
        function renderVenueSelected() {
            vSel.innerHTML = '';
            var row = vAll.find(function (x) { return x.id === selectedVenue; });
            if (row) {
                var opt = document.createElement('option');
                opt.value = String(row.id);
                opt.textContent = row.name + ' (#' + row.id + ')';
                vSel.appendChild(opt);
                vHidden.value = String(row.id);
            } else {
                vHidden.value = '';
            }
        }
        function addVenue() {
            var opt = vPool.options[vPool.selectedIndex];
            if (!opt) return;
            selectedVenue = parseInt(opt.value, 10) || 0;
            renderVenueSelected();
            renderVenuePool();
        }
        function removeVenue() {
            selectedVenue = 0;
            renderVenueSelected();
            renderVenuePool();
        }
        vFilter.addEventListener('input', renderVenuePool);
        vAdd.addEventListener('click', addVenue);
        vRemove.addEventListener('click', removeVenue);
        vPool.addEventListener('dblclick', addVenue);
        vSel.addEventListener('dblclick', removeVenue);
        renderVenueSelected();
        renderVenuePool();
    }

    var catJsonEl = document.getElementById('events-cat-picker-json');
    var cPool = document.getElementById('cat-picker-pool');
    var cSel = document.getElementById('cat-picker-selected');
    var cFilter = document.getElementById('cat-picker-filter');
    var cHiddens = document.getElementById('cat-picker-hiddens');
    var cAdd = document.getElementById('cat-picker-add');
    var cRemove = document.getElementById('cat-picker-remove');
    if (catJsonEl && cPool && cSel && cFilter && cHiddens && cAdd && cRemove) {
        var cData;
        try { cData = JSON.parse(catJsonEl.textContent || '{}'); } catch (e) { cData = { all: [], selected: [] }; }
        var cAll = Array.isArray(cData.all) ? cData.all : [];
        var cSelected = Array.isArray(cData.selected) ? cData.selected.map(function (x) { return parseInt(x, 10); }).filter(function (n) { return n > 0; }) : [];
        var cNameById = {};
        cAll.forEach(function (row) { cNameById[row.id] = row.name; });
        function syncCatHiddens() {
            cHiddens.innerHTML = '';
            Array.from(cSel.options).forEach(function (opt) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'category_ids[]';
                inp.value = opt.value;
                cHiddens.appendChild(inp);
            });
        }
        function renderCatPool() {
            var q = (cFilter.value || '').trim().toLowerCase();
            cPool.innerHTML = '';
            var taken = {};
            cSelected.forEach(function (id) { taken[id] = true; });
            cAll.filter(function (row) { return !taken[row.id]; }).forEach(function (row) {
                if (q !== '' && (row.name + ' ' + row.id).toLowerCase().indexOf(q) === -1) return;
                var opt = document.createElement('option');
                opt.value = String(row.id);
                opt.textContent = row.name + ' (#' + row.id + ')';
                cPool.appendChild(opt);
            });
        }
        function renderCatSelected() {
            cSel.innerHTML = '';
            cSelected.forEach(function (id) {
                var opt = document.createElement('option');
                opt.value = String(id);
                opt.textContent = (cNameById[id] || '?') + ' (#' + id + ')';
                cSel.appendChild(opt);
            });
            syncCatHiddens();
        }
        function addCat() {
            var opt = cPool.options[cPool.selectedIndex];
            if (!opt) return;
            var id = parseInt(opt.value, 10);
            if (!id || cSelected.indexOf(id) !== -1) return;
            cSelected.push(id);
            renderCatSelected();
            renderCatPool();
        }
        function removeCat() {
            var opt = cSel.options[cSel.selectedIndex];
            if (!opt) return;
            var id = parseInt(opt.value, 10);
            cSelected = cSelected.filter(function (x) { return x !== id; });
            renderCatSelected();
            renderCatPool();
        }
        cFilter.addEventListener('input', renderCatPool);
        cAdd.addEventListener('click', addCat);
        cRemove.addEventListener('click', removeCat);
        cPool.addEventListener('dblclick', addCat);
        cSel.addEventListener('dblclick', removeCat);
        renderCatSelected();
        renderCatPool();
    }

    var tagJsonEl = document.getElementById('events-tag-picker-json');
    var tPool = document.getElementById('tag-picker-pool');
    var tSel = document.getElementById('tag-picker-selected');
    var tFilter = document.getElementById('tag-picker-filter');
    var tHiddens = document.getElementById('tag-picker-hiddens');
    var tAdd = document.getElementById('tag-picker-add');
    var tRemove = document.getElementById('tag-picker-remove');
    if (tagJsonEl && tPool && tSel && tFilter && tHiddens && tAdd && tRemove) {
        var tData;
        try { tData = JSON.parse(tagJsonEl.textContent || '{}'); } catch (e2) { tData = { all: [], selected: [] }; }
        var tAll = Array.isArray(tData.all) ? tData.all : [];
        var tPick = Array.isArray(tData.selected) ? tData.selected.map(function (x) { return parseInt(x, 10); }).filter(function (n) { return n > 0; }) : [];
        var tNameById = {};
        tAll.forEach(function (row) { tNameById[row.id] = row.name; });
        function syncTagHiddens() {
            tHiddens.innerHTML = '';
            Array.from(tSel.options).forEach(function (opt) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'tag_ids[]';
                inp.value = opt.value;
                tHiddens.appendChild(inp);
            });
        }
        function renderTagPool() {
            var q = (tFilter.value || '').trim().toLowerCase();
            tPool.innerHTML = '';
            var taken = {};
            tPick.forEach(function (id) { taken[id] = true; });
            tAll.filter(function (row) { return !taken[row.id]; }).forEach(function (row) {
                if (q !== '' && (row.name + ' ' + row.id).toLowerCase().indexOf(q) === -1) return;
                var opt = document.createElement('option');
                opt.value = String(row.id);
                opt.textContent = row.name + ' (#' + row.id + ')';
                tPool.appendChild(opt);
            });
        }
        function renderTagSelected() {
            tSel.innerHTML = '';
            tPick.forEach(function (id) {
                var opt = document.createElement('option');
                opt.value = String(id);
                opt.textContent = (tNameById[id] || '?') + ' (#' + id + ')';
                tSel.appendChild(opt);
            });
            syncTagHiddens();
        }
        function addTag() {
            var opt = tPool.options[tPool.selectedIndex];
            if (!opt) return;
            var id = parseInt(opt.value, 10);
            if (!id || tPick.indexOf(id) !== -1) return;
            tPick.push(id);
            renderTagSelected();
            renderTagPool();
        }
        function removeTag() {
            var opt = tSel.options[tSel.selectedIndex];
            if (!opt) return;
            var id = parseInt(opt.value, 10);
            tPick = tPick.filter(function (x) { return x !== id; });
            renderTagSelected();
            renderTagPool();
        }
        tFilter.addEventListener('input', renderTagPool);
        tAdd.addEventListener('click', addTag);
        tRemove.addEventListener('click', removeTag);
        tPool.addEventListener('dblclick', addTag);
        tSel.addEventListener('dblclick', removeTag);
        renderTagSelected();
        renderTagPool();
    }
})();

(function () {
    var dialog = document.getElementById('eventpics-modal');
    var root = document.getElementById('eventpics-browser');
    var hidden = document.getElementById('event_featured_image_pick');
    var grid = document.getElementById('eventpics-grid');
    var btnUp = document.getElementById('eventpics-btn-upload');
    var btnClear = document.getElementById('eventpics-btn-clear');
    var fileInp = document.getElementById('eventpics-file-input');
    var drop = document.getElementById('eventpics-dropzone');
    var msg = document.getElementById('eventpics-msg');
    var filter = document.getElementById('eventpics-filter');
    var btnOpen = document.getElementById('eventpics-open-modal');
    var btnClearMain = document.getElementById('eventpics-clear-main');
    var btnOk = document.getElementById('eventpics-modal-ok');
    var btnCancel = document.getElementById('eventpics-modal-cancel');
    var btnX = document.getElementById('eventpics-modal-x');
    var summary = document.getElementById('eventpics-form-summary');
    var sumPreview = document.getElementById('eventpics-summary-preview');
    var sumImg = document.getElementById('eventpics-summary-img');
    var sumName = document.getElementById('eventpics-summary-name');
    var sumSource = document.getElementById('eventpics-summary-source');
    var sumEmpty = document.getElementById('eventpics-summary-empty');
    var urlInp = document.getElementById('event_featured_image_url');
    if (!dialog || !root || !hidden || !grid || !btnUp || !btnClear || !fileInp || !drop || !btnOpen || !btnOk || !btnCancel) return;

    var pendingPick = (hidden.value || '').trim();

    function setMsg(t, isErr) {
        if (!msg) return;
        msg.textContent = t || '';
        msg.classList.toggle('eventpics-msg--error', !!isErr);
    }

    function syncModalSelection() {
        var cur = (pendingPick || '').trim();
        grid.querySelectorAll('.eventpics-item').forEach(function (b) {
            b.classList.toggle('is-selected', cur !== '' && b.getAttribute('data-filename') === cur);
        });
    }

    function pickInModal(name) {
        var fn = (name || '').trim();
        if (fn && pendingPick === fn) {
            pendingPick = '';
        } else {
            pendingPick = fn;
        }
        syncModalSelection();
    }

    function summaryBase() {
        var b = (summary && summary.getAttribute('data-base')) || '';
        if (b && b.slice(-1) !== '/') {
            return b + '/';
        }
        return b;
    }

    function resolveCoverImgSrcFromUrlField(urlTrim) {
        var t = (urlTrim || '').trim();
        if (!t) return '';
        if (/^https?:\/\//i.test(t)) return t;
        if (t.indexOf('//') === 0) {
            return (window.location.protocol || 'https:') + t;
        }
        if (t.charAt(0) === '/') {
            try {
                return new URL(t, window.location.origin).href;
            } catch (e1) {
                return t;
            }
        }
        if (/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}(\/|$)/i.test(t)) {
            return 'https://' + t;
        }
        return t;
    }

    function truncateLabel(s, maxLen) {
        var t = (s || '').trim();
        if (!t) return '';
        if (t.length <= maxLen) return t;
        return t.slice(0, Math.max(0, maxLen - 1)) + '…';
    }

    function syncMainSummary() {
        var urlTrim = urlInp ? (urlInp.value || '').trim() : '';
        var fn = (hidden.value || '').trim();
        if (!sumPreview || !sumImg || !sumName || !sumEmpty) return;
        var src = '';
        var nameText = '';
        var capUrl = 'Előnézet a „Kiemelt kép URL” mező alapján (elsőbbség az eventpics felett).';
        var capPic = 'Előnézet az eventpics borító alapján (nincs kitöltött URL).';
        if (urlTrim) {
            src = resolveCoverImgSrcFromUrlField(urlTrim);
            nameText = truncateLabel(urlTrim, 52);
        } else if (fn) {
            src = summaryBase() + encodeURIComponent(fn);
            nameText = fn;
        }
        if (src === '') {
            sumPreview.hidden = true;
            sumEmpty.hidden = false;
            sumImg.removeAttribute('src');
            sumName.textContent = '';
            if (sumSource) sumSource.textContent = '';
            return;
        }
        sumPreview.hidden = false;
        sumEmpty.hidden = true;
        sumImg.src = src;
        sumName.textContent = nameText;
        if (sumSource) sumSource.textContent = urlTrim ? capUrl : capPic;
    }

    function hasThumb(filename) {
        return Array.prototype.some.call(grid.querySelectorAll('.eventpics-item'), function (b) {
            return (b.getAttribute('data-filename') || '') === filename;
        });
    }

    function addThumb(filename, imgUrl) {
        if (hasThumb(filename)) return;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'eventpics-item';
        btn.setAttribute('data-filename', filename);
        btn.title = filename;
        var chk = document.createElement('span');
        chk.className = 'eventpics-item-check';
        chk.setAttribute('aria-hidden', 'true');
        var img = document.createElement('img');
        img.src = imgUrl;
        img.alt = '';
        img.loading = 'lazy';
        img.width = 150;
        img.height = 150;
        btn.appendChild(chk);
        btn.appendChild(img);
        grid.insertBefore(btn, grid.firstChild);
        bindItem(btn);
    }

    function bindItem(btn) {
        btn.addEventListener('click', function () {
            var fn = btn.getAttribute('data-filename') || '';
            if (!fn) return;
            pickInModal(fn);
        });
    }
    grid.querySelectorAll('.eventpics-item').forEach(bindItem);

    btnClear.addEventListener('click', function () {
        pendingPick = '';
        syncModalSelection();
        setMsg('');
    });

    btnUp.addEventListener('click', function () { fileInp.click(); });

    if (filter) {
        filter.addEventListener('input', function () {
            var q = (filter.value || '').trim().toLowerCase();
            grid.querySelectorAll('.eventpics-item').forEach(function (b) {
                var fn = (b.getAttribute('data-filename') || '').toLowerCase();
                b.style.display = !q || fn.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }

    function uploadFiles(files) {
        var list = files ? Array.prototype.slice.call(files) : [];
        if (!list.length) return;
        if (list.length > 1) {
            setMsg('Egyszerre egy kép tölthető fel; az első kerül feldolgozásra.', false);
            list = [list[0]];
        }
        var url = root.getAttribute('data-upload-url') || '';
        var csrf = root.getAttribute('data-csrf') || '';
        if (!url || !csrf) {
            setMsg('Hiányzik a feltöltési beállítás.', true);
            return;
        }
        setMsg('Feltöltés…', false);
        var f = list[0];
        var fd = new FormData();
        fd.append('file', f, f.name);
        fd.append('eventpics_csrf', csrf);
        fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    setMsg((data && data.error) ? data.error : 'Feltöltés sikertelen.', true);
                    return;
                }
                addThumb(data.filename, data.thumb_url || data.url);
                pendingPick = data.filename;
                syncModalSelection();
                setMsg('Feltöltve. Nyomd meg a „Kiválasztás” gombot a mentéshez.', false);
                fileInp.value = '';
                if (filter) {
                    filter.value = '';
                    filter.dispatchEvent(new Event('input', { bubbles: true }));
                }
            })
            .catch(function () {
                setMsg('Hálózati hiba a feltöltéskor.', true);
            });
    }

    fileInp.addEventListener('change', function () {
        uploadFiles(fileInp.files);
    });

    ;['dragenter', 'dragover'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) {
            e.preventDefault();
            e.stopPropagation();
            drop.classList.add('eventpics-dropzone--active');
        });
    });
    ;['dragleave', 'drop'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) {
            e.preventDefault();
            e.stopPropagation();
            drop.classList.remove('eventpics-dropzone--active');
        });
    });
    drop.addEventListener('drop', function (e) {
        var dt = e.dataTransfer;
        if (!dt || !dt.files || !dt.files.length) return;
        uploadFiles([dt.files[0]]);
    });

    function openModal() {
        pendingPick = (hidden.value || '').trim();
        syncModalSelection();
        setMsg('');
        if (filter) {
            filter.value = '';
            filter.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }
        try {
            filter.focus();
        } catch (e2) {}
    }

    function closeModal() {
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
    }

    btnOpen.addEventListener('click', openModal);
    if (btnX) btnX.addEventListener('click', closeModal);
    btnCancel.addEventListener('click', closeModal);
    btnOk.addEventListener('click', function () {
        hidden.value = pendingPick;
        syncMainSummary();
        closeModal();
    });
    if (btnClearMain) {
        btnClearMain.addEventListener('click', function () {
            hidden.value = '';
            pendingPick = '';
            syncModalSelection();
            syncMainSummary();
        });
    }

    if (urlInp) {
        urlInp.addEventListener('input', syncMainSummary);
        urlInp.addEventListener('change', syncMainSummary);
    }

    syncMainSummary();
})();
</script>
