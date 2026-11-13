<?php
declare(strict_types=1);

require_once __DIR__ . '/dance_schools.php';

/**
 * Tánciskola XLSX import: elsődlegesen a „Tánciskolák” lap.
 * Új iskola státusza Import. Meglévő iskolánál csak az üres mezők töltődnek.
 */

function dance_school_import_session_key(): string
{
    return 'dance_school_xlsx_import';
}

function dance_school_import_fold(string $value): string
{
    $lower = mb_strtolower(trim($value), 'UTF-8');
    $map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o',
        'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
    ];

    return strtr($lower, $map);
}

function dance_school_import_clean_cell(string $value): string
{
    $value = str_replace("\u{00A0}", ' ', $value);
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    if (mb_strlen($value, 'UTF-8') > 1500) {
        $value = mb_substr($value, 0, 1500, 'UTF-8');
    }

    return $value;
}

function dance_school_import_header_key(string $header): string
{
    $h = dance_school_import_fold($header);
    $h = trim(preg_replace('/\s+/u', ' ', $h) ?? '');

    return match (true) {
        $h === 'nev' => 'name',
        $h === 'varos' => 'city',
        $h === 'cim / helyszin', $h === 'cim', $h === 'helyszin' => 'address',
        $h === 'salsa' => 'salsa',
        $h === 'bachata' => 'bachata',
        $h === 'kizomba' => 'kizomba',
        $h === 'urban kiz' => 'urban_kiz',
        $h === 'zouk' => 'zouk',
        str_starts_with($h, 'ar /'), $h === 'ar' => 'pricing',
        $h === 'weboldal', $h === 'web', $h === 'website' => 'website',
        $h === 'e-mail', $h === 'email' => 'email',
        $h === 'telefonszam', $h === 'telefon' => 'phone',
        $h === 'facebook' => 'facebook',
        str_starts_with($h, 'egyeb') => 'other',
        $h === 'forras' => 'source',
        str_starts_with($h, 'megbizhatosag') => 'note',
        default => '',
    };
}

/**
 * @return array{0: ?DOMDocument, 1: string}
 */
function dance_school_xlsx_dom(string $xml): array
{
    if ($xml === '') {
        return [null, 'Üres XML.'];
    }
    if (strlen($xml) > 4_000_000) {
        return [null, 'Az Excel egyik része túl nagy.'];
    }
    $dom = new DOMDocument();
    $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
    if ($loaded !== true) {
        return [null, 'Az Excel XML nem olvasható.'];
    }

    return [$dom, ''];
}

function dance_school_xlsx_entry(ZipArchive $zip, string $name): string
{
    $stat = $zip->statName($name);
    if ($stat === false) {
        return '';
    }
    $size = (int) ($stat['size'] ?? 0);
    if ($size > 4_000_000) {
        throw new RuntimeException('Az Excel egyik része túl nagy.');
    }
    $data = $zip->getFromName($name);
    if (!is_string($data)) {
        return '';
    }

    return $data;
}

/**
 * @return list<string>
 */
function dance_school_xlsx_shared_strings(ZipArchive $zip): array
{
    $xml = dance_school_xlsx_entry($zip, 'xl/sharedStrings.xml');
    if ($xml === '') {
        return [];
    }
    [$dom, $err] = dance_school_xlsx_dom($xml);
    if ($dom === null) {
        throw new RuntimeException($err);
    }
    $out = [];
    foreach ($dom->getElementsByTagName('si') as $si) {
        $text = '';
        foreach ($si->getElementsByTagName('t') as $tNode) {
            $text .= $tNode->textContent;
        }
        $out[] = $text;
    }

    return $out;
}

function dance_school_xlsx_resolve_path(string $baseDir, string $target): string
{
    $target = str_replace('\\', '/', $target);
    if (str_starts_with($target, '/')) {
        $path = ltrim($target, '/');
    } else {
        $path = trim($baseDir, '/') . '/' . $target;
    }
    $parts = [];
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($parts);
            continue;
        }
        $parts[] = $part;
    }

    return implode('/', $parts);
}

/**
 * @return array<string, string> rId => url
 */
function dance_school_xlsx_rels(ZipArchive $zip, string $relsPath): array
{
    $xml = dance_school_xlsx_entry($zip, $relsPath);
    if ($xml === '') {
        return [];
    }
    [$dom, $err] = dance_school_xlsx_dom($xml);
    if ($dom === null) {
        throw new RuntimeException($err);
    }
    $out = [];
    foreach ($dom->getElementsByTagName('Relationship') as $rel) {
        if (!$rel instanceof DOMElement) {
            continue;
        }
        $id = $rel->getAttribute('Id');
        $target = $rel->getAttribute('Target');
        if ($id === '' || $target === '') {
            continue;
        }
        $out[$id] = rawurldecode($target);
    }

    return $out;
}

/**
 * @param list<string> $shared
 */
function dance_school_xlsx_cell_value(DOMElement $cell, array $shared): string
{
    $type = $cell->getAttribute('t');
    if ($type === 'inlineStr') {
        $text = '';
        foreach ($cell->getElementsByTagName('t') as $tNode) {
            $text .= $tNode->textContent;
        }

        return dance_school_import_clean_cell($text);
    }
    $v = $cell->getElementsByTagName('v')->item(0);
    $raw = $v instanceof DOMNode ? (string) $v->textContent : '';
    if ($type === 's') {
        return dance_school_import_clean_cell((string) ($shared[(int) $raw] ?? ''));
    }
    if ($type === 'str') {
        return dance_school_import_clean_cell($raw);
    }

    return '';
}

/**
 * @param array<string, string> $rels
 * @param list<string> $shared
 * @return list<array{row:int, cells:array<string, string>, links:array<string, string>}>
 */
function dance_school_xlsx_grid(string $sheetXml, array $shared, array $rels): array
{
    [$dom, $err] = dance_school_xlsx_dom($sheetXml);
    if ($dom === null) {
        throw new RuntimeException($err);
    }
    $links = [];
    foreach ($dom->getElementsByTagName('hyperlink') as $link) {
        if (!$link instanceof DOMElement) {
            continue;
        }
        $ref = $link->getAttribute('ref');
        $rid = $link->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
        if ($ref === '' || $rid === '' || !isset($rels[$rid])) {
            continue;
        }
        $target = $rels[$rid];
        if (!preg_match('#^https?://#i', $target)) {
            continue;
        }
        if (preg_match('/^([A-Z]+)/', $ref, $m) === 1) {
            $links[$ref] = $target;
        }
    }

    $rows = [];
    foreach ($dom->getElementsByTagName('row') as $rowNode) {
        if (!$rowNode instanceof DOMElement) {
            continue;
        }
        $rowNum = (int) $rowNode->getAttribute('r');
        $cells = [];
        $rowLinks = [];
        foreach ($rowNode->getElementsByTagName('c') as $cell) {
            if (!$cell instanceof DOMElement || $cell->parentNode !== $rowNode) {
                continue;
            }
            $ref = $cell->getAttribute('r');
            if (preg_match('/^([A-Z]+)/', $ref, $m) !== 1) {
                continue;
            }
            $col = $m[1];
            $cells[$col] = dance_school_xlsx_cell_value($cell, $shared);
            if (isset($links[$ref])) {
                $rowLinks[$col] = $links[$ref];
            }
        }
        if ($cells !== []) {
            $rows[] = ['row' => $rowNum, 'cells' => $cells, 'links' => $rowLinks];
        }
        if (count($rows) > 420) {
            break;
        }
    }

    return $rows;
}

/**
 * @return list<array{name:string, path:string}>
 */
function dance_school_xlsx_sheets(ZipArchive $zip): array
{
    [$dom, $err] = dance_school_xlsx_dom(dance_school_xlsx_entry($zip, 'xl/workbook.xml'));
    if ($dom === null) {
        throw new RuntimeException($err !== '' ? $err : 'A munkafüzet nem olvasható.');
    }
    $rels = dance_school_xlsx_rels($zip, 'xl/_rels/workbook.xml.rels');
    $sheets = [];
    foreach ($dom->getElementsByTagName('sheet') as $sheet) {
        if (!$sheet instanceof DOMElement) {
            continue;
        }
        $rid = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
        $target = $rels[$rid] ?? '';
        if ($target === '') {
            continue;
        }
        $sheets[] = [
            'name' => $sheet->getAttribute('name'),
            'path' => dance_school_xlsx_resolve_path('xl', $target),
        ];
    }

    return $sheets;
}

/**
 * @param array<string, string> $cells
 * @return array<string, string> key => column
 */
function dance_school_import_header_map(array $cells): array
{
    $map = [];
    foreach ($cells as $col => $text) {
        $key = dance_school_import_header_key($text);
        if ($key !== '' && !isset($map[$key])) {
            $map[$key] = $col;
        }
    }

    return $map;
}

/**
 * @param array<string, string> $map
 */
function dance_school_import_headers_usable(array $map): bool
{
    if (!isset($map['name'], $map['website'])) {
        return false;
    }

    foreach (['salsa', 'bachata', 'kizomba', 'urban_kiz', 'zouk'] as $styleKey) {
        if (isset($map[$styleKey])) {
            return true;
        }
    }

    return false;
}

/**
 * @return array{sheet:string, records:list<array<string, mixed>>, error:string}
 */
function dance_school_import_records_from_xlsx(string $path): array
{
    $empty = ['sheet' => '', 'records' => [], 'error' => ''];
    if (!is_file($path)) {
        $empty['error'] = 'A feltöltött fájl nem található.';

        return $empty;
    }
    if (!class_exists(ZipArchive::class)) {
        $empty['error'] = 'A szerver nem tud xlsx fájlt olvasni.';

        return $empty;
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        $empty['error'] = 'A fájl nem olvasható Excel (xlsx).';

        return $empty;
    }

    try {
        $shared = dance_school_xlsx_shared_strings($zip);
        $sheets = dance_school_xlsx_sheets($zip);
        $chosen = null;
        $chosenGrid = null;
        $fallback = null;
        foreach ($sheets as $sheet) {
            $sheetXml = dance_school_xlsx_entry($zip, $sheet['path']);
            if ($sheetXml === '') {
                continue;
            }
            $base = basename($sheet['path']);
            $rels = dance_school_xlsx_rels($zip, 'xl/worksheets/_rels/' . $base . '.rels');
            $grid = dance_school_xlsx_grid($sheetXml, $shared, $rels);
            $headerRow = $grid[0] ?? null;
            if ($headerRow === null) {
                continue;
            }
            $map = dance_school_import_header_map($headerRow['cells']);
            if (!dance_school_import_headers_usable($map)) {
                continue;
            }
            $candidate = ['sheet' => $sheet['name'], 'grid' => $grid, 'map' => $map];
            if (dance_school_import_fold($sheet['name']) === 'tanciskolak') {
                $chosen = $candidate;
                break;
            }
            if ($fallback === null) {
                $fallback = $candidate;
            }
        }
        if ($chosen === null) {
            $chosen = $fallback;
        }
        if ($chosen === null) {
            $empty['error'] = 'Nem találok tánciskola-lapot. Kell név, weboldal és legalább egy stílusoszlop (Salsa, Bachata, Kizomba, Urban Kiz vagy Zouk).';

            return $empty;
        }
        $records = dance_school_import_records_from_grid($chosen['grid'], $chosen['map']);
        if ($records === []) {
            $empty['error'] = 'A lapon nincs importálható tánciskola-sor.';

            return $empty;
        }
        if (count($records) > 400) {
            $empty['error'] = 'Legfeljebb 400 tánciskola importálható egyszerre.';

            return $empty;
        }

        return ['sheet' => (string) $chosen['sheet'], 'records' => $records, 'error' => ''];
    } catch (Throwable $ex) {
        error_log('dance_school_import_records_from_xlsx: ' . $ex->getMessage());
        $empty['error'] = 'Az Excel olvasása sikertelen.';

        return $empty;
    } finally {
        $zip->close();
    }
}

/**
 * @param list<array{row:int, cells:array<string, string>, links:array<string, string>}> $grid
 * @param array<string, string> $map
 * @return list<array<string, mixed>>
 */
function dance_school_import_records_from_grid(array $grid, array $map): array
{
    $records = [];
    $headerSeen = false;
    foreach ($grid as $gridRow) {
        if (!$headerSeen) {
            $headerSeen = true;
            continue;
        }
        $cells = $gridRow['cells'];
        $links = $gridRow['links'];
        $read = static function (string $key) use ($cells, $map): string {
            $col = $map[$key] ?? '';
            if ($col === '') {
                return '';
            }

            return trim((string) ($cells[$col] ?? ''));
        };
        $name = $read('name');
        if ($name === '') {
            continue;
        }
        $websiteCol = $map['website'] ?? '';
        $websiteRaw = $read('website');
        $websiteLink = $websiteCol !== '' ? trim((string) ($links[$websiteCol] ?? '')) : '';
        $websiteUrl = dance_school_import_take_url($websiteLink !== '' ? $websiteLink : $websiteRaw);
        if ($websiteUrl === '' && $websiteRaw !== '') {
            $websiteUrl = dance_school_import_take_url($websiteRaw);
        }
        $facebookRaw = $read('facebook');
        $otherRaw = $read('other');
        $contacts = dance_school_import_contacts($facebookRaw, $otherRaw);
        $pricing = $read('pricing');
        $cityRaw = $read('city');
        $address = $read('address');
        $styles = [];
        $unknownStyles = [];
        foreach ([
            'salsa' => 'Salsa',
            'bachata' => 'Bachata',
            'kizomba' => 'Kizomba',
            'urban_kiz' => 'Urban Kiz',
            'zouk' => 'Zouk',
        ] as $styleKey => $label) {
            if (!isset($map[$styleKey])) {
                continue;
            }
            $state = dance_school_import_style_state($read($styleKey));
            if ($state === 'yes') {
                $styles[] = $styleKey;
            } elseif ($state === 'unknown' && trim($read($styleKey)) !== '') {
                $unknownStyles[] = $label;
            }
        }
        $warnings = [];
        $email = $read('email');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $warnings[] = 'Érvénytelen e-mail kihagyva.';
            $email = '';
        }
        $phone = $read('phone');
        if ($phone !== '' && preg_match('/^[0-9+().\/\s-]{6,64}$/', $phone) !== 1) {
            $warnings[] = 'A telefonszám formátuma miatt csak a jegyzetbe kerül.';
            $contacts['note'] = trim($contacts['note'] . ($contacts['note'] !== '' ? ' ' : '') . 'Telefon: ' . $phone);
            $phone = '';
        }
        if (mb_strlen($phone, 'UTF-8') > 64) {
            $phone = mb_substr($phone, 0, 64, 'UTF-8');
        }
        if ($websiteRaw !== '' && $websiteUrl === '') {
            $warnings[] = 'A weboldal nem használható link, kihagyva.';
        }
        $records[] = [
            'line' => (int) $gridRow['row'],
            'name' => mb_substr($name, 0, 255, 'UTF-8'),
            'city_raw' => $cityRaw,
            'city' => dance_school_import_primary_city($cityRaw),
            'address' => $address,
            'postal_code' => dance_school_import_postal($address),
            'pricing_info' => mb_substr($pricing, 0, 4000, 'UTF-8'),
            'website_url' => $websiteUrl,
            'website_host' => dance_school_import_host($websiteUrl),
            'email' => mb_substr($email, 0, 255, 'UTF-8'),
            'phone' => $phone,
            'facebook_url' => $contacts['facebook_url'],
            'instagram_url' => $contacts['instagram_url'],
            'tiktok_url' => $contacts['tiktok_url'],
            'youtube_url' => $contacts['youtube_url'],
            'facebook_raw' => $facebookRaw,
            'other_note' => $contacts['note'],
            'source' => $read('source'),
            'note' => $read('note'),
            'styles' => $styles,
            'unknown_styles' => $unknownStyles,
            'trial_lesson_info' => dance_school_import_trial_from_pricing($pricing),
            'combined' => preg_match('/\s\/\s/u', $name) === 1,
            'warnings' => $warnings,
        ];
    }

    return $records;
}

function dance_school_import_style_state(string $raw): string
{
    $v = dance_school_import_fold($raw);
    if (in_array($v, ['igen', 'yes', 'y', '1', 'x', 'true'], true)) {
        return 'yes';
    }
    if (in_array($v, ['nem', 'no', 'n', '0', 'false'], true)) {
        return 'no';
    }

    return 'unknown';
}

function dance_school_import_primary_city(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    $raw = preg_replace('/\s*\([^)]*\)/u', '', $raw) ?? $raw;
    $raw = preg_replace('/\s*\+.*$/u', '', $raw) ?? $raw;
    $parts = preg_split('/\s*[,;&]\s*|\s+és\s+|\s+&\s+/u', $raw) ?: [];
    $city = trim((string) ($parts[0] ?? ''));
    $city = preg_replace('/\s+(stb\.?|varmegye).*/iu', '', $city) ?? $city;
    $city = trim($city, " \t.-");
    if (mb_strlen($city, 'UTF-8') > 128) {
        $city = mb_substr($city, 0, 128, 'UTF-8');
    }

    return $city;
}

function dance_school_import_postal(string $address): string
{
    if (preg_match('/^\s*(\d{4})\b/u', $address, $m) === 1) {
        return $m[1];
    }

    return '';
}

function dance_school_import_take_url(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    [$url, $err] = events_normalize_safe_url($raw, false);
    if ($err !== null || $url === null || str_starts_with($url, '/')) {
        return '';
    }

    return $url;
}

function dance_school_import_host(string $url): string
{
    if ($url === '') {
        return '';
    }
    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return '';
    }
    $host = strtolower($host);
    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }

    return $host;
}

function dance_school_import_trial_from_pricing(string $pricing): string
{
    if (preg_match('/ingyenes\s+pr[oó]ba|első\s+óra\s+ingyenes|elso\s+ora\s+ingyenes/iu', $pricing) === 1) {
        return 'Első óra vagy próbaóra ingyenes.';
    }

    return '';
}

/**
 * @return array{facebook_url:string, instagram_url:string, tiktok_url:string, youtube_url:string, note:string}
 */
function dance_school_import_contacts(string $facebookRaw, string $otherRaw): array
{
    $out = [
        'facebook_url' => '',
        'instagram_url' => '',
        'tiktok_url' => '',
        'youtube_url' => '',
        'note' => '',
    ];
    $notes = [];
    $assign = static function (string $url) use (&$out): bool {
        $host = dance_school_import_host($url);
        if ($host === '') {
            return false;
        }
        if (str_contains($host, 'facebook.com') || str_contains($host, 'fb.com')) {
            if ($out['facebook_url'] === '') {
                $out['facebook_url'] = $url;
            }

            return true;
        }
        if (str_contains($host, 'instagram.com')) {
            if ($out['instagram_url'] === '') {
                $out['instagram_url'] = $url;
            }

            return true;
        }
        if (str_contains($host, 'tiktok.com')) {
            if ($out['tiktok_url'] === '') {
                $out['tiktok_url'] = $url;
            }

            return true;
        }
        if (str_contains($host, 'youtube.com') || $host === 'youtu.be') {
            if ($out['youtube_url'] === '') {
                $out['youtube_url'] = $url;
            }

            return true;
        }

        return false;
    };

    $facebookUrl = dance_school_import_take_url($facebookRaw);
    if ($facebookUrl !== '') {
        if (!$assign($facebookUrl)) {
            $notes[] = 'Facebook: ' . $facebookRaw;
        }
    } elseif ($facebookRaw !== '') {
        $notes[] = 'Facebook: ' . $facebookRaw;
    }

    $otherUrl = dance_school_import_take_url($otherRaw);
    if ($otherUrl !== '') {
        if (!$assign($otherUrl)) {
            $notes[] = 'Egyéb: ' . $otherRaw;
        }
    } elseif ($otherRaw !== '') {
        if (preg_match('/tiktok\s*:\s*@?([A-Za-z0-9._]{2,64})/iu', $otherRaw, $m) === 1) {
            $tiktok = dance_school_import_take_url('https://www.tiktok.com/@' . $m[1]);
            if ($tiktok !== '') {
                $out['tiktok_url'] = $tiktok;
            } else {
                $notes[] = 'Egyéb: ' . $otherRaw;
            }
        } else {
            $notes[] = 'Egyéb: ' . $otherRaw;
        }
    }
    $out['note'] = implode("\n", $notes);

    return $out;
}

/**
 * @return list<string>
 */
function dance_school_import_name_keys(string $name): array
{
    $pieces = [$name];
    if (preg_match_all('/\(([^)]+)\)/u', $name, $m) > 0) {
        foreach ($m[1] as $inner) {
            $inner = trim((string) $inner);
            if ($inner !== '') {
                $pieces[] = $inner;
            }
        }
        $outer = trim(preg_replace('/\([^)]*\)/u', ' ', $name) ?? '');
        if ($outer !== '') {
            $pieces[] = $outer;
        }
    }
    foreach (preg_split('/\s+\/\s+/u', $name) ?: [] as $part) {
        $part = trim((string) $part);
        if ($part !== '') {
            $pieces[] = $part;
        }
    }
    $keys = [];
    foreach ($pieces as $piece) {
        $full = dance_school_import_name_key($piece);
        if ($full === '') {
            continue;
        }
        $candidates = [$full, dance_school_import_name_core($full)];
        foreach ($candidates as $key) {
            if (strlen($key) >= 8) {
                $keys[$key] = true;
            }
        }
    }

    return array_keys($keys);
}

function dance_school_import_name_key(string $name): string
{
    $name = preg_replace('/\([^)]*\)/u', ' ', $name) ?? $name;
    $name = dance_school_import_fold($name);
    $name = preg_replace('/[^a-z0-9]+/', ' ', $name) ?? '';
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');

    return $name;
}

function dance_school_import_name_core(string $key): string
{
    $stop = [
        'tanciskola', 'tancklub', 'tancstudio', 'tancstudiok', 'mozgasstudio',
        'dance', 'studio', 'studios', 'club', 'hungary', 'project', 'group', 'akademia',
    ];
    $parts = $key === '' ? [] : explode(' ', $key);
    while (count($parts) > 1) {
        $last = $parts[count($parts) - 1];
        if (!in_array($last, $stop, true)) {
            break;
        }
        $candidate = implode(' ', array_slice($parts, 0, -1));
        if (strlen($candidate) < 8) {
            break;
        }
        array_pop($parts);
    }

    return implode(' ', $parts);
}

function dance_school_import_venue_name(string $school, string $address, string $city): string
{
    if (preg_match_all('/\(([^)]+)\)/u', $address, $m) > 0) {
        foreach ($m[1] as $inner) {
            $inner = trim((string) (preg_split('/\s*\/\s*/u', (string) $inner)[0] ?? ''));
            if ($inner === '' || preg_match('/kerület|ker\.|em\.|emelet|helyszín/iu', $inner) === 1) {
                continue;
            }
            $len = mb_strlen($inner, 'UTF-8');
            if ($len >= 3 && $len <= 80) {
                return $inner;
            }
        }
    }
    $first = trim((string) (preg_split('/\s*(?:\+|\/|;)\s*/u', $address)[0] ?? ''));
    $first = trim(preg_replace('/\([^)]*\)/u', '', $first) ?? '');
    $first = preg_replace('/^\d{4}\s*/u', '', $first) ?? $first;
    $first = preg_replace('/^(bp\.|budapest),?\s*/iu', '', $first) ?? $first;
    if ($city !== '') {
        $prefix = mb_strtolower($city, 'UTF-8');
        if (str_starts_with(mb_strtolower($first, 'UTF-8'), $prefix)) {
            $first = trim(mb_substr($first, mb_strlen($city, 'UTF-8'), null, 'UTF-8'), " \t,.-");
        }
    }
    $first = trim($first, " \t,.-");
    if (
        $first !== ''
        && mb_strtolower($first, 'UTF-8') !== mb_strtolower($city, 'UTF-8')
        && preg_match('/\b(u\.|utca|út|útja|körút|tér|köz|sor)\b/iu', $first) === 1
        && mb_strlen($first, 'UTF-8') <= 120
    ) {
        return $first;
    }
    $label = $school . ' – órahelyszín';
    if (mb_strlen($label, 'UTF-8') > 180) {
        $label = mb_substr($school, 0, 160, 'UTF-8') . ' – órahelyszín';
    }

    return $label;
}

/**
 * @param array<string, mixed> $rec
 */
function dance_school_import_notes_block(array $rec): string
{
    $lines = ['XLSX-import:'];
    $source = trim((string) ($rec['source'] ?? ''));
    $note = trim((string) ($rec['note'] ?? ''));
    $cityRaw = trim((string) ($rec['city_raw'] ?? ''));
    $city = trim((string) ($rec['city'] ?? ''));
    $address = trim((string) ($rec['address'] ?? ''));
    $other = trim((string) ($rec['other_note'] ?? ''));
    $unknown = $rec['unknown_styles'] ?? [];
    if ($source !== '') {
        $lines[] = 'Forrás: ' . $source;
    }
    if ($note !== '') {
        $lines[] = 'Megjegyzés: ' . $note;
    }
    if ($cityRaw !== '' && $cityRaw !== $city) {
        $lines[] = 'Város (teljes): ' . $cityRaw;
    }
    if ($address !== '') {
        $lines[] = 'Cím: ' . $address;
    }
    if ($other !== '') {
        $lines[] = $other;
    }
    if (is_array($unknown) && $unknown !== []) {
        $lines[] = 'Nem ellenőrzött stílus: ' . implode(', ', array_map('strval', $unknown));
    }
    if (count($lines) === 1) {
        return '';
    }
    $block = implode("\n", $lines);
    if (mb_strlen($block, 'UTF-8') > 4000) {
        $block = mb_substr($block, 0, 4000, 'UTF-8');
    }

    return $block;
}

/**
 * @return array<string, array{style_id:?int, style_label:string}>
 */
function dance_school_import_load_styles(PDO $db): array
{
    $byName = [];
    try {
        $rows = $db->query('SELECT `id`, `name` FROM `events_styles`')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $byName[dance_school_import_fold((string) ($row['name'] ?? ''))] = (int) $row['id'];
        }
    } catch (Throwable $ex) {
        error_log('dance_school_import_load_styles: ' . $ex->getMessage());
    }
    $spec = [
        'salsa' => ['label' => 'Salsa', 'aliases' => ['salsa', 'salsa (kubai/cuban)', 'kubai salsa', 'salsa cubana']],
        'bachata' => ['label' => 'Bachata', 'aliases' => ['bachata']],
        'kizomba' => ['label' => 'Kizomba', 'aliases' => ['kizomba']],
        'urban_kiz' => ['label' => 'Urban Kiz', 'aliases' => ['urban kiz', 'urban kizomba']],
        'zouk' => ['label' => 'Zouk', 'aliases' => ['zouk', 'brazil zouk', 'brazilian zouk']],
    ];
    $out = [];
    foreach ($spec as $key => $meta) {
        $styleId = null;
        foreach ($meta['aliases'] as $alias) {
            $folded = dance_school_import_fold($alias);
            if (isset($byName[$folded])) {
                $styleId = $byName[$folded];
                break;
            }
        }
        $out[$key] = ['style_id' => $styleId, 'style_label' => $meta['label']];
    }

    return $out;
}

/**
 * @return array{schools:array<int, array<string, mixed>>, by_host:array<string, list<int>>, by_name:array<string, list<int>>, styles:array<string, array{style_id:?int, style_label:string}>, next_temp:int}
 */
function dance_school_import_index(PDO $db): array
{
    $index = [
        'schools' => [],
        'by_host' => [],
        'by_name' => [],
        'styles' => dance_school_import_load_styles($db),
        'next_temp' => -1,
    ];
    if (!dance_schools_tables_ready($db)) {
        return $index;
    }
    $st = $db->query('
        SELECT `id`, `name`, `city`, `website_url`, `email`, `phone`, `facebook_url`, `instagram_url`,
               `tiktok_url`, `youtube_url`, `pricing_info`, `trial_lesson_info`, `admin_notes`, `languages`
        FROM `dance_schools`
    ');
    $schools = $st !== false ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    $activeLink = [];
    try {
        $linkSt = $db->query('SELECT `id`, `school_id`, `is_active` FROM `dance_school_venues` ORDER BY `sort_order` ASC, `id` ASC');
        foreach ($linkSt !== false ? ($linkSt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $link) {
            $schoolId = (int) ($link['school_id'] ?? 0);
            $linkId = (int) ($link['id'] ?? 0);
            if ($schoolId <= 0 || $linkId <= 0) {
                continue;
            }
            $isActive = !empty($link['is_active']);
            if (!isset($activeLink[$schoolId])) {
                $activeLink[$schoolId] = ['id' => $linkId, 'active' => $isActive];
                continue;
            }
            if ($isActive && empty($activeLink[$schoolId]['active'])) {
                $activeLink[$schoolId] = ['id' => $linkId, 'active' => true];
            }
        }
    } catch (Throwable $ex) {
        error_log('dance_school_import_index links: ' . $ex->getMessage());
    }
    $styleIds = [];
    $styleLabels = [];
    try {
        $offSt = $db->query('
            SELECT sv.`school_id`, o.`style_id`, o.`style_label`
            FROM `dance_school_offerings` o
            INNER JOIN `dance_school_venues` sv ON sv.`id` = o.`school_venue_id`
        ');
        foreach ($offSt !== false ? ($offSt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $off) {
            $schoolId = (int) ($off['school_id'] ?? 0);
            $styleId = (int) ($off['style_id'] ?? 0);
            $label = dance_school_import_fold((string) ($off['style_label'] ?? ''));
            if ($schoolId <= 0) {
                continue;
            }
            if ($styleId > 0) {
                $styleIds[$schoolId][$styleId] = true;
            }
            if ($label !== '') {
                $styleLabels[$schoolId][$label] = true;
            }
        }
    } catch (Throwable $ex) {
        error_log('dance_school_import_index offerings: ' . $ex->getMessage());
    }

    foreach ($schools as $school) {
        $id = (int) ($school['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        dance_school_import_index_add($index, [
            'id' => $id,
            'name' => (string) ($school['name'] ?? ''),
            'city' => trim((string) ($school['city'] ?? '')),
            'website_url' => trim((string) ($school['website_url'] ?? '')),
            'email' => trim((string) ($school['email'] ?? '')),
            'phone' => trim((string) ($school['phone'] ?? '')),
            'facebook_url' => trim((string) ($school['facebook_url'] ?? '')),
            'instagram_url' => trim((string) ($school['instagram_url'] ?? '')),
            'tiktok_url' => trim((string) ($school['tiktok_url'] ?? '')),
            'youtube_url' => trim((string) ($school['youtube_url'] ?? '')),
            'pricing_info' => trim((string) ($school['pricing_info'] ?? '')),
            'trial_lesson_info' => trim((string) ($school['trial_lesson_info'] ?? '')),
            'admin_notes' => trim((string) ($school['admin_notes'] ?? '')),
            'languages' => trim((string) ($school['languages'] ?? '')),
            'venue_link_id' => (int) ($activeLink[$id]['id'] ?? 0),
            'style_ids' => array_keys($styleIds[$id] ?? []),
            'style_labels' => array_keys($styleLabels[$id] ?? []),
        ]);
    }

    return $index;
}

/**
 * @param array{schools:array<int, array<string, mixed>>, by_host:array<string, list<int>>, by_name:array<string, list<int>>, styles:array<string, array{style_id:?int, style_label:string}>, next_temp:int} $index
 * @param array<string, mixed> $school
 */
function dance_school_import_index_add(array &$index, array $school): void
{
    $id = (int) ($school['id'] ?? 0);
    if ($id === 0) {
        return;
    }
    $index['schools'][$id] = $school;
    $host = dance_school_import_host((string) ($school['website_url'] ?? ''));
    if ($host !== '') {
        $index['by_host'][$host] ??= [];
        if (!in_array($id, $index['by_host'][$host], true)) {
            $index['by_host'][$host][] = $id;
        }
    }
    foreach (dance_school_import_name_keys((string) ($school['name'] ?? '')) as $key) {
        $index['by_name'][$key] ??= [];
        if (!in_array($id, $index['by_name'][$key], true)) {
            $index['by_name'][$key][] = $id;
        }
    }
}

/**
 * @param array<string, mixed> $rec
 * @param array{schools:array<int, array<string, mixed>>, by_host:array<string, list<int>>, by_name:array<string, list<int>>, styles:array<string, array{style_id:?int, style_label:string}>, next_temp:int} $index
 * @return array<string, mixed>
 */
function dance_school_import_decide(array $index, array $rec): array
{
    $name = (string) ($rec['name'] ?? '');
    $base = [
        'line' => (int) ($rec['line'] ?? 0),
        'name' => $name,
        'action' => 'invalid',
        'school_id' => 0,
        'matched_name' => '',
        'reason' => '',
        'changes' => [],
        'updates' => [],
        'append_notes' => '',
        'create_venue' => false,
        'venue_name' => '',
        'venue_city' => '',
        'venue_address' => '',
        'venue_postal' => '',
        'offerings' => [],
        'warnings' => is_array($rec['warnings'] ?? null) ? array_values($rec['warnings']) : [],
        'record' => $rec,
    ];
    if ($name === '') {
        $base['reason'] = 'Hiányzik a név.';

        return $base;
    }
    if (!empty($rec['combined'])) {
        $base['action'] = 'skip_combined';
        $base['reason'] = 'Összevont név (perjel). Nem jön létre és nem módosul automatikusan.';

        return $base;
    }

    $host = (string) ($rec['website_host'] ?? '');
    $hostIds = $host !== '' ? array_values(array_unique($index['by_host'][$host] ?? [])) : [];
    $nameIds = [];
    foreach (dance_school_import_name_keys($name) as $key) {
        foreach ($index['by_name'][$key] ?? [] as $id) {
            $nameIds[$id] = true;
        }
    }
    $nameIds = array_map('intval', array_keys($nameIds));

    $schoolId = 0;
    $uncertain = '';
    if ($hostIds !== [] && $nameIds !== []) {
        $both = array_values(array_intersect($hostIds, $nameIds));
        if (count($both) === 1) {
            $schoolId = (int) $both[0];
        } elseif (count($both) > 1) {
            $uncertain = 'A weboldal és a név is több iskolára illik.';
        } elseif (count($hostIds) === 1 && !in_array((int) $hostIds[0], $nameIds, true)) {
            $uncertain = 'A weboldal és a név másik iskolára mutat.';
        } elseif (count($hostIds) === 1) {
            $schoolId = (int) $hostIds[0];
        } else {
            $uncertain = 'A weboldal több iskolánál szerepel.';
        }
    } elseif (count($hostIds) === 1) {
        $schoolId = (int) $hostIds[0];
    } elseif (count($hostIds) > 1) {
        $uncertain = 'A weboldal domainje több iskolánál szerepel.';
    } elseif (count($nameIds) === 1) {
        $schoolId = (int) $nameIds[0];
    } elseif (count($nameIds) > 1) {
        $uncertain = 'A név több iskolára illik.';
    }

    if ($uncertain !== '') {
        $base['action'] = 'skip_uncertain';
        $base['reason'] = $uncertain;

        return $base;
    }

    $offerings = dance_school_import_offerings_for($index, $rec, $schoolId);
    $notes = dance_school_import_notes_block($rec);
    $address = trim((string) ($rec['address'] ?? ''));
    $venueName = dance_school_import_venue_name($name, $address, (string) ($rec['city'] ?? ''));

    if ($schoolId === 0) {
        $changes = ['Státusz: Import'];
        foreach ([
            'city' => 'Város',
            'website_url' => 'Weboldal',
            'email' => 'E-mail',
            'phone' => 'Telefon',
            'facebook_url' => 'Facebook',
            'instagram_url' => 'Instagram',
            'tiktok_url' => 'TikTok',
            'youtube_url' => 'YouTube',
            'pricing_info' => 'Ár / infó',
            'trial_lesson_info' => 'Próbaóra',
        ] as $field => $label) {
            if (trim((string) ($rec[$field] ?? '')) !== '') {
                $changes[] = $label;
            }
        }
        if ($notes !== '') {
            $changes[] = 'Admin jegyzet';
        }
        $createVenue = $address !== '' || $offerings !== [];
        if ($createVenue) {
            $changes[] = 'Helyszín: ' . $venueName;
        }
        if ($offerings !== []) {
            $changes[] = 'Kínálat: ' . dance_school_import_offering_labels($offerings);
        }
        $base['action'] = 'create';
        $base['reason'] = 'Új iskola, státusz: Import (nem publikus).';
        $base['changes'] = $changes;
        $base['append_notes'] = $notes;
        $base['create_venue'] = $createVenue;
        $base['venue_name'] = $venueName;
        $base['venue_city'] = (string) ($rec['city'] ?? '');
        $base['venue_address'] = $address;
        $base['venue_postal'] = (string) ($rec['postal_code'] ?? '');
        $base['offerings'] = $offerings;

        return $base;
    }

    $school = $index['schools'][$schoolId] ?? null;
    if (!is_array($school)) {
        $base['action'] = 'skip_uncertain';
        $base['reason'] = 'Az egyező iskola nem olvasható.';

        return $base;
    }
    $updates = [];
    $changes = [];
    $labels = [
        'city' => 'Város',
        'website_url' => 'Weboldal',
        'email' => 'E-mail',
        'phone' => 'Telefon',
        'facebook_url' => 'Facebook',
        'instagram_url' => 'Instagram',
        'tiktok_url' => 'TikTok',
        'youtube_url' => 'YouTube',
        'pricing_info' => 'Ár / infó',
        'trial_lesson_info' => 'Próbaóra',
        'languages' => 'Nyelv',
    ];
    $incoming = [
        'city' => (string) ($rec['city'] ?? ''),
        'website_url' => (string) ($rec['website_url'] ?? ''),
        'email' => (string) ($rec['email'] ?? ''),
        'phone' => (string) ($rec['phone'] ?? ''),
        'facebook_url' => (string) ($rec['facebook_url'] ?? ''),
        'instagram_url' => (string) ($rec['instagram_url'] ?? ''),
        'tiktok_url' => (string) ($rec['tiktok_url'] ?? ''),
        'youtube_url' => (string) ($rec['youtube_url'] ?? ''),
        'pricing_info' => (string) ($rec['pricing_info'] ?? ''),
        'trial_lesson_info' => (string) ($rec['trial_lesson_info'] ?? ''),
        'languages' => 'HU',
    ];
    foreach ($incoming as $field => $value) {
        if ($value === '') {
            continue;
        }
        if (trim((string) ($school[$field] ?? '')) === '') {
            $updates[$field] = $value;
            $changes[] = $labels[$field];
        }
    }
    $appendNotes = '';
    if ($notes !== '' && !str_contains((string) ($school['admin_notes'] ?? ''), $notes)) {
        $appendNotes = $notes;
        $changes[] = 'Admin jegyzet';
    }
    $linkId = (int) ($school['venue_link_id'] ?? 0);
    $createVenue = $linkId <= 0 && ($address !== '' || $offerings !== []);
    if ($createVenue) {
        $changes[] = 'Helyszín: ' . $venueName;
    }
    if ($offerings !== []) {
        $changes[] = 'Kínálat: ' . dance_school_import_offering_labels($offerings);
    }
    $base['school_id'] = $schoolId;
    $base['matched_name'] = (string) ($school['name'] ?? '');
    $base['updates'] = $updates;
    $base['append_notes'] = $appendNotes;
    $base['create_venue'] = $createVenue;
    $base['venue_name'] = $createVenue ? $venueName : '';
    $base['venue_city'] = (string) ($rec['city'] ?? '');
    $base['venue_address'] = $address;
    $base['venue_postal'] = (string) ($rec['postal_code'] ?? '');
    $base['offerings'] = $offerings;
    $base['changes'] = $changes;
    if ($changes === []) {
        $base['action'] = 'unchanged';
        $base['reason'] = 'Már megvan, üres mező nincs.';

        return $base;
    }
    $base['action'] = 'fill';
    $base['reason'] = 'Meglévő iskola, csak az üres mezők töltődnek.';

    return $base;
}

/**
 * @param array{schools:array<int, array<string, mixed>>, by_host:array<string, list<int>>, by_name:array<string, list<int>>, styles:array<string, array{style_id:?int, style_label:string}>, next_temp:int} $index
 * @param array<string, mixed> $rec
 * @return list<array{style_id:?int, style_label:string}>
 */
function dance_school_import_offerings_for(array $index, array $rec, int $schoolId): array
{
    $haveIds = [];
    $haveLabels = [];
    if ($schoolId !== 0 && isset($index['schools'][$schoolId])) {
        foreach ($index['schools'][$schoolId]['style_ids'] ?? [] as $styleId) {
            $haveIds[(int) $styleId] = true;
        }
        foreach ($index['schools'][$schoolId]['style_labels'] ?? [] as $label) {
            $haveLabels[(string) $label] = true;
        }
    }
    $out = [];
    $styles = $rec['styles'] ?? [];
    if (!is_array($styles)) {
        return [];
    }
    foreach ($styles as $styleKey) {
        $styleKey = (string) $styleKey;
        $meta = $index['styles'][$styleKey] ?? null;
        if (!is_array($meta)) {
            continue;
        }
        $styleId = $meta['style_id'] ?? null;
        $styleId = is_int($styleId) && $styleId > 0 ? $styleId : null;
        $label = (string) ($meta['style_label'] ?? '');
        $folded = dance_school_import_fold($label);
        if ($styleId !== null && isset($haveIds[$styleId])) {
            continue;
        }
        if ($folded !== '' && isset($haveLabels[$folded])) {
            continue;
        }
        $out[] = ['style_id' => $styleId, 'style_label' => $label];
        if ($styleId !== null) {
            $haveIds[$styleId] = true;
        }
        if ($folded !== '') {
            $haveLabels[$folded] = true;
        }
    }

    return $out;
}

/**
 * @param list<array{style_id:?int, style_label:string}> $offerings
 */
function dance_school_import_offering_labels(array $offerings): string
{
    $labels = [];
    foreach ($offerings as $offering) {
        $labels[] = (string) ($offering['style_label'] ?? '');
    }

    return implode(', ', array_filter($labels, static fn (string $label): bool => $label !== ''));
}

/**
 * @param array{schools:array<int, array<string, mixed>>, by_host:array<string, list<int>>, by_name:array<string, list<int>>, styles:array<string, array{style_id:?int, style_label:string}>, next_temp:int} $index
 * @param array<string, mixed> $decision
 */
function dance_school_import_index_remember(array &$index, array $decision): void
{
    $action = (string) ($decision['action'] ?? '');
    if ($action !== 'create' && $action !== 'fill') {
        return;
    }
    $rec = is_array($decision['record'] ?? null) ? $decision['record'] : [];
    if ($action === 'create') {
        $id = (int) ($decision['school_id'] ?? 0);
        if ($id <= 0) {
            $id = (int) $index['next_temp'];
            $index['next_temp']--;
        }
        $school = [
            'id' => $id,
            'name' => (string) ($rec['name'] ?? ''),
            'city' => (string) ($rec['city'] ?? ''),
            'website_url' => (string) ($rec['website_url'] ?? ''),
            'email' => (string) ($rec['email'] ?? ''),
            'phone' => (string) ($rec['phone'] ?? ''),
            'facebook_url' => (string) ($rec['facebook_url'] ?? ''),
            'instagram_url' => (string) ($rec['instagram_url'] ?? ''),
            'tiktok_url' => (string) ($rec['tiktok_url'] ?? ''),
            'youtube_url' => (string) ($rec['youtube_url'] ?? ''),
            'pricing_info' => (string) ($rec['pricing_info'] ?? ''),
            'trial_lesson_info' => (string) ($rec['trial_lesson_info'] ?? ''),
            'admin_notes' => (string) ($decision['append_notes'] ?? ''),
            'languages' => 'HU',
            'venue_link_id' => !empty($decision['create_venue']) ? $id : 0,
            'style_ids' => [],
            'style_labels' => [],
        ];
        foreach ($decision['offerings'] ?? [] as $offering) {
            if (!is_array($offering)) {
                continue;
            }
            $styleId = (int) ($offering['style_id'] ?? 0);
            $label = dance_school_import_fold((string) ($offering['style_label'] ?? ''));
            if ($styleId > 0) {
                $school['style_ids'][] = $styleId;
            }
            if ($label !== '') {
                $school['style_labels'][] = $label;
            }
        }
        dance_school_import_index_add($index, $school);

        return;
    }

    $id = (int) ($decision['school_id'] ?? 0);
    if (!isset($index['schools'][$id])) {
        return;
    }
    foreach ($decision['updates'] ?? [] as $field => $value) {
        if (is_string($field)) {
            $index['schools'][$id][$field] = (string) $value;
        }
    }
    $append = trim((string) ($decision['append_notes'] ?? ''));
    if ($append !== '') {
        $current = trim((string) ($index['schools'][$id]['admin_notes'] ?? ''));
        $index['schools'][$id]['admin_notes'] = $current === '' ? $append : $current . "\n" . $append;
    }
    if (!empty($decision['create_venue'])) {
        $index['schools'][$id]['venue_link_id'] = $id;
    }
    foreach ($decision['offerings'] ?? [] as $offering) {
        if (!is_array($offering)) {
            continue;
        }
        $styleId = (int) ($offering['style_id'] ?? 0);
        $label = dance_school_import_fold((string) ($offering['style_label'] ?? ''));
        if ($styleId > 0 && !in_array($styleId, $index['schools'][$id]['style_ids'], true)) {
            $index['schools'][$id]['style_ids'][] = $styleId;
        }
        if ($label !== '' && !in_array($label, $index['schools'][$id]['style_labels'], true)) {
            $index['schools'][$id]['style_labels'][] = $label;
        }
    }
    $host = dance_school_import_host((string) ($index['schools'][$id]['website_url'] ?? ''));
    if ($host !== '') {
        $index['by_host'][$host] ??= [];
        if (!in_array($id, $index['by_host'][$host], true)) {
            $index['by_host'][$host][] = $id;
        }
    }
}

/**
 * @param list<array<string, mixed>> $records
 * @return array{rows:list<array<string, mixed>>, counts:array<string, int>}
 */
function dance_school_import_plan(PDO $db, array $records): array
{
    $index = dance_school_import_index($db);
    $rows = [];
    $counts = [
        'create' => 0,
        'fill' => 0,
        'unchanged' => 0,
        'skip_combined' => 0,
        'skip_uncertain' => 0,
        'invalid' => 0,
    ];
    foreach ($records as $rec) {
        if (!is_array($rec)) {
            continue;
        }
        $decision = dance_school_import_decide($index, $rec);
        dance_school_import_index_remember($index, $decision);
        $action = (string) ($decision['action'] ?? 'invalid');
        if (!isset($counts[$action])) {
            $action = 'invalid';
            $decision['action'] = $action;
        }
        $counts[$action]++;
        $rows[] = $decision;
    }

    return ['rows' => $rows, 'counts' => $counts];
}

/**
 * @param list<array<string, mixed>> $records
 * @return array{ok:bool, created:int, filled:int, unchanged:int, skipped:int, errors:int, messages:list<string>}
 */
function dance_school_import_apply(PDO $db, array $records): array
{
    if (!dance_schools_ensure_schema($db)) {
        return [
            'ok' => false,
            'created' => 0,
            'filled' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'errors' => 1,
            'messages' => ['A tánciskola táblák nem elérhetők.'],
        ];
    }
    $index = dance_school_import_index($db);
    $created = 0;
    $filled = 0;
    $unchanged = 0;
    $skipped = 0;
    $errors = 0;
    $messages = [];
    foreach ($records as $rec) {
        if (!is_array($rec)) {
            continue;
        }
        $decision = dance_school_import_decide($index, $rec);
        $action = (string) ($decision['action'] ?? 'invalid');
        if ($action === 'unchanged' || $action === 'skip_combined' || $action === 'skip_uncertain' || $action === 'invalid') {
            if ($action === 'unchanged') {
                $unchanged++;
            } else {
                $skipped++;
            }
            continue;
        }
        try {
            $db->beginTransaction();
            if ($action === 'create') {
                $newId = dance_school_import_create($db, $decision);
                $decision['school_id'] = $newId;
                $index['next_temp'] = $newId;
                dance_school_import_index_remember($index, $decision);
                $created++;
            } else {
                dance_school_import_fill($db, $decision);
                dance_school_import_index_remember($index, $decision);
                $filled++;
            }
            $db->commit();
        } catch (Throwable $ex) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $errors++;
            error_log('dance_school_import_apply: ' . $ex->getMessage());
            $line = (int) ($rec['line'] ?? 0);
            $messages[] = 'Sor ' . $line . ' (' . (string) ($rec['name'] ?? '') . '): mentési hiba.';
        }
    }

    return [
        'ok' => $errors === 0,
        'created' => $created,
        'filled' => $filled,
        'unchanged' => $unchanged,
        'skipped' => $skipped,
        'errors' => $errors,
        'messages' => $messages,
    ];
}

/**
 * @param array<string, mixed> $decision
 */
function dance_school_import_create(PDO $db, array $decision): int
{
    $rec = is_array($decision['record'] ?? null) ? $decision['record'] : [];
    $row = dance_school_empty_row();
    $row['name'] = (string) ($rec['name'] ?? '');
    $row['city'] = (string) ($rec['city'] ?? '');
    $row['website_url'] = (string) ($rec['website_url'] ?? '');
    $row['email'] = (string) ($rec['email'] ?? '');
    $row['phone'] = (string) ($rec['phone'] ?? '');
    $row['facebook_url'] = (string) ($rec['facebook_url'] ?? '');
    $row['instagram_url'] = (string) ($rec['instagram_url'] ?? '');
    $row['tiktok_url'] = (string) ($rec['tiktok_url'] ?? '');
    $row['youtube_url'] = (string) ($rec['youtube_url'] ?? '');
    $row['pricing_info'] = (string) ($rec['pricing_info'] ?? '');
    $row['trial_lesson_info'] = (string) ($rec['trial_lesson_info'] ?? '');
    $row['languages'] = 'HU';
    $row['accepts_beginners'] = 1;
    $row['is_active'] = 1;
    $row['status'] = 'import';
    $row['admin_notes'] = (string) ($decision['append_notes'] ?? '');
    $save = dance_school_save($db, $row, null);
    if (empty($save['ok'])) {
        throw new RuntimeException((string) ($save['error'] ?? 'Mentési hiba.'));
    }
    $schoolId = (int) ($save['id'] ?? 0);
    if ($schoolId <= 0) {
        throw new RuntimeException('Az új tánciskola azonosítója hiányzik.');
    }
    dance_school_import_attach($db, $schoolId, $decision, 0);

    return $schoolId;
}

/**
 * @param array<string, mixed> $decision
 */
function dance_school_import_fill(PDO $db, array $decision): void
{
    $schoolId = (int) ($decision['school_id'] ?? 0);
    if ($schoolId <= 0) {
        throw new RuntimeException('Hiányzik a tánciskola azonosítója.');
    }
    $current = dance_school_by_id($db, $schoolId);
    if ($current === null) {
        throw new RuntimeException('A tánciskola időközben eltűnt.');
    }
    $allowed = [
        'city', 'website_url', 'email', 'phone', 'facebook_url', 'instagram_url',
        'tiktok_url', 'youtube_url', 'pricing_info', 'trial_lesson_info', 'languages',
    ];
    $sets = [];
    $params = [];
    $updates = is_array($decision['updates'] ?? null) ? $decision['updates'] : [];
    foreach ($allowed as $field) {
        if (!array_key_exists($field, $updates)) {
            continue;
        }
        if (trim((string) ($current[$field] ?? '')) !== '') {
            continue;
        }
        $value = trim((string) $updates[$field]);
        if ($value === '') {
            continue;
        }
        $sets[] = '`' . $field . '` = ?';
        $params[] = $value;
    }
    $append = trim((string) ($decision['append_notes'] ?? ''));
    $notes = trim((string) ($current['admin_notes'] ?? ''));
    if ($append !== '' && !str_contains($notes, $append)) {
        $sets[] = '`admin_notes` = ?';
        $params[] = $notes === '' ? $append : $notes . "\n" . $append;
    }
    if ($sets !== []) {
        $params[] = $schoolId;
        $db->prepare('UPDATE `dance_schools` SET ' . implode(', ', $sets) . ' WHERE `id` = ?')->execute($params);
    }
    $venues = dance_school_venues($db, $schoolId);
    $linkId = 0;
    foreach ($venues as $venue) {
        if ($linkId === 0) {
            $linkId = (int) ($venue['id'] ?? 0);
        }
        if (!empty($venue['is_active'])) {
            $linkId = (int) ($venue['id'] ?? 0);
            break;
        }
    }
    dance_school_import_attach($db, $schoolId, $decision, $linkId);
}

/**
 * @param array<string, mixed> $decision
 */
function dance_school_import_attach(PDO $db, int $schoolId, array $decision, int $existingLinkId): void
{
    $offerings = [];
    foreach ($decision['offerings'] ?? [] as $offering) {
        if (is_array($offering)) {
            $offerings[] = $offering;
        }
    }
    $linkId = $existingLinkId;
    if (!empty($decision['create_venue']) && $linkId <= 0) {
        $venueId = dance_school_import_find_or_create_venue(
            $db,
            (string) ($decision['venue_name'] ?? ''),
            (string) ($decision['venue_city'] ?? ''),
            (string) ($decision['venue_address'] ?? ''),
            (string) ($decision['venue_postal'] ?? '')
        );
        if ($venueId <= 0) {
            throw new RuntimeException('A helyszín nem hozható létre.');
        }
        $db->prepare('
            INSERT INTO `dance_school_venues` (`school_id`, `venue_id`, `notes`, `sort_order`, `is_active`)
            VALUES (?, ?, NULL, 0, 1)
        ')->execute([$schoolId, $venueId]);
        $linkId = (int) $db->lastInsertId();
    }
    if ($linkId <= 0 || $offerings === []) {
        return;
    }
    $existing = dance_school_offerings_for_school_venue($db, $linkId);
    $haveIds = [];
    $haveLabels = [];
    $maxSort = -1;
    foreach ($existing as $row) {
        $styleId = (int) ($row['style_id'] ?? 0);
        $label = dance_school_import_fold((string) ($row['style_label'] ?? ''));
        if ($styleId > 0) {
            $haveIds[$styleId] = true;
        }
        if ($label !== '') {
            $haveLabels[$label] = true;
        }
        $maxSort = max($maxSort, (int) ($row['sort_order'] ?? 0));
    }
    $ins = $db->prepare('
        INSERT INTO `dance_school_offerings`
            (`school_venue_id`, `style_id`, `style_label`, `age_group`, `level`, `class_type`, `schedule_note`, `sort_order`)
        VALUES (?, ?, ?, \'adult\', \'all\', \'group\', NULL, ?)
    ');
    foreach ($offerings as $offering) {
        $styleId = (int) ($offering['style_id'] ?? 0);
        $label = trim((string) ($offering['style_label'] ?? ''));
        $folded = dance_school_import_fold($label);
        if ($styleId > 0 && isset($haveIds[$styleId])) {
            continue;
        }
        if ($folded !== '' && isset($haveLabels[$folded])) {
            continue;
        }
        if ($styleId <= 0 && $label === '') {
            continue;
        }
        $maxSort++;
        $ins->execute([$linkId, $styleId > 0 ? $styleId : null, $label !== '' ? $label : null, $maxSort]);
        if ($styleId > 0) {
            $haveIds[$styleId] = true;
        }
        if ($folded !== '') {
            $haveLabels[$folded] = true;
        }
    }
}

function dance_school_import_find_or_create_venue(PDO $db, string $name, string $city, string $address, string $postal): int
{
    $name = trim($name);
    if ($name === '') {
        return 0;
    }
    if (mb_strlen($name, 'UTF-8') > 180) {
        $name = mb_substr($name, 0, 180, 'UTF-8');
    }
    $city = trim($city);
    if (mb_strlen($city, 'UTF-8') > 128) {
        $city = mb_substr($city, 0, 128, 'UTF-8');
    }
    $address = trim($address);
    if (mb_strlen($address, 'UTF-8') > 255) {
        $address = mb_substr($address, 0, 255, 'UTF-8');
    }
    $postal = preg_match('/^\d{4}$/', $postal) === 1 ? $postal : '';
    if ($city !== '') {
        $st = $db->prepare('SELECT `id` FROM `events_venues` WHERE `name` = ? AND (`city` = ? OR `city` IS NULL OR `city` = \'\') LIMIT 1');
        $st->execute([$name, $city]);
    } else {
        $st = $db->prepare('SELECT `id` FROM `events_venues` WHERE `name` = ? LIMIT 1');
        $st->execute([$name]);
    }
    $existing = $st->fetchColumn();
    if ($existing !== false) {
        return (int) $existing;
    }
    $slug = events_ensure_unique_venue_slug($db, events_slugify($name), null);
    $ins = $db->prepare('
        INSERT INTO `events_venues`
            (`name`, `slug`, `description`, `country`, `city`, `postal_code`, `address`, `latitude`, `longitude`, `website_url`, `google_maps_url`, `linked_venue_id`)
        VALUES (?, ?, NULL, ?, ?, ?, ?, NULL, NULL, NULL, NULL, NULL)
    ');
    $ins->execute([
        $name,
        $slug,
        'Magyarország',
        $city !== '' ? $city : null,
        $postal !== '' ? $postal : null,
        $address !== '' ? $address : null,
    ]);

    return (int) $db->lastInsertId();
}

function dance_school_import_action_label(string $action): string
{
    return match ($action) {
        'create' => 'Új',
        'fill' => 'Üres mezők',
        'unchanged' => 'Nincs teendő',
        'skip_combined' => 'Kihagyva: összevont név',
        'skip_uncertain' => 'Kihagyva: bizonytalan',
        default => 'Hibás sor',
    };
}
