<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_event_filters.php';
require_once __DIR__ . '/admin_event_calendar.php';
require_once __DIR__ . '/event_status.php';
require_once __DIR__ . '/event_edit_stats.php';
require_once __DIR__ . '/event_realtime_stats.php';

/**
 * Event Admin Stat kezdőlap: partnerportál-szerű áttekintés az összes eseményre,
 * plusz a menük katalógusszámai.
 *
 * @return array{
 *   events_total: int,
 *   published: int,
 *   draft: int,
 *   upcoming: int,
 *   next: ?array<string, mixed>,
 *   upcoming_events: list<array<string, mixed>>,
 *   page_views_30: array{human: int, bot: int, total: int},
 *   live_users: int,
 *   catalog: array<string, int>
 * }
 */
function events_admin_stats_home_summary(PDO $db): array
{
    events_view_tracking_ensure_bot_column($db);

    $empty = [
        'events_total' => 0,
        'published' => 0,
        'draft' => 0,
        'upcoming' => 0,
        'next' => null,
        'upcoming_events' => [],
        'page_views_30' => ['human' => 0, 'bot' => 0, 'total' => 0],
        'live_users' => 0,
        'catalog' => [
            'venues' => 0,
            'organizers' => 0,
            'categories' => 0,
            'tags' => 0,
            'styles' => 0,
            'partners' => 0,
        ],
    ];

    $publishedStatus = events_public_post_status();
    $todayStart = events_admin_calendar_effective_today()->format('Y-m-d 00:00:00');

    try {
        $byStatus = $db->query('
            SELECT `event_status` AS st, COUNT(*) AS cnt
            FROM `events_calendar_events`
            GROUP BY `event_status`
        ')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($byStatus as $row) {
            $cnt = (int) ($row['cnt'] ?? 0);
            $empty['events_total'] += $cnt;
            $st = (string) ($row['st'] ?? '');
            if ($st === $publishedStatus) {
                $empty['published'] = $cnt;
            }
            if (in_array($st, ['draft', 'auto-draft'], true)) {
                $empty['draft'] += $cnt;
            }
        }
    } catch (Throwable $e) {
        error_log('events_admin_stats_home_summary status: ' . $e->getMessage());
    }

    try {
        $stmt = $db->prepare('
            SELECT COUNT(*)
            FROM `events_calendar_events`
            WHERE COALESCE(`event_end`, `event_start`) >= ?
        ');
        $stmt->execute([$todayStart]);
        $empty['upcoming'] = (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('events_admin_stats_home_summary upcoming: ' . $e->getMessage());
    }

    try {
        $stmt = $db->prepare('
            SELECT e.*, v.`city` AS venue_city
            FROM `events_calendar_events` e
            LEFT JOIN `events_venues` v ON v.`id` = e.`venue_id`
            WHERE e.`event_start` IS NOT NULL
              AND e.`event_start` >= ?
            ORDER BY e.`event_start` ASC, e.`id` ASC
            LIMIT 1
        ');
        $stmt->execute([$todayStart]);
        $next = $stmt->fetch(PDO::FETCH_ASSOC);
        $empty['next'] = $next !== false ? $next : null;
    } catch (Throwable $e) {
        error_log('events_admin_stats_home_summary next: ' . $e->getMessage());
    }

    try {
        $stmt = $db->prepare('
            SELECT e.*, v.`city` AS venue_city
            FROM `events_calendar_events` e
            LEFT JOIN `events_venues` v ON v.`id` = e.`venue_id`
            WHERE e.`event_start` IS NOT NULL
              AND COALESCE(e.`event_end`, e.`event_start`) >= ?
            ORDER BY e.`event_start` ASC, e.`id` ASC
            LIMIT 5
        ');
        $stmt->execute([$todayStart]);
        $empty['upcoming_events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('events_admin_stats_home_summary upcoming_events: ' . $e->getMessage());
    }

    try {
        $empty['page_views_30'] = events_edit_stats_page_views_all(
            $db,
            events_edit_stats_range_for_preset('30')
        );
    } catch (Throwable $e) {
        error_log('events_admin_stats_home_summary page_views: ' . $e->getMessage());
    }

    try {
        events_realtime_ensure_indexes($db);
        $window = events_realtime_window();
        $empty['live_users'] = events_realtime_count_unique_users(
            $db,
            $window['start'],
            events_view_tracking_bot_column_ready($db),
            events_edit_stats_table_ready($db)
        );
    } catch (Throwable $e) {
        error_log('events_admin_stats_home_summary live: ' . $e->getMessage());
    }

    $catalogMap = [
        'venues' => 'events_venues',
        'organizers' => 'events_organizers',
        'categories' => 'events_categories',
        'tags' => 'events_tags',
        'styles' => 'events_styles',
    ];
    foreach ($catalogMap as $key => $table) {
        try {
            $empty['catalog'][$key] = events_admin_table_total_count($db, $table);
        } catch (Throwable $e) {
            error_log('events_admin_stats_home_summary catalog ' . $key . ': ' . $e->getMessage());
        }
    }

    try {
        $empty['catalog']['partners'] = (int) $db->query('SELECT COUNT(*) FROM `nextgen_partners`')->fetchColumn();
    } catch (Throwable $e) {
        error_log('events_admin_stats_home_summary partners: ' . $e->getMessage());
    }

    return $empty;
}

/**
 * Többszörös szervező ID a GET-ből (org_id / org_id[]).
 *
 * @return list<int>
 */
function events_admin_stats_organizer_ids_from_request(array $query = []): array
{
    if ($query === []) {
        $query = $_GET;
    }
    $raw = $query['org_id'] ?? [];
    if (!is_array($raw)) {
        $raw = ($raw !== '' && $raw !== null) ? [(string) $raw] : [];
    }
    $ids = [];
    foreach ($raw as $item) {
        $id = (int) $item;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

/**
 * Szervezőlista a stat szűrőhöz.
 *
 * @return list<array{id: int, name: string}>
 */
function events_admin_stats_organizer_options(PDO $db): array
{
    try {
        $stmt = $db->query('SELECT `id`, `name` FROM `events_organizers` ORDER BY `name` ASC, `id` ASC');
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $rows[] = [
                'id' => $id,
                'name' => (string) ($row['name'] ?? ''),
            ];
        }

        return $rows;
    } catch (Throwable $e) {
        error_log('events_admin_stats_organizer_options: ' . $e->getMessage());

        return [];
    }
}
