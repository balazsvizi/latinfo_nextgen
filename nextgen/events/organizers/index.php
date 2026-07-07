<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

organizers_portal_require_login();

$db = getDb();
$organizerId = organizers_portal_current_organizer_id();
organizers_portal_refresh_session_from_db($db);

$account = organizers_portal_current_account($db);
if ($account === null) {
    flash('error', 'A fiókod nem elérhető. Kérjük, jelentkezz be újra.');
    redirect(organizers_portal_url('login.php'));
}

$organizer = organizers_portal_organizer_summary($db, $organizerId);
if ($organizer === null) {
    flash('error', 'A szervezői profil nem található.');
    organizers_portal_logout();
    redirect(organizers_portal_url('login.php'));
}

$statsParams = events_edit_stats_params_from_request($_GET);
$statsData = events_edit_stats_for_organizer($db, $organizerId, $statsParams);
$statsEventRows = $statsData['event_rows'] ?? [];
$draftRows = $statsData['draft_rows'] ?? [];

$orgName = (string) ($organizer['name'] ?? '');
$publicUrl = events_url('organizer.php?id=') . $organizerId;
$pageTitle = 'Kezdőlap';
$activeNav = 'home';

require_once __DIR__ . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card szervezo-profile-card">
    <h1 class="card-title">Üdvözöllek, <?= h(organizers_portal_session_display_name()) ?>!</h1>
    <div class="szervezo-profile-grid">
        <div class="szervezo-profile-field">
            <span class="szervezo-profile-label">Szervező neve</span>
            <span class="szervezo-profile-value"><?= h($orgName) ?></span>
        </div>
        <div class="szervezo-profile-field">
            <span class="szervezo-profile-label">E-mail</span>
            <span class="szervezo-profile-value"><?= h((string) ($account['email'] ?? '')) ?></span>
        </div>
        <?php if (trim((string) ($account['név'] ?? '')) !== '' && trim((string) ($account['név'] ?? '')) !== $orgName): ?>
        <div class="szervezo-profile-field">
            <span class="szervezo-profile-label">Megjelenített név</span>
            <span class="szervezo-profile-value"><?= h((string) $account['név']) ?></span>
        </div>
        <?php endif; ?>
        <div class="szervezo-profile-field">
            <span class="szervezo-profile-label">Nyilvános oldal</span>
            <span class="szervezo-profile-value">
                <a href="<?= h($publicUrl) ?>" target="_blank" rel="noopener"><?= h($publicUrl) ?></a>
            </span>
        </div>
    </div>
</div>

<div class="dash-cards szervezo-dash-cards">
    <div class="dash-card szervezo-dash-card">
        <h3>Összes esemény</h3>
        <div class="num"><?= (int) ($organizer['event_count'] ?? 0) ?></div>
        <p>Közzétett és vázlat együtt</p>
    </div>
    <div class="dash-card szervezo-dash-card">
        <h3>Közzétett</h3>
        <div class="num"><?= (int) ($organizer['published_count'] ?? 0) ?></div>
        <p>Élőben látható események</p>
    </div>
    <div class="dash-card szervezo-dash-card">
        <h3>Közelgő</h3>
        <div class="num"><?= (int) ($organizer['upcoming_count'] ?? 0) ?></div>
        <p>Még nem lejárt események</p>
    </div>
    <div class="dash-card szervezo-dash-card">
        <h3>Következő esemény</h3>
        <div class="num szervezo-dash-card__date"><?= h(events_organizers_admin_format_datetime(isset($organizer['next_event_at']) ? (string) $organizer['next_event_at'] : null)) ?></div>
        <p>Legközelebbi közzétett dátum</p>
    </div>
</div>

<?php
$statsFormAction = organizers_portal_url('index.php');
$statsChartDomId = 'organizers-portal-dashboard-stats-chart';
require __DIR__ . '/partials/dashboard_drafts.php';
require __DIR__ . '/partials/dashboard_stats.php';
?>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
