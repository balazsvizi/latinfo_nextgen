<?php
declare(strict_types=1);

/**
 * Admin havi naptár nézet — hónap feloldás, rács, események naphoz rendelése.
 */

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
 * @return list<array{date: DateTimeImmutable, inMonth: bool, isToday: bool, key: string}>
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
            'key' => $key,
        ];
        $cursor = $cursor->modify('+1 day');
    }

    return $days;
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
        $startRaw = $row['event_start'] ?? null;
        if ($startRaw === null || $startRaw === '') {
            $undated[] = $row;
            continue;
        }
        try {
            $start = new DateTimeImmutable((string) $startRaw);
        } catch (Throwable) {
            $undated[] = $row;
            continue;
        }
        $endRaw = $row['event_end'] ?? null;
        try {
            $end = ($endRaw !== null && $endRaw !== '') ? new DateTimeImmutable((string) $endRaw) : $start;
        } catch (Throwable) {
            $end = $start;
        }

        $rangeStart = $start->setTime(0, 0, 0);
        $rangeEnd = $end->setTime(0, 0, 0);
        if ($rangeEnd < $rangeStart) {
            $rangeEnd = $rangeStart;
        }

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

    return $y . '. ' . ($huMonths[$m] ?? $monthFirst->format('F'));
}

function events_admin_calendar_event_time_label(array $row): string {
    if (!empty($row['event_allday'])) {
        return '';
    }
    $startRaw = $row['event_start'] ?? null;
    if ($startRaw === null || $startRaw === '') {
        return '';
    }
    $ts = strtotime((string) $startRaw);
    if ($ts === false) {
        return '';
    }

    return date('H:i', $ts);
}

/**
 * @return list<string>
 */
function events_admin_calendar_weekday_headers(): array {
    return ['H', 'K', 'Sze', 'Cs', 'P', 'Szo', 'V'];
}
