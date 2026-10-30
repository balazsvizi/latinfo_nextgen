<?php
declare(strict_types=1);

/**
 * Admin: intervallum buliképei megosztáshoz / kivágáshoz.
 */

/**
 * @return array{from:string,to:string}
 */
function events_repost_images_default_range(): array
{
    $tz = new DateTimeZone('Europe/Budapest');
    $tomorrow = (new DateTimeImmutable('today', $tz))->modify('+1 day')->format('Y-m-d');

    return ['from' => $tomorrow, 'to' => $tomorrow];
}

/**
 * GET dátumok (Y-m-d). Üres → holnap–holnap. Érvénytelen → null.
 *
 * @return array{from:string,to:string}|null
 */
function events_repost_images_range_from_request(): ?array
{
    $defaults = events_repost_images_default_range();
    $rawFrom = trim((string) ($_GET['from'] ?? ''));
    $rawTo = trim((string) ($_GET['to'] ?? ''));

    if ($rawFrom === '' && $rawTo === '') {
        return $defaults;
    }

    $from = $rawFrom !== '' ? $rawFrom : $rawTo;
    $to = $rawTo !== '' ? $rawTo : $rawFrom;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        return null;
    }

    try {
        $tz = new DateTimeZone('Europe/Budapest');
        $fromDt = new DateTimeImmutable($from . ' 00:00:00', $tz);
        $toDt = new DateTimeImmutable($to . ' 00:00:00', $tz);
    } catch (Throwable) {
        return null;
    }

    if ($fromDt > $toDt) {
        [$fromDt, $toDt] = [$toDt, $fromDt];
    }

    return [
        'from' => $fromDt->format('Y-m-d'),
        'to' => $toDt->format('Y-m-d'),
    ];
}

/**
 * Gyors intervallum presetek (Holnap, Ma, Hétvége).
 *
 * @return list<array{key:string,label:string,from:string,to:string}>
 */
function events_repost_images_presets(): array
{
    $tz = new DateTimeZone('Europe/Budapest');
    $today = new DateTimeImmutable('today', $tz);
    $tomorrow = $today->modify('+1 day');

    $dow = (int) $today->format('N'); // 1=hétfő … 7=vasárnap
    if ($dow === 6) {
        $weekendFrom = $today;
        $weekendTo = $today->modify('+1 day');
    } elseif ($dow === 7) {
        $weekendFrom = $today->modify('-1 day');
        $weekendTo = $today;
    } else {
        $weekendFrom = $today->modify('+' . (6 - $dow) . ' day');
        $weekendTo = $weekendFrom->modify('+1 day');
    }

    return [
        [
            'key' => 'tomorrow',
            'label' => 'Holnap',
            'from' => $tomorrow->format('Y-m-d'),
            'to' => $tomorrow->format('Y-m-d'),
        ],
        [
            'key' => 'today',
            'label' => 'Ma',
            'from' => $today->format('Y-m-d'),
            'to' => $today->format('Y-m-d'),
        ],
        [
            'key' => 'weekend',
            'label' => 'Hétvége',
            'from' => $weekendFrom->format('Y-m-d'),
            'to' => $weekendTo->format('Y-m-d'),
        ],
    ];
}

/**
 * Közzétett / előzetes események kiemelt képpel a dátumtartományban (kezdőnap szerint).
 *
 * @return list<array<string, mixed>>
 */
function events_repost_images_fetch(PDO $db, string $fromYmd, string $toYmd): array
{
    $statuses = events_publicly_visible_post_statuses();
    if ($statuses === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $sql = '
        SELECT e.`id`, e.`event_slug`, e.`event_name`, e.`event_featured_image_url`,
               e.`event_start`, e.`event_end`, e.`event_allday`, e.`event_status`,
               v.`name` AS `venue_name`, v.`city` AS `venue_city`
        FROM `events_calendar_events` e
        LEFT JOIN `events_venues` v ON v.`id` = e.`venue_id`
        WHERE e.`event_status` IN (' . $placeholders . ')
          AND e.`event_featured_image_url` IS NOT NULL
          AND TRIM(e.`event_featured_image_url`) <> \'\'
          AND DATE(e.`event_start`) >= ?
          AND DATE(e.`event_start`) <= ?
        ORDER BY e.`event_start` ASC, e.`id` ASC
    ';
    $params = array_merge($statuses, [$fromYmd, $toYmd]);
    try {
        $st = $db->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('events_repost_images_fetch: ' . $e->getMessage());

        return [];
    }
}

/**
 * Kiemelt kép abszolút URL (üres, ha nincs / érvénytelen).
 */
function events_repost_images_featured_url(string $raw): string
{
    $featuredRaw = trim(html_entity_decode(trim($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $featuredRaw = preg_replace('/^\x{FEFF}|\x{200B}/u', '', $featuredRaw) ?? $featuredRaw;
    if ($featuredRaw === '') {
        return '';
    }

    return events_absolute_url($featuredRaw);
}

/**
 * Rövid dátum/idő sor a kártya meta adatához.
 */
function events_repost_images_when_label(array $row): string
{
    $allday = !empty($row['event_allday']);
    $ts = !empty($row['event_start']) ? strtotime((string) $row['event_start']) : false;
    if ($ts === false) {
        return '';
    }
    if ($allday) {
        return date('Y. m. d.', $ts);
    }

    return date('Y. m. d. H:i', $ts);
}
