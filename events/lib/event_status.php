<?php
declare(strict_types=1);

/**
 * WordPress wp_posts.post_status értékek (The Events Calendar kompatibilitás).
 */
function events_allowed_post_statuses(): array {
    return ['publish', 'draft', 'pending', 'future', 'private', 'trash', 'auto-draft'];
}

function events_default_post_status(): string {
    return 'draft';
}

function events_public_post_status(): string {
    return 'publish';
}

function events_is_allowed_post_status(string $s): bool {
    return in_array($s, events_allowed_post_statuses(), true);
}

function events_post_status_label(string $s): string {
    return match ($s) {
        'publish' => 'Közzétéve',
        'draft' => 'Piszkozat',
        'pending' => 'Jóváhagyásra vár',
        'future' => 'Ütemezett',
        'private' => 'Privát',
        'trash' => 'Lomtár',
        'auto-draft' => 'Automatikus piszkozat',
        default => $s,
    };
}
