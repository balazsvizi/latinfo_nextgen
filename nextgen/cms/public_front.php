<?php
declare(strict_types=1);

/**
 * Gyökér /{slug} belső belépő.
 * Publikus (vagy előnézeti) CMS cikk esetén a megjelenítő fut.
 * Ismeretlen slug: WordPress index.php, ha a docrootban van.
 */

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
    cms_public_front_passthrough();
}

require_once dirname(__DIR__) . '/core/database.php';

$status = cms_public_front_post_status($slug);

$isPreview = isset($_GET['preview']) && (string) $_GET['preview'] === '1';
if ($status !== 'publish' && !$isPreview) {
    cms_public_front_passthrough();
}

$path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
$needsCanonical = str_ends_with($path, '/')
    || str_contains(strtolower($path), 'public_front.php');
if ($needsCanonical && !headers_sent()) {
    $params = $_GET;
    unset($params['slug']);
    $target = site_url(rawurlencode($slug));
    if ($params !== []) {
        $target .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
    header('Location: ' . $target, true, 301);
    exit;
}

require __DIR__ . '/megjelenit.php';
exit;

function cms_public_front_post_status(string $slug): ?string
{
    try {
        return cms_public_front_fetch_status(getDb(), $slug);
    } catch (PDOException $e) {
        $code = (int) ($e->errorInfo[1] ?? 0);
        if ($code !== 1146) {
            error_log('cms public front: ' . $e->getMessage());

            return null;
        }
    } catch (Throwable $e) {
        error_log('cms public front: ' . $e->getMessage());

        return null;
    }

    require_once __DIR__ . '/lib/schema.php';
    try {
        $db = getDb();
        if (!cms_ensure_schema($db)) {
            return null;
        }

        return cms_public_front_fetch_status($db, $slug);
    } catch (Throwable $e) {
        error_log('cms public front: ' . $e->getMessage());

        return null;
    }
}

function cms_public_front_fetch_status(PDO $db, string $slug): ?string
{
    $st = $db->prepare('SELECT `status` FROM `cms_posts` WHERE `slug` = ? LIMIT 1');
    $st->execute([$slug]);
    $found = $st->fetchColumn();

    return is_string($found) ? $found : null;
}

function cms_public_front_passthrough(): never
{
    $wpIndex = dirname(__DIR__, 2) . '/index.php';
    if (is_file($wpIndex)) {
        unset($_GET['slug']);
        $_SERVER['QUERY_STRING'] = http_build_query($_GET);
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = $wpIndex;
        $_SERVER['PHP_SELF'] = '/index.php';
        chdir(dirname($wpIndex));
        require $wpIndex;
        exit;
    }

    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"><title>Nem található</title></head><body><p>A kért oldal nem található.</p></body></html>';
    exit;
}
