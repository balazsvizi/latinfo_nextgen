<?php
declare(strict_types=1);

/**
 * CMS cikk státuszok (Draft / Publikus / Egyéb).
 */

function cms_status_draft(): string
{
    return 'draft';
}

function cms_status_publish(): string
{
    return 'publish';
}

function cms_status_other(): string
{
    return 'other';
}

/**
 * @return list<string>
 */
function cms_allowed_statuses(): array
{
    return [cms_status_draft(), cms_status_publish(), cms_status_other()];
}

function cms_status_label(string $status): string
{
    return match ($status) {
        'draft' => 'Draft',
        'publish' => 'Publikus',
        'other' => 'Egyéb',
        default => $status !== '' ? $status : 'Ismeretlen',
    };
}

function cms_status_badge_class(string $status): string
{
    return match ($status) {
        'publish' => 'event-status-badge event-status-badge--publish',
        'draft' => 'event-status-badge event-status-badge--draft',
        'other' => 'event-status-badge event-status-badge--pending',
        default => 'event-status-badge',
    };
}

function cms_normalize_status(?string $status): string
{
    $s = trim((string) $status);
    if (in_array($s, cms_allowed_statuses(), true)) {
        return $s;
    }

    return cms_status_draft();
}
