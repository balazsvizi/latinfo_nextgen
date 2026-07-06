<?php
declare(strict_types=1);
?>
<link rel="stylesheet" href="<?= h(events_leaflet_css_url()) ?>" crossorigin="anonymous">
<script src="<?= h(events_leaflet_js_url()) ?>" crossorigin="anonymous"></script>
<dialog class="events-venue-map-dialog" id="events-venue-map-dialog">
    <div class="events-venue-map-dialog__head">
        <h3 class="events-venue-map-dialog__title">Helyszín a térképen</h3>
        <p class="events-venue-map-dialog__lead">Kattintással vagy jelölő húzással pontosíthatsz. Az <strong>Alkalmazás</strong> bemásolja a GPS mezőkbe (az inline térkép is automatikusan szinkronizál).</p>
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
    var inlineMapEl = document.getElementById('venue-inline-map-host');
    var dialogMapEl = document.getElementById('events-venue-map-host');
    var btnOpen = document.getElementById('venue-map-open');
    var btnSuggest = document.getElementById('events-venue-map-suggest');
    var btnCancel = document.getElementById('events-venue-map-cancel');
    var btnApply = document.getElementById('events-venue-map-apply');
    var elCoords = document.getElementById('events-venue-map-coords');
    var elQuery = document.getElementById('events-venue-map-query');
    var elInlineMsg = document.getElementById('venue-inline-map-msg');
    var elDialogMsg = document.getElementById('events-venue-map-msg');
    var statusEl = document.getElementById('venue-edit-map-status');
    if (!inlineMapEl || typeof L === 'undefined') return;

    var inlineMap = null;
    var dialogMap = null;
    var inlineMarker = null;
    var dialogMarker = null;
    var pinIcon = null;
    var markerPos = null;

    function field(id) { return document.getElementById(id); }

    function hideMsg(el) {
        if (!el) return;
        el.textContent = '';
        el.hidden = true;
    }

    function showMsg(el, text) {
        if (!el) return;
        el.textContent = text;
        el.hidden = false;
    }

    function fmtCoord(n) {
        var s = Number(n).toFixed(7);
        s = s.replace(/\.?0+$/, '');
        return s === '' ? '0' : s;
    }

    function updateCoordLabel() {
        if (!elCoords) return;
        if (!markerPos) {
            elCoords.textContent = '';
            return;
        }
        elCoords.textContent = 'Szélesség: ' + fmtCoord(markerPos.lat) + ' · Hosszúság: ' + fmtCoord(markerPos.lng);
    }

    function updateStatus() {
        if (!statusEl) return;
        var hasCoords = markerPos !== null;
        statusEl.textContent = hasCoords
            ? 'GPS beállítva'
            : 'Nincs GPS — jelöld meg a térképen vagy kérj javaslatot';
        statusEl.classList.toggle('venue-edit-map-status--muted', !hasCoords);
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

    function makePinIcon() {
        return L.divIcon({
            className: 'events-venue-map-pin',
            html: '<div class="events-venue-map-pin__dot"></div>',
            iconSize: [26, 26],
            iconAnchor: [13, 13]
        });
    }

    function destroyMap(mapRef, markerRef) {
        if (markerRef.current && mapRef.current) {
            try { mapRef.current.removeLayer(markerRef.current); } catch (e1) { /* ignore */ }
        }
        markerRef.current = null;
        if (mapRef.current) {
            try { mapRef.current.remove(); } catch (e2) { /* ignore */ }
        }
        mapRef.current = null;
    }

    var inlineMapRef = { current: null };
    var dialogMapRef = { current: null };
    var inlineMarkerRef = { current: null };
    var dialogMarkerRef = { current: null };

    function buildMap(hostEl, mapRef, markerRef, interactive) {
        destroyMap(mapRef, markerRef);
        if (!pinIcon) pinIcon = makePinIcon();
        var map = L.map(hostEl, { scrollWheelZoom: interactive, zoomControl: true });
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OSM &copy; CARTO',
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(map);
        map.on('click', function (e) {
            setMarkerPosition(e.latlng.lat, e.latlng.lng, true);
        });
        mapRef.current = map;
        map.invalidateSize();
        return map;
    }

    function ensureMarkerOnMap(map, markerRef, lat, lng) {
        if (!map) return;
        if (!markerRef.current) {
            markerRef.current = L.marker([lat, lng], { draggable: true, icon: pinIcon }).addTo(map);
            markerRef.current.on('dragend', function () {
                var ll = markerRef.current.getLatLng();
                setMarkerPosition(ll.lat, ll.lng, true);
            });
        } else {
            markerRef.current.setLatLng([lat, lng]);
        }
    }

    function syncMarkerLayers() {
        if (markerPos) {
            ensureMarkerOnMap(inlineMapRef.current, inlineMarkerRef, markerPos.lat, markerPos.lng);
            ensureMarkerOnMap(dialogMapRef.current, dialogMarkerRef, markerPos.lat, markerPos.lng);
        } else {
            if (inlineMarkerRef.current && inlineMapRef.current) inlineMapRef.current.removeLayer(inlineMarkerRef.current);
            if (dialogMarkerRef.current && dialogMapRef.current) dialogMapRef.current.removeLayer(dialogMarkerRef.current);
            inlineMarkerRef.current = null;
            dialogMarkerRef.current = null;
        }
    }

    function writeCoordsToInputs() {
        var latIn = field('venue_latitude');
        var lngIn = field('venue_longitude');
        if (!markerPos) {
            if (latIn) latIn.value = '';
            if (lngIn) lngIn.value = '';
        } else {
            if (latIn) latIn.value = fmtCoord(markerPos.lat);
            if (lngIn) lngIn.value = fmtCoord(markerPos.lng);
        }
        document.dispatchEvent(new CustomEvent('venue-coords-updated'));
    }

    function setMarkerPosition(lat, lng, writeInputs) {
        markerPos = { lat: lat, lng: lng };
        syncMarkerLayers();
        updateCoordLabel();
        updateStatus();
        hideMsg(elInlineMsg);
        hideMsg(elDialogMsg);
        if (writeInputs) writeCoordsToInputs();
    }

    function initInlineMap() {
        var g = readGeo();
        var existing = parseExistingCoords(g);
        buildMap(inlineMapEl, inlineMapRef, inlineMarkerRef, true);
        if (existing) {
            inlineMapRef.current.setView([existing.lat, existing.lng], 15);
            setMarkerPosition(existing.lat, existing.lng, false);
        } else {
            inlineMapRef.current.setView([47.2, 19.5], 7);
        }
        window.setTimeout(function () {
            if (inlineMapRef.current) inlineMapRef.current.invalidateSize();
        }, 120);
    }

    function afterDialogPaint(done) {
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                window.setTimeout(done, 10);
            });
        });
    }

    function openPicker() {
        if (!dialog) return;
        hideMsg(elDialogMsg);
        syncQueryFromFields();
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }
        afterDialogPaint(function () {
            buildMap(dialogMapEl, dialogMapRef, dialogMarkerRef, true);
            if (markerPos) {
                dialogMapRef.current.setView([markerPos.lat, markerPos.lng], 16);
                ensureMarkerOnMap(dialogMapRef.current, dialogMarkerRef, markerPos.lat, markerPos.lng);
            } else {
                dialogMapRef.current.setView([47.2, 19.5], 7);
            }
            dialogMapRef.current.invalidateSize();
        });
    }

    function closePicker() {
        if (!dialog) return;
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
        destroyMap(dialogMapRef, dialogMarkerRef);
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

    function runGeocodeSuggest(msgTarget) {
        hideMsg(elInlineMsg);
        hideMsg(elDialogMsg);
        var g = readGeo();
        var q = elQuery && elQuery.value ? String(elQuery.value).trim() : '';
        if (!q) {
            q = buildGeocodeQuery(g);
            if (elQuery) elQuery.value = q;
        }
        if (!q) {
            showMsg(msgTarget || elInlineMsg, 'Töltsd ki a cím mezőket, vagy írj be keresendő címet.');
            return Promise.resolve();
        }
        var cc = countryCodeForNominatim(g.country);
        if (btnSuggest) btnSuggest.disabled = true;
        var prev = btnSuggest ? btnSuggest.textContent : '';
        if (btnSuggest) btnSuggest.textContent = 'Keresés…';
        return fetchNominatimJson(q, cc)
            .then(function (arr) {
                if (arr && arr.length) return arr;
                return fetchNominatimJson(q, '');
            })
            .then(function (arr) {
                if (!arr || !arr.length) {
                    showMsg(msgTarget || elInlineMsg, 'Nincs találat ehhez a címhez. Pontosítsd a címet, vagy kattints a térképre.');
                    return;
                }
                var la = parseFloat(arr[0].lat);
                var lo = parseFloat(arr[0].lon);
                if (isNaN(la) || isNaN(lo)) {
                    showMsg(msgTarget || elInlineMsg, 'Érvénytelen válasz a geokódolótól.');
                    return;
                }
                if (!inlineMapRef.current) initInlineMap();
                if (inlineMapRef.current) {
                    inlineMapRef.current.setView([la, lo], 16);
                    inlineMapRef.current.invalidateSize();
                }
                if (dialogMapRef.current) {
                    dialogMapRef.current.setView([la, lo], 16);
                    dialogMapRef.current.invalidateSize();
                }
                setMarkerPosition(la, lo, true);
            })
            .catch(function () {
                showMsg(msgTarget || elInlineMsg, 'A geokódolás nem sikerült. Próbáld újra, vagy állítsd be kattintással a térképen.');
            })
            .finally(function () {
                if (btnSuggest) {
                    btnSuggest.disabled = false;
                    btnSuggest.textContent = prev;
                }
            });
    }

    if (btnOpen) btnOpen.addEventListener('click', openPicker);
    if (btnCancel) btnCancel.addEventListener('click', closePicker);

    if (btnApply) btnApply.addEventListener('click', function () {
        if (!markerPos) {
            showMsg(elDialogMsg, 'Válassz pontot a térképen (kattintás), vagy kérj javaslatot a cím alapján.');
            return;
        }
        writeCoordsToInputs();
        closePicker();
    });

    if (btnSuggest) btnSuggest.addEventListener('click', function () {
        runGeocodeSuggest(elInlineMsg);
    });

    ['venue_address', 'venue_city', 'venue_postal_code', 'venue_country'].forEach(function (id) {
        var el = field(id);
        if (!el) return;
        el.addEventListener('change', syncQueryFromFields);
        el.addEventListener('blur', syncQueryFromFields);
    });

    var latIn = field('venue_latitude');
    var lngIn = field('venue_longitude');
    function syncFromManualCoords() {
        var existing = parseExistingCoords(readGeo());
        if (existing) {
            markerPos = existing;
            syncMarkerLayers();
            updateCoordLabel();
            updateStatus();
            if (inlineMapRef.current) {
                inlineMapRef.current.setView([existing.lat, existing.lng], Math.max(inlineMapRef.current.getZoom(), 14));
            }
        }
    }
    if (latIn) latIn.addEventListener('change', syncFromManualCoords);
    if (lngIn) lngIn.addEventListener('change', syncFromManualCoords);

    syncQueryFromFields();
    updateCoordLabel();
    initInlineMap();
})();
</script>
