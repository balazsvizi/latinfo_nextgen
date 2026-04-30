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
$eventpicsPick = (string) ($e['event_featured_image_pick'] ?? '');
?>
<div class="card" style="margin-bottom:1rem;">
    <h3 style="margin-top:0;">Szervezők és helyszín</h3>
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;">
        <fieldset class="form-group events-organizers-fieldset" id="events-org-picker-fieldset"<?= $organizers === [] ? ' data-organizers-empty="1"' : '' ?>>
            <legend>Szervezők</legend>
            <?php if ($organizers === []): ?>
                <p class="help">Nincs szervező rögzítve. Előbb <a href="<?= h(events_url('import_csv.php')) ?>?target_table=events_organizers">CSV importtal</a> vagy az adatbázisban vegyél fel szervezőket.</p>
            <?php else: ?>
                <p class="help">Szűrj, majd <strong>+</strong> a felvételhez. A kiválasztottak a lista alatt jelennek meg.</p>
                <input type="search" id="org-picker-filter" class="events-org-filter" placeholder="Szűrés név vagy ID szerint…" autocomplete="off" spellcheck="false">
                <div class="events-org-picker-stack">
                    <div class="events-org-picker-row">
                        <div class="events-org-picker-col">
                            <label class="events-org-picker-label" for="org-picker-pool">Szervezők</label>
                            <select id="org-picker-pool" class="events-org-list events-org-list--pool" size="5"></select>
                        </div>
                        <div class="events-org-picker-btns">
                            <button type="button" class="btn btn-secondary events-org-btn" id="org-picker-add" title="Hozzáadás">+</button>
                            <button type="button" class="btn btn-secondary events-org-btn" id="org-picker-remove" title="Eltávolítás">−</button>
                        </div>
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
                <div class="events-org-picker-stack">
                    <div class="events-org-picker-row">
                        <div class="events-org-picker-col">
                            <label class="events-org-picker-label" for="venue-picker-pool">Helyszínek</label>
                            <select id="venue-picker-pool" class="events-org-list events-org-list--pool" size="5"></select>
                        </div>
                        <div class="events-org-picker-btns">
                            <button type="button" class="btn btn-secondary events-org-btn" id="venue-picker-add" title="Kiválasztás">+</button>
                            <button type="button" class="btn btn-secondary events-org-btn" id="venue-picker-remove" title="Törlés">−</button>
                        </div>
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
    <h3 style="margin-top:0;">Esemény képe</h3>
    <div class="form-group">
        <label for="event_featured_image_url">Kiemelt kép URL</label>
        <input type="text" id="event_featured_image_url" name="event_featured_image_url" value="<?= h($e['event_featured_image_url'] ?? '') ?>" maxlength="2000" placeholder="https://… vagy /útvonal/kép.jpg" spellcheck="false" autocomplete="off">
        <p class="help">Ha URL meg van adva, ez elsőbbséget élvez az eventpics képpel szemben.</p>
    </div>
    <div class="form-group eventpics-media-block">
        <label>Médiagaléria (eventpics)</label>
        <p class="help">Válassz egy képet a rácsból, vagy tölts fel újat (ide húzhatod is). Ugyanaz a fájl több eseménynél is használható.</p>
        <input type="hidden" name="event_featured_image_pick" id="event_featured_image_pick" value="<?= h($eventpicsPick) ?>">
        <div
            class="eventpics-browser"
            id="eventpics-browser"
            data-upload-url="<?= h(events_url('ajax_eventpic_upload.php')) ?>"
            data-csrf="<?= h(csrf_token('events_eventpics')) ?>"
        >
            <div class="eventpics-toolbar">
                <button type="button" class="btn btn-primary" id="eventpics-btn-upload">Fájlok feltöltése</button>
                <button type="button" class="btn btn-secondary" id="eventpics-btn-clear">Nincs kiválasztott kép</button>
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
            <input type="file" id="eventpics-file-input" class="visually-hidden" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
        </div>
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

(function () {
    var root = document.getElementById('eventpics-browser');
    var hidden = document.getElementById('event_featured_image_pick');
    var grid = document.getElementById('eventpics-grid');
    var btnUp = document.getElementById('eventpics-btn-upload');
    var btnClear = document.getElementById('eventpics-btn-clear');
    var fileInp = document.getElementById('eventpics-file-input');
    var drop = document.getElementById('eventpics-dropzone');
    var msg = document.getElementById('eventpics-msg');
    var filter = document.getElementById('eventpics-filter');
    if (!root || !hidden || !grid || !btnUp || !btnClear || !fileInp || !drop) return;

    function setMsg(t, isErr) {
        if (!msg) return;
        msg.textContent = t || '';
        msg.classList.toggle('eventpics-msg--error', !!isErr);
    }

    function syncSelectionClasses() {
        var cur = (hidden.value || '').trim();
        grid.querySelectorAll('.eventpics-item').forEach(function (b) {
            b.classList.toggle('is-selected', cur !== '' && b.getAttribute('data-filename') === cur);
        });
    }

    function selectFilename(name) {
        hidden.value = name || '';
        syncSelectionClasses();
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
            if (hidden.value === fn) {
                selectFilename('');
            } else {
                selectFilename(fn);
            }
        });
    }
    grid.querySelectorAll('.eventpics-item').forEach(bindItem);

    btnClear.addEventListener('click', function () {
        selectFilename('');
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
        var url = root.getAttribute('data-upload-url') || '';
        var csrf = root.getAttribute('data-csrf') || '';
        if (!url || !csrf) {
            setMsg('Hiányzik a feltöltési beállítás.', true);
            return;
        }
        var total = list.length;
        setMsg('Feltöltés…', false);
        function runOne() {
            if (!list.length) {
                setMsg(total > 1 ? total + ' fájl feltöltve.' : 'Feltöltve.', false);
                fileInp.value = '';
                if (filter) {
                    filter.value = '';
                    filter.dispatchEvent(new Event('input', { bubbles: true }));
                }
                return;
            }
            var f = list.shift();
            var fd = new FormData();
            fd.append('file', f, f.name);
            fd.append('eventpics_csrf', csrf);
            fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) {
                        setMsg((data && data.error) ? data.error : 'Feltöltés sikertelen.', true);
                        runOne();
                        return;
                    }
                    addThumb(data.filename, data.thumb_url || data.url);
                    selectFilename(data.filename);
                    runOne();
                })
                .catch(function () {
                    setMsg('Hálózati hiba a feltöltéskor.', true);
                    runOne();
                });
        }
        runOne();
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
        uploadFiles(dt.files);
    });
})();
</script>
