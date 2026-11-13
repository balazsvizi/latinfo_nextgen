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
 * Partnerportálos mentés: csak a nyilvános tartalom. Státusz, slug, szervezők,
 * kategóriák, pénzügy és a kapcsoló változatlan marad.
 *
 * @param array<string, mixed> $current
 * @param array<string, mixed> $post
 * @return array{ok: true, row: array<string, mixed>}|array{ok: false, error: string, row: array<string, mixed>}
 */
function events_organizer_edit_apply(PDO $db, array $current, array $post): array
{
    $row = $current;
    $row['organizer_cover_url'] = trim((string) ($post['organizer_cover_url'] ?? ''));
    $eventId = (int) ($current['id'] ?? 0);
    if ($eventId <= 0 || !events_organizer_can_edit_from_row($current)) {
        return ['ok' => false, 'error' => 'Ezt az eseményt nem szerkesztheted.', 'row' => $row];
    }

    $name = trim((string) ($post['event_name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 500) {
        $row['event_name'] = $name;

        return ['ok' => false, 'error' => 'Az esemény neve kötelező, legfeljebb 500 karakter.', 'row' => $row];
    }
    $row['event_name'] = $name;

    $content = events_sanitize_html_fragment((string) ($post['event_content'] ?? ''));
    $row['event_content'] = $content;
    $status = (string) ($current['event_status'] ?? '');
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
    $row['event_status'] = $status;
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

    $coverPosted = trim((string) ($post['organizer_cover_url'] ?? ''));
    if ($coverPosted !== '') {
        [$coverUrl, $coverErr] = events_normalize_featured_image_url($coverPosted);
        if ($coverErr !== null) {
            $row['organizer_cover_url'] = $coverPosted;

            return ['ok' => false, 'error' => $coverErr, 'row' => $row];
        }
        $row['event_featured_image_url'] = $coverUrl;
        $row['organizer_cover_url'] = (string) ($coverUrl ?? '');
    }

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
