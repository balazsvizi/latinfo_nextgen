<?php
declare(strict_types=1);

/**
 * Tánciskola naptár / workshop sor.
 *
 * @var int $evIndex
 * @var array<string, mixed> $eventRow
 * @var list<array{id:int,name:string}> $locationOptions
 * @var array<string, string> $eventTypeLabels
 */
$evIndex = (int) ($evIndex ?? 0);
$eventRow = is_array($eventRow ?? null) ? $eventRow : [];
$locationOptions = $locationOptions ?? [];
$eventTypeLabels = $eventTypeLabels ?? dance_school_event_type_labels();
$prefix = 'events[' . $evIndex . ']';
$eventType = (string) ($eventRow['event_type'] ?? 'workshop');
$locId = (int) ($eventRow['location_id'] ?? 0);

$toLocal = static function (mixed $raw): string {
    $s = trim((string) ($raw ?? ''));
    if ($s === '') {
        return '';
    }
    $s = str_replace(' ', 'T', $s);

    return substr($s, 0, 16);
};
$starts = $toLocal($eventRow['starts_at'] ?? '');
$ends = $toLocal($eventRow['ends_at'] ?? '');
?>
<div class="events-edit-panel dance-school-event-row" data-dance-event-row>
    <div class="events-list-head" style="margin-bottom:0.75rem;">
        <h4 class="events-edit-panel__title" style="margin:0;">Esemény #<?= (int) $evIndex + 1 ?></h4>
        <button type="button" class="btn btn-secondary btn-sm" data-dance-event-remove>Törlés</button>
    </div>
    <input type="hidden" name="<?= h($prefix) ?>[id]" value="<?= (int) ($eventRow['id'] ?? 0) ?>">
    <div class="events-tag-dj-profile__grid">
        <div class="form-group">
            <label>Cím *</label>
            <input type="text" name="<?= h($prefix) ?>[title]" value="<?= h((string) ($eventRow['title'] ?? '')) ?>" maxlength="255" placeholder="Workshop címe">
        </div>
        <div class="form-group">
            <label>Típus</label>
            <select name="<?= h($prefix) ?>[event_type]">
                <?php foreach ($eventTypeLabels as $val => $lab): ?>
                    <option value="<?= h($val) ?>"<?= $eventType === $val ? ' selected' : '' ?>><?= h($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Kezdés *</label>
            <input type="datetime-local" name="<?= h($prefix) ?>[starts_at]" value="<?= h($starts) ?>">
        </div>
        <div class="form-group">
            <label>Vége</label>
            <input type="datetime-local" name="<?= h($prefix) ?>[ends_at]" value="<?= h($ends) ?>">
        </div>
        <div class="form-group">
            <label>Helyszín</label>
            <select name="<?= h($prefix) ?>[location_id]">
                <option value="0">— nincs —</option>
                <?php foreach ($locationOptions as $loc): ?>
                    <option value="<?= (int) $loc['id'] ?>"<?= $locId === (int) $loc['id'] ? ' selected' : '' ?>><?= h($loc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Ár / info</label>
            <input type="text" name="<?= h($prefix) ?>[price_info]" value="<?= h((string) ($eventRow['price_info'] ?? '')) ?>" maxlength="255">
        </div>
        <div class="form-group">
            <label>Jelentkezés URL</label>
            <input type="text" name="<?= h($prefix) ?>[registration_url]" value="<?= h((string) ($eventRow['registration_url'] ?? '')) ?>" maxlength="2000" placeholder="https://…">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="<?= h($prefix) ?>[is_published]" value="1"<?= !empty($eventRow['is_published']) ? ' checked' : '' ?>> Publikált</label>
        </div>
    </div>
    <div class="form-group">
        <label>Leírás</label>
        <textarea name="<?= h($prefix) ?>[description]" rows="3"><?= h((string) ($eventRow['description'] ?? '')) ?></textarea>
    </div>
</div>
