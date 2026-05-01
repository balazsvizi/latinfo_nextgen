<?php
declare(strict_types=1);

/**
 * CSV import: cél táblák és oszlop meta (mapping űrlaphoz + típusellenőrzés).
 * Az import fájlban szereplő ID nem lehet nagyobb 100000-nál (spec / migráció), ahol van `id` oszlop.
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
                'event_featured_image_url' => ['type' => 'string', 'max' => 2000, 'nullable' => true, 'note' => 'Kiemelt kép (https vagy /útvonal)'],
                'event_latinfohu_partner' => ['type' => 'bool', 'nullable' => false],
                'venue_id' => ['type' => 'uint', 'nullable' => true],
                // Virtuális: nem oszlop a táblában; az importmotor az events_calendar_event_categories táblát szinkronizálja.
                'category_ids' => ['type' => 'string', 'max' => 2000, 'nullable' => true, 'virtual' => true, 'note' => 'Opcionális: vessző, pontosvessző vagy | elválasztott kategória ID-k (events_categories.id). Üres + mapolt oszlop: minden kapcsolat törlődik. Nem mapolt: változatlan marad a kapcsoló.'],
            ],
        ],
        'events_calendar_event_organizers' => [
            'label' => 'Esemény–szervező (`events_calendar_event_organizers`, ID alapján)',
            'composite_key' => ['event_id', 'organizer_id'],
            'id_max_import' => 0,
            'columns' => [
                'event_id' => ['type' => 'uint', 'nullable' => false, 'note' => 'Esemény ID (events_calendar_events.id)'],
                'organizer_id' => ['type' => 'uint', 'nullable' => false, 'note' => 'Szervező ID (events_organizers.id)'],
                'sort_order' => ['type' => 'uint', 'nullable' => true, 'note' => 'Üres = 0; kisebb = előrébb'],
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
        'events_venues' => [
            'label' => 'Helyszínek (`events_venues`)',
            'id_max_import' => 100000,
            'columns' => [
                'id' => ['type' => 'uint', 'nullable' => true, 'note' => 'Üres = auto ID. Kitöltve: max 100000, upsert.'],
                'name' => ['type' => 'string', 'max' => 500, 'nullable' => false],
                'slug' => ['type' => 'string', 'max' => 255, 'nullable' => false, 'note' => 'Üres CSV → névből generáljuk; egyedi a táblában'],
                'description' => ['type' => 'text', 'nullable' => true],
                'country' => ['type' => 'string', 'max' => 120, 'nullable' => true, 'note' => 'Üres → Magyarország'],
                'city' => ['type' => 'string', 'max' => 255, 'nullable' => true, 'note' => 'Település'],
                'postal_code' => ['type' => 'string', 'max' => 16, 'nullable' => true, 'note' => 'IRSZ'],
                'address' => ['type' => 'text', 'nullable' => true, 'note' => 'Utca, házszám'],
                'linked_venue_id' => ['type' => 'uint', 'nullable' => true, 'note' => 'Másik venue ID; nyilvános név linkhez'],
                'created' => ['type' => 'datetime', 'nullable' => true],
                'modified' => ['type' => 'datetime', 'nullable' => true, 'note' => 'UPDATE-nél ha nincs a CSV-ben, DB frissít'],
            ],
        ],
    ];
}
