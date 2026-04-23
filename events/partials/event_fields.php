<?php
declare(strict_types=1);
/** @var array $e aktuális mezőértékek */
/** @var array $organizers id => név */
$selOrg = isset($e['organizer_ids']) && is_array($e['organizer_ids']) ? array_map('intval', $e['organizer_ids']) : [];
?>
<fieldset class="form-group events-organizers-fieldset">
    <legend>Szervezők</legend>
    <p class="help">Több szervező is kijelölhető; a lista sorrendje a megjelenített névsorrend (felülről lefelé).</p>
    <div class="events-organizer-checks">
        <?php foreach ($organizers as $oid => $onev): ?>
            <label class="events-organizer-check">
                <input type="checkbox" name="organizer_ids[]" value="<?= (int) $oid ?>" <?= in_array((int) $oid, $selOrg, true) ? 'checked' : '' ?>>
                <span><?= h($onev) ?> <span class="events-organizer-id">(#<?= (int) $oid ?>)</span></span>
            </label>
        <?php endforeach; ?>
    </div>
    <?php if ($organizers === []): ?>
        <p class="help">Nincs szervező rögzítve. Előbb <a href="<?= h(events_url('import_csv.php')) ?>?target_table=events_organizers">CSV importtal</a> vagy az adatbázisban vegyél fel szervezőket.</p>
    <?php endif; ?>
</fieldset>
<div class="form-group">
    <label for="venue_id">Helyszín ID (későbbi modul)</label>
    <input type="number" id="venue_id" name="venue_id" min="0" step="1" value="<?= h($e['venue_id']) ?>">
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
