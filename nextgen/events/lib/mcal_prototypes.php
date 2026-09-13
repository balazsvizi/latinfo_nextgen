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
