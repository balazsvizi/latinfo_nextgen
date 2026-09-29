<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/user/favorites.php';
require_once __DIR__ . '/event_edit_stats.php';

/**
 * @return array<string, string>
 */
function latinfo_favorite_stats_type_labels(string $lang = 'hu'): array
{
    $isEn = $lang === 'en';

    return [
        LATINFO_FAVORITE_TYPE_EVENT => $isEn ? 'Events' : 'Események',
        LATINFO_FAVORITE_TYPE_ORGANIZER => $isEn ? 'Organizers' : 'Szervezők',
        LATINFO_FAVORITE_TYPE_VENUE => $isEn ? 'Venues' : 'Helyszínek',
        LATINFO_FAVORITE_TYPE_DJ => 'DJ-k',
        LATINFO_FAVORITE_TYPE_ZENEKAR => $isEn ? 'Bands' : 'Zenekarok',
    ];
}

/**
 * @return array<string, string>
 */
function latinfo_favorite_stats_type_colors(): array
{
    return [
        LATINFO_FAVORITE_TYPE_EVENT => '#6d8f63',
        LATINFO_FAVORITE_TYPE_ORGANIZER => '#c45c26',
        LATINFO_FAVORITE_TYPE_VENUE => '#2f6f8f',
        LATINFO_FAVORITE_TYPE_DJ => '#8b5a9e',
        LATINFO_FAVORITE_TYPE_ZENEKAR => '#8a6d4f',
    ];
}

/**
 * @return array{date_from:string,date_to:string,entity_type:string,actor:string}
 */
function latinfo_favorite_stats_params_from_request(array $query): array
{
    $base = events_edit_stats_params_from_request($query);
    $type = latinfo_favorites_normalize_type((string) ($query['fav_type'] ?? ''));
    $actor = strtolower(trim((string) ($query['fav_actor'] ?? 'all')));
    if (!in_array($actor, ['all', 'user', 'visitor'], true)) {
        $actor = 'all';
    }

    return [
        'date_from' => (string) $base['date_from'],
        'date_to' => (string) $base['date_to'],
        'entity_type' => $type ?? 'all',
        'actor' => $actor,
    ];
}

function latinfo_favorite_stats_earliest_date(PDO $db): ?string
{
    if (!latinfo_favorites_table_ready($db)) {
        return null;
    }
    try {
        $raw = $db->query('SELECT MIN(`created_at`) FROM `latinfo_favorites`')->fetchColumn();
        if ($raw === false || $raw === null || (string) $raw === '') {
            return null;
        }

        return substr((string) $raw, 0, 10);
    } catch (Throwable) {
        return null;
    }
}

/**
 * @param array{date_from:string,date_to:string,entity_type:string,actor:string} $params
 * @return array{sql:string,bind:list<mixed>}
 */
function latinfo_favorite_stats_where(array $params): array
{
    $sql = ['f.`created_at` >= ?', 'f.`created_at` < DATE_ADD(?, INTERVAL 1 DAY)'];
    $bind = [$params['date_from'] . ' 00:00:00', $params['date_to']];
    if (($params['entity_type'] ?? 'all') !== 'all') {
        $sql[] = 'f.`entity_type` = ?';
        $bind[] = $params['entity_type'];
    }
    $actor = (string) ($params['actor'] ?? 'all');
    if ($actor === 'user') {
        $sql[] = "f.`actor_key` LIKE 'u:%'";
    } elseif ($actor === 'visitor') {
        $sql[] = "f.`actor_key` LIKE 'v:%'";
    }

    return ['sql' => implode(' AND ', $sql), 'bind' => $bind];
}

function latinfo_favorite_stats_granularity(string $dateFrom, string $dateTo): string
{
    try {
        $days = (int) (new DateTimeImmutable($dateFrom))->diff(new DateTimeImmutable($dateTo))->days;
    } catch (Throwable) {
        return 'day';
    }
    if ($days === 0) {
        return 'hour';
    }
    if ($days > 366) {
        return 'month';
    }
    if ($days > 90) {
        return 'week';
    }

    return 'day';
}

function latinfo_favorite_stats_bucket_expr(string $granularity): string
{
    return match ($granularity) {
        'hour' => "DATE_FORMAT(f.`created_at`, '%H')",
        'month' => "DATE_FORMAT(f.`created_at`, '%Y-%m-01')",
        'week' => 'DATE(DATE_SUB(f.`created_at`, INTERVAL WEEKDAY(f.`created_at`) DAY))',
        default => 'DATE(f.`created_at`)',
    };
}

/**
 * @return list<string>
 */
function latinfo_favorite_stats_bucket_keys(string $dateFrom, string $dateTo, string $granularity): array
{
    if ($granularity === 'hour') {
        $out = [];
        for ($h = 0; $h < 24; $h++) {
            $out[] = sprintf('%02d', $h);
        }

        return $out;
    }
    try {
        $from = new DateTimeImmutable($dateFrom);
        $to = new DateTimeImmutable($dateTo);
    } catch (Throwable) {
        return [];
    }
    $out = [];
    if ($granularity === 'month') {
        $cur = $from->modify('first day of this month');
        $end = $to->modify('first day of this month');
        while ($cur <= $end) {
            $out[] = $cur->format('Y-m-01');
            $cur = $cur->modify('+1 month');
        }

        return $out;
    }
    if ($granularity === 'week') {
        $cur = $from->modify('-' . ((int) $from->format('N') - 1) . ' days');
        $end = $to->modify('-' . ((int) $to->format('N') - 1) . ' days');
        while ($cur <= $end) {
            $out[] = $cur->format('Y-m-d');
            $cur = $cur->modify('+7 days');
        }

        return $out;
    }
    $cur = $from;
    while ($cur <= $to) {
        $out[] = $cur->format('Y-m-d');
        $cur = $cur->modify('+1 day');
    }

    return $out;
}

function latinfo_favorite_stats_bucket_label(string $key, string $granularity): string
{
    if ($granularity === 'hour') {
        return $key . ':00';
    }
    if ($granularity === 'month') {
        return substr($key, 0, 7);
    }
    if ($granularity === 'week') {
        return $key;
    }

    return $key;
}

/**
 * @param array{date_from:string,date_to:string,entity_type:string,actor:string} $params
 * @return array<string, mixed>
 */
function latinfo_favorite_stats(PDO $db, array $params): array
{
    $typeLabels = latinfo_favorite_stats_type_labels('hu');
    $typeColors = latinfo_favorite_stats_type_colors();
    $granularity = latinfo_favorite_stats_granularity($params['date_from'], $params['date_to']);
    $empty = [
        'table_ready' => false,
        'granularity' => $granularity,
        'totals' => [
            'hearts' => 0,
            'unique_actors' => 0,
            'unique_entities' => 0,
            'user_hearts' => 0,
            'visitor_hearts' => 0,
            'all_time' => 0,
            'by_type' => [],
        ],
        'chart' => ['labels' => [], 'datasets' => []],
        'actor_chart' => ['labels' => [], 'datasets' => []],
        'type_share' => ['labels' => [], 'data' => [], 'colors' => []],
        'hour_chart' => ['labels' => [], 'data' => []],
        'type_rows' => [],
        'top_entities' => [],
        'recent' => [],
    ];

    if (!latinfo_favorites_ensure_schema($db) || !latinfo_favorites_table_ready($db)) {
        return $empty;
    }
    $empty['table_ready'] = true;

    $where = latinfo_favorite_stats_where($params);
    $whereSql = $where['sql'];
    $bind = $where['bind'];

    try {
        $allTime = (int) $db->query('SELECT COUNT(*) FROM `latinfo_favorites`')->fetchColumn();

        $st = $db->prepare("
            SELECT
                COUNT(*) AS hearts,
                COUNT(DISTINCT f.`actor_key`) AS unique_actors,
                COUNT(DISTINCT CONCAT(f.`entity_type`, ':', f.`entity_id`)) AS unique_entities,
                SUM(CASE WHEN f.`actor_key` LIKE 'u:%' THEN 1 ELSE 0 END) AS user_hearts,
                SUM(CASE WHEN f.`actor_key` LIKE 'v:%' THEN 1 ELSE 0 END) AS visitor_hearts
            FROM `latinfo_favorites` f
            WHERE {$whereSql}
        ");
        $st->execute($bind);
        $tot = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $byType = [];
        foreach (latinfo_favorite_entity_types() as $type) {
            $byType[$type] = 0;
        }
        $st = $db->prepare("
            SELECT f.`entity_type`, COUNT(*) AS cnt
            FROM `latinfo_favorites` f
            WHERE {$whereSql}
            GROUP BY f.`entity_type`
        ");
        $st->execute($bind);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $t = (string) ($row['entity_type'] ?? '');
            if (isset($byType[$t])) {
                $byType[$t] = (int) ($row['cnt'] ?? 0);
            }
        }

        $typeRows = [];
        foreach ($byType as $type => $cnt) {
            $typeRows[] = [
                'type' => $type,
                'label' => $typeLabels[$type] ?? $type,
                'count' => $cnt,
                'color' => $typeColors[$type] ?? '#6b7280',
            ];
        }
        usort($typeRows, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        $shareLabels = [];
        $shareData = [];
        $shareColors = [];
        foreach ($typeRows as $row) {
            if ((int) $row['count'] <= 0) {
                continue;
            }
            $shareLabels[] = (string) $row['label'];
            $shareData[] = (int) $row['count'];
            $shareColors[] = (string) $row['color'];
        }

        $bucketExpr = latinfo_favorite_stats_bucket_expr($granularity);
        $bucketKeys = latinfo_favorite_stats_bucket_keys($params['date_from'], $params['date_to'], $granularity);
        $bucketLabels = array_map(
            static fn (string $k): string => latinfo_favorite_stats_bucket_label($k, $granularity),
            $bucketKeys
        );

        $seriesTypes = ($params['entity_type'] ?? 'all') === 'all'
            ? latinfo_favorite_entity_types()
            : [$params['entity_type']];
        $seriesMap = [];
        foreach ($seriesTypes as $type) {
            $seriesMap[$type] = array_fill_keys($bucketKeys, 0);
        }
        $actorUserMap = array_fill_keys($bucketKeys, 0);
        $actorVisitorMap = array_fill_keys($bucketKeys, 0);

        $st = $db->prepare("
            SELECT {$bucketExpr} AS bucket, f.`entity_type`, COUNT(*) AS cnt
            FROM `latinfo_favorites` f
            WHERE {$whereSql}
            GROUP BY bucket, f.`entity_type`
        ");
        $st->execute($bind);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $bucket = (string) ($row['bucket'] ?? '');
            $type = (string) ($row['entity_type'] ?? '');
            if ($granularity === 'hour') {
                $bucket = sprintf('%02d', (int) $bucket);
            } elseif ($granularity === 'month') {
                $bucket = substr($bucket, 0, 10);
                if (strlen($bucket) === 7) {
                    $bucket .= '-01';
                }
            } else {
                $bucket = substr($bucket, 0, 10);
            }
            if (isset($seriesMap[$type][$bucket])) {
                $seriesMap[$type][$bucket] = (int) ($row['cnt'] ?? 0);
            }
        }

        $st = $db->prepare("
            SELECT {$bucketExpr} AS bucket,
                SUM(CASE WHEN f.`actor_key` LIKE 'u:%' THEN 1 ELSE 0 END) AS user_cnt,
                SUM(CASE WHEN f.`actor_key` LIKE 'v:%' THEN 1 ELSE 0 END) AS visitor_cnt
            FROM `latinfo_favorites` f
            WHERE {$whereSql}
            GROUP BY bucket
        ");
        $st->execute($bind);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $bucket = (string) ($row['bucket'] ?? '');
            if ($granularity === 'hour') {
                $bucket = sprintf('%02d', (int) $bucket);
            } elseif ($granularity === 'month') {
                $bucket = substr($bucket, 0, 10);
                if (strlen($bucket) === 7) {
                    $bucket .= '-01';
                }
            } else {
                $bucket = substr($bucket, 0, 10);
            }
            if (isset($actorUserMap[$bucket])) {
                $actorUserMap[$bucket] = (int) ($row['user_cnt'] ?? 0);
                $actorVisitorMap[$bucket] = (int) ($row['visitor_cnt'] ?? 0);
            }
        }

        $datasets = [];
        foreach ($seriesTypes as $type) {
            $datasets[] = [
                'label' => $typeLabels[$type] ?? $type,
                'color' => $typeColors[$type] ?? '#6b7280',
                'data' => array_values(array_map(
                    static fn (string $k): int => (int) ($seriesMap[$type][$k] ?? 0),
                    $bucketKeys
                )),
            ];
        }

        $hourLabels = [];
        $hourData = array_fill(0, 24, 0);
        for ($h = 0; $h < 24; $h++) {
            $hourLabels[] = sprintf('%02d', $h);
        }
        $st = $db->prepare("
            SELECT HOUR(f.`created_at`) AS h, COUNT(*) AS cnt
            FROM `latinfo_favorites` f
            WHERE {$whereSql}
            GROUP BY h
        ");
        $st->execute($bind);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $h = (int) ($row['h'] ?? -1);
            if ($h >= 0 && $h < 24) {
                $hourData[$h] = (int) ($row['cnt'] ?? 0);
            }
        }

        $st = $db->prepare("
            SELECT f.`entity_type`, f.`entity_id`, COUNT(*) AS cnt,
                COUNT(DISTINCT f.`actor_key`) AS unique_actors,
                MAX(f.`created_at`) AS last_at
            FROM `latinfo_favorites` f
            WHERE {$whereSql}
            GROUP BY f.`entity_type`, f.`entity_id`
            ORDER BY cnt DESC, last_at DESC
            LIMIT 50
        ");
        $st->execute($bind);
        $topEntities = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $type = (string) ($row['entity_type'] ?? '');
            $eid = (int) ($row['entity_id'] ?? 0);
            $meta = latinfo_favorites_entity_public_meta($db, $type, $eid, 'hu');
            $topEntities[] = [
                'type' => $type,
                'type_label' => $typeLabels[$type] ?? $type,
                'id' => $eid,
                'label' => $meta['label'] ?? ('#' . $eid),
                'url' => $meta['url'] ?? '',
                'count' => (int) ($row['cnt'] ?? 0),
                'unique_actors' => (int) ($row['unique_actors'] ?? 0),
                'last_at' => (string) ($row['last_at'] ?? ''),
            ];
        }

        $st = $db->prepare("
            SELECT f.`id`, f.`entity_type`, f.`entity_id`, f.`actor_key`, f.`created_at`
            FROM `latinfo_favorites` f
            WHERE {$whereSql}
            ORDER BY f.`created_at` DESC, f.`id` DESC
            LIMIT 40
        ");
        $st->execute($bind);
        $recent = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $type = (string) ($row['entity_type'] ?? '');
            $eid = (int) ($row['entity_id'] ?? 0);
            $meta = latinfo_favorites_entity_public_meta($db, $type, $eid, 'hu');
            $actorKey = (string) ($row['actor_key'] ?? '');
            $recent[] = [
                'id' => (int) ($row['id'] ?? 0),
                'type' => $type,
                'type_label' => $typeLabels[$type] ?? $type,
                'entity_id' => $eid,
                'label' => $meta['label'] ?? ('#' . $eid),
                'url' => $meta['url'] ?? '',
                'actor_kind' => str_starts_with($actorKey, 'u:') ? 'user' : 'visitor',
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return [
            'table_ready' => true,
            'granularity' => $granularity,
            'totals' => [
                'hearts' => (int) ($tot['hearts'] ?? 0),
                'unique_actors' => (int) ($tot['unique_actors'] ?? 0),
                'unique_entities' => (int) ($tot['unique_entities'] ?? 0),
                'user_hearts' => (int) ($tot['user_hearts'] ?? 0),
                'visitor_hearts' => (int) ($tot['visitor_hearts'] ?? 0),
                'all_time' => $allTime,
                'by_type' => $byType,
            ],
            'chart' => [
                'labels' => $bucketLabels,
                'datasets' => $datasets,
            ],
            'actor_chart' => [
                'labels' => $bucketLabels,
                'datasets' => [
                    [
                        'label' => 'Bejelentkezett',
                        'color' => '#6d8f63',
                        'data' => array_values(array_map(
                            static fn (string $k): int => (int) ($actorUserMap[$k] ?? 0),
                            $bucketKeys
                        )),
                    ],
                    [
                        'label' => 'Vendég',
                        'color' => '#8b5a9e',
                        'data' => array_values(array_map(
                            static fn (string $k): int => (int) ($actorVisitorMap[$k] ?? 0),
                            $bucketKeys
                        )),
                    ],
                ],
            ],
            'type_share' => [
                'labels' => $shareLabels,
                'data' => $shareData,
                'colors' => $shareColors,
            ],
            'hour_chart' => [
                'labels' => $hourLabels,
                'data' => $hourData,
            ],
            'type_rows' => $typeRows,
            'top_entities' => $topEntities,
            'recent' => $recent,
        ];
    } catch (Throwable $ex) {
        error_log('latinfo_favorite_stats: ' . $ex->getMessage());

        return $empty;
    }
}
