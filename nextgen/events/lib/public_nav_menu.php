<?php
declare(strict_types=1);

/**
 * Nyilvános fejléc menü (HU/EN, tetszőleges mélységű almenükkel).
 */

require_once __DIR__ . '/event_public_lang.php';

/**
 * Menü feliratok és ARIA szövegek.
 *
 * @return array<string, string>
 */
function events_public_nav_strings(string $lang): array {
    $hu = [
        'nav_aria' => 'Főmenü',
        'toggle_open' => 'Menü megnyitása',
        'toggle_close' => 'Menü bezárása',
        'submenu_toggle' => '%s almenü',
        'calendar' => 'Naptár',
        'calendar_month' => 'Havi naptár',
        'calendar_list' => 'Eseménylista',
        'djs' => 'DJ-k',
        'organizers' => 'Szervezők',
        'latinfo' => 'Latinfo.hu',
    ];
    $en = [
        'nav_aria' => 'Main menu',
        'toggle_open' => 'Open menu',
        'toggle_close' => 'Close menu',
        'submenu_toggle' => '%s submenu',
        'calendar' => 'Calendar',
        'calendar_month' => 'Month view',
        'calendar_list' => 'Event list',
        'djs' => 'DJs',
        'organizers' => 'Organizers',
        'latinfo' => 'Latinfo.hu',
    ];

    return $lang === 'en' ? $en : $hu;
}

/**
 * Menüpontok fája. Új szint: a `children` kulcsba ágyazott, ugyanilyen szerkezetű elemek.
 *
 * @return list<array{key: string, label: string, href: string, external?: bool, children?: list<array<string, mixed>>}>
 */
function events_public_nav_menu_items(string $lang): array {
    $N = events_public_nav_strings($lang);

    return [
        [
            'key' => 'calendar',
            'label' => $N['calendar'],
            'href' => events_public_home_page_url($lang),
            'children' => [
                [
                    'key' => 'calendar-month',
                    'label' => $N['calendar_month'],
                    'href' => events_public_home_url($lang, ['view' => 'cal']),
                ],
                [
                    'key' => 'calendar-list',
                    'label' => $N['calendar_list'],
                    'href' => events_public_home_url($lang, ['view' => 'list']),
                ],
            ],
        ],
        [
            'key' => 'djs',
            'label' => $N['djs'],
            'href' => events_public_djs_page_url($lang),
        ],
        [
            'key' => 'organizers',
            'label' => $N['organizers'],
            'href' => events_public_organizers_catalog_page_url($lang),
        ],
        [
            'key' => 'latinfo',
            'label' => $N['latinfo'],
            'href' => LATINFO_PUBLIC_HOME_URL,
            'external' => true,
        ],
    ];
}

/**
 * Aktív menüág kulcsai a legfelső szinttől az aktuális oldalig.
 *
 * @return list<string>
 */
function events_public_nav_active_keys(): array {
    $script = strtolower(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
    $view = strtolower(trim((string) ($_GET['view'] ?? '')));

    return match ($script) {
        'index.php', 'public_home.php' => match ($view) {
            'list' => ['calendar', 'calendar-list'],
            'map' => ['calendar'],
            default => ['calendar', 'calendar-month'],
        },
        'megjelenit.php', 'helyszin_megjelenit.php' => ['calendar'],
        'djs.php', 'tag.php' => ['djs'],
        'szervezok.php', 'organizer.php' => ['organizers'],
        default => [],
    };
}

/**
 * Menülista kirajzolása rekurzívan (0. szint a fő sáv, mélyebb szintek almenük).
 *
 * @param list<array<string, mixed>> $items
 * @param list<string> $activeKeys
 * @param array<string, string> $N
 */
function events_public_nav_render_items(array $items, array $activeKeys, array $N, int $depth = 0): void {
    $currentKey = $activeKeys === [] ? '' : (string) $activeKeys[count($activeKeys) - 1];
    echo '<ul class="', $depth === 0 ? 'event-nav__list' : 'event-nav__submenu', '">';

    foreach ($items as $item) {
        $key = trim((string) ($item['key'] ?? ''));
        $label = trim((string) ($item['label'] ?? ''));
        $href = trim((string) ($item['href'] ?? ''));
        if ($label === '' || $href === '') {
            continue;
        }
        $children = is_array($item['children'] ?? null) ? $item['children'] : [];

        $itemClass = 'event-nav__item';
        if ($children !== []) {
            $itemClass .= ' event-nav__item--parent';
        }
        if (in_array($key, $activeKeys, true)) {
            $itemClass .= ' is-active';
        }

        echo '<li class="', h($itemClass), '">';
        echo '<div class="event-nav__row">';
        echo '<a class="event-nav__link', $depth > 0 ? ' event-nav__link--sub' : '', '" href="', h($href), '"';
        if (!empty($item['external'])) {
            echo ' rel="noopener"';
        }
        if ($key !== '' && $key === $currentKey) {
            echo ' aria-current="page"';
        }
        echo '>', h($label), '</a>';

        if ($children !== []) {
            $branchAria = sprintf((string) $N['submenu_toggle'], $label);
            echo '<button type="button" class="event-nav__branch" aria-expanded="false"',
                ' aria-label="', h($branchAria), '" title="', h($branchAria), '">',
                '<svg class="event-nav__chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">',
                '<path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
                '</svg></button>';
        }
        echo '</div>';

        if ($children !== []) {
            events_public_nav_render_items($children, $activeKeys, $N, $depth + 1);
        }
        echo '</li>';
    }

    echo '</ul>';
}
