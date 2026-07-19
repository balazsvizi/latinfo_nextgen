<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
partner_require_login();

$db = getDb();
$partnerId = partner_current_id();
$organizerId = (int) ($_GET['id'] ?? 0);
partner_require_organizer_access($db, $organizerId);

partner_portal_set_context('o:' . $organizerId);

$organizer = partner_organizer_summary($db, $organizerId);
if ($organizer === null) {
    flash('error', 'A szervező nem található.');
    redirect(partner_url('szervezok.php'));
}

$statsParams = events_edit_stats_params_from_request($_GET);
$statsData = events_edit_stats_for_organizer($db, $organizerId, $statsParams);
$statsEventRows = $statsData['event_rows'] ?? [];
$draftRows = $statsData['draft_rows'] ?? [];
$statsPreferPartnerLinks = true;
$statsEventDetailUrl = static function (array $row): ?string {
    $id = (int) ($row['id'] ?? 0);

    return $id > 0 ? partner_portal_event_detail_url($id) : null;
};

$orgName = (string) ($organizer['name'] ?? '');
$publicUrl = events_url('organizer.php?id=') . $organizerId;
$pageTitle = 'Szervező: ' . $orgName;
$activeNav = 'organizers';

require_once __DIR__ . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<p class="toolbar partner-toolbar">
    <a href="<?= h(partner_url('szervezok.php')) ?>" class="btn btn-secondary">← Szervezők</a>
    <a href="<?= h(partner_url('esemenyek.php')) ?>" class="btn btn-secondary">Eseménylista</a>
    <a href="<?= h($publicUrl) ?>" class="btn btn-secondary" target="_blank" rel="noopener">Nyilvános oldal ↗</a>
</p>

<div class="card szervezo-profile-card">
    <h1 class="card-title"><?= h($orgName) ?></h1>
    <p class="help">Szervezői dashboard – események és megtekintési statisztikák. Az eseményekre kattintva a partner részletek nyílnak meg.</p>
</div>

<div class="dash-cards szervezo-dash-cards">
    <div class="dash-card szervezo-dash-card">
        <h3>Összes esemény</h3>
        <div class="num"><?= (int) ($organizer['event_count'] ?? 0) ?></div>
    </div>
    <div class="dash-card szervezo-dash-card">
        <h3>Közzétett</h3>
        <div class="num"><?= (int) ($organizer['published_count'] ?? 0) ?></div>
    </div>
    <div class="dash-card szervezo-dash-card">
        <h3>Közelgő</h3>
        <div class="num"><?= (int) ($organizer['upcoming_count'] ?? 0) ?></div>
    </div>
    <div class="dash-card szervezo-dash-card">
        <h3>Következő</h3>
        <div class="num szervezo-dash-card__date"><?= h(events_organizers_admin_format_datetime(isset($organizer['next_event_at']) ? (string) $organizer['next_event_at'] : null)) ?></div>
    </div>
</div>

<?php
$statsFormAction = partner_url('szervezo.php?id=') . $organizerId;
$statsChartDomId = 'partner-organizer-stats-chart';
require dirname(__DIR__) . '/events/organizers/partials/dashboard_drafts.php';
require dirname(__DIR__) . '/events/organizers/partials/dashboard_stats.php';
?>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
