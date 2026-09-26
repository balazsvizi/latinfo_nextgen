<?php
declare(strict_types=1);

/**
 * Nyilvános CMS cikk megjelenítő.
 */
require_once __DIR__ . '/bootstrap.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"><title>Nem található</title></head><body><p>A cikk nem található.</p></body></html>';
    exit;
}

$db = getDb();
cms_ensure_schema($db);

$post = cms_load_post_by_slug($db, $slug);
$isAdminPreview = function_exists('isLoggedIn') && isLoggedIn()
    && isset($_GET['preview']) && (string) $_GET['preview'] === '1';

if ($post === null || ((string) ($post['status'] ?? '') !== cms_status_publish() && !$isAdminPreview)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"><title>Nem található</title></head><body><p>A cikk nem található vagy nem publikus.</p><p><a href="' . h(cms_public_list_url()) . '">Vissza a listához</a></p></body></html>';
    exit;
}

$postId = (int) ($post['id'] ?? 0);
if ((string) ($post['status'] ?? '') === cms_status_publish()) {
    cms_record_post_view($db, $postId, CMS_VIEW_SOURCE_DIRECT);
}

$themeName = '';
if (!empty($post['theme_id'])) {
    $tst = $db->prepare('SELECT `name` FROM `cms_themes` WHERE `id` = ? LIMIT 1');
    $tst->execute([(int) $post['theme_id']]);
    $themeName = (string) ($tst->fetchColumn() ?: '');
}

$tagNames = [];
try {
    $tg = $db->prepare('
        SELECT t.`name`
        FROM `cms_post_tags` cpt
        INNER JOIN `events_tags` t ON t.`id` = cpt.`tag_id`
        WHERE cpt.`post_id` = ?
        ORDER BY t.`name` ASC
    ');
    $tg->execute([$postId]);
    while ($r = $tg->fetch(PDO::FETCH_ASSOC)) {
        $tagNames[] = (string) ($r['name'] ?? '');
    }
} catch (Throwable $e) {
    // tags table optional
}

$title = trim((string) ($post['seo_title'] ?? '')) !== ''
    ? (string) $post['seo_title']
    : (string) ($post['title'] ?? 'CMS');
$description = trim((string) ($post['seo_description'] ?? ''));
if ($description === '') {
    $description = trim((string) ($post['excerpt'] ?? ''));
}
$contentHtml = events_sanitize_html_fragment((string) ($post['content_html'] ?? ''));
$featured = trim((string) ($post['featured_image_url'] ?? ''));
$siteName = defined('SITE_NAME') ? (string) SITE_NAME : 'Latinfo';
$listUrl = cms_public_list_url();
$cssUrl = nextgen_url('cms/assets/css/cms-public.css') . '?v=' . rawurlencode(nextgen_app_version());
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> – <?= h($siteName) ?></title>
    <?php if ($description !== ''): ?>
    <meta name="description" content="<?= h(mb_substr(strip_tags($description), 0, 300)) ?>">
    <?php endif; ?>
    <?php require dirname(__DIR__) . '/includes/favicon_head.php'; ?>
    <link rel="stylesheet" href="<?= h($cssUrl) ?>">
</head>
<body class="cms-public">
    <header class="cms-public__header">
        <div class="cms-public__header-inner">
            <a class="cms-public__brand" href="<?= h($listUrl) ?>"><?= h($siteName) ?> CMS</a>
            <nav class="cms-public__nav">
                <a href="<?= h($listUrl) ?>">Összes cikk</a>
            </nav>
        </div>
    </header>
    <main class="cms-public__main">
        <article class="cms-article">
            <?php if ($themeName !== ''): ?>
                <p class="cms-article__theme"><?= h($themeName) ?></p>
            <?php endif; ?>
            <h1 class="cms-article__title"><?= h((string) ($post['title'] ?? '')) ?></h1>
            <?php if (!empty($post['published_at'])): ?>
                <p class="cms-article__meta"><?= h((string) $post['published_at']) ?></p>
            <?php endif; ?>
            <?php if ($featured !== ''): ?>
                <figure class="cms-article__featured">
                    <img src="<?= h($featured) ?>" alt="">
                </figure>
            <?php endif; ?>
            <?php if (trim((string) ($post['excerpt'] ?? '')) !== ''): ?>
                <p class="cms-article__excerpt"><?= h((string) $post['excerpt']) ?></p>
            <?php endif; ?>
            <div class="cms-article__body">
                <?= $contentHtml ?>
            </div>
            <?php if ($tagNames !== []): ?>
                <ul class="cms-article__tags">
                    <?php foreach ($tagNames as $tn): ?>
                        <li><?= h($tn) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    </main>
    <footer class="cms-public__footer">
        <p><a href="<?= h($listUrl) ?>">← Vissza a cikkekhez</a></p>
        <?= nextgen_footer_version_markup() ?>
    </footer>
</body>
</html>
