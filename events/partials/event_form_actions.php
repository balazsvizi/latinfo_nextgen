<?php
declare(strict_types=1);
/** @var string|null $eventFormCopyUrl Másolás link (szerkesztésnél) */
/** @var string $eventFormCancelUrl Mégse link */
/** @var string $eventFormActionsPlacement sidebar|footer */
$eventFormCancelUrl = $eventFormCancelUrl ?? events_url('events_admin.php');
$eventFormCopyUrl = $eventFormCopyUrl ?? null;
$placement = $eventFormActionsPlacement ?? 'footer';
$actionsClass = 'events-edit-form-actions'
    . ($placement === 'sidebar' ? ' events-edit-form-actions--sidebar' : '');
?>
<div class="<?= h($actionsClass) ?>">
    <button type="submit" class="btn btn-primary">Mentés</button>
    <?php if ($eventFormCopyUrl !== null && $eventFormCopyUrl !== ''): ?>
        <a href="<?= h($eventFormCopyUrl) ?>" class="btn btn-secondary">Másolás</a>
    <?php endif; ?>
    <a href="<?= h($eventFormCancelUrl) ?>" class="btn btn-secondary">Mégse</a>
</div>
