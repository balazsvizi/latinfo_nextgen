<?php
declare(strict_types=1);

require_once __DIR__ . '/dance_schools_schema.php';
require_once __DIR__ . '/tag_type.php';
require_once __DIR__ . '/tag_profile.php';
require_once __DIR__ . '/slug.php';
require_once __DIR__ . '/djs_admin.php';
require_once __DIR__ . '/html_security.php';

/**
 * Tánctanárok – `tanar` típusú címkék + dance_teacher_profiles bővítmény.
 */

const DANCE_TEACHER_TAG_TYPE = 'tanar';

function dance_teachers_ensure_type_label(PDO $db): void
{
    if (!events_tag_types_registry_table_available($db)) {
        return;
    }
    try {
        $st = $db->prepare('UPDATE `events_tag_types` SET `name` = ? WHERE `code` = ? AND `name` IN (?, ?)');
        $st->execute(['Tánctanár', DANCE_TEACHER_TAG_TYPE, 'Tanár', 'Tánctanár']);
        if ($st->rowCount() > 0) {
            events_tag_types_clear_cache();
        }
        // Ha még 'Tanár' a név, frissítsük mindenképp Tánctanárra.
        $st2 = $db->prepare('UPDATE `events_tag_types` SET `name` = ? WHERE `code` = ? AND `name` = ?');
        $st2->execute(['Tánctanár', DANCE_TEACHER_TAG_TYPE, 'Tanár']);
        if ($st2->rowCount() > 0) {
            events_tag_types_clear_cache();
        }
    } catch (Throwable) {
        // ignore
    }
}

function dance_teacher_type_id(PDO $db): ?int
{
    events_tag_types_ensure_seeded($db);
    dance_teachers_ensure_type_label($db);

    return events_tag_type_id_by_code($db, DANCE_TEACHER_TAG_TYPE);
}

/**
 * @return array{
 *   offers_private_lessons:int,
 *   private_lesson_note:string,
 *   achievements:string,
 *   certifications:string,
 *   years_experience:?int,
 *   city:string,
 *   teaches_online:int,
 *   available_for_events:int,
 *   bio_short:string,
 *   style_ids:list<int>
 * }
 */
function dance_teacher_profile_empty(): array
{
    return [
        'offers_private_lessons' => 0,
        'private_lesson_note' => '',
        'achievements' => '',
        'certifications' => '',
        'years_experience' => null,
        'city' => '',
        'teaches_online' => 0,
        'available_for_events' => 0,
        'bio_short' => '',
        'style_ids' => [],
    ];
}

/**
 * @return array{
 *   offers_private_lessons:int,
 *   private_lesson_note:string,
 *   achievements:string,
 *   certifications:string,
 *   years_experience:?int,
 *   city:string,
 *   teaches_online:int,
 *   available_for_events:int,
 *   bio_short:string,
 *   style_ids:list<int>
 * }
 */
function dance_teacher_profile_load(PDO $db, int $tagId): array
{
    $empty = dance_teacher_profile_empty();
    if ($tagId <= 0 || !dance_schools_tables_ready($db)) {
        return $empty;
    }
    $st = $db->prepare('SELECT * FROM `dance_teacher_profiles` WHERE `tag_id` = ? LIMIT 1');
    $st->execute([$tagId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $empty['offers_private_lessons'] = !empty($row['offers_private_lessons']) ? 1 : 0;
        $empty['private_lesson_note'] = (string) ($row['private_lesson_note'] ?? '');
        $empty['achievements'] = (string) ($row['achievements'] ?? '');
        $empty['certifications'] = (string) ($row['certifications'] ?? '');
        $yrs = $row['years_experience'] ?? null;
        $empty['years_experience'] = $yrs !== null && $yrs !== '' ? (int) $yrs : null;
        $empty['city'] = (string) ($row['city'] ?? '');
        $empty['teaches_online'] = !empty($row['teaches_online']) ? 1 : 0;
        $empty['available_for_events'] = !empty($row['available_for_events']) ? 1 : 0;
        $empty['bio_short'] = (string) ($row['bio_short'] ?? '');
    }
    try {
        $stStyles = $db->prepare('SELECT `style_id` FROM `dance_teacher_styles` WHERE `tag_id` = ? ORDER BY `style_id` ASC');
        $stStyles->execute([$tagId]);
        $empty['style_ids'] = array_map('intval', $stStyles->fetchAll(PDO::FETCH_COLUMN) ?: []);
    } catch (Throwable) {
        $empty['style_ids'] = [];
    }

    return $empty;
}

/**
 * @return array{0: array<string, mixed>, 1: ?string}
 */
function dance_teacher_profile_from_post(array $post): array
{
    $profile = dance_teacher_profile_empty();
    $profile['offers_private_lessons'] = !empty($post['offers_private_lessons']) ? 1 : 0;
    $profile['private_lesson_note'] = trim((string) ($post['private_lesson_note'] ?? ''));
    $profile['achievements'] = events_sanitize_html_fragment((string) ($post['achievements'] ?? ''));
    $profile['certifications'] = trim(strip_tags((string) ($post['certifications'] ?? '')));
    $yrs = trim((string) ($post['years_experience'] ?? ''));
    if ($yrs !== '') {
        if (!ctype_digit($yrs) || (int) $yrs > 80) {
            return [$profile, 'Az oktatási tapasztalat (év) érvénytelen.'];
        }
        $profile['years_experience'] = (int) $yrs;
    }
    $profile['city'] = trim((string) ($post['city'] ?? ''));
    $profile['teaches_online'] = !empty($post['teaches_online']) ? 1 : 0;
    $profile['available_for_events'] = !empty($post['available_for_events']) ? 1 : 0;
    $bio = trim(strip_tags((string) ($post['bio_short'] ?? '')));
    if (mb_strlen($bio) > 500) {
        return [$profile, 'A rövid bemutatkozás legfeljebb 500 karakter.'];
    }
    $profile['bio_short'] = $bio;
    $styleIds = [];
    if (isset($post['style_ids']) && is_array($post['style_ids'])) {
        foreach ($post['style_ids'] as $sid) {
            $id = (int) $sid;
            if ($id > 0) {
                $styleIds[$id] = $id;
            }
        }
    }
    $profile['style_ids'] = array_values($styleIds);

    return [$profile, null];
}

/**
 * @param array<string, mixed> $profile
 */
function dance_teacher_profile_save(PDO $db, int $tagId, array $profile): ?string
{
    if ($tagId <= 0) {
        return 'Érvénytelen tanár azonosító.';
    }
    if (!dance_schools_ensure_schema($db)) {
        return 'A tánctanár táblák nem elérhetők.';
    }
    try {
        $db->prepare('
            INSERT INTO `dance_teacher_profiles`
            (`tag_id`,`offers_private_lessons`,`private_lesson_note`,`achievements`,`certifications`,
             `years_experience`,`city`,`teaches_online`,`available_for_events`,`bio_short`)
            VALUES (?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                `offers_private_lessons` = VALUES(`offers_private_lessons`),
                `private_lesson_note` = VALUES(`private_lesson_note`),
                `achievements` = VALUES(`achievements`),
                `certifications` = VALUES(`certifications`),
                `years_experience` = VALUES(`years_experience`),
                `city` = VALUES(`city`),
                `teaches_online` = VALUES(`teaches_online`),
                `available_for_events` = VALUES(`available_for_events`),
                `bio_short` = VALUES(`bio_short`)
        ')->execute([
            $tagId,
            !empty($profile['offers_private_lessons']) ? 1 : 0,
            trim((string) ($profile['private_lesson_note'] ?? '')) ?: null,
            (string) ($profile['achievements'] ?? '') ?: null,
            trim((string) ($profile['certifications'] ?? '')) ?: null,
            $profile['years_experience'] ?? null,
            trim((string) ($profile['city'] ?? '')) ?: null,
            !empty($profile['teaches_online']) ? 1 : 0,
            !empty($profile['available_for_events']) ? 1 : 0,
            trim((string) ($profile['bio_short'] ?? '')) ?: null,
        ]);

        $db->prepare('DELETE FROM `dance_teacher_styles` WHERE `tag_id` = ?')->execute([$tagId]);
        $styleIds = $profile['style_ids'] ?? [];
        if (is_array($styleIds) && $styleIds !== []) {
            $ins = $db->prepare('INSERT INTO `dance_teacher_styles` (`tag_id`, `style_id`) VALUES (?, ?)');
            foreach ($styleIds as $sid) {
                $sid = (int) $sid;
                if ($sid > 0) {
                    $ins->execute([$tagId, $sid]);
                }
            }
        }

        return null;
    } catch (Throwable $ex) {
        error_log('dance_teacher_profile_save: ' . $ex->getMessage());

        return 'Tánctanár profil mentése sikertelen.';
    }
}

function dance_teacher_set_slug(PDO $db, int $tagId, string $name, string $slugInput): ?string
{
    if (!events_tags_slug_column_available($db)) {
        events_tags_ensure_slug_column($db);
    }
    if (!events_tags_slug_column_available($db)) {
        return null;
    }
    $base = $slugInput !== '' ? events_dj_slugify($slugInput) : events_dj_slugify($name);
    $slug = events_ensure_unique_tag_slug($db, $base, $tagId);
    $db->prepare('UPDATE `events_tags` SET `slug` = ? WHERE `id` = ?')->execute([$slug, $tagId]);

    return $slug;
}

/**
 * @return array{f_q:string,order:string,dir_param:string,get_params:array<string,string>}
 */
function dance_teachers_admin_filters_from_request(): array
{
    $f_q = trim((string) ($_GET['f_q'] ?? ''));
    $allowed = ['id', 'name', 'slug', 'city', 'private', 'schools', 'views', 'photo'];
    if (isset($_GET['order']) && in_array((string) $_GET['order'], $allowed, true)) {
        $order = (string) $_GET['order'];
        $dir_param = isset($_GET['dir']) && strtolower((string) $_GET['dir']) === 'asc' ? 'asc' : 'desc';
    } else {
        $order = 'name';
        $dir_param = 'asc';
    }
    $get_params = [];
    if ($f_q !== '') {
        $get_params['f_q'] = $f_q;
    }

    return [
        'f_q' => $f_q,
        'order' => $order,
        'dir_param' => $dir_param,
        'get_params' => $get_params,
    ];
}

/**
 * @param array{f_q:string,order:string,dir_param:string} $filters
 * @return list<array<string, mixed>>
 */
function dance_teachers_admin_fetch(PDO $db, array $filters, ?int $listLimit = null): array
{
    if (!events_tags_tables_available($db) || !events_tag_types_tables_available($db)) {
        return [];
    }
    dance_schools_ensure_schema($db);
    events_tags_ensure_slug_column($db);
    events_tags_ensure_profile_columns($db);
    $typeId = dance_teacher_type_id($db);
    if ($typeId === null || $typeId <= 0) {
        return [];
    }

    $where = ['l.`tag_type_id` = ?'];
    $params = [$typeId];
    $f_q = trim((string) ($filters['f_q'] ?? ''));
    if ($f_q !== '') {
        if (ctype_digit($f_q)) {
            $where[] = '(t.`id` = ? OR t.`name` LIKE ? OR t.`slug` LIKE ? OR COALESCE(p.`city`, \'\') LIKE ?)';
            $params[] = (int) $f_q;
            $like = '%' . $f_q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        } else {
            $where[] = '(t.`name` LIKE ? OR t.`slug` LIKE ? OR COALESCE(p.`city`, \'\') LIKE ?)';
            $like = '%' . $f_q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
    }
    $whereSql = implode(' AND ', $where);
    $order = (string) ($filters['order'] ?? 'name');
    $dir = strtoupper((string) ($filters['dir_param'] ?? 'asc')) === 'DESC' ? 'DESC' : 'ASC';
    $hasPhotoCol = events_tags_profile_columns_available($db);
    $orderSql = match ($order) {
        'id' => 't.`id` ' . $dir,
        'slug' => 't.`slug` ' . $dir . ', t.`name` ASC',
        'city' => 'COALESCE(p.`city`, \'\') ' . $dir . ', t.`name` ASC',
        'private' => 'COALESCE(p.`offers_private_lessons`, 0) ' . $dir . ', t.`name` ASC',
        'schools' => 'COALESCE(sch.`school_count`, 0) ' . $dir . ', t.`name` ASC',
        'views' => 'COALESCE(vw.`view_count`, 0) ' . $dir . ', t.`name` ASC',
        'photo' => $hasPhotoCol
            ? '(CASE WHEN COALESCE(t.`photo_url`, \'\') <> \'\' OR COALESCE(t.`logo_url`, \'\') <> \'\' THEN 1 ELSE 0 END) ' . $dir . ', t.`name` ASC'
            : 't.`name` ' . $dir,
        default => 't.`name` ' . $dir . ', t.`id` ASC',
    };
    $slugSelect = events_tags_slug_column_available($db) ? 't.`slug`' : 'NULL AS `slug`';
    $photoSelect = $hasPhotoCol ? 't.`photo_url`, t.`logo_url`' : 'NULL AS `photo_url`, NULL AS `logo_url`';
    $limitSql = $listLimit === null ? '' : ' LIMIT ' . (int) $listLimit;

    $st = $db->prepare("
        SELECT t.`id`, t.`name`, {$slugSelect}, {$photoSelect},
               p.`city`, p.`offers_private_lessons`, p.`teaches_online`, p.`available_for_events`,
               p.`years_experience`, p.`bio_short`,
               COALESCE(sch.`school_count`, 0) AS `school_count`,
               COALESCE(vw.`view_count`, 0) AS `view_count`
        FROM `events_tags` t
        INNER JOIN `events_tag_type_links` l ON l.`tag_id` = t.`id`
        LEFT JOIN `dance_teacher_profiles` p ON p.`tag_id` = t.`id`
        LEFT JOIN (
            SELECT `tag_id`, COUNT(DISTINCT `school_id`) AS `school_count`
            FROM `dance_school_teachers`
            GROUP BY `tag_id`
        ) sch ON sch.`tag_id` = t.`id`
        LEFT JOIN (
            SELECT `entity_id`, COUNT(*) AS `view_count`
            FROM `dance_entity_views`
            WHERE `entity_type` = 'teacher' AND `metric` = 'page_view' AND `is_bot` = 0
            GROUP BY `entity_id`
        ) vw ON vw.`entity_id` = t.`id`
        WHERE {$whereSql}
        ORDER BY {$orderSql}
        {$limitSql}
    ");
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function dance_teachers_admin_total_count(PDO $db): int
{
    $typeId = dance_teacher_type_id($db);
    if ($typeId === null || $typeId <= 0) {
        return 0;
    }
    $st = $db->prepare('SELECT COUNT(*) FROM `events_tag_type_links` WHERE `tag_type_id` = ?');
    $st->execute([$typeId]);

    return (int) $st->fetchColumn();
}

/**
 * @return list<array{id:int,name:string}>
 */
function dance_teachers_selectable_list(PDO $db): array
{
    if (!events_tags_tables_available($db) || !events_tag_types_tables_available($db)) {
        return [];
    }
    $typeId = dance_teacher_type_id($db);
    if ($typeId === null || $typeId <= 0) {
        return [];
    }
    $st = $db->prepare('
        SELECT t.`id`, t.`name`
        FROM `events_tags` t
        INNER JOIN `events_tag_type_links` l ON l.`tag_id` = t.`id`
        WHERE l.`tag_type_id` = ?
        ORDER BY t.`name` ASC
    ');
    $st->execute([$typeId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $out[] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
    }

    return $out;
}

/**
 * @return list<array{id:int,name:string}>
 */
function dance_teacher_schools(PDO $db, int $tagId): array
{
    if ($tagId <= 0 || !dance_schools_tables_ready($db)) {
        return [];
    }
    $st = $db->prepare('
        SELECT DISTINCT s.`id`, s.`name`
        FROM `dance_school_teachers` dst
        INNER JOIN `dance_schools` s ON s.`id` = dst.`school_id`
        WHERE dst.`tag_id` = ?
        ORDER BY s.`name` ASC
    ');
    $st->execute([$tagId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $out[] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
    }

    return $out;
}

function dance_teacher_delete(PDO $db, int $tagId): array
{
    if ($tagId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen azonosító.'];
    }
    try {
        $stUse = $db->prepare('SELECT COUNT(*) FROM `events_calendar_event_tags` WHERE `tag_id` = ?');
        $stUse->execute([$tagId]);
        $useCnt = (int) $stUse->fetchColumn();
        if ($useCnt > 0) {
            return ['ok' => false, 'error' => 'A tanár nem törölhető, mert ' . $useCnt . ' eseményhez van rendelve.'];
        }
        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM `dance_school_teachers` WHERE `tag_id` = ?')->execute([$tagId]);
            $db->prepare('DELETE FROM `dance_teacher_styles` WHERE `tag_id` = ?')->execute([$tagId]);
            $db->prepare('DELETE FROM `dance_teacher_profiles` WHERE `tag_id` = ?')->execute([$tagId]);
            $db->prepare("DELETE FROM `dance_entity_views` WHERE `entity_type` = 'teacher' AND `entity_id` = ?")->execute([$tagId]);
            $db->prepare('DELETE FROM `nextgen_partner_teachers` WHERE `tag_id` = ?')->execute([$tagId]);
        } catch (Throwable) {
            // kapcsolódó táblák opcionálisak
        }
        if (events_tag_types_tables_available($db)) {
            $db->prepare('DELETE FROM `events_tag_type_links` WHERE `tag_id` = ?')->execute([$tagId]);
        }
        $db->prepare('DELETE FROM `events_tags` WHERE `id` = ?')->execute([$tagId]);
        $db->commit();

        return ['ok' => true];
    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('dance_teacher_delete: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Törlés sikertelen.'];
    }
}
