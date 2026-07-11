<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

hb_require_login();

$db = hb_get_db();
hb_refresh_session_from_db($db);

$subscriberId = hb_subscriber_id();
$userId = hb_user_id();
$bookingId = hb_get_int('id');

$booking = HbBookingService::findForSubscriber($db, $bookingId, $subscriberId);
if ($booking === null) {
    hb_flash('error', hb_t('error.not_found'));
    hb_redirect(hb_url('bookings/index.php'));
}

$properties = HbPropertyRepository::listForSubscriber($db, $subscriberId);
$selectedProperty = HbPropertyRepository::findForSubscriber($db, (int) $booking['property_id'], $subscriberId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = HbBookingService::update($db, $bookingId, $subscriberId, $userId, [
        'property_id' => (int) $booking['property_id'],
        'guest_name' => hb_post_string('guest_name'),
        'adults' => hb_post_int('adults', 1),
        'children' => hb_post_int('children', 0),
        'check_in' => hb_post_string('check_in'),
        'check_out' => hb_post_string('check_out'),
        'notes' => hb_post_string('notes'),
    ]);

    if ($result['ok']) {
        hb_flash('success', hb_t('bookings.updated'));
        if (!empty($result['warning'])) {
            hb_flash('warning', (string) $result['warning']);
        }
        hb_redirect(hb_url('bookings/edit.php?id=' . $bookingId));
    }

    hb_flash('error', (string) ($result['error'] ?? hb_t('error.generic')));
    $booking = array_merge($booking, [
        'guest_name' => hb_post_string('guest_name'),
        'adults' => hb_post_int('adults', 1),
        'children' => hb_post_int('children', 0),
        'check_in' => hb_post_string('check_in'),
        'check_out' => hb_post_string('check_out'),
        'notes' => hb_post_string('notes'),
    ]);
}

$pageTitle = hb_t('bookings.edit');
$activeNav = 'bookings';
$isEdit = true;
$formAction = hb_url('bookings/edit.php?id=' . $bookingId);
$showPropertySwitcher = false;

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="hb-page-head">
    <h1><?= hb_h(hb_t('bookings.edit')) ?></h1>
</div>

<?php if ($msg = hb_flash('success')): ?><p class="alert alert-success"><?= hb_h($msg) ?></p><?php endif; ?>
<?php if ($msg = hb_flash('error')): ?><p class="alert alert-error"><?= hb_h($msg) ?></p><?php endif; ?>
<?php if ($msg = hb_flash('warning')): ?><p class="alert alert-warning"><?= hb_h($msg) ?></p><?php endif; ?>

<?php require __DIR__ . '/partials/form.php'; ?>
<?php
require dirname(__DIR__) . '/partials/footer.php';
