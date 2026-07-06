<?php
declare(strict_types=1);

/**
 * Publikus főoldal – szűrt események térképe (Leaflet + cluster).
 *
 * @var array<string, string> $D
 * @var array{
 *     markers: list<array<string, mixed>>,
 *     geocode_jobs?: list<array<string, mixed>>,
 *     skipped: int,
 *     pending?: int,
 *     total: int
 * } $mapPayload
 */
$mapMarkers = $mapPayload['markers'] ?? [];
$mapGeocodeJobs = $mapPayload['geocode_jobs'] ?? [];
$mapMarkerCount = count($mapMarkers);
$mapSkipped = (int) ($mapPayload['skipped'] ?? 0);
$mapPending = (int) ($mapPayload['pending'] ?? 0);
$mapTotal = (int) ($mapPayload['total'] ?? 0);
$mapId = 'home-events-map';
$mapJson = json_encode(
    ['markers' => $mapMarkers, 'geocode_jobs' => $mapGeocodeJobs],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if ($mapJson === false) {
    $mapJson = '{"markers":[],"geocode_jobs":[]}';
}
$mapShowEmpty = $mapMarkerCount === 0 && $mapPending === 0;
?>
<section class="home-public__map" aria-label="<?= h((string) ($D['map_aria'] ?? 'Események térképen')) ?>">
    <div class="home-public__map-stats" role="status">
        <div class="home-public__map-stat">
            <span class="home-public__map-stat-value" id="<?= h($mapId) ?>-pin-count"><?= (int) $mapMarkerCount ?></span>
            <span class="home-public__map-stat-label"><?= h((string) ($D['map_stat_pins'] ?? 'térképen')) ?></span>
        </div>
        <?php if ($mapTotal > 0): ?>
            <div class="home-public__map-stat home-public__map-stat--muted">
                <span class="home-public__map-stat-value"><?= (int) $mapTotal ?></span>
                <span class="home-public__map-stat-label"><?= h((string) ($D['map_stat_filtered'] ?? 'szűrt esemény')) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($mapPending > 0): ?>
            <p class="home-public__map-hint home-public__map-hint--pending" id="<?= h($mapId) ?>-geocode-pending">
                <?= h(sprintf((string) ($D['map_geocode_pending'] ?? '%d esemény cím alapján töltődik a térképre…'), $mapPending)) ?>
            </p>
        <?php endif; ?>
        <?php if ($mapSkipped > 0): ?>
            <p class="home-public__map-hint"><?= h(sprintf((string) ($D['map_no_coords_hint'] ?? '%d eseménynek nincs helyszín-címe vagy GPS koordinátája.'), $mapSkipped)) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($mapShowEmpty): ?>
        <p class="home-public__map-empty"><?= h((string) ($D['map_empty'] ?? 'Nincs megjeleníthető esemény a jelenlegi szűréshez.')) ?></p>
    <?php endif; ?>

    <div class="home-public__map-frame">
        <link rel="stylesheet" href="<?= h(events_leaflet_css_url()) ?>" crossorigin="anonymous">
        <link rel="stylesheet" href="<?= h(events_leaflet_marker_cluster_css_url()) ?>" crossorigin="anonymous">
        <link rel="stylesheet" href="<?= h(events_leaflet_marker_cluster_default_css_url()) ?>" crossorigin="anonymous">
        <div
            id="<?= h($mapId) ?>"
            class="home-public__map-host venue-public-map__host"
            role="region"
            aria-label="<?= h((string) ($D['map_host_aria'] ?? 'Interaktív térkép')) ?>"
        ></div>
        <p class="venue-public-map__attrib home-public__map-attrib">
            © <a href="https://www.openstreetmap.org/copyright" rel="noopener noreferrer">OpenStreetMap</a>
            · © <a href="https://carto.com/attributions" rel="noopener noreferrer">CARTO</a>
        </p>
    </div>

    <script type="application/json" id="<?= h($mapId) ?>-json"><?= $mapJson ?></script>
    <script src="<?= h(events_leaflet_js_url()) ?>" crossorigin="anonymous"></script>
    <script src="<?= h(events_leaflet_marker_cluster_js_url()) ?>" crossorigin="anonymous"></script>
    <script>
    (function () {
        var mapId = <?= json_encode($mapId, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
        var el = document.getElementById(mapId);
        var raw = document.getElementById(mapId + '-json');
        var pinCountEl = document.getElementById(mapId + '-pin-count');
        var geocodePendingEl = document.getElementById(mapId + '-geocode-pending');
        var geocodeDoneText = <?= json_encode((string) ($D['map_geocode_done'] ?? 'Cím alapú helymeghatározás kész.'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
        if (!el || !raw || typeof L === 'undefined') return;

        var data;
        try { data = JSON.parse(raw.textContent || '{}'); } catch (e) { return; }
        var markers = Array.isArray(data.markers) ? data.markers : [];
        var geocodeJobs = Array.isArray(data.geocode_jobs) ? data.geocode_jobs : [];
        var pinCount = markers.length;
        var geocodeDelayMs = 1100;

        function esc(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function hasCoords(m) {
            var la = parseFloat(m.lat);
            var lo = parseFloat(m.lng);
            return !isNaN(la) && !isNaN(lo) && la >= -90 && la <= 90 && lo >= -180 && lo <= 180;
        }

        function markerIcon(accent) {
            var color = typeof accent === 'string' && accent ? accent : '#6d8f63';
            return L.divIcon({
                className: 'home-public-map-pin',
                html: '<div class="home-public-map-pin__dot" style="background:' + esc(color) + ';border-color:' + esc(color) + ';"></div>',
                iconSize: [28, 28],
                iconAnchor: [14, 14],
                popupAnchor: [0, -12]
            });
        }

        function popupHtml(m) {
            var html = '<div class="home-public-map-popup">';
            if (m.url) {
                html += '<a class="home-public-map-popup__title" href="' + esc(m.url) + '">' + esc(m.title) + '</a>';
            } else {
                html += '<div class="home-public-map-popup__title">' + esc(m.title) + '</div>';
            }
            if (m.date) {
                html += '<div class="home-public-map-popup__meta">' + esc(m.date) + '</div>';
            }
            if (m.venue) {
                html += '<div class="home-public-map-popup__venue">' + esc(m.venue) + '</div>';
            }
            if (m.address) {
                html += '<div class="home-public-map-popup__addr">' + esc(m.address) + '</div>';
            }
            html += '</div>';
            return html;
        }

        var map = L.map(el, {
            scrollWheelZoom: false,
            zoomControl: true,
            attributionControl: true
        });
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OSM &copy; CARTO',
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(map);

        var cluster = (typeof L.markerClusterGroup === 'function')
            ? L.markerClusterGroup({
                maxClusterRadius: 45,
                showCoverageOnHover: false,
                spiderfyOnMaxZoom: true
            })
            : L.layerGroup();

        var bounds = [];

        function updatePinCount() {
            if (pinCountEl) {
                pinCountEl.textContent = String(pinCount);
            }
        }

        function fitMapToBounds() {
            if (bounds.length === 1) {
                map.setView(bounds[0], 13);
            } else if (bounds.length > 1) {
                map.fitBounds(bounds, { padding: [36, 36], maxZoom: 14 });
            } else {
                map.setView([47.1625, 19.5033], 7);
            }
        }

        function addMarker(m, la, lo) {
            var marker = L.marker([la, lo], { icon: markerIcon(m.accent) });
            marker.bindPopup(popupHtml(m), { maxWidth: 300, minWidth: 180 });
            cluster.addLayer(marker);
            bounds.push([la, lo]);
            pinCount++;
            updatePinCount();
        }

        markers.forEach(function (m) {
            if (!hasCoords(m)) return;
            addMarker(m, parseFloat(m.lat), parseFloat(m.lng));
        });
        map.addLayer(cluster);
        fitMapToBounds();

        function fetchNominatim(q, countryCode) {
            var params = new URLSearchParams({ format: 'json', limit: '5', q: q });
            if (countryCode && String(countryCode).length === 2) {
                params.set('countrycodes', String(countryCode).toLowerCase());
            }
            return fetch('https://nominatim.openstreetmap.org/search?' + params.toString(), {
                headers: { Accept: 'application/json', 'Accept-Language': 'hu,en;q=0.8' }
            }).then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            });
        }

        var geocodeIndex = 0;
        var hadGeocodeBoundsUpdate = false;

        function finishGeocodeUi() {
            if (!geocodePendingEl) return;
            geocodePendingEl.textContent = geocodeDoneText;
            geocodePendingEl.classList.remove('home-public__map-hint--pending');
        }

        function processNextGeocode() {
            if (geocodeIndex >= geocodeJobs.length) {
                if (hadGeocodeBoundsUpdate) {
                    fitMapToBounds();
                    map.invalidateSize();
                }
                finishGeocodeUi();
                return;
            }

            var job = geocodeJobs[geocodeIndex++];
            var query = job && job.query ? String(job.query) : '';
            var countryCode = job && job.country_code ? String(job.country_code) : '';
            var jobMarkers = job && Array.isArray(job.markers) ? job.markers : [];

            function scheduleNext() {
                setTimeout(processNextGeocode, geocodeDelayMs);
            }

            if (!query || jobMarkers.length === 0) {
                scheduleNext();
                return;
            }

            fetchNominatim(query, countryCode)
                .then(function (arr) {
                    if (arr && arr.length) return arr;
                    return fetchNominatim(query, '');
                })
                .then(function (arr) {
                    if (!arr || !arr.length) return;
                    var la = parseFloat(arr[0].lat);
                    var lo = parseFloat(arr[0].lon);
                    if (isNaN(la) || isNaN(lo)) return;
                    jobMarkers.forEach(function (m) {
                        addMarker(m, la, lo);
                    });
                    hadGeocodeBoundsUpdate = true;
                })
                .catch(function () { /* csendes hiba – következő cím */ })
                .finally(scheduleNext);
        }

        if (geocodeJobs.length > 0) {
            setTimeout(processNextGeocode, 200);
        }

        function invalidate() { map.invalidateSize(); }
        setTimeout(invalidate, 200);
        setTimeout(invalidate, 600);
        window.addEventListener('resize', invalidate);
        var panel = document.getElementById('home-filters-panel');
        if (panel) {
            panel.addEventListener('toggle', function () {
                setTimeout(invalidate, 120);
            });
        }
    })();
    </script>
</section>
