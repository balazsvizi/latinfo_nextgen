<?php
declare(strict_types=1);

/**
 * CMS cikk megtekintés-statisztika.
 */

const CMS_VIEW_METRIC_PAGE = 'page_view';
const CMS_VIEW_SOURCE_DIRECT = 'direct';
const CMS_VIEW_SOURCE_LIST = 'list';

function cms_view_tracking_should_record(): bool
{
    if (function_exists('isLoggedIn') && isLoggedIn()) {
        return false;
    }
    if (!empty($_SESSION['partner_id']) || !empty($_SESSION['organizer_id'])) {
        return false;
    }

    return true;
}

function cms_view_tracking_ip_hash(): ?string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    return $ip !== '' ? hash('sha256', $ip . '|' . (defined('SITE_NAME') ? SITE_NAME : 'cms')) : null;
}

function cms_record_post_view(PDO $db, int $postId, string $source = CMS_VIEW_SOURCE_DIRECT): void
{
    if ($postId <= 0 || !cms_view_tracking_should_record()) {
        return;
    }

    $isBot = false;
    if (function_exists('events_view_tracking_detect_bot')) {
        $isBot = events_view_tracking_detect_bot();
    }

    try {
        $st = $db->prepare('
            INSERT INTO `cms_post_views` (`post_id`, `metric_type`, `source`, `ip_hash`, `is_bot`, `occurred_at`)
            VALUES (?, ?, ?, ?, ?, NOW())
        ');
        $st->execute([
            $postId,
            CMS_VIEW_METRIC_PAGE,
            $source !== '' ? $source : CMS_VIEW_SOURCE_DIRECT,
            cms_view_tracking_ip_hash(),
            $isBot ? 1 : 0,
        ]);
    } catch (Throwable $e) {
        error_log('cms_record_post_view: ' . $e->getMessage());
    }
}

/**
 * @param array{date_from?:string, date_to?:string, visitor?:string} $params
 * @return array{
 *   total: int,
 *   human: int,
 *   bot: int,
 *   by_day: list<array{day:string, total:int, human:int}>,
 *   top_posts: list<array{post_id:int, title:string, slug:string, views:int}>
 * }
 */
function cms_stats_summary(PDO $db, array $params = []): array
{
    $empty = [
        'total' => 0,
        'human' => 0,
        'bot' => 0,
        'by_day' => [],
        'top_posts' => [],
    ];

    $dateFrom = trim((string) ($params['date_from'] ?? ''));
    $dateTo = trim((string) ($params['date_to'] ?? ''));
    if ($dateFrom === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $dateFrom = date('Y-m-d', strtotime('-29 days'));
    }
    if ($dateTo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $dateTo = date('Y-m-d');
    }
    $visitor = (string) ($params['visitor'] ?? 'human');
    if (!in_array($visitor, ['all', 'human', 'bot'], true)) {
        $visitor = 'human';
    }

    $where = ['`occurred_at` >= ?', '`occurred_at` < DATE_ADD(?, INTERVAL 1 DAY)'];
    $bind = [$dateFrom . ' 00:00:00', $dateTo];
    if ($visitor === 'human') {
        $where[] = '`is_bot` = 0';
    } elseif ($visitor === 'bot') {
        $where[] = '`is_bot` = 1';
    }
    $whereSql = implode(' AND ', $where);

    try {
        $st = $db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN `is_bot` = 0 THEN 1 ELSE 0 END) AS human_cnt,
                SUM(CASE WHEN `is_bot` = 1 THEN 1 ELSE 0 END) AS bot_cnt
            FROM `cms_post_views`
            WHERE $whereSql
        ");
        $st->execute($bind);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $empty['total'] = (int) ($row['total'] ?? 0);
        $empty['human'] = (int) ($row['human_cnt'] ?? 0);
        $empty['bot'] = (int) ($row['bot_cnt'] ?? 0);

        $daySt = $db->prepare("
            SELECT DATE(`occurred_at`) AS day,
                   COUNT(*) AS total,
                   SUM(CASE WHEN `is_bot` = 0 THEN 1 ELSE 0 END) AS human_cnt
            FROM `cms_post_views`
            WHERE $whereSql
            GROUP BY DATE(`occurred_at`)
            ORDER BY day ASC
        ");
        $daySt->execute($bind);
        while ($d = $daySt->fetch(PDO::FETCH_ASSOC)) {
            $empty['by_day'][] = [
                'day' => (string) ($d['day'] ?? ''),
                'total' => (int) ($d['total'] ?? 0),
                'human' => (int) ($d['human_cnt'] ?? 0),
            ];
        }

        $topWhereSql = str_replace(
            ['`occurred_at`', '`is_bot`'],
            ['v.`occurred_at`', 'v.`is_bot`'],
            $whereSql
        );
        $topSt = $db->prepare("
            SELECT v.`post_id`, p.`title`, p.`slug`, COUNT(*) AS views
            FROM `cms_post_views` v
            INNER JOIN `cms_posts` p ON p.`id` = v.`post_id`
            WHERE $topWhereSql
            GROUP BY v.`post_id`, p.`title`, p.`slug`
            ORDER BY views DESC
            LIMIT 20
        ");
        $topSt->execute($bind);
        while ($t = $topSt->fetch(PDO::FETCH_ASSOC)) {
            $empty['top_posts'][] = [
                'post_id' => (int) ($t['post_id'] ?? 0),
                'title' => (string) ($t['title'] ?? ''),
                'slug' => (string) ($t['slug'] ?? ''),
                'views' => (int) ($t['views'] ?? 0),
            ];
        }
    } catch (Throwable $e) {
        error_log('cms_stats_summary: ' . $e->getMessage());
    }

    return $empty;
}

/**
 * @return array{date_from:string, date_to:string, visitor:string}
 */
function cms_stats_params_from_request(array $get): array
{
    $dateFrom = trim((string) ($get['date_from'] ?? ''));
    $dateTo = trim((string) ($get['date_to'] ?? ''));
    $visitor = (string) ($get['visitor'] ?? 'human');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $dateFrom = date('Y-m-d', strtotime('-29 days'));
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $dateTo = date('Y-m-d');
    }
    if (!in_array($visitor, ['all', 'human', 'bot'], true)) {
        $visitor = 'human';
    }

    return [
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'visitor' => $visitor,
    ];
}

function cms_stats_views_for_post(PDO $db, int $postId, bool $humanOnly = true): int
{
    if ($postId <= 0) {
        return 0;
    }
    try {
        $sql = 'SELECT COUNT(*) FROM `cms_post_views` WHERE `post_id` = ?';
        if ($humanOnly) {
            $sql .= ' AND `is_bot` = 0';
        }
        $st = $db->prepare($sql);
        $st->execute([$postId]);

        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
