<?php
declare(strict_types=1);

/**
 * Szervezői és esemény-szintű finance mezők (belépőjegy %, fix díj, ki fizeti).
 */

function events_organizer_finance_ticket_percent_max(): int
{
    return 500;
}

function events_organizer_finance_ticket_percent_default(): int
{
    return 200;
}

function events_organizer_finance_ensure_schema(PDO $db): bool
{
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `events_organizers` LIKE 'finance_ticket_percent'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($col === false) {
            $db->exec('ALTER TABLE `events_organizers` ADD COLUMN `finance_ticket_percent` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `name`');
        } elseif (stripos((string) ($col['Type'] ?? ''), 'tinyint') !== false) {
            $db->exec('ALTER TABLE `events_organizers` MODIFY COLUMN `finance_ticket_percent` SMALLINT UNSIGNED NULL DEFAULT NULL');
        }
        $stmt = $db->query("SHOW COLUMNS FROM `events_organizers` LIKE 'finance_fix_amount'");
        if ($stmt->fetch() === false) {
            $db->exec('ALTER TABLE `events_organizers` ADD COLUMN `finance_fix_amount` DECIMAL(12,2) NULL DEFAULT NULL AFTER `finance_ticket_percent`');
        }
        $stmt = $db->query("SHOW COLUMNS FROM `events_calendar_events` LIKE 'finance_payer_organizer_id'");
        if ($stmt->fetch() === false) {
            $db->exec('ALTER TABLE `events_calendar_events` ADD COLUMN `finance_payer_organizer_id` INT UNSIGNED NULL DEFAULT NULL AFTER `event_cost_to`');
        }
        $stmt = $db->query("SHOW COLUMNS FROM `events_calendar_events` LIKE 'finance_note'");
        if ($stmt->fetch() === false) {
            $db->exec('ALTER TABLE `events_calendar_events` ADD COLUMN `finance_note` TEXT NULL DEFAULT NULL AFTER `finance_payer_organizer_id`');
        }

        return true;
    } catch (Throwable $ex) {
        error_log('events_organizer_finance_ensure_schema: ' . $ex->getMessage());

        return false;
    }
}

/**
 * @return array{percent: ?int, fix_amount: ?float, error: ?string}
 */
function events_organizer_finance_parse_from_post(): array
{
    $percentRaw = trim((string) ($_POST['finance_ticket_percent'] ?? ''));
    $fixRaw = trim((string) ($_POST['finance_fix_amount'] ?? ''));

    $percent = null;
    $percentMax = events_organizer_finance_ticket_percent_max();
    if ($percentRaw !== '') {
        if (!preg_match('/^\d+$/', $percentRaw)) {
            return ['percent' => null, 'fix_amount' => null, 'error' => 'A belépőjegy százalék csak egész szám lehet (1–' . $percentMax . ').'];
        }
        $percent = (int) $percentRaw;
        if ($percent < 1 || $percent > $percentMax) {
            return ['percent' => null, 'fix_amount' => null, 'error' => 'A belépőjegy százalék 1 és ' . $percentMax . ' között lehet.'];
        }
    }

    $fixAmount = null;
    if ($fixRaw !== '') {
        $fixNorm = str_replace([' ', ','], ['', '.'], $fixRaw);
        if (!is_numeric($fixNorm)) {
            return ['percent' => null, 'fix_amount' => null, 'error' => 'A fix összeg csak szám lehet.'];
        }
        $fixAmount = round((float) $fixNorm, 2);
        if ($fixAmount < 0) {
            return ['percent' => null, 'fix_amount' => null, 'error' => 'A fix összeg nem lehet negatív.'];
        }
    }

    return ['percent' => $percent, 'fix_amount' => $fixAmount, 'error' => null];
}

/**
 * @return list<int>
 */
function events_finance_payer_organizer_ids_from_post(): array
{
    $raw = $_POST['finance_payer_organizer_ids'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $ids = [];
    foreach ($raw as $v) {
        $i = (int) $v;
        if ($i > 0 && !in_array($i, $ids, true)) {
            $ids[] = $i;
        }
    }

    return $ids;
}

function events_finance_normalize_note(string $raw): string
{
    $note = trim($raw);
    if (strlen($note) > 5000) {
        $note = substr($note, 0, 5000);
    }

    return $note;
}

/**
 * @return array<int, array{finance_ticket_percent: ?int, finance_fix_amount: ?float}>
 */
function events_load_organizer_finance_map(PDO $db): array
{
    if (!events_organizer_finance_ensure_schema($db)) {
        return [];
    }
    $rows = $db->query('SELECT `id`, `finance_ticket_percent`, `finance_fix_amount` FROM `events_organizers`')->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $pct = $row['finance_ticket_percent'] ?? null;
        $fix = $row['finance_fix_amount'] ?? null;
        $out[$id] = [
            'finance_ticket_percent' => $pct !== null && $pct !== '' ? (int) $pct : null,
            'finance_fix_amount' => $fix !== null && $fix !== '' ? (float) $fix : null,
        ];
    }

    return $out;
}

/**
 * Szervezői díj kalkuláció: fix összeg elsőbbséget élvez, különben belépő átlag × % (hiányzó % → 200).
 */
function events_organizer_finance_calculate_fee(?float $fixAmount, ?int $percent, ?float $costFrom, ?float $costTo): ?float
{
    if ($fixAmount !== null && $fixAmount > 0) {
        return round($fixAmount, 2);
    }
    $from = $costFrom ?? 0.0;
    $to = $costTo ?? $from;
    if ($from <= 0 && $to <= 0) {
        return null;
    }
    $effectivePercent = ($percent !== null && $percent >= 1 && $percent <= events_organizer_finance_ticket_percent_max())
        ? $percent
        : events_organizer_finance_ticket_percent_default();
    $avg = ($from + $to) / 2;

    return round($avg * $effectivePercent / 100, 2);
}

/**
 * @param list<int> $payerIds
 * @return array{0: ?int, 1: ?string}
 */
function events_finance_validate_payer_organizer_ids(PDO $db, array $payerIds): array
{
    if (count($payerIds) > 1) {
        return [null, 'A „Ki fizeti” mezőben legfeljebb egy szervező adható meg.'];
    }
    if ($payerIds === []) {
        return [null, null];
    }
    $payerId = $payerIds[0];
    $st = $db->prepare('SELECT `id` FROM `events_organizers` WHERE `id` = ? LIMIT 1');
    $st->execute([$payerId]);
    if ($st->fetchColumn() === false) {
        return [null, 'A kiválasztott „Ki fizeti” szervező nem létezik.'];
    }

    return [$payerId, null];
}
