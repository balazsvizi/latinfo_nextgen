<?php
declare(strict_types=1);

/**
 * Partnerportál: kontextus (szervező/DJ), események, naptár, dashboard.
 */

/**
 * @return array{type: 'all'|'organizer'|'dj', id: int, label: string, key: string}
 */
function partner_portal_default_context(): array
{
    return ['type' => 'all', 'id' => 0, 'label' => 'Összes partner', 'key' => 'all'];
}

/**
 * @return list<array{type: 'organizer'|'dj', id: int, label: string, key: string, roles: list<string>}>
 */
function partner_portal_available_contexts(PDO $db, int $partnerId): array
{
    $contexts = [];
    $organizers = nextgen_partner_group_organizer_assignments_for_form(
        nextgen_partner_events_organizers($db, $partnerId)
    );
    foreach ($organizers as $org) {
        $oid = (int) ($org['organizer_id'] ?? $org['id'] ?? 0);
        if ($oid <= 0) {
            continue;
        }
        $roles = $org['role_types'] ?? [];
        if (!is_array($roles) || $roles === []) {
            $roles = ['event'];
        }
        $contexts[] = [
            'type' => 'organizer',
            'id' => $oid,
            'label' => (string) ($org['name'] ?? ('Szervező #' . $oid)),
            'key' => 'o:' . $oid,
            'roles' => array_values(array_map('strval', $roles)),
        ];
    }

    $djs = nextgen_partner_group_dj_assignments_for_form(nextgen_partner_djs($db, $partnerId));
    foreach ($djs as $dj) {
        $tid = (int) ($dj['tag_id'] ?? $dj['id'] ?? 0);
        if ($tid <= 0) {
            continue;
        }
        $roles = $dj['role_types'] ?? [];
        if (!is_array($roles) || $roles === []) {
            $roles = ['dj'];
        }
        $contexts[] = [
            'type' => 'dj',
            'id' => $tid,
            'label' => (string) ($dj['name'] ?? ('DJ #' . $tid)),
            'key' => 'd:' . $tid,
            'roles' => array_values(array_map('strval', $roles)),
        ];
    }

    return $contexts;
}

/**
 * @param list<array{key: string}> $available
 */
function partner_portal_parse_context_key(string $key, array $available): array
{
    $key = trim($key);
    if ($key === '' || $key === 'all') {
        return partner_portal_default_context();
    }
    foreach ($available as $ctx) {
        if (($ctx['key'] ?? '') === $key) {
            return [
                'type' => (string) $ctx['type'],
                'id' => (int) $ctx['id'],
                'label' => (string) $ctx['label'],
                'key' => (string) $ctx['key'],
            ];
        }
    }

    return partner_portal_default_context();
}

/**
 * @return array{type: string, id: int, label: string, key: string}
 */
function partner_portal_current_context(PDO $db, int $partnerId): array
{
    $available = partner_portal_available_contexts($db, $partnerId);
    $stored = (string) ($_SESSION['partner_context'] ?? 'all');
    $ctx = partner_portal_parse_context_key($stored, $available);
    $_SESSION['partner_context'] = $ctx['key'];

    return $ctx;
}

function partner_portal_set_context(string $key): void
{
    $_SESSION['partner_context'] = $key === '' ? 'all' : $key;
}

function partner_portal_apply_context_from_request(PDO $db, int $partnerId): void
{
    if (!isset($_GET['set_ctx'])) {
        return;
    }
    $available = partner_portal_available_contexts($db, $partnerId);
    $ctx = partner_portal_parse_context_key((string) $_GET['set_ctx'], $available);
    partner_portal_set_context($ctx['key']);

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? partner_url('index.php'));
    $parts = parse_url($uri);
    $path = (string) ($parts['path'] ?? partner_url('index.php'));
    $query = [];
    if (!empty($parts['query'])) {
        parse_str((string) $parts['query'], $query);
    }
    unset($query['set_ctx']);
    $qs = http_build_query($query);
    redirect($qs === '' ? $path : ($path . '?' . $qs));
}

/**
 * @return array{organizer_ids: list<int>, tag_ids: list<int>}
 */
function partner_portal_scope_ids(PDO $db, int $partnerId, ?array $context = null): array
{
    $context ??= partner_portal_current_context($db, $partnerId);
    $available = partner_portal_available_contexts($db, $partnerId);

    if (($context['type'] ?? '') === 'organizer' && (int) ($context['id'] ?? 0) > 0) {
        return ['organizer_ids' => [(int) $context['id']], 'tag_ids' => []];
    }
    if (($context['type'] ?? '') === 'dj' && (int) ($context['id'] ?? 0) > 0) {
        return ['organizer_ids' => [], 'tag_ids' => [(int) $context['id']]];
    }

    $organizerIds = [];
    $tagIds = [];
    foreach ($available as $ctx) {
        if ($ctx['type'] === 'organizer') {
            $organizerIds[] = (int) $ctx['id'];
        } elseif ($ctx['type'] === 'dj') {
            $tagIds[] = (int) $ctx['id'];
        }
    }

    return [
        'organizer_ids' => array_values(array_unique($organizerIds)),
        'tag_ids' => array_values(array_unique($tagIds)),
    ];
}

/**
 * @return array{organizer_ids: list<int>, tag_ids: list<int>}
 */
function partner_portal_all_owned_ids(PDO $db, int $partnerId): array
{
    return partner_portal_scope_ids($db, $partnerId, partner_portal_default_context());
}

/**
 * @param list<int> $ids
 */
function partner_portal_in_placeholders(array $ids): string
{
    return implode(',', array_fill(0, count($ids), '?'));
}

/**
 * @return list<array<string, mixed>>
 */
function partner_portal_fetch_events(PDO $db, int $partnerId, ?array $context = null, int $limit = 0): array
{
    $scope = partner_portal_scope_ids($db, $partnerId, $context);
    $orgIds = $scope['organizer_ids'];
    $tagIds = $scope['tag_ids'];
    if ($orgIds === [] && $tagIds === []) {
        return [];
    }

    $parts = [];
    $params = [];
    if ($orgIds !== []) {
        $parts[] = 'e.`id` IN (
            SELECT eo.`event_id` FROM `events_calendar_event_organizers` eo
            WHERE eo.`organizer_id` IN (' . partner_portal_in_placeholders($orgIds) . ')
        )';
        foreach ($orgIds as $id) {
            $params[] = $id;
        }
    }
    if ($tagIds !== []) {
        $parts[] = 'e.`id` IN (
            SELECT et.`event_id` FROM `events_calendar_event_tags` et
            WHERE et.`tag_id` IN (' . partner_portal_in_placeholders($tagIds) . ')
        )';
        foreach ($tagIds as $id) {
            $params[] = $id;
        }
    }

    $where = '(' . implode(' OR ', $parts) . ')';
    $limitSql = $limit > 0 ? ' LIMIT ' . $limit : '';

    try {
        $stmt = $db->prepare("
            SELECT DISTINCT e.*,
                v.`name` AS venue_name,
                v.`city` AS venue_city
            FROM `events_calendar_events` e
            LEFT JOIN `events_venues` v ON v.`id` = e.`venue_id`
            WHERE {$where}
            ORDER BY e.`event_start` IS NULL, e.`event_start` DESC, e.`id` DESC
            {$limitSql}
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) {
        error_log('partner_portal_fetch_events: ' . $ex->getMessage());

        return [];
    }
}

/**
 * @return array<int, true>
 */
function partner_portal_owned_event_id_map(PDO $db, int $partnerId): array
{
    $scope = partner_portal_all_owned_ids($db, $partnerId);
    $orgIds = $scope['organizer_ids'];
    $tagIds = $scope['tag_ids'];
    if ($orgIds === [] && $tagIds === []) {
        return [];
    }

    $parts = [];
    $params = [];
    if ($orgIds !== []) {
        $parts[] = 'SELECT eo.`event_id` AS id FROM `events_calendar_event_organizers` eo
            WHERE eo.`organizer_id` IN (' . partner_portal_in_placeholders($orgIds) . ')';
        foreach ($orgIds as $id) {
            $params[] = $id;
        }
    }
    if ($tagIds !== []) {
        $parts[] = 'SELECT et.`event_id` AS id FROM `events_calendar_event_tags` et
            WHERE et.`tag_id` IN (' . partner_portal_in_placeholders($tagIds) . ')';
        foreach ($tagIds as $id) {
            $params[] = $id;
        }
    }

    try {
        $sql = implode(' UNION ', $parts);
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $map[$id] = true;
            }
        }

        return $map;
    } catch (Throwable $ex) {
        error_log('partner_portal_owned_event_id_map: ' . $ex->getMessage());

        return [];
    }
}

function partner_portal_can_access_event(PDO $db, int $partnerId, int $eventId): bool
{
    if ($partnerId <= 0 || $eventId <= 0) {
        return false;
    }
    $scope = partner_portal_all_owned_ids($db, $partnerId);
    $orgIds = $scope['organizer_ids'];
    $tagIds = $scope['tag_ids'];
    if ($orgIds === [] && $tagIds === []) {
        return false;
    }

    try {
        if ($orgIds !== []) {
            $stmt = $db->prepare(
                'SELECT 1 FROM `events_calendar_event_organizers`
                 WHERE `event_id` = ? AND `organizer_id` IN (' . partner_portal_in_placeholders($orgIds) . ')
                 LIMIT 1'
            );
            $stmt->execute(array_merge([$eventId], $orgIds));
            if ($stmt->fetchColumn()) {
                return true;
            }
        }
        if ($tagIds !== []) {
            $stmt = $db->prepare(
                'SELECT 1 FROM `events_calendar_event_tags`
                 WHERE `event_id` = ? AND `tag_id` IN (' . partner_portal_in_placeholders($tagIds) . ')
                 LIMIT 1'
            );
            $stmt->execute(array_merge([$eventId], $tagIds));
            if ($stmt->fetchColumn()) {
                return true;
            }
        }
    } catch (Throwable) {
        return false;
    }

    return false;
}

/**
 * @return array<string, mixed>|null
 */
function partner_portal_event_by_id(PDO $db, int $eventId): ?array
{
    if ($eventId <= 0) {
        return null;
    }
    try {
        $stmt = $db->prepare('
            SELECT e.*,
                v.`name` AS venue_name,
                v.`city` AS venue_city,
                v.`address` AS venue_address
            FROM `events_calendar_events` e
            LEFT JOIN `events_venues` v ON v.`id` = e.`venue_id`
            WHERE e.`id` = ?
            LIMIT 1
        ');
        $stmt->execute([$eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable) {
        return null;
    }
}

/**
 * Közzétett események a naptárhoz (teljes naptár + saját kiemelés).
 *
 * @return list<array<string, mixed>>
 */
function partner_portal_calendar_events(PDO $db, DateTimeImmutable $monthFirst, DateTimeImmutable $monthLast): array
{
    $published = events_public_post_status();
    $from = $monthFirst->modify('-7 days')->format('Y-m-d 00:00:00');
    $to = $monthLast->modify('+7 days')->format('Y-m-d 23:59:59');

    try {
        $stmt = $db->prepare('
            SELECT e.*
            FROM `events_calendar_events` e
            WHERE e.`event_status` = ?
              AND e.`event_start` IS NOT NULL
              AND e.`event_start` <= ?
              AND COALESCE(e.`event_end`, e.`event_start`) >= ?
            ORDER BY e.`event_start` ASC, e.`event_name` ASC
        ');
        $stmt->execute([$published, $to, $from]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) {
        error_log('partner_portal_calendar_events: ' . $ex->getMessage());

        return [];
    }
}

/**
 * @param list<array<string, mixed>> $events
 * @return array{upcoming: int, past: int, published: int, draft: int, total: int, next: ?array}
 */
function partner_portal_event_stats_summary(array $events): array
{
    $now = events_admin_calendar_effective_today();
    $nowTs = $now->getTimestamp();
    $publishedStatus = events_public_post_status();
    $upcoming = 0;
    $past = 0;
    $published = 0;
    $draft = 0;
    $next = null;
    $nextTs = null;

    foreach ($events as $ev) {
        $st = (string) ($ev['event_status'] ?? '');
        if ($st === $publishedStatus) {
            $published++;
        }
        if (in_array($st, ['draft', 'auto-draft'], true)) {
            $draft++;
        }
        $startRaw = trim((string) ($ev['event_start'] ?? ''));
        if ($startRaw === '') {
            continue;
        }
        try {
            $startTs = (new DateTimeImmutable($startRaw))->getTimestamp();
        } catch (Throwable) {
            continue;
        }
        $endRaw = trim((string) ($ev['event_end'] ?? ''));
        $endTs = $startTs;
        if ($endRaw !== '') {
            try {
                $endTs = (new DateTimeImmutable($endRaw))->getTimestamp();
            } catch (Throwable) {
                $endTs = $startTs;
            }
        }
        if ($endTs >= $nowTs) {
            $upcoming++;
            if ($startTs >= $nowTs && ($nextTs === null || $startTs < $nextTs)) {
                $nextTs = $startTs;
                $next = $ev;
            }
        } else {
            $past++;
        }
    }

    return [
        'upcoming' => $upcoming,
        'past' => $past,
        'published' => $published,
        'draft' => $draft,
        'total' => count($events),
        'next' => $next,
    ];
}

function partner_portal_event_detail_url(int $eventId): string
{
    return partner_url('esemeny.php?id=' . $eventId);
}

function partner_portal_month_url(string $monthKey, array $extra = []): string
{
    $params = array_merge(['month' => $monthKey], $extra);
    $qs = http_build_query($params);

    return partner_url('naptar.php' . ($qs !== '' ? '?' . $qs : ''));
}

/**
 * Admin válasz várakozik-e (utolsó üzenet admintól).
 */
function partner_portal_admin_reply_pending(PDO $db, int $partnerId): bool
{
    if ($partnerId <= 0 || !nextgen_partner_messages_table_ready($db)) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            SELECT `creator_type`
            FROM `nextgen_partner_messages`
            WHERE `partner_id` = ?
            ORDER BY `létrehozva` DESC, `id` DESC
            LIMIT 1
        ');
        $stmt->execute([$partnerId]);
        $type = (string) ($stmt->fetchColumn() ?: '');

        return $type === 'admin';
    } catch (Throwable) {
        return false;
    }
}

function partner_portal_message_count(PDO $db, int $partnerId): int
{
    if ($partnerId <= 0 || !nextgen_partner_messages_table_ready($db)) {
        return 0;
    }
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM `nextgen_partner_messages` WHERE `partner_id` = ?');
        $stmt->execute([$partnerId]);

        return (int) $stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

/**
 * @return list<array{id: int, name: string}>
 */
function partner_portal_event_organizer_names(PDO $db, int $eventId): array
{
    try {
        $stmt = $db->prepare('
            SELECT o.`id`, o.`name`
            FROM `events_calendar_event_organizers` eo
            INNER JOIN `events_organizers` o ON o.`id` = eo.`organizer_id`
            WHERE eo.`event_id` = ?
            ORDER BY eo.`sort_order` ASC, o.`name` ASC
        ');
        $stmt->execute([$eventId]);

        return array_map(
            static fn (array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['name']],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    } catch (Throwable) {
        return [];
    }
}

/**
 * @param array<int, list<array{id: int, name: string, color: string}>> $categoriesByEventId
 * @return array<int, list<array{id: int, name: string, color: string}>>
 */
function partner_portal_categories_by_event_ids(PDO $db, array $eventIds): array
{
    $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds))));
    if ($eventIds === []) {
        return [];
    }
    $map = [];
    try {
        $ph = partner_portal_in_placeholders($eventIds);
        $stmt = $db->prepare("
            SELECT ec.`event_id`, c.`id`, c.`name`, c.`color`
            FROM `events_calendar_event_categories` ec
            INNER JOIN `events_categories` c ON c.`id` = ec.`category_id`
            WHERE ec.`event_id` IN ({$ph})
            ORDER BY c.`sort_order` ASC, c.`name` ASC, c.`id` ASC
        ");
        $stmt->execute($eventIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $catRow) {
            $eid = (int) $catRow['event_id'];
            if (!isset($map[$eid])) {
                $map[$eid] = [];
            }
            $map[$eid][] = [
                'id' => (int) $catRow['id'],
                'name' => (string) $catRow['name'],
                'color' => trim((string) ($catRow['color'] ?? '')) !== '' ? trim((string) $catRow['color']) : '#6d8f63',
            ];
        }
    } catch (Throwable) {
        return [];
    }

    return $map;
}
