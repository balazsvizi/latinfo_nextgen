<?php
declare(strict_types=1);

/**
 * Eseményenkénti kapcsoló: a szervező a partnerportálon szerkesztheti az eseményt.
 * A mentés az events_request / html / változás függvényeire épül; a hívó töltse be őket.
 */
function events_organizer_edit_ensure_schema(PDO $db): bool
{
    static $done = null;
    if ($done !== null) {
        return $done;
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `events_calendar_events` LIKE 'organizer_can_edit'");
        if ($stmt !== false && $stmt->fetch(PDO::FETCH_ASSOC)) {
            $done = true;

            return true;
        }
        $db->exec("
            ALTER TABLE `events_calendar_events`
            ADD COLUMN `organizer_can_edit` TINYINT(1) NOT NULL DEFAULT 0
                COMMENT '1 = a szervező a partnerportálon szerkesztheti'
        ");
        $done = true;

        return true;
    } catch (Throwable $ex) {
        error_log('events_organizer_edit_ensure_schema: ' . $ex->getMessage());
        $done = false;

        return false;
    }
}

function events_organizer_can_edit_from_row(array $event): bool
{
    return (int) ($event['organizer_can_edit'] ?? 0) === 1
        && (string) ($event['event_status'] ?? '') !== 'trash';
}

/**
 * Partnerportálos mentés. A slug, a szervezők, a pénzügy és a „szervező szerkesztheti”
 * kapcsoló változatlan marad. A jobb oldali admin mezők (státusz, kategória, címke,
 * stílus, borítókép, változás) menthetők.
 *
 * @param array<string, mixed> $current
 * @param array<string, mixed> $post
 * @return array{ok: true, row: array<string, mixed>}|array{ok: false, error: string, row: array<string, mixed>}
 */
function events_organizer_edit_apply(PDO $db, array $current, array $post): array
{
    $row = $current;
    $eventId = (int) ($current['id'] ?? 0);
    $row['category_ids'] = events_category_ids_from_post();
    $row['tag_ids'] = events_tags_tables_available($db) ? events_tag_ids_from_post() : [];
    $row['main_style_ids'] = events_styles_tables_available($db) ? events_main_style_ids_from_post() : [];
    $row['supplementary_style_ids'] = events_styles_tables_available($db) ? events_supplementary_style_ids_from_post() : [];
    $postedImageUrl = trim((string) ($post['event_featured_image_url'] ?? ''));
    $postedPick = trim((string) ($post['event_featured_image_pick'] ?? ''));
    if ($postedImageUrl !== '') {
        $row['event_featured_image_url'] = $postedImageUrl;
    } elseif ($postedPick !== '' && events_eventpics_is_safe_filename($postedPick)) {
        $row['event_featured_image_url'] = events_eventpics_build_web_path($postedPick);
    }
    if ($eventId <= 0 || !events_organizer_can_edit_from_row($current)) {
        return ['ok' => false, 'error' => 'Ezt az eseményt nem szerkesztheted.', 'row' => $row];
    }

    $name = trim((string) ($post['event_name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 500) {
        $row['event_name'] = $name;

        return ['ok' => false, 'error' => 'Az esemény neve kötelező, legfeljebb 500 karakter.', 'row' => $row];
    }
    $row['event_name'] = $name;

    $postedStatus = trim((string) ($post['event_status'] ?? ''));
    $currentStatus = (string) ($current['event_status'] ?? '');
    $statusKept = $postedStatus === $currentStatus && $currentStatus !== '' && $currentStatus !== 'trash';
    if (!$statusKept && !in_array($postedStatus, events_organizer_edit_statuses(), true)) {
        return ['ok' => false, 'error' => 'Érvénytelen státusz.', 'row' => $row];
    }
    $row['event_status'] = $postedStatus;
    $status = $postedStatus;

    $content = events_sanitize_html_fragment((string) ($post['event_content'] ?? ''));
    $row['event_content'] = $content;
    $contentPlain = trim(strip_tags($content));
    if ($contentPlain === '' && !events_is_preliminary_post_status($status)) {
        return ['ok' => false, 'error' => 'A leírás kötelező.', 'row' => $row];
    }

    $startDate = trim((string) ($post['event_start_date'] ?? ''));
    $startTime = trim((string) ($post['event_start_time'] ?? ''));
    $endDate = trim((string) ($post['event_end_date'] ?? ''));
    $endTime = trim((string) ($post['event_end_time'] ?? ''));
    if ($endDate === '' && $startDate !== '') {
        $endDate = $startDate;
    }
    $allDay = isset($post['event_allday']) ? 1 : 0;
    if ($allDay === 1) {
        if ($startDate !== '') {
            $startTime = '00:00';
        }
        if ($endDate !== '') {
            $endTime = '23:59';
        }
    }
    $row['event_allday'] = $allDay;
    $row['event_start'] = events_build_event_datetime($startDate, $startTime);
    $row['event_end'] = events_build_event_datetime($endDate, $endTime);
    if ($startDate === '' || $row['event_start'] === null) {
        return ['ok' => false, 'error' => 'A kezdő dátum kötelező és érvényes legyen.', 'row' => $row];
    }
    if ($row['event_end'] === null) {
        return ['ok' => false, 'error' => 'A záró dátum vagy idő érvénytelen.', 'row' => $row];
    }
    if ($row['event_end'] < $row['event_start']) {
        return ['ok' => false, 'error' => 'A záró időpont nem lehet korábbi a kezdőnél.', 'row' => $row];
    }

    [$costFrom, $costFromErr] = events_organizer_edit_parse_cost((string) ($post['event_cost_from'] ?? ''));
    if ($costFromErr !== null) {
        return ['ok' => false, 'error' => $costFromErr, 'row' => $row];
    }
    [$costTo, $costToErr] = events_organizer_edit_parse_cost((string) ($post['event_cost_to'] ?? ''));
    if ($costToErr !== null) {
        return ['ok' => false, 'error' => $costToErr, 'row' => $row];
    }
    $row['event_cost_from'] = $costFrom;
    $row['event_cost_to'] = $costTo;

    [$eventUrl, $eventUrlErr] = events_normalize_safe_url((string) ($post['event_url'] ?? ''), true);
    if ($eventUrlErr !== null) {
        return ['ok' => false, 'error' => $eventUrlErr, 'row' => $row];
    }
    $row['event_url'] = $eventUrl;
    $publishUrlErr = events_validate_publish_requires_event_url($row);
    if ($publishUrlErr !== null) {
        return ['ok' => false, 'error' => $publishUrlErr, 'row' => $row];
    }

    $venuePosted = trim((string) ($post['venue_id'] ?? ''));
    if ($venuePosted === '') {
        $row['venue_id'] = null;
    } else {
        $venueId = filter_var($venuePosted, FILTER_VALIDATE_INT);
        if ($venueId === false || (int) $venueId <= 0) {
            return ['ok' => false, 'error' => 'Érvénytelen helyszín.', 'row' => $row];
        }
        $venueStmt = $db->prepare('SELECT `id` FROM `events_venues` WHERE `id` = ? LIMIT 1');
        $venueStmt->execute([(int) $venueId]);
        if (!$venueStmt->fetchColumn()) {
            return ['ok' => false, 'error' => 'A kiválasztott helyszín nem található.', 'row' => $row];
        }
        $row['venue_id'] = (int) $venueId;
    }

    $taxonomyErr = events_organizer_edit_unknown_ids($db, 'events_categories', $row['category_ids']);
    if ($taxonomyErr !== null) {
        return ['ok' => false, 'error' => 'A kiválasztott kategóriák között érvénytelen elem van.', 'row' => $row];
    }
    if (events_tags_tables_available($db)) {
        $taxonomyErr = events_organizer_edit_unknown_ids($db, 'events_tags', $row['tag_ids']);
        if ($taxonomyErr !== null) {
            return ['ok' => false, 'error' => 'A kiválasztott címkék között érvénytelen elem van.', 'row' => $row];
        }
    }
    if (events_styles_tables_available($db)) {
        $taxonomyErr = events_organizer_edit_unknown_ids($db, 'events_styles', $row['main_style_ids']);
        if ($taxonomyErr !== null) {
            return ['ok' => false, 'error' => 'A kiválasztott fő stílusok között érvénytelen elem van.', 'row' => $row];
        }
        $taxonomyErr = events_organizer_edit_unknown_ids($db, 'events_styles', $row['supplementary_style_ids']);
        if ($taxonomyErr !== null) {
            return ['ok' => false, 'error' => 'A kiválasztott kiegészítő stílusok között érvénytelen elem van.', 'row' => $row];
        }
    }

    [$featUrlInput, $featUrlErr] = events_normalize_featured_image_url((string) ($post['event_featured_image_url'] ?? ''));
    if ($featUrlErr !== null) {
        return ['ok' => false, 'error' => $featUrlErr, 'row' => $row];
    }
    [$featPickPath, $featPickErr] = events_eventpics_normalize_selected((string) ($post['event_featured_image_pick'] ?? ''));
    if ($featPickErr !== null) {
        return ['ok' => false, 'error' => $featPickErr, 'row' => $row];
    }
    $uploadFile = $_FILES['event_featured_image_upload'] ?? null;
    [$featUploadPath, $featUploadErr] = events_eventpics_handle_upload(is_array($uploadFile) ? $uploadFile : null);
    if ($featUploadErr !== null) {
        return ['ok' => false, 'error' => $featUploadErr, 'row' => $row];
    }
    $featUrlExternal = events_featured_image_external_url($featUrlInput);
    $row['event_featured_image_url'] = $featUrlExternal ?? $featUploadPath ?? $featPickPath;
    $row['event_published_at'] = events_slug_published_at_for_save(
        isset($current['event_published_at']) ? (string) $current['event_published_at'] : null,
        (string) $row['event_status']
    );

    $changeActive = isset($post['event_change_active']);
    if ($changeActive) {
        $changeType = trim((string) ($post['event_change_type'] ?? ''));
        if (!events_event_change_is_valid_type($changeType)) {
            return ['ok' => false, 'error' => 'Változás esetén válaszd ki a típust (Elmarad vagy Változás).', 'row' => $row];
        }
        $changeNote = events_event_change_normalize_note((string) ($post['event_change_note'] ?? ''));
        $row['event_change_active'] = 1;
        $row['event_change_type'] = $changeType;
        $row['event_change_note'] = $changeNote !== '' ? $changeNote : null;
    } else {
        $row['event_change_active'] = 0;
        $row['event_change_type'] = null;
        $row['event_change_note'] = null;
    }

    try {
        $db->beginTransaction();
        $upd = $db->prepare('
            UPDATE `events_calendar_events` SET
                event_name = ?,
                event_content = ?,
                event_status = ?,
                event_published_at = ?,
                event_start = ?,
                event_end = ?,
                event_allday = ?,
                event_cost_from = ?,
                event_cost_to = ?,
                event_url = ?,
                event_featured_image_url = ?,
                venue_id = ?,
                event_change_active = ?,
                event_change_type = ?,
                event_change_note = ?
            WHERE id = ?
              AND organizer_can_edit = 1
              AND event_status <> \'trash\'
        ');
        $upd->execute([
            $row['event_name'],
            $row['event_content'],
            $row['event_status'],
            $row['event_published_at'],
            $row['event_start'],
            $row['event_end'],
            $row['event_allday'],
            $row['event_cost_from'],
            $row['event_cost_to'],
            $row['event_url'],
            $row['event_featured_image_url'] ?? null,
            $row['venue_id'],
            $row['event_change_active'],
            $row['event_change_type'],
            $row['event_change_note'],
            $eventId,
        ]);
        if ($upd->rowCount() < 1) {
            $check = $db->prepare('
                SELECT `id` FROM `events_calendar_events`
                WHERE `id` = ? AND `organizer_can_edit` = 1 AND `event_status` <> \'trash\'
                LIMIT 1
            ');
            $check->execute([$eventId]);
            if (!$check->fetchColumn()) {
                $db->rollBack();

                return ['ok' => false, 'error' => 'Ezt az eseményt nem szerkesztheted.', 'row' => $row];
            }
        }
        events_save_event_categories($db, $eventId, $row['category_ids']);
        if (events_tags_tables_available($db)) {
            events_save_event_tags($db, $eventId, $row['tag_ids']);
        }
        if (events_styles_tables_available($db)) {
            events_save_event_main_styles($db, $eventId, $row['main_style_ids']);
            events_save_event_supplementary_styles($db, $eventId, $row['supplementary_style_ids']);
        }
        $db->commit();

        return ['ok' => true, 'row' => $row];
    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('events_organizer_edit_apply: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Mentési hiba történt. Kérlek próbáld újra.', 'row' => $row];
    }
}

/**
 * @return list<string>
 */
function events_organizer_edit_statuses(): array
{
    return ['publish', 'preliminary', 'draft', 'pending', 'future', 'private'];
}

/**
 * @param list<int> $ids
 */
function events_organizer_edit_unknown_ids(PDO $db, string $table, array $ids): ?string
{
    if ($ids === []) {
        return null;
    }
    if (!in_array($table, ['events_categories', 'events_tags', 'events_styles'], true)) {
        return 'Érvénytelen lista.';
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare('SELECT `id` FROM `' . $table . '` WHERE `id` IN (' . $placeholders . ')');
    $stmt->execute($ids);
    $existing = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));
    sort($existing);
    $check = $ids;
    sort($check);
    if ($existing !== $check) {
        return 'Érvénytelen azonosító.';
    }

    return null;
}

/**
 * @return array{0: ?float, 1: ?string}
 */
function events_organizer_edit_parse_cost(string $raw): array
{
    $raw = trim(str_replace(',', '.', $raw));
    if ($raw === '') {
        return [null, null];
    }
    if (!is_numeric($raw)) {
        return [null, 'A belépő csak szám lehet.'];
    }
    $amount = (float) $raw;
    if ($amount < 0) {
        return [null, 'A belépő nem lehet negatív.'];
    }

    return [$amount, null];
}
