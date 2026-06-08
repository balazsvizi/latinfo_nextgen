<?php
declare(strict_types=1);

require_once __DIR__ . '/html_security.php';

/**
 * Publikus esemény főoldal szerkeszthető HTML blokkjai (felül / alul).
 */

function events_public_home_table_available(PDO $db): bool {
    try {
        $db->query('SELECT 1 FROM `events_public_home` LIMIT 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

/**
 * @return array{content_top: string, content_bottom: string}
 */
function events_public_home_load(PDO $db): array {
    if (!events_public_home_table_available($db)) {
        return ['content_top' => '', 'content_bottom' => ''];
    }
    try {
        $row = $db->query('SELECT `content_top`, `content_bottom` FROM `events_public_home` WHERE `id` = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return ['content_top' => '', 'content_bottom' => ''];
        }

        return [
            'content_top' => (string) ($row['content_top'] ?? ''),
            'content_bottom' => (string) ($row['content_bottom'] ?? ''),
        ];
    } catch (Throwable $e) {
        error_log('events_public_home_load: ' . $e->getMessage());

        return ['content_top' => '', 'content_bottom' => ''];
    }
}

function events_public_home_save(PDO $db, string $contentTop, string $contentBottom): void {
    if (!events_public_home_table_available($db)) {
        throw new RuntimeException('Hiányzik az events_public_home tábla.');
    }
    $top = events_sanitize_html_fragment($contentTop);
    $bottom = events_sanitize_html_fragment($contentBottom);
    $st = $db->prepare('
        INSERT INTO `events_public_home` (`id`, `content_top`, `content_bottom`)
        VALUES (1, ?, ?)
        ON DUPLICATE KEY UPDATE `content_top` = VALUES(`content_top`), `content_bottom` = VALUES(`content_bottom`)
    ');
    $st->execute([$top, $bottom]);
}
