<?php
declare(strict_types=1);
/** @var string|null $eventFormCopyUrl Másolás link (szerkesztésnél) */
/** @var string $eventFormCancelUrl Mégse link */
/** @var string $eventFormActionsPlacement sidebar|footer */
/** @var bool $eventFormIsCopy Másolat létrehozása (két mentés gomb) */
$eventFormCancelUrl = $eventFormCancelUrl ?? events_url('events_admin.php');
$eventFormCopyUrl = $eventFormCopyUrl ?? null;
$eventFormIsCopy = !empty($eventFormIsCopy);
$placement = $eventFormActionsPlacement ?? 'footer';
$actionsClass = 'events-edit-form-actions'
    . ($placement === 'sidebar' ? ' events-edit-form-actions--sidebar' : '')
    . ($eventFormIsCopy ? ' events-edit-form-actions--copy' : '');
?>
<div class="<?= h($actionsClass) ?>">
    <?php if ($eventFormIsCopy): ?>
        <button type="submit" name="save_action" value="draft" class="btn btn-secondary">Mentés</button>
        <button type="submit" name="save_action" value="publish" class="btn btn-primary">Mentés és közzététel</button>
    <?php else: ?>
        <button type="submit" class="btn btn-primary" name="form_action" value="save">Mentés</button>
    <?php endif; ?>
    <?php if ($eventFormCopyUrl !== null && $eventFormCopyUrl !== ''): ?>
        <a href="<?= h($eventFormCopyUrl) ?>" class="btn btn-secondary">Másolás</a>
    <?php endif; ?>
    <a href="<?= h($eventFormCancelUrl) ?>" class="btn btn-secondary">Mégse</a>
</div>
