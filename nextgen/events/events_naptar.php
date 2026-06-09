<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
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
$listViewUrl = events_url('events_admin.php') . ($navBaseParams !== [] ? '?' . http_build_query($navBaseParams) : '');

$filterFormAction = events_url('events_naptar.php');
$filterFormHidden = ['month' => $monthKey];
$filterClearUrl = events_url('events_naptar.php?month=' . rawurlencode($monthKey));
$publicHomePreviewUrl = events_url(events_public_home_page_script() . '?month=' . rawurlencode($monthKey));

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Események – naptár';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card events-admin-card--calendar">
    <form method="get" action="<?= h($filterFormAction) ?>" class="events-admin-form events-cal-page" id="events-calendar-filter-form">
        <div class="events-list-head events-cal-page__head">
            <h2 class="events-list-title">Események</h2>
            <div class="events-list-actions">
                <a href="<?= h($filterClearUrl) ?>" class="btn btn-secondary btn-sm">Szűrők törlése</a>
                <a href="<?= h(events_url('letrehoz.php')) ?>" class="btn btn-primary btn-sm">Új esemény</a>
                <a href="<?= h($publicHomePreviewUrl) ?>" class="events-icon-action events-edit-preview-action" title="Naptár főoldal megtekintése (új lap)" aria-label="Naptár főoldal megtekintése új lapon" target="_blank" rel="noopener">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
                </a>
            </div>
        </div>

        <?php require __DIR__ . '/partials/admin_event_filters.php'; ?>

        <div class="events-cal-toolbar" aria-label="Naptár vezérlők">
            <div class="events-cal-toolbar__left">
                <div class="events-cal-toolbar__nav" aria-label="Hónap választás">
                    <a class="events-cal-toolbar__arrow" href="<?= h($prevMonthUrl) ?>" rel="prev" aria-label="Előző hónap">‹</a>
                    <a class="events-cal-toolbar__today" href="<?= h($todayMonthUrl) ?>">Ez a hónap</a>
                    <a class="events-cal-toolbar__arrow" href="<?= h($nextMonthUrl) ?>" rel="next" aria-label="Következő hónap">›</a>
                </div>
                <h3 class="events-cal-toolbar__month"><?= h($monthLabel) ?></h3>
            </div>
            <nav class="events-cal-view-switch" aria-label="Nézet választó">
                <a class="events-cal-view-switch__item" href="<?= h($listViewUrl) ?>">Lista</a>
                <span class="events-cal-view-switch__item is-active" aria-current="page">Hónap</span>
            </nav>
        </div>
        <p class="events-cal-legend" aria-label="Naptár jelmagyarázat">
            <span class="events-cal-legend__item events-cal-legend__item--published">Közzétéve</span>
            <span class="events-cal-legend__item events-cal-legend__item--unpublished">Nem közzétett</span>
        </p>

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
                                            $isPublished = events_admin_calendar_event_is_published($ev);
                                            $timeLabel = events_admin_calendar_event_time_label($ev);
                                            $eventStyle = events_admin_calendar_event_block_style($categoriesByEventId, $eid, $isPublished);
                                            $eventUrl = events_admin_calendar_event_public_url($ev);
                                            $eventStatus = (string) ($ev['event_status'] ?? '');
                                            $linkClass = 'events-cal__event-link' . ($isPublished ? '' : ' events-cal__event-link--unpublished');
                                            $statusBadgeClass = events_post_status_badge_class($eventStatus);
                                            $statusLabel = events_post_status_label($eventStatus);
                                            $linkTarget = $isPublished ? '_blank' : '_self';
                                            ?>
                                            <li class="events-cal__event<?= $isPublished ? '' : ' events-cal__event--unpublished' ?>" role="listitem">
                                                <a
                                                    class="<?= h($linkClass) ?>"
                                                    style="<?= h($eventStyle) ?>"
                                                    href="<?= h($eventUrl) ?>"
                                                    target="<?= h($linkTarget) ?>"
                                                    <?= $isPublished ? 'rel="noopener"' : '' ?>
                                                    title="<?= h((string) ($ev['event_name'] ?? '')) ?><?= $isPublished ? '' : ' (' . $statusLabel . ')' ?>"
                                                >
                                                    <?php if (!$isPublished): ?>
                                                        <span class="events-cal__event-status event-status-badge <?= h($statusBadgeClass) ?>"><?= h($statusLabel) ?></span>
                                                    <?php endif; ?>
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
                        $isPublished = events_admin_calendar_event_is_published($ev);
                        $eventStyle = events_admin_calendar_event_block_style($categoriesByEventId, $eid, $isPublished);
                        $eventUrl = events_admin_calendar_event_public_url($ev);
                        $eventStatus = (string) ($ev['event_status'] ?? '');
                        $linkClass = 'events-cal-undated__link events-cal__event-link' . ($isPublished ? '' : ' events-cal__event-link--unpublished');
                        $statusBadgeClass = events_post_status_badge_class($eventStatus);
                        $statusLabel = events_post_status_label($eventStatus);
                        $linkTarget = $isPublished ? '_blank' : '_self';
                        ?>
                        <li role="listitem"<?= $isPublished ? '' : ' class="events-cal__event--unpublished"' ?>>
                            <a class="<?= h($linkClass) ?>" style="<?= h($eventStyle) ?>" href="<?= h($eventUrl) ?>" target="<?= h($linkTarget) ?>" <?= $isPublished ? 'rel="noopener"' : '' ?>>
                                <?php if (!$isPublished): ?>
                                    <span class="events-cal__event-status event-status-badge <?= h($statusBadgeClass) ?>"><?= h($statusLabel) ?></span>
                                <?php endif; ?>
                                <span class="events-cal__event-name"><?= h((string) ($ev['event_name'] ?? '')) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </form>
</div>
<?php require __DIR__ . '/partials/admin_event_filters_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
