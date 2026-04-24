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

$huMonths = [
    1 => 'január', 2 => 'február', 3 => 'március', 4 => 'április', 5 => 'május', 6 => 'június',
    7 => 'július', 8 => 'augusztus', 9 => 'szeptember', 10 => 'október', 11 => 'november', 12 => 'december',
];
$fmtDayHu = static function (int $ts) use ($huMonths): string {
    return (int) date('Y', $ts) . '. ' . $huMonths[(int) date('n', $ts)] . ' ' . (int) date('j', $ts) . '.';
};

$allday = !empty($event['event_allday']);
$tsStart = !empty($event['event_start']) ? strtotime((string) $event['event_start']) : false;
$tsEnd = !empty($event['event_end']) ? strtotime((string) $event['event_end']) : false;

$dateLines = [];
if ($tsStart) {
    if ($tsEnd && date('Y-m-d', $tsStart) === date('Y-m-d', $tsEnd)) {
        $dateLines[] = $fmtDayHu($tsStart);
        if (!$allday) {
            $dateLines[] = date('H:i', $tsStart) . ' – ' . date('H:i', $tsEnd);
        }
    } elseif ($tsEnd) {
        $dateLines[] = $fmtDayHu($tsStart) . ($allday ? '' : ' ' . date('H:i', $tsStart));
        $dateLines[] = '– ' . $fmtDayHu($tsEnd) . ($allday ? '' : ' ' . date('H:i', $tsEnd));
    } else {
        $line = $fmtDayHu($tsStart);
        if (!$allday) {
            $line .= ' ' . date('H:i', $tsStart);
        }
        $dateLines[] = $line;
    }
}

$cfRaw = $event['event_cost_from'] ?? null;
$ctRaw = $event['event_cost_to'] ?? null;
$cf = $cfRaw !== null && $cfRaw !== '' ? (float) $cfRaw : null;
$ct = $ctRaw !== null && $ctRaw !== '' ? (float) $ctRaw : null;
$costText = null;
if ($cf !== null || $ct !== null) {
    $fmtFt = static function (float $x): string {
        $decimals = abs($x - round($x)) < 0.000001 ? 0 : 2;

        return number_format($x, $decimals, ',', ' ');
    };
    if ($cf !== null && $ct !== null) {
        $costText = abs($cf - $ct) < 0.000001
            ? $fmtFt($cf) . ' Ft'
            : $fmtFt($cf) . ' – ' . $fmtFt((float) $ct) . ' Ft';
    } elseif ($cf !== null) {
        $costText = $fmtFt($cf) . ' Ft-tól';
    } else {
        $costText = 'Legfeljebb ' . $fmtFt((float) $ct) . ' Ft';
    }
}

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
$cssUrl = events_url('assets/event_public.css');

$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Event',
    'name' => (string) $event['event_name'],
    'url' => $canonical,
];
if (!empty($event['event_start']) && $tsStart) {
    $jsonLd['startDate'] = date('c', $tsStart);
}
if (!empty($event['event_end']) && $tsEnd) {
    $jsonLd['endDate'] = date('c', $tsEnd);
}
if ($showVenue && ($venueName !== '' || $venueAddrLine !== '')) {
    $jsonLd['location'] = [
        '@type' => 'Place',
        'name' => $venueName !== '' ? $venueName : $venueSlug,
    ];
    if ($venueAddrLine !== '') {
        $jsonLd['location']['address'] = $venueAddrLine;
    }
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2d2621">
    <title><?= h((string) $title) ?> – <?= h(SITE_NAME) ?></title>
    <meta name="description" content="<?= h($desc) ?>">
    <link rel="canonical" href="<?= h($canonical) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;0,8..60,700;1,8..60,400&display=swap" rel="stylesheet">
    <?php require dirname(__DIR__) . '/nextgen/includes/favicon_head.php'; ?>
    <link rel="stylesheet" href="<?= h($cssUrl) ?>">
    <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</head>
<body class="event-public-page">
<div class="event-shell">
<article class="event-public">
    <header class="event-public__hero">
        <div class="event-public__hero-inner">
            <p class="event-public__eyebrow">Esemény</p>
            <h1 class="event-public__title"><?= h((string) $event['event_name']) ?></h1>
            <div class="event-public__badges">
                <?php if ($allday && $tsStart): ?>
                    <span class="event-badge">Egész napos</span>
                <?php endif; ?>
                <?php if (!empty($event['event_latinfohu_partner'])): ?>
                    <span class="event-badge event-badge--accent">Latinfo.hu partner</span>
                <?php endif; ?>
            </div>

            <?php
            $showMetaBlock = $dateLines !== [] || $costText !== null || $showVenue;
            ?>
            <?php if ($showMetaBlock): ?>
            <div class="event-meta">
                <?php if ($dateLines !== []): ?>
                    <div class="event-meta__card event-meta__card--wide">
                        <div class="event-meta__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        </div>
                        <div>
                            <p class="event-meta__label">Időpont</p>
                            <div class="event-meta__value">
                                <?php foreach ($dateLines as $i => $line): ?>
                                    <?php if ($i > 0): ?>
                                        <span class="event-meta__value--muted event-meta__value-line2"><?= h($line) ?></span>
                                    <?php else: ?>
                                        <?= h($line) ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($costText !== null): ?>
                    <div class="event-meta__card">
                        <div class="event-meta__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </div>
                        <div>
                            <p class="event-meta__label">Belépő</p>
                            <p class="event-meta__value"><?= h($costText) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($showVenue): ?>
                    <div class="event-meta__card event-meta__card--wide">
                        <div class="event-meta__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        </div>
                        <div>
                            <p class="event-meta__label">Helyszín</p>
                            <p class="event-meta__value">
                                <?php if ($venueSlug !== ''): ?>
                                    <a class="event-venue-name-link" href="<?= h(events_helyszin_megjelenit_url($venueSlug)) ?>"><?= $venueName !== '' ? h($venueName) : h($venueSlug) ?></a>
                                <?php else: ?>
                                    <?= $venueName !== '' ? h($venueName) : '' ?>
                                <?php endif; ?>
                            </p>
                            <?php if ($venueHasLinked): ?>
                                <p class="event-meta__value event-meta__value--muted event-venue-linked-p">
                                    <a class="event-venue-sub-link" href="<?= h(events_helyszin_megjelenit_url($venueLinkedSlug)) ?>"><?= h($venueLinkedName) ?></a>
                                </p>
                            <?php endif; ?>
                            <?php if ($venueAddrLine !== ''): ?>
                                <p class="event-meta__value event-meta__value--muted event-venue-addr-p"><?= nl2br(h($venueAddrLine)) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($event['event_url'])): ?>
                <div class="event-cta-wrap">
                    <a class="event-cta" href="<?= h((string) $event['event_url']) ?>" target="_blank" rel="noopener noreferrer">
                        <span>További információ vagy jegy</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="event-public__content">
        <div class="event-body">
            <?= $event['event_content'] ?>
        </div>
    </div>

    <footer class="event-public__footer">
        <p class="event-site-line">
            <a href="<?= h(site_url('/')) ?>"><?= h(SITE_NAME) ?></a>
        </p>
    </footer>
</article>
</div>
</body>
</html>
