<?php
declare(strict_types=1);
/**
 * @var string $status
 * @var array<string, string> $statusActions
 * @var bool $eventEditActionsBottom
 */
$eventEditActionsBottom = !empty($eventEditActionsBottom);
?>
<div class="partner-event-edit__actions<?= $eventEditActionsBottom ? ' partner-event-edit__actions--bottom' : '' ?>">
    <button type="submit" class="btn btn-primary" name="partner_save" value="1">Mentés</button>
    <div class="partner-event-edit__status-actions" role="group" aria-label="Státuszváltás">
        <?php foreach ($statusActions as $statusValue => $statusLabel): ?>
            <button
                type="submit"
                class="btn btn-secondary<?= $status === $statusValue ? ' is-current' : '' ?>"
                name="event_status"
                value="<?= h($statusValue) ?>"
                <?= $status === $statusValue ? 'aria-pressed="true"' : 'aria-pressed="false"' ?>
            ><?= h($statusLabel) ?></button>
        <?php endforeach; ?>
    </div>
</div>
