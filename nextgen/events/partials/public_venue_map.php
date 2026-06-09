<?php
declare(strict_types=1);

/**
 * Nyilvános Leaflet térkép egy helyszínhez.
 *
 * @var float $mapLat
 * @var float $mapLng
 * @var string $mapTitle
 * @var string $mapAddress
 * @var string $mapHeading
 * @var string $mapAriaLabel
 * @var string $mapVariant compact|full
 */

$mapLat = (float) ($mapLat ?? 0);
$mapLng = (float) ($mapLng ?? 0);
$mapTitle = trim((string) ($mapTitle ?? ''));
$mapAddress = trim((string) ($mapAddress ?? ''));
$mapHeading = trim((string) ($mapHeading ?? 'Térkép'));
$mapAriaLabel = trim((string) ($mapAriaLabel ?? 'Helyszín a térképen'));
$mapVariant = ($mapVariant ?? 'full') === 'compact' ? 'compact' : 'full';

if ($mapLat < -90 || $mapLat > 90 || $mapLng < -180 || $mapLng > 180) {
    return;
}

$mapId = 'ev-venue-map-' . substr(md5($mapTitle . '|' . $mapLat . '|' . $mapLng), 0, 10);
$mapPayload = json_encode([
    'lat' => $mapLat,
    'lng' => $mapLng,
    'title' => $mapTitle,
    'address' => $mapAddress,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($mapPayload === false) {
    return;
}
?>
<section class="venue-public-map venue-public-map--<?= h($mapVariant) ?>" aria-labelledby="<?= h($mapId) ?>-heading">
    <?php if ($mapVariant === 'full'): ?>
        <h2 class="venue-public-map__heading" id="<?= h($mapId) ?>-heading"><?= h($mapHeading) ?></h2>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= h(events_leaflet_css_url()) ?>" crossorigin="anonymous">
    <div
        id="<?= h($mapId) ?>"
        class="venue-public-map__host"
        role="region"
        aria-label="<?= h($mapAriaLabel) ?>"
    ></div>
    <p class="venue-public-map__attrib">
        © <a href="https://www.openstreetmap.org/copyright" rel="noopener noreferrer">OpenStreetMap</a>
        · © <a href="https://carto.com/attributions" rel="noopener noreferrer">CARTO</a>
    </p>
    <script type="application/json" id="<?= h($mapId) ?>-json"><?= $mapPayload ?></script>
    <script src="<?= h(events_leaflet_js_url()) ?>" crossorigin="anonymous"></script>
    <script>
    (function () {
        var el = document.getElementById(<?= json_encode($mapId, JSON_HEX_TAG | JSON_HEX_APOS) ?>);
        var raw = document.getElementById(<?= json_encode($mapId . '-json', JSON_HEX_TAG | JSON_HEX_APOS) ?>);
        if (!el || !raw || typeof L === 'undefined') return;
        var data;
        try { data = JSON.parse(raw.textContent || '{}'); } catch (e) { return; }
        var la = parseFloat(data.lat);
        var lo = parseFloat(data.lng);
        if (isNaN(la) || isNaN(lo)) return;
        function esc(s) {
            return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        var map = L.map(el, { scrollWheelZoom: false, zoomControl: true, attributionControl: true });
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OSM &copy; CARTO',
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(map);
        var pinIcon = L.divIcon({
            className: 'venue-public-map-pin',
            html: '<div class="venue-public-map-pin__dot"></div>',
            iconSize: [26, 26],
            iconAnchor: [13, 13],
            popupAnchor: [0, -11]
        });
        var popup = '<div class="venue-public-map-popup__title">' + esc(data.title) + '</div>';
        if (data.address) {
            popup += '<div class="venue-public-map-popup__addr">' + esc(data.address) + '</div>';
        }
        L.marker([la, lo], { icon: pinIcon }).addTo(map).bindPopup(popup, { maxWidth: 280 });
        map.setView([la, lo], <?= $mapVariant === 'compact' ? '15' : '14' ?>);
        setTimeout(function () { map.invalidateSize(); }, 200);
        window.addEventListener('resize', function () { map.invalidateSize(); });
    })();
    </script>
</section>
