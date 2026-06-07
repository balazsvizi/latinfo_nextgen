<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
require_once __DIR__ . '/lib/event_request.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
require_once __DIR__ . '/lib/admin_event_calendar.php';
requireLogin();

$db = getDb();
$filters = events_admin_filters_from_request($db);

[$monthFirst, $monthLast, $monthKey] = events_admin_calendar_resolve_month((string) ($_GET['month'] ?? ''));
$prevMonthKey = $monthFirst->modify('-1 month')->format('Y-m');
$nextMonthKey = $monthFirst->modify('+1 month')->format('Y-m');
$monthLabel = events_admin_calendar_month_label($monthFirst);

$whereSql = $filters['where'] !== [] ? 'WHERE ' . implode(' AND ', $filters['where']) : '';
$sql = "
    SELECT e.*
    FROM `events_calendar_events` e
    {$whereSql}
    ORDER BY e.event_start IS NULL, e.event_start ASC, e.event_name ASC
";
$stmt = $db->prepare($sql);
$stmt->execute($filters['params']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categoriesByEventId = [];
if ($rows !== []) {
    $eventIds = array_values(array_unique(array_map(static fn (array $r): int => (int) $r['id'], $rows)));
    $ph = implode(',', array_fill(0, count($eventIds), '?'));
    $catStmt = $db->prepare("
        SELECT ec.`event_id`, c.`id`, c.`name`, c.`color`
        FROM `events_calendar_event_categories` ec
        INNER JOIN `events_categories` c ON c.`id` = ec.`category_id`
        WHERE ec.`event_id` IN ({$ph})
        ORDER BY c.`sort_order` ASC, c.`name` ASC, c.`id` ASC
    ");
    $catStmt->execute($eventIds);
    foreach ($catStmt->fetchAll(PDO::FETCH_ASSOC) as $catRow) {
        $eid = (int) $catRow['event_id'];
        if (!isset($categoriesByEventId[$eid])) {
            $categoriesByEventId[$eid] = [];
        }
        $categoriesByEventId[$eid][] = [
            'id' => (int) $catRow['id'],
            'name' => (string) $catRow['name'],
            'color' => trim((string) ($catRow['color'] ?? '')) !== '' ? trim((string) $catRow['color']) : '#6d8f63',
        ];
    }
}

$bucket = events_admin_calendar_bucket_events($rows, $monthFirst, $monthLast);
$byDay = $bucket['byDay'];
$undated = $bucket['undated'];
$gridDays = events_admin_calendar_grid_days($monthFirst, $monthLast);
$weekdayHeaders = events_admin_calendar_weekday_headers();

$navBaseParams = $filters['get_params'];
$prevMonthUrl = events_admin_calendar_month_url($prevMonthKey, $navBaseParams);
$nextMonthUrl = events_admin_calendar_month_url($nextMonthKey, $navBaseParams);
$todayMonthUrl = events_admin_calendar_month_url((new DateTimeImmutable('today'))->format('Y-m'), $navBaseParams);
$editBase = events_url('szerkeszt.php?id=');

$filterFormAction = events_url('events_naptar.php');
$filterFormHidden = ['month' => $monthKey];
$filterClearUrl = events_url('events_naptar.php?month=' . rawurlencode($monthKey));

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Események – naptár';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card">
    <form method="get" action="<?= h($filterFormAction) ?>" class="events-admin-form" id="events-calendar-filter-form">
        <div class="events-list-head">
            <h2 class="events-list-title">Események – naptár</h2>
            <div class="events-list-actions">
                <a href="<?= h($filterClearUrl) ?>" class="btn btn-secondary">Szűrők törlése</a>
                <a href="<?= h(events_url('events_admin.php') . ($navBaseParams !== [] ? '?' . http_build_query($navBaseParams) : '')) ?>" class="btn btn-secondary">Lista nézet</a>
                <a href="<?= h(events_url('letrehoz.php')) ?>" class="btn btn-primary">Új esemény</a>
            </div>
        </div>

        <?php require __DIR__ . '/partials/admin_event_filters.php'; ?>

        <div class="events-cal-nav" aria-label="Hónap választás">
            <a class="btn btn-secondary btn-sm events-cal-nav__btn" href="<?= h($prevMonthUrl) ?>" rel="prev">← Előző hónap</a>
            <div class="events-cal-nav__title-wrap">
                <h3 class="events-cal-nav__title"><?= h($monthLabel) ?></h3>
                <a class="events-cal-nav__today" href="<?= h($todayMonthUrl) ?>">Mai hónap</a>
            </div>
            <a class="btn btn-secondary btn-sm events-cal-nav__btn" href="<?= h($nextMonthUrl) ?>" rel="next">Következő hónap →</a>
        </div>

        <div class="events-cal" role="grid" aria-label="<?= h($monthLabel) ?> naptár">
            <div class="events-cal__weekdays" role="row">
                <?php foreach ($weekdayHeaders as $wd): ?>
                    <div class="events-cal__weekday" role="columnheader"><?= h($wd) ?></div>
                <?php endforeach; ?>
            </div>
            <div class="events-cal__body">
                <?php foreach (array_chunk($gridDays, 7) as $week): ?>
                    <div class="events-cal__week" role="row">
                        <?php foreach ($week as $day): ?>
                            <?php
                            $dayKey = $day['key'];
                            $dayNum = (int) $day['date']->format('j');
                            $dayEvents = $byDay[$dayKey] ?? [];
                            $dayClasses = 'events-cal__day';
                            if (!$day['inMonth']) {
                                $dayClasses .= ' events-cal__day--outside';
                            }
                            if ($day['isToday']) {
                                $dayClasses .= ' events-cal__day--today';
                            }
                            ?>
                            <div class="<?= h($dayClasses) ?>" role="gridcell" aria-label="<?= h($day['date']->format('Y. m. d.')) ?>">
                                <div class="events-cal__day-num"><?= $dayNum ?></div>
                                <?php if ($dayEvents !== []): ?>
                                    <ul class="events-cal__events" role="list">
                                        <?php foreach ($dayEvents as $ev): ?>
                                            <?php
                                            $eid = (int) ($ev['id'] ?? 0);
                                            $timeLabel = events_admin_calendar_event_time_label($ev);
                                            $eventStyle = events_admin_calendar_event_category_style($categoriesByEventId, $eid);
                                            ?>
                                            <li class="events-cal__event" role="listitem">
                                                <a
                                                    class="events-cal__event-link"
                                                    style="<?= h($eventStyle) ?>"
                                                    href="<?= h($editBase . $eid) ?>"
                                                    title="<?= h((string) ($ev['event_name'] ?? '')) ?>"
                                                >
                                                    <?php if ($timeLabel !== ''): ?>
                                                        <span class="events-cal__event-time"><?= h($timeLabel) ?></span>
                                                    <?php endif; ?>
                                                    <span class="events-cal__event-name"><?= h((string) ($ev['event_name'] ?? '')) ?></span>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($undated !== []): ?>
            <section class="events-cal-undated" aria-label="Dátum nélküli események">
                <h3 class="events-cal-undated__title">Dátum nélküli események (<?= count($undated) ?>)</h3>
                <ul class="events-cal-undated__list" role="list">
                    <?php foreach ($undated as $ev): ?>
                        <?php
                        $eid = (int) ($ev['id'] ?? 0);
                        $eventStyle = events_admin_calendar_event_category_style($categoriesByEventId, $eid);
                        ?>
                        <li role="listitem">
                            <a class="events-cal-undated__link events-cal__event-link" style="<?= h($eventStyle) ?>" href="<?= h($editBase . $eid) ?>">
                                <?= h((string) ($ev['event_name'] ?? '')) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </form>
</div>
<?php require __DIR__ . '/partials/admin_event_filters_script.php'; ?>
<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
