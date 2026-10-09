<?php
declare(strict_types=1);

require_once __DIR__ . '/dance_schools_schema.php';
require_once __DIR__ . '/slug.php';
require_once __DIR__ . '/html_security.php';
require_once __DIR__ . '/event_request.php';

/**
 * Tánciskola CRUD és kapcsolódó adatok.
 */

function dance_school_slugify(string $name): string
{
    return events_dj_slugify($name);
}

function dance_school_slug_exists(PDO $db, string $slug, ?int $excludeId): bool
{
    if (!dance_schools_tables_ready($db)) {
        return false;
    }
    $sql = 'SELECT 1 FROM `dance_schools` WHERE `slug` = ?';
    $params = [$slug];
    if ($excludeId !== null && $excludeId > 0) {
        $sql .= ' AND `id` <> ?';
        $params[] = $excludeId;
    }
    $st = $db->prepare($sql . ' LIMIT 1');
    $st->execute($params);

    return (bool) $st->fetchColumn();
}

function dance_school_ensure_unique_slug(PDO $db, string $base, ?int $excludeId): string
{
    $slug = $base !== '' ? $base : 'tanciskola';
    $n = 2;
    while (dance_school_slug_exists($db, $slug, $excludeId)) {
        if ($n > 500) {
            $slug = $base . '_' . bin2hex(random_bytes(3));
            if (!dance_school_slug_exists($db, $slug, $excludeId)) {
                return $slug;
            }
            throw new RuntimeException('Nem sikerült egyedi slugot generálni.');
        }
        $slug = $base . '_' . $n;
        $n++;
    }

    return $slug;
}

/**
 * @return array<string, mixed>
 */
function dance_school_empty_row(): array
{
    return [
        'id' => 0,
        'name' => '',
        'slug' => '',
        'description' => '',
        'founded_year' => null,
        'city' => '',
        'website_url' => '',
        'facebook_url' => '',
        'instagram_url' => '',
        'tiktok_url' => '',
        'youtube_url' => '',
        'email' => '',
        'email_is_private' => 0,
        'phone' => '',
        'phone_is_private' => 0,
        'photo_url' => '',
        'logo_url' => '',
        'trial_lesson_info' => '',
        'pricing_info' => '',
        'schedule_url' => '',
        'registration_url' => '',
        'languages' => '',
        'accepts_beginners' => 1,
        'has_kids_classes' => 0,
        'has_performance_team' => 0,
        'is_active' => 1,
        'is_published' => 0,
        'admin_notes' => '',
        'sort_order' => 0,
    ];
}

/**
 * @return array{0: array<string, mixed>, 1: ?string}
 */
function dance_school_from_post(array $post): array
{
    $row = dance_school_empty_row();
    $row['name'] = trim((string) ($post['name'] ?? ''));
    $row['slug'] = trim((string) ($post['slug'] ?? ''));
    $row['description'] = events_sanitize_html_fragment((string) ($post['description'] ?? ''));
    $yearRaw = trim((string) ($post['founded_year'] ?? ''));
    $row['founded_year'] = $yearRaw !== '' && ctype_digit($yearRaw) ? (int) $yearRaw : null;
    if ($row['founded_year'] !== null && ($row['founded_year'] < 1900 || $row['founded_year'] > (int) date('Y') + 1)) {
        return [$row, 'A megalakulás éve érvénytelen.'];
    }
    $row['city'] = trim((string) ($post['city'] ?? ''));
    foreach (['website_url', 'facebook_url', 'instagram_url', 'tiktok_url', 'youtube_url', 'schedule_url', 'registration_url'] as $urlKey) {
        [$url, $urlErr] = events_normalize_safe_url((string) ($post[$urlKey] ?? ''), true);
        if ($urlErr !== null) {
            return [$row, $urlErr];
        }
        $row[$urlKey] = $url ?? '';
    }
    $email = trim((string) ($post['email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return [$row, 'Az e-mail cím érvénytelen.'];
    }
    $row['email'] = $email;
    $row['email_is_private'] = !empty($post['email_is_private']) ? 1 : 0;
    $row['phone'] = trim((string) ($post['phone'] ?? ''));
    $row['phone_is_private'] = !empty($post['phone_is_private']) ? 1 : 0;
    $row['trial_lesson_info'] = trim((string) ($post['trial_lesson_info'] ?? ''));
    $row['pricing_info'] = trim((string) ($post['pricing_info'] ?? ''));
    $row['languages'] = trim((string) ($post['languages'] ?? ''));
    $row['accepts_beginners'] = !empty($post['accepts_beginners']) ? 1 : 0;
    $row['has_kids_classes'] = !empty($post['has_kids_classes']) ? 1 : 0;
    $row['has_performance_team'] = !empty($post['has_performance_team']) ? 1 : 0;
    $row['is_active'] = !empty($post['is_active']) ? 1 : 0;
    $row['is_published'] = !empty($post['is_published']) ? 1 : 0;
    $row['admin_notes'] = trim((string) ($post['admin_notes'] ?? ''));
    $row['sort_order'] = (int) ($post['sort_order'] ?? 0);
    $row['photo_url'] = trim((string) ($post['photo_url'] ?? ''));
    $row['logo_url'] = trim((string) ($post['logo_url'] ?? ''));

    if ($row['name'] === '') {
        return [$row, 'A név megadása kötelező.'];
    }

    return [$row, null];
}

function dance_school_by_id(PDO $db, int $id): ?array
{
    if ($id <= 0 || !dance_schools_tables_ready($db)) {
        return null;
    }
    $st = $db->prepare('SELECT * FROM `dance_schools` WHERE `id` = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

/**
 * @param array{f_q?:string,f_city?:string,f_active?:string,order?:string,dir_param?:string} $filters
 * @return list<array<string, mixed>>
 */
function dance_schools_admin_fetch(PDO $db, array $filters, ?int $listLimit = null): array
{
    if (!dance_schools_tables_ready($db)) {
        return [];
    }
    $where = ['1=1'];
    $params = [];
    $f_q = trim((string) ($filters['f_q'] ?? ''));
    if ($f_q !== '') {
        if (ctype_digit($f_q)) {
            $where[] = '(s.`id` = ? OR s.`name` LIKE ? OR s.`slug` LIKE ? OR s.`city` LIKE ?)';
            $params[] = (int) $f_q;
            $like = '%' . $f_q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        } else {
            $where[] = '(s.`name` LIKE ? OR s.`slug` LIKE ? OR s.`city` LIKE ?)';
            $like = '%' . $f_q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
    }
    $f_city = trim((string) ($filters['f_city'] ?? ''));
    if ($f_city !== '') {
        $where[] = 's.`city` LIKE ?';
        $params[] = '%' . $f_city . '%';
    }
    $f_active = (string) ($filters['f_active'] ?? '');
    if ($f_active === '1' || $f_active === '0') {
        $where[] = 's.`is_active` = ?';
        $params[] = (int) $f_active;
    }
    $whereSql = implode(' AND ', $where);
    $order = (string) ($filters['order'] ?? 'name');
    $dir = strtoupper((string) ($filters['dir_param'] ?? 'asc')) === 'DESC' ? 'DESC' : 'ASC';
    $orderSql = match ($order) {
        'id' => 's.`id` ' . $dir,
        'city' => 's.`city` ' . $dir . ', s.`name` ASC',
        'slug' => 's.`slug` ' . $dir,
        'active' => 's.`is_active` ' . $dir . ', s.`name` ASC',
        'locations' => 'COALESCE(loc.`location_count`, 0) ' . $dir . ', s.`name` ASC',
        'teachers' => 'COALESCE(tch.`teacher_count`, 0) ' . $dir . ', s.`name` ASC',
        'events' => 'COALESCE(ev.`upcoming_count`, 0) ' . $dir . ', s.`name` ASC',
        'views' => 'COALESCE(vw.`view_count`, 0) ' . $dir . ', s.`name` ASC',
        default => 's.`name` ' . $dir . ', s.`id` ASC',
    };
    $limitSql = $listLimit === null ? '' : ' LIMIT ' . (int) $listLimit;

    $st = $db->prepare("
        SELECT s.*,
               COALESCE(loc.`location_count`, 0) AS `location_count`,
               COALESCE(tch.`teacher_count`, 0) AS `teacher_count`,
               COALESCE(ev.`upcoming_count`, 0) AS `upcoming_count`,
               COALESCE(vw.`view_count`, 0) AS `view_count`
        FROM `dance_schools` s
        LEFT JOIN (
            SELECT `school_id`, COUNT(*) AS `location_count`
            FROM `dance_school_locations`
            GROUP BY `school_id`
        ) loc ON loc.`school_id` = s.`id`
        LEFT JOIN (
            SELECT `school_id`, COUNT(DISTINCT `tag_id`) AS `teacher_count`
            FROM `dance_school_teachers`
            GROUP BY `school_id`
        ) tch ON tch.`school_id` = s.`id`
        LEFT JOIN (
            SELECT `school_id`, COUNT(*) AS `upcoming_count`
            FROM `dance_school_events`
            WHERE `starts_at` >= NOW()
            GROUP BY `school_id`
        ) ev ON ev.`school_id` = s.`id`
        LEFT JOIN (
            SELECT `entity_id`, COUNT(*) AS `view_count`
            FROM `dance_entity_views`
            WHERE `entity_type` = 'school' AND `metric` = 'page_view' AND `is_bot` = 0
            GROUP BY `entity_id`
        ) vw ON vw.`entity_id` = s.`id`
        WHERE {$whereSql}
        ORDER BY {$orderSql}
        {$limitSql}
    ");
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function dance_schools_admin_total_count(PDO $db): int
{
    if (!dance_schools_tables_ready($db)) {
        return 0;
    }

    return (int) $db->query('SELECT COUNT(*) FROM `dance_schools`')->fetchColumn();
}

/**
 * @param array<string, mixed> $row
 * @return array{ok:bool,id?:int,error?:string}
 */
function dance_school_save(PDO $db, array $row, ?int $id = null): array
{
    if (!dance_schools_ensure_schema($db)) {
        return ['ok' => false, 'error' => 'A tánciskola táblák nem elérhetők.'];
    }
    $name = trim((string) ($row['name'] ?? ''));
    if ($name === '') {
        return ['ok' => false, 'error' => 'A név megadása kötelező.'];
    }
    $slugInput = trim((string) ($row['slug'] ?? ''));
    $base = $slugInput !== '' ? dance_school_slugify($slugInput) : dance_school_slugify($name);
    try {
        $slug = dance_school_ensure_unique_slug($db, $base, $id);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    $fields = [
        'name' => $name,
        'slug' => $slug,
        'description' => (string) ($row['description'] ?? ''),
        'founded_year' => $row['founded_year'] ?? null,
        'city' => trim((string) ($row['city'] ?? '')) ?: null,
        'website_url' => trim((string) ($row['website_url'] ?? '')) ?: null,
        'facebook_url' => trim((string) ($row['facebook_url'] ?? '')) ?: null,
        'instagram_url' => trim((string) ($row['instagram_url'] ?? '')) ?: null,
        'tiktok_url' => trim((string) ($row['tiktok_url'] ?? '')) ?: null,
        'youtube_url' => trim((string) ($row['youtube_url'] ?? '')) ?: null,
        'email' => trim((string) ($row['email'] ?? '')) ?: null,
        'email_is_private' => !empty($row['email_is_private']) ? 1 : 0,
        'phone' => trim((string) ($row['phone'] ?? '')) ?: null,
        'phone_is_private' => !empty($row['phone_is_private']) ? 1 : 0,
        'photo_url' => trim((string) ($row['photo_url'] ?? '')) ?: null,
        'logo_url' => trim((string) ($row['logo_url'] ?? '')) ?: null,
        'trial_lesson_info' => trim((string) ($row['trial_lesson_info'] ?? '')) ?: null,
        'pricing_info' => trim((string) ($row['pricing_info'] ?? '')) ?: null,
        'schedule_url' => trim((string) ($row['schedule_url'] ?? '')) ?: null,
        'registration_url' => trim((string) ($row['registration_url'] ?? '')) ?: null,
        'languages' => trim((string) ($row['languages'] ?? '')) ?: null,
        'accepts_beginners' => !empty($row['accepts_beginners']) ? 1 : 0,
        'has_kids_classes' => !empty($row['has_kids_classes']) ? 1 : 0,
        'has_performance_team' => !empty($row['has_performance_team']) ? 1 : 0,
        'is_active' => !empty($row['is_active']) ? 1 : 0,
        'is_published' => !empty($row['is_published']) ? 1 : 0,
        'admin_notes' => trim((string) ($row['admin_notes'] ?? '')) ?: null,
        'sort_order' => (int) ($row['sort_order'] ?? 0),
    ];

    try {
        if ($id !== null && $id > 0) {
            $sets = [];
            $params = [];
            foreach ($fields as $col => $val) {
                $sets[] = '`' . $col . '` = ?';
                $params[] = $val;
            }
            $params[] = $id;
            $db->prepare('UPDATE `dance_schools` SET ' . implode(', ', $sets) . ' WHERE `id` = ?')->execute($params);

            return ['ok' => true, 'id' => $id];
        }
        $cols = array_keys($fields);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $colSql = '`' . implode('`,`', $cols) . '`';
        $db->prepare("INSERT INTO `dance_schools` ({$colSql}) VALUES ({$placeholders})")->execute(array_values($fields));

        return ['ok' => true, 'id' => (int) $db->lastInsertId()];
    } catch (Throwable $ex) {
        error_log('dance_school_save: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Mentési hiba történt.'];
    }
}

function dance_school_delete(PDO $db, int $id): array
{
    if ($id <= 0 || !dance_schools_tables_ready($db)) {
        return ['ok' => false, 'error' => 'Érvénytelen azonosító.'];
    }
    try {
        $db->beginTransaction();
        $locIds = $db->prepare('SELECT `id` FROM `dance_school_locations` WHERE `school_id` = ?');
        $locIds->execute([$id]);
        $ids = array_map('intval', $locIds->fetchAll(PDO::FETCH_COLUMN) ?: []);
        if ($ids !== []) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $db->prepare("DELETE FROM `dance_school_offerings` WHERE `location_id` IN ({$in})")->execute($ids);
        }
        $db->prepare('DELETE FROM `dance_school_locations` WHERE `school_id` = ?')->execute([$id]);
        $db->prepare('DELETE FROM `dance_school_events` WHERE `school_id` = ?')->execute([$id]);
        $db->prepare('DELETE FROM `dance_school_teachers` WHERE `school_id` = ?')->execute([$id]);
        try {
            $db->prepare('DELETE FROM `nextgen_partner_dance_schools` WHERE `school_id` = ?')->execute([$id]);
        } catch (Throwable) {
            // partner tábla opcionális
        }
        $db->prepare("DELETE FROM `dance_entity_views` WHERE `entity_type` = 'school' AND `entity_id` = ?")->execute([$id]);
        $db->prepare('DELETE FROM `dance_schools` WHERE `id` = ?')->execute([$id]);
        $db->commit();

        return ['ok' => true];
    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('dance_school_delete: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Törlés sikertelen.'];
    }
}

/**
 * @return list<array<string, mixed>>
 */
function dance_school_locations(PDO $db, int $schoolId): array
{
    if ($schoolId <= 0 || !dance_schools_tables_ready($db)) {
        return [];
    }
    $st = $db->prepare('
        SELECT * FROM `dance_school_locations`
        WHERE `school_id` = ?
        ORDER BY `sort_order` ASC, `id` ASC
    ');
    $st->execute([$schoolId]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return list<array<string, mixed>>
 */
function dance_school_offerings_for_location(PDO $db, int $locationId): array
{
    if ($locationId <= 0 || !dance_schools_tables_ready($db)) {
        return [];
    }
    $st = $db->prepare('
        SELECT o.*, s.`name` AS `style_name`
        FROM `dance_school_offerings` o
        LEFT JOIN `events_styles` s ON s.`id` = o.`style_id`
        WHERE o.`location_id` = ?
        ORDER BY o.`sort_order` ASC, o.`id` ASC
    ');
    try {
        $st->execute([$locationId]);
    } catch (Throwable) {
        $st = $db->prepare('
            SELECT o.*, NULL AS `style_name`
            FROM `dance_school_offerings` o
            WHERE o.`location_id` = ?
            ORDER BY o.`sort_order` ASC, o.`id` ASC
        ');
        $st->execute([$locationId]);
    }

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return list<array<string, mixed>>
 */
function dance_school_events_list(PDO $db, int $schoolId): array
{
    if ($schoolId <= 0 || !dance_schools_tables_ready($db)) {
        return [];
    }
    $st = $db->prepare('
        SELECT e.*, l.`name` AS `location_name`
        FROM `dance_school_events` e
        LEFT JOIN `dance_school_locations` l ON l.`id` = e.`location_id`
        WHERE e.`school_id` = ?
        ORDER BY e.`starts_at` ASC, e.`id` ASC
    ');
    $st->execute([$schoolId]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return list<array<string, mixed>>
 */
function dance_school_teachers_list(PDO $db, int $schoolId): array
{
    if ($schoolId <= 0 || !dance_schools_tables_ready($db)) {
        return [];
    }
    $st = $db->prepare('
        SELECT dst.*, t.`name` AS `teacher_name`, t.`slug` AS `teacher_slug`
        FROM `dance_school_teachers` dst
        INNER JOIN `events_tags` t ON t.`id` = dst.`tag_id`
        WHERE dst.`school_id` = ?
        ORDER BY dst.`sort_order` ASC, t.`name` ASC
    ');
    $st->execute([$schoolId]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Helyszínek + kínálat szinkron (POST struktúra).
 *
 * @param list<array<string, mixed>> $locations
 */
function dance_school_sync_locations(PDO $db, int $schoolId, array $locations): array
{
    if ($schoolId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen iskola.'];
    }
    $ageOk = array_keys(dance_school_age_group_labels());
    $levelOk = array_keys(dance_school_level_labels());
    $classOk = array_keys(dance_school_class_type_labels());

    try {
        $existingLoc = $db->prepare('SELECT `id` FROM `dance_school_locations` WHERE `school_id` = ?');
        $existingLoc->execute([$schoolId]);
        $keepLocIds = [];
        $sort = 0;
        foreach ($locations as $loc) {
            if (!is_array($loc)) {
                continue;
            }
            $name = trim((string) ($loc['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $locId = (int) ($loc['id'] ?? 0);
            $lat = trim((string) ($loc['latitude'] ?? ''));
            $lng = trim((string) ($loc['longitude'] ?? ''));
            $latVal = $lat !== '' && is_numeric($lat) ? (float) $lat : null;
            $lngVal = $lng !== '' && is_numeric($lng) ? (float) $lng : null;
            [$mapsNorm, $mapsErr] = events_normalize_safe_url((string) ($loc['google_maps_url'] ?? ''), true);
            $maps = ($mapsErr === null && $mapsNorm !== null) ? $mapsNorm : '';
            $payload = [
                $schoolId,
                $name,
                trim((string) ($loc['address'] ?? '')) ?: null,
                trim((string) ($loc['city'] ?? '')) ?: null,
                trim((string) ($loc['postal_code'] ?? '')) ?: null,
                trim((string) ($loc['country'] ?? '')) ?: 'Magyarország',
                $latVal,
                $lngVal,
                $maps !== '' ? $maps : null,
                trim((string) ($loc['notes'] ?? '')) ?: null,
                $sort,
                !empty($loc['is_active']) ? 1 : 0,
            ];
            if ($locId > 0) {
                $chk = $db->prepare('SELECT `id` FROM `dance_school_locations` WHERE `id` = ? AND `school_id` = ?');
                $chk->execute([$locId, $schoolId]);
                if ($chk->fetchColumn() === false) {
                    $locId = 0;
                }
            }
            if ($locId > 0) {
                $db->prepare('
                    UPDATE `dance_school_locations`
                    SET `name`=?, `address`=?, `city`=?, `postal_code`=?, `country`=?,
                        `latitude`=?, `longitude`=?, `google_maps_url`=?, `notes`=?,
                        `sort_order`=?, `is_active`=?
                    WHERE `id`=? AND `school_id`=?
                ')->execute([
                    $payload[1], $payload[2], $payload[3], $payload[4], $payload[5],
                    $payload[6], $payload[7], $payload[8], $payload[9],
                    $payload[10], $payload[11], $locId, $schoolId,
                ]);
            } else {
                $db->prepare('
                    INSERT INTO `dance_school_locations`
                    (`school_id`,`name`,`address`,`city`,`postal_code`,`country`,`latitude`,`longitude`,`google_maps_url`,`notes`,`sort_order`,`is_active`)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                ')->execute($payload);
                $locId = (int) $db->lastInsertId();
            }
            $keepLocIds[] = $locId;

            $offerings = $loc['offerings'] ?? [];
            if (!is_array($offerings)) {
                $offerings = [];
            }
            $keepOffIds = [];
            $oSort = 0;
            foreach ($offerings as $off) {
                if (!is_array($off)) {
                    continue;
                }
                $styleId = (int) ($off['style_id'] ?? 0);
                $styleLabel = trim((string) ($off['style_label'] ?? ''));
                if ($styleId <= 0 && $styleLabel === '') {
                    continue;
                }
                $age = (string) ($off['age_group'] ?? 'adult');
                $level = (string) ($off['level'] ?? 'all');
                $classType = (string) ($off['class_type'] ?? 'group');
                if (!in_array($age, $ageOk, true)) {
                    $age = 'adult';
                }
                if (!in_array($level, $levelOk, true)) {
                    $level = 'all';
                }
                if (!in_array($classType, $classOk, true)) {
                    $classType = 'group';
                }
                $offId = (int) ($off['id'] ?? 0);
                $offPayload = [
                    $locId,
                    $styleId > 0 ? $styleId : null,
                    $styleLabel !== '' ? $styleLabel : null,
                    $age,
                    $level,
                    $classType,
                    trim((string) ($off['schedule_note'] ?? '')) ?: null,
                    $oSort,
                ];
                if ($offId > 0) {
                    $ochk = $db->prepare('SELECT `id` FROM `dance_school_offerings` WHERE `id` = ? AND `location_id` = ?');
                    $ochk->execute([$offId, $locId]);
                    if ($ochk->fetchColumn() === false) {
                        $offId = 0;
                    }
                }
                if ($offId > 0) {
                    $db->prepare('
                        UPDATE `dance_school_offerings`
                        SET `style_id`=?, `style_label`=?, `age_group`=?, `level`=?, `class_type`=?, `schedule_note`=?, `sort_order`=?
                        WHERE `id`=? AND `location_id`=?
                    ')->execute([
                        $offPayload[1], $offPayload[2], $offPayload[3], $offPayload[4],
                        $offPayload[5], $offPayload[6], $offPayload[7], $offId, $locId,
                    ]);
                } else {
                    $db->prepare('
                        INSERT INTO `dance_school_offerings`
                        (`location_id`,`style_id`,`style_label`,`age_group`,`level`,`class_type`,`schedule_note`,`sort_order`)
                        VALUES (?,?,?,?,?,?,?,?)
                    ')->execute($offPayload);
                    $offId = (int) $db->lastInsertId();
                }
                $keepOffIds[] = $offId;
                $oSort++;
            }
            if ($keepOffIds !== []) {
                $in = implode(',', array_fill(0, count($keepOffIds), '?'));
                $params = $keepOffIds;
                $params[] = $locId;
                $db->prepare("DELETE FROM `dance_school_offerings` WHERE `location_id` = ? AND `id` NOT IN ({$in})")->execute(
                    array_merge([$locId], $keepOffIds)
                );
            } else {
                $db->prepare('DELETE FROM `dance_school_offerings` WHERE `location_id` = ?')->execute([$locId]);
            }
            $sort++;
        }

        if ($keepLocIds !== []) {
            $in = implode(',', array_fill(0, count($keepLocIds), '?'));
            $delLoc = $db->prepare("SELECT `id` FROM `dance_school_locations` WHERE `school_id` = ? AND `id` NOT IN ({$in})");
            $delLoc->execute(array_merge([$schoolId], $keepLocIds));
            $toDelete = array_map('intval', $delLoc->fetchAll(PDO::FETCH_COLUMN) ?: []);
            if ($toDelete !== []) {
                $din = implode(',', array_fill(0, count($toDelete), '?'));
                $db->prepare("DELETE FROM `dance_school_offerings` WHERE `location_id` IN ({$din})")->execute($toDelete);
                $db->prepare("DELETE FROM `dance_school_locations` WHERE `school_id` = ? AND `id` IN ({$din})")->execute(
                    array_merge([$schoolId], $toDelete)
                );
                $db->prepare("UPDATE `dance_school_events` SET `location_id` = NULL WHERE `school_id` = ? AND `location_id` IN ({$din})")->execute(
                    array_merge([$schoolId], $toDelete)
                );
            }
        } else {
            $allLoc = $db->prepare('SELECT `id` FROM `dance_school_locations` WHERE `school_id` = ?');
            $allLoc->execute([$schoolId]);
            $toDelete = array_map('intval', $allLoc->fetchAll(PDO::FETCH_COLUMN) ?: []);
            if ($toDelete !== []) {
                $din = implode(',', array_fill(0, count($toDelete), '?'));
                $db->prepare("DELETE FROM `dance_school_offerings` WHERE `location_id` IN ({$din})")->execute($toDelete);
            }
            $db->prepare('DELETE FROM `dance_school_locations` WHERE `school_id` = ?')->execute([$schoolId]);
            $db->prepare('UPDATE `dance_school_events` SET `location_id` = NULL WHERE `school_id` = ?')->execute([$schoolId]);
        }

        return ['ok' => true];
    } catch (Throwable $ex) {
        error_log('dance_school_sync_locations: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Helyszínek mentése sikertelen.'];
    }
}

/**
 * @param list<array<string, mixed>> $events
 */
function dance_school_sync_events(PDO $db, int $schoolId, array $events): array
{
    if ($schoolId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen iskola.'];
    }
    $typeOk = array_keys(dance_school_event_type_labels());
    try {
        $keepIds = [];
        foreach ($events as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $title = trim((string) ($ev['title'] ?? ''));
            $starts = trim((string) ($ev['starts_at'] ?? ''));
            if ($title === '' || $starts === '') {
                continue;
            }
            $startsNorm = str_replace('T', ' ', $starts);
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $startsNorm)) {
                $startsNorm .= ':00';
            }
            $ends = trim((string) ($ev['ends_at'] ?? ''));
            $endsNorm = null;
            if ($ends !== '') {
                $endsNorm = str_replace('T', ' ', $ends);
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $endsNorm)) {
                    $endsNorm .= ':00';
                }
            }
            $eventType = (string) ($ev['event_type'] ?? 'workshop');
            if (!in_array($eventType, $typeOk, true)) {
                $eventType = 'workshop';
            }
            $locId = (int) ($ev['location_id'] ?? 0);
            if ($locId > 0) {
                $lchk = $db->prepare('SELECT 1 FROM `dance_school_locations` WHERE `id` = ? AND `school_id` = ?');
                $lchk->execute([$locId, $schoolId]);
                if ($lchk->fetchColumn() === false) {
                    $locId = 0;
                }
            }
            [$regNorm, $regErr] = events_normalize_safe_url((string) ($ev['registration_url'] ?? ''), true);
            $reg = ($regErr === null && $regNorm !== null) ? $regNorm : '';
            $evId = (int) ($ev['id'] ?? 0);
            $payload = [
                $schoolId,
                $locId > 0 ? $locId : null,
                $title,
                trim((string) ($ev['description'] ?? '')) ?: null,
                $eventType,
                $startsNorm,
                $endsNorm,
                trim((string) ($ev['price_info'] ?? '')) ?: null,
                $reg !== '' ? $reg : null,
                !empty($ev['is_published']) ? 1 : 0,
            ];
            if ($evId > 0) {
                $chk = $db->prepare('SELECT `id` FROM `dance_school_events` WHERE `id` = ? AND `school_id` = ?');
                $chk->execute([$evId, $schoolId]);
                if ($chk->fetchColumn() === false) {
                    $evId = 0;
                }
            }
            if ($evId > 0) {
                $db->prepare('
                    UPDATE `dance_school_events`
                    SET `location_id`=?, `title`=?, `description`=?, `event_type`=?, `starts_at`=?, `ends_at`=?,
                        `price_info`=?, `registration_url`=?, `is_published`=?
                    WHERE `id`=? AND `school_id`=?
                ')->execute([
                    $payload[1], $payload[2], $payload[3], $payload[4], $payload[5],
                    $payload[6], $payload[7], $payload[8], $payload[9], $evId, $schoolId,
                ]);
            } else {
                $db->prepare('
                    INSERT INTO `dance_school_events`
                    (`school_id`,`location_id`,`title`,`description`,`event_type`,`starts_at`,`ends_at`,`price_info`,`registration_url`,`is_published`)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                ')->execute($payload);
                $evId = (int) $db->lastInsertId();
            }
            $keepIds[] = $evId;
        }
        if ($keepIds !== []) {
            $in = implode(',', array_fill(0, count($keepIds), '?'));
            $db->prepare("DELETE FROM `dance_school_events` WHERE `school_id` = ? AND `id` NOT IN ({$in})")->execute(
                array_merge([$schoolId], $keepIds)
            );
        } else {
            $db->prepare('DELETE FROM `dance_school_events` WHERE `school_id` = ?')->execute([$schoolId]);
        }

        return ['ok' => true];
    } catch (Throwable $ex) {
        error_log('dance_school_sync_events: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Naptár mentése sikertelen.'];
    }
}

/**
 * @param list<array<string, mixed>> $teachers
 */
function dance_school_sync_teachers(PDO $db, int $schoolId, array $teachers): array
{
    if ($schoolId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen iskola.'];
    }
    $roleOk = array_keys(dance_school_teacher_role_labels());
    try {
        $db->prepare('DELETE FROM `dance_school_teachers` WHERE `school_id` = ?')->execute([$schoolId]);
        $ins = $db->prepare('
            INSERT INTO `dance_school_teachers` (`school_id`,`tag_id`,`role_type`,`role_note`,`sort_order`)
            VALUES (?,?,?,?,?)
        ');
        $sort = 0;
        $seen = [];
        foreach ($teachers as $row) {
            if (!is_array($row)) {
                continue;
            }
            $tagId = (int) ($row['tag_id'] ?? 0);
            if ($tagId <= 0) {
                continue;
            }
            $role = (string) ($row['role_type'] ?? 'teacher');
            if (!in_array($role, $roleOk, true)) {
                $role = 'teacher';
            }
            $key = $tagId . ':' . $role;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $note = $role === 'guest' || $role === 'owner'
                ? (trim((string) ($row['role_note'] ?? '')) ?: null)
                : (trim((string) ($row['role_note'] ?? '')) ?: null);
            $ins->execute([$schoolId, $tagId, $role, $note, $sort]);
            $sort++;
        }

        return ['ok' => true];
    } catch (Throwable $ex) {
        error_log('dance_school_sync_teachers: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Tanárok mentése sikertelen.'];
    }
}

/**
 * @return list<array{id:int,name:string}>
 */
function dance_schools_selectable_list(PDO $db): array
{
    if (!dance_schools_tables_ready($db)) {
        return [];
    }
    $rows = $db->query('SELECT `id`, `name` FROM `dance_schools` WHERE `is_active` = 1 ORDER BY `name` ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $out = [];
    foreach ($rows as $row) {
        $out[] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
    }

    return $out;
}

/**
 * Helyszín sorok POST-ból.
 *
 * @return list<array<string, mixed>>
 */
function dance_school_locations_from_post(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $offerings = [];
        if (isset($row['offerings']) && is_array($row['offerings'])) {
            foreach ($row['offerings'] as $off) {
                if (!is_array($off)) {
                    continue;
                }
                $offerings[] = [
                    'id' => (int) ($off['id'] ?? 0),
                    'style_id' => (int) ($off['style_id'] ?? 0),
                    'style_label' => trim((string) ($off['style_label'] ?? '')),
                    'age_group' => (string) ($off['age_group'] ?? 'adult'),
                    'level' => (string) ($off['level'] ?? 'all'),
                    'class_type' => (string) ($off['class_type'] ?? 'group'),
                    'schedule_note' => trim((string) ($off['schedule_note'] ?? '')),
                ];
            }
        }
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => trim((string) ($row['name'] ?? '')),
            'address' => trim((string) ($row['address'] ?? '')),
            'city' => trim((string) ($row['city'] ?? '')),
            'postal_code' => trim((string) ($row['postal_code'] ?? '')),
            'country' => trim((string) ($row['country'] ?? 'Magyarország')),
            'latitude' => trim((string) ($row['latitude'] ?? '')),
            'longitude' => trim((string) ($row['longitude'] ?? '')),
            'google_maps_url' => trim((string) ($row['google_maps_url'] ?? '')),
            'notes' => trim((string) ($row['notes'] ?? '')),
            'is_active' => !empty($row['is_active']) ? 1 : 0,
            'offerings' => $offerings,
        ];
    }

    return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function dance_school_events_from_post(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'location_id' => (int) ($row['location_id'] ?? 0),
            'title' => trim((string) ($row['title'] ?? '')),
            'description' => trim((string) ($row['description'] ?? '')),
            'event_type' => (string) ($row['event_type'] ?? 'workshop'),
            'starts_at' => trim((string) ($row['starts_at'] ?? '')),
            'ends_at' => trim((string) ($row['ends_at'] ?? '')),
            'price_info' => trim((string) ($row['price_info'] ?? '')),
            'registration_url' => trim((string) ($row['registration_url'] ?? '')),
            'is_published' => !empty($row['is_published']) ? 1 : 0,
        ];
    }

    return $out;
}

/**
 * @return list<array{tag_id:int,role_type:string,role_note:string}>
 */
function dance_school_teachers_from_post(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $tagId = (int) ($row['tag_id'] ?? 0);
        if ($tagId <= 0) {
            continue;
        }
        $out[] = [
            'tag_id' => $tagId,
            'role_type' => (string) ($row['role_type'] ?? 'teacher'),
            'role_note' => trim((string) ($row['role_note'] ?? '')),
        ];
    }

    return $out;
}
