<?php
declare(strict_types=1);

/**
 * CSV import: cél táblák és oszlop meta (mapping űrlaphoz + típusellenőrzés).
 * Az import fájlban szereplő ID nem lehet nagyobb 100000-nál (spec / migráció), ahol van `id` oszlop.
 */
function events_csv_import_schema(): array {
    return [
        'events_calendar_events' => [
            'import_code' => '001',
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
            'import_code' => '002',
            'label' => 'Esemény–szervező (`events_calendar_event_organizers`, ID alapján)',
            'composite_key' => ['event_id', 'organizer_id'],
            'id_max_import' => 0,
            'columns' => [
                'event_id' => ['type' => 'uint', 'nullable' => false, 'note' => 'Esemény ID (events_calendar_events.id)'],
                'organizer_id' => ['type' => 'uint', 'nullable' => false, 'note' => 'Szervező ID (events_organizers.id)'],
                'sort_order' => ['type' => 'uint', 'nullable' => true, 'note' => 'Üres = 0; kisebb = előrébb'],
            ],
        ],
        'events_calendar_event_categories' => [
            'import_code' => '003',
            'label' => 'Esemény–kategória (`events_calendar_event_categories`, ID alapján)',
            'composite_key' => ['event_id', 'category_id'],
            'id_max_import' => 0,
            'columns' => [
                'event_id' => ['type' => 'uint', 'nullable' => false, 'note' => 'Esemény ID (events_calendar_events.id)'],
                'category_id' => ['type' => 'uint', 'nullable' => false, 'note' => 'Kategória ID (events_categories.id)'],
            ],
        ],
        'events_organizers' => [
            'import_code' => '004',
            'label' => 'Szervezők (`events_organizers`)',
            'id_max_import' => 100000,
            'columns' => [
                'id' => ['type' => 'uint', 'nullable' => true, 'note' => 'Üres = auto ID (≥200000). Kitöltve: max 100000, upsert.'],
                'name' => ['type' => 'string', 'max' => 255, 'nullable' => false],
            ],
        ],
        'events_venues' => [
            'import_code' => '005',
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
                'latitude' => ['type' => 'decimal', 'nullable' => true, 'note' => 'WGS-84 szélesség'],
                'longitude' => ['type' => 'decimal', 'nullable' => true, 'note' => 'WGS-84 hosszúság'],
                'website_url' => ['type' => 'string', 'max' => 2000, 'nullable' => true],
                'google_maps_url' => ['type' => 'string', 'max' => 2000, 'nullable' => true],
                'linked_venue_id' => ['type' => 'uint', 'nullable' => true, 'note' => 'Másik venue ID; nyilvános név linkhez'],
                'created' => ['type' => 'datetime', 'nullable' => true],
                'modified' => ['type' => 'datetime', 'nullable' => true, 'note' => 'UPDATE-nél ha nincs a CSV-ben, DB frissít'],
            ],
        ],
        'events_categories' => [
            'import_code' => '006',
            'label' => 'Kategóriák (`events_categories`)',
            'id_max_import' => 100000,
            'columns' => [
                'id' => ['type' => 'uint', 'nullable' => true, 'note' => 'Üres = auto ID (≥10000). Kitöltve: max 100000, upsert.'],
                'name' => ['type' => 'string', 'max' => 255, 'nullable' => false, 'note' => 'Magyar név'],
                'name_en' => ['type' => 'string', 'max' => 255, 'nullable' => true, 'note' => 'Angol név; üres = változatlan (UPDATE) / üres INSERT'],
                'parent_id' => ['type' => 'uint', 'nullable' => true, 'note' => 'Szülő kategória ID; üres = gyökér'],
                'color' => ['type' => 'string', 'max' => 7, 'nullable' => true, 'note' => 'Hex szín, pl. #6D8F63'],
                'sort_order' => ['type' => 'uint', 'nullable' => true, 'note' => 'Üres = 0; kisebb = előrébb'],
                'created' => ['type' => 'datetime', 'nullable' => true],
                'modified' => ['type' => 'datetime', 'nullable' => true, 'note' => 'UPDATE-nél ha nincs a CSV-ben, DB frissít'],
            ],
        ],
        'events_tags' => [
            'import_code' => '007',
            'label' => 'Címkék (`events_tags`)',
            'id_max_import' => 100000,
            'columns' => [
                'id' => ['type' => 'uint', 'nullable' => true, 'note' => 'Üres = auto ID (≥20000). Kitöltve: max 100000, upsert. Ugyanilyen név már létezik → sor kihagyva.'],
                'name' => ['type' => 'string', 'max' => 255, 'nullable' => false, 'note' => 'Címke neve (kötelező minden sorban).'],
                'tag_types' => ['type' => 'string', 'max' => 255, 'nullable' => true, 'virtual' => true, 'note' => 'Opcionális típusok (events_tag_types név vagy kód, vesszővel elválasztva)'],
                'created' => ['type' => 'datetime', 'nullable' => true],
                'modified' => ['type' => 'datetime', 'nullable' => true, 'note' => 'UPDATE-nél ha nincs a CSV-ben, DB frissít'],
            ],
        ],
        'events_calendar_event_tags' => [
            'import_code' => '008',
            'label' => 'Esemény–címke / Event-Tag (`events_calendar_event_tags`, event_id + címke név)',
            'composite_key' => ['event_id', 'tag_id'],
            'id_max_import' => 0,
            'columns' => [
                'event_id' => ['type' => 'uint', 'nullable' => false, 'note' => 'Esemény ID (events_calendar_events.id)'],
                'tag_id' => ['type' => 'uint', 'nullable' => true, 'note' => 'Címke ID (events_tags.id); üres, ha tag_name van megadva'],
                'tag_name' => ['type' => 'string', 'max' => 255, 'nullable' => true, 'virtual' => true, 'note' => 'Címke név: meglévő keresés vagy automatikus létrehozás (tag_id helyett vagy mellette)'],
                'tag_types' => ['type' => 'string', 'max' => 255, 'nullable' => true, 'virtual' => true, 'note' => 'Opcionális: új címke létrehozásakor típusok (events_tag_types, vesszővel)'],
            ],
        ],
        'events_styles' => [
            'import_code' => '009',
            'label' => 'Stílusok (`events_styles`)',
            'id_max_import' => 100000,
            'columns' => [
                'id' => ['type' => 'uint', 'nullable' => true, 'note' => 'Üres = auto ID (≥26000). Kitöltve: max 100000, upsert. Ugyanilyen név már létezik → sor kihagyva.'],
                'name' => ['type' => 'string', 'max' => 255, 'nullable' => false, 'note' => 'Stílus neve (kötelező minden sorban).'],
                'created' => ['type' => 'datetime', 'nullable' => true],
                'modified' => ['type' => 'datetime', 'nullable' => true, 'note' => 'UPDATE-nél ha nincs a CSV-ben, DB frissít'],
            ],
        ],
        'events_calendar_event_main_styles' => [
            'import_code' => '010',
            'label' => 'Esemény–fő stílus (`events_calendar_event_main_styles`, event_id + style név)',
            'composite_key' => ['event_id', 'style_id'],
            'id_max_import' => 0,
            'columns' => [
                'event_id' => ['type' => 'uint', 'nullable' => false, 'note' => 'Esemény ID (events_calendar_events.id)'],
                'style_id' => ['type' => 'uint', 'nullable' => true, 'note' => 'Stílus ID (events_styles.id); üres, ha style_name van megadva'],
                'style_name' => ['type' => 'string', 'max' => 255, 'nullable' => true, 'virtual' => true, 'note' => 'Stílus név: meglévő keresés vagy automatikus létrehozás (style_id helyett vagy mellette)'],
            ],
        ],
        'events_calendar_event_supplementary_styles' => [
            'import_code' => '011',
            'label' => 'Esemény–kiegészítő stílus (`events_calendar_event_supplementary_styles`, event_id + style név)',
            'composite_key' => ['event_id', 'style_id'],
            'id_max_import' => 0,
            'columns' => [
                'event_id' => ['type' => 'uint', 'nullable' => false, 'note' => 'Esemény ID (events_calendar_events.id)'],
                'style_id' => ['type' => 'uint', 'nullable' => true, 'note' => 'Stílus ID (events_styles.id); üres, ha style_name van megadva'],
                'style_name' => ['type' => 'string', 'max' => 255, 'nullable' => true, 'virtual' => true, 'note' => 'Stílus név: meglévő keresés vagy automatikus létrehozás (style_id helyett vagy mellette)'],
            ],
        ],
    ];
}
