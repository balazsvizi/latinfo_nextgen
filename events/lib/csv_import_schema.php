<?php
declare(strict_types=1);

/**
 * CSV import: cél táblák és oszlop meta (mapping űrlaphoz + típusellenőrzés).
 * Az import fájlban szereplő ID nem lehet nagyobb 100000-nál (spec / migráció).
 */
function events_csv_import_schema(): array {
    return [
        'events_calendar_events' => [
            'label' => 'Események (`events_calendar_events`)',
            'id_max_import' => 100000,
            'columns' => [
                'id' => ['type' => 'uint', 'nullable' => true, 'note' => 'Üres = auto ID (≥100000). Kitöltve: max 100000, upsert.'],
                'event_name' => ['type' => 'string', 'max' => 500, 'nullable' => false],
                'event_slug' => ['type' => 'string', 'max' => 255, 'nullable' => false, 'note' => 'Üres CSV → névből generáljuk'],
                'event_content' => ['type' => 'text', 'nullable' => false, 'note' => 'HTML; üres megengedett'],
                'created' => ['type' => 'datetime', 'nullable' => true],
                'modified' => ['type' => 'datetime', 'nullable' => true, 'note' => 'UPDATE-nél ha nincs a CSV-ben, DB frissít'],
                'event_status' => ['type' => 'enum', 'values' => events_allowed_post_statuses(), 'nullable' => false],
                'event_start' => ['type' => 'datetime', 'nullable' => true],
                'event_end' => ['type' => 'datetime', 'nullable' => true],
                'event_allday' => ['type' => 'bool', 'nullable' => false],
                'event_cost_from' => ['type' => 'decimal', 'nullable' => true],
                'event_cost_to' => ['type' => 'decimal', 'nullable' => true],
                'event_url' => ['type' => 'string', 'max' => 2000, 'nullable' => true],
                'event_latinfohu_partner' => ['type' => 'bool', 'nullable' => false],
                'organizer_id' => ['type' => 'uint', 'nullable' => true],
                'venue_id' => ['type' => 'uint', 'nullable' => true],
            ],
        ],
        'events_organizers' => [
            'label' => 'Szervezők (`events_organizers`)',
            'id_max_import' => 100000,
            'columns' => [
                'id' => ['type' => 'uint', 'nullable' => true, 'note' => 'Üres = auto ID (≥200000). Kitöltve: max 100000, upsert.'],
                'name' => ['type' => 'string', 'max' => 255, 'nullable' => false],
            ],
        ],
    ];
}
