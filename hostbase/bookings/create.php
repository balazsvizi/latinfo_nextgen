<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

hb_require_login();

$db = hb_get_db();
hb_refresh_session_from_db($db);

$subscriberId = hb_subscriber_id();
$userId = hb_user_id();
$properties = HbPropertyRepository::listForSubscriber($db, $subscriberId);
$currentProperty = HbPropertyRepository::ensureCurrentProperty($db, $subscriberId);

$prefillPropertyId = hb_get_int('property_id');
if ($prefillPropertyId <= 0 && $currentProperty !== null) {
    $prefillPropertyId = (int) $currentProperty['id'];
}

$selectedProperty = null;
foreach ($properties as $property) {
    if ((int) $property['id'] === $prefillPropertyId) {
        $selectedProperty = $property;
        break;
    }
}
if ($selectedProperty === null && $properties !== []) {
    $selectedProperty = $properties[0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = HbBookingService::create($db, $subscriberId, $userId, [
        'property_id' => hb_post_int('property_id'),
        'guest_name' => hb_post_string('guest_name'),
        'adults' => hb_post_int('adults', 1),
        'children' => hb_post_int('children', 0),
        'check_in' => hb_post_string('check_in'),
        'check_out' => hb_post_string('check_out'),
        'notes' => hb_post_string('notes'),
    ]);

    if ($result['ok']) {
        hb_flash('success', hb_t('bookings.created'));
        if (!empty($result['warning'])) {
            hb_flash('warning', (string) $result['warning']);
        }
        hb_redirect(hb_url('bookings/edit.php?id=' . (int) ($result['id'] ?? 0)));
    }

    hb_flash('error', (string) ($result['error'] ?? hb_t('error.generic')));
}

$pageTitle = hb_t('bookings.new');
$activeNav = 'bookings';
$isEdit = false;
$booking = null;
$formAction = hb_url('bookings/create.php');
$showPropertySwitcher = false;

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="hb-page-head">
    <h1><?= hb_h(hb_t('bookings.new')) ?></h1>
</div>

<?php if ($msg = hb_flash('error')): ?><p class="alert alert-error"><?= hb_h($msg) ?></p><?php endif; ?>

<?php if ($properties === []): ?>
    <div class="card"><p class="help"><?= hb_h(hb_t('error.not_found')) ?></p></div>
<?php else: ?>
    <?php require __DIR__ . '/partials/form.php'; ?>
<?php endif; ?>
<?php
require dirname(__DIR__) . '/partials/footer.php';
