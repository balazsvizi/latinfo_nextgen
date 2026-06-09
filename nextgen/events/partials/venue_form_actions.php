<?php
declare(strict_types=1);

/** @var string $venueFormCancelUrl */
/** @var string $venueFormActionsPlacement sidebar|footer */
/** @var bool $venueFormShowDelete */
/** @var int $venueEventCount */

$venueFormCancelUrl = $venueFormCancelUrl ?? events_url('venues.php');
$placement = $venueFormActionsPlacement ?? 'footer';
$venueFormShowDelete = !empty($venueFormShowDelete);
$venueEventCount = (int) ($venueEventCount ?? 0);
$actionsClass = 'events-edit-form-actions'
    . ($placement === 'sidebar' ? ' events-edit-form-actions--sidebar' : '');
?>
<div class="<?= h($actionsClass) ?>">
    <button type="submit" class="btn btn-primary" name="action" value="save">Mentés</button>
    <a href="<?= h($venueFormCancelUrl) ?>" class="btn btn-secondary">Mégse</a>
    <?php if ($venueFormShowDelete && $venueEventCount === 0): ?>
        <button type="submit" class="btn btn-danger events-edit-form-actions__delete" name="action" value="delete" onclick="return confirm('Biztosan törlöd ezt a helyszínt?');">Törlés</button>
    <?php endif; ?>
</div>
