<?php
declare(strict_types=1);

/**
 * Kategória felirat HU / EN nézetben: EN-nél üres name_en → magyar név.
 */
function events_category_locale_label(string $lang, string $nameHu, string $nameEn): string {
    $hu = trim($nameHu);
    if ($lang !== 'en') {
        return $hu;
    }
    $en = trim($nameEn);

    return $en !== '' ? $en : $hu;
}

/**
 * Eseményhez rendelt kategóriasorok (LEFT JOIN szülő az „Szülő / gyerek” felirathoz).
 *
 * @return list<array<string,mixed>>
 */
function events_public_event_category_rows(PDO $db, int $eventId): array {
    $st = $db->prepare('
        SELECT
            c.`id`,
            c.`name`,
            c.`name_en`,
            c.`parent_id`,
            c.`sort_order`,
            c.`color`,
            p.`name` AS `parent_name`,
            p.`name_en` AS `parent_name_en`
        FROM `events_calendar_event_categories` ec
        INNER JOIN `events_categories` c ON c.`id` = ec.`category_id`
        LEFT JOIN `events_categories` p ON p.`id` = c.`parent_id`
        WHERE ec.`event_id` = ?
        ORDER BY c.`sort_order` ASC, c.`name` ASC, c.`id` ASC
    ');
    $st->execute([$eventId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    return $rows !== false ? array_values($rows) : [];
}

/**
 * Chipen / listán: szülő létezik és van neve → „Szülő / gyerek” (lokalizálva).
 *
 * @param array<string,mixed> $row events_public_event_category_rows egy eleme
 */
function events_public_category_chip_label(string $lang, array $row): string {
    $leafHu = (string) ($row['name'] ?? '');
    $leafEn = (string) ($row['name_en'] ?? '');
    $pid = isset($row['parent_id']) && $row['parent_id'] !== null ? (int) $row['parent_id'] : 0;
    $pHu = trim((string) ($row['parent_name'] ?? ''));
    $pEn = (string) ($row['parent_name_en'] ?? '');
    $leafLoc = events_category_locale_label($lang, $leafHu, $leafEn);
    if ($pid > 0 && $pHu !== '') {
        return events_category_locale_label($lang, $pHu, $pEn) . ' / ' . $leafLoc;
    }

    return $leafLoc;
}

/**
 * Nyilvános „pill” háttér és keret RGBA (hex → biztonságos inline style részletek nélkül XSS-nek).
 */
function events_public_category_pill_inline_style(string $rawHex): string {
    $hex = strtoupper(trim($rawHex));
    if (!preg_match('/^#[0-9A-F]{6}$/', $hex)) {
        $hex = '#6D8F63';
    }
    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));

    return sprintf(
        'color:#1e281a;background:rgba(%1$d,%2$d,%3$d,0.22);border:1px solid rgba(%1$d,%2$d,%3$d,0.5);box-shadow:0 2px 10px rgba(%1$d,%2$d,%3$d,0.12)',
        $r,
        $g,
        $b
    );
}
