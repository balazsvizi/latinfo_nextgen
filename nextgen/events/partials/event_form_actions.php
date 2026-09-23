<?php
declare(strict_types=1);
/** @var string|null $eventFormCopyUrl Másolás link (szerkesztésnél) */
/** @var string $eventFormCancelUrl Mégse link */
/** @var string $eventFormActionsPlacement sidebar|footer */
/** @var bool $eventFormIsCopy Másolat létrehozása (két mentés gomb) */
/** @var bool $eventFormShowNotifyEmail Szerkesztő: szervezői értesítő e-mail gomb */
$eventFormCancelUrl = $eventFormCancelUrl ?? events_url('events_admin.php');
$eventFormCopyUrl = $eventFormCopyUrl ?? null;
$eventFormIsCopy = !empty($eventFormIsCopy);
$eventFormShowNotifyEmail = !empty($eventFormShowNotifyEmail);
$placement = $eventFormActionsPlacement ?? 'footer';
$actionsClass = 'events-edit-form-actions'
    . ($placement === 'sidebar' ? ' events-edit-form-actions--sidebar' : '')
    . ($eventFormIsCopy ? ' events-edit-form-actions--copy' : '')
    . ($eventFormShowNotifyEmail ? ' events-edit-form-actions--with-email' : '');
?>
<div class="<?= h($actionsClass) ?>">
    <?php if ($eventFormIsCopy): ?>
        <button type="submit" name="save_action" value="draft" class="btn btn-secondary">Mentés</button>
        <button type="submit" name="save_action" value="publish" class="btn btn-primary">Mentés és közzététel</button>
    <?php else: ?>
        <button type="submit" class="btn btn-primary events-edit-form-actions__save" name="form_action" value="save">Mentés</button>
        <?php if ($eventFormShowNotifyEmail): ?>
            <button
                type="button"
                class="btn btn-secondary events-edit-form-actions__email"
                data-event-notify-open
                title="E-mail a szervezőnek"
                aria-label="E-mail a szervezőnek"
            >
                <svg class="events-edit-form-actions__email-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7l8 6 8-6"/>
                </svg>
                <span>E-mail</span>
            </button>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($eventFormCopyUrl !== null && $eventFormCopyUrl !== ''): ?>
        <a href="<?= h($eventFormCopyUrl) ?>" class="btn btn-secondary">Másolás</a>
    <?php endif; ?>
    <a href="<?= h($eventFormCancelUrl) ?>" class="btn btn-secondary">Mégse</a>
</div>
