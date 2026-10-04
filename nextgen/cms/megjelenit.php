<?php
declare(strict_types=1);

/**
 * Nyilvános CMS cikk megjelenítő – Latinfo.hu shell + menü.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/events/lib/event_public_lang.php';
require_once dirname(__DIR__) . '/events/lib/public_nav_menu.php';

$lang = events_public_resolve_megjelenit_lang();
$htmlLang = $lang === 'en' ? 'en' : 'hu';

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '' || !cms_public_slug_is_valid($slug)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    $C = events_public_common_nav_strings($lang);
    echo '<!DOCTYPE html><html lang="' . h($htmlLang) . '"><head><meta charset="UTF-8"><title>Nem található</title></head><body><p>A cikk nem található.</p><p><a href="' . h(LATINFO_PUBLIC_HOME_URL) . '">' . h($C['logo_home_title'] ?? 'Latinfo.hu') . '</a></p></body></html>';
    exit;
}

if (cms_public_is_legacy_megjelenit_request()) {
    $legacyParams = [];
    foreach (['lang', 'preview'] as $param) {
        if (isset($_GET[$param]) && (string) $_GET[$param] !== '') {
            $legacyParams[$param] = (string) $_GET[$param];
        }
    }
    $target = cms_public_post_url($slug);
    if ($legacyParams !== []) {
        $target .= '?' . http_build_query($legacyParams, '', '&', PHP_QUERY_RFC3986);
    }
    header('Location: ' . $target, true, 301);
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
    echo '<!DOCTYPE html><html lang="' . h($htmlLang) . '"><head><meta charset="UTF-8"><title>Nem található</title></head><body class="event-public-page"><p>A cikk nem található vagy nem publikus.</p><p><a href="' . h(LATINFO_PUBLIC_HOME_URL) . '">Latinfo.hu</a></p></body></html>';
    exit;
}

$postId = (int) ($post['id'] ?? 0);
if (
    (string) ($post['status'] ?? '') === cms_status_publish()
    && function_exists('events_public_visitor_metrics_allowed')
    && events_public_visitor_metrics_allowed()
) {
    $clientPageView = [
        'type' => 'cms',
        'post_id' => $postId,
    ];
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
    // tags optional
}

$pageTitle = trim((string) ($post['seo_title'] ?? '')) !== ''
    ? (string) $post['seo_title']
    : (string) ($post['title'] ?? 'CMS');
$description = trim((string) ($post['seo_description'] ?? ''));
if ($description === '') {
    $description = trim((string) ($post['excerpt'] ?? ''));
}
$contentHtml = events_sanitize_html_fragment((string) ($post['content_html'] ?? ''));
$featured = trim((string) ($post['featured_image_url'] ?? ''));
$featuredAbsolute = $featured !== '' ? cms_absolute_url($featured) : '';

$publishedLabel = '';
if (!empty($post['published_at'])) {
    $ts = strtotime((string) $post['published_at']);
    if ($ts !== false) {
        $publishedLabel = $lang === 'en'
            ? date('j M Y', $ts)
            : date('Y. m. d.', $ts);
    }
}

$S = events_public_megjelenit_strings($lang);
$S['logo_home_title'] = events_public_common_nav_strings($lang)['logo_home_title'];
$S['logo_home_aria'] = events_public_common_nav_strings($lang)['logo_home_aria'];
$isEventsHome = false;
$showAdminEdit = isLoggedIn();
$adminEditUrl = $showAdminEdit ? cms_url('szerkeszt.php?id=' . $postId) : '';
$S['admin_edit_title'] = $lang === 'en' ? 'Edit' : 'Szerkesztés';
$S['admin_edit_aria'] = $lang === 'en' ? 'Edit article in CMS' : 'Cikk szerkesztése a CMS-ben';

$selfPath = cms_public_post_url($slug);
$urlHu = cms_public_lang_switch_url($slug, 'hu');
$urlEn = cms_public_lang_switch_url($slug, 'en');
$canonical = events_absolute_url($selfPath);

$cssPublicUrl = events_url('assets/event_public.css') . '?v=' . rawurlencode(nextgen_app_version());
$cssCmsUrl = nextgen_url('cms/assets/css/cms-public.css') . '?v=' . rawurlencode(nextgen_app_version());

$adminFloatTools = [];
if ($showAdminEdit) {
    $adminFloatTools = [
        [
            'href' => $adminEditUrl,
            'title' => $S['admin_edit_title'],
            'aria' => $S['admin_edit_aria'],
            'icon' => 'edit',
        ],
        [
            'href' => cms_url('posts.php'),
            'title' => 'CMS',
            'aria' => 'CMS cikklista',
            'icon' => 'list',
        ],
    ];
}

$ogPageUrl = $canonical;

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= events_public_ga_head_markup() ?>
    <meta name="theme-color" content="#6d8f63">
    <title><?= h($pageTitle) ?> – <?= h(SITE_NAME) ?></title>
    <?php if ($description !== ''): ?>
    <meta name="description" content="<?= h(mb_substr(strip_tags($description), 0, 300)) ?>">
    <?php endif; ?>
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
    <meta property="og:title" content="<?= h($pageTitle) ?>">
    <?php if ($description !== ''): ?>
    <meta property="og:description" content="<?= h(mb_substr(strip_tags($description), 0, 300)) ?>">
    <?php endif; ?>
    <meta property="og:url" content="<?= h($ogPageUrl) ?>">
    <?php if ($featuredAbsolute !== ''): ?>
    <meta property="og:image" content="<?= h($featuredAbsolute) ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?= h($ogPageUrl) ?>">
    <?= events_public_favicon_head_markup() ?>
    <link rel="stylesheet" href="<?= h($cssPublicUrl) ?>">
    <link rel="stylesheet" href="<?= h($cssCmsUrl) ?>">
</head>
<body class="event-public-page event-public-page--cms">
<?php require dirname(__DIR__) . '/events/partials/admin_float_tools.php'; ?>
<div class="event-shell event-shell--cms">
    <article class="event-public cms-article-page">
        <header class="event-public__hero event-public__hero--bar-only">
            <?php require dirname(__DIR__) . '/events/partials/public_shell_hero_bar.php'; ?>
        </header>

        <div class="cms-article-stage">
            <div class="cms-article-board">
                <header class="cms-article-head">
                    <?php if ($themeName !== ''): ?>
                        <p class="cms-article-head__kicker"><?= h($themeName) ?></p>
                    <?php endif; ?>
                    <h1 class="cms-article-head__title"><?= h((string) ($post['title'] ?? '')) ?></h1>
                    <?php if ($publishedLabel !== '' || $isAdminPreview): ?>
                        <p class="cms-article-head__meta">
                            <?php if ($publishedLabel !== ''): ?>
                                <time datetime="<?= h((string) ($post['published_at'] ?? '')) ?>"><?= h($publishedLabel) ?></time>
                            <?php endif; ?>
                            <?php if ($isAdminPreview): ?>
                                <span class="cms-article-head__preview">Előnézet</span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <?php if (trim((string) ($post['excerpt'] ?? '')) !== ''): ?>
                        <p class="cms-article-head__lead"><?= h((string) $post['excerpt']) ?></p>
                    <?php endif; ?>
                </header>

                <?php if ($featuredAbsolute !== ''): ?>
                    <figure class="cms-article-hero-media">
                        <img
                            src="<?= h($featuredAbsolute) ?>"
                            alt="<?= h((string) ($post['title'] ?? '')) ?>"
                            decoding="async"
                            fetchpriority="high"
                        >
                    </figure>
                <?php endif; ?>

                <div class="cms-article-body">
                    <?= $contentHtml ?>
                </div>

                <?php if ($tagNames !== []): ?>
                    <ul class="cms-article-tags" aria-label="<?= $lang === 'en' ? 'Tags' : 'Címkék' ?>">
                        <?php foreach ($tagNames as $tn): ?>
                            <li><?= h($tn) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <footer class="cms-article-foot">
            <?php require dirname(__DIR__) . '/events/partials/public_shell_footer.php'; ?>
        </footer>
    </article>
</div>
</body>
</html>
