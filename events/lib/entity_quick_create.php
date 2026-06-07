<?php
declare(strict_types=1);

require_once __DIR__ . '/event_request.php';
require_once __DIR__ . '/dj_request.php';
require_once __DIR__ . '/style_request.php';
require_once __DIR__ . '/venue_request.php';

/**
 * @return list<string>
 */
function events_entity_quick_create_allowed_types(): array {
    return ['organizer', 'venue', 'category', 'tag', 'dj', 'style'];
}

function events_entity_quick_create_by_name(PDO $db, string $type, string $name): int {
    $type = strtolower(trim($type));
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Üres név.');
    }
    if (!in_array($type, events_entity_quick_create_allowed_types(), true)) {
        throw new InvalidArgumentException('Ismeretlen entitás típus.');
    }

    return match ($type) {
        'organizer' => events_find_or_create_organizer_by_name($db, $name),
        'venue' => events_find_or_create_venue_by_name($db, $name),
        'category' => events_find_or_create_category_by_name($db, $name),
        'tag' => events_find_or_create_tag_by_name($db, $name),
        'dj' => events_find_or_create_dj_by_name($db, $name),
        'style' => events_find_or_create_style_by_name($db, $name),
        default => throw new InvalidArgumentException('Ismeretlen entitás típus.'),
    };
}

function events_find_or_create_organizer_by_name(PDO $db, string $name): int {
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Üres szervező név.');
    }
    $st = $db->prepare('SELECT `id` FROM `events_organizers` WHERE `name` = ? LIMIT 1');
    $st->execute([$name]);
    $existing = $st->fetchColumn();
    if ($existing !== false) {
        return (int) $existing;
    }
    $ins = $db->prepare('INSERT INTO `events_organizers` (`name`) VALUES (?)');
    $ins->execute([$name]);

    return (int) $db->lastInsertId();
}

function events_find_or_create_category_by_name(PDO $db, string $name): int {
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Üres kategória név.');
    }
    $st = $db->prepare('SELECT `id` FROM `events_categories` WHERE `name` = ? LIMIT 1');
    $st->execute([$name]);
    $existing = $st->fetchColumn();
    if ($existing !== false) {
        return (int) $existing;
    }
    $ins = $db->prepare('
        INSERT INTO `events_categories` (`name`, `name_en`, `parent_id`, `color`, `sort_order`)
        VALUES (?, ?, NULL, ?, 0)
    ');
    $ins->execute([$name, '', '#6d8f63']);

    return (int) $db->lastInsertId();
}

function events_find_or_create_venue_by_name(PDO $db, string $name): int {
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Üres helyszín név.');
    }
    $st = $db->prepare('SELECT `id` FROM `events_venues` WHERE `name` = ? LIMIT 1');
    $st->execute([$name]);
    $existing = $st->fetchColumn();
    if ($existing !== false) {
        return (int) $existing;
    }
    $baseSlug = events_slugify($name);
    $slug = events_ensure_unique_venue_slug($db, $baseSlug, null);
    $ins = $db->prepare('
        INSERT INTO `events_venues` (`name`, `slug`, `description`, `country`, `city`, `postal_code`, `address`, `linked_venue_id`)
        VALUES (?, ?, NULL, ?, NULL, NULL, NULL, NULL)
    ');
    $ins->execute([$name, $slug, events_venue_default_country()]);

    return (int) $db->lastInsertId();
}
