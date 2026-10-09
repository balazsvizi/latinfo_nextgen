<?php
declare(strict_types=1);

/**
 * Latin / salsa / bachata / kizomba tánciskolák feltöltése.
 *
 * CLI:  php nextgen/tools/seed_dance_schools.php
 * Web:  dance_schools_seed_run($db)  (Latinfo admin gomb)
 *
 * Újrafuttatható: meglévő slug esetén frissít (nem duplikál).
 * is_published mindig 0 marad.
 */

require_once dirname(__DIR__) . '/events/lib/dance_schools.php';
require_once dirname(__DIR__) . '/events/lib/slug.php';
require_once dirname(__DIR__) . '/events/lib/venue_request.php';
require_once dirname(__DIR__) . '/events/lib/entity_quick_create.php';

if (!defined('DANCE_SEED_STYLE_CUBAN')) {
    define('DANCE_SEED_STYLE_CUBAN', 'salsa_cuban');
    define('DANCE_SEED_STYLE_BACHATA', 'bachata');
    define('DANCE_SEED_STYLE_LINE', 'salsa_line');
    define('DANCE_SEED_STYLE_KIZOMBA', 'kizomba');
    define('DANCE_SEED_STYLE_ZOUK', 'zouk');
    define('DANCE_SEED_STYLE_BALLROOM', 'ballroom');
}

/** @deprecated alias – a katalógus STYLE_* konstansai */
if (!defined('STYLE_CUBAN')) {
    define('STYLE_CUBAN', DANCE_SEED_STYLE_CUBAN);
    define('STYLE_BACHATA', DANCE_SEED_STYLE_BACHATA);
    define('STYLE_LINE', DANCE_SEED_STYLE_LINE);
    define('STYLE_KIZOMBA', DANCE_SEED_STYLE_KIZOMBA);
    define('STYLE_ZOUK', DANCE_SEED_STYLE_ZOUK);
    define('STYLE_BALLROOM', DANCE_SEED_STYLE_BALLROOM);
}

/**
 * @return array<string, int> kulcs => style_id
 */
function dance_schools_seed_style_id_map(PDO $db): array
{
    $wanted = [
        DANCE_SEED_STYLE_CUBAN => ['salsa (kubai/cuban)', 'kubai salsa', 'salsa cubana', 'salsa'],
        DANCE_SEED_STYLE_BACHATA => ['bachata'],
        DANCE_SEED_STYLE_LINE => ['salsa (vonalas/crossbody)', 'vonalas salsa', 'salsa la', 'crossbody'],
        DANCE_SEED_STYLE_KIZOMBA => ['kizomba'],
        DANCE_SEED_STYLE_ZOUK => ['zouk', 'brazil zouk'],
        DANCE_SEED_STYLE_BALLROOM => ['társastánc/ballroom dance', 'társastánc', 'ballroom'],
    ];
    $byName = [];
    try {
        $rows = $db->query('SELECT `id`, `name` FROM `events_styles`')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $r) {
            $byName[mb_strtolower(trim((string) $r['name']), 'UTF-8')] = (int) $r['id'];
        }
    } catch (Throwable) {
        return [];
    }
    $map = [];
    foreach ($wanted as $key => $aliases) {
        foreach ($aliases as $alias) {
            $k = mb_strtolower($alias, 'UTF-8');
            if (isset($byName[$k])) {
                $map[$key] = $byName[$k];
                break;
            }
        }
    }

    return $map;
}

/**
 * @param list<array{0: ?string, 1: string, 2?: string, 3?: string, 4?: string, 5?: string}> $items
 * @return list<array{style_id:?int,style_label:string,age_group:string,level:string,class_type:string,schedule_note:string}>
 */
function dance_schools_seed_offs(array $items): array
{
    global $SEED_STYLE_IDS;
    $out = [];
    foreach ($items as $it) {
        $key = $it[0];
        $styleId = null;
        if (is_string($key) && $key !== '' && isset($SEED_STYLE_IDS[$key])) {
            $styleId = (int) $SEED_STYLE_IDS[$key];
        } elseif (is_int($key) && $key > 0) {
            $styleId = $key;
        }
        $out[] = [
            'id' => 0,
            'style_id' => $styleId,
            'style_label' => $it[1],
            'age_group' => $it[2] ?? 'adult',
            'level' => $it[3] ?? 'all',
            'class_type' => $it[4] ?? 'group',
            'schedule_note' => $it[5] ?? '',
        ];
    }

    return $out;
}

/** @deprecated */
function seed_offs(array $items): array
{
    return dance_schools_seed_offs($items);
}

/**
 * Seed helyszín → később bulihelyszínné (events_venues) alakul.
 *
 * @return array{name:string,address:string,city:string,postal_code:string,country:string,notes:string,is_active:int,offerings:list<array<string,mixed>>}
 */
function dance_schools_seed_loc(string $name, string $address, string $city, string $postal = '', array $offerings = [], string $notes = ''): array
{
    return [
        'name' => $name,
        'address' => $address,
        'city' => $city,
        'postal_code' => $postal,
        'country' => 'Magyarország',
        'notes' => $notes,
        'is_active' => 1,
        'offerings' => $offerings,
    ];
}

/** @deprecated */
function seed_loc(string $name, string $address, string $city, string $postal = '', array $offerings = [], string $notes = ''): array
{
    return dance_schools_seed_loc($name, $address, $city, $postal, $offerings, $notes);
}

/**
 * @param array<string, mixed> $loc
 */
function dance_schools_seed_find_or_create_venue(PDO $db, array $loc): int
{
    return dance_schools_find_or_create_venue_from_legacy($db, [
        'name' => (string) ($loc['name'] ?? ''),
        'address' => (string) ($loc['address'] ?? ''),
        'city' => (string) ($loc['city'] ?? ''),
        'postal_code' => (string) ($loc['postal_code'] ?? ''),
        'country' => (string) ($loc['country'] ?? 'Magyarország'),
        'google_maps_url' => (string) ($loc['google_maps_url'] ?? ''),
        'latitude' => $loc['latitude'] ?? null,
        'longitude' => $loc['longitude'] ?? null,
        'notes' => (string) ($loc['notes'] ?? ''),
    ]);
}

/**
 * Seed locations[] → dance_school_venues sync payload.
 *
 * @param list<array<string, mixed>> $locations
 * @return list<array<string, mixed>>
 */
function dance_schools_seed_locations_to_venues(PDO $db, array $locations): array
{
    $out = [];
    foreach ($locations as $loc) {
        if (!is_array($loc)) {
            continue;
        }
        $venueId = dance_schools_seed_find_or_create_venue($db, $loc);
        if ($venueId <= 0) {
            continue;
        }
        $out[] = [
            'id' => 0,
            'venue_id' => $venueId,
            'notes' => trim((string) ($loc['notes'] ?? '')),
            'is_active' => !empty($loc['is_active']) ? 1 : 0,
            'offerings' => is_array($loc['offerings'] ?? null) ? $loc['offerings'] : [],
        ];
    }

    return $out;
}

/**
 * @return array{ok:bool,created:int,updated:int,errors:int,total:int,lines:list<string>,error?:string}
 */
function dance_schools_seed_run(PDO $db): array
{
    if (!dance_schools_ensure_schema($db)) {
        return [
            'ok' => false,
            'created' => 0,
            'updated' => 0,
            'errors' => 1,
            'total' => 0,
            'lines' => [],
            'error' => 'A tánciskola táblák nem hozhatók létre.',
        ];
    }

    global $SEED_STYLE_IDS;
    $SEED_STYLE_IDS = dance_schools_seed_style_id_map($db);
    $lines = [];
    $lines[] = 'Stílus map: ' . json_encode($SEED_STYLE_IDS, JSON_UNESCAPED_UNICODE);

    $sourceNote = 'Forrás: nyilvános weboldalak / Latinfo beváltóhelyek lista (2026-10-09). Ellenőrizendő: aktuális órarend, telefonszám, helyszín.';

    /** @var list<array<string,mixed>> $schools */
    $schools = [
    [
        'name' => 'Chili Salsa Tánciskola',
        'slug' => 'chili_salsa',
        'city' => 'Budapest',
        'description' => '<p>Budapesti latin tánciskola: kezdő és haladó <strong>kubai salsa</strong>, <strong>bachata</strong> és <strong>kizomba</strong> tanfolyamok, valamint zumba és dancehall. Párral vagy pár nélkül. A Tánctemplomban (VII. Huszár u. 4.) tartják az órákat.</p>',
        'website_url' => 'https://chilisalsa.hu/',
        'email' => 'info@chilisalsa.hu',
        'phone' => '+36 30 598 3347',
        'schedule_url' => 'https://chilisalsa.hu/tanctanfolyamok/',
        'accepts_beginners' => 1,
        'has_kids_classes' => 0,
        'has_performance_team' => 0,
        'trial_lesson_info' => 'Kezdő tanfolyamok rendszeresen indulnak; részletek a weboldalon.',
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' https://chilisalsa.hu/',
        'locations' => [
            seed_loc('Tánctemplom', 'Huszár u. 4.', 'Budapest', '1074', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'beginner', 'group'],
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'intermediate', 'group'],
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'advanced', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'beginner', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'intermediate', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'advanced', 'group'],
                [STYLE_KIZOMBA, 'Kizomba', 'adult', 'all', 'group'],
                [null, 'Zumba', 'adult', 'all', 'group'],
                [null, 'Dancehall', 'adult', 'all', 'group'],
            ])),
        ],
    ],
    [
        'name' => 'Salsa Diabólica',
        'slug' => 'salsa_diabolica',
        'city' => 'Budapest',
        'founded_year' => 2003,
        'description' => '<p>2003 óta működő kubai salsa iskola. Oktatnak <strong>kubai salsát</strong>, bachatát, reggaetont, merenguét, sont, afro-kubai rumbát; rendszeres workshopok, wellness hétvégék (salsa7vége). Ingyenes próbaóra / bemutatóóra gyakran elérhető kezdő tanfolyamoknál. SZÉP kártya elfogadott.</p>',
        'website_url' => 'https://www.salsadiabolica.hu/',
        'email' => 'info@salsadiabolica.hu',
        'phone' => '+36 20 937 8013',
        'facebook_url' => '',
        'instagram_url' => '',
        'tiktok_url' => '',
        'schedule_url' => 'https://www.salsadiabolica.hu/kezdo-tanfolyamok/',
        'pricing_info' => 'Budapest (2025.01.01-től): 4×60 perc 7000 Ft, 8×60 13000 Ft, 12×60 17500 Ft; napijegy 1900 Ft/60 perc. Diák kedvezmény első bérletre teljesen kezdő tanfolyamon (50%).',
        'trial_lesson_info' => 'Ingyenes bemutatóóra / próbaóra gyakran induló kezdő csoportoknál.',
        'accepts_beginners' => 1,
        'has_performance_team' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' https://www.salsadiabolica.hu/ | Kapcsolat: Rodek Zsolt',
        'locations' => [
            seed_loc('Opera környéke', '', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'beginner', 'group', 'Kezdő tanfolyamok'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'beginner', 'group'],
            ]), 'Pontos teremcím az aktuális órarendben.'),
            seed_loc('Bikás Park (XI.)', '', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'beginner', 'group'],
            ]), 'Pontos teremcím az aktuális órarendben.'),
        ],
        'events' => [
            [
                'id' => 0,
                'title' => '93. salsa7vége – Zalakaros, Hotel Karos-Spa',
                'event_type' => 'camp',
                'starts_at' => '2026-11-13 15:00',
                'ends_at' => '2026-11-15 14:00',
                'description' => 'Salsa & bachata & wellness hétvége (Salsa Diabólica).',
                'price_info' => '',
                'registration_url' => 'https://www.salsadiabolica.hu/',
                'is_published' => 0,
                'location_id' => 0,
            ],
        ],
    ],
    [
        'name' => 'Goldance Tánciskola',
        'slug' => 'goldance',
        'city' => 'Budapest',
        'founded_year' => 2003,
        'description' => '<p>Budapest egyik legnagyobb tánciskolája (2003–). Több mint 8 helyszín, 90+ tanár. Stílusok: <strong>salsa</strong>, <strong>bachata</strong>, west coast swing, rocky, társastánc, zumba. Első óra ingyenes. Havi bérlettel több helyszín is látogatható.</p>',
        'website_url' => 'https://goldance.hu/',
        'email' => 'mail@goldance.hu',
        'phone' => '+36 30 442 7902',
        'schedule_url' => 'https://goldance.hu/',
        'trial_lesson_info' => 'Első óra ingyenes.',
        'accepts_beginners' => 1,
        'has_kids_classes' => 0,
        'has_performance_team' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' https://goldance.hu/helyszinek/',
        'locations' => [
            seed_loc('Fáklya Klub (központ)', 'Csengery utca 68. I. em.', 'Budapest', '1067', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'all', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
                [null, 'West Coast Swing', 'adult', 'all', 'group'],
                [null, 'Rocky', 'adult', 'all', 'group'],
                [STYLE_BALLROOM, 'Társastánc', 'adult', 'all', 'group'],
                [null, 'Zumba', 'adult', 'all', 'group'],
            ]), 'Munkanapokon nyitva: 10:00–19:30'),
            seed_loc('MABÉOSZ', 'Vörösmarty u. 65.', 'Budapest', '1064', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'all', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
            ])),
            seed_loc('Oktogon', 'Teréz körút 13.', 'Budapest', '1067', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'all', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
            ])),
            seed_loc('Etele út', 'Etele út 59-61.', 'Budapest', '1119', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'all', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
            ])),
            seed_loc('La Place', 'Szépvölgyi út 44.', 'Budapest', '1025', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'all', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
            ])),
            seed_loc('Szent Margit Gimnázium', '', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'all', 'group'],
            ]), 'Pontos cím az órarendben.'),
        ],
    ],
    [
        'name' => 'Salsa Mojito Tánciskola',
        'slug' => 'salsa_mojito',
        'city' => 'Budapest',
        'description' => '<p>Kubai salsa, bachata, west coast swing és zumba oktatás Budapest több kerületében, valamint Dunakeszin, Gödöllőn, Gyöngyösön, Hatvanban. Táborok, wellness hétvégék, bulik.</p>',
        'website_url' => 'https://www.salsamojito.hu/',
        'email' => 'info@salsamojito.hu',
        'phone' => '+36 20 418 1091',
        'schedule_url' => 'https://www.salsamojito.hu/',
        'accepts_beginners' => 1,
        'has_kids_classes' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' https://www.salsamojito.hu/',
        'locations' => [
            seed_loc('Óbuda – Sun Palace / Perc utcai terem', '', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
                [null, 'Zumba', 'adult', 'all', 'group'],
                [null, 'Zumba Gold', 'senior', 'all', 'group'],
            ]), '3. kerület'),
            seed_loc('Újpesti Kulturális Központ', '', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
                [null, 'West Coast Swing', 'adult', 'all', 'group'],
                [null, 'Zumba', 'adult', 'all', 'group'],
            ]), '4. kerület'),
            seed_loc('Kőbánya – Kőrösi KK / Újhegyi Közösségi Ház', '', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
                [null, 'Zumba', 'adult', 'all', 'group'],
            ]), '10. kerület'),
            seed_loc('CSILI Művelődési Központ', '', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
                [null, 'Zumba Gold', 'senior', 'all', 'group'],
            ]), '20. kerület Pesterzsébet'),
            seed_loc('VOKE József Attila Művelődési Központ', '', 'Dunakeszi', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
                [null, 'Zumba', 'adult', 'all', 'group'],
            ])),
            seed_loc('Patak Mozgásklub / Művészetek Háza', '', 'Gödöllő', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
                [null, 'West Coast Swing', 'adult', 'all', 'group'],
                [null, 'Zumba', 'adult', 'all', 'group'],
                [null, 'Zumba Kids', 'kids', 'all', 'kids_group'],
            ])),
            seed_loc('MATE – Magyar Agrár- és Élettudományi Egyetem', '', 'Gyöngyös', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
            ])),
            seed_loc('Hatvani Rendezvényház', '', 'Hatvan', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
            ])),
        ],
        'events' => [
            [
                'id' => 0,
                'title' => 'Oktoberfest Tánchétvége – Balatonfüred',
                'event_type' => 'camp',
                'starts_at' => '2026-10-09 15:00',
                'ends_at' => '2026-10-11 14:00',
                'description' => 'Salsa Mojito tánchétvége.',
                'registration_url' => 'https://www.salsamojito.hu/',
                'is_published' => 0,
                'location_id' => 0,
                'price_info' => '',
            ],
        ],
    ],
    [
        'name' => 'Bachata Club Hungary',
        'slug' => 'bachata_club_hungary',
        'city' => 'Budapest',
        'description' => '<p>Bachata-fókuszú közösség és oktatás Budapesten: kezdő, középhaladó, haladó, footwork és koreográfia / fellépő csoport. Több helyszínen (Broadway Dance Center, Kultur Dance Center, V29, Táncosok Klubja, Roxy, Etele/Chokito stb.).</p>',
        'website_url' => 'https://bachataclubhungary.hu/',
        'email' => 'bachataclubhungary@gmail.com',
        'phone' => '+36 20 205 2899',
        'schedule_url' => 'https://bachataclubhungary.hu/oratipusok/kezdo-bachata-tanfolyam-budapesten-bachata-club-hungary/',
        'accepts_beginners' => 1,
        'has_performance_team' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' Alternatív tel: +36 70 637 2027 (Brumi). https://bachataclubhungary.hu/',
        'locations' => [
            seed_loc('Broadway Dance Center', '', 'Budapest', '', seed_offs([
                [STYLE_BACHATA, 'Bachata', 'adult', 'beginner', 'group', 'Kedd 18:00 – Brumi & Rita'],
            ])),
            seed_loc('Kultur Dance Center', '', 'Budapest', '', seed_offs([
                [STYLE_BACHATA, 'Bachata', 'adult', 'beginner', 'group', 'Hétfő 18:30 – Seb & Betti'],
            ])),
            seed_loc('V29 Studio', '', 'Budapest', '', seed_offs([
                [STYLE_BACHATA, 'Bachata', 'adult', 'beginner', 'group', 'Kedd 19:00 – Seb & Anna'],
            ])),
            seed_loc('Táncosok Klubja', '', 'Budapest', '', seed_offs([
                [STYLE_BACHATA, 'Bachata', 'adult', 'beginner', 'group', 'Hétfő 18:30 – Gabesz & Réka'],
            ])),
            seed_loc('Roxy Stúdió', 'Tátra u. 4.', 'Budapest', '', seed_offs([
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
            ])),
            seed_loc('Etele / Chokito terem', '', 'Budapest', '', seed_offs([
                [STYLE_BACHATA, 'Bachata', 'adult', 'beginner', 'group', 'Szerda 18:00 – Gabesz & Réka'],
            ])),
        ],
    ],
    [
        'name' => 'Next Generation Salsa',
        'slug' => 'next_generation_salsa',
        'city' => 'Budapest',
        'description' => '<p>Kubai salsa oktatás kezdőtől haladóig saját stúdióban (VII. Osvát u. 9.). Lady style, men style / footwork, reggaeton, afro-kubai rumba, timba. Vezető oktatók: Váradi-Szabó Marci & Panni.</p>',
        'website_url' => 'https://www.ngsalsa.com/',
        'email' => 'nextgenerationsalsa@gmail.com',
        'phone' => '+36 20 955 6876',
        'schedule_url' => 'https://www.ngsalsa.com/',
        'accepts_beginners' => 1,
        'has_performance_team' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' Panni: +36 20 935 2680. https://www.ngsalsa.com/kapcsolat',
        'locations' => [
            seed_loc('NG Salsa Főhadiszállás', 'Osvát utca 9.', 'Budapest', '1073', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'beginner', 'group'],
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'intermediate', 'group'],
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'advanced', 'group'],
                [null, 'Lady Style', 'adult', 'all', 'group'],
                [null, 'Men Style / Footwork', 'adult', 'all', 'group'],
                [null, 'Reggaeton', 'adult', 'all', 'group'],
                [null, 'Afro-kubai rumba', 'adult', 'all', 'group'],
            ])),
        ],
    ],
    [
        'name' => 'Carmen Salsa Tánciskola',
        'slug' => 'carmen_salsa',
        'city' => 'Budapest',
        'description' => '<p>Régóta működő budapesti salsa iskola (Carmen Dance System). Több helyszínen oktatnak; nagy salsa közösség, rendszeres rendezvények.</p>',
        'website_url' => 'http://www.carmen-dance.hu/',
        'email' => 'w.eva@carmen-dance.hu',
        'phone' => '+36 30 823 2311',
        'schedule_url' => 'http://www.carmen-dance.hu/regi/orarend.php',
        'accepts_beginners' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' Ügyfélszolgálat: Papp Éva. Latinfo beváltóhely.',
        'locations' => [
            seed_loc('Gazdasági Minisztérium', 'Margit körút 83-85.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'all', 'group'],
            ]), 'II. kerület'),
            seed_loc('Csik Ferenc Általános Iskola és Gimnázium', 'Medve u. 5-7.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'all', 'group'],
            ]), 'II. kerület'),
            seed_loc('Október 6. utcai terem', 'Október 6. u. 7.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'all', 'group'],
            ]), 'V. kerület'),
            seed_loc('MVGYOSZ', 'Hermina út 47.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'all', 'group'],
            ]), 'XIV. kerület'),
        ],
    ],
    [
        'name' => 'Cuba2 Tánciskola',
        'slug' => 'cuba2',
        'city' => 'Budapest',
        'description' => '<p>Kubai salsa és reggaeton oktatás több budapesti helyszínen (Andrássy út, Veres Pálné u., Rökk Szilárd u., Tátra u.).</p>',
        'website_url' => 'http://cuba2.hu/',
        'email' => 'zsuzsa@salsapro.hu',
        'phone' => '+36 20 465 3099',
        'schedule_url' => 'http://cuba2.hu/',
        'accepts_beginners' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' Ügyfélszolgálat: Domonkos Zsuzsa. Latinfo beváltóhely.',
        'locations' => [
            seed_loc('Andrássy út 98.', 'Andrássy út 98.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'beginner', 'group'],
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'intermediate', 'group'],
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'advanced', 'group'],
            ]), 'VI. kerület'),
            seed_loc('Veres Pálné u. 19.', 'Veres Pálné u. 19.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
            ]), 'V. kerület'),
            seed_loc('Tükörműhely / Rökk Szilárd u. 21.', 'Rökk Szilárd u. 21.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
            ]), 'VIII. kerület'),
            seed_loc('Roxy Stúdió', 'Tátra u. 4.', 'Budapest', '', seed_offs([
                [null, 'Reggaeton', 'adult', 'all', 'group'],
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
            ]), 'XIII. kerület'),
        ],
    ],
    [
        'name' => 'Ritmo de la Luna',
        'slug' => 'ritmo_de_la_luna',
        'city' => 'Budapest',
        'description' => '<p>Kubai és vonalas (LA) salsa, bachata oktatás Budapesten és Vecsésen. Több helyszín (Roxy, FMK, Indigó, IPA, Klinikák, Vecsés).</p>',
        'website_url' => 'https://www.ritmodelaluna.hu/',
        'email' => 'werthani@gmail.com',
        'phone' => '+36 20 572 7792',
        'schedule_url' => 'https://www.ritmodelaluna.hu/',
        'accepts_beginners' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' Alternatív: mail@ritmodelaluna.hu / +36 70 258 5958 (Martincsevics György). Latinfo beváltóhely.',
        'locations' => [
            seed_loc('Roxy Stúdió', 'Tátra u. 4.', 'Budapest', '', seed_offs([
                [STYLE_LINE, 'LA / vonalas salsa', 'adult', 'beginner', 'group'],
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
            ])),
            seed_loc('Ferencvárosi Művelődési Központ', 'Haller utca 27.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'beginner', 'group', 'Kedd 19:00–20:30'],
            ]), 'IX. kerület'),
            seed_loc('Indigó PinceKlub', 'Őr u. 1.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
            ]), 'VIII. kerület'),
            seed_loc('Arany János utcai terem', 'Arany János u. 6-8.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
            ]), 'V. kerület'),
            seed_loc('IPA díszterem', 'Wesselényi u. 73.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
            ]), 'VII. kerület'),
            seed_loc('Klinikák terem', 'Vendel u. 1.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
            ]), 'IX. kerület'),
            seed_loc('JAM Ház', 'Károly u. 1.', 'Vecsés', '2220', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
            ])),
            seed_loc('Vecsés – Vörösmarty u.', 'Vörösmarty utca 6-12.', 'Vecsés', '2220', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
            ])),
        ],
    ],
    [
        'name' => 'Salsa Pantera',
        'slug' => 'salsa_pantera',
        'city' => 'Budapest',
        'description' => '<p>Salsa oktatás (LA, NY, cubana), lady/men style, cha-cha, zouk. Helyszínek: Kolosy Táncstúdió (Új Udvar) és Urban Dance Studio.</p>',
        'website_url' => 'http://salsapantera.hu/',
        'email' => 'info@salsapantera.hu',
        'phone' => '+36 70 252 4139',
        'accepts_beginners' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' Ügyfélszolgálat: Knoll Márta. Latinfo beváltóhely. Weboldal állapota változó lehet.',
        'locations' => [
            seed_loc('Kolosy Táncstúdió – Új Udvar', 'Bécsi út 38-44.', 'Budapest', '', seed_offs([
                [STYLE_LINE, 'Salsa LA / NY', 'adult', 'all', 'group'],
                [STYLE_CUBAN, 'Salsa Cubana', 'adult', 'beginner', 'group'],
                [null, 'Lady Style', 'adult', 'all', 'group'],
                [null, 'Men Style', 'adult', 'all', 'group'],
                [STYLE_ZOUK, 'Zouk / Lambada', 'adult', 'all', 'group'],
            ]), 'III. kerület'),
            seed_loc('Urban Dance Studio', 'Visegrádi u. 29.', 'Budapest', '', seed_offs([
                [STYLE_LINE, 'Salsa', 'adult', 'all', 'group'],
                [STYLE_CUBAN, 'Salsa Cubana', 'adult', 'all', 'group'],
            ]), 'XIII. kerület'),
        ],
    ],
    [
        'name' => 'Salsa La Cubana (Kubai Tánc Akadémia)',
        'slug' => 'salsa_la_cubana',
        'city' => 'Budapest',
        'description' => '<p>Kubai táncok akadémiája: salsa, son, woman style, zumba, reggaeton. Korábbi Akácfa u. helyszín költözés alatt / Bajza u. 44. is említve forrásokban.</p>',
        'website_url' => 'http://www.salsalacubana.com/',
        'email' => 'info@salsalacubana.com',
        'phone' => '+36 20 944 1776',
        'schedule_url' => 'http://www.salsalacubana.com/orarend.htm',
        'accepts_beginners' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' Ügyfélszolgálat: B. Bíró Zoltán. Helyszín költözés – ellenőrizendő!',
        'locations' => [
            seed_loc('Bajza utca 44.', 'Bajza utca 44.', 'Budapest', '1062', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
                [null, 'Son Cubano', 'adult', 'all', 'group'],
                [null, 'Woman Style', 'adult', 'all', 'group'],
                [null, 'Reggaeton', 'adult', 'all', 'group'],
                [null, 'Zumba', 'adult', 'all', 'group'],
            ]), 'VI. kerület – forrás: tanc.hu'),
        ],
    ],
    [
        'name' => 'SalsAmor / Salseria',
        'slug' => 'salsamor',
        'city' => 'Budapest',
        'description' => '<p>Kubai és LA salsa, bachata; afro (kizomba, semba), brazil (zouk, forró) vonalak is. Több budapesti helyszín (Eötvös 10, Madách Gimnázium, Sportmax2).</p>',
        'website_url' => 'http://www.salsamor.hu/',
        'email' => 'info@salsamor.hu',
        'phone' => '+36 30 227 4055',
        'schedule_url' => 'http://www.salsamor.hu/tanfolyamok-budapesten',
        'accepts_beginners' => 1,
        'has_performance_team' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' Latinfo beváltóhely. Alternatív tel. említés: +36 30 555 6499.',
        'locations' => [
            seed_loc('Eötvös 10 Kulturális platform', 'Eötvös u. 10.', 'Budapest', '1067', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
                [STYLE_LINE, 'LA salsa', 'adult', 'all', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
                [STYLE_KIZOMBA, 'Kizomba', 'adult', 'all', 'group'],
            ])),
            seed_loc('Madách Imre Gimnázium díszterem', 'Barcsay utca 5.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
            ]), 'VII. kerület'),
            seed_loc('Sportmax2 – Hegyvidék', 'Csörsz u. 14-16.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
            ]), 'XI. kerület, MOM park mellett'),
        ],
    ],
    [
        'name' => 'Port de Bras & Salsa Fusion',
        'slug' => 'port_de_bras_salsa_fusion',
        'city' => 'Budapest',
        'description' => '<p>Salsa és port de bras / fusion jellegű oktatás. Helyszínek: Roxy Stúdió, Astoria Dance & Sport Centrum.</p>',
        'website_url' => 'https://www.wix.com/EgressyDora/PortDeBras',
        'email' => 'egressy_dora@yahoo.com',
        'phone' => '+36 30 244 5762',
        'accepts_beginners' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' Ügyfélszolgálat: Egressy Dóra. Latinfo beváltóhely – web Wix.',
        'locations' => [
            seed_loc('Roxy Stúdió', 'Tátra u. 4.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'all', 'group'],
                [STYLE_LINE, 'Salsa fusion', 'adult', 'all', 'group'],
            ])),
            seed_loc('Astoria Dance & Sport Centrum', 'Magyar u. 36.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'all', 'group'],
            ]), 'V. kerület'),
        ],
    ],
    [
        'name' => 'Indigó (Kortárs Hastánc / Indigó PinceKlub)',
        'slug' => 'indigo_pinceklub',
        'city' => 'Budapest',
        'description' => '<p>Indigó PinceKlub (VIII. Őr u. 1.) – latin és egyéb táncórák helyszíne; kapcsolat a Kortárs Hastánc / Indigó közösséggel.</p>',
        'website_url' => 'http://www.kortarshastanc.hu/',
        'email' => 'sarkadi.eszter.mirka@gmail.com',
        'phone' => '+36 70 320 9901',
        'accepts_beginners' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' Ügyfélszolgálat: Sarkadi Eszter Mirka. Latinfo beváltóhely – stílusok ellenőrizendők.',
        'locations' => [
            seed_loc('Indigó PinceKlub', 'Őr u. 1.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'all', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
            ]), 'VIII. kerület'),
        ],
    ],
    [
        'name' => 'TáncSuli.hu – Koffer Tánciskolája / Belvárosi Tánciskola',
        'slug' => 'tancsuli_koffer',
        'city' => 'Budapest',
        'description' => '<p>Tánctanfolyamok, intenzívek, workshopok, magánórák (pl. esküvői nyitótánc), terem bérlés. Helyszínek: Tükörműhely, Schönherz Parkett Klub.</p>',
        'website_url' => 'https://www.tancsuli.hu/',
        'email' => 'tancsuli.hu@freemail.hu',
        'phone' => '+36 30 996 8902',
        'accepts_beginners' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' Ügyfélszolgálat: Komáromi Ferenc (Koffer). belvarositanciskola.hu is. Latinfo beváltóhely.',
        'locations' => [
            seed_loc('Tükörműhely Tánc Stúdió', 'Rökk Szilárd u. 21.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'all', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
                [STYLE_BALLROOM, 'Társastánc', 'adult', 'all', 'group'],
            ]), 'VIII. kerület'),
            seed_loc('Schönherz – Parkett Klub', 'Irinyi József út 42.', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'all', 'group'],
            ]), 'XI. kerület'),
        ],
    ],
    [
        'name' => 'Peter & Lili Bachata Sensual',
        'slug' => 'peter_lili_bachata_sensual',
        'city' => 'Budapest',
        'description' => '<p>Bachata sensual oktatás: páros kurzusok, ladies styling, online anyagok. Webshop / kurzusportál: bachatasensual.hu.</p>',
        'website_url' => 'https://www.bachatasensual.hu/',
        'email' => '',
        'phone' => '',
        'schedule_url' => 'https://www.bachatasensual.hu/product-category/onlinecourses/',
        'accepts_beginners' => 1,
        'languages' => 'HU, EN',
        'admin_notes' => $sourceNote . ' Latinfo TáncoljOtthon partner. Kontakt a weboldalon. Online kurzusok is.',
        'locations' => [
            seed_loc('Budapest / online', '', 'Budapest', '', seed_offs([
                [STYLE_BACHATA, 'Bachata sensual', 'adult', 'all', 'group'],
                [STYLE_BACHATA, 'Bachata sensual', 'adult', 'all', 'private'],
                [null, 'Ladies Styling', 'adult', 'all', 'group'],
            ]), 'Helyszíni órák + online kurzusok'),
        ],
    ],
    [
        'name' => 'Engy & Gery Dance Project',
        'slug' => 'engy_gery',
        'city' => 'Debrecen',
        'description' => '<p>Salsa, bachata, kizomba és west coast swing oktatás Debrecenben és Miskolcon. Logikus, lépésről-lépésre felépített módszer. Első óra / bemutató gyakran ingyenes. Párcsere opcionális.</p>',
        'website_url' => 'https://www.engygery.com/',
        'email' => '',
        'phone' => '',
        'schedule_url' => 'https://www.engygery.com/orarend/',
        'pricing_info' => 'Havi bérlet 13000 Ft (korlátlan a választott stílusra); 4 alkalmas bérlet 15000 Ft (6 hét). Kombinált bérlet több stílusra kedvezménnyel.',
        'trial_lesson_info' => 'Első óra / bemutató gyakran ingyenes és kötelezettségmentes.',
        'accepts_beginners' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' https://www.engygery.com/ – e-mail/telefon a webjelentkezésen.',
        'locations' => [
            seed_loc('Debrecen – fő helyszín', '', 'Debrecen', '', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'all', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
                [STYLE_KIZOMBA, 'Kizomba', 'adult', 'all', 'group'],
                [null, 'West Coast Swing', 'adult', 'all', 'group'],
            ]), 'Pontos cím az órarendben.'),
            seed_loc('Miskolc', '', 'Miskolc', '', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'all', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
                [null, 'Lady style', 'adult', 'all', 'group'],
            ]), 'Pontos cím az órarendben.'),
        ],
    ],
    [
        'name' => 'Onyx Dance Studio',
        'slug' => 'onyx_dance_studio',
        'city' => 'Debrecen',
        'description' => '<p>Debreceni stúdió (Turn Terem): kubai salsa, bachata, kizomba, brazil zouk. Rendszeres bulik, workshopok, fesztiválok (Flow & Feel, Magical Forest party).</p>',
        'website_url' => 'https://onyxdancestudio.hu/',
        'facebook_url' => 'https://www.facebook.com/onyx.dance.hungary',
        'instagram_url' => '',
        'email' => '',
        'phone' => '',
        'accepts_beginners' => 1,
        'has_performance_team' => 0,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' https://onyxdancestudio.hu/ – kontakt a social / web űrlapon.',
        'locations' => [
            seed_loc('Turn Terem', 'Mikepércsi út 10.', 'Debrecen', '4030', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'beginner', 'group'],
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'intermediate', 'group'],
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'advanced', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
                [STYLE_KIZOMBA, 'Kizomba', 'adult', 'all', 'group'],
                [STYLE_ZOUK, 'Brazil zouk', 'adult', 'beginner', 'group'],
            ])),
        ],
    ],
    [
        'name' => 'Bachata Dance Tánciskola',
        'slug' => 'bachata_dance_debrecen',
        'city' => 'Debrecen',
        'description' => '<p>Debreceni bachata és kubai salsa tánciskola. Saját bachata terem a Domb u. 1. alatt.</p>',
        'website_url' => 'https://bachatadance.hu/',
        'email' => 'info@bachateros.hu',
        'phone' => '+36 30 945 8636',
        'schedule_url' => 'https://bachatadance.hu/',
        'accepts_beginners' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' https://bachatadance.hu/kapcsolat/',
        'locations' => [
            seed_loc('Bachata terem', 'Domb u. 1.', 'Debrecen', '', seed_offs([
                [STYLE_BACHATA, 'Bachata', 'adult', 'beginner', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'intermediate', 'group'],
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
            ]), '2. terem is'),
        ],
    ],
    [
        'name' => 'Euphoria / Táncvilág Stúdió',
        'slug' => 'tancvilag_euphoria',
        'city' => 'Debrecen',
        'description' => '<p>Kelet-Magyarország: Debrecen és Nyíregyháza. Bachata, kizomba, kubai salsa, zouk, argentin tangó. Euphoria Dance Festival szervezői; wellness hétvégék.</p>',
        'website_url' => 'https://tanc-vilag.hu/',
        'facebook_url' => 'https://www.facebook.com/tancvilagstudio/',
        'youtube_url' => 'https://www.youtube.com/channel/UCCGCPKX__tfHzf6JPUKGY1w',
        'email' => '',
        'phone' => '',
        'accepts_beginners' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' Korábbi név: Táncvilág Stúdió. Latinfo TáncoljOtthon partner.',
        'locations' => [
            seed_loc('Debrecen', '', 'Debrecen', '', seed_offs([
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group', 'Hétfő + szerda + csütörtök'],
                [STYLE_KIZOMBA, 'Kizomba', 'adult', 'all', 'group', 'Szerda'],
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
                [STYLE_ZOUK, 'Zouk', 'adult', 'all', 'group', 'Hétfő'],
                [null, 'Argentin tangó', 'adult', 'all', 'group', 'Kedd'],
            ])),
            seed_loc('Nyíregyháza', '', 'Nyíregyháza', '', seed_offs([
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group', 'Hétfő'],
                [STYLE_KIZOMBA, 'Kizomba', 'adult', 'all', 'group', 'Hétfő'],
            ])),
        ],
    ],
    [
        'name' => 'SzegeDance Tánciskola',
        'slug' => 'szegedance',
        'city' => 'Szeged',
        'description' => '<p>Szegedi tánciskola: társastánc, kubai salsa, bachata, latin body movement, 50+ csoportok, esküvői tánc, magánórák.</p>',
        'website_url' => 'https://szegedance.hu/',
        'email' => '',
        'phone' => '',
        'schedule_url' => 'https://szegedance.hu/',
        'accepts_beginners' => 1,
        'has_kids_classes' => 0,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' https://szegedance.hu/ – kontakt a Kapcsolat oldalon.',
        'locations' => [
            seed_loc('Szeged – fő helyszínek', '', 'Szeged', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'all', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
                [STYLE_BALLROOM, 'Társastánc', 'adult', 'all', 'group'],
                [STYLE_BALLROOM, 'Társastánc', 'senior', 'beginner', 'group', '50+'],
                [null, 'Latin Body Movement', 'adult', 'all', 'group'],
                [STYLE_BALLROOM, 'Esküvői tánc', 'adult', 'all', 'private'],
            ]), 'Több helyszín – részletek az órarendben.'),
        ],
    ],
    [
        'name' => 'Flames Tánc- és Mozgásstúdió',
        'slug' => 'flames_gyor',
        'city' => 'Győr',
        'description' => '<p>Győri stúdió: bachata és társastánc (latin + standard). Alapító / oktató: Nagy Dávid.</p>',
        'website_url' => 'https://flamesstudio.hu/',
        'email' => 'info@flamesstudio.hu',
        'phone' => '+36 20 312 1108',
        'schedule_url' => 'https://flamesstudio.hu/bachata-gyor/',
        'accepts_beginners' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' Alternatív e-mail: flamestms@gmail.com. https://flamesstudio.hu/kapcsolat/',
        'locations' => [
            seed_loc('Flames Stúdió', 'Ipar u. 51/B', 'Győr', '', seed_offs([
                [STYLE_BACHATA, 'Bachata', 'adult', 'beginner', 'group', 'Kedd 19:00–20:00'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'intermediate', 'group'],
                [STYLE_BALLROOM, 'Társastánc', 'adult', 'all', 'group'],
            ]), 'Bejárat: Puskás Tivadar felől az udvarba'),
        ],
    ],
    [
        'name' => 'Salsa Mágica',
        'slug' => 'salsa_magica_pecs',
        'city' => 'Pécs',
        'founded_year' => 2004,
        'description' => '<p>2004 óta salsa oktatás Pécs Uránvárosban. Több szint; salsa stílusok mellett merengue, bachata, lambada, mambo, cha-cha-cha, rumba, samba is.</p>',
        'website_url' => 'https://salsapecs.gportal.hu/',
        'email' => 'salsapecs@citromail.hu',
        'phone' => '',
        'accepts_beginners' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' Régi G-Portál oldal – elérhetőségek ellenőrizendők.',
        'locations' => [
            seed_loc('Pécs – Uránváros', '', 'Pécs', '', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'beginner', 'group', 'Kedd 18:00–20:00'],
                [STYLE_CUBAN, 'Salsa', 'adult', 'intermediate', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
                [null, 'Merengue / mambo / cha-cha', 'adult', 'all', 'group'],
            ]), 'Pontos cím a régi oldalon / közösségi médiában.'),
        ],
    ],
    [
        'name' => 'SalsaQuimera Tánciskola',
        'slug' => 'salsaquimera_tatabanya',
        'city' => 'Tatabánya',
        'description' => '<p>Tatabányai latin tánciskola: salsa, bachata, argentin tangó. Helyszín: Puskin Művelődési Ház.</p>',
        'website_url' => 'http://sqtb.webnode.hu/',
        'email' => 'salsaquimera@gmail.com',
        'phone' => '+36 30 326 8768',
        'accepts_beginners' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' Ügyfélszolgálat: Mayer Ildikó. Latinfo beváltóhely.',
        'locations' => [
            seed_loc('Puskin Művelődési Ház', 'Kossuth L. u. 4.', 'Tatabánya', '2800', seed_offs([
                [STYLE_CUBAN, 'Salsa', 'adult', 'beginner', 'group'],
                [STYLE_CUBAN, 'Salsa', 'adult', 'advanced', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'all', 'group'],
                [null, 'Argentin tangó', 'adult', 'all', 'group'],
            ])),
        ],
    ],
    [
        'name' => 'SwingShot Tánciskola',
        'slug' => 'swingshot',
        'city' => 'Budapest',
        'description' => '<p>Swing / társasági táncok oktatása; Casa De La Música helyszín. Latinfo beváltóhely listán szerepel.</p>',
        'website_url' => 'http://swingshot.hu/',
        'email' => 'gombostimi@hotmail.com',
        'phone' => '+36 20 977 8939',
        'accepts_beginners' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' Ügyfélszolgálat: Gombos Tímea. Inkább swing – latin kapcsolódás korlátozott.',
        'locations' => [
            seed_loc('Casa De La Música', 'Vas u. 16.', 'Budapest', '', seed_offs([
                [null, 'Swing / Lindy / Boogie', 'adult', 'all', 'group'],
            ]), 'VIII. kerület'),
        ],
    ],
    [
        'name' => 'Salsa Con Timba Tánciskola',
        'slug' => 'salsa_con_timba',
        'city' => 'Budapest',
        'description' => '<p>Kubai salsa és timba oktatás Pestlőrincen / Köki mellett (KMO). Kezdő–haladó csoportok, bachata a partykon, fellépő formációk. Tanárok: Varsányi Gábor (Varsi), Kácser Mónika, Soproni József, Lóki Dániel.</p>',
        'website_url' => 'http://www.salsacontimba.hu/',
        'email' => 'varsi@salsacontimba.hu',
        'phone' => '+36 30 436 1954',
        'schedule_url' => 'http://www.salsacontimba.hu/',
        'pricing_info' => '8 alkalom 19900 Ft, 4 alkalom 11500 Ft, 1 alkalom 3500 Ft.',
        'trial_lesson_info' => 'Új kezdő csoportoknál regisztráció kötelező; részletek a weboldalon.',
        'accepts_beginners' => 1,
        'has_performance_team' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' http://www.salsacontimba.hu/',
        'locations' => [
            seed_loc('KMO – Köki Terminál mellett', '', 'Budapest', '1182', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'beginner', 'group', 'Csütörtök 19:00 / Kedd 18:30'],
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'intermediate', 'group', 'Csütörtök 20:00'],
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'advanced', 'group', 'Csütörtök 20:00'],
                [null, 'Timba / Hypersalsa', 'adult', 'all', 'group'],
                [STYLE_BACHATA, 'Bachata', 'adult', 'beginner', 'group', 'Partykon is'],
            ]), 'Pestlőrinc – pontos teremcím a jelentkezési oldalon'),
            seed_loc('Party helyszín – Szabadka utca', 'Szabadka utca 17.', 'Budapest', '1182', seed_offs([
                [STYLE_CUBAN, 'Salsa / Timba party', 'adult', 'all', 'social'],
            ]), 'Pestlőrinc'),
        ],
        'events' => [
            [
                'id' => 0,
                'title' => 'Salsa Party – Pestlőrinc',
                'event_type' => 'other',
                'starts_at' => '2026-10-22 20:00',
                'ends_at' => '2026-10-23 01:00',
                'description' => 'Salsa, bachata és timba buli; 20:30-tól alap és közép bachata oktatás.',
                'price_info' => 'Belépő 3500 Ft',
                'registration_url' => 'http://www.salsacontimba.hu/',
                'is_published' => 0,
                'location_id' => 0,
            ],
        ],
    ],
    [
        'name' => 'Cuba Baila Tánciskola',
        'slug' => 'cuba_baila',
        'city' => 'Budapest',
        'description' => '<p>Hagyományos kubai táncoktatás Budapesten Lili Garcés vezetésével (kubai tánctanár, koreográfus, 1995 óta Magyarországon). Kubai salsa, Son Cubano®, FitDance Latino & Lady Style, Rueda de Casino.</p>',
        'website_url' => 'https://cubabailatanciskola.com/',
        'email' => '',
        'phone' => '',
        'schedule_url' => 'https://cubabailatanciskola.com/',
        'accepts_beginners' => 1,
        'languages' => 'HU, ES',
        'admin_notes' => $sourceNote . ' https://cubabailatanciskola.com/ – tanárok: Lili Garcés, Osney Regal (Son). Helyszín pontosítása a weboldalon.',
        'locations' => [
            seed_loc('Budapest – Cuba Baila órák', '', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'intermediate', 'group', 'Hétfő 19:00 + Rueda'],
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'advanced', 'group', 'Hétfő 19:00'],
                [null, 'Son Cubano', 'adult', 'beginner', 'group', 'Kedd 19:00'],
                [null, 'Son Cubano', 'adult', 'intermediate', 'group', 'Kedd 20:00'],
                [null, 'FitDance Latino & Lady Style', 'adult', 'all', 'group', 'Hétfő 20:00'],
            ]), 'Pontos cím a weboldalon / jelentkezéskor.'),
        ],
    ],
    [
        'name' => 'Salsa Oktatás Budapest',
        'slug' => 'salsa_oktatas_budapest',
        'city' => 'Budapest',
        'description' => '<p>Kubai és vonalas salsa oktatás több budapesti helyszínen (többek között Fáklya Klub / Oktogon). Kezdő–haladó szintek, Club Cubano.</p>',
        'website_url' => 'https://salsaoktatasbudapest.hu/',
        'email' => '',
        'phone' => '',
        'schedule_url' => 'https://salsaoktatasbudapest.hu/orarend.html',
        'accepts_beginners' => 1,
        'has_performance_team' => 1,
        'languages' => 'HU',
        'admin_notes' => $sourceNote . ' https://salsaoktatasbudapest.hu/ – kapcsolat/órarend a weboldalon. Lehet átfedés Goldance / Fáklya helyszínnel.',
        'locations' => [
            seed_loc('Fáklya Klub', 'Csengery / Teréz krt. (Oktogon)', 'Budapest', '1067', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'beginner', 'group'],
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'intermediate', 'group'],
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'advanced', 'group'],
                [STYLE_LINE, 'Vonalas salsa', 'adult', 'beginner', 'group'],
                [STYLE_CUBAN, 'Club Cubano', 'adult', 'advanced', 'group'],
            ])),
            seed_loc('Buda – Szent Imre / Móricz környék', '', 'Budapest', '', seed_offs([
                [STYLE_CUBAN, 'Kubai salsa', 'adult', 'intermediate', 'group'],
            ]), 'Pontos cím az órarendben'),
        ],
    ],
];

    $created = 0;
    $updated = 0;
    $errors = 0;

    foreach ($schools as $pack) {
        $slug = (string) $pack['slug'];
        $existingId = null;
        $st = $db->prepare('SELECT `id` FROM `dance_schools` WHERE `slug` = ? LIMIT 1');
        $st->execute([$slug]);
        $found = $st->fetchColumn();
        if ($found !== false) {
            $existingId = (int) $found;
        }

        $row = dance_school_empty_row();
        $row['name'] = (string) $pack['name'];
        $row['slug'] = $slug;
        $row['description'] = (string) ($pack['description'] ?? '');
        $row['founded_year'] = $pack['founded_year'] ?? null;
        $row['city'] = (string) ($pack['city'] ?? '');
        $row['website_url'] = (string) ($pack['website_url'] ?? '');
        $row['facebook_url'] = (string) ($pack['facebook_url'] ?? '');
        $row['instagram_url'] = (string) ($pack['instagram_url'] ?? '');
        $row['tiktok_url'] = (string) ($pack['tiktok_url'] ?? '');
        $row['youtube_url'] = (string) ($pack['youtube_url'] ?? '');
        $row['email'] = (string) ($pack['email'] ?? '');
        $row['phone'] = (string) ($pack['phone'] ?? '');
        $row['schedule_url'] = (string) ($pack['schedule_url'] ?? '');
        $row['registration_url'] = (string) ($pack['registration_url'] ?? '');
        $row['trial_lesson_info'] = (string) ($pack['trial_lesson_info'] ?? '');
        $row['pricing_info'] = (string) ($pack['pricing_info'] ?? '');
        $row['languages'] = (string) ($pack['languages'] ?? 'HU');
        $row['accepts_beginners'] = !empty($pack['accepts_beginners']) ? 1 : 0;
        $row['has_kids_classes'] = !empty($pack['has_kids_classes']) ? 1 : 0;
        $row['has_performance_team'] = !empty($pack['has_performance_team']) ? 1 : 0;
        $row['is_active'] = 1;
        $row['is_published'] = 0;
        $row['admin_notes'] = (string) ($pack['admin_notes'] ?? $sourceNote);

        try {
            $save = dance_school_save($db, $row, $existingId);
            if (!$save['ok']) {
                throw new RuntimeException((string) ($save['error'] ?? 'save fail'));
            }
            $id = (int) ($save['id'] ?? 0);
            $locs = $pack['locations'] ?? [];
            $venueRows = dance_schools_seed_locations_to_venues($db, is_array($locs) ? $locs : []);
            $syncL = dance_school_sync_venues($db, $id, $venueRows);
            if (!$syncL['ok']) {
                throw new RuntimeException((string) ($syncL['error'] ?? 'loc fail'));
            }
            $evs = $pack['events'] ?? [];
            if (is_array($evs) && $evs !== []) {
                foreach ($evs as &$ev) {
                    if (!is_array($ev)) {
                        continue;
                    }
                    if (!isset($ev['venue_id']) && isset($ev['location_id'])) {
                        $ev['venue_id'] = (int) $ev['location_id'];
                    }
                }
                unset($ev);
                $syncE = dance_school_sync_events($db, $id, $evs);
                if (!$syncE['ok']) {
                    throw new RuntimeException((string) ($syncE['error'] ?? 'ev fail'));
                }
            }
            if ($existingId !== null) {
                $updated++;
                $lines[] = "UPD  #{$id} {$row['name']}";
            } else {
                $created++;
                $lines[] = "NEW  #{$id} {$row['name']}";
            }
        } catch (Throwable $e) {
            $errors++;
            $lines[] = "ERR  {$row['name']}: " . $e->getMessage();
        }
    }

    $total = dance_schools_admin_total_count($db);
    $lines[] = '';
    $lines[] = "Kész. Új: {$created}, frissítve: {$updated}, hiba: {$errors}, összesen DB: {$total}";
    $lines[] = 'Megjegyzés: latin/salsa-bachata-kizomba fókuszú iskolák (Latinfo kontextus).';

    return [
        'ok' => $errors === 0,
        'created' => $created,
        'updated' => $updated,
        'errors' => $errors,
        'total' => $total,
        'lines' => $lines,
    ];
}

// CLI belépési pont
if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)
) {
    require_once dirname(__DIR__) . '/init.php';
    $result = dance_schools_seed_run(getDb());
    foreach ($result['lines'] as $line) {
        echo $line . PHP_EOL;
    }
    if (!empty($result['error'])) {
        fwrite(STDERR, $result['error'] . PHP_EOL);
    }
    exit(($result['errors'] ?? 1) > 0 ? 1 : 0);
}
