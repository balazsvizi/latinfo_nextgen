<?php
declare(strict_types=1);

/**
 * Címke használat: esemény + CMS cikk számlálók és admin szűrő URL-ek.
 */

/**
 * @param list<int> $tagIds
 * @return array<int, array{events:int, cms:int, total:int}>
 */
function events_tag_usage_counts_map(PDO $db, array $tagIds): array
{
    $ids = array_values(array_unique(array_filter(
        array_map(static fn (mixed $id): int => (int) $id, $tagIds),
        static fn (int $id): bool => $id > 0
    )));
    $out = [];
    foreach ($ids as $id) {
        $out[$id] = ['events' => 0, 'cms' => 0, 'total' => 0];
    }
    if ($ids === [] || !events_tags_tables_available($db)) {
        return $out;
    }

    $ph = implode(',', array_fill(0, count($ids), '?'));

    try {
        $st = $db->prepare("
            SELECT et.`tag_id`, COUNT(DISTINCT et.`event_id`) AS `cnt`
            FROM `events_calendar_event_tags` et
            INNER JOIN `events_calendar_events` e ON e.`id` = et.`event_id`
            WHERE et.`tag_id` IN ({$ph})
              AND e.`event_status` NOT IN ('trash')
            GROUP BY et.`tag_id`
        ");
        $st->execute($ids);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $tid = (int) ($row['tag_id'] ?? 0);
            if ($tid > 0 && isset($out[$tid])) {
                $out[$tid]['events'] = (int) ($row['cnt'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        error_log('events_tag_usage_counts_map events: ' . $e->getMessage());
    }

    try {
        $stCms = $db->prepare("
            SELECT `tag_id`, COUNT(*) AS `cnt`
            FROM `cms_post_tags`
            WHERE `tag_id` IN ({$ph})
            GROUP BY `tag_id`
        ");
        $stCms->execute($ids);
        while ($row = $stCms->fetch(PDO::FETCH_ASSOC)) {
            $tid = (int) ($row['tag_id'] ?? 0);
            if ($tid > 0 && isset($out[$tid])) {
                $out[$tid]['cms'] = (int) ($row['cnt'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        // CMS tábla hiányzik / még nincs migrálva
    }

    foreach ($out as $tid => $counts) {
        $out[$tid]['total'] = $counts['events'] + $counts['cms'];
    }

    return $out;
}

function events_tag_admin_events_filter_url(int $tagId): string
{
    if ($tagId <= 0) {
        return events_url('events_admin.php');
    }

    return events_url('events_admin.php?' . http_build_query(['f_tag' => (string) $tagId], '', '&', PHP_QUERY_RFC3986));
}

function events_tag_admin_cms_filter_url(int $tagId): string
{
    if ($tagId <= 0) {
        return nextgen_url('cms/posts.php');
    }

    return nextgen_url('cms/posts.php?' . http_build_query(['tag_id' => (string) $tagId], '', '&', PHP_QUERY_RFC3986));
}
