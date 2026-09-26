<?php
declare(strict_types=1);

/**
 * Nyilvános CMS cikklísta.
 */
require_once __DIR__ . '/bootstrap.php';

$db = getDb();
cms_ensure_schema($db);

$themeFilter = (int) ($_GET['theme'] ?? 0);
$tagFilter = (int) ($_GET['tag'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));

$where = ['`p`.`status` = ?'];
$params = [cms_status_publish()];
if ($themeFilter > 0) {
    $where[] = '`p`.`theme_id` = ?';
    $params[] = $themeFilter;
}
if ($tagFilter > 0) {
    $where[] = 'EXISTS (SELECT 1 FROM `cms_post_tags` cpt WHERE cpt.`post_id` = p.`id` AND cpt.`tag_id` = ?)';
    $params[] = $tagFilter;
}
if ($q !== '') {
    $where[] = '(`p`.`title` LIKE ? OR `p`.`excerpt` LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
$whereSql = implode(' AND ', $where);

$st = $db->prepare("
    SELECT p.`id`, p.`title`, p.`slug`, p.`excerpt`, p.`published_at`, p.`featured_image_url`,
           t.`name` AS theme_name
    FROM `cms_posts` p
    LEFT JOIN `cms_themes` t ON t.`id` = p.`theme_id`
    WHERE $whereSql
    ORDER BY COALESCE(p.`published_at`, p.`created_at`) DESC, p.`id` DESC
    LIMIT 100
");
$st->execute($params);
$posts = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$themes = cms_themes_list($db, true);
$siteName = defined('SITE_NAME') ? (string) SITE_NAME : 'Latinfo';
$cssUrl = nextgen_url('cms/assets/css/cms-public.css') . '?v=' . rawurlencode(nextgen_app_version());
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cikkek – <?= h($siteName) ?></title>
    <meta name="description" content="Publikus tartalmak a <?= h($siteName) ?> CMS rendszeréből.">
    <?php require dirname(__DIR__) . '/includes/favicon_head.php'; ?>
    <link rel="stylesheet" href="<?= h($cssUrl) ?>">
</head>
<body class="cms-public">
    <header class="cms-public__header">
        <div class="cms-public__header-inner">
            <a class="cms-public__brand" href="<?= h(cms_public_list_url()) ?>"><?= h($siteName) ?> CMS</a>
        </div>
    </header>
    <main class="cms-public__main">
        <h1 class="cms-list__title">Cikkek</h1>
        <form method="get" class="cms-list__filters">
            <input type="search" name="q" value="<?= h($q) ?>" placeholder="Keresés…">
            <select name="theme">
                <option value="0">Minden téma</option>
                <?php foreach ($themes as $th): ?>
                <option value="<?= (int) $th['id'] ?>"<?= $themeFilter === (int) $th['id'] ? ' selected' : '' ?>><?= h((string) $th['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Szűrés</button>
        </form>

        <?php if ($posts === []): ?>
            <p class="cms-list__empty">Nincs publikus cikk.</p>
        <?php else: ?>
            <ul class="cms-list">
                <?php foreach ($posts as $p): ?>
                <li class="cms-list__item">
                    <a class="cms-list__link" href="<?= h(cms_public_post_url((string) $p['slug'])) ?>">
                        <?php if (trim((string) ($p['featured_image_url'] ?? '')) !== ''): ?>
                            <img class="cms-list__thumb" src="<?= h((string) $p['featured_image_url']) ?>" alt="" loading="lazy">
                        <?php endif; ?>
                        <div>
                            <?php if (!empty($p['theme_name'])): ?>
                                <span class="cms-list__theme"><?= h((string) $p['theme_name']) ?></span>
                            <?php endif; ?>
                            <h2 class="cms-list__item-title"><?= h((string) $p['title']) ?></h2>
                            <?php if (trim((string) ($p['excerpt'] ?? '')) !== ''): ?>
                                <p class="cms-list__excerpt"><?= h((string) $p['excerpt']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($p['published_at'])): ?>
                                <time class="cms-list__date"><?= h((string) $p['published_at']) ?></time>
                            <?php endif; ?>
                        </div>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </main>
    <footer class="cms-public__footer">
        <?= nextgen_footer_version_markup() ?>
    </footer>
</body>
</html>
