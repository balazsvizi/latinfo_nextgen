<?php
declare(strict_types=1);

/**
 * Nyilvános helyszín térkép – popup (lazy Leaflet init).
 *
 * @var float $mapLat
 * @var float $mapLng
 * @var string $mapTitle
 * @var string $mapAddress
 * @var string $mapHeading
 * @var string $mapAriaLabel
 * @var string $mapCloseLabel
 * @var string $mapDialogId
 */

$mapLat = (float) ($mapLat ?? 0);
$mapLng = (float) ($mapLng ?? 0);
$mapTitle = trim((string) ($mapTitle ?? ''));
$mapAddress = trim((string) ($mapAddress ?? ''));
$mapHeading = trim((string) ($mapHeading ?? 'Térkép'));
$mapAriaLabel = trim((string) ($mapAriaLabel ?? 'Helyszín a térképen'));
$mapCloseLabel = trim((string) ($mapCloseLabel ?? 'Bezárás'));
$mapDialogId = trim((string) ($mapDialogId ?? 'event-venue-map-dialog'));

if ($mapLat < -90 || $mapLat > 90 || $mapLng < -180 || $mapLng > 180) {
    return;
}

$mapHostId = $mapDialogId . '-host';
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
<link rel="stylesheet" href="<?= h(events_leaflet_css_url()) ?>" crossorigin="anonymous">
<dialog class="venue-public-map-dialog" id="<?= h($mapDialogId) ?>" aria-labelledby="<?= h($mapDialogId) ?>-title">
    <div class="venue-public-map-dialog__sheet">
        <header class="venue-public-map-dialog__head">
            <h2 class="venue-public-map-dialog__title" id="<?= h($mapDialogId) ?>-title"><?= h($mapHeading) ?></h2>
            <button type="button" class="venue-public-map-dialog__close" data-venue-map-close aria-label="<?= h($mapCloseLabel) ?>">×</button>
        </header>
        <div
            id="<?= h($mapHostId) ?>"
            class="venue-public-map-dialog__map"
            role="region"
            aria-label="<?= h($mapAriaLabel) ?>"
        ></div>
        <p class="venue-public-map-dialog__attrib">
            © <a href="https://www.openstreetmap.org/copyright" rel="noopener noreferrer">OpenStreetMap</a>
            · © <a href="https://carto.com/attributions" rel="noopener noreferrer">CARTO</a>
        </p>
    </div>
</dialog>
<script type="application/json" id="<?= h($mapDialogId) ?>-json"><?= $mapPayload ?></script>
<script src="<?= h(events_leaflet_js_url()) ?>" crossorigin="anonymous"></script>
<script>
(function () {
    var dialogId = <?= json_encode($mapDialogId, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    var hostId = <?= json_encode($mapHostId, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    var dialog = document.getElementById(dialogId);
    var host = document.getElementById(hostId);
    var raw = document.getElementById(dialogId + '-json');
    if (!dialog || !host || !raw) return;

    var mapInstance = null;
    var data;
    try { data = JSON.parse(raw.textContent || '{}'); } catch (e) { return; }

    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function ensureMap() {
        if (typeof L === 'undefined') return;
        var la = parseFloat(data.lat);
        var lo = parseFloat(data.lng);
        if (isNaN(la) || isNaN(lo)) return;

        if (mapInstance) {
            setTimeout(function () { mapInstance.invalidateSize(); }, 80);
            return;
        }

        var pinIcon = L.divIcon({
            className: 'venue-public-map-pin',
            html: '<div class="venue-public-map-pin__dot"></div>',
            iconSize: [26, 26],
            iconAnchor: [13, 13],
            popupAnchor: [0, -11]
        });
        mapInstance = L.map(host, { scrollWheelZoom: true, zoomControl: true, attributionControl: true });
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OSM &copy; CARTO',
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(mapInstance);
        var popup = '<div class="venue-public-map-popup__title">' + esc(data.title) + '</div>';
        if (data.address) {
            popup += '<div class="venue-public-map-popup__addr">' + esc(data.address) + '</div>';
        }
        L.marker([la, lo], { icon: pinIcon }).addTo(mapInstance).bindPopup(popup, { maxWidth: 280 });
        mapInstance.setView([la, lo], 15);
        setTimeout(function () { mapInstance.invalidateSize(); }, 120);
    }

    function openDialog() {
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }
        document.body.classList.add('venue-public-map-dialog-open');
        ensureMap();
    }

    function closeDialog() {
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
        document.body.classList.remove('venue-public-map-dialog-open');
    }

    document.querySelectorAll('[data-venue-map-open="' + dialogId + '"]').forEach(function (btn) {
        btn.addEventListener('click', openDialog);
    });
    dialog.querySelectorAll('[data-venue-map-close]').forEach(function (btn) {
        btn.addEventListener('click', closeDialog);
    });
    dialog.addEventListener('click', function (e) {
        if (e.target === dialog) closeDialog();
    });
    dialog.addEventListener('close', function () {
        document.body.classList.remove('venue-public-map-dialog-open');
    });
    dialog.addEventListener('cancel', function (e) {
        e.preventDefault();
        closeDialog();
    });
})();
</script>
