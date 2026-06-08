<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/venue_request.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Nincs ilyen helyszín.';
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
    ?><!DOCTYPE html>
<html lang="hu">
<head><meta charset="UTF-8"><title>Nincs ilyen helyszín</title></head>
<body><p>Nincs ilyen helyszín.</p></body>
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

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> – <?= h(SITE_NAME) ?></title>
    <?php if ($desc !== ''): ?><meta name="description" content="<?= h($desc) ?>"><?php endif; ?>
    <link rel="canonical" href="<?= h($canonical) ?>">
</head>
<body>
<article class="venue-public">
    <header>
        <h1><?= h($title) ?></h1>
        <?php if ($hasLinked): ?>
            <p class="venue-linked-line">
                <a href="<?= h(events_helyszin_megjelenit_url($linkedSlug)) ?>"><?= h($linkedName) ?></a>
            </p>
        <?php endif; ?>
        <?php if ($addrLine !== ''): ?>
            <p class="venue-address-line"><?= nl2br(h($addrLine)) ?></p>
        <?php endif; ?>
    </header>
    <?php $body = trim($safeVenueBody); ?>
    <?php if ($body !== ''): ?>
        <div class="venue-body">
            <?= $body ?>
        </div>
    <?php endif; ?>
</article>
</body>
</html>
