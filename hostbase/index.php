<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

hb_require_login();

$db = hb_get_db();
hb_refresh_session_from_db($db);

$subscriberId = hb_subscriber_id();
$properties = HbPropertyRepository::listForSubscriber($db, $subscriberId);
$currentProperty = HbPropertyRepository::ensureCurrentProperty($db, $subscriberId);
$upcoming = HbBookingService::upcomingForSubscriber($db, $subscriberId, 5);

$pageTitle = hb_t('dashboard.title');
$activeNav = 'dashboard';

require __DIR__ . '/partials/header.php';
?>
<?php if ($msg = hb_flash('success')): ?><p class="alert alert-success"><?= hb_h($msg) ?></p><?php endif; ?>
<?php if ($msg = hb_flash('error')): ?><p class="alert alert-error"><?= hb_h($msg) ?></p><?php endif; ?>
<?php if ($msg = hb_flash('warning')): ?><p class="alert alert-warning"><?= hb_h($msg) ?></p><?php endif; ?>

<div class="card">
    <h1 class="card-title"><?= hb_h(hb_t('dashboard.welcome', ['name' => hb_session_display_name()])) ?></h1>
    <p class="help"><?= hb_h(hb_t('dashboard.subscriber')) ?>: <?= hb_h((string) ($_SESSION['hb_subscriber_name'] ?? '')) ?></p>
</div>

<div class="card">
    <h2><?= hb_h(hb_t('dashboard.quick_actions')) ?></h2>
    <div class="dash-grid">
        <a href="<?= hb_h(hb_url('bookings/create.php')) ?>" class="dash-card">
            <h3><?= hb_h(hb_t('dashboard.new_booking')) ?></h3>
            <div class="num">+</div>
        </a>
        <a href="<?= hb_h(hb_url('calendar/index.php')) ?>" class="dash-card">
            <h3><?= hb_h(hb_t('dashboard.view_calendar')) ?></h3>
            <div class="num"><?= count($properties) ?></div>
            <p><?= hb_h(hb_t('nav.calendar')) ?></p>
        </a>
        <a href="<?= hb_h(hb_url('properties/index.php')) ?>" class="dash-card">
            <h3><?= hb_h(hb_t('dashboard.properties')) ?></h3>
            <div class="num"><?= count($properties) ?></div>
        </a>
    </div>
</div>

<div class="card">
    <h2><?= hb_h(hb_t('dashboard.upcoming')) ?></h2>
    <?php if ($upcoming === []): ?>
        <p class="help"><?= hb_h(hb_t('dashboard.no_upcoming')) ?></p>
    <?php else: ?>
        <ul class="booking-list">
            <?php foreach ($upcoming as $booking): ?>
                <li>
                    <div class="booking-list__guest"><?= hb_h((string) $booking['guest_name']) ?></div>
                    <div class="booking-list__meta">
                        <?= hb_h((string) $booking['property_name']) ?>
                        · <?= hb_h(hb_format_date((string) $booking['check_in'])) ?> – <?= hb_h(hb_format_date((string) $booking['check_out'])) ?>
                        · <?= (int) $booking['adults'] ?>+<?= (int) $booking['children'] ?>
                    </div>
                    <a href="<?= hb_h(hb_url('bookings/edit.php?id=' . (int) $booking['id'])) ?>"><?= hb_h(hb_t('bookings.edit')) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php
require __DIR__ . '/partials/footer.php';
