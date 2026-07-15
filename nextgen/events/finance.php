<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/finance_admin.php';
requireLogin();

$db = getDb();
$stats = events_finance_dashboard_stats($db);

$pageTitle = 'Event Finance';
$mainContentClass = 'main-content main-content--fullwidth';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card">
    <div class="events-list-head">
        <div class="events-list-head__start">
            <h1 class="events-list-title card-title" style="margin:0;">Event Finance</h1>
        </div>
        <div class="events-list-actions">
            <a href="<?= h(events_url('finance_events.php')) ?>" class="btn btn-primary">Események</a>
        </div>
    </div>

    <div class="dash-cards events-finance-dash-cards">
        <a href="<?= h(events_url('finance_events.php')) ?>" class="dash-card">
            <h3>Események</h3>
            <div class="num"><?= (int) $stats['events_total'] ?></div>
            <p>Összes esemény a naptárban</p>
        </a>
        <a href="<?= h(events_url('finance_events.php?f_has_fee=yes')) ?>" class="dash-card">
            <h3>Szervezői díj megadva</h3>
            <div class="num"><?= (int) $stats['with_fee'] ?></div>
            <p>Események díjjal · összeg: <?= h(events_finance_format_money($stats['fee_sum'])) ?></p>
        </a>
        <a href="<?= h(events_url('finance_events.php?f_has_fee=no')) ?>" class="dash-card">
            <h3>Nincs szervezői díj</h3>
            <div class="num"><?= (int) $stats['without_fee'] ?></div>
            <p>Díj nélküli vagy 0 Ft-os események</p>
        </a>
        <a href="<?= h(events_url('finance_events.php')) ?>" class="dash-card">
            <h3>Ki fizeti kitöltve</h3>
            <div class="num"><?= (int) $stats['with_payer'] ?></div>
            <p>Belépő megadva: <?= (int) $stats['with_cost'] ?> esemény</p>
        </a>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
