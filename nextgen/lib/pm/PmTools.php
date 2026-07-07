<?php
declare(strict_types=1);

require_once __DIR__ . '/page_catalog.php';

final class PmTools
{
    public static function ensureSchema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `nextgen_pm_settings` (
                `key` VARCHAR(64) NOT NULL,
                `value` TEXT NOT NULL,
                PRIMARY KEY (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `nextgen_pm_pages` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `php_path` VARCHAR(512) NOT NULL,
                `display_name` VARCHAR(255) NOT NULL DEFAULT "",
                `purpose` TEXT NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_pm_pages_php_path` (`php_path`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `nextgen_pm_page_notes` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `page_id` INT UNSIGNED NOT NULL,
                `note_text` TEXT NOT NULL,
                `response_text` TEXT NOT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_pm_page_notes_page` (`page_id`, `sort_order`),
                CONSTRAINT `fk_pm_page_notes_page` FOREIGN KEY (`page_id`) REFERENCES `nextgen_pm_pages` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $stmt = $pdo->prepare('INSERT IGNORE INTO `nextgen_pm_settings` (`key`, `value`) VALUES (:key, :value)');
        $stmt->execute([':key' => 'overlay_enabled', ':value' => '1']);
    }

    public static function isOverlayEnabled(PDO $pdo): bool
    {
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare('SELECT value FROM `nextgen_pm_settings` WHERE `key` = :key');
        $stmt->execute([':key' => 'overlay_enabled']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return ($row['value'] ?? '0') === '1';
    }

    public static function setOverlayEnabled(PDO $pdo, bool $enabled): void
    {
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO `nextgen_pm_settings` (`key`, `value`) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $stmt->execute([
            ':key' => 'overlay_enabled',
            ':value' => $enabled ? '1' : '0',
        ]);
    }

    public static function normalizePhpPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $base = rtrim(defined('BASE_URL') ? (string) BASE_URL : '', '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        if ($path === '') {
            return '/';
        }
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return $path;
    }

    public static function getOrCreatePage(PDO $pdo, string $phpPath): array
    {
        self::ensureSchema($pdo);
        $phpPath = self::normalizePhpPath($phpPath);

        $stmt = $pdo->prepare('SELECT * FROM `nextgen_pm_pages` WHERE php_path = :path');
        $stmt->execute([':path' => $phpPath]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return $row;
        }

        $insert = $pdo->prepare(
            'INSERT INTO `nextgen_pm_pages` (php_path, display_name, purpose) VALUES (:path, :name, :purpose)'
        );
        $insert->execute([
            ':path' => $phpPath,
            ':name' => self::catalogDisplayName($phpPath),
            ':purpose' => self::catalogPurpose($phpPath),
        ]);

        $stmt->execute([':path' => $phpPath]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [
            'id' => (int) $pdo->lastInsertId(),
            'php_path' => $phpPath,
            'display_name' => self::catalogDisplayName($phpPath),
            'purpose' => self::catalogPurpose($phpPath),
        ];
    }

    public static function catalogMeta(string $phpPath): ?array
    {
        $phpPath = self::normalizePhpPath($phpPath);
        $catalog = pm_tools_page_catalog();

        return $catalog[$phpPath] ?? null;
    }

    public static function catalogDisplayName(string $phpPath): string
    {
        $meta = self::catalogMeta($phpPath);

        return $meta['display_name'] ?? basename(self::normalizePhpPath($phpPath));
    }

    public static function catalogPurpose(string $phpPath): string
    {
        $meta = self::catalogMeta($phpPath);

        return $meta['purpose'] ?? '';
    }

    public static function syncCatalogMetadata(PDO $pdo): void
    {
        self::ensureSchema($pdo);
        $catalog = pm_tools_page_catalog();
        $stmt = $pdo->prepare(
            'UPDATE `nextgen_pm_pages`
             SET display_name = :name, purpose = :purpose, updated_at = CURRENT_TIMESTAMP
             WHERE php_path = :path'
        );

        foreach ($catalog as $path => $meta) {
            $stmt->execute([
                ':path' => self::normalizePhpPath($path),
                ':name' => (string) ($meta['display_name'] ?? basename($path)),
                ':purpose' => (string) ($meta['purpose'] ?? ''),
            ]);
        }
    }

    /** @return list<array<string,mixed>> */
    public static function listPagesWithNotes(PDO $pdo, bool $unansweredOnly = false): array
    {
        self::ensureSchema($pdo);
        $sql = 'SELECT DISTINCT p.id, p.php_path, p.display_name, p.purpose, p.created_at, p.updated_at
                FROM `nextgen_pm_pages` p
                INNER JOIN `nextgen_pm_page_notes` n ON n.page_id = p.id
                WHERE TRIM(n.note_text) != \'\'';
        if ($unansweredOnly) {
            $sql .= ' AND TRIM(n.response_text) = \'\'';
        }
        $sql .= ' ORDER BY p.php_path ASC';

        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function countUnansweredNotes(PDO $pdo, int $pageId): int
    {
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM `nextgen_pm_page_notes`
             WHERE page_id = :page_id AND TRIM(note_text) != \'\' AND TRIM(response_text) = \'\''
        );
        $stmt->execute([':page_id' => $pageId]);

        return (int) $stmt->fetchColumn();
    }

    public static function countTotalUnansweredPages(PDO $pdo): int
    {
        return count(self::listPagesWithNotes($pdo, true));
    }

    public static function noteRowIsUnanswered(string $noteText, string $responseText): bool
    {
        return trim($noteText) !== '' && trim($responseText) === '';
    }

    /** @return list<array<string,mixed>> */
    public static function listNotesForPage(PDO $pdo, int $pageId): array
    {
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare(
            'SELECT id, note_text, response_text, sort_order
             FROM `nextgen_pm_page_notes`
             WHERE page_id = :page_id
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([':page_id' => $pageId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param list<array{note:string,response:string}> $rows
     */
    public static function saveNotes(PDO $pdo, int $pageId, array $rows): void
    {
        self::ensureSchema($pdo);
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM `nextgen_pm_page_notes` WHERE page_id = :page_id');
            $del->execute([':page_id' => $pageId]);

            $ins = $pdo->prepare(
                'INSERT INTO `nextgen_pm_page_notes` (page_id, note_text, response_text, sort_order)
                 VALUES (:page_id, :note, :response, :sort)'
            );
            $sort = 0;
            foreach ($rows as $row) {
                $note = trim((string) ($row['note'] ?? ''));
                $response = trim((string) ($row['response'] ?? ''));
                if ($note === '' && $response === '') {
                    continue;
                }
                $ins->execute([
                    ':page_id' => $pageId,
                    ':note' => $note,
                    ':response' => $response,
                    ':sort' => $sort++,
                ]);
            }

            $upd = $pdo->prepare('UPDATE `nextgen_pm_pages` SET updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $upd->execute([':id' => $pageId]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function updatePage(
        PDO $pdo,
        int $pageId,
        string $displayName,
        string $purpose
    ): void {
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare(
            'UPDATE `nextgen_pm_pages`
             SET display_name = :name, purpose = :purpose, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $pageId,
            ':name' => trim($displayName),
            ':purpose' => trim($purpose),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public static function listAllPages(PDO $pdo): array
    {
        self::ensureSchema($pdo);
        $stmt = $pdo->query(
            'SELECT id, php_path, display_name, purpose, created_at, updated_at
             FROM `nextgen_pm_pages`
             ORDER BY php_path ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getPageById(PDO $pdo, int $pageId): ?array
    {
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare('SELECT * FROM `nextgen_pm_pages` WHERE id = :id');
        $stmt->execute([':id' => $pageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public static function scanPhpFiles(PDO $pdo, string $rootDir): int
    {
        self::ensureSchema($pdo);
        $rootDir = realpath($rootDir) ?: $rootDir;
        $added = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootDir, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $fullPath = str_replace('\\', '/', $file->getPathname());
            $relative = str_replace('\\', '/', substr($fullPath, strlen($rootDir)));
            $relative = ltrim($relative, '/');

            if (self::shouldSkipScannedPath($relative)) {
                continue;
            }

            $webPath = '/' . $relative;
            $stmt = $pdo->prepare('SELECT id FROM `nextgen_pm_pages` WHERE php_path = :path');
            $stmt->execute([':path' => $webPath]);
            if ($stmt->fetch()) {
                continue;
            }

            $insert = $pdo->prepare(
                'INSERT INTO `nextgen_pm_pages` (php_path, display_name, purpose) VALUES (:path, :name, :purpose)'
            );
            $insert->execute([
                ':path' => $webPath,
                ':name' => self::catalogDisplayName($webPath),
                ':purpose' => self::catalogPurpose($webPath),
            ]);
            $added++;
        }

        return $added;
    }

    private static function shouldSkipScannedPath(string $relative): bool
    {
        $skipPrefixes = [
            '.git/',
            'vendor/',
            'node_modules/',
            'nextgen/core/config.local.php',
        ];
        foreach ($skipPrefixes as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return str_contains($relative, '/vendor/') || str_contains($relative, '/node_modules/');
    }
}
