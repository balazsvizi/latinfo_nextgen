<?php
declare(strict_types=1);
?>
<script>
(function () {
    if (typeof L === 'undefined') return;

    var wrap = document.getElementById('venue-edit-map-preview');
    var host = document.getElementById('venue-edit-map-preview-host');
    var latIn = document.getElementById('venue_latitude');
    var lngIn = document.getElementById('venue_longitude');
    var statusEl = document.getElementById('venue-edit-map-status');
    if (!wrap || !host || !latIn || !lngIn) return;

    var previewMap = null;
    var previewMarker = null;
    var previewPin = null;
    var syncTimer = null;

    function parseCoords() {
        var lt = String(latIn.value || '').trim().replace(',', '.');
        var lg = String(lngIn.value || '').trim().replace(',', '.');
        if (lt === '' || lg === '') return null;
        var la = parseFloat(lt);
        var lo = parseFloat(lg);
        if (isNaN(la) || isNaN(lo)) return null;
        if (la < -90 || la > 90 || lo < -180 || lo > 180) return null;
        return { lat: la, lng: lo };
    }

    function setStatus(hasCoords) {
        if (!statusEl) return;
        statusEl.textContent = hasCoords
            ? 'GPS beállítva'
            : 'Nincs GPS — a nyilvános térkép nem jelenik meg';
        statusEl.classList.toggle('venue-edit-map-status--muted', !hasCoords);
    }

    function destroyPreviewMap() {
        if (previewMarker && previewMap) {
            try { previewMap.removeLayer(previewMarker); } catch (e1) { /* ignore */ }
        }
        previewMarker = null;
        if (previewMap) {
            try { previewMap.remove(); } catch (e2) { /* ignore */ }
        }
        previewMap = null;
        previewPin = null;
    }

    function ensurePreviewMap() {
        if (previewMap) return;
        previewPin = L.divIcon({
            className: 'events-venue-map-pin',
            html: '<div class="events-venue-map-pin__dot"></div>',
            iconSize: [26, 26],
            iconAnchor: [13, 13]
        });
        previewMap = L.map(host, { scrollWheelZoom: false, zoomControl: true });
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OSM &copy; CARTO',
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(previewMap);
    }

    function placePreviewMarker(lat, lng) {
        if (!previewMap) return;
        if (!previewMarker) {
            previewMarker = L.marker([lat, lng], { draggable: false, icon: previewPin }).addTo(previewMap);
        } else {
            previewMarker.setLatLng([lat, lng]);
        }
        previewMap.setView([lat, lng], 15);
        previewMap.invalidateSize();
    }

    function syncPreview() {
        var coords = parseCoords();
        var hasCoords = coords !== null;
        setStatus(hasCoords);
        wrap.classList.toggle('is-hidden', !hasCoords);
        wrap.setAttribute('aria-hidden', hasCoords ? 'false' : 'true');
        if (!hasCoords) {
            destroyPreviewMap();
            host.innerHTML = '';
            return;
        }
        ensurePreviewMap();
        placePreviewMarker(coords.lat, coords.lng);
        window.setTimeout(function () {
            if (previewMap) previewMap.invalidateSize();
        }, 80);
    }

    function scheduleSync() {
        window.clearTimeout(syncTimer);
        syncTimer = window.setTimeout(syncPreview, 180);
    }

    latIn.addEventListener('input', scheduleSync);
    latIn.addEventListener('change', syncPreview);
    lngIn.addEventListener('input', scheduleSync);
    lngIn.addEventListener('change', syncPreview);
    document.addEventListener('venue-coords-updated', syncPreview);

    syncPreview();
})();
</script>
