<?php
declare(strict_types=1);

require_once __DIR__ . '/html_security.php';

const EVENTS_PUBLIC_DJS_HUB_ANCHOR_BEFORE = 'djs-elott';
const EVENTS_PUBLIC_DJS_HUB_ANCHOR_AFTER = 'djs-statisztikak-utan';

function events_public_djs_hub_table_available(PDO $db): bool
{
    try {
        $db->query('SELECT 1 FROM `events_public_djs_hub` LIMIT 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

function events_public_djs_hub_ensure_schema(PDO $db): bool
{
    static $done = false;
    if ($done) {
        return true;
    }

    try {
        if (!events_public_djs_hub_table_available($db)) {
            $db->exec('
                CREATE TABLE IF NOT EXISTS `events_public_djs_hub` (
                    `id` TINYINT UNSIGNED NOT NULL,
                    `content_before` MEDIUMTEXT NOT NULL,
                    `content_after` MEDIUMTEXT NOT NULL,
                    `updated_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
        }
    } catch (Throwable $e) {
        error_log('events_public_djs_hub_ensure_schema: ' . $e->getMessage());

        return false;
    }

    if (!events_public_djs_hub_table_available($db)) {
        return false;
    }

    $done = true;

    return true;
}

/**
 * @return array{content_before: string, content_after: string}
 */
function events_public_djs_hub_load(PDO $db): array
{
    $empty = [
        'content_before' => '',
        'content_after' => '',
    ];
    if (!events_public_djs_hub_ensure_schema($db)) {
        return $empty;
    }

    try {
        $row = $db->query('SELECT `content_before`, `content_after` FROM `events_public_djs_hub` WHERE `id` = 1 LIMIT 1')
            ->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return $empty;
        }

        return [
            'content_before' => events_sanitize_html_fragment((string) ($row['content_before'] ?? '')),
            'content_after' => events_sanitize_html_fragment((string) ($row['content_after'] ?? '')),
        ];
    } catch (Throwable $e) {
        error_log('events_public_djs_hub_load: ' . $e->getMessage());

        return $empty;
    }
}

function events_public_djs_hub_save(PDO $db, string $contentBefore, string $contentAfter): void
{
    if (!events_public_djs_hub_ensure_schema($db)) {
        throw new RuntimeException('A DJ oldal szövegei nem menthetők.');
    }

    $before = events_sanitize_html_fragment($contentBefore);
    $after = events_sanitize_html_fragment($contentAfter);
    $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

    $st = $db->prepare('
        INSERT INTO `events_public_djs_hub` (`id`, `content_before`, `content_after`, `updated_at`)
        VALUES (1, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            `content_before` = VALUES(`content_before`),
            `content_after` = VALUES(`content_after`),
            `updated_at` = VALUES(`updated_at`)
    ');
    $st->execute([$before, $after, $now]);
}

function events_public_djs_hub_anchor_url(string $anchor): string
{
    $base = rtrim(events_public_djs_hub_canonical_url(), '/');

    return $base . '/#' . ltrim($anchor, '#');
}
