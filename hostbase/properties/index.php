<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

hb_require_login();

$db = hb_get_db();
hb_refresh_session_from_db($db);

$properties = HbPropertyRepository::listForSubscriber($db, hb_subscriber_id());

$pageTitle = hb_t('property.list_title');
$activeNav = 'properties';
$showPropertySwitcher = false;

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="hb-page-head">
    <h1><?= hb_h(hb_t('property.list_title')) ?></h1>
</div>

<?php if ($msg = hb_flash('success')): ?><p class="alert alert-success"><?= hb_h($msg) ?></p><?php endif; ?>
<?php if ($msg = hb_flash('error')): ?><p class="alert alert-error"><?= hb_h($msg) ?></p><?php endif; ?>

<div class="property-grid">
    <?php foreach ($properties as $property): ?>
        <div class="property-card">
            <h3><?= hb_h((string) $property['name']) ?></h3>
            <div class="property-card__meta">
                <?php if (!empty($property['city'])): ?>
                    <?= hb_h((string) $property['city']) ?> ·
                <?php endif; ?>
                <?= hb_h(hb_t('property.max_guests')) ?>: <?= (int) $property['max_guests'] ?>
                · Check-in: <?= hb_h(hb_format_time((string) $property['check_in_time'])) ?>
                · Check-out: <?= hb_h(hb_format_time((string) $property['check_out_time'])) ?>
            </div>
            <div class="inline-actions">
                <a href="<?= hb_h(hb_url('properties/edit.php?id=' . (int) $property['id'])) ?>" class="btn btn-secondary"><?= hb_h(hb_t('property.edit')) ?></a>
                <a href="<?= hb_h(hb_url('set_property.php?property_id=' . (int) $property['id'] . '&return=' . rawurlencode(hb_url('calendar/index.php')))) ?>" class="btn btn-secondary"><?= hb_h(hb_t('nav.calendar')) ?></a>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php
require dirname(__DIR__) . '/partials/footer.php';
