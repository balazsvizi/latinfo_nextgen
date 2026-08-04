<?php
declare(strict_types=1);

/**
 * Közzétett események XML sitemap-je (slug URL-ek).
 * Ajánlott publikus útvonal: /events/sitemap.xml
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_public_lang.php';

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex', true);
header('Cache-Control: public, max-age=3600');

$db = getDb();
$stmt = $db->prepare('
    SELECT `event_slug`, `event_start`
    FROM `events_calendar_events`
    WHERE `event_status` = ?
      AND `event_slug` IS NOT NULL
      AND TRIM(`event_slug`) <> \'\'
    ORDER BY `event_start` IS NULL, `event_start` DESC, `id` DESC
');
$stmt->execute([events_public_post_status()]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
    . ' xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

foreach ($rows as $row) {
    $slug = trim((string) ($row['event_slug'] ?? ''));
    if ($slug === '') {
        continue;
    }
    $locHu = events_absolute_url(events_public_event_page_url($slug, 'hu'));
    $locEn = events_absolute_url(events_public_event_page_url($slug, 'en'));
    $lastmod = '';
    $startRaw = trim((string) ($row['event_start'] ?? ''));
    if ($startRaw !== '') {
        $ts = strtotime($startRaw);
        if ($ts !== false) {
            $lastmod = date('Y-m-d', $ts);
        }
    }

    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($locHu, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
    if ($lastmod !== '') {
        echo '    <lastmod>' . htmlspecialchars($lastmod, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</lastmod>\n";
    }
    echo '    <xhtml:link rel="alternate" hreflang="hu" href="'
        . htmlspecialchars($locHu, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "\" />\n";
    echo '    <xhtml:link rel="alternate" hreflang="en" href="'
        . htmlspecialchars($locEn, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "\" />\n";
    echo '    <xhtml:link rel="alternate" hreflang="x-default" href="'
        . htmlspecialchars($locHu, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "\" />\n";
    echo "  </url>\n";
}

echo '</urlset>';
