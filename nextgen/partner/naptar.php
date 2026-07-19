<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
partner_require_login();

$db = getDb();
$partnerId = partner_current_id();

[$monthFirst, $monthLast, $monthKey] = events_admin_calendar_resolve_month((string) ($_GET['month'] ?? ''));
$monthLabel = events_admin_calendar_month_label($monthFirst);

$rows = partner_portal_calendar_events($db, $monthFirst, $monthLast);
$ownMap = partner_portal_owned_event_id_map($db, $partnerId);
$ownCountInMonth = 0;
foreach ($rows as $r) {
    if (isset($ownMap[(int) ($r['id'] ?? 0)])) {
        $ownCountInMonth++;
    }
}

$eventIds = array_values(array_unique(array_map(static fn (array $r): int => (int) $r['id'], $rows)));
$categoriesByEventId = partner_portal_categories_by_event_ids($db, $eventIds);

$gridDays = events_admin_calendar_grid_days($monthFirst, $monthLast);
$calendarWeeks = events_admin_calendar_build_week_layouts($rows, $gridDays, $monthFirst, $monthLast);
$weekdayHeaders = events_admin_calendar_weekday_headers();

$monthsWithEvents = events_admin_calendar_months_with_events($rows);
if (!in_array($monthKey, $monthsWithEvents, true)) {
    $monthsWithEvents[] = $monthKey;
    sort($monthsWithEvents, SORT_STRING);
}
$prevMonthKey = events_admin_calendar_step_month_key($monthKey, $monthsWithEvents, -1);
$nextMonthKey = events_admin_calendar_step_month_key($monthKey, $monthsWithEvents, 1);

$calendarOwnEventIds = $ownMap;
$calendarEventUrlBuilder = static function (array $ev) use ($ownMap): string {
    $id = (int) ($ev['id'] ?? 0);
    if ($id > 0 && isset($ownMap[$id])) {
        return partner_portal_event_detail_url($id);
    }
    $slug = trim((string) ($ev['event_slug'] ?? ''));
    if ($slug !== '' && events_admin_calendar_event_is_published($ev)) {
        return events_public_canonical_url($slug);
    }

    return partner_url('naptar.php');
};
$calendarPublicPreview = false;
$calendarLang = 'hu';
$D = ['calendar_grid_aria' => $monthLabel];

$pageTitle = 'Naptár';
$activeNav = 'calendar';
require_once __DIR__ . '/partials/header.php';
?>

<div class="partner-page-head">
    <div>
        <h1 class="partner-page-title">Partner naptár</h1>
        <p class="partner-page-lead">
            A teljes közzétett program. A <strong>te eseményeid</strong> kiemelten jelennek meg;
            rájuk kattintva a partner részletek nyílnak meg.
        </p>
    </div>
</div>

<div class="partner-cal-toolbar card">
    <div class="partner-cal-toolbar__nav">
        <a class="btn btn-secondary btn-sm" href="<?= h(partner_portal_month_url($prevMonthKey)) ?>">← Előző</a>
        <h2 class="partner-cal-toolbar__month"><?= h($monthLabel) ?></h2>
        <a class="btn btn-secondary btn-sm" href="<?= h(partner_portal_month_url($nextMonthKey)) ?>">Következő →</a>
    </div>
    <p class="partner-cal-toolbar__meta">
        <?= count($rows) ?> esemény a hónapban ·
        <span class="partner-cal-mine-pill"><?= $ownCountInMonth ?> a tied</span>
    </p>
    <div class="partner-cal-legend">
        <span class="partner-cal-legend__item partner-cal-legend__item--mine">Saját esemény</span>
        <span class="partner-cal-legend__item">Egyéb program</span>
    </div>
</div>

<div class="card partner-cal-wrap partner-cal-wrap--portal">
    <?php require dirname(__DIR__) . '/events/partials/calendar_month_grid.php'; ?>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
