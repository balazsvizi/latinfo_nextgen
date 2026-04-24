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
        'cta_external' => 'További információ vagy jegy',
        'not_found_title' => 'Nincs ilyen esemény',
        'not_found_body' => 'Nincs ilyen esemény.',
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
        'cta_external' => 'More information or tickets',
        'not_found_title' => 'Event not found',
        'not_found_body' => 'There is no event with this link.',
    ];

    return $lang === 'en' ? $en : $hu;
}

/**
 * @return list<string>
 */
function events_public_megjelenit_date_lines(bool $allday, int|false $tsStart, int|false $tsEnd, string $lang): array {
    if (!$tsStart) {
        return [];
    }
    $huMonths = [
        1 => 'január', 2 => 'február', 3 => 'március', 4 => 'április', 5 => 'május', 6 => 'június',
        7 => 'július', 8 => 'augusztus', 9 => 'szeptember', 10 => 'október', 11 => 'november', 12 => 'december',
    ];
    $enMonths = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];
    $fmtDay = static function (int $ts) use ($lang, $huMonths, $enMonths): string {
        if ($lang === 'en') {
            $m = $enMonths[(int) date('n', $ts)];

            return $m . ' ' . (int) date('j', $ts) . ', ' . (int) date('Y', $ts);
        }
        $m = $huMonths[(int) date('n', $ts)];

        return (int) date('Y', $ts) . '. ' . $m . ' ' . (int) date('j', $ts) . '.';
    };

    $lines = [];
    if ($tsEnd && date('Y-m-d', $tsStart) === date('Y-m-d', $tsEnd)) {
        $lines[] = $fmtDay($tsStart);
        if (!$allday) {
            $lines[] = date('H:i', $tsStart) . ' – ' . date('H:i', $tsEnd);
        }
    } elseif ($tsEnd) {
        $lines[] = $fmtDay($tsStart) . ($allday ? '' : ' ' . date('H:i', $tsStart));
        $lines[] = '– ' . $fmtDay($tsEnd) . ($allday ? '' : ' ' . date('H:i', $tsEnd));
    } else {
        $line = $fmtDay($tsStart);
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
