<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

hb_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hb_redirect(hb_url('bookings/index.php'));
}

$db = hb_get_db();
$bookingId = hb_post_int('id');
if ($bookingId <= 0) {
    $bookingId = hb_get_int('id');
}

if ($bookingId <= 0) {
    hb_flash('error', hb_t('error.not_found'));
    hb_redirect(hb_url('bookings/index.php'));
}

if (HbBookingService::delete($db, $bookingId, hb_subscriber_id(), hb_user_id())) {
    hb_flash('success', hb_t('bookings.deleted'));
} else {
    hb_flash('error', hb_t('error.not_found'));
}

hb_redirect(hb_url('bookings/index.php'));
