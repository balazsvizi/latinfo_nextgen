<?php
declare(strict_types=1);

/**
 * Helyi DB felzárkóztatás a nextgen/events/sql migrációkkal.
 * A CREATE/ALTER utasításokból kiszedi a FOREIGN KEY constraint-eket
 * (az alatinfo usernek gyakran nincs REFERENCES joga).
 *
 * Futtatás: php tools/_migrate_events_local.php
 */
require_once dirname(__DIR__) . '/nextgen/core/database.php';

$db = getDb();
$sqlDir = dirname(__DIR__) . '/nextgen/events/sql';

/**
 * @return list<string>
 */
function split_sql_statements(string $sql): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $parts = preg_split('/;\s*[\r\n]+/', $sql) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '' || strcasecmp($part, 'SET NAMES utf8mb4') === 0) {
            continue;
        }
        $out[] = $part;
    }
    return $out;
}

function strip_foreign_keys(string $stmt): string
{
    // CONSTRAINT ... FOREIGN KEY (...) REFERENCES ...
    $stmt = preg_replace(
        '/,\s*CONSTRAINT\s+`[^`]+`\s+FOREIGN KEY\s*\([^)]+\)\s*REFERENCES\s+`[^`]+`\s*\([^)]+\)(?:\s+ON\s+(?:DELETE|UPDATE)\s+[A-Z\s]+)*(?=\s*[,)])/i',
        '',
        $stmt
    ) ?? $stmt;
    // standalone ALTER ... ADD CONSTRAINT FOREIGN KEY — skip entirely elsewhere
    return $stmt;
}

/**
 * @param list<string> $files
 */
function run_migration_files(PDO $db, string $sqlDir, array $files): void
{
    foreach ($files as $file) {
        $path = $sqlDir . DIRECTORY_SEPARATOR . $file;
        echo "\n=== {$file} ===\n";
        if (!is_file($path)) {
            echo "SKIP missing file\n";
            continue;
        }
        $raw = (string) file_get_contents($path);
        foreach (split_sql_statements($raw) as $i => $stmt) {
            // FK drop/add often fails for this user — handle specially
            if (preg_match('/\bFOREIGN KEY\b/i', $stmt) && preg_match('/^\s*ALTER\s+TABLE\b/i', $stmt)) {
                echo 'SKIP FK ALTER #' . ($i + 1) . "\n";
                continue;
            }
            $exec = strip_foreign_keys($stmt);
            try {
                $db->exec($exec);
                echo 'OK #' . ($i + 1) . "\n";
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                // Already applied / wrong legacy FK name / etc.
                if (
                    str_contains($msg, 'Duplicate column')
                    || str_contains($msg, 'Duplicate key')
                    || str_contains($msg, 'already exists')
                    || str_contains($msg, "Can't DROP")
                    || str_contains($msg, 'check that column/key exists')
                    || str_contains($msg, 'Unknown key')
                    || str_contains($msg, '1091')
                    || str_contains($msg, '1060')
                    || str_contains($msg, '1061')
                ) {
                    echo 'SKIP #' . ($i + 1) . ': ' . $msg . "\n";
                    continue;
                }
                echo 'WARN #' . ($i + 1) . ': ' . $msg . "\n";
            }
        }
    }
}

// Sorrend: alap → datetime → junction → venues → categories (már megvan, IF NOT EXISTS) → egyéb oszlopok
$files = [
    'migration_event_datetime_columns.sql',
    'migration_event_organizers_junction.sql',
    'migration_venues.sql',
    'migration_categories.sql',
    'migration_event_change.sql',
    'migration_event_featured_image.sql',
    'migration_tags.sql',
    'migration_styles.sql',
    'migration_tag_types_registry.sql',
];

run_migration_files($db, $sqlDir, $files);

// Extra oszlopok, ha külön migráció nincs
$extraAlters = [
    "ALTER TABLE `events_calendar_events` ADD COLUMN `event_published_at` DATETIME NULL DEFAULT NULL COMMENT 'Első közzététel ideje' AFTER `event_status`",
];
echo "\n=== extra columns ===\n";
foreach ($extraAlters as $i => $stmt) {
    try {
        $db->exec($stmt);
        echo 'OK extra #' . ($i + 1) . "\n";
    } catch (PDOException $e) {
        echo 'SKIP extra #' . ($i + 1) . ': ' . $e->getMessage() . "\n";
    }
}

// organizer_id drop külön, a tényleges FK névvel
echo "\n=== drop legacy organizer_id ===\n";
foreach ([
    'ALTER TABLE `events_calendar_events` DROP FOREIGN KEY `fk_naptar_esemeny_organizer`',
    'ALTER TABLE `events_calendar_events` DROP FOREIGN KEY `fk_events_calendar_event_organizer`',
    'ALTER TABLE `events_calendar_events` DROP COLUMN `organizer_id`',
] as $i => $stmt) {
    try {
        $db->exec($stmt);
        echo 'OK drop #' . ($i + 1) . "\n";
    } catch (PDOException $e) {
        echo 'SKIP drop #' . ($i + 1) . ': ' . $e->getMessage() . "\n";
    }
}

echo "\n=== verify ===\n";
$cols = $db->query('SHOW COLUMNS FROM `events_calendar_events`')->fetchAll(PDO::FETCH_COLUMN);
echo 'events_calendar_events: ' . implode(', ', $cols) . "\n";
$tables = $db->query("SHOW TABLES LIKE 'events_%'")->fetchAll(PDO::FETCH_COLUMN);
sort($tables);
echo 'tables: ' . implode(', ', $tables) . "\n";

try {
    $n = (int) $db->query('SELECT COUNT(*) FROM `events_calendar_events`')->fetchColumn();
    $db->query('SELECT e.id, e.event_start FROM `events_calendar_events` e LEFT JOIN `events_venues` v ON v.id = e.venue_id LIMIT 1');
    $db->query('SELECT 1 FROM `events_calendar_event_organizers` LIMIT 1');
    $db->query('SELECT 1 FROM `events_categories` LIMIT 1');
    echo "smoke=OK events={$n}\n";
} catch (PDOException $e) {
    echo 'smoke=FAIL ' . $e->getMessage() . "\n";
}
