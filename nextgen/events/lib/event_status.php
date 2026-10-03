<?php
declare(strict_types=1);

/**
 * WordPress wp_posts.post_status értékek (The Events Calendar kompatibilitás)
 * + Latinfo „előzetes” státusz (nyilvánosan látható, de nem teljes közzététel).
 */
function events_allowed_post_statuses(): array {
    return ['publish', 'preliminary', 'draft', 'pending', 'future', 'private', 'trash', 'auto-draft'];
}

function events_default_post_status(): string {
    return 'draft';
}

function events_public_post_status(): string {
    return 'publish';
}

function events_preliminary_post_status(): string {
    return 'preliminary';
}

/**
 * Nyilvános naptárban / eseményoldalon megjelenő státuszok.
 *
 * @return list<string>
 */
function events_publicly_visible_post_statuses(): array {
    return [events_public_post_status(), events_preliminary_post_status()];
}

function events_is_preliminary_post_status(string $s): bool {
    return $s === events_preliminary_post_status();
}

function events_is_publicly_visible_post_status(string $s): bool {
    return in_array($s, events_publicly_visible_post_statuses(), true);
}

function events_is_allowed_post_status(string $s): bool {
    return in_array($s, events_allowed_post_statuses(), true);
}

function events_post_status_label(string $s): string {
    return match ($s) {
        'publish' => 'Közzétéve',
        'preliminary' => 'Előzetes',
        'draft' => 'Piszkozat',
        'pending' => 'Jóváhagyásra vár',
        'future' => 'Ütemezett',
        'private' => 'Privát',
        'trash' => 'Lomtár',
        'auto-draft' => 'Automatikus piszkozat',
        default => $s,
    };
}

/**
 * CSS osztály a státusz badge-hez (events-admin táblázat).
 */
function events_post_status_badge_class(string $s): string {
    $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($s));
    return $slug !== '' ? 'event-status-badge--' . $slug : 'event-status-badge--draft';
}

/**
 * Előzetes esemény leírás-sablon (HTML).
 */
function events_preliminary_content_template(): string {
    return '<p>Ez még csak egy előzetes információ. A részletek később lesznek elérhetők erről az eseményről.</p>';
}

/**
 * Hány nap van még az esemény kezdőnapjáig (múltbeli: negatív).
 * Null, ha nincs érvényes kezdő dátum.
 */
function events_days_until_event_start(?string $eventStart): ?int {
    $raw = trim((string) $eventStart);
    if ($raw === '') {
        return null;
    }
    try {
        $start = new DateTimeImmutable($raw);
    } catch (Throwable) {
        return null;
    }
    $today = new DateTimeImmutable('today');
    $startDay = $start->setTime(0, 0, 0);

    return (int) $today->diff($startDay)->format('%r%a');
}
