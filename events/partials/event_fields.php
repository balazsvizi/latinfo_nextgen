<?php
declare(strict_types=1);
/** @var array $e aktuális mezőértékek */
/** @var array $organizers id => név */
/** @var array<int, string> $venues id => név (events_load_venue_options) */
if (!isset($venues) || !is_array($venues)) {
    $venues = [];
}
$selOrg = isset($e['organizer_ids']) && is_array($e['organizer_ids']) ? array_values(array_unique(array_map('intval', $e['organizer_ids']))) : [];
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
?>
<div class="card" style="margin-bottom:1rem;">
    <h3 style="margin-top:0;">Szervezők és helyszín</h3>
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;">
        <fieldset class="form-group events-organizers-fieldset" id="events-org-picker-fieldset"<?= $organizers === [] ? ' data-organizers-empty="1"' : '' ?>>
            <legend>Szervezők</legend>
            <?php if ($organizers === []): ?>
                <p class="help">Nincs szervező rögzítve. Előbb <a href="<?= h(events_url('import_csv.php')) ?>?target_table=events_organizers">CSV importtal</a> vagy az adatbázisban vegyél fel szervezőket.</p>
            <?php else: ?>
                <p class="help">Szűrj, majd <strong>+</strong> a felvételhez. Jobb oldalon a kiválasztott lista.</p>
                <input type="search" id="org-picker-filter" class="events-org-filter" placeholder="Szűrés név vagy ID szerint…" autocomplete="off" spellcheck="false">
                <div class="events-org-picker-grid">
                    <div class="events-org-picker-col">
                        <label class="events-org-picker-label" for="org-picker-pool">Szervezők</label>
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
                <div class="events-org-picker-grid" style="grid-template-columns:1fr auto 1fr;">
                    <div class="events-org-picker-col">
                        <label class="events-org-picker-label" for="venue-picker-pool">Helyszínek</label>
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
    <div class="form-row" style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;">
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
<div class="form-group">
    <label for="event_url">További információ</label>
    <input type="url" id="event_url" name="event_url" value="<?= h($e['event_url']) ?>" maxlength="2000" placeholder="https://">
    <?php if (!empty($e['event_url'])): ?>
        <p class="help" style="margin-top:0.5rem;"><a class="btn btn-secondary" href="<?= h($e['event_url']) ?>" target="_blank" rel="noopener noreferrer">Megnyitás új ablakban</a></p>
    <?php endif; ?>
</div>
</div>
<div class="card" style="margin-bottom:1rem;">
    <h3 style="margin-top:0;">Esemény képe</h3>
    <div class="form-group">
        <label for="event_featured_image_url">Kiemelt kép URL</label>
        <input type="text" id="event_featured_image_url" name="event_featured_image_url" value="<?= h($e['event_featured_image_url'] ?? '') ?>" maxlength="2000" placeholder="https://… vagy /útvonal/kép.jpg" spellcheck="false" autocomplete="off">
        <p class="help">Ha URL meg van adva, ez elsőbbséget élvez az eventpics képpel szemben.</p>
    </div>
    <div class="form-group">
        <label for="event_featured_image_upload">Kép feltöltése az eventpics mappába</label>
        <input type="file" id="event_featured_image_upload" name="event_featured_image_upload" accept="image/jpeg,image/png,image/webp,image/gif">
    </div>
    <div class="form-group">
        <label for="event_featured_image_pick">Választás az eventpics mappából</label>
        <select id="event_featured_image_pick" name="event_featured_image_pick">
            <option value="">— nincs —</option>
            <?php foreach ($eventpicFiles as $picFile): ?>
                <option value="<?= h($picFile) ?>" <?= ($picFile === (string) ($e['event_featured_image_pick'] ?? '')) ? 'selected' : '' ?>><?= h($picFile) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="help">Egy kép több eseménynél is használható.</p>
    </div>
</div>
<script type="application/json" id="events-org-picker-json"><?= $orgPickerJson ?></script>
<script type="application/json" id="events-venue-picker-json"><?= $venuePickerJson ?></script>
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
})();
</script>
