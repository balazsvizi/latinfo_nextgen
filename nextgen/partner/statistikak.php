<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
partner_require_login();

$db = getDb();
$partnerId = partner_current_id();
$context = partner_portal_current_context($db, $partnerId);
$scope = partner_portal_scope_ids($db, $partnerId, $context);
$organizerIds = $scope['organizer_ids'];

$statsParams = events_edit_stats_params_from_request($_GET);
$statsData = events_edit_stats_for_organizers($db, $organizerIds, $statsParams);
$statsEventRows = $statsData['event_rows'] ?? [];
$draftRows = $statsData['draft_rows'] ?? [];
$statsPreferPartnerLinks = false;
$statsEventDetailUrl = static function (array $row): ?string {
    return partner_portal_event_public_url($row);
};

$pageTitle = 'Statisztikák';
$activeNav = 'stats';
require_once __DIR__ . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="partner-page-head">
    <div>
        <h1 class="partner-page-title">Statisztikák</h1>
        <p class="partner-page-lead">
            Megtekintések a(z) <strong><?= h($context['label']) ?></strong> profil eseményein —
            ugyanaz a nézet, mint az esemény-statisztikáknál, partnerre szűrve.
        </p>
    </div>
</div>

<?php if ($organizerIds === []): ?>
    <div class="card">
        <p class="help">Nincs hozzárendelt szervező, ezért nincs megjeleníthető statisztika.</p>
    </div>
<?php else: ?>
    <?php
    $statsFormAction = partner_url('statistikak.php');
    $statsChartDomId = 'partner-stats-chart';
    require dirname(__DIR__) . '/events/organizers/partials/dashboard_drafts.php';
    require dirname(__DIR__) . '/events/organizers/partials/dashboard_stats.php';
    ?>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
