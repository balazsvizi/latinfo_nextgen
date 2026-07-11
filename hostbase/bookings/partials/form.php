<?php
declare(strict_types=1);
/** @var array<string, mixed>|null $booking */
/** @var array<string, mixed>|null $selectedProperty */
/** @var list<array<string, mixed>> $properties */
/** @var string $formAction */
/** @var bool $isEdit */
?>
<form method="post" action="<?= hb_h($formAction) ?>" class="card">
    <?php if ($isEdit && $booking !== null): ?>
        <input type="hidden" name="id" value="<?= (int) $booking['id'] ?>">
    <?php endif; ?>

    <div class="form-group">
        <label for="property_id"><?= hb_h(hb_t('property.select')) ?></label>
        <select name="property_id" id="property_id" required>
            <?php foreach ($properties as $property): ?>
                <?php
                $selectedId = (int) ($selectedProperty['id'] ?? ($booking['property_id'] ?? 0));
                ?>
                <option value="<?= (int) $property['id'] ?>"<?= $selectedId === (int) $property['id'] ? ' selected' : '' ?>>
                    <?= hb_h((string) $property['name']) ?> (<?= (int) $property['max_guests'] ?> <?= hb_h(hb_t('property.max_guests')) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="guest_name"><?= hb_h(hb_t('bookings.guest_name')) ?></label>
        <input type="text" id="guest_name" name="guest_name" required maxlength="255"
               value="<?= hb_h((string) ($booking['guest_name'] ?? hb_post_string('guest_name'))) ?>">
    </div>

    <div class="form-row form-row--3">
        <div class="form-group">
            <label for="adults"><?= hb_h(hb_t('bookings.adults')) ?></label>
            <input type="number" id="adults" name="adults" min="0" max="99" required
                   value="<?= (int) ($booking['adults'] ?? hb_post_int('adults', 2)) ?>">
        </div>
        <div class="form-group">
            <label for="children"><?= hb_h(hb_t('bookings.children')) ?></label>
            <input type="number" id="children" name="children" min="0" max="99" required
                   value="<?= (int) ($booking['children'] ?? hb_post_int('children', 0)) ?>">
        </div>
        <div class="form-group">
            <label><?= hb_h(hb_t('bookings.nights')) ?></label>
            <input type="text" readonly disabled
                   value="<?php
                   $ci = (string) ($booking['check_in'] ?? hb_post_string('check_in'));
                   $co = (string) ($booking['check_out'] ?? hb_post_string('check_out'));
                   echo $ci !== '' && $co !== '' ? hb_nights_between($ci, $co) : '–';
                   ?>">
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="check_in"><?= hb_h(hb_t('bookings.check_in')) ?></label>
            <input type="date" id="check_in" name="check_in" required
                   value="<?= hb_h((string) ($booking['check_in'] ?? hb_post_string('check_in'))) ?>">
        </div>
        <div class="form-group">
            <label for="check_out"><?= hb_h(hb_t('bookings.check_out')) ?></label>
            <input type="date" id="check_out" name="check_out" required
                   value="<?= hb_h((string) ($booking['check_out'] ?? hb_post_string('check_out'))) ?>">
        </div>
    </div>

    <div class="form-group">
        <label for="notes"><?= hb_h(hb_t('bookings.notes')) ?></label>
        <textarea id="notes" name="notes" rows="4"><?= hb_h((string) ($booking['notes'] ?? hb_post_string('notes'))) ?></textarea>
    </div>

    <div class="inline-actions">
        <button type="submit" class="btn btn-primary"><?= hb_h(hb_t('bookings.save')) ?></button>
        <a href="<?= hb_h(hb_url('bookings/index.php')) ?>" class="btn btn-secondary"><?= hb_h(hb_t('common.cancel')) ?></a>
        <?php if ($isEdit && $booking !== null): ?>
            <button type="submit" formaction="<?= hb_h(hb_url('bookings/delete.php')) ?>" formmethod="post" class="btn btn-danger"
                    onclick="return confirm('<?= hb_h(hb_t('bookings.delete_confirm')) ?>');">
                <?= hb_h(hb_t('bookings.delete')) ?>
            </button>
        <?php endif; ?>
    </div>
</form>
