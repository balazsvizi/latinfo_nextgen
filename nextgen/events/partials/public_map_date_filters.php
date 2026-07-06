<?php
declare(strict_types=1);

/**
 * Térkép nézet – mindig látható dátumszűrő (Tól / Ig).
 *
 * @var array<string, mixed> $filters
 * @var array<string, string> $D
 */
?>
<div class="home-public__map-filters" aria-label="<?= h((string) ($D['map_filters_aria'] ?? 'Térkép szűrők')) ?>">
    <div class="home-public__map-date-filters">
        <div class="home-public__map-date-field">
            <label class="<?= h(events_public_filter_label_attr_classes($filters, 'start_from')) ?>" for="ev-f-start-from">
                <?= h((string) ($D['map_filter_date_from'] ?? 'Tól')) ?>
            </label>
            <input
                class="events-filter-input events-filter-input--date"
                type="date"
                name="f_start_from"
                id="ev-f-start-from"
                value="<?= h((string) ($filters['f_start_from'] ?? '')) ?>"
            >
        </div>
        <div class="home-public__map-date-field">
            <label class="<?= h(events_public_filter_label_attr_classes($filters, 'start_to')) ?>" for="ev-f-start-to">
                <?= h((string) ($D['map_filter_date_to'] ?? 'Ig')) ?>
            </label>
            <input
                class="events-filter-input events-filter-input--date"
                type="date"
                name="f_start_to"
                id="ev-f-start-to"
                value="<?= h((string) ($filters['f_start_to'] ?? '')) ?>"
            >
        </div>
    </div>
    <p class="home-public__map-filters-hint">
        <?= h((string) ($D['map_filters_date_hint'] ?? 'Alapértelmezés: a következő egy hét.')) ?>
    </p>
</div>
