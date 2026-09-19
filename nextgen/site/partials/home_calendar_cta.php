<?php
declare(strict_types=1);

/**
 * Naptár gomb a fejléc alatt, a tartalom tetején jobbra.
 * A kattintást a hero sávban futó data-public-nav-track figyelő méri.
 *
 * @var string $calendarUrl
 * @var array<string, string> $H
 */
$calendarUrl = trim((string) ($calendarUrl ?? ''));
if ($calendarUrl === '') {
    return;
}

$H = is_array($H ?? null) ? $H : [];
$ctaLabel = trim((string) ($H['calendar_cta'] ?? '')) ?: 'Naptár';
$ctaAria = trim((string) ($H['calendar_cta_aria'] ?? '')) ?: $ctaLabel;
?>
<a
    class="latinfo-home__cal-cta"
    href="<?= h($calendarUrl) ?>"
    title="<?= h($ctaAria) ?>"
    aria-label="<?= h($ctaAria) ?>"
    data-public-nav-track="home-calendar-cta"
>
    <span class="latinfo-home__cal-cta-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4.5" width="18" height="16" rx="3"/>
            <path d="M8 3v3M16 3v3M3 9.5h18"/>
        </svg>
    </span>
    <span class="latinfo-home__cal-cta-text"><?= h($ctaLabel) ?></span>
</a>
