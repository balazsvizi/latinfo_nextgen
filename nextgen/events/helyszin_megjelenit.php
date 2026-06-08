<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_public_lang.php';
require_once __DIR__ . '/lib/venue_request.php';

$lang = events_public_resolve_megjelenit_lang();
events_public_send_noindex_header();

$slug = trim((string) ($_GET['slug'] ?? ''));
$C = events_public_common_nav_strings($lang);
$S = [
    'footer_home_link' => 'Latinfo.hu',
    'lang_nav' => $lang === 'en' ? 'Language' : 'Nyelv',
    'lang_hu' => $lang === 'en' ? 'Hungarian' : 'Magyar',
    'lang_en' => $lang === 'en' ? 'English' : 'English',
    'logo_alt' => 'Latinfo.hu',
];
$cssUrl = events_url('assets/event_public.css');
$htmlLang = $lang === 'en' ? 'en' : 'hu';

if ($slug === '') {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    $eventsHome = events_public_home_page_url($lang);
    $latinfoHome = LATINFO_PUBLIC_HOME_URL;
    ?><!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= events_public_robots_noindex_head_markup() ?>
    <title><?= h($lang === 'en' ? 'Venue not found' : 'Nincs ilyen helyszín') ?></title>
    <?= events_public_favicon_head_markup() ?>
    <link rel="stylesheet" href="<?= h($cssUrl) ?>">
</head>
<body class="event-public-page">
<div class="event-shell">
    <div class="event-shell-toolbar">
        <div class="event-shell-toolbar__leading">
            <a class="event-shell-toolbar__events-home" href="<?= h($eventsHome) ?>" aria-label="<?= h($C['events_home_aria']) ?>"><?= h($C['events_home_back']) ?></a>
        </div>
    </div>
    <p class="event-not-found-msg"><?= h($lang === 'en' ? 'Venue not found.' : 'Nincs ilyen helyszín.') ?></p>
    <?php $standalone = true; require __DIR__ . '/partials/public_shell_footer.php'; ?>
</div>
</body>
</html><?php
    exit;
}

$db = getDb();
$stmt = $db->prepare('
    SELECT v.*, l.`name` AS `linked_name`, l.`slug` AS `linked_slug`
    FROM `events_venues` v
    LEFT JOIN `events_venues` l ON l.`id` = v.`linked_venue_id`
    WHERE v.`slug` = ?
    LIMIT 1
');
$stmt->execute([$slug]);
$venue = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$venue) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    $eventsHome = events_public_home_page_url($lang);
    ?><!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= events_public_robots_noindex_head_markup() ?>
    <title><?= h($lang === 'en' ? 'Venue not found' : 'Nincs ilyen helyszín') ?></title>
    <?= events_public_favicon_head_markup() ?>
    <link rel="stylesheet" href="<?= h($cssUrl) ?>">
</head>
<body class="event-public-page">
<div class="event-shell">
    <div class="event-shell-toolbar">
        <div class="event-shell-toolbar__leading">
            <a class="event-shell-toolbar__events-home" href="<?= h($eventsHome) ?>" aria-label="<?= h($C['events_home_aria']) ?>"><?= h($C['events_home_back']) ?></a>
        </div>
    </div>
    <p class="event-not-found-msg"><?= h($lang === 'en' ? 'Venue not found.' : 'Nincs ilyen helyszín.') ?></p>
    <?php $standalone = true; require __DIR__ . '/partials/public_shell_footer.php'; ?>
</div>
</body>
</html><?php
    exit;
}

$canonical = events_helyszin_megjelenit_url((string) $venue['slug']);
$title = (string) $venue['name'];
$safeVenueBody = events_sanitize_html_fragment((string) ($venue['description'] ?? ''));
$descRaw = trim(strip_tags($safeVenueBody));
$desc = function_exists('mb_substr') ? mb_substr($descRaw, 0, 160, 'UTF-8') : substr($descRaw, 0, 160);
$addrLine = events_venue_address_summary($venue);

$linkedName = trim((string) ($venue['linked_name'] ?? ''));
$linkedSlug = trim((string) ($venue['linked_slug'] ?? ''));
$hasLinked = $linkedName !== '' && $linkedSlug !== '';

$urlHu = events_public_venue_lang_switch_url($slug, 'hu');
$urlEn = events_public_venue_lang_switch_url($slug, 'en');

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= events_public_robots_noindex_head_markup() ?>
    <title><?= h($title) ?> – <?= h(SITE_NAME) ?></title>
    <?php if ($desc !== ''): ?><meta name="description" content="<?= h($desc) ?>"><?php endif; ?>
    <link rel="canonical" href="<?= h($canonical) ?>">
    <?= events_public_favicon_head_markup() ?>
    <link rel="stylesheet" href="<?= h($cssUrl) ?>">
</head>
<body class="event-public-page">
<div class="event-shell">
    <?php require __DIR__ . '/partials/public_shell_toolbar.php'; ?>

<article class="event-public venue-public">
    <header class="event-public__hero">
        <div class="event-public__hero-inner">
            <p class="event-public__eyebrow">📍 <?= h($lang === 'en' ? 'Venue' : 'Helyszín') ?></p>
            <h1 class="event-public__title"><?= h($title) ?></h1>
            <?php if ($hasLinked): ?>
                <p class="venue-linked-line">
                    <a href="<?= h(events_helyszin_megjelenit_url($linkedSlug)) ?>"><?= h($linkedName) ?></a>
                </p>
            <?php endif; ?>
            <?php if ($addrLine !== ''): ?>
                <p class="venue-address-line"><?= nl2br(h($addrLine)) ?></p>
            <?php endif; ?>
        </div>
    </header>
    <?php $body = trim($safeVenueBody); ?>
    <?php if ($body !== ''): ?>
        <div class="venue-body event-rich-text">
            <?= $body ?>
        </div>
    <?php endif; ?>

    <footer class="event-public__footer">
        <?php require __DIR__ . '/partials/public_shell_footer.php'; ?>
    </footer>
</article>
</div>
</body>
</html>
