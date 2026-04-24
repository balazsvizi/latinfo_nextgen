<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/venue_request.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Nincs ilyen esemény.';
    exit;
}

$db = getDb();
$stmt = $db->prepare('
    SELECT e.*,
        v.`name` AS `venue_name`,
        v.`slug` AS `venue_slug`,
        v.`country` AS `venue_country`,
        v.`city` AS `venue_city`,
        v.`postal_code` AS `venue_postal_code`,
        v.`address` AS `venue_address`,
        l.`name` AS `venue_linked_name`,
        l.`slug` AS `venue_linked_slug`
    FROM `events_calendar_events` e
    LEFT JOIN `events_venues` v ON v.`id` = e.`venue_id`
    LEFT JOIN `events_venues` l ON l.`id` = v.`linked_venue_id`
    WHERE e.`event_slug` = ? AND e.`event_status` = ?
    LIMIT 1
');
$stmt->execute([$slug, events_public_post_status()]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    ?><!DOCTYPE html>
<html lang="hu">
<head><meta charset="UTF-8"><title>Nincs ilyen esemény</title></head>
<body><p>Nincs ilyen esemény.</p></body>
</html><?php
    exit;
}

$venueName = trim((string) ($event['venue_name'] ?? ''));
$venueSlug = trim((string) ($event['venue_slug'] ?? ''));
$showVenue = $venueName !== '' || $venueSlug !== '';
$venueAddrLine = '';
if ($showVenue) {
    $venueAddrLine = events_venue_address_summary([
        'postal_code' => (string) ($event['venue_postal_code'] ?? ''),
        'city' => (string) ($event['venue_city'] ?? ''),
        'address' => (string) ($event['venue_address'] ?? ''),
        'country' => (string) ($event['venue_country'] ?? ''),
    ]);
}
$venueLinkedName = trim((string) ($event['venue_linked_name'] ?? ''));
$venueLinkedSlug = trim((string) ($event['venue_linked_slug'] ?? ''));
$venueHasLinked = $venueLinkedName !== '' && $venueLinkedSlug !== '';

try {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $ipHash = $ip !== '' ? hash('sha256', $ip . '|' . SITE_NAME) : null;
    $ins = $db->prepare('INSERT INTO `events_calendar_event_views` (`esemény_id`, `ip_hash`) VALUES (?, ?)');
    $ins->execute([(int) $event['id'], $ipHash]);
} catch (Throwable $e) {
    // Megtekintés napló opcionális – ne törjük a megjelenítést
}

$canonical = events_public_canonical_url($event['event_slug']);
$title = $event['event_name'];
$desc = mb_substr(trim(strip_tags($event['event_content'])), 0, 160, 'UTF-8');
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> – <?= h(SITE_NAME) ?></title>
    <meta name="description" content="<?= h($desc) ?>">
    <link rel="canonical" href="<?= h($canonical) ?>">
</head>
<body>
<article>
    <header>
        <h1><?= h($event['event_name']) ?></h1>
        <p>
            <?php if (!empty($event['event_start'])): ?>
                <?php $startTs = strtotime((string) $event['event_start']); ?>
                <time datetime="<?= h((string) $event['event_start']) ?>"><?= $startTs ? h(date('Y-m-d', $startTs)) : h((string) $event['event_start']) ?></time>
                <?php if ($startTs && empty($event['event_allday'])): ?>
                    <?= h(date('H:i', $startTs)) ?>
                <?php endif; ?>
                <?php if (!empty($event['event_end'])): ?>
                    <?php $endTs = strtotime((string) $event['event_end']); ?>
                    – <time datetime="<?= h((string) $event['event_end']) ?>"><?= $endTs ? h(date('Y-m-d', $endTs)) : h((string) $event['event_end']) ?></time>
                    <?php if ($endTs && empty($event['event_allday'])): ?>
                        <?= h(date('H:i', $endTs)) ?>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </p>
        <?php if ($showVenue): ?>
            <div class="event-venue">
                <p class="event-venue-title">
                    <strong>Helyszín:</strong>
                    <?php if ($venueSlug !== ''): ?>
                        <a href="<?= h(events_helyszin_megjelenit_url($venueSlug)) ?>"><?= $venueName !== '' ? h($venueName) : h($venueSlug) ?></a>
                    <?php else: ?>
                        <?= $venueName !== '' ? h($venueName) : '' ?>
                    <?php endif; ?>
                </p>
                <?php if ($venueHasLinked): ?>
                    <p class="event-venue-linked">
                        <a href="<?= h(events_helyszin_megjelenit_url($venueLinkedSlug)) ?>"><?= h($venueLinkedName) ?></a>
                    </p>
                <?php endif; ?>
                <?php if ($venueAddrLine !== ''): ?>
                    <p class="event-venue-address"><?= nl2br(h($venueAddrLine)) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($event['event_url'])): ?>
            <p><a href="<?= h($event['event_url']) ?>">További információ / jegy</a></p>
        <?php endif; ?>
    </header>
    <div class="event-body">
        <?= $event['event_content'] ?>
    </div>
</article>
</body>
</html>
