<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

hb_require_login();

$db = hb_get_db();
hb_refresh_session_from_db($db);

$subscriberId = hb_subscriber_id();
$properties = HbPropertyRepository::listForSubscriber($db, $subscriberId);
$currentProperty = HbPropertyRepository::ensureCurrentProperty($db, $subscriberId);

$filterPropertyId = hb_get_int('property_id');
if ($filterPropertyId <= 0 && $currentProperty !== null) {
    $filterPropertyId = (int) $currentProperty['id'];
}

$filterFrom = hb_get_string('from');
$filterTo = hb_get_string('to');

$bookings = HbBookingService::listForSubscriber(
    $db,
    $subscriberId,
    $filterPropertyId > 0 ? $filterPropertyId : null,
    $filterFrom !== '' ? $filterFrom : null,
    $filterTo !== '' ? $filterTo : null
);

$pageTitle = hb_t('bookings.title');
$activeNav = 'bookings';

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="hb-page-head">
    <h1><?= hb_h(hb_t('bookings.title')) ?></h1>
    <div class="hb-actions">
        <a href="<?= hb_h(hb_url('bookings/create.php')) ?>" class="btn btn-primary"><?= hb_h(hb_t('bookings.new')) ?></a>
    </div>
</div>

<?php if ($msg = hb_flash('success')): ?><p class="alert alert-success"><?= hb_h($msg) ?></p><?php endif; ?>
<?php if ($msg = hb_flash('error')): ?><p class="alert alert-error"><?= hb_h($msg) ?></p><?php endif; ?>
<?php if ($msg = hb_flash('warning')): ?><p class="alert alert-warning"><?= hb_h($msg) ?></p><?php endif; ?>

<div class="card">
    <form method="get" class="filter-bar">
        <div class="form-group">
            <label for="property_id"><?= hb_h(hb_t('property.select')) ?></label>
            <select name="property_id" id="property_id" class="hb-select">
                <option value="0"><?= hb_h(hb_t('property.all')) ?></option>
                <?php foreach ($properties as $property): ?>
                    <option value="<?= (int) $property['id'] ?>"<?= $filterPropertyId === (int) $property['id'] ? ' selected' : '' ?>>
                        <?= hb_h((string) $property['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="from"><?= hb_h(hb_t('bookings.filter_from')) ?></label>
            <input type="date" id="from" name="from" value="<?= hb_h($filterFrom) ?>">
        </div>
        <div class="form-group">
            <label for="to"><?= hb_h(hb_t('bookings.filter_to')) ?></label>
            <input type="date" id="to" name="to" value="<?= hb_h($filterTo) ?>">
        </div>
        <button type="submit" class="btn btn-secondary"><?= hb_h(hb_t('bookings.filter_apply')) ?></button>
        <a href="<?= hb_h(hb_url('bookings/index.php')) ?>" class="btn btn-secondary"><?= hb_h(hb_t('bookings.filter_reset')) ?></a>
    </form>
</div>

<?php if ($bookings === []): ?>
    <div class="card"><p class="help"><?= hb_h(hb_t('bookings.empty')) ?></p></div>
<?php else: ?>
    <div class="mobile-cards">
        <?php foreach ($bookings as $booking): ?>
            <div class="booking-card">
                <div class="booking-card__head">
                    <span class="booking-card__guest"><?= hb_h((string) $booking['guest_name']) ?></span>
                    <a href="<?= hb_h(hb_url('bookings/edit.php?id=' . (int) $booking['id'])) ?>"><?= hb_h(hb_t('bookings.edit')) ?></a>
                </div>
                <div class="booking-card__property"><?= hb_h((string) $booking['property_name']) ?></div>
                <div class="booking-card__meta">
                    <?= hb_h(hb_format_date((string) $booking['check_in'])) ?> – <?= hb_h(hb_format_date((string) $booking['check_out'])) ?>
                    · <?= hb_nights_between((string) $booking['check_in'], (string) $booking['check_out']) ?> <?= hb_h(hb_t('bookings.nights')) ?>
                    · <?= (int) $booking['adults'] ?>+<?= (int) $booking['children'] ?>
                </div>
                <?php if (!empty($booking['notes'])): ?>
                    <p class="help"><?= nl2br(hb_h((string) $booking['notes'])) ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card desktop-table">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?= hb_h(hb_t('bookings.guest_name')) ?></th>
                        <th><?= hb_h(hb_t('property.select')) ?></th>
                        <th><?= hb_h(hb_t('bookings.check_in')) ?></th>
                        <th><?= hb_h(hb_t('bookings.check_out')) ?></th>
                        <th><?= hb_h(hb_t('bookings.nights')) ?></th>
                        <th><?= hb_h(hb_t('bookings.total_guests')) ?></th>
                        <th><?= hb_h(hb_t('bookings.actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?= hb_h((string) $booking['guest_name']) ?></td>
                            <td><?= hb_h((string) $booking['property_name']) ?></td>
                            <td><?= hb_h(hb_format_date((string) $booking['check_in'])) ?></td>
                            <td><?= hb_h(hb_format_date((string) $booking['check_out'])) ?></td>
                            <td><?= hb_nights_between((string) $booking['check_in'], (string) $booking['check_out']) ?></td>
                            <td><?= (int) $booking['adults'] ?>+<?= (int) $booking['children'] ?></td>
                            <td><a href="<?= hb_h(hb_url('bookings/edit.php?id=' . (int) $booking['id'])) ?>"><?= hb_h(hb_t('bookings.edit')) ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php
require dirname(__DIR__) . '/partials/footer.php';
