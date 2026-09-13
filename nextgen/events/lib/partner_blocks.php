<?php
declare(strict_types=1);

require_once __DIR__ . '/html_security.php';

/**
 * „Partnereink” publikus oldal blokkjai: partner, cím és HTML blokk,
 * kézzel állítható sorrenddel. Az EN mezők üresen a magyar tartalomra esnek vissza.
 */

const EVENTS_PARTNER_BLOCK_TYPES = ['partner', 'heading', 'html'];
const EVENTS_PARTNER_BLOCK_SORT_STEP = 10;

/**
 * @return array<string, string>
 */
function events_partner_block_type_labels(): array {
    return [
        'partner' => 'Partner',
        'heading' => 'Cím',
        'html' => 'HTML blokk',
    ];
}

function events_partner_block_type_label(string $type): string {
    return events_partner_block_type_labels()[$type] ?? $type;
}

function events_partner_blocks_table_available(PDO $db): bool {
    try {
        $db->query('SELECT 1 FROM `events_partner_blocks` LIMIT 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

/**
 * Tábla létrehozása, ha hiányzik (idempotens).
 */
function events_partner_blocks_ensure_schema(PDO $db): bool {
    static $done = false;
    if ($done) {
        return true;
    }
    if (events_partner_blocks_table_available($db)) {
        $done = true;

        return true;
    }

    try {
        $db->exec('
            CREATE TABLE IF NOT EXISTS `events_partner_blocks` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `block_type` VARCHAR(16) NOT NULL DEFAULT \'partner\',
                `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
                `is_visible` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                `title` VARCHAR(255) NOT NULL DEFAULT \'\',
                `title_en` VARCHAR(255) NOT NULL DEFAULT \'\',
                `heading_level` TINYINT UNSIGNED NOT NULL DEFAULT 2,
                `link_url` VARCHAR(500) NOT NULL DEFAULT \'\',
                `logo_url` VARCHAR(500) NOT NULL DEFAULT \'\',
                `body` MEDIUMTEXT NOT NULL,
                `body_en` MEDIUMTEXT NOT NULL,
                `note_before` MEDIUMTEXT NOT NULL,
                `note_before_en` MEDIUMTEXT NOT NULL,
                `note_after` MEDIUMTEXT NOT NULL,
                `note_after_en` MEDIUMTEXT NOT NULL,
                `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_events_partner_blocks_sort` (`sort_order`, `id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
        $done = true;

        return true;
    } catch (Throwable $e) {
        error_log('events_partner_blocks_ensure_schema: ' . $e->getMessage());

        return false;
    }
}

/**
 * @return list<array<string, mixed>>
 */
function events_partner_blocks_all(PDO $db, bool $onlyVisible = false): array {
    $sql = 'SELECT * FROM `events_partner_blocks`';
    if ($onlyVisible) {
        $sql .= ' WHERE `is_visible` = 1';
    }
    $sql .= ' ORDER BY `sort_order` ASC, `id` ASC';

    try {
        $st = $db->query($sql);

        return $st === false ? [] : $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('events_partner_blocks_all: ' . $e->getMessage());

        return [];
    }
}

/**
 * @return array<string, mixed>|null
 */
function events_partner_blocks_find(PDO $db, int $id): ?array {
    if ($id <= 0) {
        return null;
    }
    $st = $db->prepare('SELECT * FROM `events_partner_blocks` WHERE `id` = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function events_partner_blocks_normalize_type(string $type): string {
    return in_array($type, EVENTS_PARTNER_BLOCK_TYPES, true) ? $type : 'partner';
}

/**
 * Új blokk a lista végére, szerkesztésre készen.
 */
function events_partner_blocks_create(PDO $db, string $type): int {
    $type = events_partner_blocks_normalize_type($type);
    $next = (int) ($db->query('SELECT COALESCE(MAX(`sort_order`), 0) FROM `events_partner_blocks`')?->fetchColumn() ?: 0);

    $st = $db->prepare('
        INSERT INTO `events_partner_blocks`
            (`block_type`, `sort_order`, `is_visible`, `body`, `body_en`, `note_before`, `note_before_en`, `note_after`, `note_after_en`)
        VALUES (?, ?, 1, \'\', \'\', \'\', \'\', \'\', \'\')
    ');
    $st->execute([$type, $next + EVENTS_PARTNER_BLOCK_SORT_STEP]);

    return (int) $db->lastInsertId();
}

/**
 * Blokk mentése típus szerinti validációval.
 *
 * @param array<string, mixed> $input
 * @throws InvalidArgumentException érvénytelen bemenetnél
 */
function events_partner_blocks_save(PDO $db, int $id, array $input): void {
    $current = events_partner_blocks_find($db, $id);
    if ($current === null) {
        throw new InvalidArgumentException('A blokk nem található.');
    }
    $type = events_partner_blocks_normalize_type((string) ($current['block_type'] ?? 'partner'));

    $title = trim((string) ($input['title'] ?? ''));
    $titleEn = trim((string) ($input['title_en'] ?? ''));
    if (mb_strlen($title) > 255 || mb_strlen($titleEn) > 255) {
        throw new InvalidArgumentException('A név / cím legfeljebb 255 karakter lehet.');
    }

    if ($type === 'partner' && $title === '') {
        throw new InvalidArgumentException('A partner nevét kötelező megadni.');
    }
    if ($type === 'heading' && $title === '') {
        throw new InvalidArgumentException('A cím szövegét kötelező megadni.');
    }

    $headingLevel = (int) ($input['heading_level'] ?? 2);
    if (!in_array($headingLevel, [2, 3], true)) {
        $headingLevel = 2;
    }

    $linkUrl = '';
    $logoUrl = '';
    if ($type === 'partner') {
        [$normalizedLink, $linkError] = events_normalize_safe_url((string) ($input['link_url'] ?? ''), false);
        if ($linkError !== null) {
            throw new InvalidArgumentException('Partner link: ' . $linkError);
        }
        $linkUrl = (string) ($normalizedLink ?? '');

        [$normalizedLogo, $logoError] = events_normalize_safe_url((string) ($input['logo_url'] ?? ''), true);
        if ($logoError !== null) {
            throw new InvalidArgumentException('Logó URL: ' . $logoError);
        }
        $logoUrl = (string) ($normalizedLogo ?? '');
        if (mb_strlen($linkUrl) > 500 || mb_strlen($logoUrl) > 500) {
            throw new InvalidArgumentException('A link és a logó URL legfeljebb 500 karakter lehet.');
        }
    }

    $body = $type === 'html' ? events_sanitize_html_fragment((string) ($input['body'] ?? '')) : '';
    $bodyEn = $type === 'html' ? events_sanitize_html_fragment((string) ($input['body_en'] ?? '')) : '';
    $noteBefore = $type === 'partner' ? events_sanitize_html_fragment((string) ($input['note_before'] ?? '')) : '';
    $noteBeforeEn = $type === 'partner' ? events_sanitize_html_fragment((string) ($input['note_before_en'] ?? '')) : '';
    $noteAfter = $type === 'partner' ? events_sanitize_html_fragment((string) ($input['note_after'] ?? '')) : '';
    $noteAfterEn = $type === 'partner' ? events_sanitize_html_fragment((string) ($input['note_after_en'] ?? '')) : '';

    if ($type === 'html' && $body === '' && $bodyEn === '') {
        throw new InvalidArgumentException('A HTML blokk tartalma nem lehet üres.');
    }

    $st = $db->prepare('
        UPDATE `events_partner_blocks`
        SET `is_visible` = ?, `title` = ?, `title_en` = ?, `heading_level` = ?,
            `link_url` = ?, `logo_url` = ?,
            `body` = ?, `body_en` = ?,
            `note_before` = ?, `note_before_en` = ?, `note_after` = ?, `note_after_en` = ?
        WHERE `id` = ?
    ');
    $st->execute([
        empty($input['is_visible']) ? 0 : 1,
        $title,
        $titleEn,
        $headingLevel,
        $linkUrl,
        $logoUrl,
        $body,
        $bodyEn,
        $noteBefore,
        $noteBeforeEn,
        $noteAfter,
        $noteAfterEn,
        $id,
    ]);
}

function events_partner_blocks_delete(PDO $db, int $id): void {
    if ($id <= 0) {
        return;
    }
    $st = $db->prepare('DELETE FROM `events_partner_blocks` WHERE `id` = ?');
    $st->execute([$id]);
}

/**
 * Sorrend csere a szomszédos blokkal.
 *
 * @param int $direction -1 = fel, 1 = le
 */
function events_partner_blocks_move(PDO $db, int $id, int $direction): bool {
    $blocks = events_partner_blocks_all($db);
    $index = null;
    foreach ($blocks as $i => $block) {
        if ((int) $block['id'] === $id) {
            $index = $i;
            break;
        }
    }
    if ($index === null) {
        return false;
    }
    $target = $direction < 0 ? $index - 1 : $index + 1;
    if ($target < 0 || $target >= count($blocks)) {
        return false;
    }

    // A tárolt sort_order duplikált is lehet, ezért a teljes listát újraszámozzuk.
    $order = $blocks;
    [$order[$index], $order[$target]] = [$order[$target], $order[$index]];

    $db->beginTransaction();
    try {
        $st = $db->prepare('UPDATE `events_partner_blocks` SET `sort_order` = ? WHERE `id` = ?');
        foreach ($order as $position => $block) {
            $st->execute([($position + 1) * EVENTS_PARTNER_BLOCK_SORT_STEP, (int) $block['id']]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('events_partner_blocks_move: ' . $e->getMessage());

        return false;
    }

    return true;
}

/**
 * Nyelvi feloldás: üres EN mező esetén a magyar tartalom jelenik meg.
 *
 * @param array<string, mixed> $row
 * @return array{type: string, title: string, heading_level: int, link_url: string, logo_url: string, body: string, note_before: string, note_after: string}
 */
function events_partner_block_localized(array $row, string $lang): array {
    $pick = static function (string $hu, string $en) use ($lang): string {
        $hu = trim($hu);
        $en = trim($en);

        return $lang === 'en' && $en !== '' ? $en : $hu;
    };

    return [
        'type' => events_partner_blocks_normalize_type((string) ($row['block_type'] ?? 'partner')),
        'title' => $pick((string) ($row['title'] ?? ''), (string) ($row['title_en'] ?? '')),
        'heading_level' => in_array((int) ($row['heading_level'] ?? 2), [2, 3], true) ? (int) $row['heading_level'] : 2,
        'link_url' => trim((string) ($row['link_url'] ?? '')),
        'logo_url' => trim((string) ($row['logo_url'] ?? '')),
        'body' => $pick((string) ($row['body'] ?? ''), (string) ($row['body_en'] ?? '')),
        'note_before' => $pick((string) ($row['note_before'] ?? ''), (string) ($row['note_before_en'] ?? '')),
        'note_after' => $pick((string) ($row['note_after'] ?? ''), (string) ($row['note_after_en'] ?? '')),
    ];
}
