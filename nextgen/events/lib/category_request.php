<?php
declare(strict_types=1);

/**
 * @return list<array{id: int, event_name: string, event_status: string, event_start: ?string}>
 */
function events_category_linked_events(PDO $db, int $categoryId): array {
    if ($categoryId <= 0) {
        return [];
    }
    try {
        $st = $db->prepare('
            SELECT e.`id`, e.`event_name`, e.`event_status`, e.`event_start`
            FROM `events_calendar_events` e
            INNER JOIN `events_calendar_event_categories` ec ON ec.`event_id` = e.`id`
            WHERE ec.`category_id` = ?
            ORDER BY e.`event_start` IS NULL, e.`event_start` DESC, e.`event_name` ASC, e.`id` DESC
        ');
        $st->execute([$categoryId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'event_name' => (string) ($row['event_name'] ?? ''),
                'event_status' => (string) ($row['event_status'] ?? ''),
                'event_start' => isset($row['event_start']) && $row['event_start'] !== null
                    ? (string) $row['event_start']
                    : null,
            ];
        }

        return $out;
    } catch (Throwable $e) {
        error_log('events_category_linked_events: ' . $e->getMessage());

        return [];
    }
}

/**
 * Forrás kategória összes esemény-kapcsolatának áthelyezése a cél kategóriába.
 *
 * @return array{0: bool, 1: string, 2: int} [siker, üzenet, érintett kapcsolatok száma]
 */
function events_category_migrate_all_events(PDO $db, int $fromCategoryId, int $toCategoryId): array {
    if ($fromCategoryId <= 0 || $toCategoryId <= 0) {
        return [false, 'Érvénytelen kategória azonosító.', 0];
    }
    if ($fromCategoryId === $toCategoryId) {
        return [false, 'A forrás és a cél kategória nem lehet ugyanaz.', 0];
    }

    $stFrom = $db->prepare('SELECT `name` FROM `events_categories` WHERE `id` = ? LIMIT 1');
    $stFrom->execute([$fromCategoryId]);
    $fromRow = $stFrom->fetch(PDO::FETCH_ASSOC);
    if (!$fromRow) {
        return [false, 'A forrás kategória nem található.', 0];
    }

    $stTo = $db->prepare('SELECT `name` FROM `events_categories` WHERE `id` = ? LIMIT 1');
    $stTo->execute([$toCategoryId]);
    $toRow = $stTo->fetch(PDO::FETCH_ASSOC);
    if (!$toRow) {
        return [false, 'A cél kategória nem található.', 0];
    }

    $fromName = (string) ($fromRow['name'] ?? ('#' . $fromCategoryId));
    $toName = (string) ($toRow['name'] ?? ('#' . $toCategoryId));

    try {
        $countSt = $db->prepare('SELECT COUNT(*) FROM `events_calendar_event_categories` WHERE `category_id` = ?');
        $countSt->execute([$fromCategoryId]);
        $beforeCount = (int) $countSt->fetchColumn();
        if ($beforeCount === 0) {
            return [false, 'A forrás kategóriához nincs esemény rendelve.', 0];
        }

        $db->beginTransaction();

        $delDup = $db->prepare('
            DELETE ec_from FROM `events_calendar_event_categories` ec_from
            INNER JOIN `events_calendar_event_categories` ec_to
                ON ec_to.`event_id` = ec_from.`event_id` AND ec_to.`category_id` = ?
            WHERE ec_from.`category_id` = ?
        ');
        $delDup->execute([$toCategoryId, $fromCategoryId]);
        $deduped = $delDup->rowCount();

        $upd = $db->prepare('
            UPDATE `events_calendar_event_categories`
            SET `category_id` = ?
            WHERE `category_id` = ?
        ');
        $upd->execute([$toCategoryId, $fromCategoryId]);
        $migrated = $upd->rowCount();

        $db->commit();

        $total = $migrated + $deduped;
        $msg = $total . ' esemény kapcsolata áthelyezve: „' . $fromName . '” → „' . $toName . '”.';
        if ($deduped > 0) {
            $msg .= ' (' . $deduped . ' eseménynél a cél kategória már megvolt, a forrás kapcsolat törölve.)';
        }

        return [true, $msg, $total];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('events_category_migrate_all_events: ' . $e->getMessage());

        return [false, 'Az áthelyezés sikertelen. Próbáld újra később.', 0];
    }
}
