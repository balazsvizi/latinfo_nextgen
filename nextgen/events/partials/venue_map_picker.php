<?php
declare(strict_types=1);
?>
<link rel="stylesheet" href="<?= h(events_leaflet_css_url()) ?>" crossorigin="anonymous">
<script src="<?= h(events_leaflet_js_url()) ?>" crossorigin="anonymous"></script>
<dialog class="events-venue-map-dialog" id="events-venue-map-dialog">
    <div class="events-venue-map-dialog__head">
        <h3 class="events-venue-map-dialog__title">Helyszín a térképen</h3>
        <p class="events-venue-map-dialog__lead">A <strong>Javaslat a cím alapján</strong> gomb megkeresi a címet; utána kattintással vagy jelölő húzással pontosíthatsz. Az <strong>Alkalmazás</strong> bemásolja a GPS mezőkbe.</p>
    </div>
    <div class="events-venue-map-dialog__tools">
        <div class="form-group">
            <label for="events-venue-map-query">Keresendő cím</label>
            <textarea id="events-venue-map-query" rows="3" maxlength="500" autocomplete="street-address" placeholder="A cím mezőkből indul; itt szabadon módosíthatod."></textarea>
        </div>
        <div class="events-venue-map-dialog__tool-row">
            <button type="button" class="btn btn-secondary btn-sm" id="events-venue-map-suggest">Javaslat a cím alapján</button>
            <span id="events-venue-map-coords" class="events-venue-map-dialog__coords" aria-live="polite"></span>
        </div>
    </div>
    <div id="events-venue-map-host" class="events-venue-map-dialog__map" role="presentation"></div>
    <p id="events-venue-map-msg" class="events-venue-map-dialog__msg" role="alert" hidden></p>
    <div class="events-venue-map-dialog__foot">
        <button type="button" class="btn btn-secondary" id="events-venue-map-cancel">Mégse</button>
        <button type="button" class="btn btn-primary" id="events-venue-map-apply">Alkalmazás a GPS mezőkbe</button>
    </div>
</dialog>
<script>
(function () {
    var dialog = document.getElementById('events-venue-map-dialog');
    var mapEl = document.getElementById('events-venue-map-host');
    var btnOpen = document.getElementById('venue-map-open');
    var btnSuggest = document.getElementById('events-venue-map-suggest');
    var btnCancel = document.getElementById('events-venue-map-cancel');
    var btnApply = document.getElementById('events-venue-map-apply');
    var elCoords = document.getElementById('events-venue-map-coords');
    var elQuery = document.getElementById('events-venue-map-query');
    var elMsg = document.getElementById('events-venue-map-msg');
    if (!dialog || !mapEl || !btnOpen || typeof L === 'undefined') return;

    var map = null;
    var marker = null;
    var pinIcon = null;

    function field(id) { return document.getElementById(id); }

    function hideMsg() {
        if (!elMsg) return;
        elMsg.textContent = '';
        elMsg.hidden = true;
    }

    function showMsg(text) {
        if (!elMsg) return;
        elMsg.textContent = text;
        elMsg.hidden = false;
    }

    function fmtCoord(n) {
        var s = Number(n).toFixed(7);
        s = s.replace(/\.?0+$/, '');
        return s === '' ? '0' : s;
    }

    function updateCoordLabel(lat, lng) {
        if (!elCoords) return;
        elCoords.textContent = (lat != null && lng != null && !isNaN(lat) && !isNaN(lng))
            ? 'Szélesség: ' + fmtCoord(lat) + ' · Hosszúság: ' + fmtCoord(lng)
            : '';
    }

    function readGeo() {
        var addr = field('venue_address');
        var city = field('venue_city');
        var postal = field('venue_postal_code');
        var country = field('venue_country');
        var latIn = field('venue_latitude');
        var lngIn = field('venue_longitude');
        return {
            address: addr ? String(addr.value || '').trim() : '',
            city: city ? String(city.value || '').trim() : '',
            postal: postal ? String(postal.value || '').trim() : '',
            country: country ? String(country.value || '').trim() : '',
            latIn: latIn,
            lngIn: lngIn
        };
    }

    function countryCodeForNominatim(countryName) {
        var c = String(countryName || '').trim().toLowerCase();
        if (!c || c === 'magyarország' || c === 'hungary' || c === 'hu') return 'hu';
        if (c === 'österreich' || c === 'austria' || c === 'at') return 'at';
        if (c === 'slovensko' || c === 'slovakia' || c === 'szlovákia' || c === 'sk') return 'sk';
        if (c === 'românia' || c === 'romania' || c === 'románia' || c === 'ro') return 'ro';
        if (c === 'deutschland' || c === 'germany' || c === 'németország' || c === 'de') return 'de';
        return '';
    }

    function buildGeocodeQuery(g) {
        var parts = [];
        if (g.address) parts.push(g.address);
        var cityLine = (g.postal ? g.postal + ' ' : '') + g.city;
        cityLine = cityLine.trim();
        if (cityLine) parts.push(cityLine);
        if (g.country) parts.push(g.country);
        return parts.join(', ').trim();
    }

    function syncQueryFromFields() {
        if (!elQuery) return;
        elQuery.value = buildGeocodeQuery(readGeo());
    }

    function parseExistingCoords(g) {
        var lt = g.latIn && g.latIn.value ? String(g.latIn.value).trim().replace(',', '.') : '';
        var lg = g.lngIn && g.lngIn.value ? String(g.lngIn.value).trim().replace(',', '.') : '';
        if (lt === '' || lg === '') return null;
        var la = parseFloat(lt);
        var lo = parseFloat(lg);
        if (isNaN(la) || isNaN(lo)) return null;
        if (la < -90 || la > 90 || lo < -180 || lo > 180) return null;
        return { lat: la, lng: lo };
    }

    function destroyMap() {
        if (marker && map) {
            try { map.removeLayer(marker); } catch (e1) { /* ignore */ }
        }
        marker = null;
        if (map) {
            try { map.remove(); } catch (e2) { /* ignore */ }
        }
        map = null;
        pinIcon = null;
    }

    function buildLeafletMap() {
        destroyMap();
        pinIcon = L.divIcon({
            className: 'events-venue-map-pin',
            html: '<div class="events-venue-map-pin__dot"></div>',
            iconSize: [26, 26],
            iconAnchor: [13, 13]
        });
        map = L.map(mapEl, { scrollWheelZoom: true, zoomControl: true });
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OSM &copy; CARTO',
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(map);
        map.on('click', function (e) {
            placeOrMoveMarker(e.latlng.lat, e.latlng.lng);
        });
        map.invalidateSize();
    }

    function afterDialogPaint(done) {
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                window.setTimeout(done, 10);
            });
        });
    }

    function removeMarker() {
        if (marker && map) map.removeLayer(marker);
        marker = null;
    }

    function placeOrMoveMarker(lat, lng) {
        if (!map) return;
        if (!marker) {
            marker = L.marker([lat, lng], { draggable: true, icon: pinIcon }).addTo(map);
            marker.on('dragend', function () {
                var ll = marker.getLatLng();
                updateCoordLabel(ll.lat, ll.lng);
                hideMsg();
            });
        } else {
            marker.setLatLng([lat, lng]);
        }
        updateCoordLabel(lat, lng);
        hideMsg();
    }

    function openPicker() {
        hideMsg();
        updateCoordLabel(null, null);
        syncQueryFromFields();
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }
        var existing = parseExistingCoords(readGeo());
        afterDialogPaint(function () {
            buildLeafletMap();
            if (!map) return;
            removeMarker();
            if (existing) {
                map.setView([existing.lat, existing.lng], 16);
                placeOrMoveMarker(existing.lat, existing.lng);
            } else {
                map.setView([47.2, 19.5], 7);
            }
            map.invalidateSize();
        });
    }

    function closePicker() {
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
        destroyMap();
    }

    function fetchNominatimJson(q, countryCodes2) {
        var params = new URLSearchParams({ format: 'json', limit: '5', q: q });
        if (countryCodes2 && String(countryCodes2).length === 2) {
            params.set('countrycodes', String(countryCodes2).toLowerCase());
        }
        return fetch('https://nominatim.openstreetmap.org/search?' + params.toString(), {
            headers: { Accept: 'application/json', 'Accept-Language': 'hu,en;q=0.8' }
        }).then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        });
    }

    btnOpen.addEventListener('click', openPicker);
    if (btnCancel) btnCancel.addEventListener('click', closePicker);

    if (btnApply) btnApply.addEventListener('click', function () {
        if (!marker) {
            showMsg('Válassz pontot a térképen (kattintás), vagy kérj javaslatot a cím alapján.');
            return;
        }
        var ll = marker.getLatLng();
        var latIn = field('venue_latitude');
        var lngIn = field('venue_longitude');
        if (latIn) latIn.value = fmtCoord(ll.lat);
        if (lngIn) lngIn.value = fmtCoord(ll.lng);
        document.dispatchEvent(new CustomEvent('venue-coords-updated'));
        closePicker();
    });

    if (btnSuggest) btnSuggest.addEventListener('click', function () {
        hideMsg();
        var g = readGeo();
        var q = elQuery && elQuery.value ? String(elQuery.value).trim() : '';
        if (!q) {
            q = buildGeocodeQuery(g);
            if (elQuery) elQuery.value = q;
        }
        if (!q) {
            showMsg('Töltsd ki a cím mezőket, vagy írj be keresendő címet.');
            return;
        }
        var cc = countryCodeForNominatim(g.country);
        btnSuggest.disabled = true;
        var prev = btnSuggest.textContent;
        btnSuggest.textContent = 'Keresés…';
        fetchNominatimJson(q, cc)
            .then(function (arr) {
                if (arr && arr.length) return arr;
                return fetchNominatimJson(q, '');
            })
            .then(function (arr) {
                if (!arr || !arr.length) {
                    showMsg('Nincs találat ehhez a címhez. Pontosítsd a címet, vagy kattints a térképre.');
                    return;
                }
                var la = parseFloat(arr[0].lat);
                var lo = parseFloat(arr[0].lon);
                if (isNaN(la) || isNaN(lo)) {
                    showMsg('Érvénytelen válasz a geokódolótól.');
                    return;
                }
                if (!map) buildLeafletMap();
                if (!map) return;
                map.invalidateSize();
                map.setView([la, lo], 16);
                placeOrMoveMarker(la, lo);
                map.invalidateSize();
            })
            .catch(function () {
                showMsg('A geokódolás nem sikerült. Próbáld újra, vagy állítsd be kattintással a térképen.');
            })
            .finally(function () {
                btnSuggest.disabled = false;
                btnSuggest.textContent = prev;
            });
    });
})();
</script>
