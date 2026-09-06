<?php
declare(strict_types=1);

/**
 * Szervezők statisztikája — ugyanaz a nézet, mint a Stat oldal, szervezőkre szűrhetően.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_edit_stats.php';
require_once __DIR__ . '/lib/admin_stats.php';
requireLogin();

$db = getDb();
events_view_tracking_ensure_bot_column($db);

$organizerOptions = events_admin_stats_organizer_options($db);
$selectedOrganizerIds = events_admin_stats_organizer_ids_from_request();
$validIds = [];
foreach ($organizerOptions as $opt) {
    $validIds[(int) $opt['id']] = true;
}
$selectedOrganizerIds = array_values(array_filter(
    $selectedOrganizerIds,
    static fn (int $id): bool => isset($validIds[$id])
));

$statsParams = events_edit_stats_params_from_request($_GET);
if ($selectedOrganizerIds === []) {
    $statsAllDateFrom = events_edit_stats_earliest_view_date_all($db);
    $statsData = events_edit_stats_for_all_events($db, $statsParams);
} else {
    $statsAllDateFrom = events_edit_stats_earliest_view_date_for_organizers($db, $selectedOrganizerIds);
    $statsData = events_edit_stats_for_organizers($db, $selectedOrganizerIds, $statsParams);
}
$statsEventRows = $statsData['event_rows'] ?? [];
$statsPreferPartnerLinks = false;
$statsShowEventRowActions = true;
$statsFilterExtraQuery = $selectedOrganizerIds !== [] ? ['org_id' => $selectedOrganizerIds] : [];
$statsEventDetailUrl = static function (array $row): ?string {
    $id = (int) ($row['id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    return events_url('szerkeszt.php?id=') . $id;
};

$selectedCount = count($selectedOrganizerIds);
$totalOrgCount = count($organizerOptions);
$pickerSummary = $selectedCount === 0
    ? 'Összes szervező (nincs szűrés)'
    : ($selectedCount . ' / ' . $totalOrgCount . ' szervező');

ob_start();
?>
<details class="events-stats-org-picker"<?= $selectedCount > 0 ? ' open' : '' ?>>
    <summary class="events-stats-org-picker__summary">
        Szervezők: <strong><?= h($pickerSummary) ?></strong>
    </summary>
    <div class="events-stats-org-picker__body">
        <p class="events-stats-org-picker__hint">
            Több szervezőt is bejelölhetsz. Ha egyik sincs kiválasztva, az összes eseményre vonatkozik a stat (mint a Stat oldalon).
        </p>
        <div class="events-stats-org-picker__toolbar">
            <button type="button" class="btn btn-sm btn-secondary" data-org-picker-all>Összes be</button>
            <button type="button" class="btn btn-sm btn-secondary" data-org-picker-none>Összes ki</button>
            <input type="search" class="events-filter-input events-stats-org-picker__search" placeholder="Szervező keresése…" autocomplete="off" data-org-picker-search>
        </div>
        <div class="events-stats-org-picker__list" role="group" aria-label="Szervezők">
            <?php foreach ($organizerOptions as $opt): ?>
                <?php
                $oid = (int) $opt['id'];
                $checked = in_array($oid, $selectedOrganizerIds, true);
                $search = mb_strtolower((string) $opt['name'], 'UTF-8');
                ?>
                <label class="events-stats-org-picker__item" data-org-name="<?= h($search) ?>">
                    <input type="checkbox" name="org_id[]" value="<?= $oid ?>"<?= $checked ? ' checked' : '' ?>>
                    <span><?= h((string) $opt['name']) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
</details>
<script>
(function () {
    var root = document.querySelector('.events-stats-org-picker');
    if (!root) return;
    var items = Array.prototype.slice.call(root.querySelectorAll('.events-stats-org-picker__item'));
    var search = root.querySelector('[data-org-picker-search]');
    var allBtn = root.querySelector('[data-org-picker-all]');
    var noneBtn = root.querySelector('[data-org-picker-none]');

    function setAll(checked) {
        items.forEach(function (item) {
            if (item.hidden) return;
            var cb = item.querySelector('input[type="checkbox"]');
            if (cb) cb.checked = checked;
        });
    }

    if (allBtn) allBtn.addEventListener('click', function () { setAll(true); });
    if (noneBtn) noneBtn.addEventListener('click', function () { setAll(false); });
    if (search) {
        search.addEventListener('input', function () {
            var q = (search.value || '').trim().toLowerCase();
            items.forEach(function (item) {
                var name = item.getAttribute('data-org-name') || '';
                item.hidden = q !== '' && name.indexOf(q) === -1;
            });
        });
    }
})();
</script>
<?php
$statsFormExtraHtml = (string) ob_get_clean();

$statsFormAction = events_url('events_szervezok_statisztika.php');
$statsChartDomId = 'events-admin-organizer-stats-chart';
$statsPageTitle = 'Szervezők statisztika';
$statsIntro = $selectedCount === 0
    ? 'Alapból ugyanaz, mint a Stat oldal (összes esemény). Válassz egy vagy több szervezőt a szűréshez.'
    : 'A kiválasztott szervező(k) eseményeire aggregált naptár előnézet, további info kattintás és oldalmegtekintés.';
$statsEventListHint = 'A táblázat a periódusban legtöbb oldalmegtekintéssel rendelkező eseményeket mutatja a kiválasztott szervezőkörben.';
$statsEmptyEventsMessage = 'Nincs megtekintett esemény a választott időszakban / szervezőknél.';

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Szervezők statisztika';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="events-stat-page-head">
    <div>
        <h1 class="events-stat-page-title">Szervezők statisztika</h1>
        <p class="events-stat-page-lead">
            Ugyanaz a nézet, mint a Stat oldalon, több szervezőre szűrhetően.
        </p>
    </div>
    <div class="events-stat-page-actions">
        <a href="<?= h(events_url('events_statisztika.php')) ?>" class="btn btn-secondary btn-sm">Összes stat</a>
        <a href="<?= h(events_url('events_lista_stat.php')) ?>" class="btn btn-secondary btn-sm">Lista stat</a>
        <a href="<?= h(events_url('events_realtime.php')) ?>" class="btn btn-secondary btn-sm">Valós idejű</a>
    </div>
</div>

<?php require __DIR__ . '/organizers/partials/dashboard_stats.php'; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
