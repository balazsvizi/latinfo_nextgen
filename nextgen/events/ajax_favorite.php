<?php
declare(strict_types=1);

/**
 * Nyilvános kedvencek (szívecskék) – AJAX.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_view_tracking.php';
require_once __DIR__ . '/lib/tag_type.php';
require_once __DIR__ . '/lib/event_public_djs.php';
require_once dirname(__DIR__) . '/lib/user/users.php';
require_once dirname(__DIR__) . '/lib/user/favorites.php';
require_once dirname(__DIR__) . '/user/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen metódus.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!rate_limit_allow(rate_limit_client_key('latinfo_favorite'), 90, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Túl sok kérés.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = getDb();
if (!latinfo_favorites_ensure_schema($db)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'A kedvencek rendszer nem érhető el.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!latinfo_favorites_public_enabled($db)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'A szívecskék jelenleg ki vannak kapcsolva.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$loggedIn = user_is_logged_in();
$userId = $loggedIn ? user_current_id() : 0;
if ($userId > 0) {
    latinfo_favorites_merge_visitor_to_user($db, $userId);
}
$actorKey = $userId > 0
    ? latinfo_favorites_user_actor_key($userId)
    : latinfo_favorites_current_actor_key();

$action = trim((string) ($_POST['action'] ?? 'toggle'));
$lang = strtolower(trim((string) ($_POST['lang'] ?? 'hu'))) === 'en' ? 'en' : 'hu';

try {
    if ($action === 'state') {
        $type = latinfo_favorites_normalize_type((string) ($_POST['entity_type'] ?? ''));
        $entityId = (int) ($_POST['entity_id'] ?? 0);
        if ($type === null || $entityId <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Hiányzó paraméter.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $state = latinfo_favorites_state($db, $type, $entityId, $actorKey);
        echo json_encode([
            'ok' => true,
            'active' => $state['active'],
            'count' => $state['count'],
            'logged_in' => $userId > 0,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'states') {
        $rawItems = $_POST['items'] ?? '';
        $decoded = is_string($rawItems) ? json_decode($rawItems, true) : $rawItems;
        if (!is_array($decoded)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Érvénytelen lista.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $pairs = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = latinfo_favorites_normalize_type((string) ($row['type'] ?? ''));
            $id = (int) ($row['id'] ?? 0);
            if ($type === null || $id <= 0) {
                continue;
            }
            $pairs[] = ['type' => $type, 'id' => $id];
        }
        $activeSet = latinfo_favorites_active_set_for_actor($db, $actorKey, $pairs);
        $results = [];
        foreach ($pairs as $pair) {
            $key = $pair['type'] . ':' . $pair['id'];
            $results[] = [
                'type' => $pair['type'],
                'id' => $pair['id'],
                'active' => isset($activeSet[$key]),
                'count' => latinfo_favorites_count($db, $pair['type'], $pair['id']),
            ];
        }
        echo json_encode([
            'ok' => true,
            'results' => $results,
            'logged_in' => $userId > 0,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'batch') {
        $activeRaw = (string) ($_POST['active'] ?? '1');
        $active = $activeRaw === '1' || $activeRaw === 'true';
        $rawItems = $_POST['items'] ?? '';
        $decoded = is_string($rawItems) ? json_decode($rawItems, true) : $rawItems;
        if (!is_array($decoded)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Érvénytelen lista.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $items = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = [
                'type' => (string) ($row['type'] ?? ''),
                'id' => (int) ($row['id'] ?? 0),
            ];
        }
        if ($items === []) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Nincs kiválasztott kedvenc.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $batch = latinfo_favorites_apply_batch($db, $items, $active, $actorKey);
        if (!$batch['ok']) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $batch['error']], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode([
            'ok' => true,
            'results' => $batch['results'],
            'logged_in' => $userId > 0,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $type = latinfo_favorites_normalize_type((string) ($_POST['entity_type'] ?? ''));
    $entityId = (int) ($_POST['entity_id'] ?? 0);
    if ($type === null || $entityId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Hiányzó paraméter.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = latinfo_favorites_toggle($db, $type, $entityId, $actorKey);
    if (!$result['ok']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $result['error']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'active' => $result['active'],
        'count' => $result['count'],
        'logged_in' => $userId > 0,
        'lang' => $lang,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('ajax_favorite: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Mentés sikertelen.'], JSON_UNESCAPED_UNICODE);
}
