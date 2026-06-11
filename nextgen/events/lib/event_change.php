<?php
declare(strict_types=1);

/**
 * Esemény változás / elmaradás jelzés — típusok, naptár és publikus megjelenítés.
 */

function events_event_change_type_cancelled(): string
{
    return 'cancelled';
}

function events_event_change_type_modified(): string
{
    return 'modified';
}

/**
 * @return array<string, string> slug => magyar admin címke
 */
function events_event_change_types(): array
{
    return [
        events_event_change_type_cancelled() => 'Elmarad',
        events_event_change_type_modified() => 'Változás',
    ];
}

function events_event_change_is_valid_type(?string $type): bool
{
    if ($type === null || $type === '') {
        return false;
    }

    return array_key_exists($type, events_event_change_types());
}

function events_event_change_type_label(?string $type): string
{
    if ($type === null || $type === '') {
        return '';
    }

    return events_event_change_types()[$type] ?? $type;
}

function events_event_change_type_label_public(?string $type, string $lang = 'hu'): string
{
    if ($type === null || $type === '') {
        return '';
    }
    if ($lang === 'en') {
        return match ($type) {
            events_event_change_type_cancelled() => 'Cancelled',
            events_event_change_type_modified() => 'Change',
            default => $type,
        };
    }

    return events_event_change_type_label($type);
}

/**
 * @param array<string, string> $strings events_public_megjelenit_strings()
 */
function events_event_change_public_heading(array $event, array $strings, string $lang = 'hu'): string
{
    $type = events_event_change_type($event);
    if ($type === events_event_change_type_cancelled()) {
        return (string) ($strings['change_notice_heading_cancelled'] ?? events_event_change_type_label_public($type, $lang));
    }
    if ($type === events_event_change_type_modified()) {
        return (string) ($strings['change_notice_heading_modified'] ?? events_event_change_type_label_public($type, $lang));
    }

    return '';
}

/**
 * @param array<string, mixed> $event
 */
function events_event_change_active(array $event): bool
{
    if (empty($event['event_change_active'])) {
        return false;
    }

    return events_event_change_is_valid_type(isset($event['event_change_type']) ? (string) $event['event_change_type'] : null);
}

/**
 * @param array<string, mixed> $event
 */
function events_event_change_type(array $event): ?string
{
    if (!events_event_change_active($event)) {
        return null;
    }

    return (string) $event['event_change_type'];
}

function events_event_change_normalize_note(string $raw): string
{
    $note = trim(strip_tags($raw));
    if (function_exists('mb_substr')) {
        return mb_substr($note, 0, 2000, 'UTF-8');
    }

    return substr($note, 0, 2000);
}

/**
 * @param array<string, mixed> $event
 */
function events_event_change_public_note(array $event): string
{
    if (!events_event_change_active($event)) {
        return '';
    }

    return trim((string) ($event['event_change_note'] ?? ''));
}

/**
 * Rövid naptár-badge szöveg.
 *
 * @param array<string, mixed> $event
 */
function events_event_change_calendar_badge_label(array $event): string
{
    $type = events_event_change_type($event);
    if ($type === events_event_change_type_cancelled()) {
        return 'ELMARAD';
    }
    if ($type === events_event_change_type_modified()) {
        return 'VÁLTOZÁS';
    }

    return '';
}

/**
 * @param array<string, mixed> $event
 */
function events_event_change_event_name_class(array $event): string
{
    if (events_event_change_type($event) === events_event_change_type_cancelled()) {
        return ' events-cal__event-name--cancelled';
    }

    return '';
}

/**
 * Naptár link / blokk módosító osztályok.
 *
 * @param array<string, mixed> $event
 */
function events_event_change_calendar_link_class(array $event): string
{
    $type = events_event_change_type($event);
    if ($type === events_event_change_type_cancelled()) {
        return ' events-cal__event-link--change-cancelled';
    }
    if ($type === events_event_change_type_modified()) {
        return ' events-cal__event-link--change-modified';
    }

    return '';
}

/**
 * Naptár blokk inline stílus — változás típus szerint felülírja a kategória színt.
 *
 * @param array<string, mixed> $event
 */
function events_event_change_calendar_block_style(array $event): string
{
    $type = events_event_change_type($event);
    if ($type === events_event_change_type_cancelled()) {
        return 'background-color:#fee2e2;color:#991b1b;border-color:#dc2626;--events-cal-accent:#dc2626';
    }
    if ($type === events_event_change_type_modified()) {
        return 'background-color:#fef3c7;color:#92400e;border-color:#f59e0b;--events-cal-accent:#f59e0b';
    }

    return '';
}

/**
 * Publikus naptár-előnézet adat.
 *
 * @param array<string, mixed> $event
 * @return array{type: string, typeLabel: string, note: string, badge: string}|null
 */
function events_event_change_preview_payload(array $event): ?array
{
    if (!events_event_change_active($event)) {
        return null;
    }

    $type = (string) events_event_change_type($event);

    return [
        'type' => $type,
        'typeLabel' => events_event_change_type_label($type),
        'note' => events_event_change_public_note($event),
        'badge' => events_event_change_calendar_badge_label($event),
    ];
}

/**
 * @param array<string, mixed> $event
 */
function events_event_change_notice_css_modifier(array $event): string
{
    $type = events_event_change_type($event);
    if ($type === events_event_change_type_cancelled()) {
        return 'event-change-notice--cancelled';
    }
    if ($type === events_event_change_type_modified()) {
        return 'event-change-notice--modified';
    }

    return '';
}
