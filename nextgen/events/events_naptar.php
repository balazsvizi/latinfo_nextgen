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
$undated = $bucket['undated'];
$gridDays = events_admin_calendar_grid_days($monthFirst, $monthLast);
$calendarWeeks = events_admin_calendar_build_week_layouts($rows, $gridDays, $monthFirst, $monthLast);
$weekdayHeaders = events_admin_calendar_weekday_headers();

$monthsWithEvents = events_admin_calendar_months_with_events($rows);
if (!in_array($monthKey, $monthsWithEvents, true)) {
    $monthsWithEvents[] = $monthKey;
    sort($monthsWithEvents, SORT_STRING);
}
$monthSelectOptions = $monthsWithEvents;
$prevMonthKey = events_admin_calendar_step_month_key($monthKey, $monthsWithEvents, -1);
$nextMonthKey = events_admin_calendar_step_month_key($monthKey, $monthsWithEvents, 1);

$navBaseParams = $filters['get_params'];
$prevMonthUrl = events_admin_calendar_month_url($prevMonthKey, $navBaseParams);
$nextMonthUrl = events_admin_calendar_month_url($nextMonthKey, $navBaseParams);
$listViewUrl = events_admin_list_view_url($navBaseParams);
$calendarViewUrl = events_admin_calendar_view_url($monthKey, $navBaseParams);
$mapViewUrl = events_admin_map_view_url($navBaseParams);
$activeView = 'month';
$filtersActive = events_admin_filters_are_active($filters);

$filterFormAction = events_url('events_naptar.php');
$filterFormHidden = [];
$filterClearUrl = events_url('events_naptar.php?month=' . rawurlencode($monthKey));
$publicHomePreviewUrl = events_public_home_url('hu', ['month' => $monthKey]);

$adminFloatTools = [
    [
        'href' => events_url('letrehoz.php'),
        'title' => 'Új esemény',
        'aria' => 'Új esemény létrehozása',
        'icon' => 'plus',
    ],
    [
        'href' => $listViewUrl,
        'title' => 'Eseménylista',
        'aria' => 'Átváltás lista nézetre',
        'icon' => 'list',
    ],
    [
        'href' => $publicHomePreviewUrl,
        'title' => 'Nyilvános naptár megtekintése',
        'aria' => 'Nyilvános naptár megtekintése',
        'icon' => 'eye',
    ],
];
$adminFloatToolsRequireLogin = false;

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Események – naptár';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<?php require __DIR__ . '/partials/admin_float_tools.php'; ?>

<div class="card events-admin-card events-admin-card--calendar">
    <form method="get" action="<?= h($filterFormAction) ?>" class="events-admin-form events-cal-page" id="events-calendar-filter-form">
        <details class="events-cal-filters-panel" id="events-cal-filters-panel"<?= $filtersActive ? ' open' : '' ?>>
            <summary class="events-cal-filters-panel__summary">
                <span class="events-cal-filters-panel__summary-text">Keresés</span>
                <?php if ($filtersActive): ?>
                    <span class="events-cal-filters-panel__meta">
                        <span class="events-cal-filters-panel__badge">Aktív</span>
                        <a href="<?= h($filterClearUrl) ?>" class="events-cal-filters-panel__clear" onclick="event.stopPropagation();">Szűrők törlése</a>
                    </span>
                <?php endif; ?>
            </summary>
            <div class="events-cal-filters-panel__body">
                <?php require __DIR__ . '/partials/admin_event_filters.php'; ?>
            </div>
        </details>

        <div class="events-list-head events-cal-page__head">
            <div class="events-cal-page__head-start">
                <?php require __DIR__ . '/partials/admin_event_view_switch.php'; ?>
                <div class="events-cal-month-switch" aria-label="Hónap választás">
                    <a class="events-cal-toolbar__arrow" href="<?= h($prevMonthUrl) ?>" rel="prev" aria-label="Előző hónap">‹</a>
                    <div class="events-filter-select-wrap events-cal-month-switch__wrap">
                        <label class="visually-hidden" for="ev-cal-month">Hónap</label>
                        <select class="events-filter-select events-cal-month-switch__select" name="month" id="ev-cal-month" title="Hónap">
                            <?php foreach ($monthSelectOptions as $optKey): ?>
                                <?php
                                try {
                                    $optLabel = events_admin_calendar_month_label(new DateTimeImmutable($optKey . '-01'));
                                } catch (Throwable) {
                                    $optLabel = $optKey;
                                }
                                ?>
                                <option value="<?= h($optKey) ?>" <?= $optKey === $monthKey ? 'selected' : '' ?>><?= h($optLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <a class="events-cal-toolbar__arrow" href="<?= h($nextMonthUrl) ?>" rel="next" aria-label="Következő hónap">›</a>
                </div>
                <h2 class="events-list-title">Események</h2>
            </div>
            <div class="events-list-actions">
                <a href="<?= h($filterClearUrl) ?>" class="btn btn-secondary btn-sm">Szűrők törlése</a>
                <a href="<?= h(events_url('letrehoz.php')) ?>" class="btn btn-primary btn-sm">Új esemény</a>
                <a href="<?= h($publicHomePreviewUrl) ?>" class="events-icon-action events-edit-preview-action" title="Naptár főoldal megtekintése (új lap)" aria-label="Naptár főoldal megtekintése új lapon" target="_blank" rel="noopener">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
                </a>
            </div>
        </div>

        <p class="events-cal-legend" aria-label="Naptár jelmagyarázat">
            <span class="events-cal-legend__item events-cal-legend__item--published">Közzétéve</span>
            <span class="events-cal-legend__item events-cal-legend__item--unpublished">Nem közzétett</span>
        </p>

        <?php
        $calendarPublicPreview = false;
        $D = ['calendar_grid_aria' => $monthLabel . ' naptár'];
        require __DIR__ . '/partials/calendar_month_grid.php';
        ?>

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
