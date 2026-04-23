<?php
declare(strict_types=1);
/** @var array $e aktuális mezőértékek */
/** @var array $organizers id => név */
?>
<div class="form-group">
    <label for="organizer_id">Szervező</label>
    <select id="organizer_id" name="organizer_id">
        <option value="">—</option>
        <?php foreach ($organizers as $oid => $onev): ?>
            <option value="<?= (int) $oid ?>" <?= (int) ($e['organizer_id'] ?? 0) === (int) $oid ? 'selected' : '' ?>><?= h($onev) ?></option>
        <?php endforeach; ?>
    </select>
</div>
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
