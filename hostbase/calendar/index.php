<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

hb_require_login();

$db = hb_get_db();
hb_refresh_session_from_db($db);

$subscriberId = hb_subscriber_id();
$properties = HbPropertyRepository::listForSubscriber($db, $subscriberId);
$currentProperty = HbPropertyRepository::ensureCurrentProperty($db, $subscriberId);

if ($currentProperty === null) {
    hb_flash('error', hb_t('error.not_found'));
    hb_redirect(hb_url('index.php'));
}

$propertyId = (int) $currentProperty['id'];
$year = hb_get_int('year', (int) date('Y'));
$month = hb_get_int('month', (int) date('n'));

if ($month < 1) {
    $month = 12;
    $year--;
} elseif ($month > 12) {
    $month = 1;
    $year++;
}

$calendar = HbCalendarService::buildMonth($db, $propertyId, $subscriberId, $year, $month);

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$pageTitle = hb_t('calendar.title');
$activeNav = 'calendar';

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="hb-page-head">
    <h1><?= hb_h(hb_t('calendar.title')) ?></h1>
    <div class="hb-actions">
        <a href="<?= hb_h(hb_url('bookings/create.php?property_id=' . $propertyId)) ?>" class="btn btn-primary"><?= hb_h(hb_t('bookings.new')) ?></a>
    </div>
</div>

<div class="card">
    <div class="cal-toolbar">
        <div class="cal-nav">
            <a href="<?= hb_h(hb_url('calendar/index.php?year=' . $prevYear . '&month=' . $prevMonth)) ?>" class="btn btn-secondary">&larr;</a>
            <a href="<?= hb_h(hb_url('calendar/index.php')) ?>" class="btn btn-secondary"><?= hb_h(hb_t('calendar.today')) ?></a>
            <a href="<?= hb_h(hb_url('calendar/index.php?year=' . $nextYear . '&month=' . $nextMonth)) ?>" class="btn btn-secondary">&rarr;</a>
        </div>
        <h2><?= hb_h($calendar['month_label']) ?> · <?= hb_h((string) $currentProperty['name']) ?></h2>
    </div>

    <div class="cal-grid-wrap">
        <div class="cal-grid">
            <?php foreach ($calendar['weekdays'] as $weekday): ?>
                <div class="cal-weekday"><?= hb_h(trim($weekday)) ?></div>
            <?php endforeach; ?>

            <?php foreach ($calendar['weeks'] as $week): ?>
                <?php foreach ($week as $day): ?>
                    <div class="cal-day<?= !$day['in_month'] ? ' is-outside' : '' ?><?= $day['is_today'] ? ' is-today' : '' ?>">
                        <div class="cal-day__num"><?= (int) $day['day'] ?></div>
                        <?php foreach ($day['bookings'] as $booking): ?>
                            <a class="cal-booking" href="<?= hb_h(hb_url('bookings/edit.php?id=' . (int) $booking['id'])) ?>" title="<?= hb_h((string) $booking['guest_name']) ?>">
                                <?= hb_h((string) $booking['guest_name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="cal-legend">
        <span class="cal-legend__item">
            <span class="cal-legend__swatch"></span>
            <?= hb_h(hb_t('calendar.occupied')) ?>
        </span>
    </div>
</div>
<?php
require dirname(__DIR__) . '/partials/footer.php';
