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

$tagsAvailable = $filters['tagsAvailable'];
$tagOptions = $filters['tagOptions'];
$djsAvailable = $filters['djsAvailable'];
$stylesAvailable = $filters['stylesAvailable'];
$get_params = $filters['get_params'];

$allowedOrder = ['id', 'organizer', 'category'];
if ($tagsAvailable) {
    $allowedOrder[] = 'tag';
}
if ($djsAvailable) {
    $allowedOrder[] = 'dj';
}
$allowedOrder = array_merge($allowedOrder, ['name', 'start', 'end', 'status', 'cal_previews', 'views']);
if (isset($_GET['order']) && in_array((string) $_GET['order'], $allowedOrder, true)) {
    $order = (string) $_GET['order'];
    $dir_param = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc';
} else {
    $order = 'id';
    $dir_param = 'desc';
}

$whereSql = $filters['where'] !== [] ? 'WHERE ' . implode(' AND ', $filters['where']) : '';
$params = $filters['params'];

$dirSql = $dir_param === 'asc' ? 'ASC' : 'DESC';
$orderSql = match ($order) {
    'id' => "e.id $dirSql",
    'organizer' => "( (SELECT MIN(o.name) FROM `events_calendar_event_organizers` eo INNER JOIN `events_organizers` o ON o.id = eo.organizer_id WHERE eo.event_id = e.id) IS NULL) ASC, (SELECT MIN(o.name) FROM `events_calendar_event_organizers` eo INNER JOIN `events_organizers` o ON o.id = eo.organizer_id WHERE eo.event_id = e.id) $dirSql",
    'category' => "( (SELECT MIN(c.name) FROM `events_calendar_event_categories` ec INNER JOIN `events_categories` c ON c.id = ec.category_id WHERE ec.event_id = e.id) IS NULL) ASC, (SELECT MIN(c.name) FROM `events_calendar_event_categories` ec INNER JOIN `events_categories` c ON c.id = ec.category_id WHERE ec.event_id = e.id) $dirSql",
    'tag' => "( (SELECT MIN(t.name) FROM `events_calendar_event_tags` et INNER JOIN `events_tags` t ON t.id = et.tag_id WHERE et.event_id = e.id) IS NULL) ASC, (SELECT MIN(t.name) FROM `events_calendar_event_tags` et INNER JOIN `events_tags` t ON t.id = et.tag_id WHERE et.event_id = e.id) $dirSql",
    'dj' => "( (SELECT MIN(tdj.name) FROM `events_calendar_event_tags` etdj INNER JOIN `events_tag_type_links` ttdj ON ttdj.tag_id = etdj.tag_id INNER JOIN `events_tag_types` tydj ON tydj.id = ttdj.tag_type_id AND tydj.code = 'dj' INNER JOIN `events_tags` tdj ON tdj.id = etdj.tag_id WHERE etdj.event_id = e.id) IS NULL) ASC, (SELECT MIN(tdj.name) FROM `events_calendar_event_tags` etdj INNER JOIN `events_tag_type_links` ttdj ON ttdj.tag_id = etdj.tag_id INNER JOIN `events_tag_types` tydj ON tydj.id = ttdj.tag_type_id AND tydj.code = 'dj' INNER JOIN `events_tags` tdj ON tdj.id = etdj.tag_id WHERE etdj.event_id = e.id) $dirSql",
    'name' => "e.event_name $dirSql",
    'start' => "e.event_start IS NULL, e.event_start $dirSql",
    'end' => "e.event_end IS NULL, e.event_end $dirSql",
    'status' => "e.event_status $dirSql",
    'cal_previews' => "naptar_elonezetek $dirSql",
    'views' => "megtekintesek $dirSql",
    default => 'e.id DESC',
};

$limitSql = $filters['show_all'] ? '' : ' LIMIT ' . EVENTS_ADMIN_LIST_DEFAULT_LIMIT;

$sql = "
    SELECT e.*,
        (SELECT GROUP_CONCAT(o.name ORDER BY eo.sort_order ASC, o.name ASC SEPARATOR ', ')
         FROM `events_calendar_event_organizers` eo
         INNER JOIN `events_organizers` o ON o.id = eo.organizer_id
         WHERE eo.event_id = e.id) AS organizer_name,
        (SELECT COUNT(*) FROM `events_calendar_event_views` m WHERE m.`esemény_id` = e.id AND m.`metric_type` = 'page_view') AS megtekintesek,
        (SELECT COUNT(*) FROM `events_calendar_event_views` m WHERE m.`esemény_id` = e.id AND m.`metric_type` = 'calendar_preview') AS naptar_elonezetek
    FROM `events_calendar_events` e
    $whereSql
    ORDER BY $orderSql
    $limitSql
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categoriesByEventId = [];
$tagsByEventId = [];
$djsByEventId = [];
$mainStylesByEventId = [];
$supplementaryStylesByEventId = [];
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
    if ($tagsAvailable) {
        $tagStmt = $db->prepare("
            SELECT et.`event_id`, t.`id`, t.`name`
            FROM `events_calendar_event_tags` et
            INNER JOIN `events_tags` t ON t.`id` = et.`tag_id`
            WHERE et.`event_id` IN ({$ph})
            ORDER BY t.`name` ASC, t.`id` ASC
        ");
        $tagStmt->execute($eventIds);
        foreach ($tagStmt->fetchAll(PDO::FETCH_ASSOC) as $tagRow) {
            $eid = (int) $tagRow['event_id'];
            if (!isset($tagsByEventId[$eid])) {
                $tagsByEventId[$eid] = [];
            }
            $tagsByEventId[$eid][] = [
                'id' => (int) $tagRow['id'],
                'name' => (string) $tagRow['name'],
            ];
        }
    }
    if ($djsAvailable) {
        $djStmt = $db->prepare("
            SELECT etdj.`event_id`, t.`id`, t.`name`
            FROM `events_calendar_event_tags` etdj
            INNER JOIN `events_tag_type_links` ttdj ON ttdj.`tag_id` = etdj.`tag_id`
            INNER JOIN `events_tag_types` tydj ON tydj.`id` = ttdj.`tag_type_id` AND tydj.`code` = 'dj'
            INNER JOIN `events_tags` t ON t.`id` = etdj.`tag_id`
            WHERE etdj.`event_id` IN ({$ph})
            ORDER BY t.`name` ASC, t.`id` ASC
        ");
        $djStmt->execute($eventIds);
        foreach ($djStmt->fetchAll(PDO::FETCH_ASSOC) as $djRow) {
            $eid = (int) $djRow['event_id'];
            if (!isset($djsByEventId[$eid])) {
                $djsByEventId[$eid] = [];
            }
            $djsByEventId[$eid][] = [
                'id' => (int) $djRow['id'],
                'name' => (string) $djRow['name'],
            ];
        }
    }
    if ($stylesAvailable) {
        $mainStyleStmt = $db->prepare("
            SELECT ms.`event_id`, s.`id`, s.`name`
            FROM `events_calendar_event_main_styles` ms
            INNER JOIN `events_styles` s ON s.`id` = ms.`style_id`
            WHERE ms.`event_id` IN ({$ph})
            ORDER BY s.`name` ASC, s.`id` ASC
        ");
        $mainStyleStmt->execute($eventIds);
        foreach ($mainStyleStmt->fetchAll(PDO::FETCH_ASSOC) as $styleRow) {
            $eid = (int) $styleRow['event_id'];
            if (!isset($mainStylesByEventId[$eid])) {
                $mainStylesByEventId[$eid] = [];
            }
            $mainStylesByEventId[$eid][] = [
                'id' => (int) $styleRow['id'],
                'name' => (string) $styleRow['name'],
            ];
        }
        $suppStyleStmt = $db->prepare("
            SELECT ss.`event_id`, s.`id`, s.`name`
            FROM `events_calendar_event_supplementary_styles` ss
            INNER JOIN `events_styles` s ON s.`id` = ss.`style_id`
            WHERE ss.`event_id` IN ({$ph})
            ORDER BY s.`name` ASC, s.`id` ASC
        ");
        $suppStyleStmt->execute($eventIds);
        foreach ($suppStyleStmt->fetchAll(PDO::FETCH_ASSOC) as $styleRow) {
            $eid = (int) $styleRow['event_id'];
            if (!isset($supplementaryStylesByEventId[$eid])) {
                $supplementaryStylesByEventId[$eid] = [];
            }
            $supplementaryStylesByEventId[$eid][] = [
                'id' => (int) $styleRow['id'],
                'name' => (string) $styleRow['name'],
            ];
        }
    }
}

$editBase = events_url('szerkeszt.php?id=');
$filterFormAction = events_url('events_admin.php');
$filterFormHidden = [];
$filterClearUrl = events_url('events_admin.php');
$calendarViewUrl = events_admin_calendar_view_url(events_admin_calendar_view_month_key($filters), $get_params);
$listViewUrl = events_admin_list_view_url($get_params, ['order' => $order, 'dir' => $dir_param]);
$activeView = 'list';
$showAll = $filters['show_all'];
$publicPreviewParams = $get_params;
$publicPreviewParams['month'] = events_admin_calendar_view_month_key($filters);
$publicHomePreviewUrl = events_url(events_public_home_page_script() . '?' . http_build_query($publicPreviewParams));

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Események';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card">
    <form method="get" action="<?= h($filterFormAction) ?>" class="events-admin-form" id="events-admin-filter-form">
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

        <?php require __DIR__ . '/partials/admin_event_view_switch.php'; ?>

        <?php
        $filterFormHidden = ['order' => $order, 'dir' => $dir_param];
        require __DIR__ . '/partials/admin_event_filters.php';
        ?>

        <?php require __DIR__ . '/partials/admin_event_list_table.php'; ?>
    </form>
</div>
<?php require __DIR__ . '/partials/admin_event_filters_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
