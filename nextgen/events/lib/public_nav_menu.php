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
        'partners' => 'Partnereink',
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
        'partners' => 'Our partners',
    ];

    return $lang === 'en' ? $en : $hu;
}

/**
 * Beégetett menü, ha az adatbázis tábla még nincs (vagy nem olvasható).
 *
 * @return list<array{key: string, label: string, href: string, external?: bool, children?: list<array<string, mixed>>}>
 */
function events_public_nav_menu_items_fallback(string $lang): array {
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
            'children' => [
                [
                    'key' => 'partners',
                    'label' => $N['partners'],
                    'href' => events_public_partners_page_url($lang),
                ],
            ],
        ],
    ];
}

/**
 * Menüpontok fája: adatbázisból, ha van szerkesztett menü, különben a beégetett fa.
 *
 * @return list<array{key: string, label: string, href: string, external?: bool, new_tab?: bool, children?: list<array<string, mixed>>}>
 */
function events_public_nav_menu_items(string $lang): array {
    if (function_exists('getDb')) {
        try {
            require_once __DIR__ . '/public_nav_items.php';
            $db = getDb();
            if (events_public_nav_items_table_available($db)) {
                return events_public_nav_items_tree_for_public($db, $lang);
            }
        } catch (Throwable $e) {
            error_log('events_public_nav_menu_items: ' . $e->getMessage());
        }
    }

    return events_public_nav_menu_items_fallback($lang);
}

/**
 * @return list<string>
 */
function events_public_nav_active_keys_by_script(): array {
    $scriptName = str_replace('\\', '/', strtolower((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
    if (str_contains($scriptName, '/nextgen/site/')) {
        return ['latinfo'];
    }

    $script = basename($scriptName);
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
        'partnereink.php' => ['latinfo', 'partners'],
        default => [],
    };
}

/**
 * @param list<array<string, mixed>> $items
 * @param list<string> $wantedKeys
 * @return list<string>
 */
function events_public_nav_trail_by_keys(array $items, array $wantedKeys): array {
    $best = [];
    $walk = static function (array $nodes, array $ancestors) use (&$walk, &$best, $wantedKeys): void {
        foreach ($nodes as $item) {
            $key = trim((string) ($item['key'] ?? ''));
            $next = $key !== '' ? array_merge($ancestors, [$key]) : $ancestors;
            if ($key !== '' && in_array($key, $wantedKeys, true) && count($next) >= count($best)) {
                $best = $next;
            }
            $children = is_array($item['children'] ?? null) ? $item['children'] : [];
            if ($children !== []) {
                $walk($children, $next);
            }
        }
    };
    $walk($items, []);

    return $best;
}

function events_public_nav_current_request_path(): string {
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $path = parse_url($uri, PHP_URL_PATH);
    $path = is_string($path) ? $path : $uri;
    $path = strtolower(rtrim($path, '/'));

    return $path === '' ? '/' : $path;
}

function events_public_nav_href_path(string $href): string {
    $path = parse_url($href, PHP_URL_PATH);
    $path = is_string($path) ? $path : $href;
    $path = strtolower(rtrim($path, '/'));

    return $path === '' ? '/' : $path;
}

/**
 * @param list<array<string, mixed>> $items
 * @return list<string>
 */
function events_public_nav_trail_by_url(array $items): array {
    $currentPath = events_public_nav_current_request_path();
    $currentView = strtolower(trim((string) ($_GET['view'] ?? '')));
    $best = [];
    $bestScore = 0;
    $walk = static function (array $nodes, array $ancestors) use (&$walk, &$best, &$bestScore, $currentPath, $currentView): void {
        foreach ($nodes as $item) {
            $key = trim((string) ($item['key'] ?? ''));
            $next = $key !== '' ? array_merge($ancestors, [$key]) : $ancestors;
            $href = trim((string) ($item['href'] ?? ''));
            if ($href !== '' && $href !== '#') {
                $hrefPath = events_public_nav_href_path($href);
                if ($hrefPath === $currentPath) {
                    $query = parse_url($href, PHP_URL_QUERY);
                    $hrefView = '';
                    if (is_string($query) && $query !== '') {
                        parse_str($query, $q);
                        $hrefView = strtolower(trim((string) ($q['view'] ?? '')));
                    }
                    $score = 10 + strlen($hrefPath);
                    if ($hrefView !== '' && $hrefView === $currentView) {
                        $score += 50;
                    } elseif ($hrefView !== '' && $hrefView !== $currentView) {
                        $score -= 20;
                    }
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $best = $next;
                    }
                }
            }
            $children = is_array($item['children'] ?? null) ? $item['children'] : [];
            if ($children !== []) {
                $walk($children, $next);
            }
        }
    };
    $walk($items, []);

    return $best;
}

/**
 * Aktív menüág kulcsai a legfelső szinttől az aktuális oldalig.
 *
 * @param list<array<string, mixed>>|null $items
 * @return list<string>
 */
function events_public_nav_active_keys(?array $items = null): array {
    $byScript = events_public_nav_active_keys_by_script();
    if ($items === null || $items === []) {
        return $byScript;
    }

    $byKey = events_public_nav_trail_by_keys($items, $byScript);
    if ($byKey !== []) {
        return $byKey;
    }

    return events_public_nav_trail_by_url($items);
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
        $hrefLower = strtolower($href);
        if ($label === '' || $href === '' || str_starts_with($hrefLower, 'javascript:') || str_starts_with($hrefLower, 'data:') || str_starts_with($hrefLower, 'vbscript:')) {
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
        if (!empty($item['new_tab'])) {
            echo ' target="_blank"';
        }
        if (!empty($item['external']) || !empty($item['new_tab'])) {
            echo ' rel="noopener"';
        }
        if ($key !== '') {
            echo ' data-public-nav-track="', h($key), '"';
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
