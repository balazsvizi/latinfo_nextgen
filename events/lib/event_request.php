<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/venue_request.php';
require_once __DIR__ . '/eventpics.php';
require_once __DIR__ . '/html_security.php';

/**
 * Űrlapról: kiemelt kép URL (üres = null), vagy hibaüzenet.
 *
 * @return array{0: ?string, 1: ?string} [érték vagy null, hiba]
 */
function events_normalize_featured_image_url(?string $raw): array {
    $u = trim((string) $raw);
    $u = preg_replace('/^\x{FEFF}|\x{200B}/u', '', $u) ?? $u;
    if ($u === '') {
        return [null, null];
    }
    if (strlen($u) > 2000) {
        return [null, 'A kiemelt kép URL legfeljebb 2000 karakter lehet.'];
    }
    if (preg_match('/[\s<>"\'{}|\\\\^`\x00-\x1f]/', $u)) {
        return [null, 'A kiemelt kép URL érvénytelen karaktereket tartalmaz.'];
    }
    if (!preg_match('#^https?://#i', $u) && !preg_match('#^//#', $u) && !str_starts_with($u, '/')) {
        $try = 'https://' . $u;
        $host = parse_url($try, PHP_URL_HOST);
        if ($host !== null && $host !== '' && (str_contains($host, '.') || strcasecmp($host, 'localhost') === 0)) {
            $u = $try;
        }
    }
    if (preg_match('#^https?://#i', $u)) {
        if (events_http_https_url_is_acceptable($u)) {
            return [$u, null];
        }

        return [null, 'A kiemelt kép URL érvénytelen (ellenőrizd a https:// formátumot).'];
    }
    if (preg_match('#^//#', $u)) {
        return [$u, null];
    }
    if (str_starts_with($u, '/')) {
        return [$u, null];
    }

    return [null, 'A kiemelt képhez teljes URL-t (https://…) vagy /-rel kezdődő útvonalat adj meg.'];
}

/**
 * Űrlap borító előnézet (URL-first, mint a mentés): nem üres URL mező → abszolút kép URL; különben eventpics.
 *
 * @return array{src: string, source: 'url'|'eventpic'|'none', label: string}
 */
function events_featured_image_form_preview_meta(string $featuredUrlField, string $eventpicsPickFilename): array {
    $raw = trim($featuredUrlField);
    $raw = preg_replace('/^\x{FEFF}|\x{200B}/u', '', $raw) ?? $raw;
    if ($raw !== '') {
        $decoded = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $src = events_absolute_url($decoded);
        $label = $raw;
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($label, 'UTF-8') > 52) {
                $label = mb_substr($label, 0, 50, 'UTF-8') . '…';
            }
        } elseif (strlen($label) > 52) {
            $label = substr($label, 0, 50) . '…';
        }

        return ['src' => $src, 'source' => 'url', 'label' => $label];
    }
    $pick = trim($eventpicsPickFilename);
    if ($pick !== '') {
        return [
            'src' => site_url('events/eventpics/' . rawurlencode($pick)),
            'source' => 'eventpic',
            'label' => $pick,
        ];
    }

    return ['src' => '', 'source' => 'none', 'label' => ''];
}

function events_load_organizer_options(PDO $db): array {
    $rows = $db->query('SELECT id, name FROM events_organizers ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['id']] = (string) $r['name'];
    }
    return $out;
}

function events_load_category_options(PDO $db): array {
    $rows = $db->query('SELECT id, name, parent_id, sort_order FROM events_categories ORDER BY sort_order ASC, name ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
    $nameById = [];
    foreach ($rows as $r) {
        $nameById[(int) $r['id']] = (string) $r['name'];
    }
    $children = [];
    foreach ($rows as $r) {
        $pid = isset($r['parent_id']) && $r['parent_id'] !== null ? (int) $r['parent_id'] : 0;
        if (!isset($children[$pid])) {
            $children[$pid] = [];
        }
        $children[$pid][] = [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
        ];
    }
    /* Megjelenítés: Szülő / gyerek (gyökérnek csak a név) */
    $formatCategoryLabel = static function (int $parentId, string $depthPrefix, string $leafName) use ($nameById): string {
        if ($parentId > 0) {
            $pname = $nameById[$parentId] ?? '';
            if ($pname !== '') {
                return $depthPrefix . $pname . ' / ' . $leafName;
            }
        }

        return $depthPrefix . $leafName;
    };
    $out = [];
    $seen = [];
    $walk = static function (int $pid, int $depth) use (&$walk, &$children, &$out, &$seen, $formatCategoryLabel): void {
        foreach ($children[$pid] ?? [] as $node) {
            $id = (int) $node['id'];
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $prefix = str_repeat('— ', max(0, $depth));
            $out[$id] = $formatCategoryLabel($pid, $prefix, (string) $node['name']);
            $walk($id, $depth + 1);
        }
    };
    $walk(0, 0);
    foreach ($rows as $r) {
        $id = (int) $r['id'];
        if ($id > 0 && !isset($out[$id])) {
            $pid = isset($r['parent_id']) && $r['parent_id'] !== null ? (int) $r['parent_id'] : 0;
            $out[$id] = $formatCategoryLabel($pid, '', (string) $r['name']);
        }
    }
    return $out;
}

/**
 * @return list<int>
 */
function events_organizer_ids_from_post(): array {
    $raw = $_POST['organizer_ids'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $ids = [];
    foreach ($raw as $v) {
        $i = (int) $v;
        if ($i > 0 && !in_array($i, $ids, true)) {
            $ids[] = $i;
        }
    }
    return $ids;
}

/**
 * @return list<int>
 */
function events_category_ids_from_post(): array {
    $raw = $_POST['category_ids'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $ids = [];
    foreach ($raw as $v) {
        $i = (int) $v;
        if ($i > 0 && !in_array($i, $ids, true)) {
            $ids[] = $i;
        }
    }
    return $ids;
}

/**
 * @return array<int, string> id => név
 */
function events_load_tag_options(PDO $db): array {
    if (!events_tags_tables_available($db)) {
        return [];
    }
    $rows = $db->query('SELECT `id`, `name` FROM `events_tags` ORDER BY `name` ASC, `id` ASC')->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['id']] = (string) $r['name'];
    }
    return $out;
}

/**
 * @return list<int>
 */
function events_tag_ids_from_post(): array {
    $raw = $_POST['tag_ids'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $ids = [];
    foreach ($raw as $v) {
        $i = (int) $v;
        if ($i > 0 && !in_array($i, $ids, true)) {
            $ids[] = $i;
        }
    }
    return $ids;
}

/**
 * @param list<int> $tagIds
 */
function events_save_event_tags(PDO $db, int $eventId, array $tagIds): void {
    if (!events_tags_tables_available($db)) {
        return;
    }
    $db->prepare('DELETE FROM `events_calendar_event_tags` WHERE `event_id` = ?')->execute([$eventId]);
    if ($tagIds === []) {
        return;
    }
    $ins = $db->prepare('INSERT INTO `events_calendar_event_tags` (`event_id`, `tag_id`) VALUES (?,?)');
    foreach ($tagIds as $tid) {
        if ($tid <= 0) {
            continue;
        }
        $ins->execute([$eventId, $tid]);
    }
}

/**
 * @return list<int>
 */
function events_load_event_tag_ids(PDO $db, int $eventId): array {
    if (!events_tags_tables_available($db)) {
        return [];
    }
    $st = $db->prepare('
        SELECT `tag_id` FROM `events_calendar_event_tags`
        WHERE `event_id` = ?
        ORDER BY `tag_id` ASC
    ');
    $st->execute([$eventId]);

    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN, 0));
}

/**
 * Tag ↔ speciális csoportok (teljes csere).
 *
 * @param list<int> $specialTagIds
 */
function events_save_tag_special_memberships(PDO $db, int $tagId, array $specialTagIds): void {
    if (!events_tags_tables_available($db)) {
        return;
    }
    $db->prepare('DELETE FROM `events_special_tags` WHERE `tag_id` = ?')->execute([$tagId]);
    if ($specialTagIds === []) {
        return;
    }
    $ins = $db->prepare('INSERT INTO `events_special_tags` (`special_tag_id`, `tag_id`) VALUES (?,?)');
    foreach ($specialTagIds as $sid) {
        if ($sid <= 0) {
            continue;
        }
        $ins->execute([$sid, $tagId]);
    }
}

/**
 * Meglévő speciális csoportok mellé hozzáad (nem távolít el korábbi kapcsolatot).
 *
 * @param list<int> $specialTagIdsToAdd
 */
function events_merge_tag_special_memberships(PDO $db, int $tagId, array $specialTagIdsToAdd): void {
    if (!events_tags_tables_available($db)) {
        return;
    }
    $add = [];
    foreach ($specialTagIdsToAdd as $sid) {
        $s = (int) $sid;
        if ($s > 0 && !in_array($s, $add, true)) {
            $add[] = $s;
        }
    }
    if ($add === []) {
        return;
    }
    $existing = events_load_special_ids_for_tag($db, $tagId);
    $merged = array_values(array_unique(array_merge($existing, $add)));
    sort($merged);
    events_save_tag_special_memberships($db, $tagId, $merged);
}

/**
 * @return list<int>
 */
function events_load_special_ids_for_tag(PDO $db, int $tagId): array {
    if (!events_tags_tables_available($db)) {
        return [];
    }
    $st = $db->prepare('SELECT `special_tag_id` FROM `events_special_tags` WHERE `tag_id` = ? ORDER BY `special_tag_id` ASC');
    $st->execute([$tagId]);

    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN, 0));
}

/**
 * @param list<int> $organizerIds
 */
function events_save_event_organizers(PDO $db, int $eventId, array $organizerIds): void {
    $db->prepare('DELETE FROM `events_calendar_event_organizers` WHERE `event_id` = ?')->execute([$eventId]);
    if ($organizerIds === []) {
        return;
    }
    $ins = $db->prepare('INSERT INTO `events_calendar_event_organizers` (`event_id`, `organizer_id`, `sort_order`) VALUES (?,?,?)');
    $ord = 0;
    foreach ($organizerIds as $oid) {
        if ($oid <= 0) {
            continue;
        }
        $ins->execute([$eventId, $oid, $ord]);
        $ord++;
    }
}

/**
 * @return list<int>
 */
function events_load_event_organizer_ids(PDO $db, int $eventId): array {
    $st = $db->prepare('
        SELECT `organizer_id` FROM `events_calendar_event_organizers`
        WHERE `event_id` = ?
        ORDER BY `sort_order` ASC, `organizer_id` ASC
    ');
    $st->execute([$eventId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN, 0));
}

/**
 * @param list<int> $categoryIds
 */
function events_save_event_categories(PDO $db, int $eventId, array $categoryIds): void {
    $db->prepare('DELETE FROM `events_calendar_event_categories` WHERE `event_id` = ?')->execute([$eventId]);
    if ($categoryIds === []) {
        return;
    }
    $ins = $db->prepare('INSERT INTO `events_calendar_event_categories` (`event_id`, `category_id`) VALUES (?,?)');
    foreach ($categoryIds as $cid) {
        if ($cid <= 0) {
            continue;
        }
        $ins->execute([$eventId, $cid]);
    }
}

/**
 * @return list<int>
 */
function events_load_event_category_ids(PDO $db, int $eventId): array {
    $st = $db->prepare('
        SELECT `category_id` FROM `events_calendar_event_categories`
        WHERE `event_id` = ?
        ORDER BY `category_id` ASC
    ');
    $st->execute([$eventId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN, 0));
}

/**
 * Űrlap → adatbázis mezők + slug egyediség.
 *
 * @param array<string,mixed> $defaults Alapértelmezett értékek (pl. DB sor szerkesztésnél)
 * @return array{0: array<string,mixed>, 1: ?string, 2: list<int>, 3: list<int>, 4: list<int>} [row, hiba, szervező ID-k, kategória ID-k, tag ID-k]
 */
function events_row_from_request(PDO $db, array $defaults, ?int $excludeIdForSlug): array {
    $row = $defaults;
    $row['event_name'] = trim((string) ($_POST['event_name'] ?? ''));
    $slugInput = trim((string) ($_POST['event_slug'] ?? ''));
    $row['event_slug'] = $slugInput !== '' ? events_slugify($slugInput) : '';
    $row['event_content'] = events_sanitize_html_fragment((string) ($_POST['event_content'] ?? ''));
    $st = (string) ($_POST['event_status'] ?? events_default_post_status());
    if (!events_is_allowed_post_status($st)) {
        $st = events_default_post_status();
    }
    $row['event_status'] = $st;

    $sd = trim((string) ($_POST['event_start_date'] ?? ''));
    $stt = trim((string) ($_POST['event_start_time'] ?? ''));
    $ed = trim((string) ($_POST['event_end_date'] ?? ''));
    $ett = trim((string) ($_POST['event_end_time'] ?? ''));
    $row['event_start'] = events_build_event_datetime($sd, $stt);
    $row['event_end'] = events_build_event_datetime($ed, $ett);
    $row['event_allday'] = isset($_POST['event_allday']) ? 1 : 0;

    $cf = trim((string) ($_POST['event_cost_from'] ?? ''));
    $ct = trim((string) ($_POST['event_cost_to'] ?? ''));
    $row['event_cost_from'] = $cf === '' ? null : (float) str_replace(',', '.', $cf);
    $row['event_cost_to'] = $ct === '' ? null : (float) str_replace(',', '.', $ct);
    $organizerIds = events_organizer_ids_from_post();
    $categoryIds = events_category_ids_from_post();
    $tagIds = events_tags_tables_available($db) ? events_tag_ids_from_post() : [];

    if ($categoryIds !== []) {
        $ph = implode(',', array_fill(0, count($categoryIds), '?'));
        $stCat = $db->prepare("SELECT `id` FROM `events_categories` WHERE `id` IN ({$ph})");
        $stCat->execute($categoryIds);
        $existing = array_map('intval', $stCat->fetchAll(PDO::FETCH_COLUMN, 0));
        sort($existing);
        $chk = $categoryIds;
        sort($chk);
        if ($existing !== $chk) {
            return [$row, 'A kiválasztott kategóriák között érvénytelen (nem létező) elem van.', $organizerIds, $categoryIds, $tagIds];
        }
    }

    if ($tagIds !== []) {
        $ph = implode(',', array_fill(0, count($tagIds), '?'));
        $stTags = $db->prepare("SELECT `id` FROM `events_tags` WHERE `id` IN ({$ph})");
        $stTags->execute($tagIds);
        $existTags = array_map('intval', $stTags->fetchAll(PDO::FETCH_COLUMN, 0));
        sort($existTags);
        $chkT = $tagIds;
        sort($chkT);
        if ($existTags !== $chkT) {
            return [$row, 'A kiválasztott címkék között érvénytelen (nem létező) elem van.', $organizerIds, $categoryIds, $tagIds];
        }
    }

    [$eventUrl, $eventUrlErr] = events_normalize_safe_url((string) ($_POST['event_url'] ?? ''), true);
    if ($eventUrlErr !== null) {
        return [$row, $eventUrlErr, $organizerIds, $categoryIds, $tagIds];
    }
    $row['event_url'] = $eventUrl;
    if (array_key_exists('event_latinfohu_partner', $_POST)) {
        $row['event_latinfohu_partner'] = isset($_POST['event_latinfohu_partner']) ? 1 : 0;
    } else {
        $row['event_latinfohu_partner'] = !empty($defaults['event_latinfohu_partner']) ? 1 : 0;
    }

    $vid = trim((string) ($_POST['venue_id'] ?? ''));
    $rawVid = $vid === '' ? null : (int) $vid;
    $row['venue_id'] = events_normalize_venue_id($db, $rawVid);

    [$featUrlInput, $featUrlErr] = events_normalize_featured_image_url((string) ($_POST['event_featured_image_url'] ?? ''));
    if ($featUrlErr !== null) {
        return [$row, $featUrlErr, $organizerIds, $categoryIds, $tagIds];
    }
    [$featPickPath, $featPickErr] = events_eventpics_normalize_selected((string) ($_POST['event_featured_image_pick'] ?? ''));
    if ($featPickErr !== null) {
        return [$row, $featPickErr, $organizerIds, $categoryIds, $tagIds];
    }
    [$featUploadPath, $featUploadErr] = events_eventpics_handle_upload($_FILES['event_featured_image_upload'] ?? null);
    if ($featUploadErr !== null) {
        return [$row, $featUploadErr, $organizerIds, $categoryIds, $tagIds];
    }
    // Prioritás: URL > friss feltöltés > eventpics kiválasztás.
    $row['event_featured_image_url'] = $featUrlInput ?? $featUploadPath ?? $featPickPath;

    if ($row['event_name'] === '') {
        return [$row, 'Az esemény neve kötelező.', $organizerIds, $categoryIds, $tagIds];
    }

    $baseSlug = $row['event_slug'] !== '' ? $row['event_slug'] : events_slugify($row['event_name']);
    $row['event_slug'] = events_ensure_unique_slug($db, $baseSlug, $excludeIdForSlug);

    return [$row, null, $organizerIds, $categoryIds, $tagIds];
}

/**
 * @param array<string,mixed> $row
 */
function events_row_for_form(array $row): array {
    $e = $row;
    foreach (['event_start', 'event_end', 'event_url'] as $k) {
        $e[$k] = $e[$k] !== null ? (string) $e[$k] : '';
    }
    [$e['event_start_date'], $e['event_start_time']] = events_split_event_datetime($e['event_start']);
    [$e['event_end_date'], $e['event_end_time']] = events_split_event_datetime($e['event_end']);
    foreach (['event_start_time', 'event_end_time'] as $tk) {
        if ($e[$tk] !== '' && strlen($e[$tk]) > 5) {
            $e[$tk] = substr($e[$tk], 0, 5);
        }
    }
    foreach (['event_cost_from', 'event_cost_to'] as $k) {
        if ($e[$k] === null) {
            $e[$k] = '';
        } else {
            $e[$k] = is_float($e[$k]) ? (string) $e[$k] : (string) $e[$k];
        }
    }
    $e['venue_id'] = isset($e['venue_id']) && $e['venue_id'] !== null ? (string) (int) $e['venue_id'] : '';
    $e['event_featured_image_url'] = isset($e['event_featured_image_url']) && $e['event_featured_image_url'] !== null
        ? (string) $e['event_featured_image_url']
        : '';
    $e['event_featured_image_pick'] = events_eventpics_extract_selected_from_featured($e['event_featured_image_url']);
    if (!isset($e['organizer_ids']) || !is_array($e['organizer_ids'])) {
        $e['organizer_ids'] = [];
    } else {
        $e['organizer_ids'] = array_values(array_unique(array_map('intval', $e['organizer_ids'])));
    }
    if (!isset($e['category_ids']) || !is_array($e['category_ids'])) {
        $e['category_ids'] = [];
    } else {
        $e['category_ids'] = array_values(array_unique(array_map('intval', $e['category_ids'])));
    }
    if (!isset($e['tag_ids']) || !is_array($e['tag_ids'])) {
        $e['tag_ids'] = [];
    } else {
        $e['tag_ids'] = array_values(array_unique(array_map('intval', $e['tag_ids'])));
    }
    $e['event_allday'] = !empty($e['event_allday']);
    $e['event_latinfohu_partner'] = !empty($e['event_latinfohu_partner']);
    $st = (string) ($e['event_status'] ?? '');
    if (!events_is_allowed_post_status($st)) {
        $e['event_status'] = events_default_post_status();
    }
    return $e;
}

function events_build_event_datetime(string $date, string $time): ?string {
    if ($date === '') {
        return null;
    }
    $tm = $time !== '' ? $time : '00:00';
    $dt = date_create($date . ' ' . $tm);
    return $dt ? $dt->format('Y-m-d H:i:s') : null;
}

/**
 * @return array{0: string, 1: string}
 */
function events_split_event_datetime(?string $datetime): array {
    if ($datetime === null || $datetime === '') {
        return ['', ''];
    }
    $dt = date_create($datetime);
    if (!$dt) {
        return ['', ''];
    }
    return [$dt->format('Y-m-d'), $dt->format('H:i')];
}
