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
?>
<fieldset class="form-group events-organizers-fieldset" id="events-org-picker-fieldset"<?= $organizers === [] ? ' data-organizers-empty="1"' : '' ?>>
    <legend>Szervezők</legend>
    <?php if ($organizers === []): ?>
        <p class="help">Nincs szervező rögzítve. Előbb <a href="<?= h(events_url('import_csv.php')) ?>?target_table=events_organizers">CSV importtal</a> vagy az adatbázisban vegyél fel szervezőket.</p>
    <?php else: ?>
        <p class="help">Szűrj a listán, válassz egy sort, majd <strong>+</strong> a felvételhez. A jobb oldali listában <strong>−</strong> vagy dupla kattintás töröl. A jobb oldali sorrend mentődik.</p>
        <input type="search" id="org-picker-filter" class="events-org-filter" placeholder="Szűrés név vagy ID szerint…" autocomplete="off" spellcheck="false">
        <div class="events-org-picker-grid">
            <div class="events-org-picker-col">
                <label class="events-org-picker-label" for="org-picker-pool">Szervezők</label>
                <select id="org-picker-pool" class="events-org-list events-org-list--pool" size="5"></select>
            </div>
            <div class="events-org-picker-btns">
                <button type="button" class="btn btn-secondary events-org-btn" id="org-picker-add" title="Hozzáadás a kiválasztottakhoz">+</button>
                <button type="button" class="btn btn-secondary events-org-btn" id="org-picker-remove" title="Eltávolítás a kiválasztottak közül">−</button>
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
        <script type="application/json" id="events-org-picker-json"><?= $orgPickerJson ?></script>
        <script>
        (function () {
            var jsonEl = document.getElementById('events-org-picker-json');
            var pool = document.getElementById('org-picker-pool');
            var selected = document.getElementById('org-picker-selected');
            var filter = document.getElementById('org-picker-filter');
            var hiddens = document.getElementById('org-picker-hiddens');
            var btnAdd = document.getElementById('org-picker-add');
            var btnRemove = document.getElementById('org-picker-remove');
            if (!jsonEl || !pool || !selected || !filter || !hiddens || !btnAdd || !btnRemove) return;
            var raw = jsonEl.textContent || '{}';
            var data;
            try { data = JSON.parse(raw); } catch (e) { return; }
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
            function poolFilterText() {
                return (filter.value || '').trim().toLowerCase();
            }
            function renderPool() {
                var q = poolFilterText();
                pool.innerHTML = '';
                var taken = {};
                selectedIds.forEach(function (id) { taken[id] = true; });
                var rows = all.filter(function (row) { return !taken[row.id]; });
                rows.sort(function (a, b) { return a.name.localeCompare(b.name, 'hu', { sensitivity: 'base' }); });
                rows.forEach(function (row) {
                    if (q !== '') {
                        var hay = (row.name + ' ' + row.id).toLowerCase();
                        if (hay.indexOf(q) === -1) return;
                    }
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
            function addFromPool() {
                var opt = pool.options[pool.selectedIndex];
                if (!opt) return;
                var id = parseInt(opt.value, 10);
                if (!id || selectedIds.indexOf(id) !== -1) return;
                selectedIds.push(id);
                renderSelected();
                renderPool();
            }
            function removeFromSelected() {
                var opt = selected.options[selected.selectedIndex];
                if (!opt) return;
                var id = parseInt(opt.value, 10);
                var idx = selectedIds.indexOf(id);
                if (idx === -1) return;
                selectedIds.splice(idx, 1);
                renderSelected();
                renderPool();
            }
            filter.addEventListener('input', function () { renderPool(); });
            btnAdd.addEventListener('click', addFromPool);
            btnRemove.addEventListener('click', removeFromSelected);
            pool.addEventListener('dblclick', addFromPool);
            selected.addEventListener('dblclick', removeFromSelected);
            pool.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') { ev.preventDefault(); addFromPool(); }
            });
            selected.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') { ev.preventDefault(); removeFromSelected(); }
            });
            renderSelected();
            renderPool();
        })();
        </script>
    <?php endif; ?>
</fieldset>
<div class="form-group">
    <label for="venue_id">Helyszín</label>
    <?php if ($venues !== []): ?>
        <select id="venue_id" name="venue_id">
            <option value="">— nincs —</option>
            <?php foreach ($venues as $vid => $vname): ?>
                <option value="<?= (int) $vid ?>" <?= ((string) (int) $vid === (string) $e['venue_id']) ? 'selected' : '' ?>><?= h($vname) ?></option>
            <?php endforeach; ?>
        </select>
    <?php else: ?>
        <p class="help">Nincs helyszín felvéve. <a href="<?= h(events_url('venues.php')) ?>">Helyszínek</a> · <a href="<?= h(events_url('venue_letrehoz.php')) ?>">Új helyszín</a> · <a href="<?= h(events_url('import_csv.php')) ?>?target_table=events_venues">CSV import</a></p>
        <input type="hidden" name="venue_id" value="">
    <?php endif; ?>
</div>
<div class="form-group">
    <label for="event_name">Esemény neve *</label>
    <input type="text" id="event_name" name="event_name" value="<?= h($e['event_name']) ?>" required maxlength="500">
</div>
<div class="form-group">
    <label for="event_slug">URL slug</label>
    <input type="text" id="event_slug" name="event_slug" value="<?= h($e['event_slug']) ?>" maxlength="255" pattern="[a-z0-9\-]*" title="Kisbetű, szám és kötőjel">
    <p class="help">Ha üres, a névből generáljuk. Nyilvános: <?= h(events_public_canonical_url('pelda-slug')) ?> (slug helye)</p>
</div>
<div class="form-group">
    <label for="event_featured_image_url">Kiemelt kép (URL)</label>
    <input type="text" id="event_featured_image_url" name="event_featured_image_url" value="<?= h($e['event_featured_image_url'] ?? '') ?>" maxlength="2000" placeholder="https://… vagy /útvonal/kép.jpg" spellcheck="false" autocomplete="off">
    <p class="help">Opcionális. Teljes https URL vagy a honlapon belüli útvonal <code>/</code>-rel. A nyilvános oldalon és a közösségi megosztásoknál (OG) jelenik meg.</p>
</div>
<div class="form-group">
    <label for="event_status">Státusz *</label>
    <select id="event_status" name="event_status" required>
        <?php foreach (events_allowed_post_statuses() as $val): ?>
            <option value="<?= h($val) ?>" <?= ($e['event_status'] === $val) ? 'selected' : '' ?>><?= h(events_post_status_label($val)) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="form-group">
    <label for="event_content">Leírás (HTML) *</label>
    <textarea id="event_content" name="event_content" class="js-html-editor-source" rows="14" required><?= h($e['event_content']) ?></textarea>
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
<div class="form-group">
    <label for="event_url">Külső link (jegy/információ)</label>
    <input type="url" id="event_url" name="event_url" value="<?= h($e['event_url']) ?>" maxlength="2000" placeholder="https://">
</div>
<div class="form-group">
    <label><input type="checkbox" name="event_latinfohu_partner" value="1" <?= !empty($e['event_latinfohu_partner']) ? 'checked' : '' ?>> Latinfo.hu partner</label>
</div>
