<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

hb_require_login();

$db = hb_get_db();
hb_refresh_session_from_db($db);

$propertyId = hb_get_int('id');
$property = HbPropertyRepository::findForSubscriber($db, $propertyId, hb_subscriber_id());

if ($property === null) {
    hb_flash('error', hb_t('error.not_found'));
    hb_redirect(hb_url('properties/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = HbPropertyRepository::update($db, $propertyId, hb_subscriber_id(), [
        'name' => hb_post_string('name'),
        'city' => hb_post_string('city'),
        'address' => hb_post_string('address'),
        'check_in_time' => hb_post_string('check_in_time'),
        'check_out_time' => hb_post_string('check_out_time'),
        'max_guests' => hb_post_int('max_guests', (int) $property['max_guests']),
    ], hb_user_id());

    if ($ok) {
        hb_flash('success', hb_t('property.saved'));
        hb_redirect(hb_url('properties/edit.php?id=' . $propertyId));
    }

    hb_flash('error', hb_t('error.generic'));
    $property = array_merge($property, [
        'name' => hb_post_string('name'),
        'city' => hb_post_string('city'),
        'address' => hb_post_string('address'),
        'check_in_time' => hb_post_string('check_in_time') . ':00',
        'check_out_time' => hb_post_string('check_out_time') . ':00',
        'max_guests' => hb_post_int('max_guests'),
    ]);
}

$pageTitle = hb_t('property.edit');
$activeNav = 'properties';
$showPropertySwitcher = false;

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="hb-page-head">
    <h1><?= hb_h(hb_t('property.edit')) ?></h1>
    <a href="<?= hb_h(hb_url('properties/index.php')) ?>" class="btn btn-secondary"><?= hb_h(hb_t('common.back')) ?></a>
</div>

<?php if ($msg = hb_flash('success')): ?><p class="alert alert-success"><?= hb_h($msg) ?></p><?php endif; ?>
<?php if ($msg = hb_flash('error')): ?><p class="alert alert-error"><?= hb_h($msg) ?></p><?php endif; ?>

<form method="post" class="card">
    <div class="form-group">
        <label for="name"><?= hb_h(hb_t('property.name')) ?></label>
        <input type="text" id="name" name="name" required maxlength="255" value="<?= hb_h((string) $property['name']) ?>">
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="city"><?= hb_h(hb_t('property.city')) ?></label>
            <input type="text" id="city" name="city" maxlength="255" value="<?= hb_h((string) ($property['city'] ?? '')) ?>">
        </div>
        <div class="form-group">
            <label for="address"><?= hb_h(hb_t('property.address')) ?></label>
            <input type="text" id="address" name="address" maxlength="500" value="<?= hb_h((string) ($property['address'] ?? '')) ?>">
        </div>
    </div>
    <div class="form-row form-row--3">
        <div class="form-group">
            <label for="check_in_time"><?= hb_h(hb_t('property.check_in')) ?></label>
            <input type="time" id="check_in_time" name="check_in_time" required value="<?= hb_h(hb_format_time((string) $property['check_in_time'])) ?>">
        </div>
        <div class="form-group">
            <label for="check_out_time"><?= hb_h(hb_t('property.check_out')) ?></label>
            <input type="time" id="check_out_time" name="check_out_time" required value="<?= hb_h(hb_format_time((string) $property['check_out_time'])) ?>">
        </div>
        <div class="form-group">
            <label for="max_guests"><?= hb_h(hb_t('property.max_guests')) ?></label>
            <input type="number" id="max_guests" name="max_guests" min="1" max="99" required value="<?= (int) $property['max_guests'] ?>">
        </div>
    </div>
    <div class="form-group">
        <label><?= hb_h(hb_t('property.unit')) ?></label>
        <input type="text" readonly disabled value="<?= hb_h((string) $property['unit_name']) ?>">
    </div>
    <button type="submit" class="btn btn-primary"><?= hb_h(hb_t('property.save')) ?></button>
</form>
<?php
require dirname(__DIR__) . '/partials/footer.php';
