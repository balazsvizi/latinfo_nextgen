<?php
declare(strict_types=1);

/**
 * Tánciskola ↔ bulihelyszín kapcsolat (+ kínálat).
 *
 * @var int $locIndex
 * @var array<string, mixed> $location  (dance_school_venues sor + venue_* mezők)
 * @var list<array<string, mixed>> $offerings
 * @var array<int, string> $venueOptions  id => név
 * @var array<int, string> $styleOptions
 * @var array<string, string> $ageLabels
 * @var array<string, string> $levelLabels
 * @var array<string, string> $classTypeLabels
 */
$locIndex = (int) ($locIndex ?? 0);
$location = is_array($location ?? null) ? $location : [];
$offerings = is_array($offerings ?? null) ? $offerings : [];
$venueOptions = $venueOptions ?? [];
$styleOptions = $styleOptions ?? [];
$ageLabels = $ageLabels ?? dance_school_age_group_labels();
$levelLabels = $levelLabels ?? dance_school_level_labels();
$classTypeLabels = $classTypeLabels ?? dance_school_class_type_labels();
$prefix = 'venues[' . $locIndex . ']';
$venueId = (int) ($location['venue_id'] ?? 0);
$venueName = trim((string) ($location['venue_name'] ?? ''));
$venueCity = trim((string) ($location['venue_city'] ?? ''));
$venueAddress = trim((string) ($location['venue_address'] ?? ''));
$venueMaps = trim((string) ($location['venue_google_maps_url'] ?? ''));
$venueMeta = [];
if ($venueCity !== '') {
    $venueMeta[] = $venueCity;
}
if ($venueAddress !== '') {
    $venueMeta[] = $venueAddress;
}
$editVenueUrl = $venueId > 0 ? events_url('venue_szerkeszt.php?id=') . $venueId : '';
?>
<div class="events-edit-panel dance-school-location-row" data-dance-location-row data-loc-index="<?= (int) $locIndex ?>">
    <div class="events-list-head" style="margin-bottom:0.75rem;">
        <h4 class="events-edit-panel__title" style="margin:0;">Helyszín #<?= (int) $locIndex + 1 ?></h4>
        <button type="button" class="btn btn-secondary btn-sm" data-dance-location-remove>Kapcsolat törlése</button>
    </div>
    <input type="hidden" name="<?= h($prefix) ?>[id]" value="<?= (int) ($location['id'] ?? 0) ?>">
    <div class="events-tag-dj-profile__grid">
        <div class="form-group">
            <label>Bulihelyszín *</label>
            <select name="<?= h($prefix) ?>[venue_id]">
                <option value="0">— válassz a listából —</option>
                <?php foreach ($venueOptions as $vid => $vname): ?>
                    <option value="<?= (int) $vid ?>"<?= $venueId === (int) $vid ? ' selected' : '' ?>><?= h((string) $vname) ?></option>
                <?php endforeach; ?>
                <?php if ($venueId > 0 && !isset($venueOptions[$venueId]) && $venueName !== ''): ?>
                    <option value="<?= (int) $venueId ?>" selected><?= h($venueName) ?> (nem a listában)</option>
                <?php endif; ?>
            </select>
            <p class="help" style="margin:0.35rem 0 0;">
                A helyszín adatai a <a href="<?= h(events_url('venues.php')) ?>" target="_blank" rel="noopener">bulihelyszíneknél</a> szerkeszthetők.
                <?php if ($editVenueUrl !== ''): ?>
                    · <a href="<?= h($editVenueUrl) ?>" target="_blank" rel="noopener">Megnyitás szerkesztésre</a>
                <?php endif; ?>
                · <a href="<?= h(events_url('venue_letrehoz.php')) ?>" target="_blank" rel="noopener">Új helyszín</a>
            </p>
            <?php if ($venueMeta !== [] || $venueMaps !== ''): ?>
                <p class="help muted" style="margin:0.25rem 0 0;">
                    <?= h(implode(' · ', $venueMeta)) ?>
                    <?php if ($venueMaps !== ''): ?>
                        · <?= events_url_open_button($venueMaps, 'Térkép') ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>Iskola-megjegyzés ehhez a helyszínhez</label>
            <textarea name="<?= h($prefix) ?>[notes]" rows="2" placeholder="pl. keddi órák, terem B…"><?= h((string) ($location['notes'] ?? '')) ?></textarea>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="<?= h($prefix) ?>[is_active]" value="1"<?= !empty($location['is_active']) || !isset($location['is_active']) ? ' checked' : '' ?>> Aktív kapcsolat</label>
        </div>
    </div>

    <div class="dance-school-offerings" data-dance-offerings>
        <h5 style="margin:1rem 0 0.5rem;">Kínálat / órák ezen a helyszínen</h5>
        <div data-dance-offerings-list>
            <?php
            if ($offerings === []) {
                $offIndex = 0;
                $offering = [];
                require __DIR__ . '/dance_school_offering_row.php';
            } else {
                foreach ($offerings as $offIndex => $offering) {
                    require __DIR__ . '/dance_school_offering_row.php';
                }
            }
            ?>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" data-dance-offering-add>+ Kínálat hozzáadása</button>
    </div>
</div>
