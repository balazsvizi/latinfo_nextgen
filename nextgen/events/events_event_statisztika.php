<?php
declare(strict_types=1);

/**
 * Egy esemény statisztikája — ugyanaz a nézet, mint az összesített Stat oldalon.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_edit_stats.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Hiányzó esemény azonosító.');
    redirect(events_url('events_statisztika.php'));
}

$db = getDb();
events_view_tracking_ensure_bot_column($db);

$eventStmt = $db->prepare('
    SELECT `id`, `event_name`, `event_slug`, `event_status`, `event_start`, `event_end`
    FROM `events_calendar_events`
    WHERE `id` = ?
    LIMIT 1
');
$eventStmt->execute([$id]);
$event = $eventStmt->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    flash('error', 'Az esemény nem található.');
    redirect(events_url('events_statisztika.php'));
}

$statsParams = events_edit_stats_params_from_request($_GET);
$statsAllDateFrom = events_edit_stats_earliest_view_date_for_event($db, $id);
$statsData = events_edit_stats_for_event($db, $id, $statsParams);
$statsData['totals']['events_in_period'] = 1;
$hasAnyMetric = (
    (int) ($statsData['totals']['page_views'] ?? 0)
    + (int) ($statsData['totals']['calendar_previews'] ?? 0)
    + (int) ($statsData['totals']['external_info_clicks'] ?? 0)
) > 0;
$statsData['totals']['events_opened'] = $hasAnyMetric ? 1 : 0;
$statsData['totals']['events_with_views'] = $statsData['totals']['events_opened'];
$statsEventRows = [];
$statsPreferPartnerLinks = false;
$statsShowEventRowActions = false;
$statsHideEventsList = true;
$statsFilterExtraQuery = ['id' => $id];
$statsEventDetailUrl = static function (array $row) use ($id): ?string {
    return events_url('szerkeszt.php?id=') . $id;
};

$statsFormAction = events_url('events_event_statisztika.php');
$statsChartDomId = 'events-admin-event-stats-chart';
$eventName = (string) ($event['event_name'] ?? '');
$statsPageTitle = 'Esemény statisztika';
$statsIntro = 'Ugyanaz a nézet, mint az összesített Stat oldalon, csak erre az eseményre: '
    . $eventName . '.';
$statsEventListHint = '';
$statsEmptyEventsMessage = '';

$publicUrl = null;
if ((string) ($event['event_status'] ?? '') === events_public_post_status()) {
    $slug = trim((string) ($event['event_slug'] ?? ''));
    if ($slug !== '') {
        $publicUrl = events_megjelenit_url($slug);
    }
}
$editUrl = events_url('szerkeszt.php?id=') . $id;

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Esemény statisztika';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="events-stat-page-head">
    <div>
        <h1 class="events-stat-page-title">Esemény statisztika</h1>
        <p class="events-stat-page-lead">
            <strong><?= h($eventName) ?></strong>
            <?php if (!empty($event['event_start'])): ?>
                · <?= h((string) $event['event_start']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="events-stat-page-actions">
        <a href="<?= h(events_url('events_statisztika.php')) ?>" class="btn btn-secondary btn-sm">Összes stat</a>
        <a href="<?= h($editUrl) ?>" class="btn btn-secondary btn-sm">Szerkesztés</a>
        <?php if ($publicUrl !== null): ?>
            <a href="<?= h($publicUrl) ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">Megtekintés</a>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/organizers/partials/dashboard_stats.php'; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
