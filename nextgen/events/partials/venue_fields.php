<?php
declare(strict_types=1);

/** @var array<string, string> $v űrlap mezők */
/** @var array<int, string> $venuesLinkOptions kapcsolt helyszín választó (id => név) */
/** @var int $venueEditId szerkesztés: slug AJAX exclude (0 = új) */
/** @var string|null $venuePublicUrl nyilvános előnézet link */

if (!isset($venuesLinkOptions) || !is_array($venuesLinkOptions)) {
    $venuesLinkOptions = [];
}
$venueEditId = (int) ($venueEditId ?? 0);
$venuePublicUrl = isset($venuePublicUrl) && $venuePublicUrl !== '' ? (string) $venuePublicUrl : null;
$hasCoords = ($v['latitude'] ?? '') !== '' && ($v['longitude'] ?? '') !== '';
$venueFormShowGeocode = $venueEditId > 0
    && !$hasCoords
    && events_venue_geocode_query([
        'address' => (string) ($v['address'] ?? ''),
        'city' => (string) ($v['city'] ?? ''),
        'postal_code' => (string) ($v['postal_code'] ?? ''),
        'country' => (string) ($v['country'] ?? ''),
    ]) !== '';
?>
<div class="events-edit-layout venue-edit-layout">
    <div class="events-edit-main">
        <div class="events-edit-panel events-edit-panel--tone-title">
            <h3 class="events-edit-panel__title">Alapadatok</h3>
            <div class="events-edit-title-row venue-edit-title-row">
                <div class="form-group venue-edit-name-field">
                    <label for="venue_name">Név *</label>
                    <input type="text" id="venue_name" name="name" value="<?= h($v['name']) ?>" required maxlength="500" placeholder="Helyszín neve…">
                </div>
                <button type="button" class="btn btn-secondary events-edit-slug-refresh" id="venue-slug-refresh" title="Slug frissítése a névből" aria-label="Slug frissítése a névből">🔄</button>
                <div class="form-group venue-edit-slug-field">
                    <label for="venue_slug">Slug (URL)</label>
                    <input type="text" id="venue_slug" name="slug" value="<?= h($v['slug']) ?>" maxlength="255" pattern="[a-z0-9\-]*" title="Kisbetű, szám és kötőjel" placeholder="url-slug">
                </div>
                <?php if ($venuePublicUrl !== null): ?>
                    <a href="<?= h($venuePublicUrl) ?>" class="events-icon-action events-edit-preview-action" title="Nyilvános megtekintés (új lap)" aria-label="Nyilvános megtekintés új lapon" target="_blank" rel="noopener">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="events-edit-panel events-edit-panel--tone-venue">
            <h3 class="events-edit-panel__title">Cím és térkép</h3>
            <div class="venue-edit-address-grid">
                <div class="form-group">
                    <label for="venue_country">Ország</label>
                    <input type="text" id="venue_country" name="country" value="<?= h($v['country']) ?>" maxlength="120" placeholder="<?= h(events_venue_default_country()) ?>">
                </div>
                <div class="form-group">
                    <label for="venue_city">Település</label>
                    <input type="text" id="venue_city" name="city" value="<?= h($v['city']) ?>" maxlength="255" placeholder="Budapest">
                </div>
                <div class="form-group">
                    <label for="venue_postal_code">IRSZ</label>
                    <input type="text" id="venue_postal_code" name="postal_code" value="<?= h($v['postal_code']) ?>" maxlength="16" inputmode="numeric" autocomplete="postal-code">
                </div>
                <div class="form-group venue-edit-address-field">
                    <label for="venue_address">Cím (utca, házszám)</label>
                    <textarea id="venue_address" name="address" rows="3" placeholder="Példa utca 1."><?= h($v['address']) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="venue_latitude">Szélesség (WGS-84)</label>
                    <input type="text" id="venue_latitude" name="latitude" inputmode="decimal" autocomplete="off" value="<?= h($v['latitude']) ?>" placeholder="47.4979">
                </div>
                <div class="form-group">
                    <label for="venue_longitude">Hosszúság (WGS-84)</label>
                    <input type="text" id="venue_longitude" name="longitude" inputmode="decimal" autocomplete="off" value="<?= h($v['longitude']) ?>" placeholder="19.0402">
                </div>
            </div>
            <div class="venue-edit-map-panel">
                <p class="venue-edit-map-panel__lead">Cím alapján keresés (Nominatim), majd kattintás vagy jelölő húzása a térképen. A koordináták automatikusan a mezőkbe kerülnek.</p>
                <div class="form-group venue-edit-map-panel__query">
                    <label for="events-venue-map-query">Keresendő cím</label>
                    <textarea id="events-venue-map-query" rows="2" maxlength="500" autocomplete="street-address" placeholder="A cím mezőkből indul; itt szabadon módosíthatod."></textarea>
                </div>
                <div class="venue-edit-map-actions">
                    <button type="button" class="btn btn-secondary btn-sm" id="events-venue-map-suggest">Javaslat a cím alapján</button>
                    <button type="button" class="btn btn-secondary btn-sm events-venue-map-open" id="venue-map-open">Nagyítás</button>
                    <span id="venue-edit-map-status" class="venue-edit-map-status<?= $hasCoords ? '' : ' venue-edit-map-status--muted' ?>"><?= $hasCoords ? 'GPS beállítva' : 'Nincs GPS — jelöld meg a térképen vagy kérj javaslatot' ?></span>
                    <span id="events-venue-map-coords" class="events-venue-map-dialog__coords" aria-live="polite"></span>
                </div>
                <p id="venue-inline-map-msg" class="venue-edit-map-panel__msg" role="alert" hidden></p>
                <div id="venue-inline-map-host" class="venue-edit-inline-map-host" role="region" aria-label="Helyszín megjelölése a térképen"></div>
            </div>
        </div>

        <div class="events-edit-panel events-edit-panel--tone-url">
            <h3 class="events-edit-panel__title">Web</h3>
            <div class="form-group">
                <label for="venue_website_url">Weboldal</label>
                <div class="events-url-open-row">
                    <input type="url" id="venue_website_url" name="website_url" value="<?= h($v['website_url']) ?>" maxlength="2000" placeholder="https://" autocomplete="url">
                    <?php if (($v['website_url'] ?? '') !== ''): ?>
                        <a class="btn btn-secondary events-url-open-btn" href="<?= h($v['website_url']) ?>" target="_blank" rel="noopener noreferrer">Megnyitás</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-group">
                <label for="venue_google_maps_url">Google Maps</label>
                <div class="events-url-open-row">
                    <input type="url" id="venue_google_maps_url" name="google_maps_url" value="<?= h($v['google_maps_url']) ?>" maxlength="2000" placeholder="https://maps.google.com/… vagy https://maps.app.goo.gl/…" autocomplete="url">
                    <?php if (($v['google_maps_url'] ?? '') !== ''): ?>
                        <a class="btn btn-secondary events-url-open-btn" href="<?= h($v['google_maps_url']) ?>" target="_blank" rel="noopener noreferrer">Megnyitás</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="events-edit-panel events-edit-panel--tone-content">
            <h3 class="events-edit-panel__title">Leírás</h3>
            <div class="form-group">
                <label class="visually-hidden" for="venue_description">Leírás</label>
                <textarea id="venue_description" name="description" class="js-html-editor-source" rows="12"><?= h($v['description']) ?></textarea>
            </div>
        </div>
    </div>

    <aside class="events-edit-sidebar venue-edit-sidebar">
        <?php
        $venueFormActionsPlacement = 'sidebar';
        require __DIR__ . '/venue_form_actions.php';
        ?>
        <?php if ($venuesLinkOptions !== []): ?>
            <div class="events-edit-panel events-edit-panel--tone-url">
                <h3 class="events-edit-panel__title">Kapcsolt helyszín</h3>
                <div class="form-group">
                    <label for="venue_linked_venue_id">A helyszín új neve</label>
                    <select id="venue_linked_venue_id" name="linked_venue_id">
                        <option value="">— nincs —</option>
                        <?php foreach ($venuesLinkOptions as $vid => $vlabel): ?>
                            <option value="<?= (int) $vid ?>" <?= ((string) (int) $vid === (string) ($v['linked_venue_id'] ?? '')) ? 'selected' : '' ?>><?= h($vlabel) ?> (<?= (int) $vid ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($venuePublicUrl !== null): ?>
            <div class="events-edit-panel events-edit-panel--tone-publish">
                <h3 class="events-edit-panel__title">Nyilvános oldal</h3>
                <p class="help venue-edit-public-link"><a href="<?= h($venuePublicUrl) ?>" target="_blank" rel="noopener"><?= h($venuePublicUrl) ?></a></p>
            </div>
        <?php endif; ?>
    </aside>
</div>
<script>
(function () {
    var nameEl = document.getElementById('venue_name');
    var slugEl = document.getElementById('venue_slug');
    var refreshBtn = document.getElementById('venue-slug-refresh');
    if (!nameEl || !slugEl) return;
    var ajaxPath = <?= json_encode(events_url('ajax_venue_unique_slug.php'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var excludeId = <?= (int) $venueEditId ?>;

    function fetchSlugFromName(force) {
        if (!force && slugEl.value.trim() !== '') return;
        var nm = nameEl.value.trim();
        if (nm === '') return;
        var u = new URL(ajaxPath, window.location.href);
        u.searchParams.set('name', nm);
        if (excludeId > 0) u.searchParams.set('exclude_id', String(excludeId));
        fetch(u.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!force && slugEl.value.trim() !== '') return;
                if (data && data.ok && typeof data.slug === 'string') slugEl.value = data.slug;
            })
            .catch(function () {});
    }

    nameEl.addEventListener('blur', function () { fetchSlugFromName(false); });
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () { fetchSlugFromName(true); });
    }
})();
</script>
