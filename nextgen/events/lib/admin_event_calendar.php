<?php
declare(strict_types=1);

require_once __DIR__ . '/event_status.php';
require_once __DIR__ . '/event_change.php';

/**
 * Admin havi naptár nézet — hónap feloldás, rács, események naphoz rendelése.
 */

function events_admin_calendar_event_is_published(array $ev): bool {
    return (string) ($ev['event_status'] ?? '') === events_public_post_status();
}

/**
 * @param array<int, list<array{color:string}>> $categoriesByEventId
 */
function events_admin_calendar_event_public_url(array $ev): string {
    $id = (int) ($ev['id'] ?? 0);
    if (!events_admin_calendar_event_is_published($ev)) {
        return events_url('szerkeszt.php?id=') . $id;
    }
    $slug = trim((string) ($ev['event_slug'] ?? ''));
    if ($slug === '') {
        return events_url('szerkeszt.php?id=') . $id;
    }

    return events_megjelenit_url($slug);
}

/**
 * Tömör háttérszín + olvasható szövegszín (naptár blokk).
 *
 * @param array<int, list<array{color:string}>> $categoriesByEventId
 */
function events_admin_calendar_event_block_style(array $categoriesByEventId, int $eventId, bool $isPublished = true): string {
    $cats = $categoriesByEventId[$eventId] ?? [];
    $hex = '#6d8f63';
    if ($cats !== []) {
        $candidate = trim((string) ($cats[0]['color'] ?? '#6d8f63'));
        if ($candidate !== '') {
            $hex = $candidate;
        }
    }
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
        $hex = '#6d8f63';
    }
    if (!$isPublished) {
        return sprintf(
            'background-color:#f8fafc;color:#334155;border-color:%s;--events-cal-accent:%s',
            $hex,
            $hex
        );
    }
    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    $textColor = $luminance > 0.62 ? '#1a1a1a' : '#ffffff';

    return sprintf('background-color:%s;color:%s;border-color:%s', $hex, $textColor, $hex);
}

/**
 * @param array<int, list<array{color: string}>> $categoriesByEventId
 * @param array<string, mixed> $event
 */
function events_admin_calendar_event_block_style_for_event(
    array $categoriesByEventId,
    array $event,
    bool $isPublished = true
): string {
    $changeStyle = events_event_change_calendar_block_style($event);
    if ($changeStyle !== '') {
        return $changeStyle;
    }

    return events_admin_calendar_event_block_style($categoriesByEventId, (int) ($event['id'] ?? 0), $isPublished);
}

/**
 * @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: string}
 */
function events_admin_calendar_resolve_month(string $monthParam): array {
    $monthParam = trim($monthParam);
    if ($monthParam !== '' && preg_match('/^\d{4}-\d{2}$/', $monthParam) === 1) {
        try {
            $first = new DateTimeImmutable($monthParam . '-01');
            $key = $first->format('Y-m');

            return [$first, $first->modify('last day of this month'), $key];
        } catch (Throwable) {
            // fall through
        }
    }
    $today = new DateTimeImmutable('today');
    $first = $today->modify('first day of this month');

    return [$first, $first->modify('last day of this month'), $first->format('Y-m')];
}

/**
 * @param array<string, string> $getParams
 */
function events_admin_calendar_month_url(string $monthKey, array $getParams, string $page = 'events_naptar.php'): string {
    $q = array_merge($getParams, ['month' => $monthKey]);

    return events_url($page . '?' . http_build_query($q));
}

/**
 * Admin lista nézet URL — szűrők megőrzése, hónap paraméter nélkül.
 *
 * @param array<string, string> $getParams
 * @param array<string, string> $extra pl. order, dir
 */
function events_admin_list_view_url(array $getParams, array $extra = []): string {
    $q = array_merge($getParams, $extra);
    unset($q['month']);
    $q = array_filter($q, static fn ($v): bool => $v !== null && $v !== '');

    return $q !== [] ? events_url('events_admin.php?' . http_build_query($q)) : events_url('events_admin.php');
}

/**
 * Admin hónap nézet URL — szűrők + aktuális hónap.
 *
 * @param array<string, string> $getParams
 */
function events_admin_calendar_view_url(string $monthKey, array $getParams): string {
    return events_admin_calendar_month_url($monthKey, $getParams, 'events_naptar.php');
}

/**
 * Hónap kulcs a lista → naptár váltáshoz (dátumszűrő „ettől” vagy mai hónap).
 *
 * @param array<string, mixed> $filters events_admin_filters_from_request()
 */
function events_admin_calendar_view_month_key(array $filters): string {
    $from = trim((string) ($filters['f_start_from'] ?? ''));
    if ($from !== '') {
        try {
            return (new DateTimeImmutable($from))->format('Y-m');
        } catch (Throwable) {
        }
    }

    return (new DateTimeImmutable('today'))->format('Y-m');
}

/**
 * @return list<array{date: DateTimeImmutable, inMonth: bool, isToday: bool, isPast: bool, key: string}>
 */
function events_admin_calendar_grid_days(DateTimeImmutable $monthFirst, DateTimeImmutable $monthLast): array {
    $gridStart = $monthFirst->modify('monday this week');
    $gridEnd = $monthLast->modify('sunday this week');
    $todayKey = (new DateTimeImmutable('today'))->format('Y-m-d');
    $monthKey = $monthFirst->format('Y-m');
    $days = [];
    $cursor = $gridStart;
    while ($cursor <= $gridEnd) {
        $key = $cursor->format('Y-m-d');
        $days[] = [
            'date' => $cursor,
            'inMonth' => str_starts_with($key, $monthKey),
            'isToday' => $key === $todayKey,
            'isPast' => $key < $todayKey,
            'key' => $key,
        ];
        $cursor = $cursor->modify('+1 day');
    }

    return $days;
}

/**
 * Naptári utolsó nap: ha a zárás 06:00 előtt van, az előző naphoz tartozik (éjszakai bulik).
 */
function events_admin_calendar_effective_end_day(DateTimeImmutable $end): DateTimeImmutable {
    $dayStart = $end->setTime(0, 0, 0);
    $sixAm = $dayStart->setTime(6, 0, 0);
    if ($end < $sixAm) {
        return $dayStart->modify('-1 day');
    }

    return $dayStart;
}

/**
 * @return array{start: DateTimeImmutable, end: DateTimeImmutable, eventStart: DateTimeImmutable}|null
 */
function events_admin_calendar_event_date_range(array $row): ?array {
    $startRaw = $row['event_start'] ?? null;
    if ($startRaw === null || $startRaw === '') {
        return null;
    }
    try {
        $eventStart = new DateTimeImmutable((string) $startRaw);
    } catch (Throwable) {
        return null;
    }
    $endRaw = $row['event_end'] ?? null;
    try {
        $end = ($endRaw !== null && $endRaw !== '') ? new DateTimeImmutable((string) $endRaw) : $eventStart;
    } catch (Throwable) {
        $end = $eventStart;
    }

    $rangeStart = $eventStart->setTime(0, 0, 0);
    if (!empty($row['event_allday'])) {
        $rangeEnd = $end->setTime(0, 0, 0);
    } else {
        $rangeEnd = events_admin_calendar_effective_end_day($end);
    }
    if ($rangeEnd < $rangeStart) {
        $rangeEnd = $rangeStart;
    }

    return [
        'start' => $rangeStart,
        'end' => $rangeEnd,
        'eventStart' => $eventStart,
    ];
}

function events_admin_calendar_is_multi_day_event(array $row): bool {
    $range = events_admin_calendar_event_date_range($row);
    if ($range === null) {
        return false;
    }

    return $range['start']->format('Y-m-d') < $range['end']->format('Y-m-d');
}

/**
 * Naptár rács: befoglaló napok (záró dátum napja is), 06:00-s „éjszakai” szabály nélkül.
 *
 * @return array{start: DateTimeImmutable, end: DateTimeImmutable, eventStart: DateTimeImmutable}|null
 */
function events_admin_calendar_event_grid_date_range(array $row): ?array {
    $startRaw = $row['event_start'] ?? null;
    if ($startRaw === null || $startRaw === '') {
        return null;
    }
    try {
        $eventStart = new DateTimeImmutable((string) $startRaw);
    } catch (Throwable) {
        return null;
    }
    $endRaw = $row['event_end'] ?? null;
    try {
        $end = ($endRaw !== null && $endRaw !== '') ? new DateTimeImmutable((string) $endRaw) : $eventStart;
    } catch (Throwable) {
        $end = $eventStart;
    }

    $rangeStart = $eventStart->setTime(0, 0, 0);
    $rangeEnd = $end->setTime(0, 0, 0);
    if ($rangeEnd < $rangeStart) {
        $rangeEnd = $rangeStart;
    }

    return [
        'start' => $rangeStart,
        'end' => $rangeEnd,
        'eventStart' => $eventStart,
    ];
}

function events_admin_calendar_is_grid_multi_day_event(array $row): bool {
    $range = events_admin_calendar_event_grid_date_range($row);
    if ($range === null) {
        return false;
    }

    return $range['start']->format('Y-m-d') < $range['end']->format('Y-m-d');
}

/**
 * Heti rács: több napos sávok + napi egyszeri események.
 *
 * @param list<array<string, mixed>> $rows
 * @param list<array{date: DateTimeImmutable, inMonth: bool, isToday: bool, isPast: bool, key: string}> $gridDays
 * @return list<array{
 *   days: list<array{date: DateTimeImmutable, inMonth: bool, isToday: bool, isPast: bool, key: string}>,
 *   laneCount: int,
 *   segments: list<array{event: array<string, mixed>, colStart: int, span: int, lane: int, roundLeft: bool, roundRight: bool, showTime: bool, isPast: bool}>,
 *   partsByColLane: array<int, array<int, array{event: array<string, mixed>, lane: int, connectLeft: bool, connectRight: bool, roundLeft: bool, roundRight: bool, showTime: bool, showLabel: bool, labelSpan: int, isPast: bool}>>,
 *   singlesByDay: array<string, list<array<string, mixed>>>
 * }>
 */
function events_admin_calendar_build_week_layouts(
    array $rows,
    array $gridDays,
    DateTimeImmutable $monthFirst,
    DateTimeImmutable $monthLast
): array {
    $monthStart = $monthFirst->setTime(0, 0, 0);
    $monthEndExclusive = $monthFirst->modify('first day of next month')->setTime(0, 0, 0);
    $todayKey = (new DateTimeImmutable('today'))->format('Y-m-d');
    $weeks = [];

    foreach (array_chunk($gridDays, 7) as $weekDays) {
        if ($weekDays === []) {
            continue;
        }
        $weekStart = $weekDays[0]['date']->setTime(0, 0, 0);
        $weekEnd = $weekDays[count($weekDays) - 1]['date']->setTime(0, 0, 0);
        $dayIndexByKey = [];
        foreach ($weekDays as $idx => $day) {
            $dayIndexByKey[$day['key']] = $idx;
        }

        $segments = [];
        $singlesByDay = [];

        foreach ($rows as $row) {
            $range = events_admin_calendar_event_grid_date_range($row);
            if ($range === null) {
                continue;
            }
            if ($range['end'] < $monthStart || $range['start'] >= $monthEndExclusive) {
                continue;
            }
            if (!events_admin_calendar_is_grid_multi_day_event($row)) {
                $dayKey = $range['start']->format('Y-m-d');
                if ($range['start'] >= $monthStart && $range['start'] < $monthEndExclusive && isset($dayIndexByKey[$dayKey])) {
                    if (!isset($singlesByDay[$dayKey])) {
                        $singlesByDay[$dayKey] = [];
                    }
                    $singlesByDay[$dayKey][] = $row;
                }
                continue;
            }
            if ($range['end'] < $weekStart || $range['start'] > $weekEnd) {
                continue;
            }

            $segStart = $range['start'] > $weekStart ? $range['start'] : $weekStart;
            $segEnd = $range['end'] < $weekEnd ? $range['end'] : $weekEnd;
            $segStartKey = $segStart->format('Y-m-d');
            $segEndKey = $segEnd->format('Y-m-d');
            if (!isset($dayIndexByKey[$segStartKey])) {
                continue;
            }

            $colStart = $dayIndexByKey[$segStartKey];
            $span = (int) $segStart->diff($segEnd)->days + 1;
            $eventStartKey = $range['start']->format('Y-m-d');

            $segments[] = [
                'event' => $row,
                'colStart' => $colStart,
                'span' => max(1, $span),
                'lane' => 0,
                'roundLeft' => $segStartKey === $range['start']->format('Y-m-d'),
                'roundRight' => $segEndKey === $range['end']->format('Y-m-d'),
                'showTime' => $segStartKey === $eventStartKey && events_admin_calendar_event_time_label($row) !== '',
                'isPast' => $segEndKey < $todayKey,
            ];
        }

        usort($segments, static function (array $a, array $b): int {
            if ($a['colStart'] !== $b['colStart']) {
                return $a['colStart'] <=> $b['colStart'];
            }

            return $b['span'] <=> $a['span'];
        });

        $laneEnds = [];
        foreach ($segments as $idx => $segment) {
            $colEnd = $segment['colStart'] + $segment['span'];
            $assignedLane = null;
            foreach ($laneEnds as $lane => $endCol) {
                if ($segment['colStart'] >= $endCol) {
                    $assignedLane = $lane;
                    $laneEnds[$lane] = $colEnd;
                    break;
                }
            }
            if ($assignedLane === null) {
                $assignedLane = count($laneEnds);
                $laneEnds[] = $colEnd;
            }
            $segments[$idx]['lane'] = $assignedLane;
        }

        $partsByColLane = [];
        foreach ($segments as $segment) {
            $colStart = (int) $segment['colStart'];
            $span = max(1, (int) $segment['span']);
            $lane = (int) $segment['lane'];
            for ($offset = 0; $offset < $span; $offset++) {
                $col = $colStart + $offset;
                $isFirst = $offset === 0;
                $isLast = $offset === $span - 1;
                if (!isset($partsByColLane[$col])) {
                    $partsByColLane[$col] = [];
                }
                $partsByColLane[$col][$lane] = [
                    'event' => $segment['event'],
                    'lane' => $lane,
                    'connectLeft' => !$isFirst,
                    'connectRight' => !$isLast,
                    'roundLeft' => $isFirst && $segment['roundLeft'],
                    'roundRight' => $isLast && $segment['roundRight'],
                    'showTime' => $isFirst && $segment['showTime'],
                    'showLabel' => $isFirst,
                    'labelSpan' => $isFirst ? $span : 0,
                    'isPast' => $segment['isPast'],
                ];
            }
        }

        foreach ($singlesByDay as $dayKey => $dayRows) {
            usort($dayRows, static function (array $a, array $b): int {
                $sa = (string) ($a['event_start'] ?? '');
                $sb = (string) ($b['event_start'] ?? '');
                if ($sa === $sb) {
                    return strcasecmp((string) ($a['event_name'] ?? ''), (string) ($b['event_name'] ?? ''));
                }
                if ($sa === '') {
                    return 1;
                }
                if ($sb === '') {
                    return -1;
                }

                return $sa <=> $sb;
            });
            $singlesByDay[$dayKey] = $dayRows;
        }

        $weeks[] = [
            'days' => $weekDays,
            'laneCount' => count($laneEnds),
            'segments' => $segments,
            'partsByColLane' => $partsByColLane,
            'singlesByDay' => $singlesByDay,
        ];
    }

    return $weeks;
}

/**
 * @param list<array<string,mixed>> $rows
 * @return array{byDay: array<string, list<array<string,mixed>>>, undated: list<array<string,mixed>>}
 */
function events_admin_calendar_bucket_events(array $rows, DateTimeImmutable $monthFirst, DateTimeImmutable $monthLast): array {
    $monthStart = $monthFirst->setTime(0, 0, 0);
    $monthEndExclusive = $monthFirst->modify('first day of next month')->setTime(0, 0, 0);
    $byDay = [];
    $undated = [];

    foreach ($rows as $row) {
        $range = events_admin_calendar_event_grid_date_range($row);
        if ($range === null) {
            $undated[] = $row;
            continue;
        }
        $rangeStart = $range['start'];
        $rangeEnd = $range['end'];

        $cursor = $rangeStart;
        $placed = false;
        while ($cursor <= $rangeEnd) {
            if ($cursor >= $monthStart && $cursor < $monthEndExclusive) {
                $key = $cursor->format('Y-m-d');
                if (!isset($byDay[$key])) {
                    $byDay[$key] = [];
                }
                $eid = (int) ($row['id'] ?? 0);
                $already = false;
                foreach ($byDay[$key] as $existing) {
                    if ((int) ($existing['id'] ?? 0) === $eid) {
                        $already = true;
                        break;
                    }
                }
                if (!$already) {
                    $byDay[$key][] = $row;
                }
                $placed = true;
            }
            $cursor = $cursor->modify('+1 day');
        }
        if (!$placed && $rangeEnd < $monthStart) {
            continue;
        }
    }

    foreach ($byDay as $key => $dayRows) {
        usort($dayRows, static function (array $a, array $b): int {
            $sa = (string) ($a['event_start'] ?? '');
            $sb = (string) ($b['event_start'] ?? '');
            if ($sa === $sb) {
                return strcasecmp((string) ($a['event_name'] ?? ''), (string) ($b['event_name'] ?? ''));
            }
            if ($sa === '') {
                return 1;
            }
            if ($sb === '') {
                return -1;
            }

            return $sa <=> $sb;
        });
        $byDay[$key] = $dayRows;
    }

    return ['byDay' => $byDay, 'undated' => $undated];
}

function events_admin_calendar_month_label(DateTimeImmutable $monthFirst): string {
    static $huMonths = [
        1 => 'január', 2 => 'február', 3 => 'március', 4 => 'április',
        5 => 'május', 6 => 'június', 7 => 'július', 8 => 'augusztus',
        9 => 'szeptember', 10 => 'október', 11 => 'november', 12 => 'december',
    ];
    $y = (int) $monthFirst->format('Y');
    $m = (int) $monthFirst->format('n');

    return ($huMonths[$m] ?? $monthFirst->format('F')) . ' ' . $y;
}

function events_admin_calendar_event_time_label(array $row): string {
    if (!empty($row['event_allday'])) {
        return '';
    }
    $startRaw = $row['event_start'] ?? null;
    if ($startRaw === null || $startRaw === '') {
        return '';
    }
    $startTs = strtotime((string) $startRaw);
    if ($startTs === false) {
        return '';
    }
    $startTime = date('H:i', $startTs);
    if ($startTime === '00:00') {
        $startTime = '';
    }

    $endRaw = $row['event_end'] ?? null;
    if ($endRaw === null || $endRaw === '') {
        return $startTime;
    }
    $endTs = strtotime((string) $endRaw);
    if ($endTs === false) {
        return $startTime;
    }
    $endTime = date('H:i', $endTs);
    if ($endTime === '00:00') {
        return $startTime;
    }
    if ($startTime === '') {
        return $endTime;
    }
    if ($startTime === $endTime) {
        return $startTime;
    }

    return $startTime . ' – ' . $endTime;
}

/**
 * @return list<string>
 */
function events_admin_calendar_weekday_headers(): array {
    return ['Hét', 'Ked', 'Sze', 'Csü', 'Pén', 'Szo', 'Vas'];
}
