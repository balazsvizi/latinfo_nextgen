<?php
declare(strict_types=1);

/**
 * Nyelv (HU / EN) csak a nyilvános esemény megjelenítőhöz (megjelenit.php).
 * Preferencia: ?lang=hu|en (sütit állít), egyébként events_megjelenit_lang süti, alapértelmezés: hu.
 */
const EVENTS_MEGJELENIT_LANG_COOKIE = 'events_megjelenit_lang';

function events_public_resolve_megjelenit_lang(): string {
    if (isset($_GET['lang'])) {
        $raw = strtolower(trim((string) $_GET['lang']));
        if ($raw === 'en' || $raw === 'hu') {
            $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            setcookie(EVENTS_MEGJELENIT_LANG_COOKIE, $raw, [
                'expires' => time() + 365 * 86400,
                'path' => '/',
                'secure' => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
            $_COOKIE[EVENTS_MEGJELENIT_LANG_COOKIE] = $raw;

            return $raw;
        }
    }
    $c = strtolower(trim((string) ($_COOKIE[EVENTS_MEGJELENIT_LANG_COOKIE] ?? '')));
    if ($c === 'en' || $c === 'hu') {
        return $c;
    }

    return 'hu';
}

/**
 * Belső linkekhez: angolnál kötelező ?lang=en; magyarnál csak ha explicit ?lang=hu van az URL-ben.
 *
 * @return array<string, string>
 */
function events_public_lang_nav_params(string $lang): array {
    if ($lang === 'en') {
        return ['lang' => 'en'];
    }
    if (isset($_GET['lang']) && strtolower(trim((string) $_GET['lang'])) === 'hu') {
        return ['lang' => 'hu'];
    }

    return [];
}

/**
 * Nyilvános esemény oldalak — egyelőre ne indexeljék a keresők.
 */
function events_public_robots_noindex_head_markup(): string {
    return '<meta name="robots" content="noindex, nofollow">' . "\n"
        . '<meta name="googlebot" content="noindex, nofollow">' . "\n";
}

function events_public_send_noindex_header(): void {
    if (!headers_sent()) {
        header('X-Robots-Tag: noindex, nofollow', true);
    }
}

/**
 * Közös navigációs szövegek (esemény főoldal + Latinfo).
 *
 * @return array<string, string>
 */
function events_public_common_nav_strings(string $lang): array {
    $hu = [
        'events_home_link' => 'Esemény naptár',
        'events_home_back' => '← Esemény naptár',
        'events_home_aria' => 'Vissza az esemény naptár főoldalára',
        'logo_events_home_title' => 'Esemény naptár főoldal',
        'logo_events_home_aria' => 'Ugrás az esemény naptár főoldalára',
    ];
    $en = [
        'events_home_link' => 'Event calendar',
        'events_home_back' => '← Event calendar',
        'events_home_aria' => 'Back to the event calendar home',
        'logo_events_home_title' => 'Event calendar home',
        'logo_events_home_aria' => 'Go to the event calendar home',
    ];

    return $lang === 'en' ? $en : $hu;
}

/**
 * @return array<string, string>
 */
function events_public_megjelenit_strings(string $lang): array {
    $hu = [
        'html_title_suffix' => ' – ',
        'lang_nav' => 'Nyelv',
        'lang_hu' => 'Magyar',
        'lang_en' => 'English',
        'eyebrow' => 'Esemény',
        'badge_allday' => 'Egész napos',
        'badge_partner' => 'Latinfo.hu partner',
        'meta_datetime' => 'Időpont',
        'meta_price' => 'Belépő',
        'meta_venue' => 'Helyszín',
        'cta_external' => 'További információ',
        'not_found_title' => 'Nincs ilyen esemény',
        'not_found_body' => 'Nincs ilyen esemény.',
        'admin_edit_title' => 'Szerkesztés',
        'admin_edit_aria' => 'Esemény szerkesztése az adminban',
        'logo_alt' => 'Latinfo.hu',
        'logo_home_title' => 'Latinfo.hu kezdőoldala',
        'logo_home_aria' => 'Ugrás a Latinfo.hu kezdőoldalára',
        'footer_home_link' => 'Latinfo.hu',
        'section_organizers' => 'Szervezők',
        'section_categories' => 'Kategóriák',
        'section_djs' => 'DJ-k',
        'all_djs_link' => 'Összes DJ',
        'section_tags' => 'Címkék',
        'section_main_styles' => 'Fő stílusok',
        'section_supplementary_styles' => 'Kiegészítő stílusok',
    ];
    $en = [
        'html_title_suffix' => ' – ',
        'lang_nav' => 'Language',
        'lang_hu' => 'Hungarian',
        'lang_en' => 'English',
        'eyebrow' => 'Event',
        'badge_allday' => 'All day',
        'badge_partner' => 'Latinfo.hu partner',
        'meta_datetime' => 'Date & time',
        'meta_price' => 'Admission',
        'meta_venue' => 'Venue',
        'cta_external' => 'More information',
        'not_found_title' => 'Event not found',
        'not_found_body' => 'There is no event with this link.',
        'admin_edit_title' => 'Edit',
        'admin_edit_aria' => 'Edit this event in admin',
        'logo_alt' => 'Latinfo.hu',
        'logo_home_title' => 'Latinfo.hu home',
        'logo_home_aria' => 'Go to the Latinfo.hu homepage',
        'footer_home_link' => 'Latinfo.hu',
        'section_organizers' => 'Organizers',
        'section_categories' => 'Categories',
        'section_djs' => 'DJs',
        'all_djs_link' => 'All DJs',
        'section_tags' => 'Tags',
        'section_main_styles' => 'Main styles',
        'section_supplementary_styles' => 'Supplementary styles',
    ];

    return $lang === 'en' ? $en : $hu;
}

/**
 * @return array<string, string>
 */
function events_public_organizer_strings(string $lang): array {
    $hu = [
        'html_title_suffix' => ' – ',
        'lang_nav' => 'Nyelv',
        'lang_hu' => 'Magyar',
        'lang_en' => 'English',
        'eyebrow' => 'Szervező',
        'events_heading' => 'Események',
        'section_upcoming' => 'Aktuális események',
        'section_past' => 'Már lezajlott események',
        'upcoming_empty' => 'Nincs következő vagy folyamatban lévő közzétett esemény.',
        'past_empty' => 'Nincs lezajlott közzétett esemény.',
        'list_empty' => 'Nincs közzétett esemény ehhez a szervezőhöz.',
        'not_found_title' => 'Nincs ilyen szervező',
        'not_found_body' => 'Nincs ilyen szervező.',
        'logo_alt' => 'Latinfo.hu',
        'logo_home_title' => 'Latinfo.hu kezdőoldala',
        'logo_home_aria' => 'Ugrás a Latinfo.hu kezdőoldalára',
        'footer_home_link' => 'Latinfo.hu',
    ];
    $en = [
        'html_title_suffix' => ' – ',
        'lang_nav' => 'Language',
        'lang_hu' => 'Hungarian',
        'lang_en' => 'English',
        'eyebrow' => 'Organizer',
        'events_heading' => 'Events',
        'section_upcoming' => 'Current & upcoming events',
        'section_past' => 'Past events',
        'upcoming_empty' => 'No upcoming or ongoing published events.',
        'past_empty' => 'No past published events.',
        'list_empty' => 'No published events for this organizer.',
        'not_found_title' => 'Organizer not found',
        'not_found_body' => 'There is no organizer with this link.',
        'logo_alt' => 'Latinfo.hu',
        'logo_home_title' => 'Latinfo.hu home',
        'logo_home_aria' => 'Go to the Latinfo.hu homepage',
        'footer_home_link' => 'Latinfo.hu',
    ];

    return $lang === 'en' ? $en : $hu;
}

/**
 * Egy nap megjelenítése (megjelenítő és szervező-lista közös formátuma).
 */
function events_public_format_event_day(int $ts, string $lang): string {
    $huMonths = [
        1 => 'január', 2 => 'február', 3 => 'március', 4 => 'április', 5 => 'május', 6 => 'június',
        7 => 'július', 8 => 'augusztus', 9 => 'szeptember', 10 => 'október', 11 => 'november', 12 => 'december',
    ];
    $enMonths = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];
    if ($lang === 'en') {
        $m = $enMonths[(int) date('n', $ts)];

        return $m . ' ' . (int) date('j', $ts) . ', ' . (int) date('Y', $ts);
    }
    $m = $huMonths[(int) date('n', $ts)];

    return (int) date('Y', $ts) . '. ' . $m . ' ' . (int) date('j', $ts) . '.';
}

/**
 * Kezdő és vége „egy napnak” számít: ugyanazon naptári napon, vagy a vége a következő napon legkésőbb 06:00-ig.
 */
function events_public_event_same_display_day(int $tsStart, int $tsEnd): bool {
    if ($tsEnd < $tsStart) {
        return false;
    }
    if (date('Y-m-d', $tsStart) === date('Y-m-d', $tsEnd)) {
        return true;
    }
    $startMidnight = strtotime(date('Y-m-d', $tsStart) . ' 00:00:00');
    $boundary = strtotime('+1 day +6 hours', $startMidnight);

    return $tsEnd <= $boundary;
}

/**
 * YYYY.MM.DD. (vezető nullákkal a hónapra és napra).
 */
function events_public_megjelenit_ymd_numeric_dots(int $ts): string {
    return date('Y', $ts) . '.' . date('m', $ts) . '.' . date('d', $ts) . '.';
}

/**
 * A hét napja (megjelenítő hero dátumsorhoz).
 */
function events_public_format_event_weekday(int $ts, string $lang): string {
    $w = (int) date('w', $ts);
    if ($lang === 'en') {
        $names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return $names[$w];
    }
    $names = ['vasárnap', 'hétfő', 'kedd', 'szerda', 'csütörtök', 'péntek', 'szombat'];

    return $names[$w];
}

/**
 * Megjelenítő hero: többnyire egy sor YYYY.MM.DD. nap HH:mm - HH:mm (éjfél utáni „hajnali” vég 06:00-ig egy napnak számít).
 *
 * @return list<string>
 */
function events_public_megjelenit_hero_datetime_lines(bool $allday, int|false $tsStart, int|false $tsEnd, string $lang): array {
    if (!$tsStart) {
        return [];
    }
    if ($allday) {
        return events_public_megjelenit_date_lines(true, $tsStart, $tsEnd, $lang);
    }
    if ($tsEnd && events_public_event_same_display_day($tsStart, $tsEnd)) {
        $line = events_public_megjelenit_ymd_numeric_dots($tsStart)
            . ' ' . events_public_format_event_weekday($tsStart, $lang)
            . ' ' . date('H:i', $tsStart) . ' - ' . date('H:i', $tsEnd);

        return [$line];
    }
    if (!$tsEnd) {
        $line = events_public_megjelenit_ymd_numeric_dots($tsStart)
            . ' ' . events_public_format_event_weekday($tsStart, $lang)
            . ' ' . date('H:i', $tsStart);

        return [$line];
    }

    return events_public_megjelenit_date_lines(false, $tsStart, $tsEnd, $lang);
}

/**
 * Kezdő dátum + hét napja, opcionálisan kezdő idő — szervező oldal kártyák (ugyanaz a nap-formátum, mint a megjelenítőn).
 */
function events_public_event_start_date_time_display(bool $allday, int|false $tsStart, string $lang): string {
    if (!$tsStart) {
        return '';
    }
    $out = events_public_megjelenit_day_line($tsStart, $lang);
    if (!$allday) {
        $out .= ' ' . date('H:i', $tsStart);
    }

    return $out;
}

/**
 * Dátum + hét napja (megjelenítő hero / szervezői lista közös formátuma).
 */
function events_public_megjelenit_day_line(int $ts, string $lang): string {
    return events_public_format_event_day($ts, $lang) . ', ' . events_public_format_event_weekday($ts, $lang);
}

/**
 * @return list<string>
 */
function events_public_megjelenit_date_lines(bool $allday, int|false $tsStart, int|false $tsEnd, string $lang): array {
    if (!$tsStart) {
        return [];
    }

    $lines = [];
    if ($tsEnd && date('Y-m-d', $tsStart) === date('Y-m-d', $tsEnd)) {
        $lines[] = events_public_megjelenit_day_line($tsStart, $lang);
        if (!$allday) {
            $lines[] = date('H:i', $tsStart) . ' – ' . date('H:i', $tsEnd);
        }
    } elseif ($tsEnd) {
        $lines[] = events_public_megjelenit_day_line($tsStart, $lang) . ($allday ? '' : ' ' . date('H:i', $tsStart));
        $lines[] = '– ' . events_public_megjelenit_day_line($tsEnd, $lang) . ($allday ? '' : ' ' . date('H:i', $tsEnd));
    } else {
        $line = events_public_megjelenit_day_line($tsStart, $lang);
        if (!$allday) {
            $line .= ' ' . date('H:i', $tsStart);
        }
        $lines[] = $line;
    }

    return $lines;
}

function events_public_megjelenit_cost_text(?float $cf, ?float $ct, string $lang): ?string {
    if ($cf === null && $ct === null) {
        return null;
    }
    $fmtNum = static function (float $x) use ($lang): string {
        $decimals = abs($x - round($x)) < 0.000001 ? 0 : 2;
        $decSep = $lang === 'en' ? '.' : ',';
        $thouSep = $lang === 'en' ? ',' : ' ';

        return number_format($x, $decimals, $decSep, $thouSep);
    };

    if ($cf !== null && $ct !== null) {
        if (abs($cf - $ct) < 0.000001) {
            return $lang === 'en'
                ? $fmtNum($cf) . ' HUF'
                : $fmtNum($cf) . ' Ft';
        }

        return $lang === 'en'
            ? $fmtNum($cf) . ' – ' . $fmtNum($ct) . ' HUF'
            : $fmtNum($cf) . ' – ' . $fmtNum($ct) . ' Ft';
    }
    if ($cf !== null) {
        return $lang === 'en'
            ? $fmtNum($cf) . ' HUF'
            : $fmtNum($cf) . ' Ft';
    }

    return $lang === 'en'
        ? 'Up to ' . $fmtNum((float) $ct) . ' HUF'
        : 'Legfeljebb ' . $fmtNum((float) $ct) . ' Ft';
}

/**
 * Ugyanaz az esemény slug, más nyelvi paraméterrel (váltó linkek).
 */
function events_public_megjelenit_lang_switch_url(string $slug, string $targetLang): string {
    $q = ['slug' => $slug, 'lang' => $targetLang];

    return events_url('megjelenit.php?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986));
}

/**
 * Nyilvános eseményoldal URL (slug + nyelv).
 */
function events_public_event_page_url(string $slug, string $lang): string {
    return events_url('megjelenit.php?' . http_build_query(['slug' => $slug, 'lang' => $lang], '', '&', PHP_QUERY_RFC3986));
}

/**
 * Nyilvános szervező-oldal URL.
 */
function events_public_organizer_page_url(int $organizerId, string $lang): string {
    return events_url('organizer.php?' . http_build_query(['id' => $organizerId, 'lang' => $lang], '', '&', PHP_QUERY_RFC3986));
}

function events_public_organizer_lang_switch_url(int $organizerId, string $targetLang): string {
    return events_url('organizer.php?' . http_build_query(['id' => $organizerId, 'lang' => $targetLang], '', '&', PHP_QUERY_RFC3986));
}

/**
 * @return array<string, string>
 */
function events_public_tag_strings(string $lang): array {
    $hu = [
        'html_title_suffix' => ' – ',
        'lang_nav' => 'Nyelv',
        'lang_hu' => 'Magyar',
        'lang_en' => 'English',
        'events_heading' => 'Események',
        'section_upcoming' => 'Aktuális események',
        'section_past' => 'Már lezajlott események',
        'upcoming_empty' => 'Nincs következő vagy folyamatban lévő közzétett esemény.',
        'past_empty' => 'Nincs lezajlott közzétett esemény.',
        'list_empty' => 'Nincs közzétett esemény ehhez a címkéhez.',
        'not_found_title' => 'Nincs ilyen címke',
        'not_found_body' => 'Nincs ilyen címke.',
        'logo_alt' => 'Latinfo.hu',
        'logo_home_title' => 'Latinfo.hu kezdőoldala',
        'logo_home_aria' => 'Ugrás a Latinfo.hu kezdőoldalára',
        'footer_home_link' => 'Latinfo.hu',
        'admin_edit_title' => 'Szerkesztés',
        'admin_edit_aria' => 'Címke szerkesztése az adminban',
        'all_djs_link' => 'Összes DJ',
    ];
    $en = [
        'html_title_suffix' => ' – ',
        'lang_nav' => 'Language',
        'lang_hu' => 'Hungarian',
        'lang_en' => 'English',
        'events_heading' => 'Events',
        'section_upcoming' => 'Current & upcoming events',
        'section_past' => 'Past events',
        'upcoming_empty' => 'No upcoming or ongoing published events.',
        'past_empty' => 'No past published events.',
        'list_empty' => 'No published events for this tag.',
        'not_found_title' => 'Tag not found',
        'not_found_body' => 'There is no tag with this link.',
        'logo_alt' => 'Latinfo.hu',
        'logo_home_title' => 'Latinfo.hu home',
        'logo_home_aria' => 'Go to the Latinfo.hu homepage',
        'footer_home_link' => 'Latinfo.hu',
        'admin_edit_title' => 'Edit',
        'admin_edit_aria' => 'Edit this tag in admin',
        'all_djs_link' => 'All DJs',
    ];

    return $lang === 'en' ? $en : $hu;
}

/**
 * @return array<string, string>
 */
function events_public_djs_strings(string $lang): array {
    $hu = [
        'html_title_suffix' => ' – ',
        'page_title' => 'DJ-k',
        'page_desc' => 'DJ-k és közzétett eseményeik a Latinfo.hu-n.',
        'eyebrow' => 'DJ-k',
        'lang_nav' => 'Nyelv',
        'lang_hu' => 'Magyar',
        'lang_en' => 'English',
        'logo_alt' => 'Latinfo.hu',
        'logo_home_title' => 'Latinfo.hu kezdőoldala',
        'logo_home_aria' => 'Ugrás a Latinfo.hu kezdőoldalára',
        'footer_home_link' => 'Latinfo.hu',
        'filter_label' => 'Keresés név szerint',
        'filter_placeholder' => 'DJ neve…',
        'sort_label' => 'Rendezés',
        'sort_name_asc' => 'Név (A→Z)',
        'sort_name_desc' => 'Név (Z→A)',
        'sort_events_desc' => 'Aktuális események (több→kevesebb)',
        'sort_events_asc' => 'Aktuális események (kevesebb→több)',
        'sort_upcoming_desc' => 'Aktuális események (több→kevesebb)',
        'events_upcoming' => 'aktuális esemény',
        'next_event' => 'Következő',
        'empty' => 'Nincs DJ a listában.',
        'empty_filter' => 'Nincs találat a szűrésre.',
        'card_aria' => 'DJ profil megnyitása',
    ];
    $en = [
        'html_title_suffix' => ' – ',
        'page_title' => 'DJs',
        'page_desc' => 'DJs and their published events on Latinfo.hu.',
        'eyebrow' => 'DJs',
        'lang_nav' => 'Language',
        'lang_hu' => 'Hungarian',
        'lang_en' => 'English',
        'logo_alt' => 'Latinfo.hu',
        'logo_home_title' => 'Latinfo.hu home',
        'logo_home_aria' => 'Go to the Latinfo.hu homepage',
        'footer_home_link' => 'Latinfo.hu',
        'filter_label' => 'Search by name',
        'filter_placeholder' => 'DJ name…',
        'sort_label' => 'Sort',
        'sort_name_asc' => 'Name (A→Z)',
        'sort_name_desc' => 'Name (Z→A)',
        'sort_events_desc' => 'Upcoming events (most→least)',
        'sort_events_asc' => 'Upcoming events (least→most)',
        'sort_upcoming_desc' => 'Upcoming events (most→least)',
        'events_upcoming' => 'upcoming events',
        'next_event' => 'Next',
        'empty' => 'No DJs in the catalog.',
        'empty_filter' => 'No matches for your search.',
        'card_aria' => 'Open DJ profile',
    ];

    return $lang === 'en' ? $en : $hu;
}

/**
 * @return array<string, string>
 */
function events_public_home_strings(string $lang): array {
    $hu = [
        'html_title_suffix' => ' – ',
        'page_title' => 'Események',
        'page_desc' => 'Közzétett események naptárban és listában a Latinfo.hu-n.',
        'eyebrow' => 'Események',
        'lang_nav' => 'Nyelv',
        'lang_hu' => 'Magyar',
        'lang_en' => 'English',
        'logo_alt' => 'Latinfo.hu',
        'logo_home_title' => 'Latinfo.hu kezdőoldala',
        'logo_home_aria' => 'Ugrás a Latinfo.hu kezdőoldalára',
        'footer_home_link' => 'Latinfo.hu',
        'calendar_aria' => 'Esemény naptár és szűrők',
        'clear_filters' => 'Szűrők törlése',
        'filters_toggle' => 'Szűrők megnyitása',
        'filters_active_badge' => 'aktív',
        'filters_aria' => 'Szűrők',
        'filter_organizer' => 'Szervező',
        'filter_organizer_ph' => 'Részlet a névből…',
        'filter_category' => 'Kategória',
        'filter_all_categories' => 'Összes kategória',
        'filter_tag' => 'Címke',
        'filter_all_tags' => 'Összes címke',
        'filter_dj' => 'DJ',
        'filter_all_djs' => 'Összes DJ',
        'filter_main_style' => 'Fő stílus',
        'filter_all_main_styles' => 'Összes fő stílus',
        'filter_supp_style' => 'Kiegészítő stílus',
        'filter_all_supp_styles' => 'Összes kiegészítő stílus',
        'filter_venue' => 'Helyszín',
        'filter_venue_ph' => 'Helyszín neve…',
        'filter_city' => 'Város',
        'filter_city_ph' => 'Város neve…',
        'filter_name' => 'Esemény neve',
        'filter_name_ph' => 'Keresés a címben…',
        'filter_date_from' => 'Ettől',
        'filter_date_to' => 'Eddig',
        'filter_submit' => 'Szűrés alkalmazása',
        'cal_controls_aria' => 'Naptár vezérlők',
        'month_nav_aria' => 'Hónap választás',
        'prev_month' => 'Előző hónap',
        'next_month' => 'Következő hónap',
        'this_month' => 'Ez a hónap',
        'view_switch_aria' => 'Nézet választó',
        'view_cal' => 'Naptár',
        'view_list' => 'Lista',
        'list_heading' => 'Események',
        'list_empty' => 'Nincs találat a szűrésre.',
        'undated_aria' => 'Dátum nélküli események',
        'undated_title' => 'Dátum nélküli események',
    ];
    $en = [
        'html_title_suffix' => ' – ',
        'page_title' => 'Events',
        'page_desc' => 'Published events in calendar and list view on Latinfo.hu.',
        'eyebrow' => 'Events',
        'lang_nav' => 'Language',
        'lang_hu' => 'Hungarian',
        'lang_en' => 'English',
        'logo_alt' => 'Latinfo.hu',
        'logo_home_title' => 'Latinfo.hu home',
        'logo_home_aria' => 'Go to Latinfo.hu home',
        'footer_home_link' => 'Latinfo.hu',
        'calendar_aria' => 'Event calendar and filters',
        'clear_filters' => 'Clear filters',
        'filters_toggle' => 'Show filters',
        'filters_active_badge' => 'active',
        'filters_aria' => 'Filters',
        'filter_organizer' => 'Organizer',
        'filter_organizer_ph' => 'Part of the name…',
        'filter_category' => 'Category',
        'filter_all_categories' => 'All categories',
        'filter_tag' => 'Tag',
        'filter_all_tags' => 'All tags',
        'filter_dj' => 'DJ',
        'filter_all_djs' => 'All DJs',
        'filter_main_style' => 'Main style',
        'filter_all_main_styles' => 'All main styles',
        'filter_supp_style' => 'Supplementary style',
        'filter_all_supp_styles' => 'All supplementary styles',
        'filter_venue' => 'Venue',
        'filter_venue_ph' => 'Venue name…',
        'filter_city' => 'City',
        'filter_city_ph' => 'City name…',
        'filter_name' => 'Event name',
        'filter_name_ph' => 'Search in title…',
        'filter_date_from' => 'From',
        'filter_date_to' => 'To',
        'filter_submit' => 'Apply filters',
        'cal_controls_aria' => 'Calendar controls',
        'month_nav_aria' => 'Month selection',
        'prev_month' => 'Previous month',
        'next_month' => 'Next month',
        'this_month' => 'This month',
        'view_switch_aria' => 'View switch',
        'view_cal' => 'Calendar',
        'view_list' => 'List',
        'list_heading' => 'Events',
        'list_empty' => 'No matches for your filters.',
        'undated_aria' => 'Events without date',
        'undated_title' => 'Events without date',
    ];

    return $lang === 'en' ? $en : $hu;
}

function events_public_home_page_url(string $lang): string {
    $base = events_url(events_public_home_page_script());
    if ($lang !== 'en') {
        return $base;
    }

    return $base . '?' . http_build_query(['lang' => 'en'], '', '&', PHP_QUERY_RFC3986);
}

function events_public_home_lang_switch_url(string $targetLang): string {
    $q = $_GET;
    $q['lang'] = $targetLang === 'en' ? 'en' : 'hu';
    $base = events_url(events_public_home_page_script());

    return $base . '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
}

function events_public_venue_lang_switch_url(string $slug, string $targetLang): string {
    return events_url('helyszin_megjelenit.php?' . http_build_query([
        'slug' => $slug,
        'lang' => $targetLang,
    ], '', '&', PHP_QUERY_RFC3986));
}

function events_public_djs_page_url(string $lang): string {
    return events_url('djs.php?' . http_build_query(['lang' => $lang], '', '&', PHP_QUERY_RFC3986));
}

function events_public_djs_lang_switch_url(string $targetLang): string {
    return events_public_djs_page_url($targetLang);
}

/**
 * Nyilvános címke-oldal URL.
 */
function events_public_tag_page_url(int $tagId, string $lang): string {
    return events_url('tag.php?' . http_build_query(['id' => $tagId, 'lang' => $lang], '', '&', PHP_QUERY_RFC3986));
}

function events_public_tag_lang_switch_url(int $tagId, string $targetLang): string {
    return events_url('tag.php?' . http_build_query(['id' => $tagId, 'lang' => $targetLang], '', '&', PHP_QUERY_RFC3986));
}

/**
 * 404 HTML (slug üres / nincs esemény) — ugyanaz a favicon és logó, mint a normál megjelenítőn.
 */
function events_public_megjelenit_not_found_html(string $lang): string {
    $T = events_public_megjelenit_strings($lang);
    $htmlLang = $lang === 'en' ? 'en' : 'hu';
    $latinfoHome = LATINFO_PUBLIC_HOME_URL;
    $eventsHome = events_public_home_page_url($lang);
    $C = events_public_common_nav_strings($lang);
    $cssUrl = events_url('assets/event_public.css');
    $logoSrc = site_url('lanueva/assets/images/logo/latinfo_black.png');
    $fav = events_public_favicon_head_markup();

    return '<!DOCTYPE html>
<html lang="' . h($htmlLang) . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    ' . events_public_robots_noindex_head_markup() . '    <meta name="theme-color" content="#6d8f63">
    <title>' . h($T['not_found_title']) . '</title>
    ' . $fav . '
    <link rel="stylesheet" href="' . h($cssUrl) . '">
</head>
<body class="event-public-page">
<div class="event-shell">
    <div class="event-shell-toolbar">
        <div class="event-shell-toolbar__leading">
            <a class="event-shell-toolbar__events-home" href="' . h($eventsHome) . '" aria-label="' . h($C['events_home_aria']) . '">' . h($C['events_home_back']) . '</a>
            <a class="event-brand-logo" href="' . h($eventsHome) . '" title="' . h($C['logo_events_home_title']) . '" aria-label="' . h($C['logo_events_home_aria']) . '">
                <img src="' . h($logoSrc) . '" alt="' . h($T['logo_alt']) . '" width="180" height="48" decoding="async">
            </a>
        </div>
    </div>
    <p class="event-not-found-msg">' . h($T['not_found_body']) . '</p>
    <p class="event-site-line event-site-line--standalone"><a href="' . h($eventsHome) . '">' . h($C['events_home_link']) . '</a><span class="event-site-line__sep" aria-hidden="true">·</span><a href="' . h($latinfoHome) . '">' . h($T['footer_home_link']) . '</a></p>
</div>
</body>
</html>';
}

/**
 * 404 HTML — ismeretlen szervező ID (organizer.php).
 */
function events_public_organizer_not_found_html(string $lang): string {
    $O = events_public_organizer_strings($lang);
    $htmlLang = $lang === 'en' ? 'en' : 'hu';
    $latinfoHome = LATINFO_PUBLIC_HOME_URL;
    $eventsHome = events_public_home_page_url($lang);
    $C = events_public_common_nav_strings($lang);
    $cssUrl = events_url('assets/event_public.css');
    $logoSrc = site_url('lanueva/assets/images/logo/latinfo_black.png');
    $fav = events_public_favicon_head_markup();

    return '<!DOCTYPE html>
<html lang="' . h($htmlLang) . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    ' . events_public_robots_noindex_head_markup() . '    <meta name="theme-color" content="#6d8f63">
    <title>' . h($O['not_found_title']) . '</title>
    ' . $fav . '
    <link rel="stylesheet" href="' . h($cssUrl) . '">
</head>
<body class="event-public-page">
<div class="event-shell">
    <div class="event-shell-toolbar">
        <div class="event-shell-toolbar__leading">
            <a class="event-shell-toolbar__events-home" href="' . h($eventsHome) . '" aria-label="' . h($C['events_home_aria']) . '">' . h($C['events_home_back']) . '</a>
            <a class="event-brand-logo" href="' . h($eventsHome) . '" title="' . h($C['logo_events_home_title']) . '" aria-label="' . h($C['logo_events_home_aria']) . '">
                <img src="' . h($logoSrc) . '" alt="' . h($O['logo_alt']) . '" width="180" height="48" decoding="async">
            </a>
        </div>
    </div>
    <p class="event-not-found-msg">' . h($O['not_found_body']) . '</p>
    <p class="event-site-line event-site-line--standalone"><a href="' . h($eventsHome) . '">' . h($C['events_home_link']) . '</a><span class="event-site-line__sep" aria-hidden="true">·</span><a href="' . h($latinfoHome) . '">' . h($O['footer_home_link']) . '</a></p>
</div>
</body>
</html>';
}

/**
 * 404 HTML — ismeretlen címke ID (tag.php).
 */
function events_public_tag_not_found_html(string $lang): string {
    $G = events_public_tag_strings($lang);
    $htmlLang = $lang === 'en' ? 'en' : 'hu';
    $latinfoHome = LATINFO_PUBLIC_HOME_URL;
    $eventsHome = events_public_home_page_url($lang);
    $C = events_public_common_nav_strings($lang);
    $cssUrl = events_url('assets/event_public.css');
    $logoSrc = site_url('lanueva/assets/images/logo/latinfo_black.png');
    $fav = events_public_favicon_head_markup();

    return '<!DOCTYPE html>
<html lang="' . h($htmlLang) . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    ' . events_public_robots_noindex_head_markup() . '    <meta name="theme-color" content="#6d8f63">
    <title>' . h($G['not_found_title']) . '</title>
    ' . $fav . '
    <link rel="stylesheet" href="' . h($cssUrl) . '">
</head>
<body class="event-public-page">
<div class="event-shell">
    <div class="event-shell-toolbar">
        <div class="event-shell-toolbar__leading">
            <a class="event-shell-toolbar__events-home" href="' . h($eventsHome) . '" aria-label="' . h($C['events_home_aria']) . '">' . h($C['events_home_back']) . '</a>
            <a class="event-brand-logo" href="' . h($eventsHome) . '" title="' . h($C['logo_events_home_title']) . '" aria-label="' . h($C['logo_events_home_aria']) . '">
                <img src="' . h($logoSrc) . '" alt="' . h($G['logo_alt']) . '" width="180" height="48" decoding="async">
            </a>
        </div>
    </div>
    <p class="event-not-found-msg">' . h($G['not_found_body']) . '</p>
    <p class="event-site-line event-site-line--standalone"><a href="' . h($eventsHome) . '">' . h($C['events_home_link']) . '</a><span class="event-site-line__sep" aria-hidden="true">·</span><a href="' . h($latinfoHome) . '">' . h($G['footer_home_link']) . '</a></p>
</div>
</body>
</html>';
}
