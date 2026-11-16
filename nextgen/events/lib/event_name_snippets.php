<?php
declare(strict_types=1);

const EVENTS_NAME_SNIPPET_LABEL_MAX = 120;

function events_name_snippets_table_available(PDO $db, bool $forceRefresh = false): bool
{
    static $cached = null;
    if (!$forceRefresh && $cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `events_name_snippets` LIMIT 1');
        $cached = true;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}

function events_name_snippets_ensure_schema(PDO $db): bool
{
    static $done = false;
    if ($done) {
        return true;
    }

    try {
        if ($db->inTransaction()) {
            return events_name_snippets_table_available($db);
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS `events_name_snippets` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `label` VARCHAR(120) NOT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_events_name_snippets_label` (`label`),
                KEY `idx_events_name_snippets_sort` (`sort_order`, `id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        events_name_snippets_table_available($db, true);
    } catch (Throwable $e) {
        error_log('events_name_snippets_ensure_schema: ' . $e->getMessage());

        return false;
    }

    if (!events_name_snippets_table_available($db, true)) {
        return false;
    }

    $done = true;

    return true;
}

/**
 * @return list<array{id: int, label: string, sort_order: int}>
 */
function events_name_snippets_list(PDO $db): array
{
    if (!events_name_snippets_table_available($db)) {
        return [];
    }

    try {
        $rows = $db->query('
            SELECT `id`, `label`, `sort_order`
            FROM `events_name_snippets`
            ORDER BY `sort_order` ASC, `id` ASC
        ')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('events_name_snippets_list: ' . $e->getMessage());

        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $label = trim((string) ($row['label'] ?? ''));
        if ($label === '') {
            continue;
        }
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'label' => $label,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ];
    }

    return $out;
}

function events_name_snippets_next_sort(PDO $db): int
{
    if (!events_name_snippets_table_available($db)) {
        return 10;
    }
    try {
        $current = (int) $db->query('SELECT COALESCE(MAX(`sort_order`), 0) FROM `events_name_snippets`')->fetchColumn();
    } catch (Throwable) {
        return 10;
    }

    return min(9999, $current + 10);
}

function events_name_snippet_normalize_label(string $label): string
{
    $normalized = preg_replace('/\s+/u', ' ', $label);

    return trim($normalized ?? '');
}

/**
 * A megadott azonosítók sorrendje lesz a megjelenési sorrend.
 *
 * @param list<int> $ids
 */
function events_name_snippets_reorder(PDO $db, array $ids): bool
{
    $ordered = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id <= 0 || isset($ordered[$id])) {
            continue;
        }
        $ordered[$id] = $id;
    }
    $ordered = array_values($ordered);
    if ($ordered === []) {
        return false;
    }

    $existing = [];
    foreach (events_name_snippets_list($db) as $row) {
        $existing[] = (int) $row['id'];
    }
    $expected = $existing;
    $posted = $ordered;
    sort($expected);
    sort($posted);
    if ($expected === [] || $expected !== $posted) {
        return false;
    }

    $ownsTransaction = !$db->inTransaction();
    try {
        if ($ownsTransaction) {
            $db->beginTransaction();
        }
        $upd = $db->prepare('UPDATE `events_name_snippets` SET `sort_order` = ? WHERE `id` = ?');
        $count = count($ordered);
        $step = $count > 999 ? 1 : 10;
        foreach ($ordered as $index => $id) {
            $sort = ($index + 1) * $step;
            if ($sort > 9999) {
                $sort = 9999;
            }
            $upd->execute([$sort, $id]);
        }
        if ($ownsTransaction) {
            $db->commit();
        }
    } catch (Throwable $e) {
        if ($ownsTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('events_name_snippets_reorder: ' . $e->getMessage());

        return false;
    }

    return true;
}

/**
 * @return array{ok: bool, error: ?string, id: int, label: string}
 */
function events_name_snippet_save(PDO $db, int $id, string $label, ?int $sortOrder = null): array
{
    $label = events_name_snippet_normalize_label($label);
    $fail = static function (string $error) use ($id, $label): array {
        return ['ok' => false, 'error' => $error, 'id' => max(0, $id), 'label' => $label];
    };

    if ($label === '') {
        return $fail('A gyorsszöveg nem lehet üres.');
    }
    if (mb_strlen($label, 'UTF-8') > EVENTS_NAME_SNIPPET_LABEL_MAX) {
        return $fail('A gyorsszöveg legfeljebb ' . EVENTS_NAME_SNIPPET_LABEL_MAX . ' karakter lehet.');
    }

    if ($sortOrder === null) {
        if ($id > 0) {
            $current = $db->prepare('SELECT `sort_order` FROM `events_name_snippets` WHERE `id` = ? LIMIT 1');
            $current->execute([$id]);
            $currentSort = $current->fetchColumn();
            $sortOrder = $currentSort === false ? 0 : (int) $currentSort;
        } else {
            $sortOrder = events_name_snippets_next_sort($db);
        }
    }
    $sortOrder = max(0, min(9999, $sortOrder));

    $dup = $db->prepare('SELECT `id` FROM `events_name_snippets` WHERE `label` = ? AND `id` <> ? LIMIT 1');
    $dup->execute([$label, max(0, $id)]);
    if ($dup->fetchColumn() !== false) {
        return $fail('Ez a gyorsszöveg már szerepel a listában.');
    }

    if ($id > 0) {
        $exists = $db->prepare('SELECT `id` FROM `events_name_snippets` WHERE `id` = ? LIMIT 1');
        $exists->execute([$id]);
        if ($exists->fetchColumn() === false) {
            return $fail('A gyorsszöveg nem található.');
        }
        $upd = $db->prepare('UPDATE `events_name_snippets` SET `label` = ?, `sort_order` = ? WHERE `id` = ?');
        $upd->execute([$label, $sortOrder, $id]);

        return ['ok' => true, 'error' => null, 'id' => $id, 'label' => $label];
    }

    $ins = $db->prepare('INSERT INTO `events_name_snippets` (`label`, `sort_order`) VALUES (?, ?)');
    $ins->execute([$label, $sortOrder]);

    return ['ok' => true, 'error' => null, 'id' => (int) $db->lastInsertId(), 'label' => $label];
}

function events_name_snippet_delete(PDO $db, int $id): ?string
{
    if ($id <= 0) {
        return null;
    }
    $st = $db->prepare('SELECT `label` FROM `events_name_snippets` WHERE `id` = ? LIMIT 1');
    $st->execute([$id]);
    $label = $st->fetchColumn();
    if ($label === false) {
        return null;
    }
    $del = $db->prepare('DELETE FROM `events_name_snippets` WHERE `id` = ?');
    $del->execute([$id]);

    return (string) $label;
}
