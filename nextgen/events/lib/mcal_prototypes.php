<?php
declare(strict_types=1);

/**
 * Admin-only mobil naptár prototípusok (sűrű / split).
 */

/**
 * @return array<string, array{id: string, letter: string, title: string, lead: string, hint: string}>
 */
function mcal_prototype_layouts(): array
{
    return [
        'dense' => [
            'id' => 'dense',
            'letter' => 'A',
            'title' => 'Sűrű',
            'lead' => 'Kompakt havi rács és egy soros eseménykártya: cím, jobbra a település, ha nem Budapest.',
            'hint' => 'Ugyanaz a teljes hónap, kevesebb üres hely. Cél: 3–4 esemény görgetés nélkül.',
        ],
        'split' => [
            'id' => 'split',
            'letter' => 'C',
            'title' => 'Split',
            'lead' => 'Sűrű kártyák, a havi rács bent marad, a lista a maradék képernyőn gördül.',
            'hint' => 'A rács nem ugrik el. A lista a viewport alján saját scrollbar-t kap.',
        ],
    ];
}

function mcal_prototype_resolve_layout(string $raw): ?string
{
    $id = strtolower(trim($raw));
    $layouts = mcal_prototype_layouts();

    return isset($layouts[$id]) ? $id : null;
}

function mcal_prototype_city_is_budapest(string $city): bool
{
    $normalized = mb_strtolower(trim($city), 'UTF-8');
    if ($normalized === '') {
        return false;
    }
    if ($normalized === 'budapest' || $normalized === 'bp' || $normalized === 'bp.') {
        return true;
    }

    return str_starts_with($normalized, 'budapest');
}

/**
 * Település a kártyán: üres, ha Budapest vagy nincs megadva.
 */
function mcal_prototype_outside_budapest_city(array $ev): string
{
    $city = trim((string) ($ev['venue_city'] ?? ''));
    if ($city === '' || mcal_prototype_city_is_budapest($city)) {
        return '';
    }

    return $city;
}

/**
 * @param array<string, list<array<string, mixed>>> $payload
 * @param array<string, list<array<string, mixed>>> $byDay
 * @return array<string, list<array<string, mixed>>>
 */
function mcal_prototype_enrich_events_payload(array $payload, array $byDay): array
{
    foreach ($payload as $dayKey => $items) {
        $byId = [];
        foreach ($byDay[$dayKey] ?? [] as $ev) {
            $eid = (int) ($ev['id'] ?? 0);
            if ($eid > 0) {
                $byId[$eid] = $ev;
            }
        }
        foreach ($items as $i => $item) {
            $ev = $byId[(int) ($item['id'] ?? 0)] ?? [];
            $payload[$dayKey][$i]['city'] = mcal_prototype_outside_budapest_city($ev);
        }
    }

    return $payload;
}

function mcal_prototype_page_url(string $layout = '', array $extra = []): string
{
    $params = $extra;
    if ($layout !== '') {
        $params['layout'] = $layout;
    }

    return events_url('mcal_prototypes.php' . ($params !== []
        ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986)
        : ''));
}
