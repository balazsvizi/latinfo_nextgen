<?php
declare(strict_types=1);

/**
 * Admin-only mobil naptár prototípusok (sűrű / hét / split).
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
            'lead' => 'Kompakt havi rács és egy soros eseménykártya: idő + cím, a dátum csak a nap fejlécében.',
            'hint' => 'Ugyanaz a teljes hónap, kevesebb üres hely. Cél: 3–4 esemény görgetés nélkül.',
        ],
        'week' => [
            'id' => 'week',
            'letter' => 'B',
            'title' => 'Hét',
            'lead' => 'Sűrű kártyák + a hónap a kiválasztott hétre zsugorodik. A „Teljes hónap” gombbal kinyitható.',
            'hint' => 'Napválasztás után csak az aktuális hét marad. Cél: 5–6 esemény görgetés nélkül.',
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

function mcal_prototype_time_label(string $meta, string $lang): string
{
    if (preg_match('/@\s*(.+)$/u', $meta, $m) === 1) {
        $time = trim($m[1]);
        $time = preg_replace('/\s+[A-Z]{3,5}$/', '', $time) ?? $time;

        return trim($time);
    }

    return $lang === 'en' ? 'All day' : 'Egész nap';
}

/**
 * @param array<string, list<array<string, mixed>>> $payload
 * @return array<string, list<array<string, mixed>>>
 */
function mcal_prototype_enrich_events_payload(array $payload, string $lang): array
{
    foreach ($payload as $dayKey => $items) {
        foreach ($items as $i => $item) {
            $payload[$dayKey][$i]['time'] = mcal_prototype_time_label((string) ($item['meta'] ?? ''), $lang);
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
