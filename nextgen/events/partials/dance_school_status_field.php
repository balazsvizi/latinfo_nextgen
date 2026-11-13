<?php
declare(strict_types=1);

/**
 * Tánciskola státusz — a szerkesztő jobb oldali oszlopának teteje.
 *
 * @var array<string, mixed> $row
 */
$statusLabels = dance_school_status_labels();
$currentStatus = dance_school_normalize_status($row['status'] ?? 'draft');
?>
<div class="events-edit-panel events-edit-panel--tone-publish events-edit-panel--publish">
    <div class="form-group events-edit-panel__status-only">
        <label class="visually-hidden" for="school_status">Státusz</label>
        <select id="school_status" name="status" required aria-label="Státusz">
            <?php foreach ($statusLabels as $statusValue => $statusLabel): ?>
                <option value="<?= h($statusValue) ?>"<?= $currentStatus === $statusValue ? ' selected' : '' ?>><?= h($statusLabel) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
